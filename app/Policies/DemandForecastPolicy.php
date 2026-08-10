<?php

namespace App\Policies;

use App\Enums\ForecastStatus;
use App\Enums\NetworkRole;
use App\Models\DemandForecast;
use App\Models\SupplyNetworkLink;
use App\Models\User;

class DemandForecastPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSppgUser()
            && $user->hasValidIdentityContext();
    }

    public function viewKdkmpIndex(User $user): bool
    {
        return $user->belongsToKdkmp()
            && $user->hasValidIdentityContext();
    }

    public function view(
        User $user,
        DemandForecast $forecast
    ): bool {
        if (! $user->hasValidIdentityContext()) {
            return false;
        }

        if ($user->isSppgUser()) {
            return $user->organization_id
                === $forecast->sppg_organization_id;
        }

        if (! $user->belongsToKdkmp()) {
            return false;
        }

        if (
            $forecast->status
            !== ForecastStatus::PUBLISHED
        ) {
            return false;
        }

        if (
            ! $forecast->sppgOrganization
            || ! $forecast->sppgOrganization->is_active
        ) {
            return false;
        }

        return SupplyNetworkLink::query()
            ->where(
                'sppg_organization_id',
                $forecast->sppg_organization_id
            )
            ->where(
                'kdkmp_organization_id',
                $user->organization_id
            )
            ->where(
                'network_role',
                NetworkRole::PRIMARY->value
            )
            ->where('is_active', true)
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->isSppgUser()
            && $user->hasValidIdentityContext();
    }

    public function updateDraft(
        User $user,
        DemandForecast $forecast
    ): bool {
        return $this->ownsForecast(
            $user,
            $forecast
        ) && $forecast->isDraft();
    }

    public function publish(
        User $user,
        DemandForecast $forecast
    ): bool {
        return $this->ownsForecast(
            $user,
            $forecast
        );
    }

    public function revise(
        User $user,
        DemandForecast $forecast
    ): bool {
        return $this->ownsForecast(
            $user,
            $forecast
        );
    }

    public function cancel(
        User $user,
        DemandForecast $forecast
    ): bool {
        return $this->ownsForecast(
            $user,
            $forecast
        );
    }

    public function close(
        User $user,
        DemandForecast $forecast
    ): bool {
        return $this->ownsForecast(
            $user,
            $forecast
        );
    }

    private function ownsForecast(
        User $user,
        DemandForecast $forecast
    ): bool {
        return $user->isSppgUser()
            && $user->hasValidIdentityContext()
            && $user->organization_id
                === $forecast->sppg_organization_id;
    }
}