<?php

namespace App\Http\Controllers\Kdkmp;

use App\Enums\CommitmentLifecycleStatus;
use App\Enums\RecoveryRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kdkmp\ApproveConfidenceRecoveryRequest;
use App\Http\Requests\Kdkmp\RejectConfidenceRecoveryRequest;
use App\Models\ConfidenceRecoveryRequest;
use App\Models\User;
use App\Services\Commitment\ConfidenceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ConfidenceRecoveryReviewController extends Controller
{
    public function __construct(
        private readonly ConfidenceService $confidenceService
    ) {
    }

    public function index(): Response
    {
        $user = request()->user();

        $this->assertManager($user);

        $recoveries =
            ConfidenceRecoveryRequest::query()
                ->where(
                    'status',
                    RecoveryRequestStatus
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
                    'requestedBy',

                    'commitmentVersion.unit',

                    'commitment.forecast.sppgOrganization',
                    'commitment.forecast.commodity',
                    'commitment.forecast.unit',

                    'commitment.producer',

                    'commitment.activeVersion.unit',
                ])
                ->orderBy('requested_at')
                ->orderBy('id')
                ->get()
                ->map(
                    fn (
                        ConfidenceRecoveryRequest $recovery
                    ) =>
                        $this->serializeQueueItem(
                            $recovery
                        )
                )
                ->values();

        return Inertia::render(
            'Kdkmp/Manager/Recoveries/Index',
            [
                'recoveries' =>
                    $recoveries,
            ]
        );
    }

    public function show(
        ConfidenceRecoveryRequest $recovery
    ): Response {
        $user = request()->user();

        $this->assertManager($user);

        Gate::authorize(
            'view',
            $recovery
        );

        $recovery->load([
            'requestedBy',
            'reviewedBy',

            'commitmentVersion.unit',
            'commitmentVersion.createdBy',
            'commitmentVersion.submittedBy',
            'commitmentVersion.reviewedBy',

            'commitment.organization',

            'commitment.forecast.sppgOrganization',
            'commitment.forecast.commodity',
            'commitment.forecast.unit',

            'commitment.producer',

            'commitment.expectedHarvest.unit',

            'commitment.activeVersion.unit',

            'commitment.confidenceEvents' =>
                fn ($query) =>
                    $query
                        ->with('actorUser')
                        ->orderByDesc(
                            'occurred_at'
                        )
                        ->orderByDesc('id'),
        ]);

        return Inertia::render(
            'Kdkmp/Manager/Recoveries/Show',
            [
                'review' =>
                    $this->serializeReview(
                        $recovery
                    ),

                'can' => [
                    'approve' =>
                        $user->can(
                            'approve',
                            $recovery
                        ),

                    'reject' =>
                        $user->can(
                            'reject',
                            $recovery
                        ),
                ],
            ]
        );
    }

    public function approve(
        ApproveConfidenceRecoveryRequest $request,
        ConfidenceRecoveryRequest $recovery
    ): RedirectResponse {
        $validated =
            $request->validated();

        $this->confidenceService->approveRecovery(
            $request->user(),
            $recovery,
            $validated[
                'review_reason'
            ] ?? null
        );

        return redirect()
            ->route(
                'kdkmp.manager.recoveries.index'
            )
            ->with(
                'success',
                'Pemulihan confidence berhasil disetujui.'
            );
    }

    public function reject(
        RejectConfidenceRecoveryRequest $request,
        ConfidenceRecoveryRequest $recovery
    ): RedirectResponse {
        $validated =
            $request->validated();

        $this->confidenceService->rejectRecovery(
            $request->user(),
            $recovery,
            $validated[
                'review_reason'
            ]
        );

        return redirect()
            ->route(
                'kdkmp.manager.recoveries.index'
            )
            ->with(
                'success',
                'Permintaan pemulihan confidence ditolak.'
            );
    }

    private function serializeQueueItem(
        ConfidenceRecoveryRequest $recovery
    ): array {
        $commitment =
            $recovery->commitment;

        return [
            'id' =>
                $recovery->id,

            'commitment_id' =>
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

                'commodity_name' =>
                    $commitment
                        ->forecast
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
            ],

            'current_confidence' =>
                $commitment
                    ->current_confidence
                    ?->value,

            'commitment_version' => [
                'id' =>
                    $recovery
                        ->commitmentVersion
                        ->id,

                'version_no' =>
                    $recovery
                        ->commitmentVersion
                        ->version_no,

                'min_volume' =>
                    (string)
                    $recovery
                        ->commitmentVersion
                        ->min_volume,

                'max_volume' =>
                    (string)
                    $recovery
                        ->commitmentVersion
                        ->max_volume,

                'unit_symbol' =>
                    $recovery
                        ->commitmentVersion
                        ->unit
                        ->symbol,
            ],

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
        ];
    }

    private function serializeReview(
        ConfidenceRecoveryRequest $recovery
    ): array {
        $commitment =
            $recovery->commitment;

        return [
            'recovery' => [
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
            ],

            'commitment' => [
                'id' =>
                    $commitment->id,

                'current_confidence' =>
                    $commitment
                        ->current_confidence
                        ?->value,

                'current_confidence_label' =>
                    $commitment
                        ->current_confidence
                        ?->label(),

                'last_confidence_verified_at' =>
                    $commitment
                        ->last_confidence_verified_at
                        ?->toIso8601String(),

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

                            'unit_symbol' =>
                                $commitment
                                    ->expectedHarvest
                                    ->unit
                                    ->symbol,
                        ]
                        : null,

                'active_version' => [
                    'id' =>
                        $commitment
                            ->activeVersion
                            ->id,

                    'version_no' =>
                        $commitment
                            ->activeVersion
                            ->version_no,

                    'min_volume' =>
                        (string)
                        $commitment
                            ->activeVersion
                            ->min_volume,

                    'max_volume' =>
                        (string)
                        $commitment
                            ->activeVersion
                            ->max_volume,

                    'availability_start_at' =>
                        $commitment
                            ->activeVersion
                            ->availability_start_at
                            ?->toIso8601String(),

                    'availability_end_at' =>
                        $commitment
                            ->activeVersion
                            ->availability_end_at
                            ?->toIso8601String(),

                    'notes' =>
                        $commitment
                            ->activeVersion
                            ->notes,

                    'unit_symbol' =>
                        $commitment
                            ->activeVersion
                            ->unit
                            ->symbol,
                ],
            ],

            /*
             * Recovery disimpan terhadap version tertentu.
             * Manager harus dapat melihat apakah request
             * masih menunjuk active version yang sama.
             */
            'requested_version' => [
                'id' =>
                    $recovery
                        ->commitmentVersion
                        ->id,

                'version_no' =>
                    $recovery
                        ->commitmentVersion
                        ->version_no,

                'is_current_active_version' =>
                    $commitment
                        ->active_version_id
                    === $recovery
                        ->commitment_version_id,

                'min_volume' =>
                    (string)
                    $recovery
                        ->commitmentVersion
                        ->min_volume,

                'max_volume' =>
                    (string)
                    $recovery
                        ->commitmentVersion
                        ->max_volume,

                'availability_start_at' =>
                    $recovery
                        ->commitmentVersion
                        ->availability_start_at
                        ?->toIso8601String(),

                'availability_end_at' =>
                    $recovery
                        ->commitmentVersion
                        ->availability_end_at
                        ?->toIso8601String(),

                'notes' =>
                    $recovery
                        ->commitmentVersion
                        ->notes,

                'unit_symbol' =>
                    $recovery
                        ->commitmentVersion
                        ->unit
                        ->symbol,
            ],

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

                            'actor_name' =>
                                $event
                                    ->actorUser
                                    ?->name,

                            'occurred_at' =>
                                $event
                                    ->occurred_at
                                    ?->toIso8601String(),
                        ]
                    )
                    ->values(),
        ];
    }

    private function assertManager(
        User $user
    ): void {
        if (
            ! $user->isKdkmpManager()
            || ! $user->hasValidIdentityContext()
        ) {
            throw new AuthorizationException(
                'Hanya KDKMP Manager aktif yang dapat mengakses Recovery Review.'
            );
        }
    }
}