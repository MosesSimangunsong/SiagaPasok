<?php

namespace App\Console\Commands;

use App\Enums\CommitmentLifecycleStatus;
use App\Enums\SupplyConfidence;
use App\Models\SupplyCommitment;
use App\Services\Commitment\ConfidenceService;
use Illuminate\Console\Command;
use Throwable;

class EvaluateStaleCommitmentConfidence extends Command
{
    protected $signature =
        'commitments:evaluate-stale-confidence
        {--chunk=100 : Jumlah Commitment per batch}';

    protected $description =
        'Downgrade stale GREEN commitments to YELLOW based on Forecast freshness configuration.';

    public function handle(
        ConfidenceService $confidenceService,
    ): int {
        $chunkSize = max(
            1,
            (int) $this->option('chunk')
        );

        $evaluated = 0;
        $downgraded = 0;
        $failed = 0;

        SupplyCommitment::query()
            ->where(
                'lifecycle_status',
                CommitmentLifecycleStatus
                    ::ACTIVE
                    ->value
            )
            ->where(
                'current_confidence',
                SupplyConfidence
                    ::GREEN
                    ->value
            )
            ->whereNotNull(
                'active_version_id'
            )
            ->whereNotNull(
                'last_confidence_verified_at'
            )
            ->whereHas(
                'forecast',
                fn ($query) =>
                    $query
                        ->whereNotNull(
                            'freshness_interval_hours'
                        )
                        ->where(
                            'freshness_interval_hours',
                            '>',
                            0
                        )
            )
            ->with([
                'forecast:id,freshness_interval_hours',
            ])
            ->orderBy('id')
            ->chunkById(
                $chunkSize,
                function (
                    $commitments
                ) use (
                    $confidenceService,
                    &$evaluated,
                    &$downgraded,
                    &$failed,
                ): void {
                    foreach (
                        $commitments
                        as $commitment
                    ) {
                        $evaluated++;

                        $freshnessIntervalHours =
                            (int)
                            $commitment
                                ->forecast
                                ->freshness_interval_hours;

                        try {
                            $changed =
                                $confidenceService
                                    ->downgradeStaleIfDue(
                                        $commitment,
                                        $freshnessIntervalHours
                                    );

                            if ($changed) {
                                $downgraded++;
                            }
                        } catch (Throwable $exception) {
                            $failed++;

                            report($exception);

                            $this->error(
                                "Commitment {$commitment->id}: "
                                .$exception->getMessage()
                            );
                        }
                    }
                }
            );

        $this->info(
            "Evaluated: {$evaluated}; "
            ."downgraded: {$downgraded}; "
            ."failed: {$failed}."
        );

        return $failed === 0
            ? self::SUCCESS
            : self::FAILURE;
    }
}