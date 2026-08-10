<?php

namespace Tests\Feature\Admin;

use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdentityManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_can_create_organization(): void
    {
        $admin = User::factory()->create();

        $response = $this
            ->actingAs($admin)
            ->post('/admin/organizations', [
                'code' => 'SPPG-BDG-01',
                'name' => 'SPPG Bandung 01',
                'organization_type' => OrganizationType::SPPG->value,
                'general_location' => 'Bandung',
                'is_active' => false,
            ]);

        $response->assertRedirect(
            route('admin.organizations.index')
        );

        $this->assertDatabaseHas('organizations', [
            'code' => 'SPPG-BDG-01',
            'name' => 'SPPG Bandung 01',
            'organization_type' => OrganizationType::SPPG->value,
            'is_active' => true,
        ]);
    }

    public function test_system_admin_can_create_valid_sppg_user(): void
    {
        $admin = User::factory()->create();

        $organization = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-TEST-01'
        );

        $response = $this
            ->actingAs($admin)
            ->post('/admin/users', [
                'organization_id' => $organization->id,
                'name' => 'SPPG User Test',
                'email' => 'sppg-user@example.com',
                'role' => UserRole::SPPG_USER->value,
                'is_active' => true,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

        $response->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'organization_id' => $organization->id,
            'email' => 'sppg-user@example.com',
            'role' => UserRole::SPPG_USER->value,
            'is_active' => true,
        ]);
    }

    public function test_system_admin_can_create_valid_kdkmp_operator(): void
    {
        $admin = User::factory()->create();

        $organization = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-TEST-01'
        );

        $response = $this
            ->actingAs($admin)
            ->post('/admin/users', [
                'organization_id' => $organization->id,
                'name' => 'KDKMP Operator Test',
                'email' => 'operator@example.com',
                'role' => UserRole::KDKMP_OPERATOR->value,
                'is_active' => true,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

        $response->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'organization_id' => $organization->id,
            'email' => 'operator@example.com',
            'role' => UserRole::KDKMP_OPERATOR->value,
        ]);
    }

    public function test_sppg_user_cannot_be_assigned_to_kdkmp(): void
    {
        $admin = User::factory()->create();

        $organization = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-INVALID-01'
        );

        $response = $this
            ->actingAs($admin)
            ->from('/admin/users/create')
            ->post('/admin/users', [
                'organization_id' => $organization->id,
                'name' => 'Invalid SPPG User',
                'email' => 'invalid-sppg@example.com',
                'role' => UserRole::SPPG_USER->value,
                'is_active' => true,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

        $response
            ->assertRedirect('/admin/users/create')
            ->assertSessionHasErrors('organization_id');

        $this->assertDatabaseMissing('users', [
            'email' => 'invalid-sppg@example.com',
        ]);
    }

    public function test_kdkmp_role_cannot_be_assigned_to_sppg(): void
    {
        $admin = User::factory()->create();

        $organization = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-INVALID-01'
        );

        $response = $this
            ->actingAs($admin)
            ->from('/admin/users/create')
            ->post('/admin/users', [
                'organization_id' => $organization->id,
                'name' => 'Invalid KDKMP User',
                'email' => 'invalid-kdkmp@example.com',
                'role' => UserRole::KDKMP_MANAGER->value,
                'is_active' => true,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

        $response
            ->assertRedirect('/admin/users/create')
            ->assertSessionHasErrors('organization_id');

        $this->assertDatabaseMissing('users', [
            'email' => 'invalid-kdkmp@example.com',
        ]);
    }

    public function test_business_user_without_organization_is_rejected(): void
    {
        $admin = User::factory()->create();

        $response = $this
            ->actingAs($admin)
            ->from('/admin/users/create')
            ->post('/admin/users', [
                'organization_id' => null,
                'name' => 'No Organization User',
                'email' => 'no-org@example.com',
                'role' => UserRole::SPPG_USER->value,
                'is_active' => true,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

        $response
            ->assertRedirect('/admin/users/create')
            ->assertSessionHasErrors('organization_id');

        $this->assertDatabaseMissing('users', [
            'email' => 'no-org@example.com',
        ]);
    }

    public function test_system_admin_without_organization_is_valid(): void
    {
        $admin = User::factory()->create();

        $response = $this
            ->actingAs($admin)
            ->post('/admin/users', [
                'organization_id' => null,
                'name' => 'Second System Admin',
                'email' => 'second-admin@example.com',
                'role' => UserRole::SYSTEM_ADMIN->value,
                'is_active' => true,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

        $response->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'organization_id' => null,
            'email' => 'second-admin@example.com',
            'role' => UserRole::SYSTEM_ADMIN->value,
            'is_active' => true,
        ]);
    }

    public function test_non_admin_cannot_create_organization(): void
    {
        $organization = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-TEST-ACCESS'
        );

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::SPPG_USER,
        ]);

        $this
            ->actingAs($user)
            ->post('/admin/organizations', [
                'code' => 'UNAUTHORIZED',
                'name' => 'Unauthorized Organization',
                'organization_type' => OrganizationType::KDKMP->value,
                'general_location' => null,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('organizations', [
            'code' => 'UNAUTHORIZED',
        ]);
    }

    private function createOrganization(
        OrganizationType $type,
        string $code
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