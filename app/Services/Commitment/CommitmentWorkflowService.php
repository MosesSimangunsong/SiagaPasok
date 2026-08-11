<?php

namespace App\Services\Commitment;

use App\Enums\AuditSource;
use App\Enums\CommitmentApprovalStatus;
use App\Enums\CommitmentLifecycleStatus;
use App\Enums\SupplyConfidence;
use App\Models\CommitmentConfidenceEvent;
use App\Models\CommitmentVersion;
use App\Models\DemandForecast;
use App\Models\ExpectedHarvest;
use App\Models\Producer;
use App\Models\SupplyCommitment;
use App\Models\Unit;
use App\Models\User;
use App\Models\FallbackRequest;
use Illuminate\Support\Carbon;
use App\Services\Audit\AuditService;
use App\Services\Notification\OperationalNotificationService;
use App\Services\Notification\DerivedForecastStateObservationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommitmentWorkflowService
{
    private const AUDIT_CREATED =
        'COMMITMENT_CREATED';

    private const AUDIT_DRAFT_UPDATED =
        'COMMITMENT_DRAFT_UPDATED';

    private const AUDIT_SOURCE_UPDATED =
        'COMMITMENT_SOURCE_UPDATED';

    private const AUDIT_SUBMITTED =
        'COMMITMENT_VERSION_SUBMITTED';

    private const AUDIT_APPROVED =
        'COMMITMENT_VERSION_APPROVED';

    private const AUDIT_REJECTED =
        'COMMITMENT_VERSION_REJECTED';

    private const AUDIT_REVISION_CREATED =
        'COMMITMENT_REVISION_CREATED';

    private const AUDIT_CONFIDENCE_INITIALIZED =
        'COMMITMENT_CONFIDENCE_INITIALIZED';
    
    private const AUDIT_FALLBACK_SOURCE_CREATED =
    'FALLBACK_SOURCE_COMMITMENT_CREATED';

    public function __construct(
    private readonly AuditService
        $auditService,

    private readonly CommitmentEligibilityService
        $commitmentEligibility,

    private readonly OperationalNotificationService
        $operationalNotificationService,

    private readonly DerivedForecastStateObservationService
        $derivedStateObservationService,
) {
}

    public function createDraft(
        User $actor,
        array $data,
    ): SupplyCommitment {
        $this->assertOperatorActor($actor);

        $validated =
            $this->validateInitialPayload(
                $data
            );

        return DB::transaction(
            function () use (
                $actor,
                $validated,
            ): SupplyCommitment {
                $forecast =
                    DemandForecast::query()
                        ->whereKey(
                            $validated[
                                'forecast_id'
                            ]
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->commitmentEligibility
    ->assertPrimaryDirectEligibility(
        $actor,
        $forecast
    );

                $producer =
                    $this->resolveActiveProducer(
                        $actor,
                        (int) $validated[
                            'producer_id'
                        ]
                    );

                $expectedHarvest =
                    $this->resolveExpectedHarvest(
                        $actor,
                        $validated[
                            'expected_harvest_id'
                        ] ?? null,
                        $producer,
                        $forecast
                    );

                $this->assertVersionPayload(
                    $forecast,
                    $expectedHarvest,
                    $validated
                );

                $commitment =
                    SupplyCommitment::create([
                        'forecast_id' =>
                            $forecast->id,

                        'organization_id' =>
                            $actor->organization_id,

                        'producer_id' =>
                            $producer->id,

                        'expected_harvest_id' =>
                            $expectedHarvest?->id,

                        'commodity_id' =>
                            $forecast->commodity_id,

                        'active_version_id' =>
                            null,

                        'lifecycle_status' =>
                            CommitmentLifecycleStatus
                                ::ACTIVE,

                        'current_confidence' =>
                            null,

                        'last_confidence_verified_at' =>
                            null,

                        'created_by' =>
                            $actor->id,
                    ]);

                $version =
                    $this->createVersionRecord(
                        commitment: $commitment,
                        actor: $actor,
                        versionNo: 1,
                        data: $validated,
                        changeReason: null,
                    );

                $this->auditService->record(
                    actor: $actor,
                    source: AuditSource::USER,
                    action:
                        self::AUDIT_CREATED,
                    entity: $commitment,
                    previousValue: null,
                    newValue: [
                        ...$this
                            ->commitmentSnapshot(
                                $commitment
                            ),

                        'draft_version' =>
                            $this->versionSnapshot(
                                $version
                            ),
                    ],
                );

                return $commitment
                    ->refresh()
                    ->load([
                        'forecast',
                        'producer',
                        'expectedHarvest',
                        'commodity',
                        'versions.unit',
                    ]);
            }
        );
    }

    public function createFallbackSourceDraft(
    User $actor,
    FallbackRequest $request,
    array $data,
): SupplyCommitment {
    $this->assertOperatorActor(
        $actor
    );

    $validated =
        $this->validateFallbackSourceInitialPayload(
            $data
        );

    return DB::transaction(
        function () use (
            $actor,
            $request,
            $validated,
        ): SupplyCommitment {
            $currentRequest =
                FallbackRequest::query()
                    ->whereKey(
                        $request->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            $forecast =
                DemandForecast::query()
                    ->whereKey(
                        $currentRequest
                            ->forecast_id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            /*
             * Ini satu-satunya initial entry path
             * NETWORK ke Forecast Commitment.
             */
            $this->commitmentEligibility
                ->assertNetworkFallbackEntryEligibility(
                    $actor,
                    $currentRequest,
                    $forecast
                );

            $producer =
                $this->resolveActiveProducer(
                    $actor,
                    (int) $validated[
                        'producer_id'
                    ]
                );

            $expectedHarvest =
                $this->resolveExpectedHarvest(
                    $actor,
                    $validated[
                        'expected_harvest_id'
                    ] ?? null,
                    $producer,
                    $forecast
                );

            $this->assertVersionPayload(
                $forecast,
                $expectedHarvest,
                $validated
            );

            $commitment =
                SupplyCommitment::create([
                    'forecast_id' =>
                        $forecast->id,

                    'organization_id' =>
                        $actor->organization_id,

                    'producer_id' =>
                        $producer->id,

                    'expected_harvest_id' =>
                        $expectedHarvest?->id,

                    'commodity_id' =>
                        $forecast->commodity_id,

                    'active_version_id' =>
                        null,

                    'lifecycle_status' =>
                        CommitmentLifecycleStatus
                            ::ACTIVE,

                    'current_confidence' =>
                        null,

                    'last_confidence_verified_at' =>
                        null,

                    'created_by' =>
                        $actor->id,
                ]);

            $version =
                $this->createVersionRecord(
                    commitment: $commitment,
                    actor: $actor,
                    versionNo: 1,
                    data: $validated,
                    changeReason: null,
                );

            /*
             * fallback_request_id disimpan pada
             * audit context, BUKAN pada Commitment
             * schema.
             *
             * OfferSource nanti menjadi persistent
             * traceability antara fallback dan
             * Commitment.
             */
            $this->auditService->record(
                actor: $actor,
                source: AuditSource::USER,
                action:
                    self::AUDIT_FALLBACK_SOURCE_CREATED,
                entity: $commitment,
                previousValue: null,
                newValue: [
                    'entry_context' =>
                        'NETWORK_FALLBACK_SOURCE',

                    'fallback_request_id' =>
                        $currentRequest->id,

                    ...$this
                        ->commitmentSnapshot(
                            $commitment
                        ),

                    'draft_version' =>
                        $this->versionSnapshot(
                            $version
                        ),
                ],
            );

            return $commitment
                ->refresh()
                ->load([
                    'forecast',
                    'producer',
                    'expectedHarvest',
                    'commodity',
                    'versions.unit',
                ]);
        }
    );
}

    public function updateDraft(
        User $actor,
        CommitmentVersion $version,
        array $data,
    ): CommitmentVersion {
        $this->assertOperatorActor($actor);

        return DB::transaction(
            function () use (
                $actor,
                $version,
                $data,
            ): CommitmentVersion {
                $currentVersion =
                    CommitmentVersion::query()
                        ->whereKey(
                            $version->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $commitment =
                    SupplyCommitment::query()
                        ->whereKey(
                            $currentVersion
                                ->commitment_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertOwner(
                    $actor,
                    $commitment
                );

                $this->assertCommitmentActive(
                    $commitment
                );

                if (! $currentVersion->isDraft()) {
                    throw ValidationException::withMessages([
                        'approval_status' => (
                            'Hanya Commitment Version DRAFT '
                            .'yang dapat diedit.'
                        ),
                    ]);
                }

                $forecast =
                    DemandForecast::query()
                        ->whereKey(
                            $commitment->forecast_id
                        )
                        ->firstOrFail();

                $this->commitmentEligibility
    ->assertExistingCommitmentEligibility(
        $actor,
        $forecast,
        $commitment
    );

                $validated =
                    $this->validateDraftPayload(
                        $data,
                        $currentVersion
                    );

                $isInitialDraft =
                    $currentVersion->version_no === 1
                    && $commitment
                        ->active_version_id === null;

                $producer =
                    $commitment->producer;

                $expectedHarvest =
                    $commitment
                        ->expectedHarvest;

                $sourceBefore =
                    $this->commitmentSnapshot(
                        $commitment
                    );

                if ($isInitialDraft) {
                    $producer =
                        $this->resolveActiveProducer(
                            $actor,
                            (int) $validated[
                                'producer_id'
                            ]
                        );

                    $expectedHarvest =
                        $this->resolveExpectedHarvest(
                            $actor,
                            $validated[
                                'expected_harvest_id'
                            ] ?? null,
                            $producer,
                            $forecast
                        );

                    $commitment->fill([
                        'producer_id' =>
                            $producer->id,

                        'expected_harvest_id' =>
                            $expectedHarvest?->id,
                    ]);
                } else {
                    $producer =
                        $this->resolveActiveProducer(
                            $actor,
                            $commitment->producer_id
                        );

                    $expectedHarvest =
                        $this->resolveExpectedHarvest(
                            $actor,
                            $commitment
                                ->expected_harvest_id,
                            $producer,
                            $forecast
                        );
                }

                $this->assertVersionPayload(
                    $forecast,
                    $expectedHarvest,
                    $validated
                );

                $before =
                    $this->versionSnapshot(
                        $currentVersion
                    );

                $currentVersion->fill([
                    'min_volume' =>
                        $validated['min_volume'],

                    'max_volume' =>
                        $validated['max_volume'],

                    'unit_id' =>
                        $validated['unit_id'],

                    'availability_start_at' =>
                        $validated[
                            'availability_start_at'
                        ],

                    'availability_end_at' =>
                        $validated[
                            'availability_end_at'
                        ],

                    'notes' =>
                        $validated['notes']
                        ?? null,

                    'change_reason' =>
                        $isInitialDraft
                            ? null
                            : trim(
                                (string) (
                                    $validated[
                                        'change_reason'
                                    ] ?? ''
                                )
                            ),

                    'operator_justification' =>
                        $this->nullableTrim(
                            $validated[
                                'operator_justification'
                            ] ?? null
                        ),
                ]);

                $versionChanged =
                    $currentVersion->isDirty();

                $sourceChanged =
                    $commitment->isDirty([
                        'producer_id',
                        'expected_harvest_id',
                    ]);

                if (
                    ! $versionChanged
                    && ! $sourceChanged
                ) {
                    return $currentVersion;
                }

                if ($sourceChanged) {
                    $commitment->save();

                    $this->auditService->record(
                        actor: $actor,
                        source: AuditSource::USER,
                        action:
                            self::AUDIT_SOURCE_UPDATED,
                        entity: $commitment,
                        previousValue:
                            $sourceBefore,
                        newValue:
                            $this->commitmentSnapshot(
                                $commitment
                            ),
                    );
                }

                if ($versionChanged) {
                    $currentVersion->save();

                    $this->auditService->record(
                        actor: $actor,
                        source: AuditSource::USER,
                        action:
                            self::AUDIT_DRAFT_UPDATED,
                        entity: $currentVersion,
                        previousValue: $before,
                        newValue:
                            $this->versionSnapshot(
                                $currentVersion
                            ),
                    );
                }

                return $currentVersion
                    ->refresh()
                    ->load([
                        'commitment',
                        'unit',
                    ]);
            }
        );
    }

    public function submit(
        User $actor,
        CommitmentVersion $version,
    ): CommitmentVersion {
        $this->assertOperatorActor($actor);

        return DB::transaction(
            function () use (
                $actor,
                $version,
            ): CommitmentVersion {
                $currentVersion =
                    CommitmentVersion::query()
                        ->whereKey(
                            $version->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $commitment =
                    SupplyCommitment::query()
                        ->whereKey(
                            $currentVersion
                                ->commitment_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertOwner(
                    $actor,
                    $commitment
                );

                $this->assertCommitmentActive(
                    $commitment
                );

                if (
                    $currentVersion
                        ->isPendingApproval()
                ) {
                    return $currentVersion;
                }

                if (! $currentVersion->isDraft()) {
                    throw ValidationException::withMessages([
                        'approval_status' => (
                            'Hanya Commitment Version DRAFT '
                            .'yang dapat disubmit.'
                        ),
                    ]);
                }

                $forecast =
                    DemandForecast::query()
                        ->whereKey(
                            $commitment->forecast_id
                        )
                        ->firstOrFail();

                $this->commitmentEligibility
    ->assertExistingCommitmentEligibility(
        $actor,
        $forecast,
        $commitment
    );

                $producer =
                    $this->resolveActiveProducer(
                        $actor,
                        $commitment->producer_id
                    );

                $expectedHarvest =
                    $this->resolveExpectedHarvest(
                        $actor,
                        $commitment
                            ->expected_harvest_id,
                        $producer,
                        $forecast
                    );

                $payload =
                    $this->versionData(
                        $currentVersion
                    );

                $this->assertVersionPayload(
                    $forecast,
                    $expectedHarvest,
                    $payload
                );

                if (
                    $currentVersion->version_no > 1
                    && trim(
                        (string)
                        $currentVersion
                            ->change_reason
                    ) === ''
                ) {
                    throw ValidationException::withMessages([
                        'change_reason' => (
                            'Alasan perubahan wajib diisi '
                            .'untuk revision Commitment.'
                        ),
                    ]);
                }

                $before =
                    $this->versionSnapshot(
                        $currentVersion
                    );

                $currentVersion->update([
                    'approval_status' =>
                        CommitmentApprovalStatus
                            ::PENDING_APPROVAL,

                    'submitted_by' =>
                        $actor->id,

                    'submitted_at' =>
                        now(),
                ]);

                $this->auditService->record(
    actor: $actor,
    source: AuditSource::USER,
    action:
        self::AUDIT_SUBMITTED,
    entity: $currentVersion,
    previousValue: $before,
    newValue:
        $this->versionSnapshot(
            $currentVersion
        ),
);

/*
 * NotificationService akan menunda persistence
 * sampai outer business transaction commit.
 *
 * Jika transaction rollback, notification tidak
 * menjadi visible.
 */
$this->operationalNotificationService
    ->commitmentApprovalRequired(
        $commitment,
        $currentVersion
    );

return $currentVersion
    ->refresh();
            }
        );
    }

    public function approve(
        User $actor,
        CommitmentVersion $version,
    ): CommitmentVersion {
        $this->assertManagerActor($actor);

        return DB::transaction(
            function () use (
                $actor,
                $version,
            ): CommitmentVersion {
                $currentVersion =
                    CommitmentVersion::query()
                        ->whereKey(
                            $version->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $commitment =
                    SupplyCommitment::query()
                        ->whereKey(
                            $currentVersion
                                ->commitment_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertOwner(
                    $actor,
                    $commitment
                );

                $this->assertCommitmentActive(
                    $commitment
                );

                if ($currentVersion->isApproved()) {
                    return $currentVersion;
                }

                if (
                    ! $currentVersion
                        ->isPendingApproval()
                ) {
                    throw ValidationException::withMessages([
                        'approval_status' => (
                            'Hanya Commitment Version '
                            .'PENDING_APPROVAL yang dapat '
                            .'disetujui.'
                        ),
                    ]);
                }

                $this->assertMakerChecker(
                    $actor,
                    $currentVersion
                );

                $forecast =
                    DemandForecast::query()
                        ->whereKey(
                            $commitment->forecast_id
                        )
                        ->firstOrFail();

                $this->commitmentEligibility
    ->assertExistingCommitmentEligibility(
        $actor,
        $forecast,
        $commitment,
        requireOperator: false,
    );

                /*
                 * Revalidate source at decision time.
                 * A producer may become inactive while
                 * the version is waiting in the queue.
                 */
                $producer =
                    $this->resolveActiveProducerForOrganization(
                        $commitment
                            ->organization_id,
                        $commitment
                            ->producer_id
                    );

                $expectedHarvest =
                    $this->resolveExpectedHarvestForOrganization(
                        $commitment
                            ->organization_id,
                        $commitment
                            ->expected_harvest_id,
                        $producer,
                        $forecast
                    );

                $this->assertVersionPayload(
                    $forecast,
                    $expectedHarvest,
                    $this->versionData(
                        $currentVersion
                    )
                );

                $beforeVersion =
                    $this->versionSnapshot(
                        $currentVersion
                    );

                $beforeCommitment =
                    $this->commitmentSnapshot(
                        $commitment
                    );

                $isInitialApproval =
                    $commitment
                        ->active_version_id === null;

                $currentVersion->update([
                    'approval_status' =>
                        CommitmentApprovalStatus
                            ::APPROVED,

                    'reviewed_by' =>
                        $actor->id,

                    'reviewed_at' =>
                        now(),

                    'review_reason' =>
                        null,

                    'approved_at' =>
                        now(),
                ]);

                $commitment->active_version_id =
                    $currentVersion->id;

                if ($isInitialApproval) {
                    $commitment->current_confidence =
                        SupplyConfidence::GREEN;

                    $commitment
                        ->last_confidence_verified_at =
                        now();
                }

                $commitment->save();

                $this->auditService->record(
                    actor: $actor,
                    source: AuditSource::USER,
                    action:
                        self::AUDIT_APPROVED,
                    entity: $currentVersion,
                    previousValue:
                        $beforeVersion,
                    newValue:
                        $this->versionSnapshot(
                            $currentVersion
                        ),
                );

                if ($isInitialApproval) {
                    $event =
                        CommitmentConfidenceEvent::create([
                            'commitment_id' =>
                                $commitment->id,

                            'from_confidence' =>
                                null,

                            'to_confidence' =>
                                SupplyConfidence
                                    ::GREEN,

                            'source' =>
                                AuditSource::USER,

                            'reason_code' =>
                                'INITIAL_APPROVAL',

                            'reason_note' =>
                                'Confidence awal GREEN setelah Commitment Version pertama disetujui.',

                            'actor_user_id' =>
                                $actor->id,

                            'occurred_at' =>
                                now(),
                        ]);

                    $this->auditService->record(
                        actor: $actor,
                        source: AuditSource::USER,
                        action:
                            self::AUDIT_CONFIDENCE_INITIALIZED,
                        entity: $commitment,
                        previousValue:
                            $beforeCommitment,
                        newValue:
                            $this->commitmentSnapshot(
                                $commitment
                            ),
                        reasonNote:
                            'Initial approval established GREEN confidence.',
                    );
                }

                /*
                 * Initial approval dapat menambah canonical
                 * Safe Supply dan contributor.
                 *
                 * Revision approval yang belum GREEN mungkin
                 * menghasilkan no-op observation; observer akan
                 * mendeduplikasi state yang sama.
                 */
                $this->derivedStateObservationService
                    ->observeAfterCommit(
                        $forecast
                    );

                return $currentVersion
                    ->refresh()
                    ->load([
                        'commitment.activeVersion',
                        'unit',
                    ]);
            }
        );
    }

    public function reject(
        User $actor,
        CommitmentVersion $version,
        string $reason,
    ): CommitmentVersion {
        $this->assertManagerActor($actor);

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'review_reason' =>
                    'Alasan penolakan wajib diisi.',
            ]);
        }

        return DB::transaction(
            function () use (
                $actor,
                $version,
                $reason,
            ): CommitmentVersion {
                $currentVersion =
                    CommitmentVersion::query()
                        ->whereKey(
                            $version->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $commitment =
                    SupplyCommitment::query()
                        ->whereKey(
                            $currentVersion
                                ->commitment_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertOwner(
                    $actor,
                    $commitment
                );

                $this->assertCommitmentActive(
                    $commitment
                );

                if ($currentVersion->isRejected()) {
                    return $currentVersion;
                }

                if (
                    ! $currentVersion
                        ->isPendingApproval()
                ) {
                    throw ValidationException::withMessages([
                        'approval_status' => (
                            'Hanya Commitment Version '
                            .'PENDING_APPROVAL yang dapat '
                            .'ditolak.'
                        ),
                    ]);
                }

                $this->assertMakerChecker(
                    $actor,
                    $currentVersion
                );

                $before =
                    $this->versionSnapshot(
                        $currentVersion
                    );

                $currentVersion->update([
                    'approval_status' =>
                        CommitmentApprovalStatus
                            ::REJECTED,

                    'reviewed_by' =>
                        $actor->id,

                    'reviewed_at' =>
                        now(),

                    'review_reason' =>
                        $reason,

                    'approved_at' =>
                        null,
                ]);

                $this->auditService->record(
                    actor: $actor,
                    source: AuditSource::USER,
                    action:
                        self::AUDIT_REJECTED,
                    entity: $currentVersion,
                    previousValue: $before,
                    newValue:
                        $this->versionSnapshot(
                            $currentVersion
                        ),
                    reasonNote: $reason,
                );

                return $currentVersion
                    ->refresh();
            }
        );
    }

    public function createRevision(
        User $actor,
        SupplyCommitment $commitment,
        array $data,
    ): CommitmentVersion {
        $this->assertOperatorActor($actor);

        $validated =
            $this->validateRevisionPayload(
                $data
            );

        return DB::transaction(
            function () use (
                $actor,
                $commitment,
                $validated,
            ): CommitmentVersion {
                $current =
                    SupplyCommitment::query()
                        ->whereKey(
                            $commitment->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertOwner(
                    $actor,
                    $current
                );

                $this->assertCommitmentActive(
                    $current
                );

                $existingOpenVersion =
                    CommitmentVersion::query()
                        ->where(
                            'commitment_id',
                            $current->id
                        )
                        ->whereIn(
                            'approval_status',
                            [
                                CommitmentApprovalStatus
                                    ::DRAFT
                                    ->value,

                                CommitmentApprovalStatus
                                    ::PENDING_APPROVAL
                                    ->value,
                            ]
                        )
                        ->exists();

                if ($existingOpenVersion) {
                    throw ValidationException::withMessages([
                        'revision' => (
                            'Masih terdapat Draft atau '
                            .'Commitment Version yang menunggu '
                            .'persetujuan.'
                        ),
                    ]);
                }

                $latestVersion =
                    CommitmentVersion::query()
                        ->where(
                            'commitment_id',
                            $current->id
                        )
                        ->orderByDesc(
                            'version_no'
                        )
                        ->first();

                if (! $latestVersion) {
                    throw ValidationException::withMessages([
                        'revision' =>
                            'Commitment belum memiliki version.',
                    ]);
                }

                if (
                    $current->active_version_id === null
                ) {
                    /*
                     * Initial version was rejected.
                     * Operator may repair it through
                     * a new immutable version.
                     */
                    if (
                        ! $latestVersion
                            ->isRejected()
                    ) {
                        throw ValidationException::withMessages([
                            'revision' => (
                                'Version baru sebelum approval '
                                .'pertama hanya dapat dibuat '
                                .'setelah version sebelumnya '
                                .'ditolak.'
                            ),
                        ]);
                    }
                } else {
                    if (
                        $current->current_confidence
                        === SupplyConfidence::GREEN
                    ) {
                        throw ValidationException::withMessages([
                            'current_confidence' => (
                                'Commitment GREEN harus '
                                .'didowngrade terlebih dahulu '
                                .'sebelum revision dibuat.'
                            ),
                        ]);
                    }

                    if (
                        $current->current_confidence
                        === SupplyConfidence::RED
                    ) {
                        throw ValidationException::withMessages([
                            'current_confidence' => (
                                'Commitment RED bersifat terminal. '
                                .'Buat logical Commitment baru '
                                .'untuk supply baru.'
                            ),
                        ]);
                    }

                    if (
                        $current->current_confidence
                        !== SupplyConfidence::YELLOW
                    ) {
                        throw ValidationException::withMessages([
                            'current_confidence' => (
                                'Revision approved Commitment '
                                .'hanya dapat dibuat saat '
                                .'confidence YELLOW.'
                            ),
                        ]);
                    }
                }

                $forecast =
                    DemandForecast::query()
                        ->whereKey(
                            $current->forecast_id
                        )
                        ->firstOrFail();

                $this->commitmentEligibility
    ->assertExistingCommitmentEligibility(
        $actor,
        $forecast,
        $current
    );

                $producer =
                    $this->resolveActiveProducer(
                        $actor,
                        $current->producer_id
                    );

                $expectedHarvest =
                    $this->resolveExpectedHarvest(
                        $actor,
                        $current
                            ->expected_harvest_id,
                        $producer,
                        $forecast
                    );

                $this->assertVersionPayload(
                    $forecast,
                    $expectedHarvest,
                    $validated
                );

                $nextVersionNo =
                    ((int)
                        CommitmentVersion::query()
                            ->where(
                                'commitment_id',
                                $current->id
                            )
                            ->max('version_no'))
                    + 1;

                $version =
                    $this->createVersionRecord(
                        commitment: $current,
                        actor: $actor,
                        versionNo: $nextVersionNo,
                        data: $validated,
                        changeReason:
                            trim(
                                $validated[
                                    'change_reason'
                                ]
                            ),
                    );

                $this->auditService->record(
                    actor: $actor,
                    source: AuditSource::USER,
                    action:
                        self::AUDIT_REVISION_CREATED,
                    entity: $version,
                    previousValue: null,
                    newValue:
                        $this->versionSnapshot(
                            $version
                        ),
                    reasonNote:
                        $version->change_reason,
                );

                return $version
                    ->load([
                        'commitment',
                        'unit',
                    ]);
            }
        );
    }

private function validateFallbackSourceInitialPayload(
    array $data,
): array {
    return Validator::make(
        $data,
        [
            /*
             * Forecast context berasal dari
             * Fallback Request, bukan client.
             */
            'forecast_id' => [
                'prohibited',
            ],

            'producer_id' => [
                'required',
                'integer',
            ],

            'expected_harvest_id' => [
                'nullable',
                'integer',
            ],

            ...$this
                ->baseVersionRules(),

            'change_reason' => [
                'prohibited',
            ],
        ]
    )->validate();
}
    
    private function validateInitialPayload(
        array $data,
    ): array {
        return Validator::make(
            $data,
            [
                'forecast_id' => [
                    'required',
                    'integer',
                    'exists:demand_forecasts,id',
                ],

                'producer_id' => [
                    'required',
                    'integer',
                ],

                'expected_harvest_id' => [
                    'nullable',
                    'integer',
                ],

                ...$this
                    ->baseVersionRules(),

                'change_reason' => [
                    'prohibited',
                ],
            ]
        )->validate();
    }

    private function validateDraftPayload(
        array $data,
        CommitmentVersion $version,
    ): array {
        $isInitialDraft =
            $version->version_no === 1
            && $version
                ->commitment
                ?->active_version_id === null;

        $rules =
            $this->baseVersionRules();

        if ($isInitialDraft) {
            $rules['producer_id'] = [
                'required',
                'integer',
            ];

            $rules[
                'expected_harvest_id'
            ] = [
                'nullable',
                'integer',
            ];

            $rules['change_reason'] = [
                'nullable',
                'string',
                'max:5000',
            ];
        } else {
            $rules['producer_id'] = [
                'prohibited',
            ];

            $rules[
                'expected_harvest_id'
            ] = [
                'prohibited',
            ];

            $rules['change_reason'] = [
                'required',
                'string',
                'max:5000',
            ];
        }

        return Validator::make(
            $data,
            $rules
        )->validate();
    }

    private function validateRevisionPayload(
        array $data,
    ): array {
        return Validator::make(
            $data,
            [
                ...$this
                    ->baseVersionRules(),

                'change_reason' => [
                    'required',
                    'string',
                    'max:5000',
                ],
            ]
        )->validate();
    }

    private function baseVersionRules(): array
    {
        return [
            'min_volume' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'max_volume' => [
                'required',
                'numeric',
                'gte:min_volume',
            ],

            'unit_id' => [
                'required',
                'integer',

                Rule::exists(
                    'units',
                    'id'
                )->where(
                    fn ($query) =>
                        $query->where(
                            'is_active',
                            true
                        )
                ),
            ],

            'availability_start_at' => [
                'required',
                'date',
            ],

            'availability_end_at' => [
                'required',
                'date',
                'after_or_equal:availability_start_at',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'operator_justification' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    private function assertVersionPayload(
        DemandForecast $forecast,
        ?ExpectedHarvest $expectedHarvest,
        array $data,
    ): void {
        $unit = Unit::query()
            ->whereKey(
                $data['unit_id']
            )
            ->where(
                'is_active',
                true
            )
            ->first();

        if (! $unit) {
            throw ValidationException::withMessages([
                'unit_id' =>
                    'Unit tidak tersedia atau tidak aktif.',
            ]);
        }

        /*
         * MVP has no automatic unit conversion.
         */
        if (
            $unit->id
            !== $forecast->unit_id
        ) {
            throw ValidationException::withMessages([
                'unit_id' => (
                    'Unit Commitment harus sama dengan '
                    .'unit Demand Forecast pada MVP.'
                ),
            ]);
        }

        $start = Carbon::parse(
    $data['availability_start_at']
);

$end = Carbon::parse(
    $data['availability_end_at']
);

        /*
         * Commitment availability must intersect
         * the forecast requirement window.
         */
        if (
            $start->greaterThan(
                $forecast->required_end_at
            )
            || $end->lessThan(
                $forecast->required_start_at
            )
        ) {
            throw ValidationException::withMessages([
                'availability_start_at' => (
                    'Window ketersediaan Commitment '
                    .'harus overlap dengan periode '
                    .'kebutuhan Forecast.'
                ),
            ]);
        }

        if (! $expectedHarvest) {
            return;
        }

        if (
            $expectedHarvest->unit_id
            !== $forecast->unit_id
        ) {
            throw ValidationException::withMessages([
                'expected_harvest_id' => (
                    'Expected Harvest menggunakan unit '
                    .'yang tidak kompatibel dengan Forecast.'
                ),
            ]);
        }

        /*
         * Expected Harvest is planning context,
         * never a hard capacity ceiling.
         */
        if (
            (float) $data['max_volume']
            > (float)
                $expectedHarvest
                    ->expected_max_volume
            && trim(
                (string) (
                    $data[
                        'operator_justification'
                    ] ?? ''
                )
            ) === ''
        ) {
            throw ValidationException::withMessages([
                'operator_justification' => (
                    'Commitment melebihi estimasi maksimum '
                    .'Expected Harvest. Tambahkan '
                    .'justification untuk melanjutkan.'
                ),
            ]);
        }
    }

    

    private function resolveActiveProducer(
        User $actor,
        int $producerId,
    ): Producer {
        return $this
            ->resolveActiveProducerForOrganization(
                $actor->organization_id,
                $producerId
            );
    }

    private function resolveActiveProducerForOrganization(
        int $organizationId,
        int $producerId,
    ): Producer {
        $producer = Producer::query()
            ->whereKey($producerId)
            ->where(
                'organization_id',
                $organizationId
            )
            ->where(
                'is_active',
                true
            )
            ->first();

        if (! $producer) {
            throw ValidationException::withMessages([
                'producer_id' => (
                    'Producer tidak tersedia, tidak aktif, '
                    .'atau bukan milik KDKMP ini.'
                ),
            ]);
        }

        return $producer;
    }

    private function resolveExpectedHarvest(
        User $actor,
        mixed $expectedHarvestId,
        Producer $producer,
        DemandForecast $forecast,
    ): ?ExpectedHarvest {
        return $this
            ->resolveExpectedHarvestForOrganization(
                $actor->organization_id,
                $expectedHarvestId,
                $producer,
                $forecast
            );
    }

    private function resolveExpectedHarvestForOrganization(
        int $organizationId,
        mixed $expectedHarvestId,
        Producer $producer,
        DemandForecast $forecast,
    ): ?ExpectedHarvest {
        if (
            $expectedHarvestId === null
            || $expectedHarvestId === ''
        ) {
            return null;
        }

        $expectedHarvest =
            ExpectedHarvest::query()
                ->whereKey(
                    (int) $expectedHarvestId
                )
                ->where(
                    'organization_id',
                    $organizationId
                )
                ->first();

        if (! $expectedHarvest) {
            throw ValidationException::withMessages([
                'expected_harvest_id' => (
                    'Expected Harvest tidak tersedia '
                    .'untuk KDKMP ini.'
                ),
            ]);
        }

        if (
            $expectedHarvest->producer_id
            !== $producer->id
        ) {
            throw ValidationException::withMessages([
                'expected_harvest_id' => (
                    'Expected Harvest tidak berasal '
                    .'dari Producer yang dipilih.'
                ),
            ]);
        }

        if (
            $expectedHarvest->commodity_id
            !== $forecast->commodity_id
        ) {
            throw ValidationException::withMessages([
                'expected_harvest_id' => (
                    'Commodity Expected Harvest tidak '
                    .'sama dengan Demand Forecast.'
                ),
            ]);
        }

        return $expectedHarvest;
    }

    private function assertMakerChecker(
        User $checker,
        CommitmentVersion $version,
    ): void {
        if (
            $version->created_by
            === $checker->id
            || $version->submitted_by
            === $checker->id
        ) {
            throw new AuthorizationException(
                'Maker tidak boleh menjadi checker untuk Commitment Version yang sama.'
            );
        }
    }

    private function assertOperatorActor(
        User $actor,
    ): void {
        if (
            ! $actor->isKdkmpOperator()
            || ! $actor->hasValidIdentityContext()
        ) {
            throw new AuthorizationException(
                'Hanya KDKMP Operator aktif yang dapat mengelola Commitment.'
            );
        }
    }

    private function assertManagerActor(
        User $actor,
    ): void {
        if (
            ! $actor->isKdkmpManager()
            || ! $actor->hasValidIdentityContext()
        ) {
            throw new AuthorizationException(
                'Hanya KDKMP Manager aktif yang dapat mengambil keputusan Commitment.'
            );
        }
    }

    private function assertOwner(
        User $actor,
        SupplyCommitment $commitment,
    ): void {
        if (
            $actor->organization_id
            !== $commitment->organization_id
        ) {
            throw new AuthorizationException(
                'Commitment tersebut bukan milik organisasi KDKMP Anda.'
            );
        }
    }

    private function assertCommitmentActive(
        SupplyCommitment $commitment,
    ): void {
        if (! $commitment->isActive()) {
            throw ValidationException::withMessages([
                'lifecycle_status' => (
                    'Commitment yang CANCELLED atau '
                    .'EXPIRED tidak dapat dimutasi.'
                ),
            ]);
        }
    }

    private function createVersionRecord(
        SupplyCommitment $commitment,
        User $actor,
        int $versionNo,
        array $data,
        ?string $changeReason,
    ): CommitmentVersion {
        return CommitmentVersion::create([
            'commitment_id' =>
                $commitment->id,

            'version_no' =>
                $versionNo,

            'min_volume' =>
                $data['min_volume'],

            'max_volume' =>
                $data['max_volume'],

            'unit_id' =>
                $data['unit_id'],

            'availability_start_at' =>
                $data[
                    'availability_start_at'
                ],

            'availability_end_at' =>
                $data[
                    'availability_end_at'
                ],

            'notes' =>
                $data['notes']
                ?? null,

            'approval_status' =>
                CommitmentApprovalStatus
                    ::DRAFT,

            'change_reason' =>
                $this->nullableTrim(
                    $changeReason
                ),

            'operator_justification' =>
                $this->nullableTrim(
                    $data[
                        'operator_justification'
                    ] ?? null
                ),

            'created_by' =>
                $actor->id,

            'submitted_by' =>
                null,

            'submitted_at' =>
                null,

            'reviewed_by' =>
                null,

            'reviewed_at' =>
                null,

            'review_reason' =>
                null,

            'approved_at' =>
                null,

            'created_at' =>
                now(),
        ]);
    }

    private function versionData(
        CommitmentVersion $version,
    ): array {
        return [
            'min_volume' =>
                (string) $version->min_volume,

            'max_volume' =>
                (string) $version->max_volume,

            'unit_id' =>
                $version->unit_id,

            'availability_start_at' =>
                $version
                    ->availability_start_at
                    ->toDateTimeString(),

            'availability_end_at' =>
                $version
                    ->availability_end_at
                    ->toDateTimeString(),

            'notes' =>
                $version->notes,

            'change_reason' =>
                $version->change_reason,

            'operator_justification' =>
                $version
                    ->operator_justification,
        ];
    }

    private function commitmentSnapshot(
        SupplyCommitment $commitment,
    ): array {
        return [
            'id' =>
                $commitment->id,

            'forecast_id' =>
                $commitment->forecast_id,

            'organization_id' =>
                $commitment->organization_id,

            'producer_id' =>
                $commitment->producer_id,

            'expected_harvest_id' =>
                $commitment
                    ->expected_harvest_id,

            'commodity_id' =>
                $commitment->commodity_id,

            'active_version_id' =>
                $commitment
                    ->active_version_id,

            'lifecycle_status' =>
                $commitment
                    ->lifecycle_status
                    ->value,

            'current_confidence' =>
                $commitment
                    ->current_confidence
                    ?->value,

            'last_confidence_verified_at' =>
                $commitment
                    ->last_confidence_verified_at
                    ?->toIso8601String(),

            'created_by' =>
                $commitment->created_by,

            'cancelled_at' =>
                $commitment
                    ->cancelled_at
                    ?->toIso8601String(),

            'cancellation_reason' =>
                $commitment
                    ->cancellation_reason,

            'expired_at' =>
                $commitment
                    ->expired_at
                    ?->toIso8601String(),
        ];
    }

    private function versionSnapshot(
        CommitmentVersion $version,
    ): array {
        return [
            'id' =>
                $version->id,

            'commitment_id' =>
                $version->commitment_id,

            'version_no' =>
                $version->version_no,

            'min_volume' =>
                (string) $version->min_volume,

            'max_volume' =>
                (string) $version->max_volume,

            'unit_id' =>
                $version->unit_id,

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

            'change_reason' =>
                $version->change_reason,

            'operator_justification' =>
                $version
                    ->operator_justification,

            'created_by' =>
                $version->created_by,

            'submitted_by' =>
                $version->submitted_by,

            'submitted_at' =>
                $version
                    ->submitted_at
                    ?->toIso8601String(),

            'reviewed_by' =>
                $version->reviewed_by,

            'reviewed_at' =>
                $version
                    ->reviewed_at
                    ?->toIso8601String(),

            'review_reason' =>
                $version->review_reason,

            'approved_at' =>
                $version
                    ->approved_at
                    ?->toIso8601String(),

            'created_at' =>
                $version
                    ->created_at
                    ?->toIso8601String(),
        ];
    }

    private function nullableTrim(
        mixed $value,
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim(
            (string) $value
        );

        return $value === ''
            ? null
            : $value;
    }
}