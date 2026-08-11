<?php

namespace App\Services\Fulfilment;

use App\Models\DemandForecast;
use App\Models\ForecastDerivedStateObservation;
use Illuminate\Validation\ValidationException;

final class FulfilmentHandoffResolver
{
    public function resolve(
        DemandForecast $forecast,
    ): ForecastDerivedStateObservation {
        $observation =
            $this->resolveOrNull(
                $forecast
            );

        if ($observation) {
            return $observation;
        }

        throw ValidationException
            ::withMessages([
                'forecast_id' =>
                    (
                        'Forecast belum memiliki '
                        .'historical Ready for '
                        .'Procurement handoff '
                        .'dengan contributor volume '
                        .'snapshot.'
                    ),
            ]);
    }

    public function resolveOrNull(
        DemandForecast $forecast,
    ): ?ForecastDerivedStateObservation {
        $observations =
            ForecastDerivedStateObservation
                ::query()
                ->where(
                    'forecast_id',
                    $forecast->id
                )
                ->orderBy('id')
                ->get();

        $insideReadyEpisode =
            false;

        $latestEpisodeSnapshot =
            null;

        foreach (
            $observations
            as $observation
        ) {
            if (
                ! $observation
                    ->ready_for_procurement
            ) {
                $insideReadyEpisode =
                    false;

                continue;
            }

            /*
             * FALSE -> TRUE berarti episode RFP
             * baru.
             *
             * Planned fulfilment harus mengambil
             * handoff dari episode TRUE terbaru,
             * bukan episode lama.
             */
            if (! $insideReadyEpisode) {
                $insideReadyEpisode =
                    true;

                $latestEpisodeSnapshot =
                    null;
            }

            /*
             * Observation lama sebelum M12-02
             * dapat mempunyai contributor volume
             * map NULL.
             *
             * Gunakan first snapshot-capable
             * observation dalam episode TRUE.
             *
             * Perubahan allocation berikutnya
             * selama episode tetap TRUE tidak
             * mengubah planned handoff.
             */
            if (
                $latestEpisodeSnapshot
                    === null
                && $this
                    ->hasContributorVolumeSnapshot(
                        $observation
                    )
            ) {
                $latestEpisodeSnapshot =
                    $observation;
            }
        }

        return $latestEpisodeSnapshot;
    }

    private function hasContributorVolumeSnapshot(
        ForecastDerivedStateObservation $observation,
    ): bool {
        $volumes =
            $observation
                ->contributor_safe_supply_by_organization;

        return
            is_array($volumes)
            && $volumes !== [];
    }
}