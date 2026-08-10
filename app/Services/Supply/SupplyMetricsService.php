<?php

namespace App\Services\Supply;

use App\Enums\CommitmentApprovalStatus;
use App\Enums\CommitmentLifecycleStatus;
use App\Enums\ForecastStatus;
use App\Enums\NetworkRole;
use App\Enums\SupplyConfidence;
use App\Enums\FallbackOfferStatus;
use App\Models\FallbackOffer;
use App\Models\CommitmentVersion;
use App\Models\DemandForecast;
use App\Models\SupplyCommitment;
use App\Models\SupplyNetworkLink;
use App\Support\FixedScaleDecimal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

final class SupplyMetricsService
{
    public const QUANTITY_SCALE = 6;

    public const COVERAGE_SCALE = 2;

    public function calculate(
    DemandForecast $forecast,
    ?CarbonInterface $evaluatedAt = null,
): SupplyMetricsResult {
    $metrics =
        $this->calculateCoreVolumeMetrics(
            $forecast,
            $evaluatedAt
        );

    $coveragePercent =
        $metrics[
            'total_safe_supply'
        ]->percentageOf(
            $metrics['demand_target'],
            self::COVERAGE_SCALE
        );

    return new SupplyMetricsResult(
        forecastId:
            $metrics['forecast']->id,

        evaluatedAt:
            $metrics['evaluated_at'],

        unitId:
            $metrics['forecast']->unit_id,

        demandTarget:
            $metrics[
                'demand_target'
            ]->toString(),

        directSafeSupply:
            $metrics[
                'direct_safe_supply'
            ]->toString(),

        atRiskSupply:
            $metrics[
                'at_risk_supply'
            ]->toString(),

        fallbackSafeSupply:
            $metrics[
                'fallback_safe_supply'
            ]->toString(),

        totalSafeSupply:
            $metrics[
                'total_safe_supply'
            ]->toString(),

        coveragePercent:
            $coveragePercent,

        shortfall:
            $metrics[
                'shortfall'
            ]->toString(),

        surplus:
            $metrics[
                'surplus'
            ]->toString(),

        contributorOrganizationIds:
            $metrics[
                'contributor_organization_ids'
            ],

        volumeReady:
            $metrics[
                'volume_ready'
            ],
    );
}

    public function calculateDirectSafeSupply(
    DemandForecast $forecast,
    ?CarbonInterface $evaluatedAt = null,
): string {
    return $this
        ->calculateCoreVolumeMetrics(
            $forecast,
            $evaluatedAt
        )[
            'direct_safe_supply'
        ]
        ->toString();
}

public function calculateAtRiskSupply(
    DemandForecast $forecast,
    ?CarbonInterface $evaluatedAt = null,
): string {
    return $this
        ->calculateCoreVolumeMetrics(
            $forecast,
            $evaluatedAt
        )[
            'at_risk_supply'
        ]
        ->toString();
}

    public function calculateFallbackSafeSupply(
    DemandForecast $forecast,
    ?CarbonInterface $evaluatedAt = null,
): string {
    $currentForecast =
        $this->resolvePublishedForecast(
            $forecast
        );

    $evaluationTime =
        $this->resolveEvaluationTime(
            $evaluatedAt
        );

    return $this
        ->calculateEffectiveFallbackMetricsForResolvedForecast(
            $currentForecast,
            $evaluationTime
        )[
            'fallback_safe_supply'
        ]
        ->toString();
}

    public function calculateTotalSafeSupply(
        DemandForecast $forecast,
        ?CarbonInterface $evaluatedAt = null,
    ): string {
        return $this
            ->calculateCoreVolumeMetrics(
                $forecast,
                $evaluatedAt
            )['total_safe_supply']
            ->toString();
    }

    public function calculateShortfall(
        DemandForecast $forecast,
        ?CarbonInterface $evaluatedAt = null,
    ): string {
        return $this
            ->calculateCoreVolumeMetrics(
                $forecast,
                $evaluatedAt
            )['shortfall']
            ->toString();
    }

