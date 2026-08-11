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
 * Materialize Document Record time expiry.
 *
 * Canonical Document Readiness tetap server-derived
 * dan tidak bergantung pada scheduler ini.
 */
Schedule::command(
    'documents:evaluate-expiry'
)
    ->everyMinute()
    ->withoutOverlapping();

/*
 * Derived Forecast state safety net.
 *
 * Mutation-driven recalculation menggunakan
 * observeAfterCommit().
 *
 * Periodic observer terutama menangkap transition
 * berbasis waktu dan menjadi retry safety net untuk
 * derived Shortfall/RFP observation.
 */
Schedule::command(
    'forecasts:observe-derived-state'
)
    ->everyMinute()
    ->withoutOverlapping();