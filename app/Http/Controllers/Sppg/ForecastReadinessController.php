<?php

namespace App\Http\Controllers\Sppg;

use App\Http\Controllers\Controller;
use App\Models\DemandForecast;
use App\Models\Organization;
use App\Services\Readiness\ReadyForProcurementEvaluationService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ForecastReadinessController extends Controller
{
    public function __construct(
        private readonly ReadyForProcurementEvaluationService
            $readyForProcurementEvaluationService,
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

        /*
         * Single canonical M09 evaluation.
         *
         * Controller tidak menghitung ulang:
         * - Safe Supply
         * - Volume Ready
         * - Contributor Set
         * - Logistics Ready
         * - Document Ready
         * - Ready for Procurement
         */
        $evaluation =
            $this
                ->readyForProcurementEvaluationService
                ->evaluate(
                    $forecast
                );

        /*
         * Organization metadata bukan business
         * readiness truth.
         *
         * SPPG hanya menerima organization-level
         * identity, bukan producer/commitment/
         * document evidence.
         */
        $organizations =
            Organization::query()
                ->whereIn(
                    'id',
                    $evaluation
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

                    /*
                     * Gunakan demand snapshot yang
                     * sama dengan M09 evaluation.
                     */
                    'target_volume' =>
                        $evaluation
                            ->demandTarget,

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
                        $evaluation
                            ->totalSafeSupply,

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
                    'evaluated_at' =>
                        $evaluation
                            ->evaluatedAt
                            ->toIso8601String(),

                    'forecast_published' =>
                        $evaluation
                            ->forecastPublished,

                    'operationally_valid' =>
                        $evaluation
                            ->operationallyValid,

                    'volume_ready' =>
                        $evaluation
                            ->volumeReady,

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
            ]
        );
    }
}