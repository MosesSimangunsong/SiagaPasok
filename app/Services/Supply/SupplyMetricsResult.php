<?php

namespace App\Services\Supply;

use Carbon\CarbonImmutable;

final readonly class SupplyMetricsResult
{
/**
 * @param array<int, int> $contributorOrganizationIds
 * @param array<int, string> $contributorSafeSupplyByOrganization
 */
    public function __construct(
        public int $forecastId,
        public CarbonImmutable $evaluatedAt,
        public int $unitId,
        public string $demandTarget,
        public string $directSafeSupply,
        public string $atRiskSupply,
        public string $fallbackSafeSupply,
        public string $totalSafeSupply,
        public ?string $coveragePercent,
        public string $shortfall,
        public string $surplus,
public array $contributorOrganizationIds,
public bool $volumeReady,
public array $contributorSafeSupplyByOrganization = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'forecast_id' =>
                $this->forecastId,

            'evaluated_at' =>
                $this->evaluatedAt
                    ->toIso8601String(),

            'unit_id' =>
                $this->unitId,

            'demand_target' =>
                $this->demandTarget,

            'direct_safe_supply' =>
                $this->directSafeSupply,

            'at_risk_supply' =>
                $this->atRiskSupply,

            'fallback_safe_supply' =>
                $this->fallbackSafeSupply,

            'total_safe_supply' =>
                $this->totalSafeSupply,

            'coverage_percent' =>
                $this->coveragePercent,

            'shortfall' =>
                $this->shortfall,

            'surplus' =>
                $this->surplus,

            'contributor_organization_ids' =>
                $this->contributorOrganizationIds,

            'contributor_safe_supply_by_organization' =>
    $this->contributorSafeSupplyByOrganization,

            'volume_ready' =>
                $this->volumeReady,
        ];
    }
}