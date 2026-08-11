<?php

namespace App\Services\Readiness;

use App\Models\DemandForecast;
use App\Services\Supply\SupplyMetricsService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

final class ReadyForProcurementEvaluationService
{
    public const REASON_FORECAST_NOT_PUBLISHED =
        'FORECAST_NOT_PUBLISHED';

    public const REASON_FORECAST_WINDOW_ENDED =
        'FORECAST_WINDOW_ENDED';

    public const REASON_VOLUME_NOT_READY =
        'VOLUME_NOT_READY';

    public const REASON_NO_EFFECTIVE_CONTRIBUTORS =
        'NO_EFFECTIVE_CONTRIBUTORS';

    public const REASON_LOGISTICS_NOT_READY =
        'LOGISTICS_NOT_READY';

    public const REASON_DOCUMENT_NOT_READY =
        'DOCUMENT_NOT_READY';

    public function __construct(
        private readonly SupplyMetricsService
            $supplyMetricsService,

        private readonly ReadinessEvaluationService
            $readinessEvaluationService,
    ) {
    }

    public function evaluate(
        DemandForecast $forecast,
        ?CarbonInterface $evaluatedAt = null,
    ): ReadyForProcurementResult {
        $evaluationTime =
            $evaluatedAt === null
                ? CarbonImmutable::now()
                : CarbonImmutable::instance(
                    $evaluatedAt
                );

        return $this->evaluateInConsistentSnapshot(
            $forecast,
            $evaluationTime
        );
    }

    private function evaluateInConsistentSnapshot(
        DemandForecast $forecast,
        CarbonImmutable $evaluationTime,
    ): ReadyForProcurementResult {
        $connection =
            DB::connection(
                $forecast->getConnectionName()
            );

        $driver =
            $connection->getDriverName();

        /*
         * PostgreSQL production/local operational
         * evaluation harus membentuk snapshot
         * sendiri agar seluruh M06 + M08 reads
         * berasal dari satu consistent view.
         */
        if (
            $driver === 'pgsql'
            && $connection->transactionLevel() > 0
        ) {
            throw new LogicException(
                'Ready for Procurement evaluation pada '
                .'PostgreSQL harus dimulai di luar '
                .'transaction aktif agar service dapat '
                .'membentuk REPEATABLE READ snapshot.'
            );
        }

        /*
         * Regular SQLite automated tests dapat
         * sudah dibungkus transaction oleh
         * RefreshDatabase.
         */
        if ($connection->transactionLevel() > 0) {
            return $this->evaluateCurrentState(
                $forecast,
                $evaluationTime
            );
        }

        return $connection->transaction(
            function () use (
                $connection,
                $forecast,
                $evaluationTime
            ): ReadyForProcurementResult {
                if (
                    $connection->getDriverName()
                    === 'pgsql'
                ) {
                    $connection->statement(
                        'SET TRANSACTION ISOLATION LEVEL '
                        .'REPEATABLE READ READ ONLY'
                    );
                }

                return $this->evaluateCurrentState(
                    $forecast,
                    $evaluationTime
                );
            }
        );
    }

