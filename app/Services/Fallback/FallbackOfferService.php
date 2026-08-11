<?php

namespace App\Services\Fallback;

use App\Enums\AuditSource;
use App\Enums\FallbackOfferStatus;
use App\Enums\ForecastStatus;
use App\Enums\NetworkRole;
use App\Models\DemandForecast;
use App\Models\FallbackOffer;
use App\Models\FallbackOfferSource;
use App\Models\FallbackRequest;
use App\Models\SupplyCommitment;
use App\Models\SupplyNetworkLink;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Notification\OperationalNotificationService;
use App\Support\FixedScaleDecimal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

use InvalidArgumentException;

final class FallbackOfferService
{
    private const AUDIT_CREATED =
        'FALLBACK_OFFER_CREATED';
    
    private const AUDIT_SUBMITTED =
    'FALLBACK_OFFER_SUBMITTED';

    private const AUDIT_AVAILABLE =
    'FALLBACK_OFFER_AVAILABLE';

    private const AUDIT_RESERVED =
    'FALLBACK_OFFER_RESERVED';

private const AUDIT_REJECTED_SUPPLIER =
    'FALLBACK_OFFER_REJECTED_BY_SUPPLIER';

private const AUDIT_REJECTED_REQUESTER =
    'FALLBACK_OFFER_REJECTED_BY_REQUESTER';

private const AUDIT_WITHDRAWN =
    'FALLBACK_OFFER_WITHDRAWN';

private const AUDIT_EXPIRED =
    'FALLBACK_OFFER_EXPIRED';

private const AUDIT_RELEASED =
    'FALLBACK_OFFER_RESERVE_RELEASED';

    private const AUDIT_ALLOCATED =
    'FALLBACK_OFFER_CAPACITY_ALLOCATED';

private const AUDIT_ACCEPTED =
    'FALLBACK_OFFER_ACCEPTED';

private const AUDIT_REQUEST_FULFILLED =
    'FALLBACK_REQUEST_FULFILLED';
    public function __construct(
    private readonly AuditService
        $auditService,

    private readonly FallbackCapacityService
        $capacityService,

    private readonly FallbackRequestService
        $requestService,

    private readonly FallbackReservationService
        $reservationService,

    private readonly OperationalNotificationService
        $operationalNotificationService,
) {
}