    public function calculateSurplus(
        DemandForecast $forecast,
        ?CarbonInterface $evaluatedAt = null,
    ): string {
        return $this
            ->calculateCoreVolumeMetrics(
                $forecast,
                $evaluatedAt
            )['surplus']
            ->toString();
    }

    public function calculateVolumeReady(
        DemandForecast $forecast,
        ?CarbonInterface $evaluatedAt = null,
    ): bool {
        return $this
            ->calculateCoreVolumeMetrics(
                $forecast,
                $evaluatedAt
            )['volume_ready'];
    }

    public function calculateCoveragePercent(
    DemandForecast $forecast,
    ?CarbonInterface $evaluatedAt = null,
): ?string {
    $metrics =
        $this->calculateCoreVolumeMetrics(
            $forecast,
            $evaluatedAt
        );

    /*
     * Coverage adalah informational percentage.
     *
     * percentageOf():
     * - denominator 0 => null
     * - capped 100%
     * - ROUND HALF UP
     * - tanpa binary floating point
     *
     * Volume Ready TIDAK bergantung pada nilai
     * Coverage yang sudah dibulatkan.
     */
    return $metrics[
        'total_safe_supply'
    ]->percentageOf(
        $metrics['demand_target'],
        self::COVERAGE_SCALE
    );
}

/**
 * @return array<int, int>
 */
/**
 * @return array<int, int>
 */
public function calculateContributorOrganizationIds(
    DemandForecast $forecast,
    ?CarbonInterface $evaluatedAt = null,
): array {
    return $this
        ->calculateCoreVolumeMetrics(
            $forecast,
            $evaluatedAt
        )[
            'contributor_organization_ids'
        ];
}

    /**
     * @return array{
     *     forecast: DemandForecast,
     *     evaluated_at: CarbonImmutable,
     *     demand_target: FixedScaleDecimal,
     *     direct_safe_supply: FixedScaleDecimal,
     *     fallback_safe_supply: FixedScaleDecimal,
     *     total_safe_supply: FixedScaleDecimal,
     *     shortfall: FixedScaleDecimal,
     *     surplus: FixedScaleDecimal,
     *     volume_ready: bool
     * }
     */
    /**
 * @return array{
 *     forecast: DemandForecast,
 *     evaluated_at: CarbonImmutable,
 *     demand_target: FixedScaleDecimal,
 *     direct_safe_supply: FixedScaleDecimal,
 *     at_risk_supply: FixedScaleDecimal,
 *     fallback_safe_supply: FixedScaleDecimal,
 *     total_safe_supply: FixedScaleDecimal,
 *     shortfall: FixedScaleDecimal,
 *     surplus: FixedScaleDecimal,
 *     contributor_organization_ids: array<int, int>,
 *     volume_ready: bool
 * }
 */
private function calculateCoreVolumeMetrics(
    DemandForecast $forecast,
    ?CarbonInterface $evaluatedAt,
): array {
    /*
     * Forecast dan evaluation instant masing-masing
     * di-resolve satu kali untuk satu invocation.
     */
    $currentForecast =
        $this->resolvePublishedForecast(
            $forecast
        );

    $evaluationTime =
        $this->resolveEvaluationTime(
            $evaluatedAt
        );

    $demandTarget =
        FixedScaleDecimal::from(
            (string)
            $currentForecast->target_volume
        );

    $primaryBuckets =
        $this->calculatePrimarySupplyBuckets(
            $currentForecast,
            $evaluationTime
        );

    $directSafeSupply =
        $primaryBuckets[
            'direct_safe_supply'
        ];

    $atRiskSupply =
        $primaryBuckets[
            'at_risk_supply'
        ];

    $fallbackMetrics =
    $this
        ->calculateEffectiveFallbackMetricsForResolvedForecast(
            $currentForecast,
            $evaluationTime
        );

$fallbackSafeSupply =
    $fallbackMetrics[
        'fallback_safe_supply'
    ];

$totalSafeSupply =
    $directSafeSupply->add(
        $fallbackSafeSupply
    );

    $shortfall =
        $demandTarget
            ->subtractToZero(
                $totalSafeSupply
            );

    $surplus =
        $totalSafeSupply
            ->subtractToZero(
                $demandTarget
            );

    /*
     * Readiness memakai exact quantity.
     * Coverage rounding tidak memengaruhi gate ini.
     */
    $volumeReady =
        $totalSafeSupply
            ->greaterThanOrEqual(
                $demandTarget
            );
    $contributorOrganizationIds =
    array_values(
        array_unique([
            ...$primaryBuckets[
                'contributor_organization_ids'
            ],

            ...$fallbackMetrics[
                'contributor_organization_ids'
            ],
        ])
    );

/*
 * Canonical result harus deterministic.
 */
sort(
    $contributorOrganizationIds,
    SORT_NUMERIC
);

    return [
        'forecast' =>
            $currentForecast,

        'evaluated_at' =>
            $evaluationTime,

        'demand_target' =>
            $demandTarget,

        'direct_safe_supply' =>
            $directSafeSupply,

        'at_risk_supply' =>
            $atRiskSupply,

        'fallback_safe_supply' =>
            $fallbackSafeSupply,

        'total_safe_supply' =>
            $totalSafeSupply,

        'shortfall' =>
            $shortfall,

        'surplus' =>
            $surplus,

        'contributor_organization_ids' =>
    $contributorOrganizationIds,

        'volume_ready' =>
            $volumeReady,
    ];
}

