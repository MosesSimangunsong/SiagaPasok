<?php

namespace Tests\Feature\Demo;

use App\Enums\SupplyConfidence;
use App\Models\CommitmentVersion;
use App\Models\DemandForecast;
use App\Models\ExpectedHarvest;
use App\Models\Producer;
use App\Models\SupplyCommitment;
use App\Models\User;
use App\Services\Demo\DemoFallbackRequestService;
use App\Services\Demo\DemoSupplyRiskService;
use App\Services\Fallback\FallbackCapacityService;
use App\Services\Supply\SupplyMetricsService;
use App\Support\Demo\DemoIdentifiers;
use App\Support\Demo\DemoScenarioActionResolver;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoFallbackSourceStageTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_control_is_unavailable_when_demo_mode_is_disabled(): void
    {
        $context =
            $this->openFallbackScenario();

        config()->set(
            'siagapasok.demo.enabled',
            false
        );

        $this->actingAs(
            $context['network_operator']
        )
            ->post(
                route(
                    'demo.scenario.fallback.source.prepare'
                )
            )
            ->assertNotFound();

        $this->assertNull(
            $this->networkSourceCommitment(
                $context['forecast'],
                $context['network_operator']
            )
        );
    }

    public function test_primary_operator_cannot_prepare_network_source_commitment(): void
    {
        $context =
            $this->openFallbackScenario();

        $this->actingAs(
            $context['primary_operator']
        )
            ->post(
                route(
                    'demo.scenario.fallback.source.prepare'
                )
            )
            ->assertForbidden();

        $this->assertNull(
            $this->networkSourceCommitment(
                $context['forecast'],
                $context['network_operator']
            )
        );
    }

    public function test_network_operator_creates_and_submits_source_160_through_real_commitment_workflow(): void
    {
        $context =
            $this->openFallbackScenario();

        $resolver =
            app(
                DemoScenarioActionResolver::class
            );

        $beforeAction =
            $resolver->resolve(
                $context['network_operator']
            );

        $this->assertSame(
            'fallback_source_prepare',
            $beforeAction['key']
        );

        $this->actingAs(
            $context['network_operator']
        )
            ->post(
                route(
                    'demo.scenario.fallback.source.prepare'
                )
            )
            ->assertRedirect(
                route('home')
            );

        $commitment =
            $this->networkSourceCommitment(
                $context['forecast'],
                $context['network_operator']
            );

        $this->assertNotNull(
            $commitment
        );

        $version =
            CommitmentVersion::query()
                ->where(
                    'commitment_id',
                    $commitment->id
                )
                ->where(
                    'version_no',
                    1
                )
                ->firstOrFail();

        $this->assertTrue(
            $version->isPendingApproval()
        );

        $this->assertSame(
            DemoIdentifiers::NETWORK_SOURCE_VOLUME,
            (string) $version->min_volume
        );

        $this->assertSame(
            DemoIdentifiers::NETWORK_SOURCE_VOLUME,
            (string) $version->max_volume
        );

        $this->assertSame(
            DemoIdentifiers
                ::NETWORK_SOURCE_COMMITMENT_NOTE,
            $version->notes
        );

        $this->assertSame(
            $context['network_operator']->id,
            $version->created_by
        );

        $this->assertSame(
            $context['network_operator']->id,
            $version->submitted_by
        );

        $this->assertNull(
            $commitment->current_confidence
        );

        $this->assertDatabaseHas(
            'expected_harvests',
            [
                'organization_id' =>
                    $context[
                        'network_operator'
                    ]->organization_id,

                'notes' =>
                    DemoIdentifiers
                        ::NETWORK_SOURCE_HARVEST_NOTE,

                'expected_min_volume' =>
                    DemoIdentifiers
                        ::NETWORK_SOURCE_VOLUME,

                'expected_max_volume' =>
                    DemoIdentifiers
                        ::NETWORK_SOURCE_VOLUME,
            ]
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_user_id' =>
                    $context[
                        'network_operator'
                    ]->id,

                'action' =>
                    'FALLBACK_SOURCE_COMMITMENT_CREATED',

                'entity_id' =>
                    $commitment->id,
            ]
        );

        /*
         * Repeated presentation click tidak boleh
         * membuat source Commitment kedua.
         */
        $this->post(
            route(
                'demo.scenario.fallback.source.prepare'
            )
        )
            ->assertRedirect(
                route('home')
            );

        $this->assertSame(
            1,
            SupplyCommitment::query()
                ->where(
                    'forecast_id',
                    $context['forecast']->id
                )
                ->where(
                    'organization_id',
                    $context[
                        'network_operator'
                    ]->organization_id
                )
                ->where(
                    'producer_id',
                    $commitment->producer_id
                )
                ->count()
        );

        $managerAction =
            $resolver->resolve(
                $context['network_manager']
            );

        $this->assertSame(
            'fallback_source_approve',
            $managerAction['key']
        );
    }

    public function test_network_manager_approves_source_and_makes_exactly_160_eligible_without_adding_requester_safe_supply(): void
    {
        $context =
            $this->openFallbackScenario();

        $this->actingAs(
            $context['network_operator']
        )
            ->post(
                route(
                    'demo.scenario.fallback.source.prepare'
                )
            )
            ->assertRedirect(
                route('home')
            );

        $commitment =
            $this->networkSourceCommitment(
                $context['forecast'],
                $context['network_operator']
            );

        $version =
            CommitmentVersion::query()
                ->where(
                    'commitment_id',
                    $commitment->id
                )
                ->where(
                    'version_no',
                    1
                )
                ->firstOrFail();

        $this->actingAs(
            $context['network_manager']
        )
            ->post(
                route(
                    'demo.scenario.fallback.source.approve'
                )
            )
            ->assertRedirect(
                route('home')
            );

        $commitment
            ->refresh()
            ->load('activeVersion');

        $version->refresh();

        $this->assertTrue(
            $version->isApproved()
        );

        $this->assertSame(
            $context['network_manager']->id,
            $version->reviewed_by
        );

        $this->assertNotSame(
            $version->submitted_by,
            $version->reviewed_by
        );

        $this->assertSame(
            SupplyConfidence::GREEN,
            $commitment->current_confidence
        );

        $this->assertNotNull(
            $commitment->activeVersion
        );

        $this->assertTrue(
            $commitment
                ->activeVersion
                ->isApproved()
        );

        $availableCapacity =
            app(
                FallbackCapacityService::class
            )->availableCapacity(
                $commitment,
                $context['forecast'],
                $context[
                    'network_operator'
                ]->organization_id
            );

        $this->assertSame(
            '160.000000',
            $availableCapacity
        );

        /*
         * NETWORK source belum menjadi requester
         * Safe Supply sampai Offer ACCEPTED.
         */
        $metrics =
            app(
                SupplyMetricsService::class
            )->calculate(
                $context['forecast']
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
            '150.000000',
            $metrics->shortfall
        );

        $this->assertFalse(
            $metrics->volumeReady
        );

        $this->assertDatabaseHas(
            'commitment_confidence_events',
            [
                'commitment_id' =>
                    $commitment->id,

                'to_confidence' =>
                    SupplyConfidence
                        ::GREEN
                        ->value,

                'reason_code' =>
                    'INITIAL_APPROVAL',

                'actor_user_id' =>
                    $context[
                        'network_manager'
                    ]->id,
            ]
        );

        /*
         * Repeated Manager click tetap idempotent.
         */
        $this->post(
            route(
                'demo.scenario.fallback.source.approve'
            )
        )
            ->assertRedirect(
                route('home')
            );

        $this->assertSame(
            '160.000000',
            app(
                FallbackCapacityService::class
            )->availableCapacity(
                $commitment->refresh(),
                $context['forecast'],
                $context[
                    'network_operator'
                ]->organization_id
            )
        );
    }

    /**
     * @return array{
     *     primary_operator: User,
     *     primary_manager: User,
     *     network_operator: User,
     *     network_manager: User,
     *     forecast: DemandForecast
     * }
     */
    private function openFallbackScenario(): array
    {
        config()->set(
            'siagapasok.demo.enabled',
            true
        );

        $this->seed(
            DatabaseSeeder::class
        );

        $primaryOperator =
            User::query()
                ->where(
                    'email',
                    DemoIdentifiers
                        ::PRIMARY_OPERATOR_EMAIL
                )
                ->firstOrFail();

        $primaryManager =
            User::query()
                ->where(
                    'email',
                    DemoIdentifiers
                        ::PRIMARY_MANAGER_EMAIL
                )
                ->firstOrFail();

        $networkOperator =
            User::query()
                ->where(
                    'email',
                    DemoIdentifiers
                        ::NETWORK_OPERATOR_EMAIL
                )
                ->firstOrFail();

        $networkManager =
            User::query()
                ->where(
                    'email',
                    DemoIdentifiers
                        ::NETWORK_MANAGER_EMAIL
                )
                ->firstOrFail();

        $forecast =
            DemandForecast::query()
                ->where(
                    'forecast_code',
                    DemoIdentifiers
                        ::FORECAST_CODE
                )
                ->firstOrFail();

        app(
            DemoSupplyRiskService::class
        )->apply(
            $primaryOperator
        );

        $fallbackService =
            app(
                DemoFallbackRequestService::class
            );

        $fallbackService
            ->prepareAndSubmit(
                $primaryOperator
            );

        $fallbackService
            ->approveBroadcast(
                $primaryManager
            );

        return [
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

    private function networkSourceCommitment(
        DemandForecast $forecast,
        User $networkOperator
    ): ?SupplyCommitment {
        $producer =
            Producer::query()
                ->where(
                    'organization_id',
                    $networkOperator
                        ->organization_id
                )
                ->where(
                    'producer_code',
                    DemoIdentifiers
                        ::NETWORK_SOURCE_PRODUCER_CODE
                )
                ->firstOrFail();

        return SupplyCommitment::query()
            ->where(
                'forecast_id',
                $forecast->id
            )
            ->where(
                'organization_id',
                $networkOperator
                    ->organization_id
            )
            ->where(
                'producer_id',
                $producer->id
            )
            ->first();
    }
}