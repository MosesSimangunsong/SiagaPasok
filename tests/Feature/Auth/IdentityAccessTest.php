<?php

namespace Tests\Feature\Auth;

use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdentityAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_protected_admin_route(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect(route('login'));
    }

    public function test_public_registration_route_does_not_exist(): void
    {
        $this->get('/register')
            ->assertNotFound();

        $this->post('/register', [
            'name' => 'Unauthorized Registration',
            'email' => 'registration@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertNotFound();
    }

    public function test_system_admin_is_redirected_to_admin_workspace(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::SYSTEM_ADMIN,
            'organization_id' => null,
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_sppg_user_is_redirected_to_sppg_workspace(): void
    {
        $organization = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-TEST-01'
        );

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::SPPG_USER,
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('sppg.dashboard'));
    }

    public function test_kdkmp_operator_is_redirected_to_operator_workspace(): void
    {
        $organization = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-TEST-01'
        );

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::KDKMP_OPERATOR,
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('kdkmp.operator.dashboard'));
    }

    public function test_kdkmp_manager_is_redirected_to_manager_workspace(): void
    {
        $organization = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-TEST-02'
        );

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::KDKMP_MANAGER,
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('kdkmp.manager.dashboard'));
    }

    public function test_valid_system_admin_can_access_admin_workspace(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SYSTEM_ADMIN,
            'organization_id' => null,
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk();
    }

    public function test_sppg_user_cannot_access_admin_workspace(): void
    {
        $organization = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-TEST-02'
        );

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::SPPG_USER,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_kdkmp_operator_cannot_access_admin_workspace(): void
    {
        $organization = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-TEST-03'
        );

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::KDKMP_OPERATOR,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_kdkmp_manager_cannot_access_admin_workspace(): void
    {
        $organization = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-TEST-04'
        );

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::KDKMP_MANAGER,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()
            ->inactive()
            ->create([
                'email' => 'inactive@example.com',
                'password' => 'password123',
            ]);

        $response = $this->post('/login', [
            'email' => 'inactive@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_from_inactive_organization_cannot_login(): void
    {
        $organization = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-INACTIVE-01',
            false
        );

        User::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'inactive-org@example.com',
            'password' => 'password123',
            'role' => UserRole::SPPG_USER,
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'inactive-org@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_valid_login_updates_last_login_timestamp(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin-login@example.com',
            'password' => 'password123',
            'role' => UserRole::SYSTEM_ADMIN,
            'organization_id' => null,
            'last_login_at' => null,
        ]);

        $this->post('/login', [
            'email' => 'admin-login@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('home'));

        $admin->refresh();

        $this->assertNotNull($admin->last_login_at);
        $this->assertAuthenticatedAs($admin);
    }

    private function createOrganization(
        OrganizationType $type,
        string $code,
        bool $isActive = true
    ): Organization {
        return Organization::create([
            'code' => $code,
            'name' => "Organization {$code}",
            'organization_type' => $type,
            'is_active' => $isActive,
            'general_location' => 'Lokasi Test',
        ]);
    }
} 