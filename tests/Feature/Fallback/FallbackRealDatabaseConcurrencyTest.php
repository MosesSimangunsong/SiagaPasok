<?php

namespace Tests\Feature\Fallback;

use App\Enums\CommitmentApprovalStatus;
use App\Enums\CommitmentLifecycleStatus;
use App\Enums\FallbackOfferStatus;
use App\Enums\NetworkRole;
use App\Enums\OrganizationType;
use App\Enums\SupplyConfidence;
use App\Enums\UserRole;
use App\Models\Commodity;
use App\Models\CommitmentVersion;
use App\Models\FallbackOffer;
use App\Models\FallbackOfferSource;
use App\Models\Organization;
use App\Models\Producer;
use App\Models\SupplyCommitment;
use App\Models\SupplyNetworkLink;
use App\Models\Unit;
use App\Models\User;
use App\Services\Fallback\FallbackOfferService;
use App\Services\Fallback\FallbackRequestService;
use App\Services\Forecast\DemandForecastService;
use App\Support\FixedScaleDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\FallbackConcurrencyDatabase;
use Tests\TestCase;

class FallbackRealDatabaseConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (
            ! FallbackConcurrencyDatabase
                ::isConfigured()
        ) {
            $this->markTestSkipped(
    'Real PostgreSQL concurrency gate belum diaktifkan. '
    .'Set SIAGAPASOK_REAL_DB_CONCURRENCY=true '
    .'untuk menjalankan gate ini.'
);
        }

        FallbackConcurrencyDatabase
            ::configure();

        /*
         * Dedicated database only.
         *
         * Safety check nama database dilakukan
         * di FallbackConcurrencyDatabase.
         */
        Artisan::call(
            'migrate:fresh',
            [
                '--database' =>
                    FallbackConcurrencyDatabase
                        ::CONNECTION,

                '--force' =>
                    true,
            ]
        );

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse(
                '2026-08-10 10:00:00'
            )
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    

    public function test_competing_manager_approvals_cannot_double_reserve_same_source_capacity(): void
    {
        $context =
            $this->createOpenRequestContext();

        $source =
    $this->createEligibleSource(
        $context,
        'RESERVE-SOURCE',
        '200.000000'
    );

        /*
         * Dua Offer masing-masing 160.
         *
         * DRAFT/PENDING belum reserve, sehingga
         * keduanya valid sebelum approval.
         *
         * Source hanya mempunyai current minimum
         * 200. Hanya satu Offer 160 yang boleh
         * menjadi AVAILABLE.
         */
        $offerA =
            $this->createPendingOffer(
                $context,
                $source,
                '160.000000',
                'Race Offer A'
            );

        $offerB =
            $this->createPendingOffer(
                $context,
                $source,
                '160.000000',
                'Race Offer B'
            );

        $results =
            $this->runConcurrentApprovals(
                $context[
                    'networkManager'
                ],
                [
                    $offerA,
                    $offerB,
                ]
            );

        $statuses =
            collect(
                $results
            )
                ->pluck(
                    'status'
                )
                ->sort()
                ->values()
                ->all();

        /*
         * Expected:
         *
         * satu transaction berhasil;
         * satu transaction kalah setelah
         * melihat current exposure terbaru.
         *
         * DB deadlock / lock timeout mentah
         * bukan hasil yang diterima.
         */
        $this->assertSame(
            [
                'ok',
                'validation',
            ],
            $statuses,
            'Race harus menghasilkan tepat satu '
            .'approval sukses dan satu business '
            .'validation failure.'
        );

        $offerA->refresh();
        $offerB->refresh();

        $offerStates = [
            $offerA->status,
            $offerB->status,
        ];

        $availableCount =
            collect(
                $offerStates
            )
                ->filter(
                    fn (
                        FallbackOfferStatus $status
                    ): bool =>
                        $status
                        === FallbackOfferStatus
                            ::AVAILABLE
                )
                ->count();

        $pendingCount =
            collect(
                $offerStates
            )
                ->filter(
                    fn (
                        FallbackOfferStatus $status
                    ): bool =>
                        $status
                        === FallbackOfferStatus
                            ::PENDING_APPROVAL
                )
                ->count();

        $this->assertSame(
            1,
            $availableCount
        );

        $this->assertSame(
            1,
            $pendingCount
        );

        $sourceRows =
            FallbackOfferSource::query()
                ->whereIn(
                    'fallback_offer_id',
                    [
                        $offerA->id,
                        $offerB->id,
                    ]
                )
                ->orderBy('id')
                ->get();

        $totalReserved =
            FixedScaleDecimal::zero();

        foreach ($sourceRows as $row) {
            $totalReserved =
                $totalReserved->add(
                    FixedScaleDecimal::from(
                        (string)
                        $row
                            ->reserved_volume
                    )
                );
        }

        /*
         * Critical invariant:
         *
         * bukan 320.
         */
        $this->assertSame(
            '160.000000',
            $totalReserved
                ->toString()
        );

        $this->assertTrue(
            $totalReserved->compare(
                FixedScaleDecimal::from(
                    '200.000000'
                )
            ) <= 0
        );
    }

