<?php

namespace App\Http\Controllers\Kdkmp;

use App\Enums\ReadinessApprovalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kdkmp\ApproveReadinessChecklistRequest;
use App\Http\Requests\Kdkmp\RejectReadinessChecklistRequest;
use App\Models\ReadinessChecklist;
use App\Models\ReadinessItem;
use App\Models\User;
use App\Services\Readiness\ReadinessChecklistReviewService;
use App\Services\Readiness\ReadinessEvaluationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReadinessApprovalController extends Controller
{
    public function __construct(
        private readonly ReadinessChecklistReviewService $reviewService,
        private readonly ReadinessEvaluationService $evaluationService,
    ) {
    }

    public function index(): Response
    {
        $user =
            request()->user();

        $this->assertManager(
            $user
        );

        $checklists =
            ReadinessChecklist::query()
                ->where(
                    'organization_id',
                    $user->organization_id
                )
                ->where(
                    'status',
                    ReadinessApprovalStatus
                        ::PENDING_APPROVAL
                        ->value
                )
                ->where(
                    'is_current_version',
                    true
                )
                ->with([
                    'forecast.sppgOrganization',
                    'forecast.commodity',
                    'forecast.unit',
                    'submittedBy',
                ])
                ->orderBy('submitted_at')
                ->orderBy('id')
                ->get()
                ->map(
                    fn (
                        ReadinessChecklist $checklist
                    ): array => [
                        'id' =>
                            $checklist->id,

                        'readiness_type' =>
                            $checklist
                                ->readiness_type
                                ->value,

                        'version_no' =>
                            $checklist
                                ->version_no,

                        'forecast_version' =>
                            $checklist
                                ->forecast_version,

                        'forecast' => [
                            'id' =>
                                $checklist
                                    ->forecast
                                    ->id,

                            'forecast_code' =>
                                $checklist
                                    ->forecast
                                    ->forecast_code,

                            'sppg_name' =>
                                $checklist
                                    ->forecast
                                    ->sppgOrganization
                                    ->name,

                            'commodity_name' =>
                                $checklist
                                    ->forecast
                                    ->commodity
                                    ->name,

                            'required_start_at' =>
                                $checklist
                                    ->forecast
                                    ->required_start_at
                                    ?->toIso8601String(),

                            'required_end_at' =>
                                $checklist
                                    ->forecast
                                    ->required_end_at
                                    ?->toIso8601String(),
                        ],

                        'submitted_by' =>
                            $checklist
                                ->submittedBy
                                ? [
                                    'id' =>
                                        $checklist
                                            ->submittedBy
                                            ->id,

                                    'name' =>
                                        $checklist
                                            ->submittedBy
                                            ->name,
                                ]
                                : null,

                        'submitted_at' =>
                            $checklist
                                ->submitted_at
                                ?->toIso8601String(),
                    ]
                )
                ->values();

        return Inertia::render(
            'Kdkmp/Manager/Readiness/Index',
            [
                'checklists' =>
                    $checklists,
            ]
        );
    }

    public function show(
        ReadinessChecklist $checklist
    ): Response {
        $user =
            request()->user();

        $this->assertManager(
            $user
        );

        Gate::authorize(
            'view',
            $checklist
        );

        $checklist->load([
            'forecast.sppgOrganization',
            'forecast.commodity',
            'forecast.unit',
            'organization',
            'preparedBy',
            'submittedBy',
            'items.requirement',
            'items.documentRecord',
        ]);

        $evaluation =
            $this->evaluationService
                ->evaluateContributor(
                    $checklist->forecast,
                    $checklist->organization_id
                );

        return Inertia::render(
            'Kdkmp/Manager/Readiness/Show',
            [
                'review' => [
                    'id' =>
                        $checklist->id,

                    'readiness_type' =>
                        $checklist
                            ->readiness_type
                            ->value,

                    'version_no' =>
                        $checklist
                            ->version_no,

                    'forecast_version' =>
                        $checklist
                            ->forecast_version,

                    'status' =>
                        $checklist
                            ->status
                            ->value,
                    'prepared_by' =>
    $checklist
        ->preparedBy
        ? [
            'id' =>
                $checklist
                    ->preparedBy
                    ->id,

            'name' =>
                $checklist
                    ->preparedBy
                    ->name,
        ]
        : null,

'submitted_by' =>
    $checklist
        ->submittedBy
        ? [
            'id' =>
                $checklist
                    ->submittedBy
                    ->id,

            'name' =>
                $checklist
                    ->submittedBy
                    ->name,
        ]
        : null,

'submitted_at' =>
    $checklist
        ->submitted_at
        ?->toIso8601String(),

'review_reason' =>
    $checklist
        ->review_reason,

                    'organization' => [
                        'id' =>
                            $checklist
                                ->organization
                                ->id,

                        'code' =>
                            $checklist
                                ->organization
                                ->code,

                        'name' =>
                            $checklist
                                ->organization
                                ->name,
                    ],

                    'forecast' => [
                        'id' =>
                            $checklist
                                ->forecast
                                ->id,

                        'forecast_code' =>
                            $checklist
                                ->forecast
                                ->forecast_code,

                        'version' =>
                            $checklist
                                ->forecast
                                ->version,

                        'sppg_name' =>
                            $checklist
                                ->forecast
                                ->sppgOrganization
                                ->name,

                        'commodity_name' =>
                            $checklist
                                ->forecast
                                ->commodity
                                ->name,

                        'target_volume' =>
                            (string)
                            $checklist
                                ->forecast
                                ->target_volume,

                        'unit_symbol' =>
                            $checklist
                                ->forecast
                                ->unit
                                ->symbol,

                        'required_start_at' =>
                            $checklist
                                ->forecast
                                ->required_start_at
                                ?->toIso8601String(),

                        'required_end_at' =>
                            $checklist
                                ->forecast
                                ->required_end_at
                                ?->toIso8601String(),
                    ],

                    'items' =>
                        $checklist
                            ->items
                            ->map(
                                fn (
                                    ReadinessItem $item
                                ): array => [
                                    'id' =>
                                        $item->id,

                                    'requirement' => [
                                        'code' =>
                                            $item
                                                ->requirement
                                                ->requirement_code,

                                        'label' =>
                                            $item
                                                ->requirement
                                                ->label,

                                        'scope' =>
                                            $item
                                                ->requirement
                                                ->requirement_scope
                                                ->value,
                                    ],

                                    'is_required' =>
                                        (bool)
                                        $item
                                            ->is_required,

                                    'is_satisfied' =>
                                        (bool)
                                        $item
                                            ->is_satisfied,

                                    'note' =>
                                        $item->note,

                                    'value_json' =>
                                        $item
                                            ->value_json,

                                    'document_record' =>
                                        $item
                                            ->documentRecord
                                            ? [
                                                'id' =>
                                                    $item
                                                        ->documentRecord
                                                        ->id,

                                                'document_name' =>
                                                    $item
                                                        ->documentRecord
                                                        ->document_name,

                                                'reference_number' =>
                                                    $item
                                                        ->documentRecord
                                                        ->reference_number,

                                                'status' =>
                                                    $item
                                                        ->documentRecord
                                                        ->status
                                                        ->value,

                                                'revision_no' =>
                                                    $item
                                                        ->documentRecord
                                                        ->revision_no,

                                                'valid_from' =>
                                                    $item
                                                        ->documentRecord
                                                        ->valid_from
                                                        ?->toIso8601String(),

                                                'expires_at' =>
                                                    $item
                                                        ->documentRecord
                                                        ->expires_at
                                                        ?->toIso8601String(),
                                            ]
                                            : null,
                                ]
                            )
                            ->values(),

                    'current_readiness' => [
                        'logistics_ready' =>
                            $evaluation
                                ->logisticsReady,

                        'document_ready' =>
                            $evaluation
                                ->documentReady,
                    ],
                ],

                'can' => [
                    'approve' =>
                        $user->can(
                            'approve',
                            $checklist
                        ),

                    'reject' =>
                        $user->can(
                            'reject',
                            $checklist
                        ),
                ],
            ]
        );
    }

    public function approve(
        ApproveReadinessChecklistRequest $request,
        ReadinessChecklist $checklist
    ): RedirectResponse {
        $this->reviewService
            ->approve(
                $request->user(),
                $checklist
            );

        return redirect()
            ->route(
                'kdkmp.manager.readiness.index'
            )
            ->with(
                'success',
                'Readiness berhasil disetujui.'
            );
    }

    public function reject(
        RejectReadinessChecklistRequest $request,
        ReadinessChecklist $checklist
    ): RedirectResponse {
        $validated =
            $request->validated();

        $this->reviewService
            ->reject(
                $request->user(),
                $checklist,
                $validated[
                    'review_reason'
                ]
            );

        return redirect()
            ->route(
                'kdkmp.manager.readiness.index'
            )
            ->with(
                'success',
                'Readiness berhasil ditolak.'
            );
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
                'Hanya KDKMP Manager aktif yang dapat mengakses Readiness Approval Queue.'
            );
        }
    }
}