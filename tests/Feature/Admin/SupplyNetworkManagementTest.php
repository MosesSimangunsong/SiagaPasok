<?php

namespace Tests\Feature\Admin;

use App\Enums\NetworkRole;
use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\SupplyNetworkLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplyNetworkManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_can_access_supply_network_page(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get('/admin/supply-network')
            ->assertOk();
    }

    public function test_non_admin_cannot_access_supply_network_page(): void
    {
        $organization = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-NET-ACCESS'
        );

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::SPPG_USER,
        ]);

        $this->actingAs($user)
            ->get('/admin/supply-network')
            ->assertForbidden();
    }

    public function test_system_admin_can_create_primary_link_through_http(): void
    {
        $admin = User::factory()->create();

        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-HTTP-01'
        );

        $kdkmp = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-HTTP-01'
        );

        $response = $this
            ->actingAs($admin)
            ->post('/admin/supply-network', [
                'sppg_organization_id' => $sppg->id,
                'kdkmp_organization_id' => $kdkmp->id,
                'network_role' => NetworkRole::PRIMARY->value,
                'is_active' => true,
            ]);

        $response->assertRedirect(
            route('admin.supply-network.index')
        );

        $this->assertDatabaseHas('supply_network_links', [
            'sppg_organization_id' => $sppg->id,
            'kdkmp_organization_id' => $kdkmp->id,
            'network_role' => NetworkRole::PRIMARY->value,
            'is_active' => true,
            'configured_by' => $admin->id,
        ]);
    }

    public function test_duplicate_network_pair_is_rejected_through_http(): void
    {
        $admin = User::factory()->create();

        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-HTTP-02'
        );

        $kdkmp = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-HTTP-02'
        );

        $this->actingAs($admin)
            ->post('/admin/supply-network', [
                'sppg_organization_id' => $sppg->id,
                'kdkmp_organization_id' => $kdkmp->id,
                'network_role' => NetworkRole::PRIMARY->value,
                'is_active' => true,
            ]);

        $response = $this
            ->actingAs($admin)
            ->from('/admin/supply-network')
            ->post('/admin/supply-network', [
                'sppg_organization_id' => $sppg->id,
                'kdkmp_organization_id' => $kdkmp->id,
                'network_role' => NetworkRole::NETWORK->value,
                'is_active' => false,
            ]);

        $response
            ->assertRedirect('/admin/supply-network')
            ->assertSessionHasErrors(
                'kdkmp_organization_id'
            );

        $this->assertSame(
            1,
            SupplyNetworkLink::query()->count()
        );
    }

    public function test_sppg_to_sppg_relation_is_rejected_through_http(): void
    {
        $admin = User::factory()->create();

        $sppgA = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-INVALID-A'
        );

        $sppgB = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-INVALID-B'
        );

        $response = $this
            ->actingAs($admin)
            ->from('/admin/supply-network')
            ->post('/admin/supply-network', [
                'sppg_organization_id' => $sppgA->id,
                'kdkmp_organization_id' => $sppgB->id,
                'network_role' => NetworkRole::PRIMARY->value,
                'is_active' => true,
            ]);

        $response
            ->assertRedirect('/admin/supply-network')
            ->assertSessionHasErrors(
                'kdkmp_organization_id'
            );

        $this->assertDatabaseCount(
            'supply_network_links',
            0
        );
    }

    public function test_kdkmp_to_kdkmp_relation_is_rejected_through_http(): void
    {
        $admin = User::factory()->create();

        $kdkmpA = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-INVALID-A'
        );

        $kdkmpB = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-INVALID-B'
        );

        $response = $this
            ->actingAs($admin)
            ->from('/admin/supply-network')
            ->post('/admin/supply-network', [
                'sppg_organization_id' => $kdkmpA->id,
                'kdkmp_organization_id' => $kdkmpB->id,
                'network_role' => NetworkRole::PRIMARY->value,
                'is_active' => true,
            ]);

        $response
            ->assertRedirect('/admin/supply-network')
            ->assertSessionHasErrors(
                'sppg_organization_id'
            );

        $this->assertDatabaseCount(
            'supply_network_links',
            0
        );
    }

    public function test_second_active_primary_is_rejected_through_http(): void
    {
        $admin = User::factory()->create();

        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-HTTP-03'
        );

        $firstKdkmp = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-HTTP-03-A'
        );

        $secondKdkmp = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-HTTP-03-B'
        );

        $this->actingAs($admin)
            ->post('/admin/supply-network', [
                'sppg_organization_id' => $sppg->id,
                'kdkmp_organization_id' => $firstKdkmp->id,
                'network_role' => NetworkRole::PRIMARY->value,
                'is_active' => true,
            ]);

        $response = $this
            ->actingAs($admin)
            ->from('/admin/supply-network')
            ->post('/admin/supply-network', [
                'sppg_organization_id' => $sppg->id,
                'kdkmp_organization_id' => $secondKdkmp->id,
                'network_role' => NetworkRole::PRIMARY->value,
                'is_active' => true,
            ]);

        $response
            ->assertRedirect('/admin/supply-network')
            ->assertSessionHasErrors('network_role');

        $this->assertSame(
            1,
            $this->activePrimaryCount($sppg)
        );
    }

    public function test_admin_can_promote_network_link_to_primary_through_http(): void
    {
        $admin = User::factory()->create();

        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-HTTP-04'
        );

        $kdkmpA = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-HTTP-04-A'
        );

        $kdkmpB = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-HTTP-04-B'
        );

        $primary = SupplyNetworkLink::create([
            'sppg_organization_id' => $sppg->id,
            'kdkmp_organization_id' => $kdkmpA->id,
            'network_role' => NetworkRole::PRIMARY,
            'is_active' => true,
            'configured_by' => $admin->id,
        ]);

        $network = SupplyNetworkLink::create([
            'sppg_organization_id' => $sppg->id,
            'kdkmp_organization_id' => $kdkmpB->id,
            'network_role' => NetworkRole::NETWORK,
            'is_active' => true,
            'configured_by' => $admin->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(
                "/admin/supply-network/{$network->id}/assign-primary"
            );

        $response->assertRedirect(
            route('admin.supply-network.index')
        );

        $primary->refresh();
        $network->refresh();

        $this->assertSame(
            NetworkRole::NETWORK,
            $primary->network_role
        );

        $this->assertSame(
            NetworkRole::PRIMARY,
            $network->network_role
        );

        $this->assertTrue($primary->is_active);
        $this->assertTrue($network->is_active);

        $this->assertSame(
            1,
            $this->activePrimaryCount($sppg)
        );
    }

    public function test_active_primary_cannot_be_deactivated_through_http(): void
    {
        $admin = User::factory()->create();

        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-HTTP-05'
        );

        $kdkmp = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-HTTP-05'
        );

        $link = SupplyNetworkLink::create([
            'sppg_organization_id' => $sppg->id,
            'kdkmp_organization_id' => $kdkmp->id,
            'network_role' => NetworkRole::PRIMARY,
            'is_active' => true,
            'configured_by' => $admin->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->from('/admin/supply-network')
            ->patch(
                "/admin/supply-network/{$link->id}/active-state",
                [
                    'is_active' => false,
                ]
            );

        $response
            ->assertRedirect('/admin/supply-network')
            ->assertSessionHasErrors('is_active');

        $this->assertTrue(
            $link->fresh()->is_active
        );
    }

    public function test_non_admin_cannot_mutate_network_configuration(): void
    {
        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-HTTP-06'
        );

        $kdkmp = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-HTTP-06'
        );

        $user = User::factory()->create([
            'organization_id' => $sppg->id,
            'role' => UserRole::SPPG_USER,
        ]);

        $this->actingAs($user)
            ->post('/admin/supply-network', [
                'sppg_organization_id' => $sppg->id,
                'kdkmp_organization_id' => $kdkmp->id,
                'network_role' => NetworkRole::PRIMARY->value,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount(
            'supply_network_links',
            0
        );
    }

    private function createOrganization(
        OrganizationType $type,
        string $code,
    ): Organization {
        return Organization::create([
            'code' => $code,
            'name' => "Organization {$code}",
            'organization_type' => $type,
            'is_active' => true,
            'general_location' => 'Lokasi Test',
        ]);
    }

    private function activePrimaryCount(
        Organization $sppg,
    ): int {
        return SupplyNetworkLink::query()
            ->where(
                'sppg_organization_id',
                $sppg->id
            )
            ->where(
                'network_role',
                NetworkRole::PRIMARY->value
            )
            ->where('is_active', true)
            ->count();
    }
}