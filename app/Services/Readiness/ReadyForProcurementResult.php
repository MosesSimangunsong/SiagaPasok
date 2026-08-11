<?php

namespace App\Services\Readiness;

use Carbon\CarbonImmutable;

final readonly class ReadyForProcurementResult
{
    /**
     * @param array<int, int> $contributorOrganizationIds
     * @param array<int, ContributorReadinessResult> $contributorReadinessResults
     * @param array<int, string> $reasonCodes
     */
    public function __construct(
        public int $forecastId,
        public CarbonImmutable $evaluatedAt,
        public bool $forecastPublished,
        public bool $operationallyValid,
        public bool $volumeReady,
        public array $contributorOrganizationIds,
        public array $contributorReadinessResults,
        public bool $allContributorsLogisticsReady,
        public bool $allContributorsDocumentReady,
        public bool $readyForProcurement,
        public array $reasonCodes,
    ) {
    }

    public function hasContributors(): bool
    {
        return $this->contributorOrganizationIds !== [];
    }

    public function toArray(): array
    {
        return [
            'forecast_id' =>
                $this->forecastId,

            'evaluated_at' =>
                $this->evaluatedAt
                    ->toIso8601String(),

            'forecast_published' =>
                $this->forecastPublished,

            'operationally_valid' =>
                $this->operationallyValid,

            'volume_ready' =>
                $this->volumeReady,

            'contributor_organization_ids' =>
                $this->contributorOrganizationIds,

            'contributor_readiness' =>
                array_map(
                    static fn (
                        ContributorReadinessResult $result
                    ): array =>
                        $result->toArray(),
                    $this->contributorReadinessResults
                ),

            'all_contributors_logistics_ready' =>
                $this->allContributorsLogisticsReady,

            'all_contributors_document_ready' =>
                $this->allContributorsDocumentReady,

            'ready_for_procurement' =>
                $this->readyForProcurement,

            'reason_codes' =>
                $this->reasonCodes,
        ];
    }
}