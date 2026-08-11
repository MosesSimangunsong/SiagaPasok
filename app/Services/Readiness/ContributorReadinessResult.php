<?php

namespace App\Services\Readiness;

use Carbon\CarbonImmutable;

final readonly class ContributorReadinessResult
{
    /**
     * @param array<int, string> $logisticsReasonCodes
     * @param array<int, string> $documentReasonCodes
     */
    public function __construct(
        public int $forecastId,
        public int $organizationId,
        public CarbonImmutable $evaluatedAt,
        public bool $isContributor,
        public bool $logisticsReady,
        public bool $documentReady,
        public array $logisticsReasonCodes,
        public array $documentReasonCodes,
    ) {
    }

    public function allReady(): bool
    {
        return $this->isContributor
            && $this->logisticsReady
            && $this->documentReady;
    }

    public function toArray(): array
    {
        return [
            'forecast_id' =>
                $this->forecastId,

            'organization_id' =>
                $this->organizationId,

            'evaluated_at' =>
                $this->evaluatedAt
                    ->toIso8601String(),

            'is_contributor' =>
                $this->isContributor,

            'logistics_ready' =>
                $this->logisticsReady,

            'document_ready' =>
                $this->documentReady,

            'all_ready' =>
                $this->allReady(),

            'logistics_reason_codes' =>
                $this->logisticsReasonCodes,

            'document_reason_codes' =>
                $this->documentReasonCodes,
        ];
    }
} 