    /**
 * @return array{
 *     direct_safe_supply: FixedScaleDecimal,
 *     at_risk_supply: FixedScaleDecimal,
 *     contributor_organization_ids: array<int, int>
 * }
 */
private function calculatePrimarySupplyBuckets(
    DemandForecast $forecast,
    CarbonImmutable $evaluatedAt,
): array {
    $zeroResult = [
        'direct_safe_supply' =>
            FixedScaleDecimal::zero(),

        'at_risk_supply' =>
            FixedScaleDecimal::zero(),

        'contributor_organization_ids' =>
            [],
    ];

    /*
     * Forecast operational boundary sudah lewat.
     */
    if (
        $evaluatedAt->gt(
            CarbonImmutable::instance(
                $forecast->required_end_at
            )
        )
    ) {
        return $zeroResult;
    }

    $primaryOrganizationId =
        $this->resolveActivePrimaryOrganizationId(
            $forecast
        );

    /*
     * Fail closed bila exactly-one PRIMARY
     * invariant tidak terpenuhi.
     */
    if ($primaryOrganizationId === null) {
        return $zeroResult;
    }

    /*
     * GREEN dan YELLOW dibaca dalam query yang sama.
     *
     * Ini membuat satu canonical calculation
     * mempunyai satu commitment-state read,
     * bukan membaca GREEN lalu YELLOW secara
     * terpisah.
     */
    $commitments =
        SupplyCommitment::query()
            ->where(
                'forecast_id',
                $forecast->id
            )
            ->where(
                'organization_id',
                $primaryOrganizationId
            )
            ->where(
                'commodity_id',
                $forecast->commodity_id
            )
            ->where(
                'lifecycle_status',
                CommitmentLifecycleStatus
                    ::ACTIVE
                    ->value
            )
            ->whereIn(
                'current_confidence',
                [
                    SupplyConfidence::GREEN
                        ->value,

                    SupplyConfidence::YELLOW
                        ->value,
                ]
            )
            ->whereNotNull(
                'active_version_id'
            )
            ->with([
                'activeVersion',
            ])
            ->orderBy('id')
            ->get();

    $directSafe =
        FixedScaleDecimal::zero();

    $atRisk =
        FixedScaleDecimal::zero();

    foreach ($commitments as $commitment) {
        $version =
            $commitment->activeVersion;

        if (
            ! $version
            || ! $this->isEligibleActiveVersion(
                $commitment,
                $version,
                $forecast,
                $evaluatedAt
            )
        ) {
            continue;
        }

        $minimum =
            FixedScaleDecimal::from(
                (string)
                $version->min_volume
            );

        if (
            $commitment->current_confidence
            === SupplyConfidence::GREEN
        ) {
            $directSafe =
                $directSafe->add(
                    $minimum
                );

            continue;
        }

        if (
            $commitment->current_confidence
            === SupplyConfidence::YELLOW
        ) {
            $atRisk =
                $atRisk->add(
                    $minimum
                );
        }
    }

    /*
     * Contributor harus memiliki effective
     * Safe Supply > 0.
     *
     * At-Risk saja tidak cukup.
     */
    $contributors =
        $directSafe->isZero()
            ? []
            : [
                $primaryOrganizationId,
            ];

    return [
        'direct_safe_supply' =>
            $directSafe,

        'at_risk_supply' =>
            $atRisk,

        'contributor_organization_ids' =>
            $contributors,
    ];
}