public function test_accept_and_expiry_race_cannot_both_mutate_same_available_offer(): void
{
    $context =
        $this->createOpenRequestContext();

    $source =
        $this->createEligibleSource(
            $context,
            'EXPIRY-RACE-SOURCE',
            '100.000000'
        );

    $offer =
        $this->createAvailableOffer(
            $context,
            $source,
            '100.000000',
            'Accept versus expiry race'
        );

    /*
     * createPendingOffer() fixture memakai:
     *
     * expires_at = 2026-08-19 12:00:00
     */
    $this->assertSame(
        FallbackOfferStatus::AVAILABLE,
        $offer->status
    );

    $sourceRow =
        FallbackOfferSource::query()
            ->where(
                'fallback_offer_id',
                $offer->id
            )
            ->firstOrFail();

    $this->assertSame(
        '100.000000',
        (string)
        $sourceRow->reserved_volume
    );

    $this->assertSame(
        '0.000000',
        (string)
        $sourceRow->allocated_volume
    );

    $this->assertSame(
        '0.000000',
        (string)
        $sourceRow->released_volume
    );

    /*
     * Worker Accept mempunyai logical evaluation
     * time sesaat SEBELUM expiry.
     *
     * Worker Expire mempunyai logical evaluation
     * time tepat PADA expiry instant.
     *
     * Jadi keduanya valid ketika masing-masing
     * mencoba transition, tetapi PostgreSQL row
     * lock pada Offer harus menserialisasi hasil.
     */
    $results =
        $this->runConcurrentAcceptAndExpire(
            $context['manager'],
            $offer,
            '100.000000',
            '2026-08-19 11:59:59.500000',
            '2026-08-19 12:00:00.000000'
        );

    $statuses =
        collect(
            $results
        )
            ->pluck('status')
            ->sort()
            ->values()
            ->all();

    /*
     * Exactly one transition menang.
     *
     * Loser melihat state hasil winner dan
     * berhenti sebagai business validation,
     * bukan SQL/deadlock failure.
     */
    $this->assertSame(
        [
            'ok',
            'validation',
        ],
        $statuses
    );

    $offer->refresh();

    $sourceRow->refresh();

    $this->assertContains(
        $offer->status,
        [
            FallbackOfferStatus::ACCEPTED,
            FallbackOfferStatus::EXPIRED,
        ]
    );

    $this->assertNotSame(
        FallbackOfferStatus::AVAILABLE,
        $offer->status
    );

    $reserved =
        FixedScaleDecimal::from(
            (string)
            $sourceRow->reserved_volume
        );

    $allocated =
        FixedScaleDecimal::from(
            (string)
            $sourceRow->allocated_volume
        );

    $released =
        FixedScaleDecimal::from(
            (string)
            $sourceRow->released_volume
        );

    /*
     * Reservation awal tidak boleh berubah.
     */
    $this->assertSame(
        '100.000000',
        $reserved->toString()
    );

    /*
     * Semua reserved capacity harus berakhir
     * tepat sebagai allocation ATAU release.
     *
     * Tidak boleh:
     * - double allocation;
     * - double release;
     * - capacity leak;
     * - allocated + released > reserved.
     */
    $consumedReserve =
        $allocated->add(
            $released
        );

    $this->assertSame(
        '100.000000',
        $consumedReserve
            ->toString()
    );

    if (
        $offer->status
        === FallbackOfferStatus::ACCEPTED
    ) {
        /*
         * Accept menang.
         *
         * Expire kemudian membaca ACCEPTED dan
         * wajib gagal karena hanya AVAILABLE
         * dapat di-expire.
         */
        $this->assertSame(
            '100.000000',
            (string)
            $offer->accepted_volume
        );

        $this->assertSame(
            '100.000000',
            $allocated->toString()
        );

        $this->assertSame(
            '0.000000',
            $released->toString()
        );

        $request =
            $context['request']
                ->fresh();

        $this->assertSame(
            '100.000000',
            app(
                FallbackRequestService::class
            )->calculateAcceptedVolume(
                $request
            )
        );

        $this->assertSame(
            '50.000000',
            app(
                FallbackRequestService::class
            )->calculateRemainingVolume(
                $request
            )
        );

        $this->assertSame(
            \App\Enums\FallbackRequestStatus::OPEN,
            $request->status
        );
    } else {
        /*
         * Expire menang.
         *
         * Reserve dilepas penuh.
         * Accept kemudian wajib melihat Offer
         * non-AVAILABLE dan gagal.
         */
        $this->assertSame(
            FallbackOfferStatus::EXPIRED,
            $offer->status
        );

        $this->assertSame(
            '0.000000',
            (string)
            $offer->accepted_volume
        );

        $this->assertSame(
            '0.000000',
            $allocated->toString()
        );

        $this->assertSame(
            '100.000000',
            $released->toString()
        );

        $request =
            $context['request']
                ->fresh();

        $this->assertSame(
            '0.000000',
            app(
                FallbackRequestService::class
            )->calculateAcceptedVolume(
                $request
            )
        );

        $this->assertSame(
            '150.000000',
            app(
                FallbackRequestService::class
            )->calculateRemainingVolume(
                $request
            )
        );

        $this->assertSame(
            \App\Enums\FallbackRequestStatus::OPEN,
            $request->status
        );
    }
}


