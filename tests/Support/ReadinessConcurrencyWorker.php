<?php

declare(strict_types=1);

use App\Models\DocumentRecord;
use App\Models\ReadinessChecklist;
use App\Models\User;
use App\Services\Readiness\DocumentRecordService;
use App\Services\Readiness\ReadinessChecklistReviewService;
use App\Services\Readiness\ReadinessChecklistRevisionService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;
use Tests\Support\FallbackConcurrencyDatabase;

$root =
    dirname(
        __DIR__,
        2
    );

require $root
    .'/vendor/autoload.php';

$app =
    require $root
        .'/bootstrap/app.php';

$app->make(
    Kernel::class
)->bootstrap();

/*
 * Reuse PostgreSQL concurrency connection
 * infrastructure M07.
 *
 * Tidak mengubah Fallback worker dan tidak
 * membuat safety configuration kedua.
 */
FallbackConcurrencyDatabase
    ::configure();

$operation =
    $argv[1] ?? '';

$actorId =
    isset($argv[2])
        ? (int) $argv[2]
        : 0;

$targetId =
    isset($argv[3])
        ? (int) $argv[3]
        : 0;

$barrierDirectory =
    $argv[4] ?? '';

$workerId =
    $argv[5] ?? '';

$testNow =
    $argv[6] ?? '';

$operationValue =
    $argv[7] ?? null;

if (
    $operation === ''
    || $actorId <= 0
    || $targetId <= 0
    || $barrierDirectory === ''
    || $workerId === ''
    || $testNow === ''
) {
    fwrite(
        STDERR,
        'Invalid readiness concurrency worker arguments.'
        .PHP_EOL
    );

    exit(2);
}

CarbonImmutable::setTestNow(
    CarbonImmutable::parse(
        $testNow
    )
);

$actor =
    User::query()
        ->findOrFail(
            $actorId
        );

/*
 * Resolve target sebelum READY supaya bootstrap/
 * connection/model lookup bukan bagian dari race.
 *
 * Service tetap melakukan fresh query +
 * lockForUpdate() ketika operation dijalankan.
 */
$target =
    match ($operation) {
        'revision',
        'approve' =>
            ReadinessChecklist::query()
                ->findOrFail(
                    $targetId
                ),

        'document_update' =>
            DocumentRecord::query()
                ->findOrFail(
                    $targetId
                ),

        default =>
            throw new RuntimeException(
                'Unknown readiness worker operation: '
                .$operation
            ),
    };

$readyPath =
    $barrierDirectory
    .DIRECTORY_SEPARATOR
    .$workerId
    .'.ready';

$resultPath =
    $barrierDirectory
    .DIRECTORY_SEPARATOR
    .$workerId
    .'.result.json';

$goPath =
    $barrierDirectory
    .DIRECTORY_SEPARATOR
    .'go';

file_put_contents(
    $readyPath,
    'ready'
);

$deadline =
    microtime(true)
    + 15.0;

while (
    ! file_exists(
        $goPath
    )
) {
    if (
        microtime(true)
        >= $deadline
    ) {
        file_put_contents(
            $resultPath,
            json_encode(
                [
                    'status' =>
                        'worker_timeout',

                    'message' =>
                        'Barrier GO tidak diterima.',
                ],
                JSON_PRETTY_PRINT
            )
        );

        exit(3);
    }

    usleep(
        10_000
    );
}

try {
    $result =
        match ($operation) {
            'revision' =>
                app(
                    ReadinessChecklistRevisionService::class
                )->createRevision(
                    $actor,
                    $target
                ),

            'approve' =>
                app(
                    ReadinessChecklistReviewService::class
                )->approve(
                    $actor,
                    $target
                ),

            'document_update' =>
                app(
                    DocumentRecordService::class
                )->update(
                    $actor,
                    $target,
                    [
                        'notes' =>
                            $operationValue
                            ?? 'Concurrent document mutation.',
                    ]
                ),

            default =>
                throw new RuntimeException(
                    'Unknown readiness worker operation: '
                    .$operation
                ),
        };

    $payload =
        match ($operation) {
            'revision',
            'approve' => [
                'status' =>
                    'ok',

                'checklist_id' =>
                    $result->id,

                'checklist_status' =>
                    $result
                        ->status
                        ->value,

                'version_no' =>
                    $result->version_no,

                'is_current_version' =>
                    $result
                        ->is_current_version,
            ],

            'document_update' => [
                'status' =>
                    'ok',

                'document_record_id' =>
                    $result->id,

                'document_status' =>
                    $result
                        ->status
                        ->value,

                'revision_no' =>
                    $result->revision_no,
            ],
        };

    file_put_contents(
        $resultPath,
        json_encode(
            $payload,
            JSON_PRETTY_PRINT
        )
    );
} catch (
    ValidationException $exception
) {
    /*
     * Expected loser outcome untuk race tertentu.
     *
     * Contoh:
     * - second concurrent revision;
     * - approval setelah evidence berubah.
     */
    file_put_contents(
        $resultPath,
        json_encode(
            [
                'status' =>
                    'validation',

                'errors' =>
                    $exception->errors(),
            ],
            JSON_PRETTY_PRINT
        )
    );
} catch (Throwable $exception) {
    /*
     * Deadlock mentah, SQL exception,
     * lock timeout, bootstrap error dan exception
     * lain bukan acceptable concurrency outcome.
     */
    file_put_contents(
        $resultPath,
        json_encode(
            [
                'status' =>
                    'error',

                'class' =>
                    $exception::class,

                'message' =>
                    $exception
                        ->getMessage(),
            ],
            JSON_PRETTY_PRINT
        )
    );
}

CarbonImmutable::setTestNow();

exit(0);