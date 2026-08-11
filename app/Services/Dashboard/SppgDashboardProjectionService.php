<?php

namespace App\Services\Dashboard;

use App\Enums\ForecastStatus;
use App\Models\DemandForecast;
use App\Models\Organization;
use App\Models\User;
use App\Services\Readiness\ReadyForProcurementEvaluationService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class SppgDashboardProjectionService
{
    public function __construct(
        private readonly ReadyForProcurementEvaluationService
            $readyForProcurementEvaluationService,
    ) {
    }

    public function build(
        User $user,
        ?CarbonInterface $evaluatedAt = null,
    ): array {
        $user->loadMissing(
            'organization'
        );

        $organizationId =
            (int) $user->organization_id;

        $evaluationTime =
            $evaluatedAt === null
                ? CarbonImmutable::now()
                : CarbonImmutable::instance(
                    $evaluatedAt
                );

        /*
         * Dashboard operasional hanya membawa
         * PUBLISHED Forecast yang masih berada
         * pada atau menuju required period.
         *
         * DRAFT dihitung terpisah sebagai
         * authoring context dan tidak pernah
         * dikirim ke canonical supply evaluator.
         */
        $forecasts =
            DemandForecast::query()
                ->where(
                    'sppg_organization_id',
                    $organizationId
                )
                ->where(
                    'status',
                    ForecastStatus::PUBLISHED
                        ->value
                )
                ->where(
                    'required_end_at',
                    '>=',
                    $evaluationTime
                )
                ->with([
                    'commodity',
                    'unit',
                ])
                ->orderBy(
                    'required_start_at'
                )
                ->orderBy('id')
                ->get();

        /*
         * Satu logical evaluation instant
         * digunakan untuk seluruh Forecast.
         *
         * ReadyForProcurementEvaluationService
         * tetap menjadi pemilik domain truth.
         */
        $evaluations =
            $forecasts
                ->mapWithKeys(
                    fn (
                        DemandForecast $forecast
                    ): array => [
                        $forecast->id =>
                            $this
                                ->readyForProcurementEvaluationService
                                ->evaluate(
                                    $forecast,
                                    $evaluationTime
                                ),
                    ]
                );

        /*
         * Hanya organization identity.
         *
         * Tidak ada join ke Producer,
         * ExpectedHarvest, CommitmentVersion,
         * OfferSource, ReadinessItem, atau
         * DocumentRecord.
         */
        $contributorOrganizationIds =
            $evaluations
                ->flatMap(
                    fn ($evaluation) =>
                        $evaluation
                            ->contributorOrganizationIds
                )
                ->map(
                    fn ($id): int =>
                        (int) $id
                )
                ->unique()
                ->values();

        $organizations =
            Organization::query()
                ->whereIn(
                    'id',
                    $contributorOrganizationIds
                )
                ->get([
                    'id',
                    'code',
                    'name',
                ])
                ->keyBy('id');

        $forecastPayloads =
            $forecasts
                ->map(
                    function (
                        DemandForecast $forecast
                    ) use (
                        $evaluations,
                        $organizations,
                    ): array {
                        $evaluation =
                            $evaluations->get(
                                $forecast->id
                            );

                        $contributors =
                            collect(
                                $evaluation
                                    ->contributorReadinessResults
                            )
                                ->map(
                                    function (
                                        $readiness
                                    ) use (
                                        $organizations
                                    ): array {
                                        $organization =
                                            $organizations
                                                ->get(
                                                    $readiness
                                                        ->organizationId
                                                );

                                        return [
                                            'organization' => [
                                                'id' =>
                                                    $readiness
                                                        ->organizationId,

                                                'code' =>
                                                    $organization
                                                        ?->code,

                                                'name' =>
                                                    $organization
                                                        ?->name,
                                            ],

                                            'logistics_ready' =>
                                                $readiness
                                                    ->logisticsReady,

                                            'document_ready' =>
                                                $readiness
                                                    ->documentReady,

                                            'logistics_reason_codes' =>
                                                $readiness
                                                    ->logisticsReasonCodes,

                                            'document_reason_codes' =>
                                                $readiness
                                                    ->documentReasonCodes,
                                        ];
                                    }
                                )
                                ->values();

                        return [
                            'forecast' => [
                                'id' =>
                                    $forecast->id,

                                'forecast_code' =>
                                    $forecast
                                        ->forecast_code,

                                'version' =>
                                    $forecast
                                        ->version,

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

                                'required_start_at' =>
                                    $forecast
                                        ->required_start_at
                                        ?->toIso8601String(),

                                'required_end_at' =>
                                    $forecast
                                        ->required_end_at
                                        ?->toIso8601String(),
                            ],

                            'supply' => [
                                'demand_target' =>
                                    $evaluation
                                        ->demandTarget,

                                'total_safe_supply' =>
                                    $evaluation
                                        ->totalSafeSupply,

                                'at_risk_supply' =>
                                    $evaluation
                                        ->atRiskSupply,

                                'coverage_percent' =>
                                    $evaluation
                                        ->coveragePercent,

                                'shortfall' =>
                                    $evaluation
                                        ->shortfall,

                                'volume_ready' =>
                                    $evaluation
                                        ->volumeReady,
                            ],

                            'procurement' => [
                                'operationally_valid' =>
                                    $evaluation
                                        ->operationallyValid,

                                'all_contributors_logistics_ready' =>
                                    $evaluation
                                        ->allContributorsLogisticsReady,

                                'all_contributors_document_ready' =>
                                    $evaluation
                                        ->allContributorsDocumentReady,

                                'ready_for_procurement' =>
                                    $evaluation
                                        ->readyForProcurement,

                                'reason_codes' =>
                                    $evaluation
                                        ->reasonCodes,
                            ],

                            'contributors' =>
                                $contributors,
                        ];
                    }
                )
                ->values();

        $draftForecastCount =
            DemandForecast::query()
                ->where(
                    'sppg_organization_id',
                    $organizationId
                )
                ->where(
                    'status',
                    ForecastStatus::DRAFT
                        ->value
                )
                ->count();

        $readyCount =
            $forecastPayloads
                ->filter(
                    fn (array $item): bool =>
                        (bool)
                        $item['procurement'][
                            'ready_for_procurement'
                        ]
                )
                ->count();

        $attentionCount =
            $forecastPayloads
                ->filter(
                    fn (array $item): bool =>
                        ! $item['procurement'][
                            'ready_for_procurement'
                        ]
                )
                ->count();

        return [
            'evaluatedAt' =>
                $evaluationTime
                    ->toIso8601String(),

            'organization' => [
                'id' =>
                    $user
                        ->organization
                        ->id,

                'code' =>
                    $user
                        ->organization
                        ->code,

                'name' =>
                    $user
                        ->organization
                        ->name,

                'general_location' =>
                    $user
                        ->organization
                        ->general_location,
            ],

            'summary' => [
                'active_forecast_count' =>
                    $forecastPayloads
                        ->count(),

                'attention_forecast_count' =>
                    $attentionCount,

                'ready_for_procurement_count' =>
                    $readyCount,

                'draft_forecast_count' =>
                    $draftForecastCount,
            ],

            'forecasts' =>
                $forecastPayloads,
        ];
    }
}