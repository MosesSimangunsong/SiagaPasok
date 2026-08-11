<?php

namespace App\Services\Notification;

use App\Enums\AuditSource;
use App\Models\DemandForecast;
use App\Models\ForecastDerivedStateObservation;
use App\Services\Audit\AuditService;
use App\Services\Readiness\ReadyForProcurementEvaluationService;
use App\Support\FixedScaleDecimal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;



final class DerivedForecastStateObservationService
{

    private const PG_ADVISORY_LOCK_NAMESPACE =
        9_100_000_000;
    private const AUDIT_RFP_REACHED =
        'READY_FOR_PROCUREMENT_REACHED';

    private const AUDIT_RFP_LOST =
        'READY_FOR_PROCUREMENT_LOST';

    public function __construct(
        private readonly ReadyForProcurementEvaluationService
            $evaluationService,

        private readonly AuditService
            $auditService,

        private readonly OperationalNotificationService
            $notificationService,
    ) {
    }

    public function observe(
    DemandForecast $forecast,
    ?CarbonInterface $evaluatedAt = null,
): ForecastDerivedStateObservation {
    $connection =
        DB::connection(
            $forecast
                ->getConnectionName()
        );

    $driver =
        $connection->getDriverName();

    $forecastId =
        (int) $forecast->getKey();

    $advisoryLockKey =
        self::PG_ADVISORY_LOCK_NAMESPACE
        + $forecastId;

    /*
     * PostgreSQL:
     *
     * Serialize observer untuk Forecast yang sama
     * SEBELUM canonical M09 snapshot dibentuk.
     *
     * Session advisory lock tidak membuka database
     * transaction sehingga M09 tetap dapat membuat
     * REPEATABLE READ READ ONLY transaction sendiri.
     */
    if ($driver === 'pgsql') {
        $connection->selectOne(
            'SELECT pg_advisory_lock(?)',
            [
                $advisoryLockKey,
            ]
        );
    }

    try {
        /*
         * Canonical current truth tetap berasal
         * dari M09. Observation tidak menghitung
         * ulang supply/readiness sendiri.
         */
        $evaluation =
            $this->evaluationService
                ->evaluate(
                    $forecast,
                    $evaluatedAt
                );

        return $connection->transaction(
            function () use (
                $forecast,
                $evaluation
            ): ForecastDerivedStateObservation {
                /*
                 * Forecast row menjadi serialization
                 * point bagi write-side observation
                 * persistence.
                 */
                $currentForecast =
                    DemandForecast::query()
                        ->whereKey(
                            $forecast->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $previous =
                    ForecastDerivedStateObservation
                        ::query()
                        ->where(
                            'forecast_id',
                            $currentForecast->id
                        )
                        ->orderByDesc(
                            'evaluated_at'
                        )
                        ->orderByDesc('id')
                        ->first();

                /*
                 * Explicit historical evaluation
                 * yang lebih tua tidak boleh
                 * menjadi latest observation.
                 */
                if (
                    $previous
                    && $previous
                        ->evaluated_at
                        ->gt(
                            $evaluation
                                ->evaluatedAt
                        )
                ) {
                    return $previous;
                }

                $currentSnapshot = [
                    'forecast_id' =>
                        $currentForecast->id,

                    'forecast_version' =>
                        $currentForecast->version,

                    'demand_target' =>
                        $evaluation
                            ->demandTarget,

                    'total_safe_supply' =>
                        $evaluation
                            ->totalSafeSupply,

                    'shortfall' =>
                        $evaluation
                            ->shortfall,

                    'ready_for_procurement' =>
                        $evaluation
                            ->readyForProcurement,

                    'contributor_organization_ids' =>
                        $this
                            ->normalizeOrganizationIds(
                                $evaluation
                                    ->contributorOrganizationIds
                            ),

                    'reason_codes' =>
                        array_values(
                            $evaluation
                                ->reasonCodes
                        ),

                    'evaluated_at' =>
                        $evaluation
                            ->evaluatedAt,

                    'created_at' =>
                        CarbonImmutable::now(),
                ];

                if (
                    $previous
                    && $this->sameState(
                        $previous,
                        $currentSnapshot
                    )
                ) {
                    return $previous;
                }

                $observation =
                    ForecastDerivedStateObservation
                        ::create(
                            $currentSnapshot
                        );

                $shortfallIncreased =
                    $this->shortfallIncreased(
                        $previous,
                        $observation
                    );

                $rfpReached =
                    $observation
                        ->ready_for_procurement
                    && (
                        $previous === null
                        || ! $previous
                            ->ready_for_procurement
                    );

                $rfpLost =
                    $previous !== null
                    && $previous
                        ->ready_for_procurement
                    && ! $observation
                        ->ready_for_procurement;

                if ($shortfallIncreased) {
                    $this->notificationService
                        ->shortfallIncreased(
                            $currentForecast,
                            $observation
                        );
                }

                if ($rfpReached) {
                    $this->auditService->record(
                        actor:
                            null,

                        source:
                            AuditSource::SYSTEM,

                        action:
                            self::AUDIT_RFP_REACHED,

                        entity:
                            $currentForecast,

                        previousValue:
                            $this
                                ->previousAuditSnapshot(
                                    $previous
                                ),

                        newValue:
                            $this->auditSnapshot(
                                $observation
                            ),

                        reasonNote:
                            'Seluruh derived Ready for Procurement gate terpenuhi.',
                    );

                    $this->notificationService
                        ->readyForProcurementReached(
                            $currentForecast,
                            $observation
                        );
                }

                if ($rfpLost) {
                    /*
                     * Gunakan union previous + current
                     * contributor.
                     *
                     * Contributor yang justru keluar
                     * dari current Safe Supply akibat
                     * degradation tetap harus menerima
                     * RFP-lost notification.
                     */
                    $affectedContributorIds =
                        $this
                            ->normalizeOrganizationIds([
                                ...(
                                    $previous
                                        ->contributor_organization_ids
                                    ?? []
                                ),

                                ...(
                                    $observation
                                        ->contributor_organization_ids
                                    ?? []
                                ),
                            ]);

                    $this->auditService->record(
                        actor:
                            null,

                        source:
                            AuditSource::SYSTEM,

                        action:
                            self::AUDIT_RFP_LOST,

                        entity:
                            $currentForecast,

                        previousValue:
                            $this->auditSnapshot(
                                $previous
                            ),

                        newValue:
                            $this->auditSnapshot(
                                $observation
                            ),

                        reasonNote:
                            $observation
                                ->reason_codes === []
                                ? (
                                    'Derived Ready for '
                                    .'Procurement tidak '
                                    .'lagi terpenuhi.'
                                )
                                : implode(
                                    ', ',
                                    $observation
                                        ->reason_codes
                                ),
                    );

                    $this->notificationService
                        ->readyForProcurementLost(
                            $currentForecast,
                            $observation,
                            $affectedContributorIds
                        );
                }

                return $observation;
            }
        );
    } finally {
        if ($driver === 'pgsql') {
            $connection->selectOne(
                'SELECT pg_advisory_unlock(?)',
                [
                    $advisoryLockKey,
                ]
            );
        }
    }
}

    /**
     * Aman dipanggil dari domain transaction.
     *
     * Pada PostgreSQL actual M09 evaluation baru
     * berjalan setelah transaction mutation selesai.
     */
    public function observeAfterCommit(
        DemandForecast $forecast,
    ): void {
        $forecastId =
            (int) $forecast->getKey();

        $connectionName =
            $forecast->getConnectionName();

        $callback =
            function () use (
                $forecastId,
                $connectionName
            ): void {
                $model =
                    new DemandForecast();

                $model->setConnection(
                    $connectionName
                );

                $currentForecast =
                    $model
                        ->newQuery()
                        ->find(
                            $forecastId
                        );

                if (! $currentForecast) {
                    return;
                }

                $this->observe(
                    $currentForecast
                );
            };

        $connection =
            DB::connection(
                $connectionName
            );

        if (
            $connection
                ->transactionLevel() > 0
        ) {
            $connection->afterCommit(
                $callback
            );

            return;
        }

        $callback();
    }

    private function shortfallIncreased(
        ?ForecastDerivedStateObservation $previous,
        ForecastDerivedStateObservation $current,
    ): bool {
        /*
         * First observed shortfall hanya baseline.
         *
         * User Flow secara eksplisit menyatakan
         * initial Forecast dapat mempunyai supply 0
         * tanpa berarti operational failure.
         */
        if (
            $previous === null
            || $previous->shortfall === null
            || $current->shortfall === null
        ) {
            return false;
        }

        $before =
            FixedScaleDecimal::from(
                (string)
                $previous->shortfall
            );

        $after =
            FixedScaleDecimal::from(
                (string)
                $current->shortfall
            );

        return $after->compare(
            $before
        ) > 0;
    }

    private function sameState(
        ForecastDerivedStateObservation $previous,
        array $current,
    ): bool {
        return
            $previous->forecast_version
                === $current[
                    'forecast_version'
                ]
            && (string)
                $previous->demand_target
                === (string)
                $current['demand_target']
            && $this->nullableDecimalEqual(
                $previous
                    ->total_safe_supply,
                $current[
                    'total_safe_supply'
                ]
            )
            && $this->nullableDecimalEqual(
                $previous->shortfall,
                $current['shortfall']
            )
            && $previous
                ->ready_for_procurement
                === $current[
                    'ready_for_procurement'
                ]
            && $this
                ->normalizeOrganizationIds(
                    $previous
                        ->contributor_organization_ids
                    ?? []
                )
                === $current[
                    'contributor_organization_ids'
                ]
            && array_values(
                $previous->reason_codes
                ?? []
            )
                === $current[
                    'reason_codes'
                ];
    }

    private function nullableDecimalEqual(
        mixed $left,
        mixed $right,
    ): bool {
        if (
            $left === null
            || $right === null
        ) {
            return $left === null
                && $right === null;
        }

        return FixedScaleDecimal::from(
            (string) $left
        )->compare(
            FixedScaleDecimal::from(
                (string) $right
            )
        ) === 0;
    }

    /**
     * @param array<int, mixed> $ids
     *
     * @return array<int, int>
     */
    private function normalizeOrganizationIds(
        array $ids,
    ): array {
        $normalized =
            array_values(
                array_unique(
                    array_map(
                        'intval',
                        $ids
                    )
                )
            );

        sort(
            $normalized,
            SORT_NUMERIC
        );

        return $normalized;
    }

    private function previousAuditSnapshot(
        ?ForecastDerivedStateObservation $previous,
    ): array {
        if ($previous) {
            return $this->auditSnapshot(
                $previous
            );
        }

        return [
            'ready_for_procurement' =>
                false,

            'observation_state' =>
                'IMPLICIT_INITIAL_STATE',
        ];
    }

    private function auditSnapshot(
        ForecastDerivedStateObservation $observation,
    ): array {
        return [
            'observation_id' =>
                $observation->id,

            'forecast_version' =>
                $observation
                    ->forecast_version,

            'demand_target' =>
                (string)
                $observation
                    ->demand_target,

            'total_safe_supply' =>
                $observation
                    ->total_safe_supply,

            'shortfall' =>
                $observation
                    ->shortfall,

            'ready_for_procurement' =>
                $observation
                    ->ready_for_procurement,

            'contributor_organization_ids' =>
                $observation
                    ->contributor_organization_ids,

            'reason_codes' =>
                $observation
                    ->reason_codes,

            'evaluated_at' =>
                $observation
                    ->evaluated_at
                    ->toIso8601String(),
        ];
    }
}