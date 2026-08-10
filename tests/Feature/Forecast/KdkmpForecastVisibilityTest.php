<?php

namespace Tests\Feature\Forecast;

use App\Enums\NetworkRole;
use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Models\Commodity;
use App\Models\Organization;
use App\Models\SupplyNetworkLink;
use App\Models\Unit;
use App\Models\User;
use App\Services\Forecast\DemandForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class KdkmpForecastVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_kdkmp_sees_only_relevant_published_forecast(): void
    {
        [$unit, $commodity] =
            $this->createReferenceData();

        $admin = User::factory()->create();

        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-VIS-01'
        );

        $primaryKdkmp =
            $this->createOrganization(
                OrganizationType::KDKMP,
                'KDKMP-VIS-PRIMARY'
            );

        $this->createLink(
            $admin,
            $sppg,
            $primaryKdkmp,
            NetworkRole::PRIMARY
        );

        $sppgUser = $this->createSppgUser(
            $sppg
        );

        $service = app(
            DemandForecastService::class
        );

        $draft = $service->createDraft(
            $sppgUser,
            $this->validPayload(
                $unit,
                $commodity
            )
        );

        $publishedDraft =
            $service->createDraft(
                $sppgUser,
                [
                    ...$this->validPayload(
                        $unit,
                        $commodity
                    ),
                    'target_volume' => 150,
                ]
            );

        $published =
            $service->publish(
                $sppgUser,
                $publishedDraft,
                $publishedDraft->version
            );

        $operator =
            $this->createKdkmpUser(
                $primaryKdkmp,
                UserRole::KDKMP_OPERATOR
            );

        $this->actingAs($operator)
            ->get('/kdkmp/forecasts')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
    'Kdkmp/Forecasts/Index'
)
                        ->has('forecasts', 1)
                        ->where(
                            'forecasts.0.id',
                            $published->id
                        )
            );

        $this->actingAs($operator)
            ->get(
                "/kdkmp/forecasts/{$published->id}"
            )
            ->assertOk();

        $this->actingAs($operator)
            ->get(
                "/kdkmp/forecasts/{$draft->id}"
            )
            ->assertForbidden();
    }

    public function test_network_only_kdkmp_does_not_receive_direct_forecast_visibility(): void
    {
        [$unit, $commodity] =
            $this->createReferenceData(
                'NETWORK'
            );

        $admin = User::factory()->create();

        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-VIS-02'
        );

        $primary =
            $this->createOrganization(
                OrganizationType::KDKMP,
                'KDKMP-VIS-02-P'
            );

        $network =
            $this->createOrganization(
                OrganizationType::KDKMP,
                'KDKMP-VIS-02-N'
            );

        $this->createLink(
            $admin,
            $sppg,
            $primary,
            NetworkRole::PRIMARY
        );

        $this->createLink(
            $admin,
            $sppg,
            $network,
            NetworkRole::NETWORK
        );

        $sppgUser =
            $this->createSppgUser($sppg);

        $service = app(
            DemandForecastService::class
        );

        $draft = $service->createDraft(
            $sppgUser,
            $this->validPayload(
                $unit,
                $commodity
            )
        );

        $published = $service->publish(
            $sppgUser,
            $draft,
            $draft->version
        );

        $networkOperator =
            $this->createKdkmpUser(
                $network,
                UserRole::KDKMP_OPERATOR
            );

        $this->actingAs($networkOperator)
            ->get('/kdkmp/forecasts')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page->has(
                        'forecasts',
                        0
                    )
            );

        $this->actingAs($networkOperator)
            ->get(
                "/kdkmp/forecasts/{$published->id}"
            )
            ->assertForbidden();
    }

    public function test_unrelated_kdkmp_cannot_read_forecast(): void
    {
        [$unit, $commodity] =
            $this->createReferenceData(
                'UNRELATED'
            );

        $admin = User::factory()->create();

        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-VIS-03'
        );

        $primary =
            $this->createOrganization(
                OrganizationType::KDKMP,
                'KDKMP-VIS-03-P'
            );

        $unrelated =
            $this->createOrganization(
                OrganizationType::KDKMP,
                'KDKMP-VIS-03-X'
            );

        $this->createLink(
            $admin,
            $sppg,
            $primary,
            NetworkRole::PRIMARY
        );

        $sppgUser =
            $this->createSppgUser($sppg);

        $service = app(
            DemandForecastService::class
        );

        $draft = $service->createDraft(
            $sppgUser,
            $this->validPayload(
                $unit,
                $commodity
            )
        );

        $published = $service->publish(
            $sppgUser,
            $draft,
            $draft->version
        );

        $operator =
            $this->createKdkmpUser(
                $unrelated,
                UserRole::KDKMP_OPERATOR
            );

        $this->actingAs($operator)
            ->get(
                "/kdkmp/forecasts/{$published->id}"
            )
            ->assertForbidden();
    }

    public function test_kdkmp_cannot_mutate_forecast(): void
    {
        [$unit, $commodity] =
            $this->createReferenceData(
                'MUTATE'
            );

        $kdkmp = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-MUTATE'
        );

        $operator =
            $this->createKdkmpUser(
                $kdkmp,
                UserRole::KDKMP_OPERATOR
            );

        $this->actingAs($operator)
            ->post(
                '/sppg/forecasts',
                $this->validPayload(
                    $unit,
                    $commodity
                )
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'demand_forecasts',
            0
        );
    }

    private function createReferenceData(
        string $suffix = ''
    ): array {
        $normalized = $suffix !== ''
            ? "-{$suffix}"
            : '';

        $unit = Unit::create([
            'code' => "kg{$normalized}",
            'name' => "Kilogram {$suffix}",
            'symbol' => 'kg',
            'decimal_precision' => 2,
            'is_active' => true,
        ]);

        $commodity = Commodity::create([
            'code' => "BAYAM{$normalized}",
            'name' => "Bayam {$suffix}",
            'default_unit_id' => $unit->id,
            'harvest_behavior' => null,
            'notes' => null,
            'is_active' => true,
        ]);

        return [
            $unit,
            $commodity,
        ];
    }

    private function validPayload(
        Unit $unit,
        Commodity $commodity
    ): array {
        return [
            'commodity_id' => $commodity->id,
            'unit_id' => $unit->id,
            'target_volume' => 100,
            'required_start_at' =>
                '2026-08-20 08:00:00',

            'required_end_at' =>
                '2026-08-20 12:00:00',

            'freshness_interval_hours' => 24,
            'notes' => 'Forecast visibility test',
        ];
    }

    private function createOrganization(
        OrganizationType $type,
        string $code
    ): Organization {
        return Organization::create([
            'code' => $code,
            'name' => $code,
            'organization_type' => $type,
            'is_active' => true,
            'general_location' => 'Lokasi Test',
        ]);
    }

    private function createSppgUser(
        Organization $organization
    ): User {
        return User::factory()->create([
            'organization_id' =>
                $organization->id,

            'role' => UserRole::SPPG_USER,
            'is_active' => true,
        ]);
    }

    private function createKdkmpUser(
        Organization $organization,
        UserRole $role
    ): User {
        return User::factory()->create([
            'organization_id' =>
                $organization->id,

            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function createLink(
        User $admin,
        Organization $sppg,
        Organization $kdkmp,
        NetworkRole $role
    ): SupplyNetworkLink {
        return SupplyNetworkLink::create([
            'sppg_organization_id' =>
                $sppg->id,

            'kdkmp_organization_id' =>
                $kdkmp->id,

            'network_role' => $role,
            'is_active' => true,
            'configured_by' => $admin->id,
        ]);
    }
}