<?php

namespace App\Policies;

use App\Enums\FallbackRequestStatus;
use App\Enums\NetworkRole;
use App\Models\FallbackOffer;
use App\Models\FallbackRequest;
use App\Models\SupplyNetworkLink;
use App\Models\User;

class FallbackOfferPolicy
{
    public function viewAny(
        User $user
    ): bool {
        return $user->belongsToKdkmp()
            && $user->hasValidIdentityContext();
    }

    /**
     * Offer boleh dibaca oleh:
     *
     * - supplier organization pemilik Offer;
     * - requester organization parent Request.
     *
     * Tetapi payload kedua sisi TIDAK sama.
     *
     * Supplier dapat memperoleh internal source
     * context miliknya sendiri.
     *
     * Requester hanya memperoleh aggregate
     * supplier Offer.
     */
    public function view(
        User $user,
        FallbackOffer $offer
    ): bool {
        return $this->belongsToSupplier(
            $user,
            $offer
        )
            || $this->belongsToRequester(
                $user,
                $offer
            );
    }

    public function viewSupplier(
        User $user,
        FallbackOffer $offer
    ): bool {
        return $this->belongsToSupplier(
            $user,
            $offer
        );
    }

    public function viewRequester(
        User $user,
        FallbackOffer $offer
    ): bool {
        return $this->belongsToRequester(
            $user,
            $offer
        );
    }

    /**
     * HARD PRIVACY BOUNDARY.
     *
     * FallbackOfferSource / source Commitment /
     * producer detail hanya boleh dilihat oleh
     * supplier organization pemilik Offer.
     */
    public function viewSources(
        User $user,
        FallbackOffer $offer
    ): bool {
        return $this->belongsToSupplier(
            $user,
            $offer
        );
    }

    /**
     * Initial Offer entry hanya untuk Operator
     * yang merupakan NETWORK aktif terhadap SPPG
     * parent Request.
     *
     * Service tetap authority final untuk:
     * - Request OPEN;
     * - supplier != requester;
     * - source capacity;
     * - expiry;
     * - exact business state.
     */
    public function createForRequest(
        User $user,
        FallbackRequest $request
    ): bool {
        if (
            ! $user->isKdkmpOperator()
            || ! $user->hasValidIdentityContext()
        ) {
            return false;
        }

        return $this->isEligibleNetworkSupplier(
            $user,
            $request
        );
    }

    public function submit(
        User $user,
        FallbackOffer $offer
    ): bool {
        return $user->isKdkmpOperator()
            && $this->belongsToSupplier(
                $user,
                $offer
            );
    }

    /**
     * Supplier Manager:
     *
     * PENDING_APPROVAL -> AVAILABLE / REJECTED.
     *
     * State validation tetap berada di service.
     */
    public function supplierReview(
        User $user,
        FallbackOffer $offer
    ): bool {
        return $user->isKdkmpManager()
            && $this->belongsToSupplier(
                $user,
                $offer
            );
    }

    /**
     * Supplier Manager dapat withdraw sesuai
     * state machine service.
     */
    public function withdraw(
        User $user,
        FallbackOffer $offer
    ): bool {
        return $user->isKdkmpManager()
            && $this->belongsToSupplier(
                $user,
                $offer
            );
    }

    /**
     * Requester Manager menjadi decision maker
     * untuk AVAILABLE Offer:
     *
     * - Accept;
     * - Reject.
     */
    public function requesterDecision(
        User $user,
        FallbackOffer $offer
    ): bool {
        return $user->isKdkmpManager()
            && $this->belongsToRequester(
                $user,
                $offer
            );
    }

    private function belongsToSupplier(
        User $user,
        FallbackOffer $offer
    ): bool {
        return $user->hasValidIdentityContext()
            && $user->belongsToKdkmp()
            && $user->organization_id
                === $offer
                    ->supplier_organization_id;
    }

    private function belongsToRequester(
        User $user,
        FallbackOffer $offer
    ): bool {
        if (
            ! $user->hasValidIdentityContext()
            || ! $user->belongsToKdkmp()
        ) {
            return false;
        }

        $offer->loadMissing(
            'fallbackRequest'
        );

        return $offer->fallbackRequest
            && $user->organization_id
                === $offer
                    ->fallbackRequest
                    ->requester_organization_id;
    }

    private function isEligibleNetworkSupplier(
        User $user,
        FallbackRequest $request
    ): bool {
        if (
            $request->status
            !== FallbackRequestStatus::OPEN
        ) {
            return false;
        }

        /*
         * C17 / privacy boundary:
         * requester tidak menjadi supplier untuk
         * Request miliknya sendiri.
         */
        if (
            $user->organization_id
            === $request
                ->requester_organization_id
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