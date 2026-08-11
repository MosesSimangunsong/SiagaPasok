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

/*
 * Materialize approved Commitment lifecycle expiry.
 *
 * Canonical supply calculation tetap time-aware
 * dan tidak bergantung pada scheduler ini.
 *
 * Dijalankan sebelum stale confidence agar
 * Commitment yang sudah melewati availability end
 * tidak menerima downgrade stale yang tidak lagi
 * relevan.
 */
Schedule::command(
    'commitments:evaluate-expiry'
)
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command(
    'commitments:evaluate-stale-confidence'
)
    ->hourly()
    ->withoutOverlapping();

/*
 * Materialize lifecycle Fallback berbasis waktu.
 */
Schedule::command(
    'fallback:evaluate-expiry'
)
    ->everyMinute()
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
 * Diletakkan setelah lifecycle jobs agar snapshot
 * periodik membaca materialized temporal state
 * terbaru.
 */
Schedule::command(
    'forecasts:observe-derived-state'
)
    ->everyMinute()
    ->withoutOverlapping();