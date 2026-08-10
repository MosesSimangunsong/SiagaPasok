<?php

namespace Tests\Unit\Identity;

use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserIdentityContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_without_organization_has_valid_identity_context(): void
    {
        $user = User::factory()->create([
            'organization_id' => null,
            'role' => UserRole::SYSTEM_ADMIN,
            'is_active' => true,
        ]);

        $this->assertTrue($user->hasValidIdentityContext());
    }

    public function test_system_admin_with_organization_is_invalid(): void
    {
        $organization = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-CTX-01'
        );

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::SYSTEM_ADMIN,
            'is_active' => true,
        ]);

        $this->assertFalse($user->hasValidIdentityContext());
    }

    public function test_sppg_user_with_sppg_organization_is_valid(): void
    {
        $organization = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-CTX-02'
        );

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::SPPG_USER,
            'is_active' => true,
        ]);

        $this->assertTrue($user->hasValidIdentityContext());
    }

    public function test_sppg_user_with_kdkmp_organization_is_invalid(): void
    {
        $organization = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-CTX-01'
        );

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::SPPG_USER,
            'is_active' => true,
        ]);

        $this->assertFalse($user->hasValidIdentityContext());
    }

    public function test_kdkmp_operator_with_kdkmp_organization_is_valid(): void
    {
        $organization = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-CTX-02'
        );

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::KDKMP_OPERATOR,
            'is_active' => true,
        ]);

        $this->assertTrue($user->hasValidIdentityContext());
    }

    public function test_kdkmp_manager_with_sppg_organization_is_invalid(): void
    {
        $organization = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-CTX-03'
        );

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::KDKMP_MANAGER,
            'is_active' => true,
        ]);

        $this->assertFalse($user->hasValidIdentityContext());
    }

    public function test_inactive_user_is_invalid(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
        ]);

        $this->assertFalse($user->hasValidIdentityContext());
    }

    public function test_business_user_from_inactive_organization_is_invalid(): void
    {
        $organization = Organization::create([
            'code' => 'SPPG-INACTIVE-CTX',
            'name' => 'Inactive SPPG',
            'organization_type' => OrganizationType::SPPG,
            'is_active' => false,
            'general_location' => 'Lokasi Test',
        ]);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::SPPG_USER,
            'is_active' => true,
        ]);

        $this->assertFalse($user->hasValidIdentityContext());
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