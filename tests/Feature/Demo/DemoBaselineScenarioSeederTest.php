<?php

namespace Tests\Feature\Demo;

use App\Enums\SupplyConfidence;
use App\Models\DemandForecast;
use App\Models\Organization;
use App\Models\Producer;
use App\Models\SupplyCommitment;
use App\Models\User;
use App\Services\Supply\SupplyMetricsService;
use App\Support\Demo\DemoIdentifiers;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoBaselineScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DemoBaselineScenarioSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_baseline_seed_is_rejected_when_demo_mode_is_disabled(): void
    {
        config()->set(
            'siagapasok.demo.enabled',
            false
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->seed(
            DemoBaselineScenarioSeeder::class
        );
    }

    public function test_demo_baseline_creates_canonical_covered_state_and_is_idempotent(): void
    {
        config()->set(
            'siagapasok.demo.enabled',
            true
        );

        $this->seed(
            DatabaseSeeder::class
        );

        $this->seed(
            DatabaseSeeder::class
        );

        $forecast = DemandForecast::query()
            ->where(
                'forecast_code',
                DemoIdentifiers::FORECAST_CODE
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

        $operator = User::query()
            ->where(
                'email',
                DemoIdentifiers
                    ::PRIMARY_OPERATOR_EMAIL
            )
            ->firstOrFail();

        $manager = User::query()
            ->where(
                'email',
                DemoIdentifiers
                    ::PRIMARY_MANAGER_EMAIL
            )
            ->firstOrFail();

        $baselineProducer = Producer::query()
            ->where(
                'organization_id',
                $primaryOrganization->id
            )
            ->where(
                'producer_code',
                DemoIdentifiers
                    ::PRIMARY_BASELINE_PRODUCER_CODE
            )
            ->firstOrFail();

        $riskProducer = Producer::query()
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

        $baselineCommitment =
            SupplyCommitment::query()
                ->where(
                    'forecast_id',
                    $forecast->id
                )
                ->where(
                    'producer_id',
                    $baselineProducer->id
                )
                ->firstOrFail()
                ->load('activeVersion');

        $riskCommitment =
            SupplyCommitment::query()
                ->where(
                    'forecast_id',
                    $forecast->id
                )
                ->where(
                    'producer_id',
                    $riskProducer->id
                )
                ->firstOrFail()
                ->load('activeVersion');

        $this->assertTrue(
            $forecast->isPublished()
        );

        $this->assertSame(
            DemoIdentifiers::FORECAST_TARGET_VOLUME,
            (string) $forecast->target_volume
        );

        $this->assertDatabaseCount(
            'demand_forecasts',
            1
        );

        $this->assertDatabaseCount(
            'expected_harvests',
            2
        );

        $this->assertDatabaseCount(
            'supply_commitments',
            2
        );

        $this->assertDatabaseCount(
            'commitment_versions',
            2
        );

        $this->assertSame(
            SupplyConfidence::GREEN,
            $baselineCommitment
                ->current_confidence
        );

        $this->assertSame(
            SupplyConfidence::GREEN,
            $riskCommitment
                ->current_confidence
        );

        $this->assertNotNull(
            $baselineCommitment
                ->activeVersion
        );

        $this->assertNotNull(
            $riskCommitment
                ->activeVersion
        );

        $this->assertTrue(
            $baselineCommitment
                ->activeVersion
                ->isApproved()
        );

        $this->assertTrue(
            $riskCommitment
                ->activeVersion
                ->isApproved()
        );

        $this->assertSame(
            DemoIdentifiers::PRIMARY_BASELINE_VOLUME,
            (string)
                $baselineCommitment
                    ->activeVersion
                    ->min_volume
        );

        $this->assertSame(
            DemoIdentifiers::PRIMARY_RISK_VOLUME,
            (string)
                $riskCommitment
                    ->activeVersion
                    ->min_volume
        );

        $this->assertSame(
            $operator->id,
            $baselineCommitment
                ->activeVersion
                ->submitted_by
        );

        $this->assertSame(
            $manager->id,
            $baselineCommitment
                ->activeVersion
                ->reviewed_by
        );

        $this->assertSame(
            $operator->id,
            $riskCommitment
                ->activeVersion
                ->submitted_by
        );

        $this->assertSame(
            $manager->id,
            $riskCommitment
                ->activeVersion
                ->reviewed_by
        );

        $this->assertDatabaseHas(
            'commitment_confidence_events',
            [
                'commitment_id' =>
                    $baselineCommitment->id,
                'to_confidence' =>
                    SupplyConfidence::GREEN->value,
                'reason_code' =>
                    'INITIAL_APPROVAL',
                'actor_user_id' =>
                    $manager->id,
            ]
        );

        $this->assertDatabaseHas(
            'commitment_confidence_events',
            [
                'commitment_id' =>
                    $riskCommitment->id,
                'to_confidence' =>
                    SupplyConfidence::GREEN->value,
                'reason_code' =>
                    'INITIAL_APPROVAL',
                'actor_user_id' =>
                    $manager->id,
            ]
        );

        $metrics = app(
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
                    '400.000000',
            ],
            $metrics
                ->contributorSafeSupplyByOrganization
        );
    }
}