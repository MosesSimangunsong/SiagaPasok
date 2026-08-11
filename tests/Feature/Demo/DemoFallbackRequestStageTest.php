<?php

namespace Tests\Feature\Demo;

use App\Enums\FallbackRequestStatus;
use App\Models\DemandForecast;
use App\Models\FallbackRequest;
use App\Models\User;
use App\Services\Demo\DemoSupplyRiskService;
use App\Services\Fallback\FallbackRequestService;
use App\Support\Demo\DemoIdentifiers;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoFallbackRequestStageTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_fallback_request_is_unavailable_when_demo_mode_is_disabled(): void
    {
        [
            $operator,
        ] = $this->seedDisruptedScenario();

        config()->set(
            'siagapasok.demo.enabled',
            false
        );

        $this->actingAs(
            $operator
        )
            ->post(
                route(
                    'demo.scenario.fallback.request'
                )
            )
            ->assertNotFound();

        $this->assertDatabaseCount(
            'fallback_requests',
            0
        );
    }

    public function test_network_operator_cannot_prepare_primary_demo_fallback_request(): void
    {
        $this->seedDisruptedScenario();

        $networkOperator =
            User::query()
                ->where(
                    'email',
                    DemoIdentifiers
                        ::NETWORK_OPERATOR_EMAIL
                )
                ->firstOrFail();

        $this->actingAs(
            $networkOperator
        )
            ->post(
                route(
                    'demo.scenario.fallback.request'
                )
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'fallback_requests',
            0
        );
    }

    public function test_primary_operator_prepares_and_submits_fallback_request_150_from_canonical_shortfall(): void
    {
        [
            $operator,
            $forecast,
        ] = $this->seedDisruptedScenario();

        $this->actingAs(
            $operator
        )
            ->post(
                route(
                    'demo.scenario.fallback.request'
                )
            )
            ->assertRedirect(
                route('home')
            );

        $fallbackRequest =
            $this->demoRequest(
                $forecast
            );

        $this->assertSame(
            FallbackRequestStatus
                ::PENDING_APPROVAL,
            $fallbackRequest->status
        );

        $this->assertSame(
            DemoIdentifiers
                ::FALLBACK_REQUEST_VOLUME,
            (string)
                $fallbackRequest
                    ->requested_volume
        );

        $this->assertSame(
            $operator->organization_id,
            $fallbackRequest
                ->requester_organization_id
        );

        $this->assertSame(
            $operator->id,
            $fallbackRequest
                ->created_by
        );

        $this->assertSame(
            $operator->id,
            $fallbackRequest
                ->submitted_by
        );

        $this->assertSame(
            DemoIdentifiers
                ::FALLBACK_REQUEST_NOTE,
            $fallbackRequest
                ->broadcast_note
        );

        $this->assertTrue(
            $fallbackRequest
                ->response_deadline_at
                ->isFuture()
        );

        $requestService =
            app(
                FallbackRequestService::class
            );

        $this->assertSame(
            '0.000000',
            $requestService
                ->calculateAcceptedVolume(
                    $fallbackRequest
                )
        );

        $this->assertSame(
            '150.000000',
            $requestService
                ->calculateRemainingVolume(
                    $fallbackRequest
                )
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_user_id' =>
                    $operator->id,
                'action' =>
                    'FALLBACK_REQUEST_CREATED',
                'entity_id' =>
                    $fallbackRequest->id,
            ]
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_user_id' =>
                    $operator->id,
                'action' =>
                    'FALLBACK_REQUEST_SUBMITTED',
                'entity_id' =>
                    $fallbackRequest->id,
            ]
        );

        /*
         * Retry presentation action harus
         * tidak membuat request kedua.
         */
        $this->post(
            route(
                'demo.scenario.fallback.request'
            )
        )
            ->assertRedirect(
                route('home')
            );

        $this->assertDatabaseCount(
            'fallback_requests',
            1
        );
    }

    public function test_primary_manager_broadcasts_the_same_request_through_real_maker_checker_flow(): void
    {
        [
            $operator,
            $forecast,
        ] = $this->seedDisruptedScenario();

        $manager =
            User::query()
                ->where(
                    'email',
                    DemoIdentifiers
                        ::PRIMARY_MANAGER_EMAIL
                )
                ->firstOrFail();

        $this->actingAs(
            $operator
        )
            ->post(
                route(
                    'demo.scenario.fallback.request'
                )
            )
            ->assertRedirect(
                route('home')
            );

        $fallbackRequest =
            $this->demoRequest(
                $forecast
            );

        $this->assertSame(
            FallbackRequestStatus
                ::PENDING_APPROVAL,
            $fallbackRequest->status
        );

        $this->actingAs(
            $manager
        )
            ->post(
                route(
                    'demo.scenario.fallback.broadcast'
                )
            )
            ->assertRedirect(
                route('home')
            );

        $fallbackRequest->refresh();

        $this->assertSame(
            FallbackRequestStatus::OPEN,
            $fallbackRequest->status
        );

        $this->assertSame(
            $operator->id,
            $fallbackRequest
                ->submitted_by
        );

        $this->assertSame(
            $manager->id,
            $fallbackRequest
                ->reviewed_by
        );

        $this->assertNotSame(
            $fallbackRequest
                ->submitted_by,
            $fallbackRequest
                ->reviewed_by
        );

        $this->assertNotNull(
            $fallbackRequest
                ->opened_at
        );

        $this->assertSame(
            '150.000000',
            app(
                FallbackRequestService::class
            )
                ->calculateRemainingVolume(
                    $fallbackRequest
                )
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_user_id' =>
                    $manager->id,
                'action' =>
                    'FALLBACK_REQUEST_OPENED',
                'entity_id' =>
                    $fallbackRequest->id,
            ]
        );

        /*
         * Repeat Manager click tetap idempotent.
         */
        $this->post(
            route(
                'demo.scenario.fallback.broadcast'
            )
        )
            ->assertRedirect(
                route('home')
            );

        $this->assertDatabaseCount(
            'fallback_requests',
            1
        );
    }

    /**
     * @return array{
     *     0: User,
     *     1: DemandForecast
     * }
     */
    private function seedDisruptedScenario(): array
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
            $operator
        );

        return [
            $operator,
            $forecast,
        ];
    }

    private function demoRequest(
        DemandForecast $forecast
    ): FallbackRequest {
        return FallbackRequest::query()
            ->where(
                'forecast_id',
                $forecast->id
            )
            ->where(
                'broadcast_note',
                DemoIdentifiers
                    ::FALLBACK_REQUEST_NOTE
            )
            ->firstOrFail();
    }
}