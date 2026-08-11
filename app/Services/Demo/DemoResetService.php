<?php

namespace App\Services\Demo;

use App\Models\CommitmentConfidenceEvent;
use App\Models\CommitmentVersion;
use App\Models\ConfidenceRecoveryRequest;
use App\Models\DemandForecast;
use App\Models\ExpectedHarvest;
use App\Models\FallbackOffer;
use App\Models\FallbackOfferSource;
use App\Models\FallbackRequest;
use App\Models\ForecastDerivedStateObservation;
use App\Models\FulfilmentFeedback;
use App\Models\ReadinessChecklist;
use App\Models\ReadinessItem;
use App\Models\SupplyCommitment;
use App\Models\User;
use App\Support\Demo\DemoIdentifiers;
use Database\Seeders\DemoBaselineScenarioSeeder;
use Database\Seeders\DemoIdentitySeeder;
use Database\Seeders\DemoReadinessRequirementSeeder;
use Database\Seeders\DemoSupplySeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class DemoResetService
{
    public function reset(
        User $actor
    ): DemandForecast {
        $this->assertDemoEnabled();

        $actor->loadMissing(
            'organization'
        );

        $this->assertResetActor(
            $actor
        );

        return DB::transaction(
            function () use (
                $actor
            ): DemandForecast {
                $primaryOrganizationId =
                    $this->organizationId(
                        DemoIdentifiers
                            ::PRIMARY_KDKMP_CODE
                    );

                $networkOrganizationId =
                    $this->organizationId(
                        DemoIdentifiers
                            ::NETWORK_KDKMP_CODE
                    );

                $demoUserIds =
                    $this->demoUserIds();

                $forecast =
                    DemandForecast::query()
                        ->where(
                            'forecast_code',
                            DemoIdentifiers
                                ::FORECAST_CODE
                        )
                        ->lockForUpdate()
                        ->first();

                if ($forecast) {
                    if (
                        $forecast
                            ->sppg_organization_id
                        !== $actor
                            ->organization_id
                    ) {
                        $this->fail(
                            'Stable demo Forecast ditemukan di luar SPPG Badung Demo. Reset dibatalkan.'
                        );
                    }

                    if (
                        ! $primaryOrganizationId
                        || ! $networkOrganizationId
                    ) {
                        $this->fail(
                            'Demo Forecast masih ada tetapi deterministic KDKMP identity tidak lengkap. Reset dibatalkan agar tidak menghapus data secara ambigu.'
                        );
                    }

                    $this->deleteScenario(
                        forecast: $forecast,
                        primaryOrganizationId:
                            $primaryOrganizationId,
                        networkOrganizationId:
                            $networkOrganizationId,
                        demoUserIds:
                            $demoUserIds,
                    );
                }

                /*
                 * Base identities/data tidak dihapus.
                 *
                 * Seeder berikut hanya menormalisasi kembali
                 * deterministic demo rows yang memiliki stable
                 * code/email/producer code/requirement code.
                 */
                app(
                    DemoIdentitySeeder::class
                )->run();

                app(
                    DemoSupplySeeder::class
                )->run();

                app(
                    DemoReadinessRequirementSeeder::class
                )->run();

                /*
                 * Recreate legal starting state:
                 *
                 * Forecast 400
                 * PRIMARY Commitment 250 GREEN
                 * PRIMARY Commitment 150 GREEN
                 * Safe Supply 400
                 */
                app(
                    DemoBaselineScenarioSeeder::class
                )->run();

                return DemandForecast::query()
                    ->where(
                        'forecast_code',
                        DemoIdentifiers
                            ::FORECAST_CODE
                    )
                    ->firstOrFail();
            },
            3
        );
    }

    private function deleteScenario(
        DemandForecast $forecast,
        int $primaryOrganizationId,
        int $networkOrganizationId,
        array $demoUserIds
    ): void {
        $forecastId =
            $forecast->id;

        $this->assertNoForeignOwnership(
            forecastId:
                $forecastId,
            primaryOrganizationId:
                $primaryOrganizationId,
            networkOrganizationId:
                $networkOrganizationId,
        );

        $commitmentIds =
            $this->integerIds(
                DB::table(
                    'supply_commitments'
                )
                    ->where(
                        'forecast_id',
                        $forecastId
                    )
            );

        $expectedHarvestIds =
            $commitmentIds === []
                ? []
                : DB::table(
                    'supply_commitments'
                )
                    ->whereIn(
                        'id',
                        $commitmentIds
                    )
                    ->whereNotNull(
                        'expected_harvest_id'
                    )
                    ->pluck(
                        'expected_harvest_id'
                    )
                    ->map(
                        static fn (
                            mixed $id
                        ): int => (int) $id
                    )
                    ->unique()
                    ->values()
                    ->all();

        if (
            $expectedHarvestIds !== []
            && DB::table(
                'supply_commitments'
            )
                ->whereNotIn(
                    'id',
                    $commitmentIds
                )
                ->whereIn(
                    'expected_harvest_id',
                    $expectedHarvestIds
                )
                ->exists()
        ) {
            $this->fail(
                'Expected Harvest demo masih direferensikan Commitment di luar scenario demo. Reset dibatalkan.'
            );
        }

        $versionIds =
            $commitmentIds === []
                ? []
                : $this->integerIds(
                    DB::table(
                        'commitment_versions'
                    )
                        ->whereIn(
                            'commitment_id',
                            $commitmentIds
                        )
                );

        $confidenceEventIds =
            $commitmentIds === []
                ? []
                : $this->integerIds(
                    DB::table(
                        'commitment_confidence_events'
                    )
                        ->whereIn(
                            'commitment_id',
                            $commitmentIds
                        )
                );

        $recoveryRequestIds =
            $commitmentIds === []
                ? []
                : $this->integerIds(
                    DB::table(
                        'confidence_recovery_requests'
                    )
                        ->whereIn(
                            'commitment_id',
                            $commitmentIds
                        )
                );

        $fallbackRequestIds =
            $this->integerIds(
                DB::table(
                    'fallback_requests'
                )
                    ->where(
                        'forecast_id',
                        $forecastId
                    )
            );

        $fallbackOfferIds =
            $fallbackRequestIds === []
                ? []
                : $this->integerIds(
                    DB::table(
                        'fallback_offers'
                    )
                        ->whereIn(
                            'fallback_request_id',
                            $fallbackRequestIds
                        )
                );

        $fallbackOfferSourceIds =
            $fallbackOfferIds === []
                ? []
                : $this->integerIds(
                    DB::table(
                        'fallback_offer_sources'
                    )
                        ->whereIn(
                            'fallback_offer_id',
                            $fallbackOfferIds
                        )
                );

        if ($fallbackOfferIds !== []) {
            $sourceCommitmentIds =
                DB::table(
                    'fallback_offer_sources'
                )
                    ->whereIn(
                        'fallback_offer_id',
                        $fallbackOfferIds
                    )
                    ->pluck(
                        'supply_commitment_id'
                    )
                    ->map(
                        static fn (
                            mixed $id
                        ): int => (int) $id
                    )
                    ->unique()
                    ->values()
                    ->all();

            if (
                array_diff(
                    $sourceCommitmentIds,
                    $commitmentIds
                ) !== []
            ) {
                $this->fail(
                    'Fallback Offer demo menggunakan source Commitment di luar demo Forecast. Reset dibatalkan.'
                );
            }
        }

        if ($commitmentIds !== []) {
            $externalOfferSourceQuery =
                DB::table(
                    'fallback_offer_sources'
                )
                    ->whereIn(
                        'supply_commitment_id',
                        $commitmentIds
                    );

            if (
                $fallbackOfferIds !== []
            ) {
                $externalOfferSourceQuery
                    ->whereNotIn(
                        'fallback_offer_id',
                        $fallbackOfferIds
                    );
            }

            if (
                $externalOfferSourceQuery
                    ->exists()
            ) {
                $this->fail(
                    'Commitment demo masih dipakai Fallback Offer di luar scenario demo. Reset dibatalkan.'
                );
            }
        }

        $readinessChecklistIds =
            $this->integerIds(
                DB::table(
                    'readiness_checklists'
                )
                    ->where(
                        'forecast_id',
                        $forecastId
                    )
            );

        if (
            $readinessChecklistIds !== []
            && DB::table(
                'readiness_checklists'
            )
                ->whereNotIn(
                    'id',
                    $readinessChecklistIds
                )
                ->whereIn(
                    'supersedes_checklist_id',
                    $readinessChecklistIds
                )
                ->exists()
        ) {
            $this->fail(
                'Readiness Checklist demo direferensikan checklist di luar scenario demo. Reset dibatalkan.'
            );
        }

        $readinessItemIds =
            $readinessChecklistIds === []
                ? []
                : $this->integerIds(
                    DB::table(
                        'readiness_items'
                    )
                        ->whereIn(
                            'readiness_checklist_id',
                            $readinessChecklistIds
                        )
                );

        $observationIds =
            Schema::hasTable(
                'forecast_derived_state_observations'
            )
                ? $this->integerIds(
                    DB::table(
                        'forecast_derived_state_observations'
                    )
                        ->where(
                            'forecast_id',
                            $forecastId
                        )
                )
                : [];

        $fulfilmentFeedbackIds =
            Schema::hasTable(
                'fulfilment_feedbacks'
            )
                ? $this->integerIds(
                    DB::table(
                        'fulfilment_feedbacks'
                    )
                        ->where(
                            'forecast_id',
                            $forecastId
                        )
                )
                : [];

        $entityIds = [
            DemandForecast::class => [
                $forecastId,
            ],

            ExpectedHarvest::class =>
                $expectedHarvestIds,

            SupplyCommitment::class =>
                $commitmentIds,

            CommitmentVersion::class =>
                $versionIds,

            CommitmentConfidenceEvent::class =>
                $confidenceEventIds,

            ConfidenceRecoveryRequest::class =>
                $recoveryRequestIds,

            FallbackRequest::class =>
                $fallbackRequestIds,

            FallbackOffer::class =>
                $fallbackOfferIds,

            FallbackOfferSource::class =>
                $fallbackOfferSourceIds,

            ReadinessChecklist::class =>
                $readinessChecklistIds,

            ReadinessItem::class =>
                $readinessItemIds,

            ForecastDerivedStateObservation::class =>
                $observationIds,

            FulfilmentFeedback::class =>
                $fulfilmentFeedbackIds,
        ];

        /*
         * Audit rows tidak memiliki FK ke business
         * entity, jadi purge menggunakan exact
         * morph type + entity ID.
         */
        $this->deleteAuditRows(
            $entityIds
        );

        /*
         * Inbox demo adalah bagian dari deterministic
         * presentation state.
         *
         * Selain recipient demo, notification yang
         * menunjuk exact deleted demo entities juga
         * dibersihkan.
         */
        $this->deleteNotifications(
            demoUserIds:
                $demoUserIds,
            entityIds:
                $entityIds,
        );

        if (
            $fulfilmentFeedbackIds !== []
        ) {
            DB::table(
                'fulfilment_feedbacks'
            )
                ->whereIn(
                    'id',
                    $fulfilmentFeedbackIds
                )
                ->delete();
        }

        if (
            $observationIds !== []
        ) {
            /*
             * Model observation append-only secara
             * normal. Query builder digunakan hanya
             * di controlled demo reset.
             */
            DB::table(
                'forecast_derived_state_observations'
            )
                ->whereIn(
                    'id',
                    $observationIds
                )
                ->delete();
        }

        if (
            $readinessItemIds !== []
        ) {
            DB::table(
                'readiness_items'
            )
                ->whereIn(
                    'id',
                    $readinessItemIds
                )
                ->delete();
        }

        if (
            $readinessChecklistIds !== []
        ) {
            DB::table(
                'readiness_checklists'
            )
                ->whereIn(
                    'id',
                    $readinessChecklistIds
                )
                ->update([
                    'supersedes_checklist_id' =>
                        null,
                ]);

            DB::table(
                'readiness_checklists'
            )
                ->whereIn(
                    'id',
                    $readinessChecklistIds
                )
                ->delete();
        }

        if (
            $fallbackOfferSourceIds !== []
        ) {
            DB::table(
                'fallback_offer_sources'
            )
                ->whereIn(
                    'id',
                    $fallbackOfferSourceIds
                )
                ->delete();
        }

        if (
            $fallbackOfferIds !== []
        ) {
            DB::table(
                'fallback_offers'
            )
                ->whereIn(
                    'id',
                    $fallbackOfferIds
                )
                ->delete();
        }

        if (
            $fallbackRequestIds !== []
        ) {
            DB::table(
                'fallback_requests'
            )
                ->whereIn(
                    'id',
                    $fallbackRequestIds
                )
                ->delete();
        }

        if (
            $recoveryRequestIds !== []
        ) {
            DB::table(
                'confidence_recovery_requests'
            )
                ->whereIn(
                    'id',
                    $recoveryRequestIds
                )
                ->delete();
        }

        if (
            $confidenceEventIds !== []
        ) {
            DB::table(
                'commitment_confidence_events'
            )
                ->whereIn(
                    'id',
                    $confidenceEventIds
                )
                ->delete();
        }

        if (
            $commitmentIds !== []
        ) {
            /*
             * supply_commitments.active_version_id
             * menunjuk commitment_versions.
             *
             * Putuskan circular FK terlebih dahulu.
             */
            DB::table(
                'supply_commitments'
            )
                ->whereIn(
                    'id',
                    $commitmentIds
                )
                ->update([
                    'active_version_id' =>
                        null,
                ]);
        }

        if (
            $versionIds !== []
        ) {
            DB::table(
                'commitment_versions'
            )
                ->whereIn(
                    'id',
                    $versionIds
                )
                ->delete();
        }

        if (
            $commitmentIds !== []
        ) {
            DB::table(
                'supply_commitments'
            )
                ->whereIn(
                    'id',
                    $commitmentIds
                )
                ->delete();
        }

        if (
            $expectedHarvestIds !== []
        ) {
            DB::table(
                'expected_harvests'
            )
                ->whereIn(
                    'id',
                    $expectedHarvestIds
                )
                ->delete();
        }

        DB::table(
            'demand_forecasts'
        )
            ->where(
                'id',
                $forecastId
            )
            ->delete();

    }

    private function assertNoForeignOwnership(
        int $forecastId,
        int $primaryOrganizationId,
        int $networkOrganizationId
    ): void {
        $allowedContributorIds = [
            $primaryOrganizationId,
            $networkOrganizationId,
        ];

        if (
            DB::table(
                'supply_commitments'
            )
                ->where(
                    'forecast_id',
                    $forecastId
                )
                ->whereNotIn(
                    'organization_id',
                    $allowedContributorIds
                )
                ->exists()
        ) {
            $this->fail(
                'Demo Forecast memiliki Commitment milik organization di luar dua deterministic KDKMP demo.'
            );
        }

        if (
            DB::table(
                'fallback_requests'
            )
                ->where(
                    'forecast_id',
                    $forecastId
                )
                ->where(
                    'requester_organization_id',
                    '!=',
                    $primaryOrganizationId
                )
                ->exists()
        ) {
            $this->fail(
                'Demo Forecast memiliki Fallback Request dari organization di luar requester demo.'
            );
        }

        if (
            DB::table(
                'readiness_checklists'
            )
                ->where(
                    'forecast_id',
                    $forecastId
                )
                ->whereNotIn(
                    'organization_id',
                    $allowedContributorIds
                )
                ->exists()
        ) {
            $this->fail(
                'Demo Forecast memiliki Readiness Checklist milik organization non-demo.'
            );
        }

        $fallbackRequestIds =
            DB::table(
                'fallback_requests'
            )
                ->where(
                    'forecast_id',
                    $forecastId
                )
                ->pluck('id');

        if (
            $fallbackRequestIds->isNotEmpty()
            && DB::table(
                'fallback_offers'
            )
                ->whereIn(
                    'fallback_request_id',
                    $fallbackRequestIds
                )
                ->where(
                    'supplier_organization_id',
                    '!=',
                    $networkOrganizationId
                )
                ->exists()
        ) {
            $this->fail(
                'Demo Fallback Request memiliki Offer dari supplier organization di luar deterministic NETWORK demo.'
            );
        }

        if (
            Schema::hasTable(
                'fulfilment_feedbacks'
            )
            && DB::table(
                'fulfilment_feedbacks'
            )
                ->where(
                    'forecast_id',
                    $forecastId
                )
                ->whereNotIn(
                    'contributor_organization_id',
                    $allowedContributorIds
                )
                ->exists()
        ) {
            $this->fail(
                'Demo Forecast memiliki Fulfilment Feedback untuk contributor non-demo.'
            );
        }
    }

    /**
     * @param array<class-string<Model>, array<int, int>> $entityIds
     */
    private function deleteAuditRows(
        array $entityIds
    ): void {
        if (
            ! Schema::hasTable(
                'audit_logs'
            )
        ) {
            return;
        }

        DB::table(
            'audit_logs'
        )
            ->where(
                function (
                    Builder $query
                ) use (
                    $entityIds
                ): void {
                    $query->whereRaw(
                        '1 = 0'
                    );

                    foreach (
                        $entityIds
                        as $modelClass => $ids
                    ) {
                        if ($ids === []) {
                            continue;
                        }

                        $morphClass =
                            $this->morphClass(
                                $modelClass
                            );

                        $query->orWhere(
                            function (
                                Builder $nested
                            ) use (
                                $morphClass,
                                $ids
                            ): void {
                                $nested
                                    ->where(
                                        'entity_type',
                                        $morphClass
                                    )
                                    ->whereIn(
                                        'entity_id',
                                        $ids
                                    );
                            }
                        );
                    }
                }
            )
            ->delete();
    }

    /**
     * @param array<int, int> $demoUserIds
     * @param array<class-string<Model>, array<int, int>> $entityIds
     */
    private function deleteNotifications(
        array $demoUserIds,
        array $entityIds
    ): void {
        if (
            ! Schema::hasTable(
                'notifications'
            )
        ) {
            return;
        }

        DB::table(
            'notifications'
        )
            ->where(
                function (
                    Builder $query
                ) use (
                    $demoUserIds,
                    $entityIds
                ): void {
                    $query->whereRaw(
                        '1 = 0'
                    );

                    if (
                        $demoUserIds !== []
                    ) {
                        $query->orWhereIn(
                            'recipient_user_id',
                            $demoUserIds
                        );
                    }

                    foreach (
                        $entityIds
                        as $modelClass => $ids
                    ) {
                        if ($ids === []) {
                            continue;
                        }

                        $morphClass =
                            $this->morphClass(
                                $modelClass
                            );

                        $query->orWhere(
                            function (
                                Builder $nested
                            ) use (
                                $morphClass,
                                $ids
                            ): void {
                                $nested
                                    ->where(
                                        'related_entity_type',
                                        $morphClass
                                    )
                                    ->whereIn(
                                        'related_entity_id',
                                        $ids
                                    );
                            }
                        );
                    }
                }
            )
            ->delete();
    }

    /**
     * @param class-string<Model> $modelClass
     */
    private function morphClass(
        string $modelClass
    ): string {
        /** @var Model $model */
        $model =
            new $modelClass();

        return $model
            ->getMorphClass();
    }

    /**
     * @return array<int, int>
     */
    private function demoUserIds(): array
    {
        $emails = [
            DemoIdentifiers::ADMIN_EMAIL,
            ...DemoIdentifiers
                ::operationalAccountEmails(),
        ];

        return DB::table(
            'users'
        )
            ->whereIn(
                'email',
                $emails
            )
            ->pluck('id')
            ->map(
                static fn (
                    mixed $id
                ): int => (int) $id
            )
            ->values()
            ->all();
    }

    private function organizationId(
        string $code
    ): ?int {
        $id =
            DB::table(
                'organizations'
            )
                ->where(
                    'code',
                    $code
                )
                ->value('id');

        return $id === null
            ? null
            : (int) $id;
    }

    /**
     * @return array<int, int>
     */
    private function integerIds(
        Builder $query
    ): array {
        return $query
            ->pluck('id')
            ->map(
                static fn (
                    mixed $id
                ): int => (int) $id
            )
            ->values()
            ->all();
    }

    private function assertDemoEnabled():
        void {
        if (
            ! (bool) config(
                'siagapasok.demo.enabled',
                false
            )
        ) {
            throw new AuthorizationException(
                'Demo reset hanya tersedia ketika SiagaPasok demo mode aktif.'
            );
        }
    }

    private function assertResetActor(
        User $actor
    ): void {
        if (
            ! $actor->isSppgUser()
            || ! $actor
                ->hasValidIdentityContext()
            || $actor->email
                !== DemoIdentifiers
                    ::SPPG_EMAIL
            || $actor
                ->organization
                ?->code
                !== DemoIdentifiers
                    ::SPPG_CODE
        ) {
            throw new AuthorizationException(
                'Demo reset hanya dapat dijalankan oleh seeded SPPG Badung Demo account.'
            );
        }
    }

    private function fail(
        string $message
    ): never {
        throw ValidationException::withMessages([
            'demo_reset' =>
                $message,
        ]);
    }
}