public function test_source_downgrade_and_offer_approval_are_serialized_on_same_commitment(): void
{
    $context =
        $this->createOpenRequestContext();

    $source =
        $this->createEligibleSource(
            $context,
            'DEGRADE-RACE-SOURCE',
            '200.000000'
        );

    $offer =
        $this->createPendingOffer(
            $context,
            $source,
            '160.000000',
            'Source degradation versus approval race'
        );

    $this->assertSame(
        SupplyConfidence::GREEN,
        $source->current_confidence
    );

    $this->assertSame(
        FallbackOfferStatus
            ::PENDING_APPROVAL,
        $offer->status
    );

    $sourceRow =
        FallbackOfferSource::query()
            ->where(
                'fallback_offer_id',
                $offer->id
            )
            ->firstOrFail();

    /*
     * PENDING_APPROVAL belum mempunyai reserve.
     */
    $this->assertSame(
        '0.000000',
        (string)
        $sourceRow->reserved_volume
    );

    $results =
        $this->runConcurrentApprovalAndDowngrade(
            manager:
                $context[
                    'networkManager'
                ],
            operator:
                $context[
                    'networkOperator'
                ],
            offer:
                $offer
        );

    /*
     * Downgrade adalah worsening-reality event.
     * Ia tidak boleh hilang hanya karena Offer
     * approval sedang berlangsung.
     */
    $this->assertSame(
        'ok',
        $results[
            'downgrade-worker'
        ]['status']
    );

    /*
     * Approval mempunyai dua legal outcomes:
     *
     * 1. ok:
     *    approval memperoleh Commitment lock
     *    lebih dulu.
     *
     * 2. validation:
     *    downgrade memperoleh/commit lock lebih
     *    dulu sehingga capacity sudah tidak
     *    eligible ketika approval merecheck.
     */
    $this->assertContains(
        $results[
            'approve-worker'
        ]['status'],
        [
            'ok',
            'validation',
        ]
    );

    $source->refresh();
    $offer->refresh();
    $sourceRow->refresh();

    /*
     * Final biological truth wajib YELLOW.
     */
    $this->assertSame(
        SupplyConfidence::YELLOW,
        $source->current_confidence
    );

    $this->assertSame(
        '0.000000',
        (string)
        $sourceRow->allocated_volume
    );

    $this->assertSame(
        '0.000000',
        (string)
        $sourceRow->released_volume
    );

    if (
        $results[
            'approve-worker'
        ]['status']
        === 'validation'
    ) {
        /*
         * Downgrade menang lebih dahulu.
         *
         * Approval wajib melihat YELLOW dan tidak
         * boleh membentuk reserve berdasarkan
         * snapshot GREEN lama.
         */
        $this->assertSame(
            FallbackOfferStatus
                ::PENDING_APPROVAL,
            $offer->status
        );

        $this->assertSame(
            '0.000000',
            (string)
            $sourceRow->reserved_volume
        );

        return;
    }

    /*
     * Approval menang lebih dahulu dan reserve
     * dibuat ketika source masih GREEN.
     *
     * Downgrade setelah itu tetap harus diizinkan:
     * worsening reality tidak boleh diblok hanya
     * karena reserve sudah ada.
     */
    $this->assertSame(
        FallbackOfferStatus::AVAILABLE,
        $offer->status
    );

    $this->assertSame(
        '160.000000',
        (string)
        $sourceRow->reserved_volume
    );

    /*
     * Walaupun Offer historis masih AVAILABLE,
     * source yang sekarang YELLOW tidak lagi
     * eligible untuk Acceptance.
     */
    try {
        app(
            FallbackOfferService::class
        )->accept(
            $context['manager'],
            $offer,
            '150.000000'
        );

        $this->fail(
            'Offer AVAILABLE dengan underlying '
            .'source YELLOW berhasil di-Accept.'
        );
    } catch (
        \Illuminate\Validation\ValidationException
    ) {
        /*
         * Expected:
         * supportsCurrentExposure/current source
         * eligibility menolak Acceptance.
         */
    }

    $offer->refresh();
    $sourceRow->refresh();

    $this->assertSame(
        FallbackOfferStatus::AVAILABLE,
        $offer->status
    );

    $this->assertSame(
        '0.000000',
        (string)
        $offer->accepted_volume
    );

    $this->assertSame(
        '0.000000',
        (string)
        $sourceRow->allocated_volume
    );
}


