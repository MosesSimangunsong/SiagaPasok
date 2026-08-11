<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ForecastStatus;
use App\Enums\NetworkRole;
use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Models\Commodity;
use App\Models\DemandForecast;
use App\Models\Organization;
use App\Models\SupplyNetworkLink;
use App\Models\Unit;
use App\Models\User;
use App\Enums\NotificationPriority;
use App\Enums\NotificationType;
use App\Services\Notification\NotificationService;
use App\Services\Forecast\DemandForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SppgDashboardHttpContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_sppg_dashboard_exposes_only_own_canonical_aggregate_forecast_truth(): void
    {
        $unit =
            Unit::create([
                'code' =>
                    'kg-sppg-dashboard',

                'name' =>
                    'Kilogram Dashboard',

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
                    'COM-SPPG-DASHBOARD',

                'name' =>
                    'Komoditas Dashboard',

                'default_unit_id' =>
                    $unit->id,

                'harvest_behavior' =>
                    null,

                'notes' =>
                    null,

                'is_active' =>
                    true,
            ]);

        $admin =
            User::factory()->create();

        $sppg =
            $this->organization(
                OrganizationType::SPPG,
                'SPPG-DASHBOARD-A'
            );

        $otherSppg =
            $this->organization(
                OrganizationType::SPPG,
                'SPPG-DASHBOARD-B'
            );

        $kdkmp =
            $this->organization(
                OrganizationType::KDKMP,
                'KDKMP-DASHBOARD-A'
            );

        $otherKdkmp =
            $this->organization(
                OrganizationType::KDKMP,
                'KDKMP-DASHBOARD-B'
            );

        SupplyNetworkLink::create([
            'sppg_organization_id' =>
                $sppg->id,

            'kdkmp_organization_id' =>
                $kdkmp->id,

            'network_role' =>
                NetworkRole::PRIMARY,

            'is_active' =>
                true,

            'configured_by' =>
                $admin->id,
        ]);

        SupplyNetworkLink::create([
            'sppg_organization_id' =>
                $otherSppg->id,

            'kdkmp_organization_id' =>
                $otherKdkmp->id,

            'network_role' =>
                NetworkRole::PRIMARY,

            'is_active' =>
                true,

            'configured_by' =>
                $admin->id,
        ]);

        $user =
            User::factory()->create([
                'organization_id' =>
                    $sppg->id,

                'role' =>
                    UserRole::SPPG_USER,

                'is_active' =>
                    true,
            ]);

        $otherUser =
            User::factory()->create([
                'organization_id' =>
                    $otherSppg->id,

                'role' =>
                    UserRole::SPPG_USER,

                'is_active' =>
                    true,
            ]);

        $operator =
            User::factory()->create([
                'organization_id' =>
                    $kdkmp->id,

                'role' =>
                    UserRole::KDKMP_OPERATOR,

                'is_active' =>
                    true,
            ]);

        $service =
            app(
                DemandForecastService::class
            );

        $forecast =
            $service->createDraft(
                $user,
                $this->forecastPayload(
                    $unit,
                    $commodity
                )
            );

        $forecast =
            $service->publish(
                $user,
                $forecast,
                $forecast->version
            );

        /*
         * DRAFT Forecast milik SPPG yang sama
         * dihitung sebagai authoring context,
         * tetapi tidak boleh dievaluasi sebagai
         * operational supply.
         */
        $draft =
            $service->createDraft(
                $user,
                $this->forecastPayload(
                    $unit,
                    $commodity,
                    6
                )
            );

        /*
         * Forecast tenant lain benar-benar ada,
         * tetapi tidak boleh masuk Dashboard A.
         */
        $otherForecast =
            $service->createDraft(
                $otherUser,
                $this->forecastPayload(
                    $unit,
                    $commodity,
                    8
                )
            );

        $otherForecast =
            $service->publish(
                $otherUser,
                $otherForecast,
                $otherForecast->version
            );

            $notificationService =
    app(
        NotificationService::class
    );

/*
 * RFP event milik SPPG A harus
 * muncul pada dashboard user A.
 */
$notificationService->send(
    recipient:
        $user,

    type:
        NotificationType::RFP,

    priority:
        NotificationPriority
            ::INFORMATION,

    title:
        'Ready for Procurement tercapai',

    message:
        'Forecast A memenuhi seluruh gate.',

    relatedEntity:
        $forecast,

    actionUrl:
        '/sppg/forecasts/'
        .$forecast->id,

    deduplicationKey:
        'dashboard-sppg-a-rfp'
);

/*
 * Notification non-RFP untuk recipient
 * yang sama tidak termasuk Recent RFP.
 */
$notificationService->send(
    recipient:
        $user,

    type:
        NotificationType::SHORTFALL,

    priority:
        NotificationPriority
            ::WARNING,

    title:
        'Shortfall meningkat',

    message:
        'Notification ini bukan RFP.',

    relatedEntity:
        $forecast,

    actionUrl:
        '/sppg/forecasts/'
        .$forecast->id,

    deduplicationKey:
        'dashboard-sppg-a-shortfall'
);

/*
 * RFP event tenant/recipient lain
 * tidak boleh bocor ke user A.
 */
$notificationService->send(
    recipient:
        $otherUser,

    type:
        NotificationType::RFP,

    priority:
        NotificationPriority
            ::WARNING,

    title:
        'RFP tenant lain hilang',

    message:
        'Tidak boleh tampil pada Dashboard A.',

    relatedEntity:
        $otherForecast,

    actionUrl:
        '/sppg/forecasts/'
        .$otherForecast->id,

    deduplicationKey:
        'dashboard-sppg-b-rfp'
);

        $this->assertSame(
            ForecastStatus::DRAFT,
            $draft->status
        );

        $this->assertSame(
            ForecastStatus::PUBLISHED,
            $forecast->status
        );

        $response =
            $this->actingAs(
                $user
            )
                ->get(
                    '/sppg'
                );

        $response
            ->assertOk()
            ->assertInertia(
                fn (
                    Assert $page
                ) =>
                    $page
                        ->component(
                            'Sppg/Dashboard'
                        )
                        ->where(
                            'organization.id',
                            $sppg->id
                        )
                        ->where(
                            'summary.active_forecast_count',
                            1
                        )
                        ->where(
                            'summary.attention_forecast_count',
                            1
                        )
                        ->where(
                            'summary.ready_for_procurement_count',
                            0
                        )
                        ->where(
                            'summary.draft_forecast_count',
                            1
                        )
                        ->has(
                            'forecasts',
                            1
                        )
                        ->where(
                            'forecasts.0.forecast.id',
                            $forecast->id
                        )
                        ->where(
                            'forecasts.0.supply.demand_target',
                            '100.000000'
                        )
                        ->where(
                            'forecasts.0.supply.total_safe_supply',
                            '0.000000'
                        )
                        ->where(
                            'forecasts.0.supply.at_risk_supply',
                            '0.000000'
                        )
                        ->where(
                            'forecasts.0.supply.shortfall',
                            '100.000000'
                        )
                        ->where(
                            'forecasts.0.supply.volume_ready',
                            false
                        )
                        ->where(
                            'forecasts.0.procurement.ready_for_procurement',
                            false
                        )
                        ->has(
                            'forecasts.0.contributors',
                            0
                        )

                        ->has(
    'recentRfpTransitions',
    1
)
->where(
    'recentRfpTransitions.0.priority',
    NotificationPriority
        ::INFORMATION
        ->value
)
->where(
    'recentRfpTransitions.0.title',
    'Ready for Procurement tercapai'
)
->where(
    'recentRfpTransitions.0.message',
    'Forecast A memenuhi seluruh gate.'
)
->where(
    'recentRfpTransitions.0.action_url',
    '/sppg/forecasts/'
    .$forecast->id
)
            );

        /*
         * Privacy regression guard.
         *
         * SPPG payload tidak boleh membawa
         * private KDKMP source vocabulary.
         */
        $response
            ->assertDontSee(
                'producer_name'
            )
            ->assertDontSee(
                'producer_id'
            )
            ->assertDontSee(
                'expected_harvest'
            )
            ->assertDontSee(
                'supply_commitment_id'
            )
            ->assertDontSee(
                'document_record_id'
            );

        $this->assertNotSame(
            $forecast->id,
            $otherForecast->id
        );

        /*
         * KDKMP role tidak dapat menggunakan
         * SPPG dashboard surface.
         */
        $this->actingAs(
            $operator
        )
            ->get(
                '/sppg'
            )
            ->assertForbidden();
    }

    private function organization(
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
                'Lokasi Dashboard Test',
        ]);
    }

    private function forecastPayload(
        Unit $unit,
        Commodity $commodity,
        int $dayOffset = 3,
    ): array {
        $start =
            now()
                ->addDays(
                    $dayOffset
                )
                ->startOfHour();

        $end =
            $start
                ->copy()
                ->addHours(4);

        return [
            'commodity_id' =>
                $commodity->id,

            'unit_id' =>
                $unit->id,

            'target_volume' =>
                '100.000000',

            'required_start_at' =>
                $start
                    ->toDateTimeString(),

            'required_end_at' =>
                $end
                    ->toDateTimeString(),

            'freshness_interval_hours' =>
                24,

            'notes' =>
                'SPPG Dashboard contract test.',
        ];
    }
}