<?php

namespace App\Services\Fallback;

use App\Enums\CommitmentApprovalStatus;
use App\Enums\CommitmentLifecycleStatus;
use App\Enums\SupplyConfidence;
use App\Models\DemandForecast;
use App\Models\FallbackOfferSource;
use App\Models\SupplyCommitment;
use App\Support\FixedScaleDecimal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class FallbackCapacityService
{
    public function availableCapacity(
        SupplyCommitment $commitment,
        DemandForecast $forecast,
        int $supplierOrganizationId,
        ?CarbonInterface $evaluatedAt = null,
    ): string {
        $evaluationTime =
            $evaluatedAt === null
                ? CarbonImmutable::now()
                : CarbonImmutable::instance(
                    $evaluatedAt
                );

        $minimum =
            $this->eligibleMinimum(
                $commitment,
                $forecast,
                $supplierOrganizationId,
                $evaluationTime
            );

        if ($minimum === null) {
            return FixedScaleDecimal::zero()
                ->toString();
        }

        $usedCapacity =
            $this->currentReservedOrAllocatedCapacity(
                $commitment
            );

        return $minimum
            ->subtractToZero(
                $usedCapacity
            )
            ->toString();
    }

    public function isEligibleSource(
        SupplyCommitment $commitment,
        DemandForecast $forecast,
        int $supplierOrganizationId,
        ?CarbonInterface $evaluatedAt = null,
    ): bool {
        $evaluationTime =
            $evaluatedAt === null
                ? CarbonImmutable::now()
                : CarbonImmutable::instance(
                    $evaluatedAt
                );

        return $this->eligibleMinimum(
            $commitment,
            $forecast,
            $supplierOrganizationId,
            $evaluationTime
        ) !== null;
    }

    public function supportsCurrentExposure(
    SupplyCommitment $commitment,
    DemandForecast $forecast,
    int $supplierOrganizationId,
    ?CarbonInterface $evaluatedAt = null,
): bool {
    $evaluationTime =
        $evaluatedAt === null
            ? CarbonImmutable::now()
            : CarbonImmutable::instance(
                $evaluatedAt
            );

    $minimum =
        $this->eligibleMinimum(
            $commitment,
            $forecast,
            $supplierOrganizationId,
            $evaluationTime
        );

    if ($minimum === null) {
        return false;
    }

    $currentExposure =
        $this->currentReservedOrAllocatedCapacity(
            $commitment
        );

    /*
     * Reservation yang sudah dibuat ketika
     * AVAILABLE tetap harus ditopang oleh
     * CURRENT active minimum.
     *
     * Contoh:
     *
     * reserve sebelumnya = 150
     * revised current min = 120
     *
     * maka Accept harus fail closed.
     */
    return $currentExposure->compare(
        $minimum
    ) <= 0;
}

public function currentExposure(
    SupplyCommitment $commitment,
): string {
    return $this
        ->currentReservedOrAllocatedCapacity(
            $commitment
        )
        ->toString();
}

public function activeMinimumSupportsCurrentExposure(
    SupplyCommitment $commitment,
    string $activeMinimum,
): bool {
    $minimum =
        FixedScaleDecimal::from(
            $activeMinimum
        );

    $exposure =
        $this
            ->currentReservedOrAllocatedCapacity(
                $commitment
            );

    /*
     * Current exposure:
     *
     * reserved - released
     *
     * Ini mencakup:
     * - open AVAILABLE reservation;
     * - historical ACCEPTED allocation.
     *
     * Recovery ke GREEN hanya aman jika
     * active minimum masih >= seluruh exposure.
     */
    return $minimum->compare(
        $exposure
    ) >= 0;
}

    private function eligibleMinimum(
        SupplyCommitment $commitment,
        DemandForecast $forecast,
        int $supplierOrganizationId,
        CarbonImmutable $evaluatedAt,
    ): ?FixedScaleDecimal {
        if (
            $commitment->organization_id
            !== $supplierOrganizationId
        ) {
            return null;
        }

        if (
            $commitment->forecast_id
            !== $forecast->id
        ) {
            return null;
        }

        if (
            $commitment->commodity_id
            !== $forecast->commodity_id
        ) {
            return null;
        }

        if (
            $commitment->lifecycle_status
            !== CommitmentLifecycleStatus::ACTIVE
        ) {
            return null;
        }

        if (
            $commitment->current_confidence
            !== SupplyConfidence::GREEN
        ) {
            return null;
        }

        if (
            $commitment->active_version_id
            === null
        ) {
            return null;
        }

        $commitment->loadMissing(
            'activeVersion'
        );

        $version =
            $commitment->activeVersion;

        if (
            ! $version
            || $version->id
                !== $commitment->active_version_id
            || $version->commitment_id
                !== $commitment->id
        ) {
            return null;
        }

        if (
            $version->approval_status
            !== CommitmentApprovalStatus::APPROVED
        ) {
            return null;
        }

        /*
         * MVP tidak melakukan unit conversion.
         */
        if (
            $version->unit_id
            !== $forecast->unit_id
        ) {
            return null;
        }

        $availabilityStart =
            CarbonImmutable::instance(
                $version->availability_start_at
            );

        $availabilityEnd =
            CarbonImmutable::instance(
                $version->availability_end_at
            );

        $requiredStart =
            CarbonImmutable::instance(
                $forecast->required_start_at
            );

        $requiredEnd =
            CarbonImmutable::instance(
                $forecast->required_end_at
            );

        $windowsOverlap =
            $availabilityStart->lte(
                $requiredEnd
            )
            && $availabilityEnd->gte(
                $requiredStart
            );

        if (! $windowsOverlap) {
            return null;
        }

        /*
         * Future availability valid untuk
         * forward planning.
         *
         * Tetapi source yang availability end-nya
         * sudah lewat tidak lagi eligible.
         */
        if (
            $evaluatedAt->gt(
                $availabilityEnd
            )
        ) {
            return null;
        }

        if (
            $evaluatedAt->gt(
                $requiredEnd
            )
        ) {
            return null;
        }

        $minimum =
            FixedScaleDecimal::from(
                (string)
                $version->min_volume
            );

        if ($minimum->isZero()) {
            return null;
        }

        return $minimum;
    }

    private function currentReservedOrAllocatedCapacity(
        SupplyCommitment $commitment,
    ): FixedScaleDecimal {
        /*
         * Ledger semantics:
         *
         * open reserve
         * = reserved - allocated - released
         *
         * current capacity exposure
         * = allocated + open reserve
         * = reserved - released
         *
         * Karena allocated volume pada ACCEPTED
         * offer tetap memakai source capacity.
         */
        $sourceRows =
            FallbackOfferSource::query()
                ->where(
                    'supply_commitment_id',
                    $commitment->id
                )
                ->orderBy('id')
                ->get([
                    'reserved_volume',
                    'allocated_volume',
                    'released_volume',
                ]);

        $totalExposure =
            FixedScaleDecimal::zero();

        foreach ($sourceRows as $source) {
            $reserved =
                FixedScaleDecimal::from(
                    (string)
                    $source->reserved_volume
                );

            $allocated =
                FixedScaleDecimal::from(
                    (string)
                    $source->allocated_volume
                );

            $released =
                FixedScaleDecimal::from(
                    (string)
                    $source->released_volume
                );

            /*
             * Defensive ledger integrity.
             *
             * Jika persisted ledger corrupted,
             * fail closed dengan menganggap source
             * tidak memiliki capacity tambahan.
             */
            if (
                $allocated->compare(
                    $reserved
                ) > 0
                || $released->compare(
                    $reserved
                ) > 0
                || $allocated
                    ->add($released)
                    ->compare(
                        $reserved
                    ) > 0
            ) {
                return FixedScaleDecimal::from(
                    (string)
                    $commitment
                        ->activeVersion
                        ->min_volume
                );
            }

            $exposure =
                $reserved
                    ->subtractToZero(
                        $released
                    );

            $totalExposure =
                $totalExposure->add(
                    $exposure
                );
        }

        return $totalExposure;
    }
}