    private function evaluateCurrentState(
        DemandForecast $forecast,
        CarbonImmutable $evaluationTime,
    ): ReadyForProcurementResult {
        /*
         * Resolve current persisted Forecast,
         * bukan mempercayai stale caller model.
         */
        $currentForecast =
            DemandForecast::query()
                ->whereKey(
                    $forecast->getKey()
                )
                ->firstOrFail();

        $forecastPublished =
            $currentForecast->isPublished();

        $operationallyValid =
            ! $evaluationTime->gt(
                CarbonImmutable::instance(
                    $currentForecast
                        ->required_end_at
                )
            );

        $demandTarget =
            (string)
            $currentForecast->target_volume;

        /*
         * M06 intentionally hanya menerima
         * Forecast PUBLISHED.
         *
         * Untuk state non-PUBLISHED, RFP langsung
         * fail closed tanpa mencoba menciptakan
         * pseudo supply metrics.
         */
        if (! $forecastPublished) {
            return new ReadyForProcurementResult(
                forecastId:
                    $currentForecast->id,

                evaluatedAt:
                    $evaluationTime,

                forecastPublished:
                    false,

                operationallyValid:
                    $operationallyValid,

                demandTarget:
                    $demandTarget,

                totalSafeSupply:
                    null,

                coveragePercent:
                    null,

                shortfall:
                    null,

                volumeReady:
                    false,

                contributorOrganizationIds:
                    [],

                contributorReadinessResults:
                    [],

                allContributorsLogisticsReady:
                    false,

                allContributorsDocumentReady:
                    false,

                readyForProcurement:
                    false,

                reasonCodes: [
                    self::REASON_FORECAST_NOT_PUBLISHED,
                ],
            );
        }

        /*
         * SINGLE canonical M06 evaluation.
         *
         * Semua output supply yang dikonsumsi
         * HTTP/UI harus berasal dari object ini.
         */
        $supplyMetrics =
            $this->supplyMetricsService
                ->calculate(
                    $currentForecast,
                    $evaluationTime
                );

        /*
         * M06 sendiri sudah fail closed setelah
         * required_end_at sehingga metrics di
         * sini tetap canonical dan dapat
         * ditampilkan tanpa perhitungan kedua.
         */
        if (! $operationallyValid) {
            return new ReadyForProcurementResult(
                forecastId:
                    $currentForecast->id,

                evaluatedAt:
                    $evaluationTime,

                forecastPublished:
                    true,

                operationallyValid:
                    false,

                demandTarget:
                    $supplyMetrics->demandTarget,

                totalSafeSupply:
                    $supplyMetrics->totalSafeSupply,

                coveragePercent:
                    $supplyMetrics->coveragePercent,

                shortfall:
                    $supplyMetrics->shortfall,

                volumeReady:
                    false,

                contributorOrganizationIds:
                    $supplyMetrics
                        ->contributorOrganizationIds,

                contributorReadinessResults:
                    [],

                allContributorsLogisticsReady:
                    false,

                allContributorsDocumentReady:
                    false,

                readyForProcurement:
                    false,

                reasonCodes: [
                    self::REASON_FORECAST_WINDOW_ENDED,
                ],
            );
        }

        $contributorOrganizationIds =
            $supplyMetrics
                ->contributorOrganizationIds;

        $hasContributors =
            $contributorOrganizationIds !== [];

        /*
         * Empty contributor set tidak boleh
         * menjadi TRUE melalui vacuous truth.
         */
        $allLogisticsReady =
            $hasContributors;

        $allDocumentsReady =
            $hasContributors;

        $contributorReadinessResults = [];

        foreach (
            $contributorOrganizationIds
            as $organizationId
        ) {
            /*
             * Canonical M08 evaluation.
             *
             * Gunakan evaluation instant yang
             * sama dengan M06.
             */
            $readiness =
                $this
                    ->readinessEvaluationService
                    ->evaluateContributor(
                        $currentForecast,
                        $organizationId,
                        $evaluationTime
                    );

            $contributorReadinessResults[] =
                $readiness;

            if (
                ! $readiness->isContributor
                || ! $readiness->logisticsReady
            ) {
                $allLogisticsReady =
                    false;
            }

            if (
                ! $readiness->isContributor
                || ! $readiness->documentReady
            ) {
                $allDocumentsReady =
                    false;
            }
        }

        $reasonCodes = [];

        if (! $supplyMetrics->volumeReady) {
            $reasonCodes[] =
                self::REASON_VOLUME_NOT_READY;
        }

        if (! $hasContributors) {
            $reasonCodes[] =
                self::REASON_NO_EFFECTIVE_CONTRIBUTORS;
        }

        if (
            $hasContributors
            && ! $allLogisticsReady
        ) {
            $reasonCodes[] =
                self::REASON_LOGISTICS_NOT_READY;
        }

        if (
            $hasContributors
            && ! $allDocumentsReady
        ) {
            $reasonCodes[] =
                self::REASON_DOCUMENT_NOT_READY;
        }

        $readyForProcurement =
            $supplyMetrics->volumeReady
            && $hasContributors
            && $allLogisticsReady
            && $allDocumentsReady;

        return new ReadyForProcurementResult(
            forecastId:
                $currentForecast->id,

            evaluatedAt:
                $evaluationTime,

            forecastPublished:
                true,

            operationallyValid:
                true,

            demandTarget:
                $supplyMetrics->demandTarget,

            totalSafeSupply:
                $supplyMetrics->totalSafeSupply,

            coveragePercent:
                $supplyMetrics->coveragePercent,

            shortfall:
                $supplyMetrics->shortfall,

            volumeReady:
                $supplyMetrics->volumeReady,

            contributorOrganizationIds:
                $contributorOrganizationIds,

            contributorReadinessResults:
                $contributorReadinessResults,

            allContributorsLogisticsReady:
                $allLogisticsReady,

            allContributorsDocumentReady:
                $allDocumentsReady,

            readyForProcurement:
                $readyForProcurement,

            reasonCodes:
                $reasonCodes,
        );
    }
}