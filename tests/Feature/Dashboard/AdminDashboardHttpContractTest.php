<?php

namespace Tests\Feature\Dashboard;

use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminDashboardHttpContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_dashboard_exposes_administrative_status_counts_only(): void
    {
        $activeOrganization =
            Organization::create([
                'code' =>
                    'ADMIN-DASH-ACTIVE',

                'name' =>
                    'Admin Dashboard Active',

                'organization_type' =>
                    OrganizationType::SPPG,

                'general_location' =>
                    'Lokasi Aktif',

                'is_active' =>
                    true,
            ]);

        $inactiveOrganization =
            Organization::create([
                'code' =>
                    'ADMIN-DASH-INACTIVE',

                'name' =>
                    'Admin Dashboard Inactive',

                'organization_type' =>
                    OrganizationType::KDKMP,

                'general_location' =>
                    'Lokasi Nonaktif',

                'is_active' =>
                    false,
            ]);

        $admin =
            User::factory()->create([
                'organization_id' =>
                    null,

                'role' =>
                    UserRole::SYSTEM_ADMIN,

                'is_active' =>
                    true,
            ]);

        User::factory()->create([
            'organization_id' =>
                $activeOrganization->id,

            'role' =>
                UserRole::SPPG_USER,

            'is_active' =>
                true,
        ]);

        User::factory()->create([
            'organization_id' =>
                $inactiveOrganization->id,

            'role' =>
                UserRole::KDKMP_OPERATOR,

            'is_active' =>
                false,
        ]);

        $this->actingAs(
            $admin
        )
            ->get(
                '/admin'
            )
            ->assertOk()
            ->assertInertia(
                fn (
                    Assert $page
                ) =>
                    $page
                        ->component(
                            'Admin/Dashboard'
                        )
                        ->where(
                            'stats.organizations',
                            2
                        )
                        ->where(
                            'stats.active_organizations',
                            1
                        )
                        ->where(
                            'stats.inactive_organizations',
                            1
                        )
                        ->where(
                            'stats.users',
                            3
                        )
                        ->where(
                            'stats.active_users',
                            2
                        )
                        ->where(
                            'stats.inactive_users',
                            1
                        )
            );
    }

    public function test_business_roles_cannot_open_system_admin_dashboard(): void
    {
        $organization =
            Organization::create([
                'code' =>
                    'ADMIN-DASH-BOUNDARY',

                'name' =>
                    'Admin Dashboard Boundary',

                'organization_type' =>
                    OrganizationType::SPPG,

                'general_location' =>
                    'Lokasi Boundary',

                'is_active' =>
                    true,
            ]);

        $sppgUser =
            User::factory()->create([
                'organization_id' =>
                    $organization->id,

                'role' =>
                    UserRole::SPPG_USER,

                'is_active' =>
                    true,
            ]);

        $this->actingAs(
            $sppgUser
        )
            ->get(
                '/admin'
            )
            ->assertForbidden();
    }
}