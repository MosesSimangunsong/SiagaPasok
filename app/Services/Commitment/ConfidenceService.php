<?php

namespace App\Services\Commitment;

use App\Enums\AuditSource;
use App\Enums\CommitmentApprovalStatus;
use App\Enums\RecoveryRequestStatus;
use App\Enums\SupplyConfidence;
use App\Models\CommitmentConfidenceEvent;
use App\Models\CommitmentVersion;
use App\Models\ConfidenceRecoveryRequest;
use App\Models\SupplyCommitment;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Fallback\FallbackCapacityService;
use App\Services\Notification\OperationalNotificationService;
use App\Services\Notification\DerivedForecastStateObservationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfidenceService
{
    private const AUDIT_CONFIDENCE_DOWNGRADED =
        'COMMITMENT_CONFIDENCE_DOWNGRADED';

    private const AUDIT_RECOVERY_REQUESTED =
        'CONFIDENCE_RECOVERY_REQUESTED';

    private const AUDIT_RECOVERY_APPROVED =
        'CONFIDENCE_RECOVERY_APPROVED';

    private const AUDIT_RECOVERY_REJECTED =
        'CONFIDENCE_RECOVERY_REJECTED';

    private const AUDIT_CONFIDENCE_RECOVERED =
        'COMMITMENT_CONFIDENCE_RECOVERED';

    public function __construct(
    private readonly AuditService
        $auditService,

    private readonly FallbackCapacityService
        $fallbackCapacity,

    private readonly OperationalNotificationService
        $operationalNotificationService,

    private readonly DerivedForecastStateObservationService
        $derivedStateObservationService,
) {
}

    public function downgrade(
        User $actor,
        SupplyCommitment $commitment,
        SupplyConfidence $toConfidence,
        ?string $reasonCode,
        string $reasonNote,
    ): SupplyCommitment {
        $this->assertOperatorActor($actor);

        $reasonNote = trim($reasonNote);

        if ($reasonNote === '') {
            throw ValidationException::withMessages([
                'reason_note' =>
                    'Alasan perubahan confidence wajib diisi.',
            ]);
        }

        return $this->performDowngrade(
            actor: $actor,
            source: AuditSource::USER,
            commitment: $commitment,
            toConfidence: $toConfidence,
            reasonCode: $this->nullableTrim(
                $reasonCode
            ),
            reasonNote: $reasonNote,
            organizationId:
                $actor->organization_id,
        );
    }

    public function downgradeBySystem(
        SupplyCommitment $commitment,
        SupplyConfidence $toConfidence,
        string $reasonCode,
        string $reasonNote,
    ): SupplyCommitment {
        $reasonCode = trim($reasonCode);
        $reasonNote = trim($reasonNote);

        if ($reasonCode === '') {
            throw ValidationException::withMessages([
                'reason_code' => (
                    'System confidence downgrade '
                    .'memerlukan reason code.'
                ),
            ]);
        }

        if ($reasonNote === '') {
            throw ValidationException::withMessages([
                'reason_note' => (
                    'System confidence downgrade '
                    .'memerlukan reason.'
                ),
            ]);
        }

        return $this->performDowngrade(
            actor: null,
            source: AuditSource::SYSTEM,
            commitment: $commitment,
            toConfidence: $toConfidence,
            reasonCode: $reasonCode,
            reasonNote: $reasonNote,
            organizationId:
                $commitment->organization_id,
        );
    }


    public function downgradeStaleIfDue(
    SupplyCommitment $commitment,
    int $freshnessIntervalHours,
): bool {
    if ($freshnessIntervalHours <= 0) {
        throw ValidationException::withMessages([
            'freshness_interval_hours' => (
                'Freshness interval harus lebih besar '
                .'dari 0 jam.'
            ),
        ]);
    }

    return DB::transaction(
        function () use (
            $commitment,
            $freshnessIntervalHours,
        ): bool {
            $current =
                SupplyCommitment::query()
                    ->whereKey(
                        $commitment->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            /*
             * Scheduler boleh menemukan candidate
             * berdasarkan snapshot lama. Setelah lock,
             * semua condition harus diperiksa ulang.
             */
            if (! $current->isActive()) {
                return false;
            }

            if (
                $current->current_confidence
                !== SupplyConfidence::GREEN
            ) {
                return false;
            }

            if (
                $current
                    ->last_confidence_verified_at
                === null
            ) {
                return false;
            }

            $this->resolveValidActiveVersion(
                $current
            );

            $staleAt =
                $current
                    ->last_confidence_verified_at
                    ->copy()
                    ->addHours(
                        $freshnessIntervalHours
                    );

            if (now()->lt($staleAt)) {
                return false;
            }

            $before =
                $this->commitmentSnapshot(
                    $current
                );

            $reasonCode =
                'STALE_DATA';

            $reasonNote = (
                'Confidence diturunkan otomatis karena '
                .'verifikasi terakhir telah melewati '
                .$freshnessIntervalHours
                .' jam freshness interval Forecast.'
            );

            $current->update([
                'current_confidence' =>
                    SupplyConfidence::YELLOW,
            ]);

            $event =
    CommitmentConfidenceEvent::create([
                'commitment_id' =>
                    $current->id,

                'from_confidence' =>
                    SupplyConfidence::GREEN,

                'to_confidence' =>
                    SupplyConfidence::YELLOW,

                'source' =>
                    AuditSource::SYSTEM,

                'reason_code' =>
                    $reasonCode,

                'reason_note' =>
                    $reasonNote,

                'actor_user_id' =>
                    null,

                'occurred_at' =>
                    now(),
            ]);

            $this->auditService->record(
                actor: null,
                source: AuditSource::SYSTEM,
                action:
                    self::AUDIT_CONFIDENCE_DOWNGRADED,
                entity: $current,
                previousValue: $before,
                newValue:
                    $this->commitmentSnapshot(
                        $current
                    ),
                reasonNote:
                    $reasonNote,
            );

            $this->operationalNotificationService
    ->staleCommitmentDetected(
        $current,
        $event
    );

    $this->derivedStateObservationService
    ->observeAfterCommit(
        $current->forecast
    );

            return true;
        }
    );
}


    public function requestRecovery(
        User $actor,
        SupplyCommitment $commitment,
        string $reason,
    ): ConfidenceRecoveryRequest {
        $this->assertOperatorActor($actor);

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'recovery_reason' =>
                    'Alasan pemulihan wajib diisi.',
            ]);
        }

        return DB::transaction(
            function () use (
                $actor,
                $commitment,
                $reason,
            ): ConfidenceRecoveryRequest {
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

                if (
                    $current->current_confidence
                    === SupplyConfidence::RED
                ) {
                    throw ValidationException::withMessages([
                        'current_confidence' => (
                            'Commitment RED bersifat terminal '
                            .'dan tidak dapat dipulihkan. '
                            .'Buat Commitment baru jika supply '
                            .'baru tersedia.'
                        ),
                    ]);
                }

                if (
                    $current->current_confidence
                    !== SupplyConfidence::YELLOW
                ) {
                    throw ValidationException::withMessages([
                        'current_confidence' => (
                            'Recovery hanya dapat diajukan '
                            .'untuk Commitment YELLOW.'
                        ),
                    ]);
                }

                $activeVersion =
                    $this->resolveValidActiveVersion(
                        $current
                    );

                $this->assertNoOpenRevision(
                    $current
                );

                $existing =
                    ConfidenceRecoveryRequest::query()
                        ->where(
                            'commitment_id',
                            $current->id
                        )
                        ->where(
                            'status',
                            RecoveryRequestStatus
                                ::PENDING_APPROVAL
                                ->value
                        )
                        ->first();

                if ($existing) {
    throw ValidationException::withMessages([
        'recovery' => (
            'Masih terdapat Recovery Request '
            .'PENDING_APPROVAL untuk Commitment ini. '
            .'Tunggu keputusan Manager sebelum '
            .'mengajukan Recovery baru.'
        ),
    ]);
}

                $recovery =
                    ConfidenceRecoveryRequest::create([
                        'commitment_id' =>
                            $current->id,

                        'commitment_version_id' =>
                            $activeVersion->id,

                        'status' =>
                            RecoveryRequestStatus
                                ::PENDING_APPROVAL,

                        'recovery_reason' =>
                            $reason,

                        'requested_by' =>
                            $actor->id,

                        'requested_at' =>
                            now(),

                        'reviewed_by' =>
                            null,

                        'reviewed_at' =>
                            null,

                        'review_reason' =>
                            null,
                    ]);

                $this->auditService->record(
                    actor: $actor,
                    source: AuditSource::USER,
                    action:
                        self::AUDIT_RECOVERY_REQUESTED,
                    entity: $recovery,
                    previousValue: null,
                    newValue:
                        $this->recoverySnapshot(
                            $recovery
                        ),
                    reasonNote: $reason,
                );

                $this->operationalNotificationService
    ->confidenceRecoveryApprovalRequired(
        $current,
        $recovery
    );

                return $recovery->load([
                    'commitment.activeVersion',
                    'commitmentVersion',
                    'requestedBy',
                ]);
            }
        );
    }

    public function approveRecovery(
        User $actor,
        ConfidenceRecoveryRequest $recovery,
        ?string $reviewReason = null,
    ): ConfidenceRecoveryRequest {
        $this->assertManagerActor($actor);

        $reviewReason =
            $this->nullableTrim(
                $reviewReason
            );

        return DB::transaction(
            function () use (
                $actor,
                $recovery,
                $reviewReason,
            ): ConfidenceRecoveryRequest {
                $currentRecovery =
                    ConfidenceRecoveryRequest::query()
                        ->whereKey(
                            $recovery->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $commitment =
                    SupplyCommitment::query()
                        ->whereKey(
                            $currentRecovery
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
                    $currentRecovery->isApproved()
                ) {
                    return $currentRecovery;
                }

                if (
                    ! $currentRecovery
                        ->isPendingApproval()
                ) {
                    throw ValidationException::withMessages([
                        'status' => (
                            'Hanya Recovery Request '
                            .'PENDING_APPROVAL yang dapat '
                            .'disetujui.'
                        ),
                    ]);
                }

                $this->assertRecoveryMakerChecker(
                    $actor,
                    $currentRecovery
                );

                if (
                    $commitment->current_confidence
                    === SupplyConfidence::RED
                ) {
                    throw ValidationException::withMessages([
                        'current_confidence' => (
                            'Commitment RED bersifat terminal '
                            .'dan tidak dapat dipulihkan.'
                        ),
                    ]);
                }

                if (
                    $commitment->current_confidence
                    !== SupplyConfidence::YELLOW
                ) {
                    throw ValidationException::withMessages([
                        'current_confidence' => (
                            'Recovery hanya dapat disetujui '
                            .'ketika Commitment masih YELLOW.'
                        ),
                    ]);
                }

                $activeVersion =
                    $this->resolveValidActiveVersion(
                        $commitment
                    );

                if (
                    $activeVersion->id
                    !== $currentRecovery
                        ->commitment_version_id
                ) {
                    throw ValidationException::withMessages([
                        'commitment_version_id' => (
                            'Active Commitment Version telah '
                            .'berubah sejak Recovery Request '
                            .'dibuat. Ajukan Recovery baru '
                            .'untuk version yang aktif.'
                        ),
                    ]);
                }

                /*
                 * Prevent old approved volume becoming GREEN
                 * while a known-risk revision is still
                 * DRAFT/PENDING_APPROVAL.
                 */
                $this->assertNoOpenRevision(
                    $commitment
                );

                /*
 * M07 / C19:
 *
 * Commitment YELLOW boleh mempunyai revised
 * active minimum yang lebih rendah karena
 * sistem harus mampu merekam realitas supply
 * yang memburuk.
 *
 * Tetapi commitment tersebut TIDAK boleh
 * kembali GREEN jika active minimum baru
 * tidak lagi menopang reservation/allocation
 * fallback yang masih melekat padanya.
 */
if (
    ! $this->fallbackCapacity
        ->activeMinimumSupportsCurrentExposure(
            $commitment,
            (string)
            $activeVersion->min_volume
        )
) {
    $currentExposure =
        $this->fallbackCapacity
            ->currentExposure(
                $commitment
            );

    throw ValidationException::withMessages([
        'recovery' => (
            'Recovery ke GREEN tidak dapat '
            .'disetujui karena active minimum '
            .(string) $activeVersion->min_volume
            .' lebih kecil dari fallback capacity '
            .'exposure '
            .$currentExposure
            .'.'
        ),
    ]);
}

                $beforeRecovery =
                    $this->recoverySnapshot(
                        $currentRecovery
                    );

                $beforeCommitment =
                    $this->commitmentSnapshot(
                        $commitment
                    );

                $currentRecovery->update([
                    'status' =>
                        RecoveryRequestStatus
                            ::APPROVED,

                    'reviewed_by' =>
                        $actor->id,

                    'reviewed_at' =>
                        now(),

                    'review_reason' =>
                        $reviewReason,
                ]);

                $commitment->update([
                    'current_confidence' =>
                        SupplyConfidence::GREEN,

                    'last_confidence_verified_at' =>
                        now(),
                ]);

                CommitmentConfidenceEvent::create([
                    'commitment_id' =>
                        $commitment->id,

                    'from_confidence' =>
                        SupplyConfidence::YELLOW,

                    'to_confidence' =>
                        SupplyConfidence::GREEN,

                    'source' =>
                        AuditSource::USER,

                    'reason_code' =>
                        'RECOVERY_APPROVED',

                    'reason_note' =>
                        $currentRecovery
                            ->recovery_reason,

                    'actor_user_id' =>
                        $actor->id,

                    'occurred_at' =>
                        now(),
                ]);

                $this->auditService->record(
                    actor: $actor,
                    source: AuditSource::USER,
                    action:
                        self::AUDIT_RECOVERY_APPROVED,
                    entity: $currentRecovery,
                    previousValue:
                        $beforeRecovery,
                    newValue:
                        $this->recoverySnapshot(
                            $currentRecovery
                        ),
                    reasonNote:
                        $reviewReason,
                );

                $this->auditService->record(
                    actor: $actor,
                    source: AuditSource::USER,
                    action:
                        self::AUDIT_CONFIDENCE_RECOVERED,
                    entity: $commitment,
                    previousValue:
                        $beforeCommitment,
                    newValue:
                        $this->commitmentSnapshot(
                            $commitment
                        ),
                    reasonNote:
                        $currentRecovery
                            ->recovery_reason,
                );

                /*
 * YELLOW -> GREEN dapat mengembalikan Safe Supply,
 * menutup Shortfall, dan membuat RFP tercapai lagi.
 */
$this->derivedStateObservationService
    ->observeAfterCommit(
        $commitment->forecast
    );

                return $currentRecovery
                    ->refresh()
                    ->load([
                        'commitment.activeVersion',
                        'commitmentVersion',
                        'requestedBy',
                        'reviewedBy',
                    ]);
            }
        );
    }

    public function rejectRecovery(
        User $actor,
        ConfidenceRecoveryRequest $recovery,
        string $reviewReason,
    ): ConfidenceRecoveryRequest {
        $this->assertManagerActor($actor);

        $reviewReason = trim(
            $reviewReason
        );

        if ($reviewReason === '') {
            throw ValidationException::withMessages([
                'review_reason' =>
                    'Alasan penolakan Recovery wajib diisi.',
            ]);
        }

        return DB::transaction(
            function () use (
                $actor,
                $recovery,
                $reviewReason,
            ): ConfidenceRecoveryRequest {
                $currentRecovery =
                    ConfidenceRecoveryRequest::query()
                        ->whereKey(
                            $recovery->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $commitment =
                    SupplyCommitment::query()
                        ->whereKey(
                            $currentRecovery
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
                    $currentRecovery->isRejected()
                ) {
                    return $currentRecovery;
                }

                if (
                    ! $currentRecovery
                        ->isPendingApproval()
                ) {
                    throw ValidationException::withMessages([
                        'status' => (
                            'Hanya Recovery Request '
                            .'PENDING_APPROVAL yang dapat '
                            .'ditolak.'
                        ),
                    ]);
                }

                $this->assertRecoveryMakerChecker(
                    $actor,
                    $currentRecovery
                );

                $before =
                    $this->recoverySnapshot(
                        $currentRecovery
                    );

                $currentRecovery->update([
                    'status' =>
                        RecoveryRequestStatus
                            ::REJECTED,

                    'reviewed_by' =>
                        $actor->id,

                    'reviewed_at' =>
                        now(),

                    'review_reason' =>
                        $reviewReason,
                ]);

                /*
                 * Confidence sengaja tidak berubah.
                 * Rejected Recovery = tetap YELLOW.
                 */

                $this->auditService->record(
                    actor: $actor,
                    source: AuditSource::USER,
                    action:
                        self::AUDIT_RECOVERY_REJECTED,
                    entity: $currentRecovery,
                    previousValue: $before,
                    newValue:
                        $this->recoverySnapshot(
                            $currentRecovery
                        ),
                    reasonNote:
                        $reviewReason,
                );

                return $currentRecovery
                    ->refresh();
            }
        );
    }

    private function performDowngrade(
        ?User $actor,
        AuditSource $source,
        SupplyCommitment $commitment,
        SupplyConfidence $toConfidence,
        ?string $reasonCode,
        string $reasonNote,
        int $organizationId,
    ): SupplyCommitment {
        if (
            ! in_array(
                $toConfidence,
                [
                    SupplyConfidence::YELLOW,
                    SupplyConfidence::RED,
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'to_confidence' => (
                    'Downgrade hanya dapat menuju '
                    .'YELLOW atau RED.'
                ),
            ]);
        }

        return DB::transaction(
            function () use (
                $actor,
                $source,
                $commitment,
                $toConfidence,
                $reasonCode,
                $reasonNote,
                $organizationId,
            ): SupplyCommitment {
                $current =
                    SupplyCommitment::query()
                        ->whereKey(
                            $commitment->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $current->organization_id
                    !== $organizationId
                ) {
                    throw new AuthorizationException(
                        'Commitment berada di luar organization scope.'
                    );
                }

                if ($actor) {
                    $this->assertOwner(
                        $actor,
                        $current
                    );
                }

                $this->assertCommitmentActive(
                    $current
                );

                $this->resolveValidActiveVersion(
                    $current
                );

                $fromConfidence =
                    $current->current_confidence;

                /*
                 * Repeated command is idempotent.
                 * No duplicate event/audit is created.
                 */
                if (
                    $fromConfidence
                    === $toConfidence
                ) {
                    return $current;
                }

                if (
                    $fromConfidence === null
                ) {
                    throw ValidationException::withMessages([
                        'current_confidence' => (
                            'Commitment belum memiliki '
                            .'approved confidence state.'
                        ),
                    ]);
                }

                if (
                    $fromConfidence
                    === SupplyConfidence::RED
                ) {
                    throw ValidationException::withMessages([
                        'current_confidence' => (
                            'Commitment RED bersifat terminal.'
                        ),
                    ]);
                }

                $allowed =
                    match ($fromConfidence) {
                        SupplyConfidence::GREEN =>
                            in_array(
                                $toConfidence,
                                [
                                    SupplyConfidence::YELLOW,
                                    SupplyConfidence::RED,
                                ],
                                true
                            ),

                        SupplyConfidence::YELLOW =>
                            $toConfidence
                            === SupplyConfidence::RED,

                        SupplyConfidence::RED =>
                            false,
                    };

                if (! $allowed) {
                    throw ValidationException::withMessages([
                        'to_confidence' => (
                            "Transition {$fromConfidence->value}"
                            ." → {$toConfidence->value} "
                            .'tidak diizinkan.'
                        ),
                    ]);
                }

                $before =
                    $this->commitmentSnapshot(
                        $current
                    );

                $current->update([
                    'current_confidence' =>
                        $toConfidence,
                ]);

                $event =
    CommitmentConfidenceEvent::create([
        'commitment_id' =>
            $current->id,

        'from_confidence' =>
            $fromConfidence,

        'to_confidence' =>
            $toConfidence,

        'source' =>
            $source,

        'reason_code' =>
            $reasonCode,

        'reason_note' =>
            $reasonNote,

        'actor_user_id' =>
            $actor?->id,

        'occurred_at' =>
            now(),
    ]);

                $this->auditService->record(
                    actor: $actor,
                    source: $source,
                    action:
                        self::AUDIT_CONFIDENCE_DOWNGRADED,
                    entity: $current,
                    previousValue: $before,
                    newValue:
                        $this->commitmentSnapshot(
                            $current
                        ),
                    reasonNote:
                        $reasonNote,
                );

                /*
 * Generic explicit/system downgrade.
 *
 * Dedicated stale evaluator memiliki notification
 * type sendiri dan tidak melewati block ini.
 */
$this->operationalNotificationService
    ->supplyConfidenceDowngraded(
        $current,
        $event
    );

    $this->derivedStateObservationService
    ->observeAfterCommit(
        $current->forecast
    );

    if ($fromConfidence === $toConfidence) {
    return $current;
}

                return $current->refresh();
            }
        );
    }

    private function resolveValidActiveVersion(
        SupplyCommitment $commitment,
    ): CommitmentVersion {
        if (
            $commitment->active_version_id === null
        ) {
            throw ValidationException::withMessages([
                'active_version_id' => (
                    'Commitment belum memiliki '
                    .'approved active version.'
                ),
            ]);
        }

        $version =
            CommitmentVersion::query()
                ->whereKey(
                    $commitment->active_version_id
                )
                ->where(
                    'commitment_id',
                    $commitment->id
                )
                ->first();

        if (
            ! $version
            || $version->approval_status
                !== CommitmentApprovalStatus::APPROVED
        ) {
            throw ValidationException::withMessages([
                'active_version_id' => (
                    'Active Commitment Version '
                    .'tidak valid atau belum APPROVED.'
                ),
            ]);
        }

        return $version;
    }

    private function assertNoOpenRevision(
        SupplyCommitment $commitment,
    ): void {
        $hasOpenRevision =
            CommitmentVersion::query()
                ->where(
                    'commitment_id',
                    $commitment->id
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

        if ($hasOpenRevision) {
            throw ValidationException::withMessages([
                'recovery' => (
                    'Recovery belum dapat diproses karena '
                    .'masih terdapat revision DRAFT atau '
                    .'PENDING_APPROVAL.'
                ),
            ]);
        }
    }

    private function assertRecoveryMakerChecker(
        User $checker,
        ConfidenceRecoveryRequest $recovery,
    ): void {
        if (
            $recovery->requested_by
            === $checker->id
        ) {
            throw new AuthorizationException(
                'Requester Recovery tidak boleh menjadi checker untuk request yang sama.'
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
                'Hanya KDKMP Operator aktif yang dapat mengelola confidence.'
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
                'Hanya KDKMP Manager aktif yang dapat mengambil keputusan Recovery.'
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
                    .'EXPIRED tidak dapat mengubah confidence.'
                ),
            ]);
        }
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

    private function recoverySnapshot(
        ConfidenceRecoveryRequest $recovery,
    ): array {
        return [
            'id' =>
                $recovery->id,

            'commitment_id' =>
                $recovery->commitment_id,

            'commitment_version_id' =>
                $recovery
                    ->commitment_version_id,

            'status' =>
                $recovery->status->value,

            'recovery_reason' =>
                $recovery->recovery_reason,

            'requested_by' =>
                $recovery->requested_by,

            'requested_at' =>
                $recovery
                    ->requested_at
                    ?->toIso8601String(),

            'reviewed_by' =>
                $recovery->reviewed_by,

            'reviewed_at' =>
                $recovery
                    ->reviewed_at
                    ?->toIso8601String(),

            'review_reason' =>
                $recovery->review_reason,
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