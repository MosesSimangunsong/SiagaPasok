<?php

namespace Tests\Feature\Demo;

use App\Enums\ReadinessType;
use App\Enums\SupplyConfidence;
use App\Models\DemandForecast;
use App\Models\FallbackOffer;
use App\Models\FallbackRequest;
use App\Models\Producer;
use App\Models\ReadinessChecklist;
use App\Models\ReadinessRequirement;
use App\Models\SupplyCommitment;
use App\Models\User;
use App\Services\Demo\DemoContributorReadinessService;
use App\Services\Demo\DemoFallbackOfferService;
use App\Services\Demo\DemoFallbackRequestService;
use App\Services\Demo\DemoFallbackSourceService;
use App\Services\Demo\DemoResetService;
use App\Services\Demo\DemoSupplyRiskService;
use App\Services\Readiness\ReadyForProcurementEvaluationService;
use App\Services\Supply\SupplyMetricsService;
use App\Support\Demo\DemoIdentifiers;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\FallbackConcurrencyDatabase;
use Tests\TestCase;

class DemoPostgresRehearsalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (
            ! FallbackConcurrencyDatabase
                ::isConfigured()
        ) {
            $this->markTestSkipped(
                'Real PostgreSQL rehearsal belum diaktifkan. '
                .'Set SIAGAPASOK_REAL_DB_CONCURRENCY=true.'
            );
        }

        FallbackConcurrencyDatabase
            ::configure();

        config()->set(
            'siagapasok.demo.enabled',
            true
        );

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse(
                '2026-08-11 10:00:00'
            )
        );

        $exitCode =
            Artisan::call(
                'migrate:fresh',
                [
                    '--database' =>
                        FallbackConcurrencyDatabase
                            ::CONNECTION,

                    '--seed' =>
                        true,

                    '--force' =>
                        true,
                ]
            );

        if ($exitCode !== 0) {
            throw new RuntimeException(
                'Clean PostgreSQL migrate:fresh --seed gagal: '
                .Artisan::output()
            );
        }
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        DB::purge(
            FallbackConcurrencyDatabase
                ::CONNECTION
        );

        parent::tearDown();
    }

    public function test_clean_postgres_seed_full_demo_reset_and_reseed_are_reproducible(): void
    {
        /*
         * Gate 1:
         * benar-benar PostgreSQL disposable DB.
         */
        $connection =
            DB::connection(
                FallbackConcurrencyDatabase
                    ::CONNECTION
            );

        $this->assertSame(
            'pgsql',
            $connection->getDriverName()
        );

        $this->assertSame(
            FallbackConcurrencyDatabase
                ::databaseName(),
            $connection->getDatabaseName()
        );

        $this->assertNotSame(
            'db_siagapasok',
            $connection->getDatabaseName()
        );

        /*
         * Gate 2:
         * clean migration + DatabaseSeeder
         * menghasilkan legal baseline.
         */
        $forecast =
            $this->demoForecast();

        $this->assertBaseline(
            $forecast
        );

        $actors =
            $this->actors();

        /*
         * Gate 3:
         * Supply risk.
         *
         * Safe 400
         * -> Safe 250
         * -> At-Risk 150
         * -> Shortfall 150.
         */
        app(
            DemoSupplyRiskService::class
        )->apply(
            $actors['primary_operator']
        );

        $disruptedMetrics =
            app(
                SupplyMetricsService::class
            )->calculate(
                $forecast->refresh()
            );

        $this->assertSame(
            '250.000000',
            $disruptedMetrics
                ->totalSafeSupply
        );

        $this->assertSame(
            '150.000000',
            $disruptedMetrics
                ->atRiskSupply
        );

        $this->assertSame(
            '150.000000',
            $disruptedMetrics
                ->shortfall
        );

        $this->assertFalse(
            $disruptedMetrics
                ->volumeReady
        );

        /*
         * Gate 4:
         * Fallback Request
         * Operator -> Manager broadcast.
         */
        $requestService =
            app(
                DemoFallbackRequestService::class
            );

        $fallbackRequest =
            $requestService
                ->prepareAndSubmit(
                    $actors[
                        'primary_operator'
                    ]
                );

        $fallbackRequest =
            $requestService
                ->approveBroadcast(
                    $actors[
                        'primary_manager'
                    ]
                );

        $this->assertTrue(
            $fallbackRequest->isOpen()
        );

        $this->assertSame(
            '150.000000',
            (string)
                $fallbackRequest
                    ->requested_volume
        );

        /*
         * Gate 5:
         * NETWORK source 160.
         *
         * Operator prepare/submit
         * -> Manager approve
         * -> APPROVED + GREEN.
         */
        $sourceService =
            app(
                DemoFallbackSourceService::class
            );

        $source =
            $sourceService
                ->prepareAndSubmit(
                    $actors[
                        'network_operator'
                    ]
                );

        $source =
            $sourceService
                ->approve(
                    $actors[
                        'network_manager'
                    ]
                );

        $this->assertSame(
            SupplyConfidence::GREEN,
            $source
                ->current_confidence
        );

        $this->assertTrue(
            $source
                ->activeVersion
                ->isApproved()
        );

        /*
         * Gate 6:
         * Offer 160
         * -> reserve 160
         * -> Accept 150
         * -> allocate 150
         * -> release 10.
         */
        $offerService =
            app(
                DemoFallbackOfferService::class
            );

        $offer =
            $offerService
                ->prepareAndSubmit(
                    $actors[
                        'network_operator'
                    ]
                );

        $offer =
            $offerService
                ->approveForAvailability(
                    $actors[
                        'network_manager'
                    ]
                );

        $offer
            ->refresh()
            ->load('sources');

        $this->assertTrue(
            $offer->isAvailable()
        );

        $this->assertCount(
            1,
            $offer->sources
        );

        $offerSource =
            $offer->sources->first();

        $this->assertSame(
            '160.000000',
            (string)
                $offerSource
                    ->reserved_volume
        );

        $this->assertSame(
            '0.000000',
            (string)
                $offerSource
                    ->allocated_volume
        );

        $this->assertSame(
            '0.000000',
            (string)
                $offerSource
                    ->released_volume
        );

        /*
         * AVAILABLE belum boleh menjadi Safe Supply.
         */
        $beforeAccept =
            app(
                SupplyMetricsService::class
            )->calculate(
                $forecast->refresh()
            );

        $this->assertSame(
            '250.000000',
            $beforeAccept
                ->totalSafeSupply
        );

        $this->assertSame(
            '0.000000',
            $beforeAccept
                ->fallbackSafeSupply
        );

        $offer =
            $offerService
                ->accept(
                    $actors[
                        'primary_manager'
                    ]
                );

        $offer
            ->refresh()
            ->load('sources');

        $offerSource =
            $offer->sources->first();

        $this->assertTrue(
            $offer->isAccepted()
        );

        $this->assertSame(
            '150.000000',
            (string)
                $offer->accepted_volume
        );

        $this->assertSame(
            '160.000000',
            (string)
                $offerSource
                    ->reserved_volume
        );

        $this->assertSame(
            '150.000000',
            (string)
                $offerSource
                    ->allocated_volume
        );

        $this->assertSame(
            '10.000000',
            (string)
                $offerSource
                    ->released_volume
        );

        $fallbackRequest->refresh();

        $this->assertTrue(
            $fallbackRequest
                ->isFulfilled()
        );

        $recoveredMetrics =
            app(
                SupplyMetricsService::class
            )->calculate(
                $forecast->refresh()
            );

        $this->assertSame(
            '250.000000',
            $recoveredMetrics
                ->directSafeSupply
        );

        $this->assertSame(
            '150.000000',
            $recoveredMetrics
                ->atRiskSupply
        );

        $this->assertSame(
            '150.000000',
            $recoveredMetrics
                ->fallbackSafeSupply
        );

        $this->assertSame(
            '400.000000',
            $recoveredMetrics
                ->totalSafeSupply
        );

        $this->assertSame(
            '0.000000',
            $recoveredMetrics
                ->shortfall
        );

        $this->assertTrue(
            $recoveredMetrics
                ->volumeReady
        );

        $this->assertCount(
            2,
            $recoveredMetrics
                ->contributorOrganizationIds
        );

        /*
         * Gate 7:
         * kedua contributor menyelesaikan
         * Logistics + Document maker-checker.
         */
        $readinessService =
            app(
                DemoContributorReadinessService::class
            );

        foreach (
            [
                [
                    'operator' =>
                        $actors[
                            'primary_operator'
                        ],

                    'manager' =>
                        $actors[
                            'primary_manager'
                        ],
                ],
                [
                    'operator' =>
                        $actors[
                            'network_operator'
                        ],

                    'manager' =>
                        $actors[
                            'network_manager'
                        ],
                ],
            ]
            as $contributor
        ) {
            foreach (
                [
                    ReadinessType::LOGISTICS,
                    ReadinessType::DOCUMENT,
                ]
                as $readinessType
            ) {
                $readinessService
                    ->prepareAndSubmit(
                        $contributor[
                            'operator'
                        ],
                        $readinessType
                    );

                $readinessService
                    ->approve(
                        $contributor[
                            'manager'
                        ],
                        $readinessType
                    );
            }
        }

        $this->assertSame(
            4,
            ReadinessChecklist::query()
                ->where(
                    'forecast_id',
                    $forecast->id
                )
                ->count()
        );

        $checklists =
            ReadinessChecklist::query()
                ->where(
                    'forecast_id',
                    $forecast->id
                )
                ->get();

        foreach (
            $checklists
            as $checklist
        ) {
            $this->assertTrue(
                $checklist->isApproved()
            );

            $this->assertNotSame(
                $checklist->submitted_by,
                $checklist->reviewed_by
            );
        }

        /*
         * Gate 8:
         * canonical RFP menjadi TRUE.
         */
        $ready =
            app(
                ReadyForProcurementEvaluationService::class
            )->evaluate(
                $forecast->refresh()
            );

        $this->assertTrue(
            $ready->volumeReady
        );

        $this->assertTrue(
            $ready
                ->allContributorsLogisticsReady
        );

        $this->assertTrue(
            $ready
                ->allContributorsDocumentReady
        );

        $this->assertTrue(
            $ready
                ->readyForProcurement
        );

        $this->assertSame(
            [],
            $ready->reasonCodes
        );

        /*
         * Gate 9:
         * controlled reset.
         */
        $resetForecast =
            app(
                DemoResetService::class
            )->reset(
                $actors['sppg']
            );

        $this->assertBaseline(
            $resetForecast
        );

        /*
         * Gate 10:
         * seed kembali sesudah reset.
         *
         * Harus tetap deterministic dan
         * tidak menciptakan duplicate rows.
         */
        $seedExitCode =
            Artisan::call(
                'db:seed',
                [
                    '--database' =>
                        FallbackConcurrencyDatabase
                            ::CONNECTION,

                    '--class' =>
                        DatabaseSeeder::class,

                    '--force' =>
                        true,
                ]
            );

        $this->assertSame(
            0,
            $seedExitCode,
            Artisan::output()
        );

        $finalForecast =
            $this->demoForecast();

        $this->assertBaseline(
            $finalForecast
        );
    }

    private function assertBaseline(
        DemandForecast $forecast
    ): void {
        $this->assertTrue(
            $forecast->isPublished()
        );

        $this->assertSame(
            DemoIdentifiers
                ::FORECAST_TARGET_VOLUME,
            (string)
                $forecast->target_volume
        );

        $commitments =
            SupplyCommitment::query()
                ->with('activeVersion')
                ->where(
                    'forecast_id',
                    $forecast->id
                )
                ->get();

        $this->assertCount(
            2,
            $commitments
        );

        foreach (
            $commitments
            as $commitment
        ) {
            $this->assertSame(
                SupplyConfidence::GREEN,
                $commitment
                    ->current_confidence
            );

            $this->assertTrue(
                $commitment
                    ->activeVersion
                    ->isApproved()
            );
        }

        $metrics =
            app(
                SupplyMetricsService::class
            )->calculate(
                $forecast
            );

        $this->assertSame(
            '400.000000',
            $metrics->demandTarget
        );

        $this->assertSame(
            '400.000000',
            $metrics->directSafeSupply
        );

        $this->assertSame(
            '0.000000',
            $metrics->atRiskSupply
        );

        $this->assertSame(
            '0.000000',
            $metrics->fallbackSafeSupply
        );

        $this->assertSame(
            '400.000000',
            $metrics->totalSafeSupply
        );

        $this->assertSame(
            '100.00',
            $metrics->coveragePercent
        );

        $this->assertSame(
            '0.000000',
            $metrics->shortfall
        );

        $this->assertSame(
            '0.000000',
            $metrics->surplus
        );

        $this->assertTrue(
            $metrics->volumeReady
        );

        $this->assertCount(
            1,
            $metrics
                ->contributorOrganizationIds
        );

        $this->assertSame(
            0,
            FallbackRequest::query()
                ->where(
                    'forecast_id',
                    $forecast->id
                )
                ->count()
        );

        $this->assertSame(
            0,
            FallbackOffer::query()
                ->where(
                    'availability_note',
                    DemoIdentifiers
                        ::FALLBACK_OFFER_NOTE
                )
                ->count()
        );

        $this->assertSame(
            0,
            ReadinessChecklist::query()
                ->where(
                    'forecast_id',
                    $forecast->id
                )
                ->count()
        );

        $rfp =
            app(
                ReadyForProcurementEvaluationService::class
            )->evaluate(
                $forecast
            );

        $this->assertTrue(
            $rfp->volumeReady
        );

        $this->assertFalse(
            $rfp
                ->readyForProcurement
        );

        /*
         * Deterministic base identities.
         */
        $this->assertSame(
            6,
            User::query()
                ->whereIn(
                    'email',
                    [
                        DemoIdentifiers
                            ::ADMIN_EMAIL,

                        ...DemoIdentifiers
                            ::operationalAccountEmails(),
                    ]
                )
                ->count()
        );

        $this->assertSame(
            18,
            Producer::query()
                ->whereIn(
                    'producer_code',
                    DemoIdentifiers
                        ::producerCodes()
                )
                ->count()
        );

        $this->assertSame(
            2,
            ReadinessRequirement::query()
                ->whereIn(
                    'requirement_code',
                    [
                        DemoIdentifiers
                            ::LOGISTICS_REQUIREMENT_CODE,

                        DemoIdentifiers
                            ::DOCUMENT_REQUIREMENT_CODE,
                    ]
                )
                ->count()
        );
    }

    /**
     * @return array{
     *     sppg: User,
     *     primary_operator: User,
     *     primary_manager: User,
     *     network_operator: User,
     *     network_manager: User
     * }
     */
    private function actors(): array
    {
        return [
            'sppg' =>
                $this->demoUser(
                    DemoIdentifiers
                        ::SPPG_EMAIL
                ),

            'primary_operator' =>
                $this->demoUser(
                    DemoIdentifiers
                        ::PRIMARY_OPERATOR_EMAIL
                ),

            'primary_manager' =>
                $this->demoUser(
                    DemoIdentifiers
                        ::PRIMARY_MANAGER_EMAIL
                ),

            'network_operator' =>
                $this->demoUser(
                    DemoIdentifiers
                        ::NETWORK_OPERATOR_EMAIL
                ),

            'network_manager' =>
                $this->demoUser(
                    DemoIdentifiers
                        ::NETWORK_MANAGER_EMAIL
                ),
        ];
    }

    private function demoUser(
        string $email
    ): User {
        return User::query()
            ->with('organization')
            ->where(
                'email',
                $email
            )
            ->firstOrFail();
    }

    private function demoForecast():
        DemandForecast {
        return DemandForecast::query()
            ->where(
                'forecast_code',
                DemoIdentifiers
                    ::FORECAST_CODE
            )
            ->firstOrFail();
    }
}