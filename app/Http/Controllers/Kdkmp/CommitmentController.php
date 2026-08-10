<?php

namespace App\Http\Controllers\Kdkmp;

use App\Enums\CommitmentApprovalStatus;
use App\Enums\CommitmentLifecycleStatus;
use App\Enums\ForecastStatus;
use App\Enums\NetworkRole;
use App\Enums\SupplyConfidence;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kdkmp\StoreSupplyCommitmentRequest;
use App\Models\CommitmentVersion;
use App\Models\DemandForecast;
use App\Models\ExpectedHarvest;
use App\Models\Producer;
use App\Models\SupplyCommitment;
use App\Models\SupplyNetworkLink;
use App\Services\Commitment\CommitmentWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CommitmentController extends Controller
{
    public function __construct(
        private readonly CommitmentWorkflowService $workflowService
    ) {
    }

    public function index(): Response
    {
        Gate::authorize(
            'viewAny',
            SupplyCommitment::class
        );

        $user = request()->user();

        $commitments =
            $this->commitmentQuery(
                $user->organization_id
            )
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get()
                ->map(
                    fn (SupplyCommitment $commitment) =>
                        $this->serializeListItem(
                            $commitment
                        )
                )
                ->values();

        return Inertia::render(
            'Kdkmp/Commitments/Index',
            [
                'commitments' =>
                    $commitments,

                'canCreate' =>
                    $user->can(
                        'create',
                        SupplyCommitment::class
                    ),
            ]
        );
    }

    public function confidence(): Response
    {
        Gate::authorize(
            'viewAny',
            SupplyCommitment::class
        );

        $user = request()->user();

        $commitments =
            $this->commitmentQuery(
                $user->organization_id
            )
                ->whereNotNull(
                    'current_confidence'
                )
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get()
                ->map(
                    fn (SupplyCommitment $commitment) =>
                        $this->serializeListItem(
                            $commitment
                        )
                )
                ->values();

        return Inertia::render(
            'Kdkmp/Commitments/Confidence',
            [
                'commitments' =>
                    $commitments,
            ]
        );
    }

    public function create(): Response
    {
        Gate::authorize(
            'create',
            SupplyCommitment::class
        );

        $user = request()->user();

        $options =
            $this->formOptions(
                $user->organization_id
            );

        $selectedForecastId =
            request()->integer(
                'forecast_id'
            );

        if (
            $selectedForecastId
            && ! $options['forecasts']->contains(
                fn ($forecast) =>
                    $forecast['id']
                    === $selectedForecastId
            )
        ) {
            $selectedForecastId = null;
        }

        return Inertia::render(
            'Kdkmp/Commitments/Create',
            [
                ...$options,

                'selectedForecastId' =>
                    $selectedForecastId,
            ]
        );
    }

    public function store(
        StoreSupplyCommitmentRequest $request
    ): RedirectResponse {
        $commitment =
            $this->workflowService->createDraft(
                $request->user(),
                $request->validated()
            );

        return redirect()
            ->route(
                'kdkmp.commitments.show',
                $commitment
            )
            ->with(
                'success',
                'Draft komitmen pasokan berhasil dibuat.'
            );
    }

    public function show(
        SupplyCommitment $commitment
    ): Response {
        Gate::authorize(
            'view',
            $commitment
        );

        $user = request()->user();

        $commitment->load([
            'forecast.sppgOrganization',
            'forecast.unit',
            'commodity',
            'organization',
            'producer',
            'expectedHarvest.unit',
            'activeVersion.unit',

            'versions' => fn ($query) =>
                $query
                    ->with([
                        'unit',
                        'createdBy',
                        'submittedBy',
                        'reviewedBy',
                    ])
                    ->orderByDesc(
                        'version_no'
                    ),

            'confidenceEvents' => fn ($query) =>
                $query
                    ->with('actorUser')
                    ->orderByDesc(
                        'occurred_at'
                    )
                    ->orderByDesc('id'),

            'recoveryRequests' => fn ($query) =>
                $query
                    ->with([
                        'commitmentVersion',
                        'requestedBy',
                        'reviewedBy',
                    ])
                    ->orderByDesc(
                        'requested_at'
                    )
                    ->orderByDesc('id'),
        ]);

        $latestVersion =
            $commitment
                ->versions
                ->first();

        $hasOpenVersion =
            $commitment
                ->versions
                ->contains(
                    fn (
                        CommitmentVersion $version
                    ) =>
                        in_array(
                            $version
                                ->approval_status,
                            [
                                CommitmentApprovalStatus
                                    ::DRAFT,

                                CommitmentApprovalStatus
                                    ::PENDING_APPROVAL,
                            ],
                            true
                        )
                );

        $canStartRevision =
            $user->can(
                'createRevision',
                $commitment
            )
            && ! $hasOpenVersion
            && (
                (
                    $commitment
                        ->active_version_id
                    === null
                    && $latestVersion
                    ?->isRejected()
                )
                || (
                    $commitment
                        ->active_version_id
                    !== null
                    && $commitment
                        ->current_confidence
                    === SupplyConfidence::YELLOW
                )
            );

        $canRequestRecovery =
            $user->can(
                'requestRecovery',
                $commitment
            )
            && $commitment
                ->current_confidence
                === SupplyConfidence::YELLOW
            && ! $hasOpenVersion;

        return Inertia::render(
            'Kdkmp/Commitments/Show',
            [
                'commitment' =>
                    $this->serializeDetail(
                        $commitment
                    ),

                'can' => [
                    'editLatestDraft' =>
                        $latestVersion
                        ?->isDraft()
                        && $user->can(
                            'updateDraft',
                            $latestVersion
                        ),

                    'submitLatestDraft' =>
                        $latestVersion
                        ?->isDraft()
                        && $user->can(
                            'submit',
                            $latestVersion
                        ),

                    'createRevision' =>
                        $canStartRevision,

                    'downgradeConfidence' =>
                        $user->can(
                            'downgradeConfidence',
                            $commitment
                        )
                        && in_array(
                            $commitment
                                ->current_confidence,
                            [
                                SupplyConfidence::GREEN,
                                SupplyConfidence::YELLOW,
                            ],
                            true
                        ),

                    'requestRecovery' =>
                        $canRequestRecovery,
                ],
            ]
        );
    }

    private function commitmentQuery(
        int $organizationId
    ) {
        return SupplyCommitment::query()
            ->where(
                'organization_id',
                $organizationId
            )
            ->with([
                'forecast.sppgOrganization',
                'forecast.unit',
                'commodity',
                'producer',
                'activeVersion.unit',

                'versions' => fn ($query) =>
                    $query
                        ->orderByDesc(
                            'version_no'
                        ),
            ]);
    }

    private function formOptions(
        int $organizationId
    ): array {
        $sppgIds =
            SupplyNetworkLink::query()
                ->where(
                    'kdkmp_organization_id',
                    $organizationId
                )
                ->where(
                    'network_role',
                    NetworkRole::PRIMARY->value
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

        $forecasts =
            DemandForecast::query()
                ->whereIn(
                    'sppg_organization_id',
                    $sppgIds
                )
                ->where(
                    'status',
                    ForecastStatus
                        ::PUBLISHED
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
                    fn (
                        DemandForecast $forecast
                    ) => [
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

                        'target_volume' =>
                            (string)
                            $forecast
                                ->target_volume,

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

                        'required_start_at' =>
                            $forecast
                                ->required_start_at
                                ?->toIso8601String(),

                        'required_end_at' =>
                            $forecast
                                ->required_end_at
                                ?->toIso8601String(),
                    ]
                )
                ->values();

        $producers =
            Producer::query()
                ->where(
                    'organization_id',
                    $organizationId
                )
                ->where(
                    'is_active',
                    true
                )
                ->orderBy('name')
                ->get([
                    'id',
                    'producer_code',
                    'name',
                    'village',
                    'district',
                ])
                ->map(
                    fn (
                        Producer $producer
                    ) => [
                        'id' =>
                            $producer->id,

                        'producer_code' =>
                            $producer
                                ->producer_code,

                        'name' =>
                            $producer->name,

                        'village' =>
                            $producer->village,

                        'district' =>
                            $producer->district,
                    ]
                )
                ->values();

        $expectedHarvests =
            ExpectedHarvest::query()
                ->where(
                    'organization_id',
                    $organizationId
                )
                ->whereHas(
                    'producer',
                    fn ($query) =>
                        $query->where(
                            'is_active',
                            true
                        )
                )
                ->with([
                    'producer',
                    'commodity',
                    'unit',
                ])
                ->orderBy(
                    'harvest_start_at'
                )
                ->orderBy('id')
                ->get()
                ->map(
                    fn (
                        ExpectedHarvest $harvest
                    ) => [
                        'id' =>
                            $harvest->id,

                        'producer_id' =>
                            $harvest
                                ->producer_id,

                        'commodity_id' =>
                            $harvest
                                ->commodity_id,

                        'unit_id' =>
                            $harvest
                                ->unit_id,

                        'producer_name' =>
                            $harvest
                                ->producer
                                ->name,

                        'commodity_name' =>
                            $harvest
                                ->commodity
                                ->name,

                        'expected_min_volume' =>
                            (string)
                            $harvest
                                ->expected_min_volume,

                        'expected_max_volume' =>
                            (string)
                            $harvest
                                ->expected_max_volume,

                        'harvest_start_at' =>
                            $harvest
                                ->harvest_start_at
                                ?->toIso8601String(),

                        'harvest_end_at' =>
                            $harvest
                                ->harvest_end_at
                                ?->toIso8601String(),

                        'unit' => [
                            'symbol' =>
                                $harvest
                                    ->unit
                                    ->symbol,

                            'decimal_precision' =>
                                $harvest
                                    ->unit
                                    ->decimal_precision,
                        ],
                    ]
                )
                ->values();

        return [
            'forecasts' =>
                $forecasts,

            'producers' =>
                $producers,

            'expectedHarvests' =>
                $expectedHarvests,
        ];
    }

    private function serializeListItem(
        SupplyCommitment $commitment
    ): array {
        $latestVersion =
            $commitment
                ->versions
                ->first();

        return [
            'id' =>
                $commitment->id,

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

            'commodity' => [
                'id' =>
                    $commitment
                        ->commodity
                        ->id,

                'name' =>
                    $commitment
                        ->commodity
                        ->name,
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

                'is_active' =>
                    (bool)
                    $commitment
                        ->producer
                        ->is_active,
            ],

            'lifecycle_status' =>
                $commitment
                    ->lifecycle_status
                    ->value,

            'lifecycle_label' =>
                $commitment
                    ->lifecycle_status
                    ->label(),

            'current_confidence' =>
                $commitment
                    ->current_confidence
                    ?->value,

            'current_confidence_label' =>
                $commitment
                    ->current_confidence
                    ?->label(),

            'workflow_state' =>
                $this->workflowState(
                    $commitment,
                    $latestVersion
                ),

            'latest_version' =>
                $latestVersion
                    ? $this->serializeVersion(
                        $latestVersion
                    )
                    : null,

            'active_version' =>
                $commitment->activeVersion
                    ? $this->serializeVersion(
                        $commitment
                            ->activeVersion
                    )
                    : null,

            'last_confidence_verified_at' =>
                $commitment
                    ->last_confidence_verified_at
                    ?->toIso8601String(),

            'created_at' =>
                $commitment
                    ->created_at
                    ?->toIso8601String(),
        ];
    }

    private function serializeDetail(
        SupplyCommitment $commitment
    ): array {
        return [
            ...$this->serializeListItem(
                $commitment
            ),

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

            'versions' =>
                $commitment
                    ->versions
                    ->map(
                        fn (
                            CommitmentVersion $version
                        ) =>
                            $this->serializeVersion(
                                $version,
                                true
                            )
                    )
                    ->values(),

            'confidence_events' =>
                $commitment
                    ->confidenceEvents
                    ->map(
                        fn ($event) => [
                            'id' =>
                                $event->id,

                            'from_confidence' =>
                                $event
                                    ->from_confidence
                                    ?->value,

                            'to_confidence' =>
                                $event
                                    ->to_confidence
                                    ->value,

                            'source' =>
                                $event
                                    ->source
                                    ->value,

                            'reason_code' =>
                                $event
                                    ->reason_code,

                            'reason_note' =>
                                $event
                                    ->reason_note,

                            'actor' =>
                                $event
                                    ->actorUser
                                    ? [
                                        'id' =>
                                            $event
                                                ->actorUser
                                                ->id,

                                        'name' =>
                                            $event
                                                ->actorUser
                                                ->name,
                                    ]
                                    : null,

                            'occurred_at' =>
                                $event
                                    ->occurred_at
                                    ?->toIso8601String(),
                        ]
                    )
                    ->values(),

            'recovery_requests' =>
                $commitment
                    ->recoveryRequests
                    ->map(
                        fn ($recovery) => [
                            'id' =>
                                $recovery->id,

                            'status' =>
                                $recovery
                                    ->status
                                    ->value,

                            'status_label' =>
                                $recovery
                                    ->status
                                    ->label(),

                            'commitment_version_id' =>
                                $recovery
                                    ->commitment_version_id,

                            'recovery_reason' =>
                                $recovery
                                    ->recovery_reason,

                            'requested_by' => [
                                'id' =>
                                    $recovery
                                        ->requestedBy
                                        ->id,

                                'name' =>
                                    $recovery
                                        ->requestedBy
                                        ->name,
                            ],

                            'requested_at' =>
                                $recovery
                                    ->requested_at
                                    ?->toIso8601String(),

                            'reviewed_by' =>
                                $recovery
                                    ->reviewedBy
                                    ? [
                                        'id' =>
                                            $recovery
                                                ->reviewedBy
                                                ->id,

                                        'name' =>
                                            $recovery
                                                ->reviewedBy
                                                ->name,
                                    ]
                                    : null,

                            'reviewed_at' =>
                                $recovery
                                    ->reviewed_at
                                    ?->toIso8601String(),

                            'review_reason' =>
                                $recovery
                                    ->review_reason,
                        ]
                    )
                    ->values(),
        ];
    }

    private function serializeVersion(
        CommitmentVersion $version,
        bool $includeActors = false,
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

            'approval_status' =>
                $version
                    ->approval_status
                    ->value,

            'approval_status_label' =>
                $version
                    ->approval_status
                    ->label(),

            'change_reason' =>
                $version
                    ->change_reason,

            'operator_justification' =>
                $version
                    ->operator_justification,

            'submitted_at' =>
                $version
                    ->submitted_at
                    ?->toIso8601String(),

            'reviewed_at' =>
                $version
                    ->reviewed_at
                    ?->toIso8601String(),

            'review_reason' =>
                $version
                    ->review_reason,

            'approved_at' =>
                $version
                    ->approved_at
                    ?->toIso8601String(),

            'created_at' =>
                $version
                    ->created_at
                    ?->toIso8601String(),

            ...(
                $includeActors
                    ? [
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
                    ]
                    : []
            ),
        ];
    }

    private function workflowState(
        SupplyCommitment $commitment,
        ?CommitmentVersion $latestVersion
    ): array {
        if (! $latestVersion) {
            return [
                'value' => 'UNKNOWN',
                'label' => 'Tidak diketahui',
            ];
        }

        if (
            $commitment
                ->active_version_id
            === null
        ) {
            return [
                'value' =>
                    $latestVersion
                        ->approval_status
                        ->value,

                'label' =>
                    $latestVersion
                        ->approval_status
                        ->label(),
            ];
        }

        if (
            $latestVersion->id
            !== $commitment
                ->active_version_id
        ) {
            return match (
                $latestVersion
                    ->approval_status
            ) {
                CommitmentApprovalStatus::DRAFT => [
                    'value' =>
                        'REVISION_DRAFT',
                    'label' =>
                        'Draft Revisi',
                ],

                CommitmentApprovalStatus::PENDING_APPROVAL => [
                    'value' =>
                        'PENDING_REVISION',
                    'label' =>
                        'Revisi Menunggu Persetujuan',
                ],

                CommitmentApprovalStatus::REJECTED => [
                    'value' =>
                        'REVISION_REJECTED',
                    'label' =>
                        'Revisi Ditolak',
                ],

                default => [
                    'value' =>
                        $latestVersion
                            ->approval_status
                            ->value,

                    'label' =>
                        $latestVersion
                            ->approval_status
                            ->label(),
                ],
            };
        }

        return [
            'value' => 'APPROVED',
            'label' => 'Disetujui',
        ];
    }
}