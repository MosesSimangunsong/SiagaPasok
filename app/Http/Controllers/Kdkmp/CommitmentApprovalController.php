<?php

namespace App\Http\Controllers\Kdkmp;

use App\Enums\CommitmentApprovalStatus;
use App\Enums\CommitmentLifecycleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kdkmp\ApproveCommitmentVersionRequest;
use App\Http\Requests\Kdkmp\RejectCommitmentVersionRequest;
use App\Models\CommitmentVersion;
use App\Models\SupplyCommitment;
use App\Models\User;
use App\Services\Commitment\CommitmentWorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CommitmentApprovalController extends Controller
{
    public function __construct(
        private readonly CommitmentWorkflowService $workflowService
    ) {
    }

    public function index(): Response
    {
        $user = request()->user();

        $this->assertManager($user);

        $versions =
            CommitmentVersion::query()
                ->where(
                    'approval_status',
                    CommitmentApprovalStatus
                        ::PENDING_APPROVAL
                        ->value
                )
                ->whereHas(
                    'commitment',
                    fn ($query) =>
                        $query
                            ->where(
                                'organization_id',
                                $user->organization_id
                            )
                            ->where(
                                'lifecycle_status',
                                CommitmentLifecycleStatus
                                    ::ACTIVE
                                    ->value
                            )
                )
                ->with([
                    'unit',
                    'createdBy',
                    'submittedBy',

                    'commitment.forecast.sppgOrganization',
                    'commitment.forecast.commodity',
                    'commitment.forecast.unit',

                    'commitment.producer',

                    'commitment.expectedHarvest.unit',

                    'commitment.activeVersion.unit',
                ])
                ->orderBy('submitted_at')
                ->orderBy('id')
                ->get()
                ->map(
                    fn (
                        CommitmentVersion $version
                    ) =>
                        $this->serializeQueueItem(
                            $version
                        )
                )
                ->values();

        return Inertia::render(
            'Kdkmp/Manager/Approvals/Index',
            [
                'versions' =>
                    $versions,
            ]
        );
    }

    public function show(
        SupplyCommitment $commitment,
        CommitmentVersion $version
    ): Response {
        $user = request()->user();

        $this->assertManager($user);

        $this->assertVersionBelongsToCommitment(
            $commitment,
            $version
        );

        Gate::authorize(
            'view',
            $version
        );

        $commitment->load([
            'organization',

            'forecast.sppgOrganization',
            'forecast.commodity',
            'forecast.unit',

            'producer',

            'expectedHarvest.commodity',
            'expectedHarvest.unit',

            'activeVersion.unit',
        ]);

        $version->load([
            'unit',
            'createdBy',
            'submittedBy',
            'reviewedBy',
        ]);

        return Inertia::render(
            'Kdkmp/Manager/Approvals/Show',
            [
                'review' =>
                    $this->serializeReview(
                        $commitment,
                        $version
                    ),

                'can' => [
                    'approve' =>
                        $user->can(
                            'approve',
                            $version
                        ),

                    'reject' =>
                        $user->can(
                            'reject',
                            $version
                        ),
                ],
            ]
        );
    }

    public function approve(
        ApproveCommitmentVersionRequest $request,
        SupplyCommitment $commitment,
        CommitmentVersion $version
    ): RedirectResponse {
        $this->assertVersionBelongsToCommitment(
            $commitment,
            $version
        );

        $this->workflowService->approve(
            $request->user(),
            $version
        );

        return redirect()
            ->route(
                'kdkmp.manager.approvals.index'
            )
            ->with(
                'success',
                'Komitmen pasokan berhasil disetujui.'
            );
    }

    public function reject(
        RejectCommitmentVersionRequest $request,
        SupplyCommitment $commitment,
        CommitmentVersion $version
    ): RedirectResponse {
        $this->assertVersionBelongsToCommitment(
            $commitment,
            $version
        );

        $validated =
            $request->validated();

        $this->workflowService->reject(
            $request->user(),
            $version,
            $validated[
                'review_reason'
            ]
        );

        return redirect()
            ->route(
                'kdkmp.manager.approvals.index'
            )
            ->with(
                'success',
                'Komitmen pasokan ditolak.'
            );
    }

    private function serializeQueueItem(
        CommitmentVersion $version
    ): array {
        $commitment =
            $version->commitment;

        return [
            'id' =>
                $version->id,

            'commitment_id' =>
                $commitment->id,

            'review_type' =>
                $commitment
                    ->active_version_id === null
                    ? 'INITIAL'
                    : 'REVISION',

            'review_type_label' =>
                $commitment
                    ->active_version_id === null
                    ? 'Persetujuan Awal'
                    : 'Revisi Komitmen',

            'version_no' =>
                $version->version_no,

            'forecast' => [
                'id' =>
                    $commitment
                        ->forecast
                        ->id,

                'forecast_code' =>
                    $commitment
                        ->forecast
                        ->forecast_code,

                'sppg_name' =>
                    $commitment
                        ->forecast
                        ->sppgOrganization
                        ->name,

                'commodity_name' =>
                    $commitment
                        ->forecast
                        ->commodity
                        ->name,

                'required_start_at' =>
                    $commitment
                        ->forecast
                        ->required_start_at
                        ?->toIso8601String(),

                'required_end_at' =>
                    $commitment
                        ->forecast
                        ->required_end_at
                        ?->toIso8601String(),
            ],

            'producer' => [
                'id' =>
                    $commitment
                        ->producer
                        ->id,

                'producer_code' =>
                    $commitment
                        ->producer
                        ->producer_code,

                'name' =>
                    $commitment
                        ->producer
                        ->name,
            ],

            'min_volume' =>
                (string)
                $version->min_volume,

            'max_volume' =>
                (string)
                $version->max_volume,

            'unit' => [
                'symbol' =>
                    $version
                        ->unit
                        ->symbol,

                'decimal_precision' =>
                    $version
                        ->unit
                        ->decimal_precision,
            ],

            'current_confidence' =>
                $commitment
                    ->current_confidence
                    ?->value,

            'submitted_by' =>
                $version
                    ->submittedBy
                    ? [
                        'id' =>
                            $version
                                ->submittedBy
                                ->id,

                        'name' =>
                            $version
                                ->submittedBy
                                ->name,
                    ]
                    : null,

            'submitted_at' =>
                $version
                    ->submitted_at
                    ?->toIso8601String(),
        ];
    }

    private function serializeReview(
        SupplyCommitment $commitment,
        CommitmentVersion $version
    ): array {
        return [
            'commitment' => [
                'id' =>
                    $commitment->id,

                'organization' => [
                    'id' =>
                        $commitment
                            ->organization
                            ->id,

                    'code' =>
                        $commitment
                            ->organization
                            ->code,

                    'name' =>
                        $commitment
                            ->organization
                            ->name,
                ],

                'lifecycle_status' =>
                    $commitment
                        ->lifecycle_status
                        ->value,

                'current_confidence' =>
                    $commitment
                        ->current_confidence
                        ?->value,

                'current_confidence_label' =>
                    $commitment
                        ->current_confidence
                        ?->label(),

                'forecast' => [
                    'id' =>
                        $commitment
                            ->forecast
                            ->id,

                    'forecast_code' =>
                        $commitment
                            ->forecast
                            ->forecast_code,

                    'sppg' => [
                        'id' =>
                            $commitment
                                ->forecast
                                ->sppgOrganization
                                ->id,

                        'code' =>
                            $commitment
                                ->forecast
                                ->sppgOrganization
                                ->code,

                        'name' =>
                            $commitment
                                ->forecast
                                ->sppgOrganization
                                ->name,
                    ],

                    'commodity' => [
                        'id' =>
                            $commitment
                                ->forecast
                                ->commodity
                                ->id,

                        'code' =>
                            $commitment
                                ->forecast
                                ->commodity
                                ->code,

                        'name' =>
                            $commitment
                                ->forecast
                                ->commodity
                                ->name,
                    ],

                    'target_volume' =>
                        (string)
                        $commitment
                            ->forecast
                            ->target_volume,

                    'unit' => [
                        'id' =>
                            $commitment
                                ->forecast
                                ->unit
                                ->id,

                        'name' =>
                            $commitment
                                ->forecast
                                ->unit
                                ->name,

                        'symbol' =>
                            $commitment
                                ->forecast
                                ->unit
                                ->symbol,
                    ],

                    'required_start_at' =>
                        $commitment
                            ->forecast
                            ->required_start_at
                            ?->toIso8601String(),

                    'required_end_at' =>
                        $commitment
                            ->forecast
                            ->required_end_at
                            ?->toIso8601String(),
                ],

                'producer' => [
                    'id' =>
                        $commitment
                            ->producer
                            ->id,

                    'producer_code' =>
                        $commitment
                            ->producer
                            ->producer_code,

                    'name' =>
                        $commitment
                            ->producer
                            ->name,

                    'village' =>
                        $commitment
                            ->producer
                            ->village,

                    'district' =>
                        $commitment
                            ->producer
                            ->district,

                    'is_active' =>
                        (bool)
                        $commitment
                            ->producer
                            ->is_active,
                ],

                'expected_harvest' =>
                    $commitment
                        ->expectedHarvest
                        ? [
                            'id' =>
                                $commitment
                                    ->expectedHarvest
                                    ->id,

                            'expected_min_volume' =>
                                (string)
                                $commitment
                                    ->expectedHarvest
                                    ->expected_min_volume,

                            'expected_max_volume' =>
                                (string)
                                $commitment
                                    ->expectedHarvest
                                    ->expected_max_volume,

                            'harvest_start_at' =>
                                $commitment
                                    ->expectedHarvest
                                    ->harvest_start_at
                                    ?->toIso8601String(),

                            'harvest_end_at' =>
                                $commitment
                                    ->expectedHarvest
                                    ->harvest_end_at
                                    ?->toIso8601String(),

                            'unit' => [
                                'symbol' =>
                                    $commitment
                                        ->expectedHarvest
                                        ->unit
                                        ->symbol,
                            ],
                        ]
                        : null,
            ],

            'version' =>
                $this->serializeVersion(
                    $version
                ),

            'active_version' =>
                $commitment
                    ->activeVersion
                    ? $this->serializeVersion(
                        $commitment
                            ->activeVersion
                    )
                    : null,

            /*
             * UI menggunakan flag ini hanya
             * untuk label/context.
             *
             * Tidak membuat state database baru.
             */
            'is_revision' =>
                $commitment
                    ->active_version_id !== null,
        ];
    }

    private function serializeVersion(
        CommitmentVersion $version
    ): array {
        return [
            'id' =>
                $version->id,

            'version_no' =>
                $version->version_no,

            'min_volume' =>
                (string)
                $version->min_volume,

            'max_volume' =>
                (string)
                $version->max_volume,

            'unit' => [
                'id' =>
                    $version
                        ->unit
                        ->id,

                'name' =>
                    $version
                        ->unit
                        ->name,

                'symbol' =>
                    $version
                        ->unit
                        ->symbol,

                'decimal_precision' =>
                    $version
                        ->unit
                        ->decimal_precision,
            ],

            'availability_start_at' =>
                $version
                    ->availability_start_at
                    ?->toIso8601String(),

            'availability_end_at' =>
                $version
                    ->availability_end_at
                    ?->toIso8601String(),

            'notes' =>
                $version->notes,

            'change_reason' =>
                $version
                    ->change_reason,

            'operator_justification' =>
                $version
                    ->operator_justification,

            'approval_status' =>
                $version
                    ->approval_status
                    ->value,

            'created_by' =>
                $version
                    ->createdBy
                    ? [
                        'id' =>
                            $version
                                ->createdBy
                                ->id,

                        'name' =>
                            $version
                                ->createdBy
                                ->name,
                    ]
                    : null,

            'submitted_by' =>
                $version
                    ->submittedBy
                    ? [
                        'id' =>
                            $version
                                ->submittedBy
                                ->id,

                        'name' =>
                            $version
                                ->submittedBy
                                ->name,
                    ]
                    : null,

            'submitted_at' =>
                $version
                    ->submitted_at
                    ?->toIso8601String(),

            'reviewed_by' =>
                $version
                    ->reviewedBy
                    ? [
                        'id' =>
                            $version
                                ->reviewedBy
                                ->id,

                        'name' =>
                            $version
                                ->reviewedBy
                                ->name,
                    ]
                    : null,

            'reviewed_at' =>
                $version
                    ->reviewed_at
                    ?->toIso8601String(),

            'review_reason' =>
                $version
                    ->review_reason,
        ];
    }

    private function assertVersionBelongsToCommitment(
        SupplyCommitment $commitment,
        CommitmentVersion $version
    ): void {
        abort_if(
            $version->commitment_id
            !== $commitment->id,
            404
        );
    }

    private function assertManager(
        User $user
    ): void {
        if (
            ! $user->isKdkmpManager()
            || ! $user->hasValidIdentityContext()
        ) {
            throw new AuthorizationException(
                'Hanya KDKMP Manager aktif yang dapat mengakses Approval Queue.'
            );
        }
    }
}