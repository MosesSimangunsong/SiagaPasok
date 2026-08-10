<?php

namespace App\Http\Controllers\Kdkmp;

use App\Enums\FallbackRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kdkmp\RejectFallbackRequestRequest;
use App\Models\FallbackRequest;
use App\Models\User;
use App\Services\Fallback\FallbackRequestService;
use App\Services\Supply\SupplyMetricsService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FallbackRequestApprovalController extends Controller
{
    public function __construct(
        private readonly FallbackRequestService $requestService,
        private readonly SupplyMetricsService $supplyMetrics,
    ) {
    }

    public function index(): Response
    {
        $user =
            request()->user();

        $this->assertManager(
            $user
        );

        $requests =
            FallbackRequest::query()
                ->where(
                    'requester_organization_id',
                    $user->organization_id
                )
                ->where(
                    'status',
                    FallbackRequestStatus
                        ::PENDING_APPROVAL
                        ->value
                )
                ->with([
                    'forecast.sppgOrganization',
                    'forecast.commodity',
                    'forecast.unit',
                    'unit',
                    'createdBy',
                    'submittedBy',
                ])
                ->orderBy(
                    'submitted_at'
                )
                ->orderBy('id')
                ->get()
                ->map(
                    fn (
                        FallbackRequest $fallbackRequest
                    ) =>
                        $this->serializeQueueItem(
                            $fallbackRequest
                        )
                )
                ->values();

        return Inertia::render(
            'Kdkmp/Manager/FallbackRequests/Index',
            [
                'requests' =>
                    $requests,
            ]
        );
    }

    public function show(
        FallbackRequest $fallbackRequest
    ): Response {
        $user =
            request()->user();

        $this->assertManager(
            $user
        );

        Gate::authorize(
            'viewRequester',
            $fallbackRequest
        );

        $fallbackRequest->load([
            'forecast.sppgOrganization',
            'forecast.commodity',
            'forecast.unit',

            'requesterOrganization',
            'unit',

            'createdBy',
            'submittedBy',
            'reviewedBy',
        ]);

        $metrics =
            $this->supplyMetrics
                ->calculate(
                    $fallbackRequest
                        ->forecast
                );

        return Inertia::render(
            'Kdkmp/Manager/FallbackRequests/Show',
            [
                'review' => [
                    ...$this->serializeQueueItem(
                        $fallbackRequest
                    ),

                    'requester_organization' => [
                        'id' =>
                            $fallbackRequest
                                ->requesterOrganization
                                ->id,

                        'code' =>
                            $fallbackRequest
                                ->requesterOrganization
                                ->code,

                        'name' =>
                            $fallbackRequest
                                ->requesterOrganization
                                ->name,
                    ],

                    'broadcast_note' =>
                        $fallbackRequest
                            ->broadcast_note,

                    /*
                     * Manager melihat current derived
                     * truth, bukan snapshot Shortfall
                     * dari saat draft dibuat.
                     */
                    'current_metrics' => [
                        'demand_target' =>
                            $metrics
                                ->demandTarget,

                        'total_safe_supply' =>
                            $metrics
                                ->totalSafeSupply,

                        'shortfall' =>
                            $metrics
                                ->shortfall,

                        'coverage_percent' =>
                            $metrics
                                ->coveragePercent,
                    ],
                ],

                'can' => [
                    'approve' =>
                        $fallbackRequest
                            ->isPendingApproval()
                        && $user->can(
                            'approveBroadcast',
                            $fallbackRequest
                        ),

                    'reject' =>
                        $fallbackRequest
                            ->isPendingApproval()
                        && $user->can(
                            'rejectBroadcast',
                            $fallbackRequest
                        ),
                ],
            ]
        );
    }

    public function approve(
        Request $request,
        FallbackRequest $fallbackRequest
    ): RedirectResponse {
        Gate::authorize(
            'approveBroadcast',
            $fallbackRequest
        );

        $this->requestService
            ->approveBroadcast(
                $request->user(),
                $fallbackRequest
            );

        return redirect()
            ->route(
                'kdkmp.manager.fallback-requests.index'
            )
            ->with(
                'success',
                'Fallback Request berhasil disetujui dan dibuka ke NETWORK.'
            );
    }

    public function reject(
        RejectFallbackRequestRequest $request,
        FallbackRequest $fallbackRequest
    ): RedirectResponse {
        $validated =
            $request->validated();

        $this->requestService
            ->rejectBroadcast(
                $request->user(),
                $fallbackRequest,
                $validated[
                    'review_reason'
                ]
            );

        return redirect()
            ->route(
                'kdkmp.manager.fallback-requests.index'
            )
            ->with(
                'success',
                'Fallback Request ditolak.'
            );
    }

    private function serializeQueueItem(
        FallbackRequest $fallbackRequest
    ): array {
        return [
            'id' =>
                $fallbackRequest->id,

            'forecast' => [
                'id' =>
                    $fallbackRequest
                        ->forecast
                        ->id,

                'forecast_code' =>
                    $fallbackRequest
                        ->forecast
                        ->forecast_code,

                'sppg_name' =>
                    $fallbackRequest
                        ->forecast
                        ->sppgOrganization
                        ->name,

                'commodity_name' =>
                    $fallbackRequest
                        ->forecast
                        ->commodity
                        ->name,

                'target_volume' =>
                    (string)
                    $fallbackRequest
                        ->forecast
                        ->target_volume,

                'required_start_at' =>
                    $fallbackRequest
                        ->forecast
                        ->required_start_at
                        ?->toIso8601String(),

                'required_end_at' =>
                    $fallbackRequest
                        ->forecast
                        ->required_end_at
                        ?->toIso8601String(),
            ],

            'requested_volume' =>
                (string)
                $fallbackRequest
                    ->requested_volume,

            'accepted_volume' =>
                $this->requestService
                    ->calculateAcceptedVolume(
                        $fallbackRequest
                    ),

            'remaining_volume' =>
                $this->requestService
                    ->calculateRemainingVolume(
                        $fallbackRequest
                    ),

            'unit' => [
                'id' =>
                    $fallbackRequest
                        ->unit
                        ->id,

                'name' =>
                    $fallbackRequest
                        ->unit
                        ->name,

                'symbol' =>
                    $fallbackRequest
                        ->unit
                        ->symbol,

                'decimal_precision' =>
                    $fallbackRequest
                        ->unit
                        ->decimal_precision,
            ],

            'response_deadline_at' =>
                $fallbackRequest
                    ->response_deadline_at
                    ?->toIso8601String(),

            'status' =>
                $fallbackRequest
                    ->status
                    ->value,

            'status_label' =>
                $fallbackRequest
                    ->status
                    ->label(),

            'created_by' =>
                $fallbackRequest
                    ->createdBy
                    ? [
                        'id' =>
                            $fallbackRequest
                                ->createdBy
                                ->id,

                        'name' =>
                            $fallbackRequest
                                ->createdBy
                                ->name,
                    ]
                    : null,

            'submitted_by' =>
                $fallbackRequest
                    ->submittedBy
                    ? [
                        'id' =>
                            $fallbackRequest
                                ->submittedBy
                                ->id,

                        'name' =>
                            $fallbackRequest
                                ->submittedBy
                                ->name,
                    ]
                    : null,

            'submitted_at' =>
                $fallbackRequest
                    ->submitted_at
                    ?->toIso8601String(),
        ];
    }

    private function assertManager(
        User $user
    ): void {
        if (
            ! $user->isKdkmpManager()
            || ! $user
                ->hasValidIdentityContext()
        ) {
            throw new AuthorizationException(
                'Hanya KDKMP Manager aktif yang dapat mengakses Fallback Request Approval Queue.'
            );
        }
    }
}