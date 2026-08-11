<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(
        Inspiring::quote()
    );
})->purpose(
    'Display an inspiring quote'
);

Schedule::command(
    'commitments:evaluate-stale-confidence'
)
    ->hourly()
    ->withoutOverlapping();

/*
 * Derived Forecast state safety net.
 *
 * Mutation-driven recalculation tetap menggunakan
 * observeAfterCommit(). Scheduler ini terutama
 * memastikanculation tetap menggunakan
 * observeAfterCommit(). Scheduler ini terutama
 * memastikan transition berbasis waktu seperti
 * required_end_at tidak bergantung pada user membuka
 * halaman tertentu.
 *
 * MVP scale kecil; no Redis/queue infrastructure
 * diperlukan.
 */
Schedule::command(
    'forecasts:observe-derived-state'
)
    ->everyMinute()
    ->withoutOverlapping();