<?php

namespace App\Services\Notification;

use App\Enums\UserRole;
use App\Enums\NetworkRole;
use App\Enums\OrganizationType;
use App\Models\DemandForecast;
use App\Models\Organization;
use App\Models\SupplyNetworkLink;
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
 * Recipient broadcast Fallback Request.
 *
 * Hanya operational users dari active NETWORK
 * KDKMP yang terkait dengan SPPG Forecast.
 *
 * @return Collection<int, User>
 */
public function fallbackNetworkRecipients(
    DemandForecast $forecast,
    int $requesterOrganizationId,
): Collection {
    $networkOrganizationIds =
        SupplyNetworkLink::query()
            ->where(
                'sppg_organization_id',
                $forecast
                    ->sppg_organization_id
            )
            ->where(
                'network_role',
                NetworkRole::NETWORK->value
            )
            ->where(
                'is_active',
                true
            )
            ->where(
                'kdkmp_organization_id',
                '!=',
                $requesterOrganizationId
            )
            ->orderBy(
                'kdkmp_organization_id'
            )
            ->pluck(
                'kdkmp_organization_id'
            )
            ->map(
                static fn ($id): int =>
                    (int) $id
            )
            ->unique()
            ->values();

    if (
        $networkOrganizationIds
            ->isEmpty()
    ) {
        return collect();
    }

    /*
     * Link aktif saja belum cukup.
     * Organization supplier juga harus aktif.
     */
    $activeOrganizationIds =
        Organization::query()
            ->whereIn(
                'id',
                $networkOrganizationIds
                    ->all()
            )
            ->where(
                'organization_type',
                OrganizationType::KDKMP
                    ->value
            )
            ->where(
                'is_active',
                true
            )
            ->orderBy('id')
            ->pluck('id');

    if (
        $activeOrganizationIds
            ->isEmpty()
    ) {
        return collect();
    }

    return User::query()
        ->whereIn(
            'organization_id',
            $activeOrganizationIds
                ->all()
        )
        ->where(
            'is_active',
            true
        )
        ->whereIn(
            'role',
            [
                UserRole::KDKMP_OPERATOR
                    ->value,

                UserRole::KDKMP_MANAGER
                    ->value,
            ]
        )
        ->orderBy(
            'organization_id'
        )
        ->orderBy('id')
        ->get();
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