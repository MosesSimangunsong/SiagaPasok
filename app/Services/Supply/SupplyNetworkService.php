<?php

namespace App\Services\Supply;

use App\Enums\ForecastStatus;
use App\Enums\NetworkRole;
use App\Models\DemandForecast;
use App\Models\Organization;
use App\Models\SupplyNetworkLink;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplyNetworkService
{
    public function createLink(
        User $actor,
        Organization $sppg,
        Organization $kdkmp,
        NetworkRole $networkRole,
        bool $isActive = true,
    ): SupplyNetworkLink {
        $this->assertSystemAdmin($actor);

        return DB::transaction(function () use (
            $actor,
            $sppg,
            $kdkmp,
            $networkRole,
            $isActive,
        ): SupplyNetworkLink {
            $lockedSppg = Organization::query()
                ->whereKey($sppg->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $freshKdkmp = Organization::query()
                ->findOrFail($kdkmp->getKey());

            $this->assertValidPair(
                $lockedSppg,
                $freshKdkmp
            );

            $this->assertPairDoesNotExist(
                $lockedSppg,
                $freshKdkmp
            );

            if ($isActive) {
                $this->assertNoPublishedForecastUsesNetwork(
                    $lockedSppg
                );

                if ($networkRole === NetworkRole::PRIMARY) {
                    $this->assertNoOtherActivePrimary(
                        $lockedSppg
                    );
                }

                if ($networkRole === NetworkRole::NETWORK) {
                    $this->assertActivePrimaryExists(
                        $lockedSppg
                    );
                }
            }

            return SupplyNetworkLink::create([
                'sppg_organization_id' => $lockedSppg->id,
                'kdkmp_organization_id' => $freshKdkmp->id,
                'network_role' => $networkRole,
                'is_active' => $isActive,
                'configured_by' => $actor->id,
            ]);
        });
    }

    public function setActiveState(
        User $actor,
        SupplyNetworkLink $link,
        bool $isActive,
    ): SupplyNetworkLink {
        $this->assertSystemAdmin($actor);

        return DB::transaction(function () use (
            $actor,
            $link,
            $isActive,
        ): SupplyNetworkLink {
            $lockedSppg = Organization::query()
                ->whereKey($link->sppg_organization_id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentLink = SupplyNetworkLink::query()
                ->whereKey($link->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $freshKdkmp = Organization::query()
                ->findOrFail($currentLink->kdkmp_organization_id);

            $this->assertValidPair(
                $lockedSppg,
                $freshKdkmp
            );

            /*
             * Idempotent request: no topology change occurs,
             * therefore no C32 guard is needed.
             */
            if ($currentLink->is_active === $isActive) {
                return $currentLink;
            }

            $this->assertNoPublishedForecastUsesNetwork(
                $lockedSppg
            );

            if ($isActive) {
                if ($currentLink->network_role === NetworkRole::PRIMARY) {
                    $this->assertNoOtherActivePrimary(
                        $lockedSppg,
                        $currentLink->id
                    );
                }

                if ($currentLink->network_role === NetworkRole::NETWORK) {
                    $this->assertActivePrimaryExists(
                        $lockedSppg
                    );
                }
            } else {
                if (
                    $currentLink->network_role === NetworkRole::PRIMARY
                    && $currentLink->is_active
                ) {
                    throw ValidationException::withMessages([
                        'is_active' => (
                            'KDKMP PRIMARY aktif tidak dapat dinonaktifkan langsung. '
                            .'Tetapkan KDKMP lain sebagai PRIMARY terlebih dahulu.'
                        ),
                    ]);
                }
            }

            $currentLink->update([
                'is_active' => $isActive,
                'configured_by' => $actor->id,
            ]);

            return $currentLink->refresh();
        });
    }

    public function assignPrimary(
        User $actor,
        SupplyNetworkLink $targetLink,
    ): SupplyNetworkLink {
        $this->assertSystemAdmin($actor);

        return DB::transaction(function () use (
            $actor,
            $targetLink,
        ): SupplyNetworkLink {
            $lockedSppg = Organization::query()
                ->whereKey($targetLink->sppg_organization_id)
                ->lockForUpdate()
                ->firstOrFail();

            $links = SupplyNetworkLink::query()
                ->where(
                    'sppg_organization_id',
                    $lockedSppg->id
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            /** @var SupplyNetworkLink|null $target */
            $target = $links->firstWhere(
                'id',
                $targetLink->getKey()
            );

            if (! $target) {
                throw ValidationException::withMessages([
                    'network_link' => (
                        'Network link tidak ditemukan pada SPPG tersebut.'
                    ),
                ]);
            }

            $freshKdkmp = Organization::query()
                ->findOrFail(
                    $target->kdkmp_organization_id
                );

            $this->assertValidPair(
                $lockedSppg,
                $freshKdkmp
            );

            /*
             * Already the active PRIMARY:
             * treat the repeated command as idempotent.
             */
            if (
                $target->is_active
                && $target->network_role === NetworkRole::PRIMARY
            ) {
                return $target;
            }

            /*
             * C32:
             * PRIMARY / active network topology cannot change
             * while an SPPG still has a PUBLISHED Forecast.
             */
            $this->assertNoPublishedForecastUsesNetwork(
                $lockedSppg
            );

            foreach ($links as $link) {
                if (
                    $link->id !== $target->id
                    && $link->is_active
                    && $link->network_role === NetworkRole::PRIMARY
                ) {
                    $link->update([
                        'network_role' => NetworkRole::NETWORK,
                        'configured_by' => $actor->id,
                    ]);
                }
            }

            $target->update([
                'network_role' => NetworkRole::PRIMARY,
                'is_active' => true,
                'configured_by' => $actor->id,
            ]);

            return $target->refresh();
        });
    }

    private function assertSystemAdmin(User $actor): void
    {
        if (
            ! $actor->isSystemAdmin()
            || ! $actor->hasValidIdentityContext()
        ) {
            throw new AuthorizationException(
                'Hanya System Admin aktif yang dapat mengubah konfigurasi supply network.'
            );
        }
    }

    private function assertValidPair(
        Organization $sppg,
        Organization $kdkmp,
    ): void {
        if (! $sppg->isSppg()) {
            throw ValidationException::withMessages([
                'sppg_organization_id' => (
                    'Organization pada sisi SPPG harus bertipe SPPG.'
                ),
            ]);
        }

        if (! $kdkmp->isKdkmp()) {
            throw ValidationException::withMessages([
                'kdkmp_organization_id' => (
                    'Organization pada sisi KDKMP harus bertipe KDKMP.'
                ),
            ]);
        }
    }

    private function assertPairDoesNotExist(
        Organization $sppg,
        Organization $kdkmp,
    ): void {
        $exists = SupplyNetworkLink::query()
            ->where(
                'sppg_organization_id',
                $sppg->id
            )
            ->where(
                'kdkmp_organization_id',
                $kdkmp->id
            )
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'kdkmp_organization_id' => (
                    'KDKMP tersebut sudah terhubung dengan SPPG ini.'
                ),
            ]);
        }
    }

    private function assertNoOtherActivePrimary(
        Organization $sppg,
        ?int $exceptLinkId = null,
    ): void {
        $query = SupplyNetworkLink::query()
            ->where(
                'sppg_organization_id',
                $sppg->id
            )
            ->where(
                'network_role',
                NetworkRole::PRIMARY->value
            )
            ->where('is_active', true);

        if ($exceptLinkId !== null) {
            $query->whereKeyNot($exceptLinkId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'network_role' => (
                    'SPPG hanya boleh memiliki satu KDKMP PRIMARY aktif.'
                ),
            ]);
        }
    }

    private function assertActivePrimaryExists(
        Organization $sppg,
    ): void {
        $exists = SupplyNetworkLink::query()
            ->where(
                'sppg_organization_id',
                $sppg->id
            )
            ->where(
                'network_role',
                NetworkRole::PRIMARY->value
            )
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'network_role' => (
                    'Tetapkan satu KDKMP PRIMARY aktif sebelum '
                    .'mengaktifkan KDKMP NETWORK.'
                ),
            ]);
        }
    }

    private function assertNoPublishedForecastUsesNetwork(
        Organization $sppg,
    ): void {
        $hasPublishedForecast = DemandForecast::query()
            ->where(
                'sppg_organization_id',
                $sppg->id
            )
            ->where(
                'status',
                ForecastStatus::PUBLISHED->value
            )
            ->exists();

        if ($hasPublishedForecast) {
            throw ValidationException::withMessages([
                'network_configuration' => (
                    'Konfigurasi Supply Network tidak dapat diubah '
                    .'selama SPPG masih memiliki Forecast PUBLISHED. '
                    .'Tutup atau batalkan Forecast aktif terlebih dahulu.'
                ),
            ]);
        }
    }
}