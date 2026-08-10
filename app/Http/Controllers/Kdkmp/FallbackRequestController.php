<?php

namespace App\Http\Controllers\Kdkmp;

use App\Enums\ForecastStatus;
use App\Enums\NetworkRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kdkmp\StoreFallbackRequestRequest;
use App\Models\DemandForecast;
use App\Models\FallbackRequest;
use App\Models\SupplyNetworkLink;
use App\Services\Fallback\FallbackRequestService;
use App\Services\Supply\SupplyMetricsService;
use App\Support\FixedScaleDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FallbackRequestController extends Controller
{
    public function __construct(
        private readonly FallbackRequestService $requestService,
        private readonly SupplyMetricsService $supplyMetrics,
    ) {
    }

    public function index(): Response
    {
        Gate::authorize(
            'viewAny',
            FallbackRequest::class
        );

        $user =
            request()->user();

        /*
         * Ini requester workspace.
         *
         * NETWORK broadcast akan mempunyai
         * controller terpisah pada M07.6A3.
         */
        $requests =
            FallbackRequest::query()
                ->where(
                    'requester_organization_id',
                    $user->organization_id
                )
                ->with([
                    'forecast.sppgOrganization',
                    'forecast.commodity',
                    'forecast.unit',
                    'unit',
                ])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get()
                ->map(
                    fn (
                        FallbackRequest $fallbackRequest
                    ) =>
                        $this->serializeListItem(
                            $fallbackRequest
                        )
                )
                ->values();

        return Inertia::render(
            'Kdkmp/FallbackRequests/Index',
            [
                'requests' =>
                    $requests,

                'canCreate' =>
                    $user->can(
                        'create',
                        FallbackRequest::class
                    ),
            ]
        );
    }

    public function create(): Response
    {
        Gate::authorize(
            'create',
            FallbackRequest::class
        );

        $user =
            request()->user();

        $forecasts =
            $this->requestableForecastOptions(
                $user->organization_id
            );

        $selectedForecastId =
            request()->integer(
                'forecast_id'
            );

        if (
            $selectedForecastId
            && ! $forecasts->contains(
                fn ($forecast) =>
                    $forecast['id']
                    === $selectedForecastId
            )
        ) {
            $selectedForecastId =
                null;
        }

        return Inertia::render(
            'Kdkmp/FallbackRequests/Create',
            [
                'forecasts' =>
                    $forecasts,

                'selectedForecastId' =>
                    $selectedForecastId,
            ]
        );
    }

    public function store(
        StoreFallbackRequestRequest $request
    ): RedirectResponse {
        $validated =
            $request->validated();

        $forecast =
            DemandForecast::query()
                ->findOrFail(
                    $validated['forecast_id']
                );

        unset(
            $validated['forecast_id']
        );

        $fallbackRequest =
            $this->requestService
                ->createDraft(
                    $request->user(),
                    $forecast,
                    $validated
                );

        return redirect()
            ->route(
                'kdkmp.fallback-requests.show',
                $fallbackRequest
            )
            ->with(
                'success',
                'Draft Fallback Request berhasil dibuat.'
            );
    }

    public function show(
        FallbackRequest $fallbackRequest
    ): Response {
        /*
         * Requester detail memakai ability khusus.
         *
         * NETWORK supplier tidak boleh memakai
         * endpoint ini walaupun mempunyai
         * broadcast read permission.
         */
        Gate::authorize(
            'viewRequester',
            $fallbackRequest
        );

        $user =
            request()->user();

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

        return Inertia::render(
            'Kdkmp/FallbackRequests/Show',
            [
                'fallbackRequest' =>
                    $this->serializeDetail(
                        $fallbackRequest
                    ),

                'can' => [
                    'submit' =>
                        $fallbackRequest
                            ->isDraft()
                        && $user->can(
                            'submit',
                            $fallbackRequest
                        ),

                    'cancel' =>
                        (
                            $fallbackRequest
                                ->isDraft()
                            || $fallbackRequest
                                ->isOpen()
                        )
                        && $user->can(
                            'cancel',
                            $fallbackRequest
                        ),
                ],
            ]
        );
    }

    private function requestableForecastOptions(
        int $organizationId
    ) {
        $sppgIds =
            SupplyNetworkLink::query()
                ->where(
                    'kdkmp_organization_id',
                    $organizationId
                )
                ->where(
                    'network_role',
                    NetworkRole::PRIMARY
                        ->value
                )
                ->where(
                    'is_active',
                    true
                )
                ->whereHas(
                    'sppgOrganization',
                    fn ($query) =>
                        $query->where(
                            'is_active',
                            true
                        )
                )
                ->pluck(
                    'sppg_organization_id'
                );

        return DemandForecast::query()
            ->whereIn(
                'sppg_organization_id',
                $sppgIds
            )
            ->where(
                'status',
                ForecastStatus::PUBLISHED
                    ->value
            )
            ->with([
                'sppgOrganization',
                'commodity',
                'unit',
            ])
            ->orderBy(
                'required_start_at'
            )
            ->orderBy('id')
            ->get()
            ->map(
                function (
                    DemandForecast $forecast
                ): ?array {
                    $metrics =
                        $this->supplyMetrics
                            ->calculate(
                                $forecast
                            );

                    /*
                     * Form hanya menampilkan Forecast
                     * yang saat ini benar-benar
                     * mempunyai Shortfall.
                     *
                     * Service tetap melakukan
                     * revalidation saat create/submit.
                     */
                    if (
                        FixedScaleDecimal::from(
                            $metrics->shortfall
                        )->isZero()
                    ) {
                        return null;
                    }

                    return [
                        'id' =>
                            $forecast->id,

                        'forecast_code' =>
                            $forecast
                                ->forecast_code,

                        'commodity' => [
                            'id' =>
                                $forecast
                                    ->commodity
                                    ->id,

                            'code' =>
                                $forecast
                                    ->commodity
                                    ->code,

                            'name' =>
                                $forecast
                                    ->commodity
                                    ->name,
                        ],

                        'sppg' => [
                            'id' =>
                                $forecast
                                    ->sppgOrganization
                                    ->id,

                            'code' =>
                                $forecast
                                    ->sppgOrganization
                                    ->code,

                            'name' =>
                                $forecast
                                    ->sppgOrganization
                                    ->name,
                        ],

                        'target_volume' =>
                            (string)
                            $forecast
                                ->target_volume,

                        'current_safe_supply' =>
                            $metrics
                                ->totalSafeSupply,

                        'current_shortfall' =>
                            $metrics->shortfall,

                        'coverage_percent' =>
                            $metrics
                                ->coveragePercent,

                        'unit' => [
                            'id' =>
                                $forecast
                                    ->unit
                                    ->id,

                            'name' =>
                                $forecast
                                    ->unit
                                    ->name,

                            'symbol' =>
                                $forecast
                                    ->unit
                                    ->symbol,

                            'decimal_precision' =>
                                $forecast
                                    ->unit
                                    ->decimal_precision,
                        ],

                        'required_start_at' =>
                            $forecast
                                ->required_start_at
                                ?->toIso8601String(),

                        'required_end_at' =>
                            $forecast
                                ->required_end_at
                                ?->toIso8601String(),
                    ];
                }
            )
            ->filter()
            ->values();
    }

    private function serializeListItem(
        FallbackRequest $fallbackRequest
    ): array {
        return [
            'id' =>
                $fallbackRequest->id,

            'forecast' =>
                $this->serializeForecast(
                    $fallbackRequest->forecast
                ),

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

            'created_at' =>
                $fallbackRequest
                    ->created_at
                    ?->toIso8601String(),
        ];
    }

    private function serializeDetail(
        FallbackRequest $fallbackRequest
    ): array {
        return [
            ...$this->serializeListItem(
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

            'reviewed_by' =>
                $fallbackRequest
                    ->reviewedBy
                    ? [
                        'id' =>
                            $fallbackRequest
                                ->reviewedBy
                                ->id,

                        'name' =>
                            $fallbackRequest
                                ->reviewedBy
                                ->name,
                    ]
                    : null,

            'reviewed_at' =>
                $fallbackRequest
                    ->reviewed_at
                    ?->toIso8601String(),

            'review_reason' =>
                $fallbackRequest
                    ->review_reason,

            'opened_at' =>
                $fallbackRequest
                    ->opened_at
                    ?->toIso8601String(),

            'fulfilled_at' =>
                $fallbackRequest
                    ->fulfilled_at
                    ?->toIso8601String(),

            'cancelled_at' =>
                $fallbackRequest
                    ->cancelled_at
                    ?->toIso8601String(),

            'cancellation_reason' =>
                $fallbackRequest
                    ->cancellation_reason,

            'expired_at' =>
                $fallbackRequest
                    ->expired_at
                    ?->toIso8601String(),
        ];
    }

    private function serializeForecast(
        DemandForecast $forecast
    ): array {
        return [
            'id' =>
                $forecast->id,

            'forecast_code' =>
                $forecast->forecast_code,

            'commodity' => [
                'id' =>
                    $forecast
                        ->commodity
                        ->id,

                'code' =>
                    $forecast
                        ->commodity
                        ->code,

                'name' =>
                    $forecast
                        ->commodity
                        ->name,
            ],

            'sppg' => [
                'id' =>
                    $forecast
                        ->sppgOrganization
                        ->id,

                'code' =>
                    $forecast
                        ->sppgOrganization
                        ->code,

                'name' =>
                    $forecast
                        ->sppgOrganization
                        ->name,
            ],

            'target_volume' =>
                (string)
                $forecast
                    ->target_volume,

            'required_start_at' =>
                $forecast
                    ->required_start_at
                    ?->toIso8601String(),

            'required_end_at' =>
                $forecast
                    ->required_end_at
                    ?->toIso8601String(),
        ];
    }
}