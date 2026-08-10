<?php

namespace Tests\Feature\Admin;

use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Models\Commodity;
use App\Models\Organization;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_can_access_master_data_page(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get('/admin/master-data')
            ->assertOk();
    }

    public function test_non_admin_cannot_access_master_data_page(): void
    {
        $organization = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-MASTER-01'
        );

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::SPPG_USER,
        ]);

        $this->actingAs($user)
            ->get('/admin/master-data')
            ->assertForbidden();
    }

    public function test_system_admin_can_create_unit(): void
    {
        $admin = User::factory()->create();

        $response = $this
            ->actingAs($admin)
            ->post('/admin/master-data/units', [
                'code' => 'ton',
                'name' => 'Ton',
                'symbol' => 't',
                'decimal_precision' => 2,
                'is_active' => true,
            ]);

        $response->assertRedirect(
            route('admin.master-data.index')
        );

        $this->assertDatabaseHas('units', [
            'code' => 'ton',
            'name' => 'Ton',
            'symbol' => 't',
            'decimal_precision' => 2,
            'is_active' => true,
        ]);
    }

    public function test_duplicate_unit_code_is_rejected(): void
    {
        $admin = User::factory()->create();

        Unit::create([
            'code' => 'kg',
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'decimal_precision' => 2,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->from('/admin/master-data/units/create')
            ->post('/admin/master-data/units', [
                'code' => 'kg',
                'name' => 'Duplicate Kilogram',
                'symbol' => 'kg',
                'decimal_precision' => 2,
                'is_active' => true,
            ]);

        $response
            ->assertRedirect('/admin/master-data/units/create')
            ->assertSessionHasErrors('code');

        $this->assertSame(
            1,
            Unit::query()->where('code', 'kg')->count()
        );
    }

    public function test_system_admin_can_update_unit(): void
    {
        $admin = User::factory()->create();

        $unit = Unit::create([
            'code' => 'kg',
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'decimal_precision' => 2,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->put("/admin/master-data/units/{$unit->id}", [
                'code' => 'kg',
                'name' => 'Kilogram',
                'symbol' => 'kg',
                'decimal_precision' => 3,
                'is_active' => false,
            ]);

        $response->assertRedirect(
            route('admin.master-data.index')
        );

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'decimal_precision' => 3,
            'is_active' => false,
        ]);
    }

    public function test_system_admin_can_create_commodity(): void
    {
        $admin = User::factory()->create();

        $unit = Unit::create([
            'code' => 'kg',
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'decimal_precision' => 2,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post('/admin/master-data/commodities', [
                'code' => 'TOMAT',
                'name' => 'Tomat',
                'default_unit_id' => $unit->id,
                'harvest_behavior' => 'SINGLE',
                'notes' => 'Commodity test',
                'is_active' => true,
            ]);

        $response->assertRedirect(
            route('admin.master-data.index')
        );

        $this->assertDatabaseHas('commodities', [
            'code' => 'TOMAT',
            'name' => 'Tomat',
            'default_unit_id' => $unit->id,
            'harvest_behavior' => 'SINGLE',
            'is_active' => true,
        ]);
    }

    public function test_duplicate_commodity_code_is_rejected(): void
    {
        $admin = User::factory()->create();

        $unit = $this->createKilogramUnit();

        Commodity::create([
            'code' => 'BAYAM',
            'name' => 'Bayam',
            'default_unit_id' => $unit->id,
            'harvest_behavior' => null,
            'notes' => null,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->from('/admin/master-data/commodities/create')
            ->post('/admin/master-data/commodities', [
                'code' => 'BAYAM',
                'name' => 'Bayam Duplikat',
                'default_unit_id' => $unit->id,
                'harvest_behavior' => null,
                'notes' => null,
                'is_active' => true,
            ]);

        $response
            ->assertRedirect(
                '/admin/master-data/commodities/create'
            )
            ->assertSessionHasErrors('code');

        $this->assertSame(
            1,
            Commodity::query()
                ->where('code', 'BAYAM')
                ->count()
        );
    }

    public function test_system_admin_can_update_commodity(): void
    {
        $admin = User::factory()->create();

        $unit = $this->createKilogramUnit();

        $commodity = Commodity::create([
            'code' => 'KANGKUNG',
            'name' => 'Kangkung',
            'default_unit_id' => $unit->id,
            'harvest_behavior' => null,
            'notes' => null,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->put(
                "/admin/master-data/commodities/{$commodity->id}",
                [
                    'code' => 'KANGKUNG',
                    'name' => 'Kangkung',
                    'default_unit_id' => $unit->id,
                    'harvest_behavior' => 'RECURRING',
                    'notes' => 'Updated metadata',
                    'is_active' => false,
                ]
            );

        $response->assertRedirect(
            route('admin.master-data.index')
        );

        $this->assertDatabaseHas('commodities', [
            'id' => $commodity->id,
            'harvest_behavior' => 'RECURRING',
            'notes' => 'Updated metadata',
            'is_active' => false,
        ]);
    }

    public function test_non_admin_cannot_create_unit_or_commodity(): void
    {
        $organization = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-MASTER-01'
        );

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::KDKMP_MANAGER,
        ]);

        $unit = $this->createKilogramUnit();

        $this->actingAs($user)
            ->post('/admin/master-data/units', [
                'code' => 'g',
                'name' => 'Gram',
                'symbol' => 'g',
                'decimal_precision' => 2,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->post('/admin/master-data/commodities', [
                'code' => 'CABAI',
                'name' => 'Cabai',
                'default_unit_id' => $unit->id,
                'harvest_behavior' => null,
                'notes' => null,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('units', [
            'code' => 'g',
        ]);

        $this->assertDatabaseMissing('commodities', [
            'code' => 'CABAI',
        ]);
    }

    private function createKilogramUnit(): Unit
    {
        return Unit::create([
            'code' => 'kg',
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'decimal_precision' => 2,
            'is_active' => true,
        ]);
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
}