public function test_concurrent_reject_and_withdraw_release_reserve_exactly_once(): void
{
    $context =
        $this->createOpenRequestContext();

    $source =
        $this->createEligibleSource(
            $context,
            'RELEASE-RACE-SOURCE',
            '100.000000'
        );

    $offer =
        $this->createAvailableOffer(
            $context,
            $source,
            '100.000000',
            'Concurrent reserve release race'
        );

    $sourceRow =
        FallbackOfferSource::query()
            ->where(
                'fallback_offer_id',
                $offer->id
            )
            ->firstOrFail();

    /*
     * Preconditions.
     */
    $this->assertSame(
        FallbackOfferStatus::AVAILABLE,
        $offer->status
    );

    $this->assertSame(
        '100.000000',
        (string)
        $sourceRow->reserved_volume
    );

    $this->assertSame(
        '0.000000',
        (string)
        $sourceRow->allocated_volume
    );

    $this->assertSame(
        '0.000000',
        (string)
        $sourceRow->released_volume
    );

    /*
     * Dua valid AVAILABLE transitions yang
     * sama-sama mencoba release open reserve:
     *
     * requester Manager -> REJECTED
     * supplier Manager  -> WITHDRAWN
     */
    $results =
        $this->runConcurrentRejectAndWithdraw(
            requesterManager:
                $context['manager'],
            supplierManager:
                $context['networkManager'],
            offer:
                $offer
        );

    $statuses =
        collect(
            $results
        )
            ->pluck('status')
            ->sort()
            ->values()
            ->all();

    /*
     * Exactly one transition harus commit.
     *
     * Worker kedua membaca terminal state hasil
     * worker pertama dan berhenti lewat business
     * validation.
     */
    $this->assertSame(
        [
            'ok',
            'validation',
        ],
        $statuses,
        'Concurrent release harus menghasilkan '
        .'satu transition sukses dan satu '
        .'business validation failure.'
    );

    $offer->refresh();
    $sourceRow->refresh();

    $this->assertContains(
        $offer->status,
        [
            FallbackOfferStatus::REJECTED,
            FallbackOfferStatus::WITHDRAWN,
        ]
    );

    /*
     * Terminal berarti tidak ada transition
     * release kedua yang boleh mengubah ledger.
     */
    $this->assertTrue(
        $offer->isTerminal()
    );

    $reserved =
        FixedScaleDecimal::from(
            (string)
            $sourceRow->reserved_volume
        );

    $allocated =
        FixedScaleDecimal::from(
            (string)
            $sourceRow->allocated_volume
        );

    $released =
        FixedScaleDecimal::from(
            (string)
            $sourceRow->released_volume
        );

    /*
     * Lifetime reservation tidak dihapus.
     */
    $this->assertSame(
        '100.000000',
        $reserved->toString()
    );

    /*
     * Tidak ada Acceptance pada race ini.
     */
    $this->assertSame(
        '0.000000',
        $allocated->toString()
    );

    /*
     * Critical invariant:
     *
     * reserve dilepas tepat sekali.
     * Tidak 0, tidak 200.
     */
    $this->assertSame(
        '100.000000',
        $released->toString()
    );

    /*
     * Open reserve:
     *
     * reserved - allocated - released = 0.
     */
    $used =
        $allocated->add(
            $released
        );

    $this->assertSame(
        $reserved->toString(),
        $used->toString()
    );

    /*
     * Request tidak mengalami accepted progress
     * karena kedua competing actions sama-sama
     * release-oriented.
     */
    $request =
        $context['request']
            ->fresh();

    $this->assertSame(
        '0.000000',
        app(
            FallbackRequestService::class
        )->calculateAcceptedVolume(
            $request
        )
    );

    $this->assertSame(
        '150.000000',
        app(
            FallbackRequestService::class
        )->calculateRemainingVolume(
            $request
        )
    );

    $this->assertSame(
        \App\Enums\FallbackRequestStatus::OPEN,
        $request->status
    );

    /*
     * Re-read dari database memastikan tidak ada
     * delayed second mutation setelah child
     * process selesai.
     */
    $sourceRow =
        FallbackOfferSource::query()
            ->findOrFail(
                $sourceRow->id
            );

    $this->assertSame(
        '100.000000',
        (string)
        $sourceRow->released_volume
    );
}


    public function test_competing_accepts_cannot_exceed_same_request_remaining_volume(): void
{
    $context =
        $this->createOpenRequestContext();

    /*
     * Dua source berbeda.
     *
     * Tujuan race ini bukan menguji source lock
     * lagi, tetapi Request lock sebagai
     * serialization point untuk competing Accept.
     */
    $sourceA =
        $this->createEligibleSource(
            $context,
            'ACCEPT-SOURCE-A',
            '100.000000'
        );

    $sourceB =
        $this->createEligibleSource(
            $context,
            'ACCEPT-SOURCE-B',
            '100.000000'
        );

    /*
     * Request = 150.
     *
     * Masing-masing Offer = 100 dan mempunyai
     * independent valid reserve.
     */
    $offerA =
        $this->createAvailableOffer(
            $context,
            $sourceA,
            '100.000000',
            'Accept Race Offer A'
        );

    $offerB =
        $this->createAvailableOffer(
            $context,
            $sourceB,
            '100.000000',
            'Accept Race Offer B'
        );

    /*
     * Precondition:
     * keduanya benar-benar AVAILABLE.
     */
    $this->assertSame(
        FallbackOfferStatus::AVAILABLE,
        $offerA->status
    );

    $this->assertSame(
        FallbackOfferStatus::AVAILABLE,
        $offerB->status
    );

    /*
     * Kedua process mencoba Accept 100
     * pada Request remaining 150.
     *
     * Keduanya tidak boleh berhasil karena:
     *
     * 100 + 100 > 150.
     */
    $results =
        $this->runConcurrentAccepts(
            $context['manager'],
            [
                $offerA,
                $offerB,
            ],
            '100.000000'
        );

    $statuses =
        collect(
            $results
        )
            ->pluck(
                'status'
            )
            ->sort()
            ->values()
            ->all();

    $this->assertSame(
        [
            'ok',
            'validation',
        ],
        $statuses,
        'Double Accept race harus menghasilkan '
        .'satu successful acceptance dan satu '
        .'business validation failure.'
    );

    /*
     * Loser harus gagal karena remaining Request
     * sudah turun menjadi 50.
     */
    $validationResult =
        collect(
            $results
        )
            ->firstWhere(
                'status',
                'validation'
            );

    $this->assertIsArray(
        $validationResult
    );

    $this->assertArrayHasKey(
        'accepted_volume',
        $validationResult[
            'errors'
        ] ?? []
    );

    $offerA->refresh();
    $offerB->refresh();

    $acceptedOffers =
        collect([
            $offerA,
            $offerB,
        ])->filter(
            fn (
                FallbackOffer $offer
            ): bool =>
                $offer->status
                === FallbackOfferStatus::ACCEPTED
        );

    $availableOffers =
        collect([
            $offerA,
            $offerB,
        ])->filter(
            fn (
                FallbackOffer $offer
            ): bool =>
                $offer->status
                === FallbackOfferStatus::AVAILABLE
        );

    /*
     * Exactly one winner.
     */
    $this->assertCount(
        1,
        $acceptedOffers
    );

    /*
     * Offer yang kalah tetap AVAILABLE.
     *
     * Tidak boleh berubah menjadi state parsial/
     * invalid sebagai efek samping failed race.
     */
    $this->assertCount(
        1,
        $availableOffers
    );

    $totalAccepted =
        FixedScaleDecimal::zero();

    foreach (
        [
            $offerA,
            $offerB,
        ] as $offer
    ) {
        $totalAccepted =
            $totalAccepted->add(
                FixedScaleDecimal::from(
                    (string)
                    $offer
                        ->accepted_volume
                )
            );
    }

    /*
     * Critical invariant:
     *
     * bukan 200.
     */
    $this->assertSame(
        '100.000000',
        $totalAccepted
            ->toString()
    );

    $request =
        $context['request']
            ->fresh();

    /*
     * Request 150 - accepted 100 = remaining 50.
     */
    $acceptedRequestVolume =
        app(
            FallbackRequestService::class
        )->calculateAcceptedVolume(
            $request
        );

    $remainingRequestVolume =
        app(
            FallbackRequestService::class
        )->calculateRemainingVolume(
            $request
        );

    $this->assertSame(
        '100.000000',
        $acceptedRequestVolume
    );

    $this->assertSame(
        '50.000000',
        $remainingRequestVolume
    );

    /*
     * Partial fulfilment tidak membuat Request
     * terminal.
     */
    $this->assertSame(
        \App\Enums\FallbackRequestStatus::OPEN,
        $request->status
    );

    /*
     * Pastikan allocation ledger juga hanya
     * menghasilkan total 100.
     */
    $sourceRows =
        FallbackOfferSource::query()
            ->whereIn(
                'fallback_offer_id',
                [
                    $offerA->id,
                    $offerB->id,
                ]
            )
            ->orderBy('id')
            ->get();

    $totalAllocated =
        FixedScaleDecimal::zero();

    foreach ($sourceRows as $sourceRow) {
        $totalAllocated =
            $totalAllocated->add(
                FixedScaleDecimal::from(
                    (string)
                    $sourceRow
                        ->allocated_volume
                )
            );
    }

    $this->assertSame(
        '100.000000',
        $totalAllocated
            ->toString()
    );

    /*
     * Winner:
     * reserve 100, allocate 100.
     *
     * Loser:
     * tetap AVAILABLE dengan reserve 100,
     * allocate 0.
     */
    $allocatedRows =
        $sourceRows->filter(
            fn (
                FallbackOfferSource $source
            ): bool =>
                ! FixedScaleDecimal::from(
                    (string)
                    $source
                        ->allocated_volume
                )->isZero()
        );

    $this->assertCount(
        1,
        $allocatedRows
    );
}


