<?php

namespace App\Services\Commitment;

use App\Enums\ForecastStatus;
use App\Enums\NetworkRole;
use App\Models\DemandForecast;
use App\Models\FallbackRequest;
use App\Models\SupplyCommitment;
use App\Models\SupplyNetworkLink;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class CommitmentEligibilityService
{
    /**
     * Entry point normal Direct Commitment.
     *
     * Hanya active PRIMARY.
     */
    public function assertPrimaryDirectEligibility(
        User $actor,
        DemandForecast $forecast,
        bool $requireOperator = true,
    ): void {
        $this->assertKdkmpIdentity(
            $actor,
            $requireOperator
        );

        $this->assertPublishedForecast(
            $forecast
        );

        $primaryOrganizationIds =
            $this->activePrimaryOrganizationIds(
                $forecast
            );

        if (
            $primaryOrganizationIds->count() !== 1
            || (int) $primaryOrganizationIds->first()
                !== $actor->organization_id
        ) {
            throw new AuthorizationException(
                'KDKMP bukan PRIMARY aktif untuk Forecast tersebut.'
            );
        }
    }

    /**
     * Entry point KHUSUS ketika NETWORK membuat
     * initial Commitment untuk membackup fallback.
     *
     * NETWORK tidak mendapatkan normal direct
     * Commitment create path.
     */
    public function assertNetworkFallbackEntryEligibility(
        User $actor,
        FallbackRequest $request,
        DemandForecast $forecast,
        bool $requireOperator = true,
    ): void {
        $this->assertKdkmpIdentity(
            $actor,
            $requireOperator
        );

        $this->assertPublishedForecast(
            $forecast
        );

        if (! $request->isOpen()) {
            throw ValidationException::withMessages([
                'fallback_request_id' => (
                    'Fallback source Commitment hanya '
                    .'dapat disiapkan untuk Fallback '
                    .'Request OPEN.'
                ),
            ]);
        }

        if (
            $request->forecast_id
            !== $forecast->id
        ) {
            throw ValidationException::withMessages([
                'fallback_request_id' => (
                    'Fallback Request tidak sesuai '
                    .'dengan Forecast source Commitment.'
                ),
            ]);
        }

        /*
         * Defensive unit integrity.
         *
         * Tidak ada unit conversion pada MVP.
         */
        if (
            $request->unit_id
            !== $forecast->unit_id
        ) {
            throw ValidationException::withMessages([
                'unit_id' => (
                    'Unit Fallback Request tidak '
                    .'sesuai dengan Forecast.'
                ),
            ]);
        }

        if (
            $request->requester_organization_id
            === $actor->organization_id
        ) {
            throw new AuthorizationException(
                'Requester tidak dapat menjadi supplier untuk Fallback Request yang sama.'
            );
        }

        /*
         * OPEN state mungkin belum diproses oleh
         * batch expiry. Jangan izinkan entry baru
         * setelah response deadline secara waktu.
         *
         * Equality dengan deadline masih valid.
         */
        if (
            CarbonImmutable::now()->gt(
                CarbonImmutable::instance(
                    $request
                        ->response_deadline_at
                )
            )
        ) {
            throw ValidationException::withMessages([
                'fallback_request_id' =>
                    'Fallback Request telah melewati response deadline.',
            ]);
        }

        /*
         * Requester harus tetap merupakan exactly
         * one active PRIMARY.
         *
         * Normal M02/M03 mutation path seharusnya
         * menjaga invariant ini, tetapi calculator
         * dan fallback tetap fail closed terhadap
         * corrupted persisted state.
         */
        $primaryOrganizationIds =
            $this->activePrimaryOrganizationIds(
                $forecast
            );

        if (
            $primaryOrganizationIds->count() !== 1
            || (int) $primaryOrganizationIds->first()
                !== $request
                    ->requester_organization_id
        ) {
            throw ValidationException::withMessages([
                'fallback_request_id' => (
                    'Requester Fallback tidak lagi '
                    .'sesuai dengan PRIMARY aktif '
                    .'Forecast.'
                ),
            ]);
        }

        $isActiveNetwork =
            SupplyNetworkLink::query()
                ->where(
                    'sppg_organization_id',
                    $forecast
                        ->sppg_organization_id
                )
                ->where(
                    'kdkmp_organization_id',
                    $actor->organization_id
                )
                ->where(
                    'network_role',
                    NetworkRole::NETWORK
                        ->value
                )
                ->where(
                    'is_active',
                    true
                )
                ->exists();

        if (! $isActiveNetwork) {
            throw new AuthorizationException(
                'KDKMP supplier bukan NETWORK aktif untuk Forecast tersebut.'
            );
        }
    }

    /**
     * Eligibility untuk mutation Commitment yang
     * SUDAH terbentuk secara sah.
     *
     * PRIMARY:
     * berasal dari normal direct entry.
     *
     * NETWORK:
     * initial logical Commitment hanya dapat
     * terbentuk melalui OPEN Fallback Request.
     *
     * Setelah terbentuk, supplier dapat menjalankan
     * standard immutable version workflow terhadap
     * Commitment miliknya sendiri tanpa harus
     * menyimpan fallback_request_id pada Commitment.
     */
    public function assertExistingCommitmentEligibility(
        User $actor,
        DemandForecast $forecast,
        SupplyCommitment $commitment,
        bool $requireOperator = true,
    ): void {
        $this->assertKdkmpIdentity(
            $actor,
            $requireOperator
        );

        $this->assertPublishedForecast(
            $forecast
        );

        if (
            $commitment->organization_id
            !== $actor->organization_id
        ) {
            throw new AuthorizationException(
                'Commitment tersebut bukan milik organisasi KDKMP Anda.'
            );
        }

        if (
            $commitment->forecast_id
            !== $forecast->id
        ) {
            throw ValidationException::withMessages([
                'forecast_id' =>
                    'Commitment tidak sesuai dengan Forecast.',
            ]);
        }

        $activeNetworkLinks =
    SupplyNetworkLink::query()
        ->where(
            'sppg_organization_id',
            $forecast
                ->sppg_organization_id
        )
        ->where(
            'kdkmp_organization_id',
            $actor->organization_id
        )
        ->where(
            'is_active',
            true
        )
        ->orderBy('id')
        ->get([
            'network_role',
        ]);

if ($activeNetworkLinks->isEmpty()) {
    throw new AuthorizationException(
        'KDKMP tidak memiliki network relationship aktif terhadap Forecast.'
    );
}

/*
 * network_role adalah enum-cast attribute.
 *
 * Jangan membandingkan hasil Eloquent terhadap
 * NetworkRole::*->value karena attribute yang
 * dibaca dari model sudah berupa NetworkRole.
 */
$hasNetworkRole =
    $activeNetworkLinks->contains(
        fn (
            SupplyNetworkLink $link
        ): bool =>
            $link->network_role
            === NetworkRole::NETWORK
    );

if ($hasNetworkRole) {
    return;
}

$hasPrimaryRole =
    $activeNetworkLinks->contains(
        fn (
            SupplyNetworkLink $link
        ): bool =>
            $link->network_role
            === NetworkRole::PRIMARY
    );

if ($hasPrimaryRole) {
    /*
     * PRIMARY tetap harus memenuhi exactly-one
     * active PRIMARY invariant untuk SPPG.
     */
    $primaryOrganizationIds =
        $this->activePrimaryOrganizationIds(
            $forecast
        );

    if (
        $primaryOrganizationIds->count() === 1
        && (int)
            $primaryOrganizationIds->first()
            === $actor->organization_id
    ) {
        return;
    }
}

        throw new AuthorizationException(
            'KDKMP tidak eligible mengelola Commitment untuk Forecast tersebut.'
        );
    }

    private function assertKdkmpIdentity(
        User $actor,
        bool $requireOperator,
    ): void {
        if (
            $requireOperator
            && ! $actor->isKdkmpOperator()
        ) {
            throw new AuthorizationException(
                'Hanya KDKMP Operator yang dapat membuat atau mengubah Commitment.'
            );
        }

        if (
            ! $actor->hasValidIdentityContext()
            || ! $actor->belongsToKdkmp()
        ) {
            throw new AuthorizationException(
                'Konteks organisasi KDKMP tidak valid.'
            );
        }
    }

    private function assertPublishedForecast(
        DemandForecast $forecast,
    ): void {
        if (
            $forecast->status
            !== ForecastStatus::PUBLISHED
        ) {
            throw ValidationException::withMessages([
                'forecast_id' => (
                    'Supply Commitment hanya dapat '
                    .'diproses untuk Forecast PUBLISHED.'
                ),
            ]);
        }
    }

    private function activePrimaryOrganizationIds(
        DemandForecast $forecast,
    ) {
        return SupplyNetworkLink::query()
            ->where(
                'sppg_organization_id',
                $forecast
                    ->sppg_organization_id
            )
            ->where(
                'network_role',
                NetworkRole::PRIMARY
                    ->value
            )
            ->where(
                'is_active',
                true
            )
            ->orderBy('id')
            ->pluck(
                'kdkmp_organization_id'
            );
    }
}