    public function createDraft(
        User $actor,
        FallbackRequest $request,
        array $data,
    ): FallbackOffer {
        $this->assertOperatorActor(
            $actor
        );

        $validated =
            $this->validateDraftPayload(
                $data
            );

        return DB::transaction(
            function () use (
                $actor,
                $request,
                $validated,
            ): FallbackOffer {
                $currentRequest =
                    FallbackRequest::query()
                        ->whereKey(
                            $request->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $forecast =
                    DemandForecast::query()
                        ->whereKey(
                            $currentRequest
                                ->forecast_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $evaluationTime =
                    CarbonImmutable::now();

                $this->assertOpenRequest(
                    $currentRequest,
                    $forecast,
                    $evaluationTime
                );

                $this->assertSupplierEligibility(
                    $actor,
                    $currentRequest,
                    $forecast
                );

                $offeredVolume =
                    $this->normalizePositiveVolume(
                        $validated[
                            'offered_volume'
                        ]
                    );

                $expiresAt =
                    CarbonImmutable::parse(
                        $validated[
                            'expires_at'
                        ]
                    );

                $this->assertOfferExpiry(
                    $expiresAt,
                    $currentRequest,
                    $forecast,
                    $evaluationTime
                );

                $sourceIds =
                    array_values(
                        array_unique(
                            array_map(
                                'intval',
                                $validated[
                                    'source_commitment_ids'
                                ]
                            )
                        )
                    );

                /*
                 * lockForUpdate di sini tidak
                 * melakukan reservation.
                 *
                 * Tujuannya hanya memastikan
                 * selected source snapshot stabil
                 * selama DRAFT creation.
                 */
                $commitments =
                    SupplyCommitment::query()
                        ->whereIn(
                            'id',
                            $sourceIds
                        )
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                if (
                    $commitments->count()
                    !== count($sourceIds)
                ) {
                    throw ValidationException::withMessages([
                        'source_commitment_ids' => (
                            'Satu atau lebih source '
                            .'Commitment tidak ditemukan.'
                        ),
                    ]);
                }

                $totalAvailable =
                    FixedScaleDecimal::zero();

                foreach ($commitments as $commitment) {
                    $available =
                        FixedScaleDecimal::from(
                            $this->capacityService
                                ->availableCapacity(
                                    $commitment,
                                    $forecast,
                                    $actor
                                        ->organization_id,
                                    $evaluationTime
                                )
                        );

                    /*
                     * Setiap selected source harus
                     * benar-benar eligible dan
                     * memiliki capacity > 0.
                     */
                    if ($available->isZero()) {
                        throw ValidationException::withMessages([
                            'source_commitment_ids' => (
                                'Source Commitment '
                                .$commitment->id
                                .' tidak memiliki eligible '
                                .'fallback capacity saat ini.'
                            ),
                        ]);
                    }

                    $totalAvailable =
                        $totalAvailable->add(
                            $available
                        );
                }

                /*
                 * DRAFT belum reserve, tetapi angka
                 * bebas tetap tidak boleh dibuat.
                 */
                if (
                    $offeredVolume->compare(
                        $totalAvailable
                    ) > 0
                ) {
                    throw ValidationException::withMessages([
                        'offered_volume' => (
                            'Offered volume tidak boleh '
                            .'melebihi eligible capacity '
                            .$totalAvailable->toString().'.'
                        ),
                    ]);
                }

                $offer =
                    FallbackOffer::create([
                        'fallback_request_id' =>
                            $currentRequest->id,

                        /*
                         * Organization berasal dari
                         * authenticated actor.
                         */
                        'supplier_organization_id' =>
                            $actor->organization_id,

                        'offered_volume' =>
                            $offeredVolume
                                ->toString(),

                        'accepted_volume' =>
                            FixedScaleDecimal::zero()
                                ->toString(),

                        /*
                         * Tidak dipercaya dari client.
                         */
                        'unit_id' =>
                            $forecast->unit_id,

                        'availability_note' =>
                            $validated[
                                'availability_note'
                            ] ?? null,

                        'expires_at' =>
                            $expiresAt,

                        'status' =>
                            FallbackOfferStatus::DRAFT,

                        'created_by' =>
                            $actor->id,
                    ]);

                /*
                 * Pada DRAFT, source association
                 * sudah persist tetapi belum ada
                 * reservation.
                 */
                foreach ($commitments as $commitment) {
                    FallbackOfferSource::create([
                        'fallback_offer_id' =>
                            $offer->id,

                        'supply_commitment_id' =>
                            $commitment->id,

                        'reserved_volume' =>
                            '0.000000',

                        'allocated_volume' =>
                            '0.000000',

                        'released_volume' =>
                            '0.000000',

                        'reserved_at' =>
                            null,

                        'allocated_at' =>
                            null,

                        'released_at' =>
                            null,
                    ]);
                }

                $offer->load([
                    'fallbackRequest.forecast',
                    'supplierOrganization',
                    'unit',
                    'createdBy',
                    'sources.supplyCommitment',
                ]);

                $this->auditService->record(
                    actor: $actor,
                    source: AuditSource::USER,
                    action:
                        self::AUDIT_CREATED,
                    entity: $offer,
                    previousValue: null,
                    newValue:
                        $this->snapshot(
                            $offer
                        ),
                );

                return $offer;
            }
        );
    }

    public function submit(
    User $actor,
    FallbackOffer $offer,
): FallbackOffer {
    $this->assertOperatorActor(
        $actor
    );

    return DB::transaction(
        function () use (
            $actor,
            $offer,
        ): FallbackOffer {
            $currentOffer =
                FallbackOffer::query()
                    ->whereKey(
                        $offer->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            $this->assertSupplierOwner(
                $actor,
                $currentOffer
            );

            /*
             * Retry submit harus idempotent.
             */
            if (
                $currentOffer
                    ->isPendingApproval()
            ) {
                return $currentOffer
                    ->load([
                        'fallbackRequest.forecast',
                        'supplierOrganization',
                        'unit',
                        'createdBy',
                        'submittedBy',
                        'sources.supplyCommitment',
                    ]);
            }

            if (
                ! $currentOffer->isDraft()
            ) {
                throw ValidationException::withMessages([
                    'status' => (
                        'Hanya Fallback Offer DRAFT '
                        .'yang dapat disubmit.'
                    ),
                ]);
            }

            $request =
                FallbackRequest::query()
                    ->whereKey(
                        $currentOffer
                            ->fallback_request_id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            $forecast =
                DemandForecast::query()
                    ->whereKey(
                        $request->forecast_id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            $evaluationTime =
                CarbonImmutable::now();

            $this->assertOpenRequest(
                $request,
                $forecast,
                $evaluationTime
            );

            $this->assertSupplierEligibility(
                $actor,
                $request,
                $forecast
            );

            $this->assertOfferExpiry(
                CarbonImmutable::instance(
                    $currentOffer
                        ->expires_at
                ),
                $request,
                $forecast,
                $evaluationTime
            );

            /*
             * Submit belum melakukan reservation.
             *
             * Tetapi eligible capacity harus
             * direcheck karena mungkin berubah
             * setelah DRAFT dibuat.
             */
            $this->assertOfferHasEnoughCurrentCapacity(
                $currentOffer,
                $forecast,
                $evaluationTime
            );

            $before =
                $this->snapshot(
                    $currentOffer
                        ->load('sources')
                );

            $currentOffer->update([
                'status' =>
                    FallbackOfferStatus
                        ::PENDING_APPROVAL,

                'submitted_by' =>
                    $actor->id,

                'submitted_at' =>
                    now(),
            ]);

            $currentOffer
                ->refresh()
                ->load([
                    'fallbackRequest.forecast',
                    'supplierOrganization',
                    'unit',
                    'createdBy',
                    'submittedBy',
                    'sources.supplyCommitment',
                ]);

            $this->auditService->record(
    actor: $actor,
    source: AuditSource::USER,
    action:
        self::AUDIT_SUBMITTED,
    entity: $currentOffer,
    previousValue: $before,
    newValue:
        $this->snapshot(
            $currentOffer
        ),
);

$this->operationalNotificationService
    ->fallbackOfferApprovalRequired(
        $currentOffer
    );

return $currentOffer;
        }
    );
}

public function approveForAvailability(
    User $actor,
    FallbackOffer $offer,
): FallbackOffer {
    $this->assertManagerActor(
        $actor
    );

    return DB::transaction(
        function () use (
            $actor,
            $offer,
        ): FallbackOffer {
            /*
             * Lock Offer terlebih dahulu.
             */
            $currentOffer =
                FallbackOffer::query()
                    ->whereKey(
                        $offer->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            $this->assertSupplierOwner(
                $actor,
                $currentOffer
            );

            /*
             * Repeated approval tidak boleh
             * menghasilkan reservation kedua.
             */
            if (
                $currentOffer->isAvailable()
            ) {
                return $currentOffer
                    ->load([
                        'fallbackRequest.forecast',
                        'supplierOrganization',
                        'unit',
                        'createdBy',
                        'submittedBy',
                        'supplierReviewedBy',
                        'sources.supplyCommitment',
                    ]);
            }

            if (
                ! $currentOffer
                    ->isPendingApproval()
            ) {
                throw ValidationException::withMessages([
                    'status' => (
                        'Hanya Fallback Offer '
                        .'PENDING_APPROVAL yang dapat '
                        .'disetujui menjadi AVAILABLE.'
                    ),
                ]);
            }

            $this->assertMakerChecker(
                $actor,
                $currentOffer
            );

            $request =
                FallbackRequest::query()
                    ->whereKey(
                        $currentOffer
                            ->fallback_request_id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            $forecast =
                DemandForecast::query()
                    ->whereKey(
                        $request->forecast_id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            $evaluationTime =
                CarbonImmutable::now();

            /*
             * State supplier/request/forecast
             * semuanya direcheck saat approval.
             */
            $this->assertOpenRequest(
                $request,
                $forecast,
                $evaluationTime
            );

            $this->assertSupplierEligibility(
                $actor,
                $request,
                $forecast
            );

            $this->assertOfferExpiry(
                CarbonImmutable::instance(
                    $currentOffer
                        ->expires_at
                ),
                $request,
                $forecast,
                $evaluationTime
            );

            /*
             * Lock source ledger dalam ordering
             * deterministik.
             */
            $sourceRows =
                FallbackOfferSource::query()
                    ->where(
                        'fallback_offer_id',
                        $currentOffer->id
                    )
                    ->orderBy(
                        'supply_commitment_id'
                    )
                    ->lockForUpdate()
                    ->get();

            if ($sourceRows->isEmpty()) {
                throw ValidationException::withMessages([
                    'source_commitment_ids' => (
                        'Fallback Offer tidak memiliki '
                        .'source Commitment.'
                    ),
                ]);
            }

            /*
             * PENDING_APPROVAL seharusnya belum
             * mempunyai ledger exposure.
             *
             * Jika persisted state abnormal,
             * jangan melakukan reserve kedua.
             */
            foreach ($sourceRows as $sourceRow) {
                if (
                    ! FixedScaleDecimal::from(
                        (string)
                        $sourceRow
                            ->reserved_volume
                    )->isZero()
                    || ! FixedScaleDecimal::from(
                        (string)
                        $sourceRow
                            ->allocated_volume
                    )->isZero()
                    || ! FixedScaleDecimal::from(
                        (string)
                        $sourceRow
                            ->released_volume
                    )->isZero()
                ) {
                    throw ValidationException::withMessages([
                        'source_commitment_ids' => (
                            'Ledger source Offer tidak '
                            .'berada pada state yang valid '
                            .'untuk reservation.'
                        ),
                    ]);
                }
            }

            $commitmentIds =
                $sourceRows
                    ->pluck(
                        'supply_commitment_id'
                    )
                    ->map(
                        fn ($id): int =>
                            (int) $id
                    )
                    ->values()
                    ->all();

            /*
             * SupplyCommitment adalah serialization
             * point utama untuk competing offers.
             *
             * Dua approval yang memakai source sama
             * harus mencoba memperoleh lock pada row
             * Commitment yang sama.
             */
            $commitments =
                SupplyCommitment::query()
                    ->whereIn(
                        'id',
                        $commitmentIds
                    )
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

            if (
                $commitments->count()
                !== count($commitmentIds)
            ) {
                throw ValidationException::withMessages([
                    'source_commitment_ids' => (
                        'Satu atau lebih source '
                        .'Commitment tidak ditemukan.'
                    ),
                ]);
            }

            $before =
                $this->snapshot(
                    $currentOffer
                        ->load('sources')
                );

            $remainingToReserve =
                FixedScaleDecimal::from(
                    (string)
                    $currentOffer
                        ->offered_volume
                );

            /*
             * Deterministic greedy reservation:
             *
             * source commitment ID terkecil
             * digunakan lebih dulu.
             *
             * ERD tidak memiliki proposed volume
             * per source pada DRAFT, jadi Manager
             * approval adalah saat offered volume
             * dipetakan ke selected source ledger.
             */
            foreach ($sourceRows as $sourceRow) {
                if (
                    $remainingToReserve
                        ->isZero()
                ) {
                    break;
                }

                $commitment =
                    $commitments->get(
                        $sourceRow
                            ->supply_commitment_id
                    );

                $available =
                    FixedScaleDecimal::from(
                        $this->capacityService
                            ->availableCapacity(
                                $commitment,
                                $forecast,
                                $currentOffer
                                    ->supplier_organization_id,
                                $evaluationTime
                            )
                    );

                if ($available->isZero()) {
                    continue;
                }

                $reserveForSource =
                    $available->compare(
                        $remainingToReserve
                    ) >= 0
                        ? $remainingToReserve
                        : $available;

                $sourceRow->update([
                    'reserved_volume' =>
                        $reserveForSource
                            ->toString(),

                    'reserved_at' =>
                        $evaluationTime,
                ]);

                $remainingToReserve =
                    $remainingToReserve
                        ->subtractToZero(
                            $reserveForSource
                        );
            }

            /*
             * C18:
             *
             * AVAILABLE hanya jika offered volume
             * telah dibackup reserve secara penuh.
             *
             * Exception menyebabkan transaction
             * rollback, termasuk source rows yang
             * sempat di-update di loop di atas.
             */
            if (
                ! $remainingToReserve
                    ->isZero()
            ) {
                throw ValidationException::withMessages([
                    'offered_volume' => (
                        'Eligible capacity berubah dan '
                        .'tidak lagi cukup untuk '
                        .'mereservasi seluruh Offered '
                        .'Volume. Sesuaikan Offer dan '
                        .'submit kembali.'
                    ),
                ]);
            }

            $currentOffer->update([
                'status' =>
                    FallbackOfferStatus
                        ::AVAILABLE,

                'supplier_reviewed_by' =>
                    $actor->id,

                'supplier_reviewed_at' =>
                    $evaluationTime,

                'supplier_review_reason' =>
                    null,
            ]);

            $currentOffer
                ->refresh()
                ->load([
                    'fallbackRequest.forecast',
                    'supplierOrganization',
                    'unit',
                    'createdBy',
                    'submittedBy',
                    'supplierReviewedBy',
                    'sources.supplyCommitment',
                ]);

            $this->auditService->record(
    actor: $actor,
    source: AuditSource::USER,
    action:
        self::AUDIT_RESERVED,
    entity: $currentOffer,
    previousValue: $before,
    newValue:
        $this->snapshot(
            $currentOffer
        ),
);

            $this->auditService->record(
    actor: $actor,
    source: AuditSource::USER,
    action:
        self::AUDIT_AVAILABLE,
    entity: $currentOffer,
    previousValue: $before,
    newValue:
        $this->snapshot(
            $currentOffer
        ),
);

/*
 * Requester Manager baru boleh diberi CTA setelah:
 *
 * - source masih eligible;
 * - reserve penuh berhasil;
 * - Offer benar-benar AVAILABLE.
 */
$this->operationalNotificationService
    ->fallbackOfferDecisionRequired(
        $currentOffer,
        $request
    );

return $currentOffer;
        }
    );
}


public function accept(
    User $actor,
    FallbackOffer $offer,
    string $acceptedVolume,
): FallbackOffer {
    $this->assertManagerActor(
        $actor
    );

    $accepted =
        $this->normalizeAcceptedVolume(
            $acceptedVolume
        );

    return DB::transaction(
        function () use (
            $actor,
            $offer,
            $accepted,
        ): FallbackOffer {
            /*
             * Lock Offer dan Request terlebih dahulu.
             *
             * Request lock menjadi serialization
             * point untuk beberapa AVAILABLE offers
             * yang mungkin diterima hampir bersamaan.
             */
            $currentOffer =
                FallbackOffer::query()
                    ->whereKey(
                        $offer->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            $request =
                FallbackRequest::query()
                    ->whereKey(
                        $currentOffer
                            ->fallback_request_id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            $this->assertRequesterOwner(
                $actor,
                $request
            );

            /*
             * Idempotent retry.
             *
             * Request bahkan mungkin sudah
             * FULFILLED pada repeated HTTP call,
             * jadi check ACCEPTED dilakukan sebelum
             * OPEN-request validation.
             */
            if ($currentOffer->isAccepted()) {
                $existingAccepted =
                    FixedScaleDecimal::from(
                        (string)
                        $currentOffer
                            ->accepted_volume
                    );

                if (
                    $existingAccepted->compare(
                        $accepted
                    ) !== 0
                ) {
                    throw ValidationException::withMessages([
                        'accepted_volume' => (
                            'Fallback Offer sudah '
                            .'ACCEPTED dengan volume '
                            .$existingAccepted
                                ->toString()
                            .'.'
                        ),
                    ]);
                }

                return $currentOffer
                    ->load([
                        'fallbackRequest.forecast',
                        'supplierOrganization',
                        'unit',
                        'createdBy',
                        'submittedBy',
                        'supplierReviewedBy',
                        'requesterDecidedBy',
                        'sources.supplyCommitment',
                    ]);
            }

            if (
                ! $currentOffer
                    ->isAvailable()
            ) {
                throw ValidationException::withMessages([
                    'status' => (
                        'Hanya Fallback Offer '
                        .'AVAILABLE yang dapat diterima.'
                    ),
                ]);
            }

            $forecast =
                DemandForecast::query()
                    ->whereKey(
                        $request->forecast_id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            $evaluationTime =
                CarbonImmutable::now();

            /*
             * Request/Forecast juga harus masih
             * operational pada saat keputusan
             * requester dibuat.
             */
            $this->assertOpenRequest(
                $request,
                $forecast,
                $evaluationTime
            );

            /*
             * AVAILABLE yang mencapai expires_at
             * sudah stale dan tidak dapat Accept.
             *
             * assertOfferExpiry mensyaratkan:
             * expires_at > evaluated_at
             */
            $this->assertOfferExpiry(
                CarbonImmutable::instance(
                    $currentOffer->expires_at
                ),
                $request,
                $forecast,
                $evaluationTime
            );

            /*
             * Supplier harus tetap active NETWORK
             * dan requester tetap current PRIMARY.
             */
            $this->assertOfferNetworkTopology(
                $currentOffer
                    ->supplier_organization_id,
                $request,
                $forecast
            );

            $offered =
                FixedScaleDecimal::from(
                    (string)
                    $currentOffer
                        ->offered_volume
                );

            /*
             * C21.
             */
            if (
                $accepted->compare(
                    $offered
                ) > 0
            ) {
                throw ValidationException::withMessages([
                    'accepted_volume' => (
                        'Accepted volume tidak boleh '
                        .'melebihi Offered Volume '
                        .$offered->toString().'.'
                    ),
                ]);
            }

            /*
             * C22.
             *
             * Request row sedang lock, sehingga dua
             * Accept untuk Request yang sama tidak
             * boleh sama-sama memakai remaining
             * requirement lama.
             */
            $remainingRequest =
                FixedScaleDecimal::from(
                    $this->requestService
                        ->calculateRemainingVolume(
                            $request
                        )
                );

            if ($remainingRequest->isZero()) {
                throw ValidationException::withMessages([
                    'accepted_volume' => (
                        'Fallback Request tidak lagi '
                        .'memiliki remaining requirement.'
                    ),
                ]);
            }

            if (
                $accepted->compare(
                    $remainingRequest
                ) > 0
            ) {
                throw ValidationException::withMessages([
                    'accepted_volume' => (
                        'Accepted volume tidak boleh '
                        .'melebihi remaining request '
                        .$remainingRequest
                            ->toString()
                        .'.'
                    ),
                ]);
            }

            /*
             * Lock source ledger dalam urutan yang
             * sama dengan reservation path.
             */
            $sourceRows =
                FallbackOfferSource::query()
                    ->where(
                        'fallback_offer_id',
                        $currentOffer->id
                    )
                    ->orderBy(
                        'supply_commitment_id'
                    )
                    ->lockForUpdate()
                    ->get();

            if ($sourceRows->isEmpty()) {
                throw ValidationException::withMessages([
                    'source_ledger' =>
                        'Fallback Offer tidak memiliki source reservation.',
                ]);
            }

            $commitmentIds =
                $sourceRows
                    ->pluck(
                        'supply_commitment_id'
                    )
                    ->map(
                        fn ($id): int =>
                            (int) $id
                    )
                    ->values()
                    ->all();

            /*
             * Serialize Accept terhadap confidence /
             * revision / competing capacity mutation
             * pada source Commitment yang sama.
             */
            $commitments =
                SupplyCommitment::query()
                    ->whereIn(
                        'id',
                        $commitmentIds
                    )
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

            if (
                $commitments->count()
                !== count($commitmentIds)
            ) {
                throw ValidationException::withMessages([
                    'source_ledger' =>
                        'Source Commitment Fallback tidak lengkap.',
                ]);
            }

            $totalReserved =
                FixedScaleDecimal::zero();

            foreach ($sourceRows as $sourceRow) {
                $reserved =
                    FixedScaleDecimal::from(
                        (string)
                        $sourceRow
                            ->reserved_volume
                    );

                $allocated =
                    FixedScaleDecimal::from(
                        (string)
                        $sourceRow
                            ->allocated_volume
                    );

                $released =
                    FixedScaleDecimal::from(
                        (string)
                        $sourceRow
                            ->released_volume
                    );

                /*
                 * AVAILABLE belum boleh mempunyai
                 * allocation/release sebelumnya.
                 */
                if (
                    ! $allocated->isZero()
                    || ! $released->isZero()
                ) {
                    throw ValidationException::withMessages([
                        'source_ledger' => (
                            'Fallback Offer AVAILABLE '
                            .'memiliki ledger allocation/'
                            .'release yang tidak valid.'
                        ),
                    ]);
                }

                $totalReserved =
                    $totalReserved->add(
                        $reserved
                    );

                /*
                 * Source tanpa actual reserve tidak
                 * membackup Offer dan tidak perlu
                 * memblok Acceptance bila kemudian
                 * degrade.
                 */
                if ($reserved->isZero()) {
                    continue;
                }

                $commitment =
                    $commitments->get(
                        $sourceRow
                            ->supply_commitment_id
                    );

                /*
                 * User Flow:
                 * underlying supply harus masih
                 * valid ketika Accept.
                 *
                 * Ini juga menangkap current minimum
                 * yang turun di bawah exposure.
                 */
                if (
                    ! $this->capacityService
                        ->supportsCurrentExposure(
                            $commitment,
                            $forecast,
                            $currentOffer
                                ->supplier_organization_id,
                            $evaluationTime
                        )
                ) {
                    throw ValidationException::withMessages([
                        'source_ledger' => (
                            'Underlying fallback source '
                            .$commitment->id
                            .' tidak lagi valid untuk '
                            .'Acceptance.'
                        ),
                    ]);
                }
            }

            /*
             * C18 defensive recheck:
             * AVAILABLE harus mempunyai full reserve.
             */
            if (
                $totalReserved->compare(
                    $offered
                ) !== 0
            ) {
                throw ValidationException::withMessages([
                    'source_ledger' => (
                        'Total source reservation tidak '
                        .'sesuai dengan Offered Volume.'
                    ),
                ]);
            }

            $before =
                $this->snapshot(
                    $currentOffer
                        ->load('sources')
                );

            $requestBefore =
                $this->requestSnapshot(
                    $request
                );

            /*
             * Deterministic allocation mengikuti
             * ordering source reservation.
             */
            $remainingToAllocate =
                $accepted;

            foreach ($sourceRows as $sourceRow) {
                if (
                    $remainingToAllocate
                        ->isZero()
                ) {
                    break;
                }

                $reserved =
                    FixedScaleDecimal::from(
                        (string)
                        $sourceRow
                            ->reserved_volume
                    );

                if ($reserved->isZero()) {
                    continue;
                }

                $allocateForSource =
                    $reserved->compare(
                        $remainingToAllocate
                    ) >= 0
                        ? $remainingToAllocate
                        : $reserved;

                $sourceRow->update([
                    'allocated_volume' =>
                        $allocateForSource
                            ->toString(),

                    'allocated_at' =>
                        $evaluationTime,
                ]);

                $remainingToAllocate =
                    $remainingToAllocate
                        ->subtractToZero(
                            $allocateForSource
                        );
            }

            /*
             * Seluruh accepted portion wajib
             * mempunyai source allocation.
             *
             * Failure me-rollback seluruh perubahan.
             */
            if (
                ! $remainingToAllocate
                    ->isZero()
            ) {
                throw ValidationException::withMessages([
                    'accepted_volume' => (
                        'Accepted Volume tidak dapat '
                        .'dialokasikan secara penuh '
                        .'terhadap source reserve.'
                    ),
                ]);
            }

            /*
             * C23:
             * semua unused reserve setelah partial
             * Acceptance langsung dilepas.
             */
            $released =
                $this->reservationService
    ->releaseOpenReserve(
        $currentOffer,
        $evaluationTime
    );

            $currentOffer->update([
                'accepted_volume' =>
                    $accepted->toString(),

                'status' =>
                    FallbackOfferStatus::ACCEPTED,

                'requester_decided_by' =>
                    $actor->id,

                'requester_decided_at' =>
                    $evaluationTime,

                'requester_decision_reason' =>
                    null,
            ]);

            /*
             * Setelah Offer menjadi ACCEPTED,
             * progress Request direcalculate dari
             * seluruh historical ACCEPTED Offers.
             */
            $remainingAfterAcceptance =
                FixedScaleDecimal::from(
                    $this->requestService
                        ->calculateRemainingVolume(
                            $request
                        )
                );

            $requestBecameFulfilled =
                false;

            if (
                $remainingAfterAcceptance
                    ->isZero()
            ) {
                $request->update([
                    'status' =>
                        \App\Enums\FallbackRequestStatus
                            ::FULFILLED,

                    'fulfilled_at' =>
                        $evaluationTime,
                ]);

                $requestBecameFulfilled =
                    true;
            }

            /*
             * Jika masih ada requirement,
             * Request tetap OPEN.
             */
            $currentOffer
                ->refresh()
                ->load([
                    'fallbackRequest.forecast',
                    'supplierOrganization',
                    'unit',
                    'createdBy',
                    'submittedBy',
                    'supplierReviewedBy',
                    'requesterDecidedBy',
                    'sources.supplyCommitment',
                ]);

            $request->refresh();

            /*
             * Allocation adalah event tersendiri
             * karena ledger capacity termasuk
             * minimum audit requirements.
             */
            $this->auditService->record(
                actor: $actor,
                source: AuditSource::USER,
                action:
                    self::AUDIT_ALLOCATED,
                entity: $currentOffer,
                previousValue: $before,
                newValue: [
                    ...$this->snapshot(
                        $currentOffer
                    ),

                    'allocated_in_transition' =>
                        $accepted->toString(),
                ],
            );

            $this->recordReleaseAuditIfNeeded(
                actor: $actor,
                source: AuditSource::USER,
                offer: $currentOffer,
                before: $before,
                released: $released,
                reason:
                    $released->isZero()
                        ? null
                        : 'Unused reserve setelah partial acceptance.',
            );

            $this->auditService->record(
                actor: $actor,
                source: AuditSource::USER,
                action:
                    self::AUDIT_ACCEPTED,
                entity: $currentOffer,
                previousValue: $before,
                newValue:
                    $this->snapshot(
                        $currentOffer
                    ),
            );

            if ($requestBecameFulfilled) {
                $this->auditService->record(
                    actor: $actor,
                    source: AuditSource::USER,
                    action:
                        self::AUDIT_REQUEST_FULFILLED,
                    entity: $request,
                    previousValue:
                        $requestBefore,
                    newValue:
                        $this->requestSnapshot(
                            $request
                        ),
                );
            }

            return $currentOffer;
        }
    );
}

public function rejectBySupplierManager(
    User $actor,
    FallbackOffer $offer,
    ?string $reason = null,
): FallbackOffer {
    $this->assertManagerActor(
        $actor
    );

    $reason =
        $this->normalizeOptionalReason(
            $reason
        );

    return DB::transaction(
        function () use (
            $actor,
            $offer,
            $reason,
        ): FallbackOffer {
            $currentOffer =
                FallbackOffer::query()
                    ->whereKey(
                        $offer->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            $this->assertSupplierOwner(
                $actor,
                $currentOffer
            );

            if ($currentOffer->isRejected()) {
                return $currentOffer
                    ->load([
                        'sources',
                    ]);
            }

            /*
             * PENDING_APPROVAL -> REJECTED
             *
             * Ini keputusan Manager supplier.
             * Pada state ini belum ada reserve.
             */
            if (
                ! $currentOffer
                    ->isPendingApproval()
            ) {
                throw ValidationException::withMessages([
                    'status' => (
                        'Supplier Manager hanya dapat '
                        .'menolak Fallback Offer '
                        .'PENDING_APPROVAL.'
                    ),
                ]);
            }

            $before =
                $this->snapshot(
                    $currentOffer
                        ->load('sources')
                );

            $currentOffer->update([
                'status' =>
                    FallbackOfferStatus::REJECTED,

                'supplier_reviewed_by' =>
                    $actor->id,

                'supplier_reviewed_at' =>
                    now(),

                'supplier_review_reason' =>
                    $reason,
            ]);

            $currentOffer
                ->refresh()
                ->load([
                    'fallbackRequest.forecast',
                    'supplierOrganization',
                    'unit',
                    'createdBy',
                    'submittedBy',
                    'supplierReviewedBy',
                    'sources.supplyCommitment',
                ]);

            $this->auditService->record(
                actor: $actor,
                source: AuditSource::USER,
                action:
                    self::AUDIT_REJECTED_SUPPLIER,
                entity: $currentOffer,
                previousValue: $before,
                newValue:
                    $this->snapshot(
                        $currentOffer
                    ),
                reasonNote:
                    $reason,
            );

            return $currentOffer;
        }
    );
}

public function rejectByRequesterManager(
    User $actor,
    FallbackOffer $offer,
    ?string $reason = null,
): FallbackOffer {
    $this->assertManagerActor(
        $actor
    );

    $reason =
        $this->normalizeOptionalReason(
            $reason
        );

    return DB::transaction(
        function () use (
            $actor,
            $offer,
            $reason,
        ): FallbackOffer {
            $currentOffer =
                FallbackOffer::query()
                    ->whereKey(
                        $offer->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            if ($currentOffer->isRejected()) {
                return $currentOffer
                    ->load([
                        'sources',
                    ]);
            }

            /*
             * Requester decision hanya berlaku
             * pada published supplier Offer.
             */
            if (
                ! $currentOffer->isAvailable()
            ) {
                throw ValidationException::withMessages([
                    'status' => (
                        'Requester Manager hanya dapat '
                        .'menolak Fallback Offer '
                        .'AVAILABLE.'
                    ),
                ]);
            }

            $request =
                FallbackRequest::query()
                    ->whereKey(
                        $currentOffer
                            ->fallback_request_id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            $this->assertRequesterOwner(
                $actor,
                $request
            );

            $before =
                $this->snapshot(
                    $currentOffer
                        ->load('sources')
                );

            $released =
                $this->reservationService
    ->releaseOpenReserve(
        $currentOffer,
        CarbonImmutable::now()
    );

            $currentOffer->update([
                'status' =>
                    FallbackOfferStatus::REJECTED,

                'requester_decided_by' =>
                    $actor->id,

                'requester_decided_at' =>
                    now(),

                'requester_decision_reason' =>
                    $reason,
            ]);

            $currentOffer
                ->refresh()
                ->load([
                    'fallbackRequest.forecast',
                    'supplierOrganization',
                    'unit',
                    'createdBy',
                    'submittedBy',
                    'supplierReviewedBy',
                    'requesterDecidedBy',
                    'sources.supplyCommitment',
                ]);

            $this->recordReleaseAuditIfNeeded(
                actor: $actor,
                source: AuditSource::USER,
                offer: $currentOffer,
                before: $before,
                released: $released,
                reason: $reason,
            );

            $this->auditService->record(
                actor: $actor,
                source: AuditSource::USER,
                action:
                    self::AUDIT_REJECTED_REQUESTER,
                entity: $currentOffer,
                previousValue: $before,
                newValue:
                    $this->snapshot(
                        $currentOffer
                    ),
                reasonNote:
                    $reason,
            );

            return $currentOffer;
        }
    );
}

public function withdraw(
    User $actor,
    FallbackOffer $offer,
    ?string $reason = null,
): FallbackOffer {
    $this->assertManagerActor(
        $actor
    );

    $reason =
        $this->normalizeOptionalReason(
            $reason
        );

    return DB::transaction(
        function () use (
            $actor,
            $offer,
            $reason,
        ): FallbackOffer {
            $currentOffer =
                FallbackOffer::query()
                    ->whereKey(
                        $offer->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            $this->assertSupplierOwner(
                $actor,
                $currentOffer
            );

            if ($currentOffer->isWithdrawn()) {
                return $currentOffer
                    ->load([
                        'sources',
                    ]);
            }

            /*
             * Locked lifecycle:
             *
             * DRAFT     -> WITHDRAWN
             * AVAILABLE -> WITHDRAWN
             *
             * PENDING_APPROVAL tidak memiliki
             * transition WITHDRAWN.
             *
             * ACCEPTED terminal dan tidak dapat
             * ditarik sepihak.
             */
            if (
                ! $currentOffer->isDraft()
                && ! $currentOffer->isAvailable()
            ) {
                throw ValidationException::withMessages([
                    'status' => (
                        'Fallback Offer hanya dapat '
                        .'ditarik dari status DRAFT '
                        .'atau AVAILABLE.'
                    ),
                ]);
            }

            $before =
                $this->snapshot(
                    $currentOffer
                        ->load('sources')
                );

            $released =
                FixedScaleDecimal::zero();

            if ($currentOffer->isAvailable()) {
                $released =
                    $this->reservationService
    ->releaseOpenReserve(
        $currentOffer,
        CarbonImmutable::now()
    );
            }

            $currentOffer->update([
                'status' =>
                    FallbackOfferStatus::WITHDRAWN,

                'withdrawn_by' =>
                    $actor->id,

                'withdrawn_at' =>
                    now(),

                'withdrawal_reason' =>
                    $reason,
            ]);

            $currentOffer
                ->refresh()
                ->load([
                    'fallbackRequest.forecast',
                    'supplierOrganization',
                    'unit',
                    'createdBy',
                    'submittedBy',
                    'supplierReviewedBy',
                    'withdrawnBy',
                    'sources.supplyCommitment',
                ]);

            $this->recordReleaseAuditIfNeeded(
                actor: $actor,
                source: AuditSource::USER,
                offer: $currentOffer,
                before: $before,
                released: $released,
                reason: $reason,
            );

            $this->auditService->record(
                actor: $actor,
                source: AuditSource::USER,
                action:
                    self::AUDIT_WITHDRAWN,
                entity: $currentOffer,
                previousValue: $before,
                newValue:
                    $this->snapshot(
                        $currentOffer
                    ),
                reasonNote:
                    $reason,
            );

            return $currentOffer;
        }
    );
}

public function expire(
    FallbackOffer $offer,
    ?CarbonInterface $evaluatedAt = null,
): FallbackOffer {
    $evaluationTime =
        $evaluatedAt === null
            ? CarbonImmutable::now()
            : CarbonImmutable::instance(
                $evaluatedAt
            );

    return DB::transaction(
        function () use (
            $offer,
            $evaluationTime,
        ): FallbackOffer {
            $currentOffer =
                FallbackOffer::query()
                    ->whereKey(
                        $offer->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            if ($currentOffer->isExpired()) {
                return $currentOffer
                    ->load([
                        'sources',
                    ]);
            }

            /*
             * Foundation hanya memberi:
             *
             * AVAILABLE -> EXPIRED
             */
            if (
                ! $currentOffer->isAvailable()
            ) {
                throw ValidationException::withMessages([
                    'status' => (
                        'Hanya Fallback Offer AVAILABLE '
                        .'yang dapat menjadi EXPIRED.'
                    ),
                ]);
            }

            $expiresAt =
                CarbonImmutable::instance(
                    $currentOffer->expires_at
                );

            /*
             * expires_at adalah expiry instant.
             *
             * Tepat pada timestamp tersebut,
             * Offer sudah tidak dapat di-Accept.
             */
            if (
                $evaluationTime->lt(
                    $expiresAt
                )
            ) {
                throw ValidationException::withMessages([
                    'expires_at' =>
                        'Fallback Offer belum mencapai expiry.',
                ]);
            }

            $before =
                $this->snapshot(
                    $currentOffer
                        ->load('sources')
                );

            $released =
                $this->reservationService
    ->releaseOpenReserve(
        $currentOffer,
        $evaluationTime
    );

            $currentOffer->update([
                'status' =>
                    FallbackOfferStatus::EXPIRED,
            ]);

            $currentOffer
                ->refresh()
                ->load([
                    'fallbackRequest.forecast',
                    'supplierOrganization',
                    'unit',
                    'createdBy',
                    'submittedBy',
                    'supplierReviewedBy',
                    'sources.supplyCommitment',
                ]);

            $this->recordReleaseAuditIfNeeded(
                actor: null,
                source: AuditSource::SYSTEM,
                offer: $currentOffer,
                before: $before,
                released: $released,
                reason:
                    'Fallback Offer mencapai expiry.',
            );

            $this->auditService->record(
                actor: null,
                source: AuditSource::SYSTEM,
                action:
                    self::AUDIT_EXPIRED,
                entity: $currentOffer,
                previousValue: $before,
                newValue:
                    $this->snapshot(
                        $currentOffer
                    ),
                reasonNote:
                    'Fallback Offer mencapai expiry.',
            );

            return $currentOffer;
        }
    );
}

/**
 * @return int jumlah AVAILABLE Offer yang berubah EXPIRED
 */
public function expireDueAvailableOffers(
    ?CarbonInterface $evaluatedAt = null,
): int {
    $evaluationTime =
        $evaluatedAt === null
            ? CarbonImmutable::now()
            : CarbonImmutable::instance(
                $evaluatedAt
            );

    $offerIds =
        FallbackOffer::query()
            ->where(
                'status',
                FallbackOfferStatus
                    ::AVAILABLE
                    ->value
            )
            ->where(
                'expires_at',
                '<=',
                $evaluationTime
            )
            ->orderBy('id')
            ->pluck('id');

    $expiredCount = 0;

    foreach ($offerIds as $offerId) {
        $offer =
            FallbackOffer::query()
                ->find(
                    $offerId
                );

        if (! $offer) {
            continue;
        }

        try {
            $expired =
                $this->expire(
                    $offer,
                    $evaluationTime
                );

            if ($expired->isExpired()) {
                $expiredCount++;
            }
        } catch (ValidationException) {
            /*
             * State dapat berubah antara candidate
             * query dan row lock.
             */
            continue;
        }
    }

    return $expiredCount;
}

private function assertOfferHasEnoughCurrentCapacity(
    FallbackOffer $offer,
    DemandForecast $forecast,
    CarbonImmutable $evaluatedAt,
): void {
    $sourceRows =
        FallbackOfferSource::query()
            ->where(
                'fallback_offer_id',
                $offer->id
            )
            ->orderBy(
                'supply_commitment_id'
            )
            ->get();

    if ($sourceRows->isEmpty()) {
        throw ValidationException::withMessages([
            'source_commitment_ids' =>
                'Fallback Offer harus memiliki minimal satu source Commitment.',
        ]);
    }

    $commitments =
        SupplyCommitment::query()
            ->whereIn(
                'id',
                $sourceRows->pluck(
                    'supply_commitment_id'
                )
            )
            ->orderBy('id')
            ->get()
            ->keyBy('id');

    if (
        $commitments->count()
        !== $sourceRows->count()
    ) {
        throw ValidationException::withMessages([
            'source_commitment_ids' => (
                'Satu atau lebih source '
                .'Commitment tidak ditemukan.'
            ),
        ]);
    }

    $totalAvailable =
        FixedScaleDecimal::zero();

    foreach ($sourceRows as $sourceRow) {
        $commitment =
            $commitments->get(
                $sourceRow
                    ->supply_commitment_id
            );

        $available =
            FixedScaleDecimal::from(
                $this->capacityService
                    ->availableCapacity(
                        $commitment,
                        $forecast,
                        $offer
                            ->supplier_organization_id,
                        $evaluatedAt
                    )
            );

        if ($available->isZero()) {
            throw ValidationException::withMessages([
                'source_commitment_ids' => (
                    'Source Commitment '
                    .$commitment->id
                    .' tidak lagi memiliki eligible '
                    .'fallback capacity.'
                ),
            ]);
        }

        $totalAvailable =
            $totalAvailable->add(
                $available
            );
    }

    $offeredVolume =
        FixedScaleDecimal::from(
            (string)
            $offer->offered_volume
        );

    if (
        $offeredVolume->compare(
            $totalAvailable
        ) > 0
    ) {
        throw ValidationException::withMessages([
            'offered_volume' => (
                'Current eligible capacity '
                .$totalAvailable->toString()
                .' tidak lagi mencukupi Offered '
                .'Volume '
                .$offeredVolume->toString().'.'
            ),
        ]);
    }
}

private function assertSupplierOwner(
    User $actor,
    FallbackOffer $offer,
): void {
    if (
        $actor->organization_id
        !== $offer
            ->supplier_organization_id
    ) {
        throw new AuthorizationException(
            'Fallback Offer bukan milik organization supplier pengguna.'
        );
    }
}

private function assertManagerActor(
    User $actor,
): void {
    if (
        ! $actor->isKdkmpManager()
        || ! $actor
            ->hasValidIdentityContext()
    ) {
        throw new AuthorizationException(
            'Hanya KDKMP Manager aktif yang dapat menyetujui Fallback Offer.'
        );
    }
}

private function assertMakerChecker(
    User $actor,
    FallbackOffer $offer,
): void {
    if (
        $offer->submitted_by === null
        || $offer->submitted_by
            === $actor->id
    ) {
        throw new AuthorizationException(
            'Maker tidak boleh menjadi checker untuk Fallback Offer yang sama.'
        );
    }
}


private function assertRequesterOwner(
    User $actor,
    FallbackRequest $request,
): void {
    if (
        $actor->organization_id
        !== $request
            ->requester_organization_id
    ) {
        throw new AuthorizationException(
            'Fallback Request bukan milik organization requester pengguna.'
        );
    }
}

private function normalizeOptionalReason(
    ?string $reason,
): ?string {
    if ($reason === null) {
        return null;
    }

    $normalized =
        trim(
            $reason
        );

    return $normalized === ''
        ? null
        : $normalized;
}

private function recordReleaseAuditIfNeeded(
    ?User $actor,
    AuditSource $source,
    FallbackOffer $offer,
    array $before,
    FixedScaleDecimal $released,
    ?string $reason,
): void {
    if ($released->isZero()) {
        return;
    }

    $this->auditService->record(
        actor: $actor,
        source: $source,
        action:
            self::AUDIT_RELEASED,
        entity: $offer,
        previousValue: $before,
        newValue: [
            ...$this->snapshot(
                $offer
            ),

            'released_in_transition' =>
                $released->toString(),
        ],
        reasonNote:
            $reason,
    );
}

private function normalizeAcceptedVolume(
    string $value,
): FixedScaleDecimal {
    try {
        $volume =
            FixedScaleDecimal::from(
                $value
            );
    } catch (InvalidArgumentException) {
        throw ValidationException::withMessages([
            'accepted_volume' => (
                'Accepted volume harus menggunakan '
                .'angka valid dengan maksimal '
                .'6 digit desimal.'
            ),
        ]);
    }

    if ($volume->isZero()) {
        throw ValidationException::withMessages([
            'accepted_volume' =>
                'Accepted volume harus lebih besar dari 0.',
        ]);
    }

    return $volume;
}

private function assertOfferNetworkTopology(
    int $supplierOrganizationId,
    FallbackRequest $request,
    DemandForecast $forecast,
): void {
    $primaryIds =
        SupplyNetworkLink::query()
            ->where(
                'sppg_organization_id',
                $forecast
                    ->sppg_organization_id
            )
            ->where(
                'network_role',
                NetworkRole::PRIMARY
                    ->value
            )
            ->where(
                'is_active',
                true
            )
            ->orderBy('id')
            ->pluck(
                'kdkmp_organization_id'
            );

    if (
        $primaryIds->count() !== 1
        || (int)
            $primaryIds->first()
            !== $request
                ->requester_organization_id
    ) {
        throw ValidationException::withMessages([
            'fallback_request_id' => (
                'Requester tidak lagi sesuai '
                .'dengan PRIMARY aktif Forecast.'
            ),
        ]);
    }

    $isActiveNetwork =
        SupplyNetworkLink::query()
            ->where(
                'sppg_organization_id',
                $forecast
                    ->sppg_organization_id
            )
            ->where(
                'kdkmp_organization_id',
                $supplierOrganizationId
            )
            ->where(
                'network_role',
                NetworkRole::NETWORK
                    ->value
            )
            ->where(
                'is_active',
                true
            )
            ->exists();

    if (! $isActiveNetwork) {
        throw new AuthorizationException(
            'Supplier tidak lagi merupakan NETWORK aktif untuk Forecast tersebut.'
        );
    }
}

private function requestSnapshot(
    FallbackRequest $request,
): array {
    return [
        'forecast_id' =>
            $request->forecast_id,

        'requester_organization_id' =>
            $request
                ->requester_organization_id,

        'requested_volume' =>
            (string)
            $request->requested_volume,

        'accepted_request_volume' =>
            $this->requestService
                ->calculateAcceptedVolume(
                    $request
                ),

        'remaining_request_volume' =>
            $this->requestService
                ->calculateRemainingVolume(
                    $request
                ),

        'unit_id' =>
            $request->unit_id,

        'status' =>
            $request->status->value,

        'fulfilled_at' =>
            $request
                ->fulfilled_at
                ?->toIso8601String(),
    ];
}
    private function validateDraftPayload(
        array $data,
    ): array {
        return Validator::make(
            $data,
            [
                /*
                 * Request/supplier/unit selalu
                 * diturunkan dari server context.
                 */
                'fallback_request_id' => [
                    'prohibited',
                ],

                'supplier_organization_id' => [
                    'prohibited',
                ],

                'unit_id' => [
                    'prohibited',
                ],

                'accepted_volume' => [
                    'prohibited',
                ],

                'status' => [
                    'prohibited',
                ],

                'offered_volume' => [
                    'required',
                    'numeric',
                    'gt:0',
                ],

                'availability_note' => [
                    'nullable',
                    'string',
                ],

                'expires_at' => [
                    'required',
                    'date',
                ],

                'source_commitment_ids' => [
                    'required',
                    'array',
                    'min:1',
                ],

                'source_commitment_ids.*' => [
                    'required',
                    'integer',
                    'distinct',
                ],
            ]
        )->validate();
    }

    private function normalizePositiveVolume(
        mixed $value,
    ): FixedScaleDecimal {
        try {
            $volume =
                FixedScaleDecimal::from(
                    (string) $value
                );
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'offered_volume' => (
                    'Offered volume harus menggunakan '
                    .'angka valid dengan maksimal '
                    .'6 digit desimal.'
                ),
            ]);
        }

        if ($volume->isZero()) {
            throw ValidationException::withMessages([
                'offered_volume' =>
                    'Offered volume harus lebih besar dari 0.',
            ]);
        }

        return $volume;
    }

    private function assertOpenRequest(
        FallbackRequest $request,
        DemandForecast $forecast,
        CarbonImmutable $evaluatedAt,
    ): void {
        if (! $request->isOpen()) {
            throw ValidationException::withMessages([
                'fallback_request_id' =>
                    'Hanya Fallback Request OPEN yang dapat menerima Offer.',
            ]);
        }

        if (
            $forecast->status
            !== ForecastStatus::PUBLISHED
        ) {
            throw ValidationException::withMessages([
                'forecast_id' => (
                    'Fallback Offer hanya dapat '
                    .'diproses untuk Forecast PUBLISHED.'
                ),
            ]);
        }

        if (
            $request->forecast_id
            !== $forecast->id
        ) {
            throw ValidationException::withMessages([
                'forecast_id' =>
                    'Fallback Request tidak sesuai dengan Forecast.',
            ]);
        }

        if (
            $request->unit_id
            !== $forecast->unit_id
        ) {
            throw ValidationException::withMessages([
                'unit_id' => (
                    'Unit Fallback Request tidak '
                    .'sesuai dengan Forecast.'
                ),
            ]);
        }

        /*
         * OPEN stale row tetap ditolak walaupun
         * batch expiry belum berjalan.
         */
        if (
            $evaluatedAt->gt(
                CarbonImmutable::instance(
                    $request
                        ->response_deadline_at
                )
            )
        ) {
            throw ValidationException::withMessages([
                'fallback_request_id' =>
                    'Fallback Request telah melewati response deadline.',
            ]);
        }

        if (
            $evaluatedAt->gt(
                CarbonImmutable::instance(
                    $forecast
                        ->required_end_at
                )
            )
        ) {
            throw ValidationException::withMessages([
                'forecast_id' =>
                    'Operational boundary Forecast telah terlewati.',
            ]);
        }
    }

    private function assertSupplierEligibility(
    User $actor,
    FallbackRequest $request,
    DemandForecast $forecast,
): void {
    if (
        $actor->organization_id
        === $request
            ->requester_organization_id
    ) {
        throw new AuthorizationException(
            'Requester tidak dapat menjadi supplier untuk Fallback Request yang sama.'
        );
    }

    $this->assertOfferNetworkTopology(
        $actor->organization_id,
        $request,
        $forecast
    );
}

    private function assertOperatorActor(
        User $actor,
    ): void {
        if (
            ! $actor->isKdkmpOperator()
            || ! $actor
                ->hasValidIdentityContext()
        ) {
            throw new AuthorizationException(
                'Hanya KDKMP Operator aktif yang dapat menyiapkan Fallback Offer.'
            );
        }
    }

    private function assertOfferExpiry(
        CarbonImmutable $expiresAt,
        FallbackRequest $request,
        DemandForecast $forecast,
        CarbonImmutable $evaluatedAt,
    ): void {
        if (
            ! $expiresAt->gt(
                $evaluatedAt
            )
        ) {
            throw ValidationException::withMessages([
                'expires_at' =>
                    'Offer expiry harus berada setelah waktu saat ini.',
            ]);
        }

        $requestDeadline =
            CarbonImmutable::instance(
                $request
                    ->response_deadline_at
            );

        $requiredEnd =
            CarbonImmutable::instance(
                $forecast
                    ->required_end_at
            );

        if (
            $expiresAt->gt(
                $requestDeadline
            )
        ) {
            throw ValidationException::withMessages([
                'expires_at' => (
                    'Offer expiry tidak boleh melewati '
                    .'Fallback Request response deadline.'
                ),
            ]);
        }

        if (
            $expiresAt->gt(
                $requiredEnd
            )
        ) {
            throw ValidationException::withMessages([
                'expires_at' => (
                    'Offer expiry tidak boleh melewati '
                    .'Forecast operational boundary.'
                ),
            ]);
        }
    }

    private function snapshot(
        FallbackOffer $offer,
    ): array {
        return [
            'fallback_request_id' =>
                $offer->fallback_request_id,

            'supplier_organization_id' =>
                $offer
                    ->supplier_organization_id,

            'offered_volume' =>
                (string)
                $offer->offered_volume,

            'accepted_volume' =>
                (string)
                $offer->accepted_volume,

            'unit_id' =>
                $offer->unit_id,

            'availability_note' =>
                $offer->availability_note,

            'expires_at' =>
                $offer
                    ->expires_at
                    ?->toIso8601String(),

            'status' =>
                $offer->status->value,

            /*
             * Source internals hanya masuk audit
             * internal; jangan expose snapshot ini
             * sebagai requester API payload.
             */
            'source_commitment_ids' =>
    $offer->sources
        ->pluck(
            'supply_commitment_id'
        )
        ->map(
            fn ($id): int =>
                (int) $id
        )
        ->values()
        ->all(),

'source_ledger' =>
    $offer->sources
        ->map(
            fn (
                FallbackOfferSource $source
            ): array => [
                'supply_commitment_id' =>
                    $source
                        ->supply_commitment_id,

                'reserved_volume' =>
                    (string)
                    $source
                        ->reserved_volume,

                'allocated_volume' =>
                    (string)
                    $source
                        ->allocated_volume,

                'released_volume' =>
                    (string)
                    $source
                        ->released_volume,
            ]
        )
        ->values()
        ->all(),

'created_by' =>
    $offer->created_by,

'submitted_by' =>
    $offer->submitted_by,

'submitted_at' =>
    $offer
        ->submitted_at
        ?->toIso8601String(),

'supplier_reviewed_by' =>
    $offer
        ->supplier_reviewed_by,

'supplier_reviewed_at' =>
    $offer
        ->supplier_reviewed_at
        ?->toIso8601String(),

'supplier_review_reason' =>
    $offer
        ->supplier_review_reason,
  
        'requester_decided_by' =>
    $offer->requester_decided_by,

'requester_decided_at' =>
    $offer
        ->requester_decided_at
        ?->toIso8601String(),

'requester_decision_reason' =>
    $offer
        ->requester_decision_reason,
        ];
    }
}