private function runConcurrentAcceptAndExpire(
    User $manager,
    FallbackOffer $offer,
    string $acceptedVolume,
    string $acceptTime,
    string $expiryTime,
): array {
    $barrierDirectory =
        storage_path(
            'framework/testing/'
            .'fallback-expiry-concurrency-'
            .Str::uuid()
        );

    File::ensureDirectoryExists(
        $barrierDirectory
    );

    $processes = [];

    try {
        /*
         * Requester Manager Accept.
         */
        $acceptProcess =
            new Process([
                PHP_BINARY,

                base_path(
                    'tests/Support/'
                    .'FallbackConcurrencyWorker.php'
                ),

                'accept',

                (string)
                $manager->id,

                (string)
                $offer->id,

                $barrierDirectory,

                'accept-worker',

                $acceptTime,

                $acceptedVolume,
            ]);

        $acceptProcess->setTimeout(
            30
        );

        /*
         * System expiry.
         *
         * Worker infrastructure masih menerima
         * actor ID untuk uniform argument contract,
         * tetapi FallbackOfferService::expire()
         * tidak menggunakan actor.
         */
        $expireProcess =
            new Process([
                PHP_BINARY,

                base_path(
                    'tests/Support/'
                    .'FallbackConcurrencyWorker.php'
                ),

                'expire',

                (string)
                $manager->id,

                (string)
                $offer->id,

                $barrierDirectory,

                'expire-worker',

                $expiryTime,
            ]);

        $expireProcess->setTimeout(
            30
        );

        $processes = [
            'accept-worker' =>
                $acceptProcess,

            'expire-worker' =>
                $expireProcess,
        ];

        foreach (
            $processes
            as $process
        ) {
            $process->start();
        }

        $this->waitUntilWorkersReady(
            $barrierDirectory,
            array_keys(
                $processes
            )
        );

        /*
         * Release keduanya dari barrier yang sama.
         */
        file_put_contents(
            $barrierDirectory
            .DIRECTORY_SEPARATOR
            .'go',
            'go'
        );

        foreach (
            $processes
            as $process
        ) {
            $process->wait();
        }

        $results = [];

        foreach (
            $processes
            as $workerId => $process
        ) {
            $resultPath =
                $barrierDirectory
                .DIRECTORY_SEPARATOR
                .$workerId
                .'.result.json';

            $this->assertFileExists(
                $resultPath,
                "Worker {$workerId} tidak menghasilkan result file.\n"
                ."STDOUT:\n"
                .$process->getOutput()
                ."\nSTDERR:\n"
                .$process->getErrorOutput()
            );

            $decoded =
                json_decode(
                    (string)
                    file_get_contents(
                        $resultPath
                    ),
                    true
                );

            $this->assertIsArray(
                $decoded
            );

            /*
             * Raw PostgreSQL failure bukan
             * acceptable race outcome.
             */
            $this->assertNotSame(
                'error',
                $decoded[
                    'status'
                ] ?? null,
                'Worker database error: '
                .json_encode(
                    $decoded
                )
            );

            $this->assertNotSame(
                'worker_timeout',
                $decoded[
                    'status'
                ] ?? null,
                'Concurrency barrier timeout.'
            );

            $results[] =
                $decoded;
        }

        return $results;
    } finally {
        foreach (
            $processes
            as $process
        ) {
            if (
                $process->isRunning()
            ) {
                $process->stop(
                    1
                );
            }
        }

        File::deleteDirectory(
            $barrierDirectory
        );
    }
}


private function runConcurrentApprovalAndDowngrade(
    User $manager,
    User $operator,
    FallbackOffer $offer,
): array {
    $barrierDirectory =
        storage_path(
            'framework/testing/'
            .'fallback-source-degrade-'
            .Str::uuid()
        );

    File::ensureDirectoryExists(
        $barrierDirectory
    );

    $processes = [];

    try {
        $approveProcess =
            new Process([
                PHP_BINARY,

                base_path(
                    'tests/Support/'
                    .'FallbackConcurrencyWorker.php'
                ),

                'approve',

                (string)
                $manager->id,

                (string)
                $offer->id,

                $barrierDirectory,

                'approve-worker',

                CarbonImmutable::now()
                    ->format(
                        'Y-m-d H:i:s'
                    ),
            ]);

        $downgradeProcess =
            new Process([
                PHP_BINARY,

                base_path(
                    'tests/Support/'
                    .'FallbackConcurrencyWorker.php'
                ),

                'downgrade',

                (string)
                $operator->id,

                (string)
                $offer->id,

                $barrierDirectory,

                'downgrade-worker',

                CarbonImmutable::now()
                    ->format(
                        'Y-m-d H:i:s'
                    ),
            ]);

        $approveProcess->setTimeout(
            30
        );

        $downgradeProcess->setTimeout(
            30
        );

        $processes = [
            'approve-worker' =>
                $approveProcess,

            'downgrade-worker' =>
                $downgradeProcess,
        ];

        foreach (
            $processes
            as $process
        ) {
            $process->start();
        }

        $this->waitUntilWorkersReady(
            $barrierDirectory,
            array_keys(
                $processes
            )
        );

        file_put_contents(
            $barrierDirectory
            .DIRECTORY_SEPARATOR
            .'go',
            'go'
        );

        foreach (
            $processes
            as $process
        ) {
            $process->wait();
        }

        $results = [];

        foreach (
            $processes
            as $workerId => $process
        ) {
            $resultPath =
                $barrierDirectory
                .DIRECTORY_SEPARATOR
                .$workerId
                .'.result.json';

            $this->assertFileExists(
                $resultPath,
                "Worker {$workerId} tidak menghasilkan result file.\n"
                ."STDOUT:\n"
                .$process->getOutput()
                ."\nSTDERR:\n"
                .$process->getErrorOutput()
            );

            $decoded =
                json_decode(
                    (string)
                    file_get_contents(
                        $resultPath
                    ),
                    true
                );

            $this->assertIsArray(
                $decoded
            );

            $this->assertNotSame(
                'error',
                $decoded[
                    'status'
                ] ?? null,
                'Worker database error: '
                .json_encode(
                    $decoded
                )
            );

            $this->assertNotSame(
                'worker_timeout',
                $decoded[
                    'status'
                ] ?? null,
                'Concurrency barrier timeout.'
            );

            $results[
                $workerId
            ] =
                $decoded;
        }

        return $results;
    } finally {
        foreach (
            $processes
            as $process
        ) {
            if (
                $process->isRunning()
            ) {
                $process->stop(
                    1
                );
            }
        }

        File::deleteDirectory(
            $barrierDirectory
        );
    }
}

