<?php

namespace App\Console\Commands;

use App\Services\Commitment\CommitmentLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

final class EvaluateExpiredCommitments extends Command
{
    protected $signature =
        'commitments:evaluate-expiry';

    protected $description =
        'Expire due approved Supply Commitments.';

    public function handle(
        CommitmentLifecycleService $lifecycleService,
    ): int {
        $evaluationTime =
            CarbonImmutable::now();

        try {
            $expired =
                $lifecycleService
                    ->expireDueCommitments(
                        $evaluationTime
                    );

            $this->info(
                'Evaluation time: '
                .$evaluationTime
                    ->toIso8601String()
                .'; expired commitments: '
                .$expired
                .'.'
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report(
                $exception
            );

            $this->error(
                'Commitment expiry evaluation gagal: '
                .$exception->getMessage()
            );

            return self::FAILURE;
        }
    }
}