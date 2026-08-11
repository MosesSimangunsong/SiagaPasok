<?php

namespace Tests\Feature\Demo;

use App\Enums\NetworkRole;
use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Support\Demo\DemoIdentifiers;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoIdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class DemoEnvironmentBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_route_is_not_available_when_demo_mode_is_disabled(): void
    {
        config()->set(
            'siagapasok.demo.enabled',
            false
        );

        Route::get(
            '/__tests/demo-disabled',
            fn () => response()->noContent()
        )->middleware('demo');

        $this->get('/__tests/demo-disabled')
            ->assertNotFound();
    }

    public function test_demo_route_is_available_when_demo_mode_is_enabled(): void
    {
        config()->set(
            'siagapasok.demo.enabled',
            true
        );

        Route::get(
            '/__tests/demo-enabled',
            fn () => response()->noContent()
        )->middleware('demo');

        $this->get('/__tests/demo-enabled')
            ->assertNoContent();
    }

    public function test_explicit_demo_identity_seed_is_rejected_when_demo_mode_is_disabled(): void
    {
        config()->set(
            'siagapasok.demo.enabled',
            false
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->seed(
            DemoIdentitySeeder::class
        );
    }

    public function test_normal_database_seed_does_not_create_demo_identity_when_demo_mode_is_disabled(): void
    {
        config()->set(
            'siagapasok.demo.enabled',
            false
        );

        $this->seed(
            DatabaseSeeder::class
        );

        $this->assertDatabaseMissing(
            'organizations',
            [
                'code' => DemoIdentifiers::SPPG_CODE,
            ]
        );

        $this->assertDatabaseMissing(
            'users',
            [
                'email' => DemoIdentifiers::SPPG_EMAIL,
            ]
        );
    }

    public function test_demo_identity_seed_is_deterministic_idempotent_and_login_ready(): void
    {
        config()->set(
            'siagapasok.demo.enabled',
            true
        );

        $this->seed(
            DemoIdentitySeeder::class
        );

        $this->seed(
            DemoIdentitySeeder::class
        );

        $this->assertDatabaseCount(
            'organizations',
            3
        );

        $this->assertDatabaseCount(
            'users',
            6
        );

        $this->assertDatabaseCount(
            'supply_network_links',
            2
        );

        $this->assertDatabaseHas(
            'organizations',
            [
                'code' => DemoIdentifiers::SPPG_CODE,
                'name' => DemoIdentifiers::SPPG_NAME,
                'organization_type' =>
                    OrganizationType::SPPG->value,
                'is_active' => true,
            ]
        );

        $this->assertDatabaseHas(
            'organizations',
            [
                'code' =>
                    DemoIdentifiers::PRIMARY_KDKMP_CODE,
                'name' =>
                    DemoIdentifiers::PRIMARY_KDKMP_NAME,
                'organization_type' =>
                    OrganizationType::KDKMP->value,
                'is_active' => true,
            ]
        );

        $this->assertDatabaseHas(
            'organizations',
            [
                'code' =>
                    DemoIdentifiers::NETWORK_KDKMP_CODE,
                'name' =>
                    DemoIdentifiers::NETWORK_KDKMP_NAME,
                'organization_type' =>
                    OrganizationType::KDKMP->value,
                'is_active' => true,
            ]
        );

        $this->assertDatabaseHas(
            'users',
            [
                'email' => DemoIdentifiers::ADMIN_EMAIL,
                'organization_id' => null,
                'role' =>
                    UserRole::SYSTEM_ADMIN->value,
                'is_active' => true,
            ]
        );

        $this->assertDatabaseHas(
            'users',
            [
                'email' => DemoIdentifiers::SPPG_EMAIL,
                'role' =>
                    UserRole::SPPG_USER->value,
                'is_active' => true,
            ]
        );

        $this->assertDatabaseHas(
            'users',
            [
                'email' =>
                    DemoIdentifiers::PRIMARY_OPERATOR_EMAIL,
                'role' =>
                    UserRole::KDKMP_OPERATOR->value,
                'is_active' => true,
            ]
        );

        $this->assertDatabaseHas(
            'users',
            [
                'email' =>
                    DemoIdentifiers::PRIMARY_MANAGER_EMAIL,
                'role' =>
                    UserRole::KDKMP_MANAGER->value,
                'is_active' => true,
            ]
        );

        $this->assertDatabaseHas(
            'users',
            [
                'email' =>
                    DemoIdentifiers::NETWORK_OPERATOR_EMAIL,
                'role' =>
                    UserRole::KDKMP_OPERATOR->value,
                'is_active' => true,
            ]
        );

        $this->assertDatabaseHas(
            'users',
            [
                'email' =>
                    DemoIdentifiers::NETWORK_MANAGER_EMAIL,
                'role' =>
                    UserRole::KDKMP_MANAGER->value,
                'is_active' => true,
            ]
        );

        $sppgId = (int) \App\Models\Organization::query()
            ->where(
                'code',
                DemoIdentifiers::SPPG_CODE
            )
            ->value('id');

        $primaryId = (int) \App\Models\Organization::query()
            ->where(
                'code',
                DemoIdentifiers::PRIMARY_KDKMP_CODE
            )
            ->value('id');

        $networkId = (int) \App\Models\Organization::query()
            ->where(
                'code',
                DemoIdentifiers::NETWORK_KDKMP_CODE
            )
            ->value('id');

        $this->assertDatabaseHas(
            'supply_network_links',
            [
                'sppg_organization_id' => $sppgId,
                'kdkmp_organization_id' => $primaryId,
                'network_role' =>
                    NetworkRole::PRIMARY->value,
                'is_active' => true,
            ]
        );

        $this->assertDatabaseHas(
            'supply_network_links',
            [
                'sppg_organization_id' => $sppgId,
                'kdkmp_organization_id' => $networkId,
                'network_role' =>
                    NetworkRole::NETWORK->value,
                'is_active' => true,
            ]
        );

        $this->post(
            '/login',
            [
                'email' => DemoIdentifiers::SPPG_EMAIL,
                'password' =>
                    DemoIdentifiers::DEMO_PASSWORD,
            ]
        )->assertRedirect(
            route('home')
        );

        $this->assertAuthenticated();
    }
}