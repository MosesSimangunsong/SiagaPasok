<?php

declare(strict_types=1);

use App\Enums\SupplyConfidence;
use App\Models\FallbackOffer;
use App\Models\User;
use App\Services\Commitment\ConfidenceService;
use App\Services\Fallback\FallbackOfferService;
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

FallbackConcurrencyDatabase
    ::configure();

$operation =
    $argv[1] ?? '';

$actorId =
    isset($argv[2])
        ? (int) $argv[2]
        : 0;

$offerId =
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
    || $offerId <= 0
    || $barrierDirectory === ''
    || $workerId === ''
    || $testNow === ''
) {
    fwrite(
        STDERR,
        'Invalid concurrency worker arguments.'
        .PHP_EOL
    );

    exit(2);
}

CarbonImmutable::setTestNow(
    CarbonImmutable::parse(
        $testNow
    )
);

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

$actor =
    User::query()
        ->findOrFail(
            $actorId
        );

$offer =
    FallbackOffer::query()
        ->findOrFail(
            $offerId
        );

$sourceCommitment =
    null;

if ($operation === 'downgrade') {
    $source =
        $offer
            ->sources()
            ->with(
                'supplyCommitment'
            )
            ->orderBy(
                'supply_commitment_id'
            )
            ->firstOrFail();

    $sourceCommitment =
        $source
            ->supplyCommitment;
}

/*
 * Worker sudah:
 * - bootstrap Laravel;
 * - terkoneksi ke PostgreSQL test DB;
 * - resolve actor;
 * - resolve Offer.
 *
 * Baru setelah itu worker menyatakan READY.
 */
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
    $fallbackService =
        app(
            FallbackOfferService::class
        );

    $result =
        match ($operation) {
            'approve' =>
                $fallbackService
                    ->approveForAvailability(
                        $actor,
                        $offer
                    ),

            'accept' =>
                $fallbackService
                    ->accept(
                        $actor,
                        $offer,
                        selfAcceptVolume(
                            $operationValue
                        )
                    ),

            'expire' =>
                $fallbackService
                    ->expire(
                        $offer,
                        CarbonImmutable::now()
                    ),

            'downgrade' =>
                app(
                    ConfidenceService::class
                )->downgrade(
                    $actor,
                    $sourceCommitment,
                    SupplyConfidence::YELLOW,
                    'CONCURRENCY_SOURCE_DEGRADE',
                    'Source diturunkan menjadi YELLOW saat '
                    .'Fallback Offer approval berlangsung.'
                ),
            
              'reject_requester' =>
    $fallbackService
        ->rejectByRequesterManager(
            $actor,
            $offer,
            $operationValue
                ?? 'Concurrent requester rejection.'
        ),

'withdraw' =>
    $fallbackService
        ->withdraw(
            $actor,
            $offer,
            $operationValue
                ?? 'Concurrent supplier withdrawal.'
        ),

            default =>
                throw new RuntimeException(
                    'Unknown worker operation: '
                    .$operation
                ),
        };

    $payload =
        match ($operation) {
            'downgrade' => [
                'status' =>
                    'ok',

                'commitment_id' =>
                    $result->id,

                'current_confidence' =>
                    $result
                        ->current_confidence
                        ?->value,
            ],

            default => [
                'status' =>
                    'ok',

                'offer_id' =>
                    $result->id,

                'offer_status' =>
                    $result
                        ->status
                        ->value,

                'accepted_volume' =>
                    (string)
                    $result
                        ->accepted_volume,
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
     * Business validation adalah expected loser
     * outcome untuk sebagian race scenario.
     *
     * Contoh Double Accept:
     * worker kedua membaca remaining Request
     * setelah worker pertama commit.
     */
    file_put_contents(
        $resultPath,
        json_encode(
            [
                'status' =>
                    'validation',

                'errors' =>
                    $exception
                        ->errors(),
            ],
            JSON_PRETTY_PRINT
        )
    );
} catch (Throwable $exception) {
    /*
     * Raw deadlock, lock timeout, SQL exception,
     * bootstrap failure, dan exception lain
     * bukan expected business result.
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

function selfAcceptVolume(
    ?string $value
): string {
    if (
        $value === null
        || trim($value) === ''
    ) {
        throw new RuntimeException(
            'Accept worker membutuhkan accepted volume.'
        );
    }

    return trim(
        $value
    );
}