    /**
 * @return array{
 *     fallback_safe_supply: FixedScaleDecimal,
 *     contributor_organization_ids: array<int, int>
 * }
 */
private function calculateEffectiveFallbackMetricsForResolvedForecast(
    DemandForecast $forecast,
    CarbonImmutable $evaluatedAt,
): array {
    $zeroResult = [
        'fallback_safe_supply' =>
            FixedScaleDecimal::zero(),

        'contributor_organization_ids' =>
            [],
    ];

    /*
     * Setelah Forecast operational boundary,
     * tidak ada current effective Safe Supply.
     */
    if (
        $evaluatedAt->gt(
            CarbonImmutable::instance(
                $forecast->required_end_at
            )
        )
    ) {
        return $zeroResult;
    }

    /*
     * Fail closed bila current PRIMARY topology
     * invalid.
     *
     * Requester Fallback harus berasal dari
     * PRIMARY Forecast tersebut.
     */
    $primaryOrganizationId =
        $this->resolveActivePrimaryOrganizationId(
            $forecast
        );

    if ($primaryOrganizationId === null) {
        return $zeroResult;
    }

    /*
     * Current NETWORK membership juga dibaca dari
     * persisted network truth.
     */
    $activeNetworkOrganizationIds =
        SupplyNetworkLink::query()
            ->where(
                'sppg_organization_id',
                $forecast
                    ->sppg_organization_id
            )
            ->where(
                'network_role',
                NetworkRole::NETWORK
                    ->value
            )
            ->where(
                'is_active',
                true
            )
            ->orderBy(
                'kdkmp_organization_id'
            )
            ->pluck(
                'kdkmp_organization_id'
            )
            ->map(
                fn ($id): int =>
                    (int) $id
            )
            ->all();

    $activeNetworkLookup =
        array_fill_keys(
            $activeNetworkOrganizationIds,
            true
        );

    /*
     * AVAILABLE tidak masuk query ini.
     *
     * Hanya ACCEPTED commercial allocation yang
     * dapat menjadi candidate Safe Supply.
     */
    $offers =
        FallbackOffer::query()
            ->where(
                'status',
                FallbackOfferStatus
                    ::ACCEPTED
                    ->value
            )
            ->whereHas(
                'fallbackRequest',
                fn ($query) =>
                    $query->where(
                        'forecast_id',
                        $forecast->id
                    )
            )
            ->with([
                'fallbackRequest',
                'sources.supplyCommitment.activeVersion',
            ])
            ->orderBy('id')
            ->get();

    $totalFallbackSafe =
        FixedScaleDecimal::zero();

    $contributors = [];

    foreach ($offers as $offer) {
        $request =
            $offer->fallbackRequest;

        /*
         * Defensive persisted-integrity checks.
         */
        if (
            ! $request
            || $request->forecast_id
                !== $forecast->id
            || $request->requester_organization_id
                !== $primaryOrganizationId
            || $request->unit_id
                !== $forecast->unit_id
            || $offer->unit_id
                !== $forecast->unit_id
        ) {
            continue;
        }

        $supplierOrganizationId =
            (int)
            $offer->supplier_organization_id;

        /*
         * Accepted fallback must originate from
         * a NETWORK supplier.
         */
        if (
            ! isset(
                $activeNetworkLookup[
                    $supplierOrganizationId
                ]
            )
        ) {
            continue;
        }

        $acceptedVolume =
            FixedScaleDecimal::from(
                (string)
                $offer->accepted_volume
            );

        if ($acceptedVolume->isZero()) {
            continue;
        }

        $effectiveOfferContribution =
            FixedScaleDecimal::zero();

        $offerLedgerValid =
            true;

        foreach ($offer->sources as $source) {
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
             * Historical ledger harus memenuhi:
             *
             * allocated + released <= reserved
             */
            if (
                $allocated
                    ->add($released)
                    ->compare(
                        $reserved
                    ) > 0
            ) {
                $offerLedgerValid =
                    false;

                break;
            }

            /*
             * Source yang tidak mempunyai allocation
             * tidak mempunyai effective contribution.
             */
            if ($allocated->isZero()) {
                continue;
            }

            $commitment =
                $source->supplyCommitment;

            if (
                ! $commitment
                || ! $this
                    ->isEligibleFallbackSourceCommitment(
                        $commitment,
                        $forecast,
                        $supplierOrganizationId,
                        $evaluatedAt
                    )
            ) {
                continue;
            }

            /*
             * Effective Source Contribution
             * = allocated_volume.
             */
            $effectiveOfferContribution =
                $effectiveOfferContribution
                    ->add(
                        $allocated
                    );
        }

        /*
         * Corrupted ledger tidak boleh menghasilkan
         * optimistic Safe Supply.
         */
        if (! $offerLedgerValid) {
            continue;
        }

        /*
         * ERD invariant:
         *
         * Effective Offer Contribution
         * <= accepted_volume
         *
         * Normal transaction flow harus membuat
         * keduanya konsisten; cap ini merupakan
         * defensive fail-safe terhadap persisted
         * anomaly.
         */
        if (
            $effectiveOfferContribution
                ->compare(
                    $acceptedVolume
                ) > 0
        ) {
            $effectiveOfferContribution =
                $acceptedVolume;
        }

        if (
            $effectiveOfferContribution
                ->isZero()
        ) {
            continue;
        }

        $totalFallbackSafe =
            $totalFallbackSafe->add(
                $effectiveOfferContribution
            );

        /*
         * Contributor identity = Organization.
         * Tidak pernah Producer/Commitment.
         */
        $contributors[] =
            $supplierOrganizationId;
    }

    $contributors =
        array_values(
            array_unique(
                $contributors
            )
        );

    sort(
        $contributors,
        SORT_NUMERIC
    );

    return [
        'fallback_safe_supply' =>
            $totalFallbackSafe,

        'contributor_organization_ids' =>
            $contributors,
    ];
}

