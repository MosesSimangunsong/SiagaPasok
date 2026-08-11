<?php

namespace Tests\Feature\Fulfilment;

use App\Enums\ForecastStatus;
use App\Enums\FulfilmentResult;
use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Models\Commodity;
use App\Models\DemandForecast;
use App\Models\ForecastDerivedStateObservation;
use App\Models\FulfilmentFeedback;
use App\Models\Organization;
use App\Models\Unit;
use App\Models\User;
use App\Services\Fulfilment\FulfilmentFeedbackService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FulfilmentHttpContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse(
                '2026-08-26 10:00:00'
            )
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_sppg_sees_only_closed_forecast_with_historical_handoff(): void
    {
        $context =
            $this->createContext(
                'SPPG-READ'
            );

        $this->createHandoff(
            $context['forecast'],
            $context['contributor'],
            '100.000000'
        );

        $response =
            $this
                ->actingAs(
                    $context['sppg_user']
                )
                ->get(
                    '/sppg/fulfilments'
                );

        $response
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'Sppg/Fulfilment/Index'
                        )
                        ->has(
                            'forecasts',
                            1
                        )
                        ->where(
                            'forecasts.0.id',
                            $context[
                                'forecast'
                            ]->id
                        )
                        ->where(
                            'forecasts.0.contributor_count',
                            1
                        )
                        ->where(
                            'forecasts.0.feedback_recorded_count',
                            0
                        )
                        ->where(
                            'forecasts.0.feedback_pending_count',
                            1
                        )
            );

        $this
            ->actingAs(
                $context['sppg_user']
            )
            ->get(
                '/sppg/forecasts/'
                .$context['forecast']->id
                .'/fulfilments'
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'Sppg/Fulfilment/Show'
                        )
                        ->where(
                            'forecast.id',
                            $context[
                                'forecast'
                            ]->id
                        )
                        ->has(
                            'contributors',
                            1
                        )
                        ->where(
                            'contributors.0.organization.id',
                            $context[
                                'contributor'
                            ]->id
                        )
                        ->where(
                            'contributors.0.planned_volume_snapshot',
                            '100.000000'
                        )
                        ->where(
                            'contributors.0.feedback',
                            null
                        )
                        ->where(
                            'contributors.0.can_record',
                            true
                        )
            );
    }

    public function test_sppg_records_feedback_through_explicit_http_command_and_result_is_server_derived(): void
    {
        $context =
            $this->createContext(
                'SPPG-WRITE'
            );

        $this->createHandoff(
            $context['forecast'],
            $context['contributor'],
            '100.000000'
        );

        $this
            ->actingAs(
                $context['sppg_user']
            )
            ->post(
                '/sppg/forecasts/'
                .$context['forecast']->id
                .'/fulfilments/'
                .$context['contributor']->id,
                [
                    'delivered_volume' =>
                        '60.000000',

                    'fulfilment_date' =>
                        '2026-08-25',

                    'reason_note' =>
                        'Sebagian volume tidak terealisasi.',

                    /*
                     * Tidak termasuk validation
                     * contract dan tidak boleh menjadi
                     * authority.
                     */
                    'result' =>
                        FulfilmentResult
                            ::FULFILLED
                            ->value,
                ]
            )
            ->assertRedirect(
                route(
                    'sppg.fulfilments.show',
                    $context['forecast']
                )
            );

        $feedback =
            FulfilmentFeedback::query()
                ->firstOrFail();

        $this->assertSame(
            '100.000000',
            (string)
            $feedback
                ->planned_volume_snapshot
        );

        $this->assertSame(
            '60.000000',
            (string)
            $feedback
                ->delivered_volume
        );

        $this->assertSame(
            FulfilmentResult::PARTIAL,
            $feedback->result
        );

        $this
            ->actingAs(
                $context['sppg_user']
            )
            ->get(
                '/sppg/forecasts/'
                .$context['forecast']->id
                .'/fulfilments'
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->where(
                            'contributors.0.feedback.result',
                            FulfilmentResult
                                ::PARTIAL
                                ->value
                        )
                        ->where(
                            'contributors.0.can_record',
                            false
                        )
                        ->where(
                            'summary.recorded_count',
                            1
                        )
                        ->where(
                            'summary.pending_count',
                            0
                        )
            );
    }

    public function test_other_sppg_cannot_view_or_record_feedback_for_forecast(): void
    {
        $context =
            $this->createContext(
                'SPPG-ISOLATION'
            );

        $this->createHandoff(
            $context['forecast'],
            $context['contributor'],
            '100.000000'
        );

        $otherSppg =
            $this->createOrganization(
                OrganizationType::SPPG,
                'SPPG-OTHER'
            );

        $otherUser =
            User::factory()->create([
                'organization_id' =>
                    $otherSppg->id,

                'role' =>
                    UserRole::SPPG_USER,

                'is_active' =>
                    true,
            ]);

        $this
            ->actingAs(
                $otherUser
            )
            ->get(
                '/sppg/forecasts/'
                .$context['forecast']->id
                .'/fulfilments'
            )
            ->assertForbidden();

        $this
            ->actingAs(
                $otherUser
            )
            ->post(
                '/sppg/forecasts/'
                .$context['forecast']->id
                .'/fulfilments/'
                .$context['contributor']->id,
                [
                    'delivered_volume' =>
                        '100.000000',

                    'fulfilment_date' =>
                        '2026-08-25',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'fulfilment_feedbacks',
            0
        );
    }

    public function test_published_forecast_does_not_expose_fulfilment_surface(): void
    {
        $context =
            $this->createContext(
                'NOT-CLOSED'
            );

        $this->createHandoff(
            $context['forecast'],
            $context['contributor'],
            '100.000000'
        );

        $context['forecast']->update([
            'status' =>
                ForecastStatus::PUBLISHED,

            'closed_at' =>
                null,
        ]);

        $this
            ->actingAs(
                $context['sppg_user']
            )
            ->get(
                '/sppg/forecasts/'
                .$context['forecast']->id
                .'/fulfilments'
            )
            ->assertNotFound();

        $this
            ->actingAs(
                $context['sppg_user']
            )
            ->post(
                '/sppg/forecasts/'
                .$context['forecast']->id
                .'/fulfilments/'
                .$context['contributor']->id,
                [
                    'delivered_volume' =>
                        '100.000000',

                    'fulfilment_date' =>
                        '2026-08-25',
                ]
            )
            ->assertSessionHasErrors(
                'status'
            );

        $this->assertDatabaseCount(
            'fulfilment_feedbacks',
            0
        );
    }

    public function test_kdkmp_reads_only_own_organization_feedback(): void
    {
        $contextA =
            $this->createContext(
                'KDKMP-A'
            );

        $contextB =
            $this->createContext(
                'KDKMP-B'
            );

        $this->createHandoff(
            $contextA['forecast'],
            $contextA['contributor'],
            '100.000000'
        );

        $this->createHandoff(
            $contextB['forecast'],
            $contextB['contributor'],
            '80.000000'
        );

        $feedbackA =
            app(
                FulfilmentFeedbackService::class
            )->record(
                $contextA['sppg_user'],
                $contextA['forecast'],
                $contextA[
                    'contributor'
                ]->id,
                [
                    'delivered_volume' =>
                        '100.000000',

                    'fulfilment_date' =>
                        '2026-08-25',
                ]
            );

        $feedbackB =
            app(
                FulfilmentFeedbackService::class
            )->record(
                $contextB['sppg_user'],
                $contextB['forecast'],
                $contextB[
                    'contributor'
                ]->id,
                [
                    'delivered_volume' =>
                        '0',

                    'fulfilment_date' =>
                        '2026-08-25',

                    'reason_note' =>
                        'Tidak terealisasi.',
                ]
            );

        foreach (
            [
                $contextA[
                    'kdkmp_operator'
                ],
                $contextA[
                    'kdkmp_manager'
                ],
            ]
            as $user
        ) {
            $this
                ->actingAs(
                    $user
                )
                ->get(
                    '/kdkmp/fulfilments'
                )
                ->assertOk()
                ->assertInertia(
                    fn (Assert $page) =>
                        $page
                            ->component(
                                'Kdkmp/Fulfilment/Index'
                            )
                            ->has(
                                'feedbacks',
                                1
                            )
                            ->where(
                                'feedbacks.0.id',
                                $feedbackA->id
                            )
                );

            $this
                ->actingAs(
                    $user
                )
                ->get(
                    '/kdkmp/fulfilments/'
                    .$feedbackA->id
                )
                ->assertOk()
                ->assertInertia(
                    fn (Assert $page) =>
                        $page
                            ->component(
                                'Kdkmp/Fulfilment/Show'
                            )
                            ->where(
                                'feedback.id',
                                $feedbackA->id
                            )
                            ->where(
                                'feedback.result',
                                FulfilmentResult
                                    ::FULFILLED
                                    ->value
                            )
                );

            $this
                ->actingAs(
                    $user
                )
                ->get(
                    '/kdkmp/fulfilments/'
                    .$feedbackB->id
                )
                ->assertForbidden();
        }

        /*
         * Route KDKMP tidak menjadi alternative
         * SPPG reader.
         */
        $this
            ->actingAs(
                $contextA['sppg_user']
            )
            ->get(
                '/kdkmp/fulfilments'
            )
            ->assertForbidden();
    }

    private function createHandoff(
        DemandForecast $forecast,
        Organization $contributor,
        string $plannedVolume,
    ): void {
        ForecastDerivedStateObservation
            ::create([
                'forecast_id' =>
                    $forecast->id,

                'forecast_version' =>
                    $forecast->version,

                'demand_target' =>
                    $plannedVolume,

                'total_safe_supply' =>
                    '0.000000',

                'shortfall' =>
                    $plannedVolume,

                'ready_for_procurement' =>
                    false,

                'contributor_organization_ids' =>
                    [],

                'contributor_safe_supply_by_organization' =>
                    [],

                'reason_codes' => [
                    'VOLUME_NOT_READY',
                ],

                'evaluated_at' =>
                    '2026-08-20 08:00:00',

                'created_at' =>
                    '2026-08-20 08:00:00',
            ]);

        ForecastDerivedStateObservation
            ::create([
                'forecast_id' =>
                    $forecast->id,

                'forecast_version' =>
                    $forecast->version,

                'demand_target' =>
                    $plannedVolume,

                'total_safe_supply' =>
                    $plannedVolume,

                'shortfall' =>
                    '0.000000',

                'ready_for_procurement' =>
                    true,

                'contributor_organization_ids' => [
                    $contributor->id,
                ],

                'contributor_safe_supply_by_organization' => [
                    $contributor->id =>
                        $plannedVolume,
                ],

                'reason_codes' =>
                    [],

                'evaluated_at' =>
                    '2026-08-20 09:00:00',

                'created_at' =>
                    '2026-08-20 09:00:00',
            ]);
    }

    private function createContext(
        string $suffix,
    ): array {
        $unit =
            Unit::create([
                'code' =>
                    "KG-HTTP-{$suffix}",

                'name' =>
                    "Kilogram HTTP {$suffix}",

                'symbol' =>
                    'kg',

                'decimal_precision' =>
                    6,

                'is_active' =>
                    true,
            ]);

        $commodity =
            Commodity::create([
                'code' =>
                    "COM-HTTP-{$suffix}",

                'name' =>
                    "Komoditas HTTP {$suffix}",

                'default_unit_id' =>
                    $unit->id,

                'harvest_behavior' =>
                    null,

                'notes' =>
                    null,

                'is_active' =>
                    true,
            ]);

        $sppg =
            $this->createOrganization(
                OrganizationType::SPPG,
                "SPPG-HTTP-{$suffix}"
            );

        $contributor =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-HTTP-{$suffix}"
            );

        $sppgUser =
            User::factory()->create([
                'organization_id' =>
                    $sppg->id,

                'role' =>
                    UserRole::SPPG_USER,

                'is_active' =>
                    true,
            ]);

        $operator =
            User::factory()->create([
                'organization_id' =>
                    $contributor->id,

                'role' =>
                    UserRole::KDKMP_OPERATOR,

                'is_active' =>
                    true,
            ]);

        $manager =
            User::factory()->create([
                'organization_id' =>
                    $contributor->id,

                'role' =>
                    UserRole::KDKMP_MANAGER,

                'is_active' =>
                    true,
            ]);

        $forecast =
            DemandForecast::create([
                'sppg_organization_id' =>
                    $sppg->id,

                'commodity_id' =>
                    $commodity->id,

                'unit_id' =>
                    $unit->id,

                'forecast_code' =>
                    "FRC-HTTP-{$suffix}",

                'target_volume' =>
                    '100.000000',

                'required_start_at' =>
                    '2026-08-20 08:00:00',

                'required_end_at' =>
                    '2026-08-25 17:00:00',

                'freshness_interval_hours' =>
                    24,

                'status' =>
                    ForecastStatus::CLOSED,

                'notes' =>
                    'HTTP fulfilment fixture',

                'published_at' =>
                    '2026-08-10 08:00:00',

                'closed_at' =>
                    '2026-08-25 18:00:00',

                'cancelled_at' =>
                    null,

                'cancellation_reason' =>
                    null,

                'version' =>
                    1,

                'created_by' =>
                    $sppgUser->id,

                'updated_by' =>
                    $sppgUser->id,
            ]);

        return [
            'unit' =>
                $unit,

            'commodity' =>
                $commodity,

            'sppg' =>
                $sppg,

            'contributor' =>
                $contributor,

            'sppg_user' =>
                $sppgUser,

            'kdkmp_operator' =>
                $operator,

            'kdkmp_manager' =>
                $manager,

            'forecast' =>
                $forecast,
        ];
    }

    private function createOrganization(
        OrganizationType $type,
        string $code,
    ): Organization {
        return Organization::create([
            'code' =>
                $code,

            'name' =>
                $code,

            'organization_type' =>
                $type,

            'is_active' =>
                true,

            'general_location' =>
                'Lokasi HTTP Fulfilment',
        ]);
    }
}