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
        /*
         * Satu evaluation instant untuk seluruh
         * derived calculation.
         *
         * Supply, contributor membership, Logistics,
         * dan Document readiness tidak boleh memakai
         * clock instant yang berbeda.
         */
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
         * PostgreSQL M09 membutuhkan satu stable
         * snapshot.
         *
         * Jika caller sudah membuka transaction,
         * service tidak dapat menaikkan isolation
         * menjadi REPEATABLE READ dengan aman setelah
         * transaction tersebut mungkin melakukan query.
         *
         * Fail explicitly daripada diam-diam
         * menghasilkan consistency contract yang
         * lebih lemah.
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
         * SQLite :memory: pada regular test suite
         * dapat sudah berada di transaction milik
         * testing framework.
         *
         * Dalam kondisi tersebut kita gunakan
         * transaction yang sudah aktif.
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
                /*
                 * Harus menjadi statement transaction
                 * pertama sebelum query domain apa pun.
                 *
                 * READ ONLY:
                 * evaluasi M09 tidak mempunyai side
                 * effect.
                 *
                 * REPEATABLE READ:
                 * seluruh M06 + M08 reads melihat
                 * snapshot PostgreSQL yang konsisten.
                 */
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
         * Jangan mempercayai model snapshot dari
         * caller.
         *
         * Forecast dapat direvisi / ditutup /
         * dibatalkan setelah model sebelumnya
         * di-load.
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

        $prerequisiteReasons = [];

        if (! $forecastPublished) {
            $prerequisiteReasons[] =
                self::REASON_FORECAST_NOT_PUBLISHED;
        }

        if (! $operationallyValid) {
            $prerequisiteReasons[] =
                self::REASON_FORECAST_WINDOW_ENDED;
        }

        /*
         * M06 hanya menerima Forecast PUBLISHED.
         *
         * Jangan memanggil canonical supply calculator
         * apabila prerequisite Forecast sendiri sudah
         * tidak valid.
         */
        if ($prerequisiteReasons !== []) {
            return new ReadyForProcurementResult(
                forecastId:
                    $currentForecast->id,

                evaluatedAt:
                    $evaluationTime,

                forecastPublished:
                    $forecastPublished,

                operationallyValid:
                    $operationallyValid,

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

                reasonCodes:
                    $prerequisiteReasons,
            );
        }

        /*
         * SINGLE canonical M06 evaluation.
         *
         * Jangan menghitung:
         * - Safe Supply
         * - Fallback Safe Supply
         * - Shortfall
         * - Volume Ready
         * - Contributor Set
         *
         * di M09.
         */
        $supplyMetrics =
            $this->supplyMetricsService
                ->calculate(
                    $currentForecast,
                    $evaluationTime
                );

        $contributorOrganizationIds =
            $supplyMetrics
                ->contributorOrganizationIds;

        $hasContributors =
            $contributorOrganizationIds !== [];

        /*
         * Empty set tidak boleh dianggap
         * "all contributors ready" secara vacuous.
         *
         * Foundation mempunyai gate eksplisit:
         * ContributorSet harus non-empty.
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
             * M08 tetap canonical authority.
             *
             * evaluatedAt yang sama diteruskan.
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

            /*
             * isContributor diperiksa kembali
             * secara fail-closed.
             *
             * Dalam stable snapshot normal,
             * organisasi dari canonical M06 set
             * harus tetap contributor ketika
             * M08 mengevaluasinya.
             */
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

        /*
         * Authoritative M09 conjunction.
         */
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