    private function resolvePublishedForecast(
        DemandForecast $forecast,
    ): DemandForecast {
        /*
         * Jangan menggunakan stale model snapshot
         * yang diberikan caller.
         *
         * Published Forecast dapat direvisi oleh SPPG.
         */
        $current =
            DemandForecast::query()
                ->whereKey(
                    $forecast->getKey()
                )
                ->firstOrFail();

        if (
            $current->status
            !== ForecastStatus::PUBLISHED
        ) {
            throw ValidationException::withMessages([
                'status' => (
                    'Supply Metrics operasional hanya '
                    .'dapat dihitung untuk Forecast '
                    .'PUBLISHED.'
                ),
            ]);
        }

        return $current;
    }

    private function resolveEvaluationTime(
        ?CarbonInterface $evaluatedAt,
    ): CarbonImmutable {
        if ($evaluatedAt === null) {
            return CarbonImmutable::now();
        }

        return CarbonImmutable::instance(
            $evaluatedAt
        );
    }

    private function resolveActivePrimaryOrganizationId(
    DemandForecast $forecast,
): ?int {
    $organizationIds =
        SupplyNetworkLink::query()
            ->where(
                'sppg_organization_id',
                $forecast
                    ->sppg_organization_id
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
            ->orderBy('id')
            ->pluck(
                'kdkmp_organization_id'
            );

    /*
     * Business invariant:
     * satu SPPG mempunyai exactly one active
     * PRIMARY untuk operational Forecast.
     *
     * Jika persisted state tidak memenuhi
     * invariant, calculator fail closed.
     */
    if ($organizationIds->count() !== 1) {
        return null;
    }

    return (int) $organizationIds->first();
}


private function isEligibleFallbackSourceCommitment(
    SupplyCommitment $commitment,
    DemandForecast $forecast,
    int $supplierOrganizationId,
    CarbonImmutable $evaluatedAt,
): bool {
    /*
     * Offer source harus benar-benar milik
     * supplier Organization pada Offer.
     */
    if (
        $commitment->organization_id
        !== $supplierOrganizationId
    ) {
        return false;
    }

    if (
        $commitment->forecast_id
        !== $forecast->id
    ) {
        return false;
    }

    if (
        $commitment->commodity_id
        !== $forecast->commodity_id
    ) {
        return false;
    }

    if (
        $commitment->lifecycle_status
        !== CommitmentLifecycleStatus::ACTIVE
    ) {
        return false;
    }

    /*
     * Accepted historical allocation baru
     * effective Safe jika CURRENT confidence
     * masih GREEN.
     */
    if (
        $commitment->current_confidence
        !== SupplyConfidence::GREEN
    ) {
        return false;
    }

    if (
        $commitment->active_version_id
        === null
    ) {
        return false;
    }

    $commitment->loadMissing(
        'activeVersion'
    );

    $version =
        $commitment->activeVersion;

    if (
        ! $version
        || ! $this->isEligibleActiveVersion(
            $commitment,
            $version,
            $forecast,
            $evaluatedAt
        )
    ) {
        return false;
    }

    return true;
}
    private function isEligibleActiveVersion(
        SupplyCommitment $commitment,
        CommitmentVersion $version,
        DemandForecast $forecast,
        CarbonImmutable $evaluatedAt,
    ): bool {
        if (
            $version->id
            !== $commitment->active_version_id
            || $version->commitment_id
                !== $commitment->id
        ) {
            return false;
        }

        if (
            $version->approval_status
            !== CommitmentApprovalStatus::APPROVED
        ) {
            return false;
        }

        /*
         * Tidak ada automatic unit conversion
         * pada MVP.
         */
        if (
            $version->unit_id
            !== $forecast->unit_id
        ) {
            return false;
        }

        $availabilityStart =
            CarbonImmutable::instance(
                $version
                    ->availability_start_at
            );

        $availabilityEnd =
            CarbonImmutable::instance(
                $version
                    ->availability_end_at
            );

        $requiredStart =
            CarbonImmutable::instance(
                $forecast
                    ->required_start_at
            );

        $requiredEnd =
            CarbonImmutable::instance(
                $forecast
                    ->required_end_at
            );

        /*
         * Re-evaluate overlap terhadap current
         * Forecast karena Published Forecast dapat
         * direvisi.
         */
        $windowsOverlap =
            $availabilityStart->lte(
                $requiredEnd
            )
            && $availabilityEnd->gte(
                $requiredStart
            );

        if (! $windowsOverlap) {
            return false;
        }

        /*
         * Future availability tetap dihitung untuk
         * forward planning.
         *
         * Equality dengan end boundary masih valid.
         */
        if (
            $evaluatedAt->gt(
                $availabilityEnd
            )
        ) {
            return false;
        }

        return true;
    }
}