private function runConcurrentAccepts(
    User $manager,
    array $offers,
    string $acceptedVolume,
): array {
    $barrierDirectory =
        storage_path(
            'framework/testing/'
            .'fallback-accept-concurrency-'
            .Str::uuid()
        );

    File::ensureDirectoryExists(
        $barrierDirectory
    );

    $processes = [];

    try {
        foreach (
            array_values(
                $offers
            )
            as $index => $offer
        ) {
            $workerId =
                'worker-'
                .($index + 1);

            $process =
                new Process([
                    PHP_BINARY,

                    base_path(
                        'tests/Support/'
                        .'FallbackConcurrencyWorker.php'
                    ),

                    'accept',

                    (string)
                    $manager->id,

                    (string)
                    $offer->id,

                    $barrierDirectory,

                    $workerId,

                    CarbonImmutable::now()
                        ->format(
                            'Y-m-d H:i:s'
                        ),

                    $acceptedVolume,
                ]);

            $process->setTimeout(
                30
            );

            $process->start();

            $processes[
                $workerId
            ] =
                $process;
        }

        $this->waitUntilWorkersReady(
            $barrierDirectory,
            array_keys(
                $processes
            )
        );

        file_put_contents(
            $barrierDirectory
            .DIRECTORY_SEPARATOR
            .'go',
            'go'
        );

        foreach (
            $processes
            as $process
        ) {
            $process->wait();
        }

        $results = [];

        foreach (
            $processes
            as $workerId => $process
        ) {
            $resultPath =
                $barrierDirectory
                .DIRECTORY_SEPARATOR
                .$workerId
                .'.result.json';

            $this->assertFileExists(
                $resultPath,
                "Worker {$workerId} tidak menghasilkan result file.\n"
                ."STDOUT:\n"
                .$process->getOutput()
                ."\nSTDERR:\n"
                .$process->getErrorOutput()
            );

            $decoded =
                json_decode(
                    (string)
                    file_get_contents(
                        $resultPath
                    ),
                    true
                );

            $this->assertIsArray(
                $decoded
            );

            /*
             * PostgreSQL deadlock/lock timeout/
             * SQL error bukan accepted outcome.
             */
            $this->assertNotSame(
                'error',
                $decoded[
                    'status'
                ] ?? null,
                'Worker database error: '
                .json_encode(
                    $decoded
                )
            );

            $this->assertNotSame(
                'worker_timeout',
                $decoded[
                    'status'
                ] ?? null,
                'Concurrency barrier timeout.'
            );

            $results[] =
                $decoded;
        }

        return $results;
    } finally {
        foreach (
            $processes
            as $process
        ) {
            if (
                $process->isRunning()
            ) {
                $process->stop(
                    1
                );
            }
        }

        File::deleteDirectory(
            $barrierDirectory
        );
    }
}


