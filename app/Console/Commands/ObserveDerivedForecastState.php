<?php

namespace App\Console\Commands;

use App\Models\DemandForecast;
use App\Services\Notification\DerivedForecastStateObservationService;
use Illuminate\Console\Command;
use Throwable;

class ObserveDerivedForecastState extends Command
{
    protected $signature =
        'forecasts:observe-derived-state
        {--chunk=100 : Jumlah Forecast per batch}';

    protected $description =
        'Observe derived Shortfall and Ready for Procurement transitions.';

    public function handle(
        DerivedForecastStateObservationService
            $observationService,
    ): int {
        $chunkSize =
            max(
                1,
                (int)
                $this->option(
                    'chunk'
                )
            );

        $evaluated = 0;
        $failed = 0;

        /*
         * MVP scale kecil.
         *
         * Scan semua Forecast sengaja dipilih
         * supaya terminal transition yang sebelumnya
         * RFP TRUE juga tetap dapat diamati.
         */
        DemandForecast::query()
            ->orderBy('id')
            ->chunkById(
                $chunkSize,
                function (
                    $forecasts
                ) use (
                    $observationService,
                    &$evaluated,
                    &$failed,
                ): void {
                    foreach (
                        $forecasts
                        as $forecast
                    ) {
                        try {
                            $observationService
                                ->observe(
                                    $forecast
                                );

                            $evaluated++;
                        } catch (
                            Throwable $exception
                        ) {
                            $failed++;

                            report(
                                $exception
                            );

                            $this->error(
                                'Forecast '
                                .$forecast->id
                                .': '
                                .$exception
                                    ->getMessage()
                            );
                        }
                    }
                }
            );

        $this->info(
            "Evaluated: {$evaluated}; "
            ."failed: {$failed}."
        );

        return $failed === 0
            ? self::SUCCESS
            : self::FAILURE;
    }
}