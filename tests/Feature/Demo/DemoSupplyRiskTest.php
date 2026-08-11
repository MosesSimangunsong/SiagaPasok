<?php

namespace Tests\Feature\Demo;

use App\Enums\SupplyConfidence;
use App\Models\CommitmentConfidenceEvent;
use App\Models\DemandForecast;
use App\Models\Organization;
use App\Models\Producer;
use App\Models\SupplyCommitment;
use App\Models\User;
use App\Services\Demo\DemoSupplyRiskService;
use App\Services\Supply\SupplyMetricsService;
use App\Support\Demo\DemoIdentifiers;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSupplyRiskTest extends TestCase
{
    use RefreshDatabase;

    public function test_supply_risk_control_is_unavailable_when_demo_mode_is_disabled(): void
    {
        config()->set(
            'siagapasok.demo.enabled',
            true
        );

        $this->seed(
            DatabaseSeeder::class
        );

        $operator =
            User::query()
                ->where(
                    'email',
                    DemoIdentifiers
                        ::PRIMARY_OPERATOR_EMAIL
                )
                ->firstOrFail();

        $commitment =
            $this->riskCommitment();

        config()->set(
            'siagapasok.demo.enabled',
            false
        );

        $this->actingAs(
            $operator
        )
            ->post(
                route(
                    'demo.scenario.supply-risk'
                )
            )
            ->assertNotFound();

        $this->assertSame(
            SupplyConfidence::GREEN,
            $commitment
                ->refresh()
                ->current_confidence
        );
    }

    public function test_network_operator_cannot_trigger_primary_supply_risk_scenario(): void
    {
        config()->set(
            'siagapasok.demo.enabled',
            true
        );

        $this->seed(
            DatabaseSeeder::class
        );

        $networkOperator =
            User::query()
                ->where(
                    'email',
                    DemoIdentifiers
                        ::NETWORK_OPERATOR_EMAIL
                )
                ->firstOrFail();

        $commitment =
            $this->riskCommitment();

        $this->actingAs(
            $networkOperator
        )
            ->post(
                route(
                    'demo.scenario.supply-risk'
                )
            )
            ->assertForbidden();

        $this->assertSame(
            SupplyConfidence::GREEN,
            $commitment
                ->refresh()
                ->current_confidence
        );
    }

    public function test_primary_demo_operator_can_apply_supply_risk_through_real_confidence_workflow(): void
    {
        config()->set(
            'siagapasok.demo.enabled',
            true
        );

        $this->seed(
            DatabaseSeeder::class
        );

        $forecast =
            DemandForecast::query()
                ->where(
                    'forecast_code',
                    DemoIdentifiers
                        ::FORECAST_CODE
                )
                ->firstOrFail();

        $primaryOrganization =
            Organization::query()
                ->where(
                    'code',
                    DemoIdentifiers
                        ::PRIMARY_KDKMP_CODE
                )
                ->firstOrFail();

        $operator =
            User::query()
                ->where(
                    'email',
                    DemoIdentifiers
                        ::PRIMARY_OPERATOR_EMAIL
                )
                ->firstOrFail();

        $commitment =
            $this->riskCommitment();

        $initialMetrics =
            app(
                SupplyMetricsService::class
            )->calculate(
                $forecast
            );

        $this->assertSame(
            '400.000000',
            $initialMetrics
                ->directSafeSupply
        );

        $this->assertSame(
            '0.000000',
            $initialMetrics
                ->atRiskSupply
        );

        $this->assertSame(
            '0.000000',
            $initialMetrics
                ->shortfall
        );

        $this->actingAs(
            $operator
        )
            ->post(
                route(
                    'demo.scenario.supply-risk'
                )
            )
            ->assertRedirect(
                route('home')
            );

        $commitment->refresh();

        $this->assertSame(
            SupplyConfidence::YELLOW,
            $commitment
                ->current_confidence
        );

        $metrics =
            app(
                SupplyMetricsService::class
            )->calculate(
                $forecast->refresh()
            );

        $this->assertSame(
            '400.000000',
            $metrics->demandTarget
        );

        $this->assertSame(
            '250.000000',
            $metrics->directSafeSupply
        );

        $this->assertSame(
            '150.000000',
            $metrics->atRiskSupply
        );

        $this->assertSame(
            '0.000000',
            $metrics->fallbackSafeSupply
        );

        $this->assertSame(
            '250.000000',
            $metrics->totalSafeSupply
        );

        $this->assertSame(
            '62.50',
            $metrics->coveragePercent
        );

        $this->assertSame(
            '150.000000',
            $metrics->shortfall
        );

        $this->assertSame(
            '0.000000',
            $metrics->surplus
        );

        $this->assertFalse(
            $metrics->volumeReady
        );

        $this->assertSame(
            [
                $primaryOrganization->id,
            ],
            $metrics
                ->contributorOrganizationIds
        );

        $this->assertSame(
            [
                $primaryOrganization->id =>
                    '250.000000',
            ],
            $metrics
                ->contributorSafeSupplyByOrganization
        );

        $this->assertDatabaseHas(
            'commitment_confidence_events',
            [
                'commitment_id' =>
                    $commitment->id,
                'from_confidence' =>
                    SupplyConfidence
                        ::GREEN
                        ->value,
                'to_confidence' =>
                    SupplyConfidence
                        ::YELLOW
                        ->value,
                'reason_code' =>
                    DemoSupplyRiskService
                        ::REASON_CODE,
                'actor_user_id' =>
                    $operator->id,
            ]
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_user_id' =>
                    $operator->id,
                'actor_organization_id' =>
                    $primaryOrganization->id,
                'action' =>
                    'COMMITMENT_CONFIDENCE_DOWNGRADED',
                'entity_id' =>
                    $commitment->id,
            ]
        );

        /*
         * Repeated presentation click harus aman.
         */
        $this->post(
            route(
                'demo.scenario.supply-risk'
            )
        )
            ->assertRedirect(
                route('home')
            );

        $demoRiskEventCount =
            CommitmentConfidenceEvent::query()
                ->where(
                    'commitment_id',
                    $commitment->id
                )
                ->where(
                    'reason_code',
                    DemoSupplyRiskService
                        ::REASON_CODE
                )
                ->count();

        $this->assertSame(
            1,
            $demoRiskEventCount
        );
    }

    private function riskCommitment(): SupplyCommitment
    {
        $forecast =
            DemandForecast::query()
                ->where(
                    'forecast_code',
                    DemoIdentifiers
                        ::FORECAST_CODE
                )
                ->firstOrFail();

        $primaryOrganization =
            Organization::query()
                ->where(
                    'code',
                    DemoIdentifiers
                        ::PRIMARY_KDKMP_CODE
                )
                ->firstOrFail();

        $producer =
            Producer::query()
                ->where(
                    'organization_id',
                    $primaryOrganization->id
                )
                ->where(
                    'producer_code',
                    DemoIdentifiers
                        ::PRIMARY_RISK_PRODUCER_CODE
                )
                ->firstOrFail();

        return SupplyCommitment::query()
            ->where(
                'forecast_id',
                $forecast->id
            )
            ->where(
                'organization_id',
                $primaryOrganization->id
            )
            ->where(
                'producer_id',
                $producer->id
            )
            ->firstOrFail();
    }
}