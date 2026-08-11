<?php

namespace Tests\Feature\Demo;

use App\Models\DemandForecast;
use App\Models\FallbackOffer;
use App\Models\FallbackRequest;
use App\Models\User;
use App\Services\Demo\DemoFallbackOfferService;
use App\Services\Demo\DemoFallbackRequestService;
use App\Services\Demo\DemoFallbackSourceService;
use App\Services\Demo\DemoSupplyRiskService;
use App\Services\Fallback\FallbackRequestService;
use App\Services\Supply\SupplyMetricsService;
use App\Support\Demo\DemoIdentifiers;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoFallbackOfferRecoveryStageTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_offer_recovery_completes_locked_160_150_10_scenario(): void
    {
        $context =
            $this->sourceReadyScenario();

        $this->actingAs(
            $context['network_operator']
        )
            ->post(
                route(
                    'demo.scenario.fallback.offer.prepare'
                )
            )
            ->assertRedirect(
                route('home')
            );

        $offer =
            $this->demoOffer(
                $context['request']
            );

        $this->assertTrue(
            $offer->isPendingApproval()
        );

        $sourceLedger =
            $offer->sources()
                ->firstOrFail();

        $this->assertSame(
            '0.000000',
            (string)
                $sourceLedger
                    ->reserved_volume
        );

        $this->actingAs(
            $context['network_manager']
        )
            ->post(
                route(
                    'demo.scenario.fallback.offer.approve'
                )
            )
            ->assertRedirect(
                route('home')
            );

        $offer
            ->refresh()
            ->load('sources');

        $this->assertTrue(
            $offer->isAvailable()
        );

        $sourceLedger =
            $offer->sources->first();

        $this->assertSame(
            '160.000000',
            (string)
                $sourceLedger
                    ->reserved_volume
        );

        $this->assertSame(
            '0.000000',
            (string)
                $sourceLedger
                    ->allocated_volume
        );

        $this->assertSame(
            '0.000000',
            (string)
                $sourceLedger
                    ->released_volume
        );

        /*
         * AVAILABLE belum Safe Supply.
         */
        $beforeAccept =
            app(
                SupplyMetricsService::class
            )->calculate(
                $context['forecast']
            );

        $this->assertSame(
            '250.000000',
            $beforeAccept->totalSafeSupply
        );

        $this->assertSame(
            '0.000000',
            $beforeAccept->fallbackSafeSupply
        );

        $this->assertSame(
            '150.000000',
            $beforeAccept->shortfall
        );

        $this->actingAs(
            $context['primary_manager']
        )
            ->post(
                route(
                    'demo.scenario.fallback.offer.accept'
                )
            )
            ->assertRedirect(
                route('home')
            );

        $offer
            ->refresh()
            ->load('sources');

        $context['request']
            ->refresh();

        $sourceLedger =
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
                $sourceLedger
                    ->reserved_volume
        );

        $this->assertSame(
            '150.000000',
            (string)
                $sourceLedger
                    ->allocated_volume
        );

        $this->assertSame(
            '10.000000',
            (string)
                $sourceLedger
                    ->released_volume
        );

        $this->assertTrue(
            $context['request']
                ->isFulfilled()
        );

        $this->assertSame(
            '0.000000',
            app(
                FallbackRequestService::class
            )->calculateRemainingVolume(
                $context['request']
            )
        );

        $metrics =
            app(
                SupplyMetricsService::class
            )->calculate(
                $context['forecast']
                    ->refresh()
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
            '150.000000',
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

        $this->assertCount(
            2,
            $metrics
                ->contributorOrganizationIds
        );

        /*
         * Retry dengan volume sama harus idempotent.
         */
        $this->post(
            route(
                'demo.scenario.fallback.offer.accept'
            )
        )
            ->assertRedirect(
                route('home')
            );

        $offer
            ->refresh()
            ->load('sources');

        $sourceLedger =
            $offer->sources->first();

        $this->assertSame(
            '150.000000',
            (string)
                $sourceLedger
                    ->allocated_volume
        );

        $this->assertSame(
            '10.000000',
            (string)
                $sourceLedger
                    ->released_volume
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_user_id' =>
                    $context[
                        'network_manager'
                    ]->id,

                'action' =>
                    'FALLBACK_OFFER_RESERVED',

                'entity_id' =>
                    $offer->id,
            ]
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_user_id' =>
                    $context[
                        'primary_manager'
                    ]->id,

                'action' =>
                    'FALLBACK_OFFER_ACCEPTED',

                'entity_id' =>
                    $offer->id,
            ]
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_user_id' =>
                    $context[
                        'primary_manager'
                    ]->id,

                'action' =>
                    'FALLBACK_OFFER_RESERVE_RELEASED',

                'entity_id' =>
                    $offer->id,
            ]
        );
    }

    public function test_demo_offer_routes_are_unavailable_when_demo_mode_is_disabled(): void
    {
        $context =
            $this->sourceReadyScenario();

        config()->set(
            'siagapasok.demo.enabled',
            false
        );

        $this->actingAs(
            $context['network_operator']
        )
            ->post(
                route(
                    'demo.scenario.fallback.offer.prepare'
                )
            )
            ->assertNotFound();

        $this->assertDatabaseCount(
            'fallback_offers',
            0
        );
    }

    /**
     * @return array{
     *     primary_operator: User,
     *     primary_manager: User,
     *     network_operator: User,
     *     network_manager: User,
     *     forecast: DemandForecast,
     *     request: FallbackRequest
     * }
     */
    private function sourceReadyScenario(): array
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

        $requestService =
            app(
                DemoFallbackRequestService::class
            );

        $request =
            $requestService
                ->prepareAndSubmit(
                    $primaryOperator
                );

        $request =
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

            'request' =>
                $request,
        ];
    }

    private function demoOffer(
        FallbackRequest $request
    ): FallbackOffer {
        return FallbackOffer::query()
            ->with('sources')
            ->where(
                'fallback_request_id',
                $request->id
            )
            ->where(
                'availability_note',
                DemoIdentifiers
                    ::FALLBACK_OFFER_NOTE
            )
            ->firstOrFail();
    }
}