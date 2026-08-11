<?php

namespace Tests\Feature\Demo;

use App\Enums\ForecastStatus;
use App\Enums\OrganizationType;
use App\Enums\ReadinessType;
use App\Enums\SupplyConfidence;
use App\Enums\UserRole;
use App\Models\Commodity;
use App\Models\DemandForecast;
use App\Models\FallbackOffer;
use App\Models\FallbackRequest;
use App\Models\Organization;
use App\Models\Producer;
use App\Models\ReadinessChecklist;
use App\Models\ReadinessRequirement;
use App\Models\SupplyCommitment;
use App\Models\Unit;
use App\Models\User;
use App\Services\Demo\DemoContributorReadinessService;
use App\Services\Demo\DemoFallbackOfferService;
use App\Services\Demo\DemoFallbackRequestService;
use App\Services\Demo\DemoFallbackSourceService;
use App\Services\Demo\DemoSupplyRiskService;
use App\Services\Readiness\ReadyForProcurementEvaluationService;
use App\Services\Supply\SupplyMetricsService;
use App\Support\Demo\DemoIdentifiers;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_reset_is_unavailable_when_demo_mode_is_disabled(): void
    {
        config()->set(
            'siagapasok.demo.enabled',
            true
        );

        $this->seed(
            DatabaseSeeder::class
        );

        $sppg =
            $this->demoUser(
                DemoIdentifiers::SPPG_EMAIL
            );

        $forecast =
            $this->demoForecast();

        config()->set(
            'siagapasok.demo.enabled',
            false
        );

        $this->actingAs(
            $sppg
        )
            ->post(
                route('demo.reset')
            )
            ->assertNotFound();

        $this->assertDatabaseHas(
            'demand_forecasts',
            [
                'id' =>
                    $forecast->id,

                'forecast_code' =>
                    DemoIdentifiers
                        ::FORECAST_CODE,
            ]
        );
    }

    public function test_non_demo_sppg_account_cannot_reset_demo_dataset(): void
    {
        config()->set(
            'siagapasok.demo.enabled',
            true
        );

        $this->seed(
            DatabaseSeeder::class
        );

        [
            $nonDemoUser,
        ] = $this
            ->createNonDemoSentinel();

        $forecast =
            $this->demoForecast();

        $this->actingAs(
            $nonDemoUser
        )
            ->post(
                route('demo.reset')
            )
            ->assertForbidden();

        $this->assertDatabaseHas(
            'demand_forecasts',
            [
                'id' =>
                    $forecast->id,

                'forecast_code' =>
                    DemoIdentifiers
                        ::FORECAST_CODE,
            ]
        );
    }

    public function test_completed_demo_scenario_can_be_reset_to_deterministic_baseline_without_touching_non_demo_forecast(): void
    {
        $context =
            $this
                ->completeReadyForProcurementScenario();

        [
            ,
            $nonDemoForecast,
        ] = $this
            ->createNonDemoSentinel();

        $beforeReset =
            app(
                ReadyForProcurementEvaluationService::class
            )->evaluate(
                $context['forecast']
            );

        $this->assertTrue(
            $beforeReset
                ->readyForProcurement
        );

        $this->assertDatabaseHas(
            'fallback_requests',
            [
                'broadcast_note' =>
                    DemoIdentifiers
                        ::FALLBACK_REQUEST_NOTE,
            ]
        );

        $this->assertDatabaseHas(
            'fallback_offers',
            [
                'availability_note' =>
                    DemoIdentifiers
                        ::FALLBACK_OFFER_NOTE,
            ]
        );

        $this->assertSame(
            4,
            ReadinessChecklist::query()
                ->where(
                    'forecast_id',
                    $context['forecast']->id
                )
                ->count()
        );

        $this->actingAs(
            $context['sppg']
        )
            ->post(
                route('demo.reset')
            )
            ->assertRedirect(
                route('home')
            );

        $this->assertDatabaseHas(
            'demand_forecasts',
            [
                'id' =>
                    $nonDemoForecast->id,

                'forecast_code' =>
                    'NON-DEMO-FRC-KEEP',
            ]
        );

        $this->assertSame(
            1,
            DemandForecast::query()
                ->where(
                    'forecast_code',
                    DemoIdentifiers
                        ::FORECAST_CODE
                )
                ->count()
        );

        $forecast =
            $this->demoForecast();

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
        }

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

        $this->assertTrue(
            $metrics->volumeReady
        );

        /*
         * Baseline hanya mempunyai PRIMARY
         * contributor lagi.
         */
        $this->assertCount(
            1,
            $metrics
                ->contributorOrganizationIds
        );

        /*
         * RFP kembali FALSE karena readiness
         * scenario sudah dihapus.
         */
        $afterReset =
            app(
                ReadyForProcurementEvaluationService::class
            )->evaluate(
                $forecast
            );

        $this->assertTrue(
            $afterReset->volumeReady
        );

        $this->assertFalse(
            $afterReset
                ->readyForProcurement
        );

        /*
         * Identity/base-data dipertahankan.
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

        /*
         * Reset kedua harus aman dan tidak
         * membuat duplicate baseline.
         */
        $this->post(
            route('demo.reset')
        )
            ->assertRedirect(
                route('home')
            );

        $forecast =
            $this->demoForecast();

        $this->assertSame(
            1,
            DemandForecast::query()
                ->where(
                    'forecast_code',
                    DemoIdentifiers
                        ::FORECAST_CODE
                )
                ->count()
        );

        $this->assertSame(
            2,
            SupplyCommitment::query()
                ->where(
                    'forecast_id',
                    $forecast->id
                )
                ->count()
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
            ReadinessChecklist::query()
                ->where(
                    'forecast_id',
                    $forecast->id
                )
                ->count()
        );

        $secondMetrics =
            app(
                SupplyMetricsService::class
            )->calculate(
                $forecast
            );

        $this->assertSame(
            '400.000000',
            $secondMetrics
                ->totalSafeSupply
        );

        $this->assertSame(
            '0.000000',
            $secondMetrics
                ->shortfall
        );

        $this->assertDatabaseHas(
            'demand_forecasts',
            [
                'id' =>
                    $nonDemoForecast->id,

                'forecast_code' =>
                    'NON-DEMO-FRC-KEEP',
            ]
        );
    }

    /**
     * @return array{
     *     sppg: User,
     *     primary_operator: User,
     *     primary_manager: User,
     *     network_operator: User,
     *     network_manager: User,
     *     forecast: DemandForecast
     * }
     */
    private function completeReadyForProcurementScenario():
        array {
        config()->set(
            'siagapasok.demo.enabled',
            true
        );

        $this->seed(
            DatabaseSeeder::class
        );

        $sppg =
            $this->demoUser(
                DemoIdentifiers::SPPG_EMAIL
            );

        $primaryOperator =
            $this->demoUser(
                DemoIdentifiers
                    ::PRIMARY_OPERATOR_EMAIL
            );

        $primaryManager =
            $this->demoUser(
                DemoIdentifiers
                    ::PRIMARY_MANAGER_EMAIL
            );

        $networkOperator =
            $this->demoUser(
                DemoIdentifiers
                    ::NETWORK_OPERATOR_EMAIL
            );

        $networkManager =
            $this->demoUser(
                DemoIdentifiers
                    ::NETWORK_MANAGER_EMAIL
            );

        $forecast =
            $this->demoForecast();

        app(
            DemoSupplyRiskService::class
        )->apply(
            $primaryOperator
        );

        $requestService =
            app(
                DemoFallbackRequestService::class
            );

        $requestService
            ->prepareAndSubmit(
                $primaryOperator
            );

        $requestService
            ->approveBroadcast(
                $primaryManager
            );

        $sourceService =
            app(
                DemoFallbackSourceService::class
            );

        $sourceService
            ->prepareAndSubmit(
                $networkOperator
            );

        $sourceService
            ->approve(
                $networkManager
            );

        $offerService =
            app(
                DemoFallbackOfferService::class
            );

        $offerService
            ->prepareAndSubmit(
                $networkOperator
            );

        $offerService
            ->approveForAvailability(
                $networkManager
            );

        $offerService
            ->accept(
                $primaryManager
            );

        $readinessService =
            app(
                DemoContributorReadinessService::class
            );

        foreach (
            [
                [
                    $primaryOperator,
                    $primaryManager,
                ],
                [
                    $networkOperator,
                    $networkManager,
                ],
            ]
            as [
                $operator,
                $manager,
            ]
        ) {
            $readinessService
                ->prepareAndSubmit(
                    $operator,
                    ReadinessType::LOGISTICS
                );

            $readinessService
                ->approve(
                    $manager,
                    ReadinessType::LOGISTICS
                );

            $readinessService
                ->prepareAndSubmit(
                    $operator,
                    ReadinessType::DOCUMENT
                );

            $readinessService
                ->approve(
                    $manager,
                    ReadinessType::DOCUMENT
                );
        }

        $evaluation =
            app(
                ReadyForProcurementEvaluationService::class
            )->evaluate(
                $forecast
            );

        $this->assertTrue(
            $evaluation
                ->readyForProcurement
        );

        return [
            'sppg' =>
                $sppg,

            'primary_operator' =>
                $primaryOperator,

            'primary_manager' =>
                $primaryManager,

            'network_operator' =>
                $networkOperator,

            'network_manager' =>
                $networkManager,

            'forecast' =>
                $forecast,
        ];
    }

    /**
     * @return array{
     *     0: User,
     *     1: DemandForecast
     * }
     */
    private function createNonDemoSentinel():
        array {
        $organization =
            Organization::query()
                ->create([
                    'code' =>
                        'NON-DEMO-SPPG-KEEP',

                    'name' =>
                        'SPPG Non Demo Keep',

                    'organization_type' =>
                        OrganizationType::SPPG,

                    'general_location' =>
                        'Data non-demo untuk safety test',

                    'is_active' =>
                        true,
                ]);

        $user =
            User::query()
                ->create([
                    'organization_id' =>
                        $organization->id,

                    'name' =>
                        'Non Demo SPPG User',

                    'email' =>
                        'non-demo.sppg@example.test',

                    'password' =>
                        'NonDemoPassword123!',

                    'role' =>
                        UserRole::SPPG_USER,

                    'is_active' =>
                        true,
                ]);

        $commodity =
            Commodity::query()
                ->where(
                    'code',
                    'KANGKUNG'
                )
                ->firstOrFail();

        $unit =
            Unit::query()
                ->where(
                    'code',
                    'kg'
                )
                ->firstOrFail();

        $forecast =
            DemandForecast::query()
                ->create([
                    'sppg_organization_id' =>
                        $organization->id,

                    'commodity_id' =>
                        $commodity->id,

                    'unit_id' =>
                        $unit->id,

                    'forecast_code' =>
                        'NON-DEMO-FRC-KEEP',

                    'target_volume' =>
                        '50.000000',

                    'required_start_at' =>
                        now()->addDays(40),

                    'required_end_at' =>
                        now()->addDays(41),

                    'freshness_interval_hours' =>
                        72,

                    'status' =>
                        ForecastStatus::DRAFT,

                    'notes' =>
                        'NON-DEMO SAFETY SENTINEL',

                    'version' =>
                        1,

                    'created_by' =>
                        $user->id,

                    'updated_by' =>
                        $user->id,
                ]);

        return [
            $user,
            $forecast,
        ];
    }

    private function demoUser(
        string $email
    ): User {
        return User::query()
            ->with(
                'organization'
            )
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