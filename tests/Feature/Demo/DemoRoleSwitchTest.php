<?php

namespace Tests\Feature\Demo;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Demo\DemoIdentifiers;
use Database\Seeders\DemoIdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoRoleSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_switch_is_unavailable_when_demo_mode_is_disabled(): void
    {
        config()->set(
            'siagapasok.demo.enabled',
            true
        );

        $this->seed(
            DemoIdentitySeeder::class
        );

        $admin = User::query()
            ->where(
                'email',
                DemoIdentifiers::ADMIN_EMAIL
            )
            ->firstOrFail();

        config()->set(
            'siagapasok.demo.enabled',
            false
        );

        $this->actingAs($admin)
            ->post(
                route(
                    'demo.switch-account',
                    [
                        'account' => 'sppg',
                    ]
                )
            )
            ->assertNotFound();

        $this->assertAuthenticatedAs(
            $admin
        );
    }

    public function test_arbitrary_user_id_cannot_be_used_as_demo_switch_target(): void
    {
        config()->set(
            'siagapasok.demo.enabled',
            true
        );

        $this->seed(
            DemoIdentitySeeder::class
        );

        $admin = User::query()
            ->where(
                'email',
                DemoIdentifiers::ADMIN_EMAIL
            )
            ->firstOrFail();

        $target = User::query()
            ->where(
                'email',
                DemoIdentifiers::SPPG_EMAIL
            )
            ->firstOrFail();

        $this->actingAs($admin)
            ->post(
                route(
                    'demo.switch-account',
                    [
                        'account' =>
                            (string) $target->id,
                    ]
                )
            )
            ->assertNotFound();

        $this->assertAuthenticatedAs(
            $admin
        );
    }

    public function test_inactive_seeded_account_cannot_be_selected(): void
    {
        config()->set(
            'siagapasok.demo.enabled',
            true
        );

        $this->seed(
            DemoIdentitySeeder::class
        );

        $admin = User::query()
            ->where(
                'email',
                DemoIdentifiers::ADMIN_EMAIL
            )
            ->firstOrFail();

        $target = User::query()
            ->where(
                'email',
                DemoIdentifiers::SPPG_EMAIL
            )
            ->firstOrFail();

        $target->forceFill([
            'is_active' => false,
        ])->save();

        $this->actingAs($admin)
            ->post(
                route(
                    'demo.switch-account',
                    [
                        'account' => 'sppg',
                    ]
                )
            )
            ->assertNotFound();

        $this->assertAuthenticatedAs(
            $admin
        );
    }

    public function test_presenter_can_switch_between_all_operational_seeded_accounts_without_mutating_identity(): void
    {
        config()->set(
            'siagapasok.demo.enabled',
            true
        );

        $this->seed(
            DemoIdentitySeeder::class
        );

        $admin = User::query()
            ->where(
                'email',
                DemoIdentifiers::ADMIN_EMAIL
            )
            ->firstOrFail();

        $targets = [
            [
                'key' => 'sppg',
                'email' =>
                    DemoIdentifiers::SPPG_EMAIL,
                'role' =>
                    UserRole::SPPG_USER,
                'organization_code' =>
                    DemoIdentifiers::SPPG_CODE,
            ],
            [
                'key' => 'tani-operator',
                'email' =>
                    DemoIdentifiers::PRIMARY_OPERATOR_EMAIL,
                'role' =>
                    UserRole::KDKMP_OPERATOR,
                'organization_code' =>
                    DemoIdentifiers::PRIMARY_KDKMP_CODE,
            ],
            [
                'key' => 'tani-manager',
                'email' =>
                    DemoIdentifiers::PRIMARY_MANAGER_EMAIL,
                'role' =>
                    UserRole::KDKMP_MANAGER,
                'organization_code' =>
                    DemoIdentifiers::PRIMARY_KDKMP_CODE,
            ],
            [
                'key' => 'mitra-operator',
                'email' =>
                    DemoIdentifiers::NETWORK_OPERATOR_EMAIL,
                'role' =>
                    UserRole::KDKMP_OPERATOR,
                'organization_code' =>
                    DemoIdentifiers::NETWORK_KDKMP_CODE,
            ],
            [
                'key' => 'mitra-manager',
                'email' =>
                    DemoIdentifiers::NETWORK_MANAGER_EMAIL,
                'role' =>
                    UserRole::KDKMP_MANAGER,
                'organization_code' =>
                    DemoIdentifiers::NETWORK_KDKMP_CODE,
            ],
        ];

        $this->actingAs($admin);

        foreach ($targets as $expected) {
            $target = User::query()
                ->with('organization')
                ->where(
                    'email',
                    $expected['email']
                )
                ->firstOrFail();

            $originalRole =
                $target->role;

            $originalOrganizationId =
                $target->organization_id;

            $this->post(
                route(
                    'demo.switch-account',
                    [
                        'account' =>
                            $expected['key'],
                    ]
                )
            )
                ->assertRedirect(
                    route('home')
                );

            $this->assertAuthenticatedAs(
                $target
            );

            $target->refresh();
            $target->load('organization');

            $this->assertSame(
                $expected['role'],
                $target->role
            );

            $this->assertSame(
                $expected['organization_code'],
                $target->organization?->code
            );

            $this->assertSame(
                $originalRole,
                $target->role
            );

            $this->assertSame(
                $originalOrganizationId,
                $target->organization_id
            );
        }
    }
}