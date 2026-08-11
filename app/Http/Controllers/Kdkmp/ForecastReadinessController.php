<?php

namespace App\Http\Controllers\Sppg;

use App\Http\Controllers\Controller;
use App\Models\DemandForecast;
use App\Models\Organization;
use App\Services\Readiness\ReadinessEvaluationService;
use App\Services\Supply\SupplyMetricsService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ForecastReadinessController extends Controller
{
    public function __construct(
        private readonly SupplyMetricsService $supplyMetricsService,
        private readonly ReadinessEvaluationService $readinessEvaluationService,
    ) {
    }

    public function show(
        DemandForecast $forecast
    ): Response {
        Gate::authorize(
            'view',
            $forecast
        );

        $forecast->load([
            'commodity',
            'unit',
        ]);

        $metrics =
            $this->supplyMetricsService
                ->calculate(
                    $forecast
                );

        $organizations =
            Organization::query()
                ->whereIn(
                    'id',
                    $metrics
                        ->contributorOrganizationIds
                )
                ->get([
                    'id',
                    'code',
                    'name',
                ])
                ->keyBy('id');

        $contributors =
            collect(
                $metrics
                    ->contributorOrganizationIds
            )
                ->map(
                    function (
                        int $organizationId
                    ) use (
                        $forecast,
                        $organizations
                    ): array {
                        $organization =
                            $organizations
                                ->get(
                                    $organizationId
                                );

                        $readiness =
                            $this
                                ->readinessEvaluationService
                                ->evaluateContributor(
                                    $forecast,
                                    $organizationId
                                );

                        return [
                            'organization' => [
                                'id' =>
                                    $organizationId,

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

        return Inertia::render(
            'Sppg/Forecasts/Readiness',
            [
                'forecast' => [
                    'id' =>
                        $forecast->id,

                    'forecast_code' =>
                        $forecast
                            ->forecast_code,

                    'commodity' => [
                        'id' =>
                            $forecast
                                ->commodity
                                ->id,

                        'name' =>
                            $forecast
                                ->commodity
                                ->name,
                    ],

                    'target_volume' =>
                        (string)
                        $forecast
                            ->target_volume,

                    'unit' => [
                        'id' =>
                            $forecast
                                ->unit
                                ->id,

                        'symbol' =>
                            $forecast
                                ->unit
                                ->symbol,
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
                    'total_safe_supply' =>
                        $metrics
                            ->totalSafeSupply,

                    'coverage_percent' =>
                        $metrics
                            ->coveragePercent,

                    'shortfall' =>
                        $metrics
                            ->shortfall,

                    'volume_ready' =>
                        $metrics
                            ->volumeReady,
                ],

                'contributors' =>
                    $contributors,
            ]
        );
    }
}