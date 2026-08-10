<?php

namespace Tests\Feature\Supply;

use App\Enums\NetworkRole;
use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\SupplyNetworkLink;
use App\Models\User;
use App\Services\Supply\SupplyNetworkService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SupplyNetworkServiceTest extends TestCase
{
    use RefreshDatabase;

    private SupplyNetworkService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SupplyNetworkService::class);
    }

    public function test_system_admin_can_create_primary_network_link(): void
    {
        $admin = User::factory()->create();

        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-01'
        );

        $kdkmp = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-01'
        );

        $link = $this->service->createLink(
            $admin,
            $sppg,
            $kdkmp,
            NetworkRole::PRIMARY,
            true,
        );

        $this->assertTrue($link->is_active);
        $this->assertSame(
            NetworkRole::PRIMARY,
            $link->network_role
        );

        $this->assertSame(
            $admin->id,
            $link->configured_by
        );

        $this->assertDatabaseHas('supply_network_links', [
            'sppg_organization_id' => $sppg->id,
            'kdkmp_organization_id' => $kdkmp->id,
            'network_role' => NetworkRole::PRIMARY->value,
            'is_active' => true,
            'configured_by' => $admin->id,
        ]);
    }

    public function test_non_system_admin_cannot_configure_network(): void
    {
        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-02'
        );

        $kdkmp = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-02'
        );

        $user = User::factory()->create([
            'organization_id' => $sppg->id,
            'role' => UserRole::SPPG_USER,
        ]);

        $this->expectException(
            AuthorizationException::class
        );

        $this->service->createLink(
            $user,
            $sppg,
            $kdkmp,
            NetworkRole::PRIMARY,
            true,
        );
    }

    public function test_sppg_side_must_really_be_sppg(): void
    {
        $admin = User::factory()->create();

        $invalidSppg = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-WRONG-SPPG'
        );

        $kdkmp = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-03'
        );

        try {
            $this->service->createLink(
                $admin,
                $invalidSppg,
                $kdkmp,
                NetworkRole::PRIMARY,
                true,
            );

            $this->fail(
                'ValidationException was not thrown.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'sppg_organization_id',
                $exception->errors()
            );
        }
    }

    public function test_kdkmp_side_must_really_be_kdkmp(): void
    {
        $admin = User::factory()->create();

        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-03'
        );

        $invalidKdkmp = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-WRONG-KDKMP'
        );

        try {
            $this->service->createLink(
                $admin,
                $sppg,
                $invalidKdkmp,
                NetworkRole::PRIMARY,
                true,
            );

            $this->fail(
                'ValidationException was not thrown.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'kdkmp_organization_id',
                $exception->errors()
            );
        }
    }

    public function test_duplicate_sppg_kdkmp_pair_is_rejected(): void
    {
        $admin = User::factory()->create();

        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-04'
        );

        $kdkmp = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-04'
        );

        $this->service->createLink(
            $admin,
            $sppg,
            $kdkmp,
            NetworkRole::PRIMARY,
            true,
        );

        try {
            $this->service->createLink(
                $admin,
                $sppg,
                $kdkmp,
                NetworkRole::NETWORK,
                false,
            );

            $this->fail(
                'Duplicate network link was accepted.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'kdkmp_organization_id',
                $exception->errors()
            );
        }

        $this->assertSame(
            1,
            SupplyNetworkLink::query()->count()
        );
    }

    public function test_active_network_link_requires_active_primary_first(): void
    {
        $admin = User::factory()->create();

        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-05'
        );

        $kdkmp = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-05'
        );

        try {
            $this->service->createLink(
                $admin,
                $sppg,
                $kdkmp,
                NetworkRole::NETWORK,
                true,
            );

            $this->fail(
                'Active NETWORK was accepted without PRIMARY.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'network_role',
                $exception->errors()
            );
        }

        $this->assertDatabaseMissing(
            'supply_network_links',
            [
                'sppg_organization_id' => $sppg->id,
                'kdkmp_organization_id' => $kdkmp->id,
            ]
        );
    }

    public function test_network_link_can_be_active_after_primary_exists(): void
    {
        $admin = User::factory()->create();

        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-06'
        );

        $primary = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-PRIMARY-06'
        );

        $network = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-NETWORK-06'
        );

        $this->service->createLink(
            $admin,
            $sppg,
            $primary,
            NetworkRole::PRIMARY,
            true,
        );

        $link = $this->service->createLink(
            $admin,
            $sppg,
            $network,
            NetworkRole::NETWORK,
            true,
        );

        $this->assertTrue($link->is_active);

        $this->assertSame(
            NetworkRole::NETWORK,
            $link->network_role
        );
    }

    public function test_second_active_primary_is_rejected(): void
    {
        $admin = User::factory()->create();

        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-07'
        );

        $first = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-PRIMARY-07-A'
        );

        $second = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-PRIMARY-07-B'
        );

        $this->service->createLink(
            $admin,
            $sppg,
            $first,
            NetworkRole::PRIMARY,
            true,
        );

        try {
            $this->service->createLink(
                $admin,
                $sppg,
                $second,
                NetworkRole::PRIMARY,
                true,
            );

            $this->fail(
                'Second active PRIMARY was accepted.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'network_role',
                $exception->errors()
            );
        }

        $this->assertSame(
            1,
            $this->activePrimaryCount($sppg)
        );
    }

    public function test_assign_primary_switches_primary_atomically(): void
    {
        $admin = User::factory()->create();

        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-08'
        );

        $kdkmpA = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-08-A'
        );

        $kdkmpB = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-08-B'
        );

        $linkA = $this->service->createLink(
            $admin,
            $sppg,
            $kdkmpA,
            NetworkRole::PRIMARY,
            true,
        );

        $linkB = $this->service->createLink(
            $admin,
            $sppg,
            $kdkmpB,
            NetworkRole::NETWORK,
            true,
        );

        $newPrimary = $this->service->assignPrimary(
            $admin,
            $linkB
        );

        $linkA->refresh();
        $linkB->refresh();

        $this->assertSame(
            NetworkRole::NETWORK,
            $linkA->network_role
        );

        $this->assertTrue($linkA->is_active);

        $this->assertSame(
            NetworkRole::PRIMARY,
            $linkB->network_role
        );

        $this->assertTrue($linkB->is_active);

        $this->assertSame(
            $linkB->id,
            $newPrimary->id
        );

        $this->assertSame(
            1,
            $this->activePrimaryCount($sppg)
        );
    }

    public function test_active_primary_cannot_be_deactivated_directly(): void
    {
        $admin = User::factory()->create();

        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-09'
        );

        $kdkmp = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-09'
        );

        $link = $this->service->createLink(
            $admin,
            $sppg,
            $kdkmp,
            NetworkRole::PRIMARY,
            true,
        );

        try {
            $this->service->setActiveState(
                $admin,
                $link,
                false
            );

            $this->fail(
                'Active PRIMARY was deactivated directly.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'is_active',
                $exception->errors()
            );
        }

        $this->assertTrue(
            $link->fresh()->is_active
        );
    }

    public function test_network_link_can_be_deactivated(): void
    {
        $admin = User::factory()->create();

        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-10'
        );

        $primary = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-10-A'
        );

        $network = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-10-B'
        );

        $this->service->createLink(
            $admin,
            $sppg,
            $primary,
            NetworkRole::PRIMARY,
            true,
        );

        $networkLink = $this->service->createLink(
            $admin,
            $sppg,
            $network,
            NetworkRole::NETWORK,
            true,
        );

        $updated = $this->service->setActiveState(
            $admin,
            $networkLink,
            false
        );

        $this->assertFalse(
            $updated->is_active
        );

        $this->assertSame(
            1,
            $this->activePrimaryCount($sppg)
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
            ->where('sppg_organization_id', $sppg->id)
            ->where(
                'network_role',
                NetworkRole::PRIMARY->value
            )
            ->where('is_active', true)
            ->count();
    }
}