private function runConcurrentRejectAndWithdraw(
    User $requesterManager,
    User $supplierManager,
    FallbackOffer $offer,
): array {
    $barrierDirectory =
        storage_path(
            'framework/testing/'
            .'fallback-release-concurrency-'
            .Str::uuid()
        );

    File::ensureDirectoryExists(
        $barrierDirectory
    );

    $processes = [];

    try {
        $rejectProcess =
            new Process([
                PHP_BINARY,

                base_path(
                    'tests/Support/'
                    .'FallbackConcurrencyWorker.php'
                ),

                'reject_requester',

                (string)
                $requesterManager->id,

                (string)
                $offer->id,

                $barrierDirectory,

                'reject-worker',

                CarbonImmutable::now()
                    ->format(
                        'Y-m-d H:i:s'
                    ),

                'Concurrent requester rejection.',
            ]);

        $withdrawProcess =
            new Process([
                PHP_BINARY,

                base_path(
                    'tests/Support/'
                    .'FallbackConcurrencyWorker.php'
                ),

                'withdraw',

                (string)
                $supplierManager->id,

                (string)
                $offer->id,

                $barrierDirectory,

                'withdraw-worker',

                CarbonImmutable::now()
                    ->format(
                        'Y-m-d H:i:s'
                    ),

                'Concurrent supplier withdrawal.',
            ]);

        $rejectProcess->setTimeout(
            30
        );

        $withdrawProcess->setTimeout(
            30
        );

        $processes = [
            'reject-worker' =>
                $rejectProcess,

            'withdraw-worker' =>
                $withdrawProcess,
        ];

        foreach (
            $processes
            as $process
        ) {
            $process->start();
        }

        $this->waitUntilWorkersReady(
            $barrierDirectory,
            array_keys(
                $processes
            )
        );

        /*
         * Kedua PostgreSQL sessions dilepas
         * melalui barrier yang sama.
         */
        file_put_contents(
            $barrierDirectory
            .DIRECTORY_SEPARATOR
            .'go',
            'go'
        );

        foreach (
            $processes
            as $process
        ) {
            $process->wait();
        }

        $results = [];

        foreach (
            $processes
            as $workerId => $process
        ) {
            $resultPath =
                $barrierDirectory
                .DIRECTORY_SEPARATOR
                .$workerId
                .'.result.json';

            $this->assertFileExists(
                $resultPath,
                "Worker {$workerId} tidak menghasilkan result file.\n"
                ."STDOUT:\n"
                .$process->getOutput()
                ."\nSTDERR:\n"
                .$process->getErrorOutput()
            );

            $decoded =
                json_decode(
                    (string)
                    file_get_contents(
                        $resultPath
                    ),
                    true
                );

            $this->assertIsArray(
                $decoded
            );

            /*
             * PostgreSQL lock contention harus
             * selesai menjadi serialized business
             * outcome, bukan raw database error.
             */
            $this->assertNotSame(
                'error',
                $decoded[
                    'status'
                ] ?? null,
                'Worker database error: '
                .json_encode(
                    $decoded
                )
            );

            $this->assertNotSame(
                'worker_timeout',
                $decoded[
                    'status'
                ] ?? null,
                'Concurrency barrier timeout.'
            );

            $results[
                $workerId
            ] =
                $decoded;
        }

        return $results;
    } finally {
        foreach (
            $processes
            as $process
        ) {
            if (
                $process->isRunning()
            ) {
                $process->stop(
                    1
                );
            }
        }

        File::deleteDirectory(
            $barrierDirectory
        );
    }
}

    private function runConcurrentApprovals(
        User $manager,
        array $offers,
    ): array {
        $barrierDirectory =
            storage_path(
                'framework/testing/'
                .'fallback-concurrency-'
                .Str::uuid()
            );

        File::ensureDirectoryExists(
            $barrierDirectory
        );

        $processes = [];

        try {
            foreach (
                array_values(
                    $offers
                )
                as $index => $offer
            ) {
                $workerId =
                    'worker-'
                    .($index + 1);

                $process =
                    new Process([
                        PHP_BINARY,

                        base_path(
                            'tests/Support/'
                            .'FallbackConcurrencyWorker.php'
                        ),

                        'approve',

                        (string)
                        $manager->id,

                        (string)
                        $offer->id,

                        $barrierDirectory,

                        $workerId,

                        CarbonImmutable::now()
                            ->format(
                                'Y-m-d H:i:s'
                            ),
                    ]);

                $process->setTimeout(
                    30
                );

                $process->start();

                $processes[
                    $workerId
                ] =
                    $process;
            }

            $this->waitUntilWorkersReady(
                $barrierDirectory,
                array_keys(
                    $processes
                )
            );

            /*
             * Kedua child process dilepas dari
             * barrier sedekat mungkin secara waktu.
             */
            file_put_contents(
                $barrierDirectory
                .DIRECTORY_SEPARATOR
                .'go',
                'go'
            );

            foreach (
                $processes
                as $process
            ) {
                $process->wait();
            }

            $results = [];

            foreach (
                $processes
                as $workerId => $process
            ) {
                $resultPath =
                    $barrierDirectory
                    .DIRECTORY_SEPARATOR
                    .$workerId
                    .'.result.json';

                $this->assertFileExists(
                    $resultPath,
                    "Worker {$workerId} tidak menghasilkan result file.\n"
                    ."STDOUT:\n"
                    .$process->getOutput()
                    ."\nSTDERR:\n"
                    .$process->getErrorOutput()
                );

                $decoded =
                    json_decode(
                        (string)
                        file_get_contents(
                            $resultPath
                        ),
                        true
                    );

                $this->assertIsArray(
                    $decoded
                );

                /*
                 * DB deadlock, lock timeout,
                 * bootstrap exception, dll harus
                 * membuat test gagal.
                 */
                $this->assertNotSame(
                    'error',
                    $decoded[
                        'status'
                    ] ?? null,
                    'Worker database error: '
                    .json_encode(
                        $decoded
                    )
                );

                $this->assertNotSame(
                    'worker_timeout',
                    $decoded[
                        'status'
                    ] ?? null,
                    'Concurrency barrier timeout.'
                );

                $results[] =
                    $decoded;
            }

            return $results;
        } finally {
            foreach (
                $processes
                as $process
            ) {
                if (
                    $process->isRunning()
                ) {
                    $process->stop(
                        1
                    );
                }
            }

            File::deleteDirectory(
                $barrierDirectory
            );
        }
    }

    private function waitUntilWorkersReady(
        string $barrierDirectory,
        array $workerIds,
    ): void {
        $deadline =
            microtime(true)
            + 15.0;

        while (true) {
            $allReady =
                collect(
                    $workerIds
                )
                    ->every(
                        fn (
                            string $workerId
                        ): bool =>
                            file_exists(
                                $barrierDirectory
                                .DIRECTORY_SEPARATOR
                                .$workerId
                                .'.ready'
                            )
                    );

            if ($allReady) {
                return;
            }

            if (
                microtime(true)
                >= $deadline
            ) {
                $this->fail(
                    'Concurrency workers tidak '
                    .'mencapai barrier READY.'
                );
            }

            usleep(
                10_000
            );
        }
    }

    private function createOpenRequestContext(): array
    {
        $unit =
            Unit::create([
                'code' =>
                    'KG-RACE-RESERVE',

                'name' =>
                    'Kilogram',

                'symbol' =>
                    'kg',

                'decimal_precision' =>
                    6,

                'is_active' =>
                    true,
            ]);

        $commodity =
            Commodity::create([
                'code' =>
                    'COM-RACE-RESERVE',

                'name' =>
                    'Commodity Race Reserve',

                'default_unit_id' =>
                    $unit->id,

                'harvest_behavior' =>
                    null,

                'notes' =>
                    null,

                'is_active' =>
                    true,
            ]);

        $admin =
            User::factory()->create([
                'organization_id' =>
                    null,

                'role' =>
                    UserRole::SYSTEM_ADMIN,

                'is_active' =>
                    true,
            ]);

        $sppg =
            $this->createOrganization(
                OrganizationType::SPPG,
                'SPPG-RACE-RESERVE'
            );

        $primary =
            $this->createOrganization(
                OrganizationType::KDKMP,
                'PRIMARY-RACE-RESERVE'
            );

        $network =
            $this->createOrganization(
                OrganizationType::KDKMP,
                'NETWORK-RACE-RESERVE'
            );

        SupplyNetworkLink::create([
            'sppg_organization_id' =>
                $sppg->id,

            'kdkmp_organization_id' =>
                $primary->id,

            'network_role' =>
                NetworkRole::PRIMARY,

            'is_active' =>
                true,

            'configured_by' =>
                $admin->id,
        ]);

        SupplyNetworkLink::create([
            'sppg_organization_id' =>
                $sppg->id,

            'kdkmp_organization_id' =>
                $network->id,

            'network_role' =>
                NetworkRole::NETWORK,

            'is_active' =>
                true,

            'configured_by' =>
                $admin->id,
        ]);

        $sppgUser =
            User::factory()->create([
                'organization_id' =>
                    $sppg->id,

                'role' =>
                    UserRole::SPPG_USER,

                'is_active' =>
                    true,
            ]);

        $operator =
            $this->createKdkmpUser(
                $primary,
                UserRole::KDKMP_OPERATOR
            );

        $manager =
            $this->createKdkmpUser(
                $primary,
                UserRole::KDKMP_MANAGER
            );

        $networkOperator =
            $this->createKdkmpUser(
                $network,
                UserRole::KDKMP_OPERATOR
            );

        $networkManager =
            $this->createKdkmpUser(
                $network,
                UserRole::KDKMP_MANAGER
            );

        $forecastService =
            app(
                DemandForecastService::class
            );

        $forecast =
            $forecastService->createDraft(
                $sppgUser,
                [
                    'commodity_id' =>
                        $commodity->id,

                    'unit_id' =>
                        $unit->id,

                    'target_volume' =>
                        '200.000000',

                    'required_start_at' =>
                        '2026-08-20 08:00:00',

                    'required_end_at' =>
                        '2026-08-20 12:00:00',

                    'freshness_interval_hours' =>
                        24,

                    'notes' =>
                        'Real DB concurrency fixture.',
                ]
            );

        $forecast =
            $forecastService->publish(
                $sppgUser,
                $forecast,
                $forecast->version
            );

        $requestService =
            app(
                FallbackRequestService::class
            );

        $fallbackRequest =
            $requestService->createDraft(
                $operator,
                $forecast,
                [
                    'requested_volume' =>
                        '150.000000',

                    'response_deadline_at' =>
                        '2026-08-19 18:00:00',

                    'broadcast_note' =>
                        'Real DB race fixture.',
                ]
            );

        $fallbackRequest =
            $requestService->submit(
                $operator,
                $fallbackRequest
            );

        $fallbackRequest =
            $requestService
                ->approveBroadcast(
                    $manager,
                    $fallbackRequest
                );

        return [
            'unit' =>
                $unit,

            'commodity' =>
                $commodity,

            'primary' =>
                $primary,

            'network' =>
                $network,

            'operator' =>
                $operator,

            'manager' =>
                $manager,

            'networkOperator' =>
                $networkOperator,

            'networkManager' =>
                $networkManager,

            'forecast' =>
                $forecast,

            'request' =>
                $fallbackRequest,
        ];
    }

    private function createEligibleSource(
    array $context,
    string $suffix,
    string $minimum,
): SupplyCommitment {
    $producer =
        Producer::create([
            'organization_id' =>
                $context[
                    'network'
                ]->id,

            'producer_code' =>
                'PROD-RACE-'
                .$suffix,

            'name' =>
                'Producer Race '
                .$suffix,

            'village' =>
                'Desa Test',

            'district' =>
                'Kecamatan Test',

            'contact_phone' =>
                '081234567890',

            'notes' =>
                'Real DB source fixture.',

            'is_active' =>
                true,

            'created_by' =>
                $context[
                    'networkOperator'
                ]->id,
        ]);

    $commitment =
        SupplyCommitment::create([
            'forecast_id' =>
                $context[
                    'forecast'
                ]->id,

            'organization_id' =>
                $context[
                    'network'
                ]->id,

            'producer_id' =>
                $producer->id,

            'expected_harvest_id' =>
                null,

            'commodity_id' =>
                $context[
                    'commodity'
                ]->id,

            'active_version_id' =>
                null,

            'lifecycle_status' =>
                CommitmentLifecycleStatus::ACTIVE,

            'current_confidence' =>
                SupplyConfidence::GREEN,

            'last_confidence_verified_at' =>
                now(),

            'created_by' =>
                $context[
                    'networkOperator'
                ]->id,
        ]);

    $version =
        CommitmentVersion::create([
            'commitment_id' =>
                $commitment->id,

            'version_no' =>
                1,

            'min_volume' =>
                $minimum,

            'max_volume' =>
                $minimum,

            'unit_id' =>
                $context[
                    'unit'
                ]->id,

            'availability_start_at' =>
                '2026-08-20 07:00:00',

            'availability_end_at' =>
                '2026-08-20 13:00:00',

            'notes' =>
                'Approved NETWORK source.',

            'approval_status' =>
                CommitmentApprovalStatus
                    ::APPROVED,

            'change_reason' =>
                null,

            'operator_justification' =>
                null,

            'created_by' =>
                $context[
                    'networkOperator'
                ]->id,

            'submitted_by' =>
                $context[
                    'networkOperator'
                ]->id,

            'submitted_at' =>
                now(),

            'reviewed_by' =>
                null,

            'reviewed_at' =>
                now(),

            'review_reason' =>
                null,

            'approved_at' =>
                now(),

            'created_at' =>
                now(),
        ]);

    $commitment->update([
        'active_version_id' =>
            $version->id,
    ]);

    return $commitment
        ->fresh();
}

