<?php

namespace Tests\Feature\Notification;

use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use App\Services\Notification\NotificationRecipientResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationRecipientResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_kdkmp_managers_returns_only_active_managers_from_target_organization(): void
    {
        $organization =
            $this->createOrganization(
                'TARGET'
            );

        $otherOrganization =
            $this->createOrganization(
                'OTHER'
            );

        $activeManagerA =
            $this->createUser(
                $organization,
                UserRole::KDKMP_MANAGER
            );

        $activeManagerB =
            $this->createUser(
                $organization,
                UserRole::KDKMP_MANAGER
            );

        /*
         * Same organization, wrong role.
         */
        $this->createUser(
            $organization,
            UserRole::KDKMP_OPERATOR
        );

        /*
         * Same organization + correct role,
         * tetapi inactive.
         */
        $this->createUser(
            $organization,
            UserRole::KDKMP_MANAGER,
            false
        );

        /*
         * Correct role tetapi tenant berbeda.
         */
        $this->createUser(
            $otherOrganization,
            UserRole::KDKMP_MANAGER
        );

        /*
         * System Admin tidak memiliki
         * organization operational scope.
         */
        User::factory()->create([
            'organization_id' =>
                null,

            'role' =>
                UserRole::SYSTEM_ADMIN,

            'is_active' =>
                true,
        ]);

        $recipients =
            $this->resolver()
                ->kdkmpManagers(
                    $organization->id
                );

        $this->assertSame(
            [
                $activeManagerA->id,
                $activeManagerB->id,
            ],
            $recipients
                ->pluck('id')
                ->all()
        );
    }

    public function test_kdkmp_operators_and_managers_returns_both_operational_roles_only(): void
    {
        $organization =
            $this->createOrganization(
                'OPS-MANAGER'
            );

        $operator =
            $this->createUser(
                $organization,
                UserRole::KDKMP_OPERATOR
            );

        $manager =
            $this->createUser(
                $organization,
                UserRole::KDKMP_MANAGER
            );

        $this->createUser(
            $organization,
            UserRole::SPPG_USER
        );

        $recipients =
            $this->resolver()
                ->kdkmpOperatorsAndManagers(
                    $organization->id
                );

        $this->assertSame(
            [
                $operator->id,
                $manager->id,
            ],
            $recipients
                ->pluck('id')
                ->all()
        );
    }

    public function test_sppg_users_are_scoped_to_requested_sppg_organization(): void
    {
        $sppg =
            Organization::create([
                'code' =>
                    'SPPG-NOTIF-TARGET',

                'name' =>
                    'SPPG Notification Target',

                'organization_type' =>
                    OrganizationType::SPPG,

                'is_active' =>
                    true,

                'general_location' =>
                    'Lokasi Test',
            ]);

        $otherSppg =
            Organization::create([
                'code' =>
                    'SPPG-NOTIF-OTHER',

                'name' =>
                    'SPPG Notification Other',

                'organization_type' =>
                    OrganizationType::SPPG,

                'is_active' =>
                    true,

                'general_location' =>
                    'Lokasi Test',
            ]);

        $targetUser =
            $this->createUser(
                $sppg,
                UserRole::SPPG_USER
            );

        $this->createUser(
            $otherSppg,
            UserRole::SPPG_USER
        );

        $recipients =
            $this->resolver()
                ->sppgUsers(
                    $sppg->id
                );

        $this->assertSame(
            [
                $targetUser->id,
            ],
            $recipients
                ->pluck('id')
                ->all()
        );
    }

    public function test_inactive_user_never_receives_notification_recipient_resolution(): void
    {
        $organization =
            $this->createOrganization(
                'INACTIVE'
            );

        $this->createUser(
            $organization,
            UserRole::KDKMP_OPERATOR,
            false
        );

        $this->createUser(
            $organization,
            UserRole::KDKMP_MANAGER,
            false
        );

        $this->assertTrue(
            $this->resolver()
                ->kdkmpOperatorsAndManagers(
                    $organization->id
                )
                ->isEmpty()
        );
    }

    private function resolver():
        NotificationRecipientResolver
    {
        return app(
            NotificationRecipientResolver::class
        );
    }

    private function createOrganization(
        string $suffix,
    ): Organization {
        return Organization::create([
            'code' =>
                "KDKMP-NOTIF-{$suffix}",

            'name' =>
                "KDKMP Notification {$suffix}",

            'organization_type' =>
                OrganizationType::KDKMP,

            'is_active' =>
                true,

            'general_location' =>
                'Lokasi Notification Test',
        ]);
    }

    private function createUser(
        Organization $organization,
        UserRole $role,
        bool $isActive = true,
    ): User {
        return User::factory()->create([
            'organization_id' =>
                $organization->id,

            'role' =>
                $role,

            'is_active' =>
                $isActive,
        ]);
    }
}