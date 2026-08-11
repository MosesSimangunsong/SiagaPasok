<?php

namespace App\Services\Commitment;

use App\Enums\AuditSource;
use App\Enums\CommitmentApprovalStatus;
use App\Enums\CommitmentLifecycleStatus;
use App\Models\SupplyCommitment;
use App\Services\Audit\AuditService;
use App\Services\Notification\DerivedForecastStateObservationService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CommitmentLifecycleService
{
    private const AUDIT_EXPIRED =
        'COMMITMENT_EXPIRED';

    public function __construct(
        private readonly AuditService
            $auditService,

        private readonly DerivedForecastStateObservationService
            $derivedStateObservationService,
    ) {
    }

    public function expireIfDue(
        SupplyCommitment $commitment,
        ?CarbonInterface $evaluatedAt = null,
    ): bool {
        $evaluationTime =
            $evaluatedAt === null
                ? CarbonImmutable::now()
                : CarbonImmutable::instance(
                    $evaluatedAt
                );

        return DB::transaction(
            function () use (
                $commitment,
                $evaluationTime,
            ): bool {
                $current =
                    SupplyCommitment::query()
                        ->whereKey(
                            $commitment->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                /*
                 * Retry scheduler harus idempotent.
                 */
                if ($current->isExpired()) {
                    return false;
                }

                /*
                 * CANCELLED terminal dan tidak boleh
                 * ditulis ulang sebagai EXPIRED.
                 */
                if (! $current->isActive()) {
                    return false;
                }

                /*
                 * DRAFT / belum pernah APPROVED tidak
                 * mempunyai operational availability
                 * yang dapat di-expire.
                 */
                if (
                    $current->active_version_id
                    === null
                ) {
                    return false;
                }

                $current->load([
                    'activeVersion',
                    'forecast',
                ]);

                $activeVersion =
                    $current->activeVersion;

                if (
                    ! $activeVersion
                    || $activeVersion->id
                        !== $current
                            ->active_version_id
                    || $activeVersion
                        ->commitment_id
                        !== $current->id
                    || $activeVersion
                        ->approval_status
                        !== CommitmentApprovalStatus
                            ::APPROVED
                ) {
                    throw ValidationException::withMessages([
                        'active_version_id' => (
                            'Active Commitment Version '
                            .'tidak valid untuk lifecycle '
                            .'expiry.'
                        ),
                    ]);
                }

                $availabilityEnd =
                    CarbonImmutable::instance(
                        $activeVersion
                            ->availability_end_at
                    );

                /*
                 * Equality masih valid.
                 *
                 * Ini sama dengan canonical M06/M07:
                 *
                 * T == availability_end_at
                 *     -> masih eligible.
                 *
                 * T > availability_end_at
                 *     -> expired.
                 */
                if (
                    ! $evaluationTime->gt(
                        $availabilityEnd
                    )
                ) {
                    return false;
                }

                $before =
                    $this->snapshot(
                        $current
                    );

                /*
                 * Confidence sengaja dipertahankan.
                 *
                 * Lifecycle EXPIRED sendiri sudah
                 * membuat contribution tidak eligible.
                 * Historical confidence tidak ditulis
                 * ulang hanya karena waktu berlalu.
                 */
                $current->update([
                    'lifecycle_status' =>
                        CommitmentLifecycleStatus
                            ::EXPIRED,

                    'expired_at' =>
                        $evaluationTime,
                ]);

                $reason =
                    'Commitment otomatis EXPIRED '
                    .'karena waktu evaluasi server '
                    .'telah melewati availability_end_at '
                    .$availabilityEnd
                        ->toIso8601String()
                    .'.';

                $this->auditService->record(
                    actor:
                        null,

                    source:
                        AuditSource::SYSTEM,

                    action:
                        self::AUDIT_EXPIRED,

                    entity:
                        $current,

                    previousValue:
                        $before,

                    newValue:
                        $this->snapshot(
                            $current
                        ),

                    reasonNote:
                        $reason,
                );

                /*
                 * Direct source:
                 * Safe Supply dapat turun.
                 *
                 * Accepted fallback source:
                 * historical ACCEPTED tetap ada,
                 * tetapi effective contribution
                 * dapat menjadi 0.
                 *
                 * Observer mendeduplikasi bila state
                 * canonical ternyata tidak berubah.
                 */
                if ($current->forecast) {
                    $this
                        ->derivedStateObservationService
                        ->observeAfterCommit(
                            $current->forecast
                        );
                }

                return true;
            }
        );
    }

    public function expireDueCommitments(
        ?CarbonInterface $evaluatedAt = null,
    ): int {
        $evaluationTime =
            $evaluatedAt === null
                ? CarbonImmutable::now()
                : CarbonImmutable::instance(
                    $evaluatedAt
                );

        $commitmentIds =
            SupplyCommitment::query()
                ->where(
                    'lifecycle_status',
                    CommitmentLifecycleStatus
                        ::ACTIVE
                        ->value
                )
                ->whereNotNull(
                    'active_version_id'
                )
                ->whereHas(
                    'activeVersion',
                    fn ($query) =>
                        $query
                            ->where(
                                'approval_status',
                                CommitmentApprovalStatus
                                    ::APPROVED
                                    ->value
                            )
                            ->where(
                                'availability_end_at',
                                '<',
                                $evaluationTime
                            )
                )
                ->orderBy('id')
                ->pluck('id');

        $expiredCount = 0;

        foreach (
            $commitmentIds
            as $commitmentId
        ) {
            $commitment =
                SupplyCommitment::query()
                    ->find(
                        $commitmentId
                    );

            if (! $commitment) {
                continue;
            }

            try {
                if (
                    $this->expireIfDue(
                        $commitment,
                        $evaluationTime
                    )
                ) {
                    $expiredCount++;
                }
            } catch (ValidationException) {
                /*
                 * Candidate state dapat berubah
                 * sebelum row lock berhasil.
                 *
                 * Corrupted/non-current candidate
                 * tidak boleh dipaksa menjadi EXPIRED.
                 */
                continue;
            }
        }

        return $expiredCount;
    }

    private function snapshot(
        SupplyCommitment $commitment,
    ): array {
        return [
            'id' =>
                $commitment->id,

            'forecast_id' =>
                $commitment->forecast_id,

            'organization_id' =>
                $commitment
                    ->organization_id,

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
}