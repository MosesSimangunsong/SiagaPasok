<?php

namespace App\Policies;

use App\Enums\FallbackRequestStatus;
use App\Enums\NetworkRole;
use App\Models\FallbackRequest;
use App\Models\SupplyNetworkLink;
use App\Models\User;

class FallbackRequestPolicy
{
    public function viewAny(
        User $user
    ): bool {
        return $user->belongsToKdkmp()
            && $user->hasValidIdentityContext();
    }

    /**
     * Detail Request dapat dibaca oleh:
     *
     * 1. requester organization sendiri; atau
     * 2. active NETWORK supplier ketika Request OPEN.
     *
     * Controller tetap wajib membedakan serializer:
     * requester boleh memperoleh requester-side payload,
     * sedangkan NETWORK hanya broadcast-safe payload.
     */
    public function view(
        User $user,
        FallbackRequest $request
    ): bool {
        return $this->belongsToRequester(
            $user,
            $request
        )
            || $this->canViewBroadcast(
                $user,
                $request
            );
    }

    /**
     * Explicit ability untuk requester-side pages.
     */
    public function viewRequester(
        User $user,
        FallbackRequest $request
    ): bool {
        return $this->belongsToRequester(
            $user,
            $request
        );
    }

    /**
     * Explicit ability untuk supplier network
     * broadcast page.
     */
    public function viewBroadcast(
        User $user,
        FallbackRequest $request
    ): bool {
        return $this->canViewBroadcast(
            $user,
            $request
        );
    }

    /**
     * Context Forecast/Shortfall/PRIMARY tetap
     * diverifikasi FallbackRequestService.
     *
     * Policy hanya role + tenant boundary.
     */
    public function create(
        User $user
    ): bool {
        return $user->isKdkmpOperator()
            && $user->hasValidIdentityContext();
    }

    public function submit(
        User $user,
        FallbackRequest $request
    ): bool {
        return $user->isKdkmpOperator()
            && $this->belongsToRequester(
                $user,
                $request
            );
    }

    public function approveBroadcast(
        User $user,
        FallbackRequest $request
    ): bool {
        return $user->isKdkmpManager()
            && $this->belongsToRequester(
                $user,
                $request
            );
    }

    public function rejectBroadcast(
        User $user,
        FallbackRequest $request
    ): bool {
        return $user->isKdkmpManager()
            && $this->belongsToRequester(
                $user,
                $request
            );
    }

    public function cancel(
        User $user,
        FallbackRequest $request
    ): bool {
        return $user->isKdkmpManager()
            && $this->belongsToRequester(
                $user,
                $request
            );
    }

    private function belongsToRequester(
        User $user,
        FallbackRequest $request
    ): bool {
        return $user->hasValidIdentityContext()
            && $user->belongsToKdkmp()
            && $user->organization_id
                === $request
                    ->requester_organization_id;
    }

    private function canViewBroadcast(
        User $user,
        FallbackRequest $request
    ): bool {
        if (
            ! $user->hasValidIdentityContext()
            || ! $user->belongsToKdkmp()
        ) {
            return false;
        }

        /*
         * Requester menggunakan requester view,
         * bukan broadcast supplier view.
         */
        if (
            $user->organization_id
            === $request
                ->requester_organization_id
        ) {
            return false;
        }

        /*
         * Broadcast hanya aktif ketika OPEN.
         *
         * Historical supplier Offer tetap dapat
         * dibaca melalui FallbackOfferPolicy
         * meskipun parent Request kemudian terminal.
         */
        if (
            $request->status
            !== FallbackRequestStatus::OPEN
        ) {
            return false;
        }

        $request->loadMissing(
            'forecast'
        );

        if (! $request->forecast) {
            return false;
        }

        return SupplyNetworkLink::query()
            ->where(
                'sppg_organization_id',
                $request
                    ->forecast
                    ->sppg_organization_id
            )
            ->where(
                'kdkmp_organization_id',
                $user->organization_id
            )
            ->where(
                'network_role',
                NetworkRole::NETWORK->value
            )
            ->where(
                'is_active',
                true
            )
            ->exists();
    }
}