private function createAvailableOffer(
    array $context,
    SupplyCommitment $source,
    string $volume,
    string $note,
): FallbackOffer {
    $offer =
        $this->createPendingOffer(
            $context,
            $source,
            $volume,
            $note
        );

    return app(
        FallbackOfferService::class
    )->approveForAvailability(
        $context[
            'networkManager'
        ],
        $offer
    );
}

    private function createPendingOffer(
        array $context,
        SupplyCommitment $source,
        string $volume,
        string $note,
    ): FallbackOffer {
        $service =
            app(
                FallbackOfferService::class
            );

        $offer =
            $service->createDraft(
                $context[
                    'networkOperator'
                ],
                $context[
                    'request'
                ],
                [
                    'offered_volume' =>
                        $volume,

                    'availability_note' =>
                        $note,

                    'expires_at' =>
                        '2026-08-19 12:00:00',

                    'source_commitment_ids' => [
                        $source->id,
                    ],
                ]
            );

        return $service->submit(
            $context[
                'networkOperator'
            ],
            $offer
        );
    }

    private function createOrganization(
        OrganizationType $type,
        string $code,
    ): Organization {
        return Organization::create([
            'code' =>
                $code,

            'name' =>
                "Organization {$code}",

            'organization_type' =>
                $type,

            'is_active' =>
                true,

            'general_location' =>
                'Lokasi Test',
        ]);
    }

    private function createKdkmpUser(
        Organization $organization,
        UserRole $role,
    ): User {
        return User::factory()->create([
            'organization_id' =>
                $organization->id,

            'role' =>
                $role,

            'is_active' =>
                true,
        ]);
    }
}