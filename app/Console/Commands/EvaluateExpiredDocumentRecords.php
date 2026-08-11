<?php

namespace App\Console\Commands;

use App\Enums\DocumentStatus;
use App\Models\DocumentRecord;
use App\Services\Readiness\DocumentRecordService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class EvaluateExpiredDocumentRecords extends Command
{
    protected $signature =
        'documents:evaluate-expiry
        {--chunk=100 : Jumlah Document Record per batch}';

    protected $description =
        'Materialize due VALID Document Records as EXPIRED.';

    public function handle(
        DocumentRecordService $documentRecordService,
    ): int {
        $chunkSize =
            max(
                1,
                (int)
                $this->option(
                    'chunk'
                )
            );

        /*
         * Satu immutable evaluation instant untuk
         * seluruh command run.
         */
        $evaluationTime =
            CarbonImmutable::now();

        $evaluated = 0;
        $expired = 0;
        $failed = 0;

        DocumentRecord::query()
            ->where(
                'status',
                DocumentStatus::VALID
                    ->value
            )
            ->whereNotNull(
                'expires_at'
            )
            ->where(
                'expires_at',
                '<',
                $evaluationTime
            )
            ->orderBy('id')
            ->chunkById(
                $chunkSize,
                function (
                    $records
                ) use (
                    $documentRecordService,
                    $evaluationTime,
                    &$evaluated,
                    &$expired,
                    &$failed,
                ): void {
                    foreach (
                        $records
                        as $record
                    ) {
                        $evaluated++;

                        try {
                            $changed =
                                $documentRecordService
                                    ->expireIfDue(
                                        $record,
                                        $evaluationTime
                                    );

                            if ($changed) {
                                $expired++;
                            }
                        } catch (
                            Throwable $exception
                        ) {
                            $failed++;

                            report(
                                $exception
                            );

                            $this->error(
                                'Document Record '
                                .$record->id
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
            ."expired: {$expired}; "
            ."failed: {$failed}."
        );

        return $failed === 0
            ? self::SUCCESS
            : self::FAILURE;
    }
}