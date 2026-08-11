<?php

namespace App\Services\Notification;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Collection;

final class NotificationRecipientResolver
{
    /**
     * @return Collection<int, User>
     */
    public function kdkmpManagers(
        int $organizationId,
    ): Collection {
        return $this->activeUsersByRoles(
            $organizationId,
            [
                UserRole::KDKMP_MANAGER,
            ]
        );
    }

    /**
     * @return Collection<int, User>
     */
    public function kdkmpOperators(
        int $organizationId,
    ): Collection {
        return $this->activeUsersByRoles(
            $organizationId,
            [
                UserRole::KDKMP_OPERATOR,
            ]
        );
    }

    /**
     * @return Collection<int, User>
     */
    public function kdkmpOperatorsAndManagers(
        int $organizationId,
    ): Collection {
        return $this->activeUsersByRoles(
            $organizationId,
            [
                UserRole::KDKMP_OPERATOR,
                UserRole::KDKMP_MANAGER,
            ]
        );
    }

    /**
     * @return Collection<int, User>
     */
    public function sppgUsers(
        int $organizationId,
    ): Collection {
        return $this->activeUsersByRoles(
            $organizationId,
            [
                UserRole::SPPG_USER,
            ]
        );
    }

    /**
     * @param array<int, UserRole> $roles
     * @return Collection<int, User>
     */
    private function activeUsersByRoles(
        int $organizationId,
        array $roles,
    ): Collection {
        $roleValues =
            collect($roles)
                ->map(
                    static fn (
                        UserRole $role
                    ): string =>
                        $role->value
                )
                ->values()
                ->all();

        return User::query()
            ->where(
                'organization_id',
                $organizationId
            )
            ->where(
                'is_active',
                true
            )
            ->whereIn(
                'role',
                $roleValues
            )
            ->orderBy('id')
            ->get();
    }
}