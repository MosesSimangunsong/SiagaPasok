<?php

namespace App\Services\Fallback;

use App\Enums\AuditSource;
use App\Enums\FallbackRequestStatus;
use App\Enums\ForecastStatus;
use App\Enums\NetworkRole;
use App\Enums\FallbackOfferStatus;
use App\Models\FallbackOffer;
use App\Models\DemandForecast;
use App\Models\FallbackRequest;
use App\Models\SupplyNetworkLink;
use App\Models\User;
use App\Models\FallbackOfferSource;
use App\Services\Audit\AuditService;
use App\Services\Supply\SupplyMetricsService;
use App\Services\Notification\OperationalNotificationService;
use App\Support\FixedScaleDecimal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;



final class FallbackRequestService
{
    private const AUDIT_CREATED =
        'FALLBACK_REQUEST_CREATED';

    private const AUDIT_SUBMITTED =
        'FALLBACK_REQUEST_SUBMITTED';

    private const AUDIT_OPENED =
        'FALLBACK_REQUEST_OPENED';

    private const AUDIT_REJECTED =
        'FALLBACK_REQUEST_REJECTED';

    private const AUDIT_CANCELLED =
    'FALLBACK_REQUEST_CANCELLED';

    private const AUDIT_EXPIRED =
    'FALLBACK_REQUEST_EXPIRED';
  
    private const AUDIT_OFFER_REJECTED_REQUESTER =
    'FALLBACK_OFFER_REJECTED_BY_REQUESTER';

private const AUDIT_OFFER_EXPIRED =
    'FALLBACK_OFFER_EXPIRED';

private const AUDIT_OFFER_RELEASED =
    'FALLBACK_OFFER_RESERVE_RELEASED';

    public function __construct(
    private readonly AuditService
        $auditService,

    private readonly SupplyMetricsService
        $supplyMetrics,

    private readonly FallbackReservationService
        $reservationService,

    private readonly OperationalNotificationService
        $operationalNotificationService,
) {
}


    public function calculateAcceptedVolume(
    FallbackRequest $request,
): string {
    $offers =
        FallbackOffer::query()
            ->where(
                'fallback_request_id',
                $request->id
            )
            ->where(
                'status',
                FallbackOfferStatus
                    ::ACCEPTED
                    ->value
            )
            ->orderBy('id')
            ->get([
                'accepted_volume',
            ]);

    $accepted =
        FixedScaleDecimal::zero();

    foreach ($offers as $offer) {
        $accepted =
            $accepted->add(
                FixedScaleDecimal::from(
                    (string)
                    $offer->accepted_volume
                )
            );
    }

    return $accepted->toString();
}

public function calculateRemainingVolume(
    FallbackRequest $request,
): string {
    $requested =
        FixedScaleDecimal::from(
            (string)
            $request->requested_volume
        );

    $accepted =
        FixedScaleDecimal::from(
            $this->calculateAcceptedVolume(
                $request
            )
        );

    return $requested
        ->subtractToZero(
            $accepted
        )
        ->toString();
}

    public function createDraft(
        User $actor,
        DemandForecast $forecast,
        array $data,
    ): FallbackRequest {
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
                $forecast,
                $validated,
            ): FallbackRequest {
                $currentForecast =
                    DemandForecast::query()
                        ->whereKey(
                            $forecast->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertRequestableForecast(
                    $actor,
                    $currentForecast
                );

                $requestedVolume =
                    $this->normalizeVolume(
                        $validated[
                            'requested_volume'
                        ]
                    );

                $this->assertResponseDeadline(
                    $validated[
                        'response_deadline_at'
                    ],
                    $currentForecast
                );

                $this->assertRequestedVolumeWithinCurrentShortfall(
                    $currentForecast,
                    $requestedVolume
                );

                $request =
                    FallbackRequest::create([
                        'forecast_id' =>
                            $currentForecast->id,

                        /*
                         * Organization tidak pernah
                         * dipercaya dari client.
                         */
                        'requester_organization_id' =>
                            $actor->organization_id,

                        'requested_volume' =>
                            $requestedVolume,

                        /*
                         * Unit selalu mengikuti
                         * Forecast canonical unit.
                         */
                        'unit_id' =>
                            $currentForecast->unit_id,

                        'response_deadline_at' =>
                            $validated[
                                'response_deadline_at'
                            ],

                        'status' =>
                            FallbackRequestStatus::DRAFT,

                        'broadcast_note' =>
                            $validated[
                                'broadcast_note'
                            ] ?? null,

                        'created_by' =>
                            $actor->id,
                    ]);

                $this->auditService->record(
                    actor: $actor,
                    source: AuditSource::USER,
                    action:
                        self::AUDIT_CREATED,
                    entity: $request,
                    previousValue: null,
                    newValue:
                        $this->snapshot(
                            $request
                        ),
                );

                return $request
                    ->refresh()
                    ->load([
                        'forecast',
                        'requesterOrganization',
                        'unit',
                        'createdBy',
                    ]);
            }
        );
    }

    public function submit(
        User $actor,
        FallbackRequest $request,
    ): FallbackRequest {
        $this->assertOperatorActor(
            $actor
        );

        return DB::transaction(
            function () use (
                $actor,
                $request,
            ): FallbackRequest {
                $currentRequest =
                    FallbackRequest::query()
                        ->whereKey(
                            $request->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertOwner(
                    $actor,
                    $currentRequest
                );

                /*
                 * Retry submit tidak membuat
                 * audit/event kedua.
                 */
                if (
                    $currentRequest
                        ->isPendingApproval()
                ) {
                    return $currentRequest;
                }

                if (
                    ! $currentRequest
                        ->isDraft()
                ) {
                    throw ValidationException::withMessages([
                        'status' => (
                            'Hanya Fallback Request DRAFT '
                            .'yang dapat disubmit.'
                        ),
                    ]);
                }

                $forecast =
                    DemandForecast::query()
                        ->whereKey(
                            $currentRequest
                                ->forecast_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertRequestableForecast(
                    $actor,
                    $forecast
                );

                $this->assertStoredRequestMatchesForecast(
                    $currentRequest,
                    $forecast
                );

                /*
                 * Shortfall wajib dibaca ulang.
                 * Kondisi mungkin sudah berubah
                 * sejak draft dibuat.
                 */
                $this->assertRequestedVolumeWithinCurrentShortfall(
                    $forecast,
                    (string)
                    $currentRequest
                        ->requested_volume
                );

                $this->assertResponseDeadline(
                    $currentRequest
                        ->response_deadline_at,
                    $forecast
                );

                $before =
                    $this->snapshot(
                        $currentRequest
                    );

                $currentRequest->update([
                    'status' =>
                        FallbackRequestStatus
                            ::PENDING_APPROVAL,

                    'submitted_by' =>
                        $actor->id,

                    'submitted_at' =>
                        now(),
                ]);

                $this->auditService->record(
    actor: $actor,
    source: AuditSource::USER,
    action:
        self::AUDIT_SUBMITTED,
    entity: $currentRequest,
    previousValue: $before,
    newValue:
        $this->snapshot(
            $currentRequest
        ),
);

$this->operationalNotificationService
    ->fallbackRequestApprovalRequired(
        $currentRequest
    );

return $currentRequest
    ->refresh()
                    ->load([
                        'forecast',
                        'requesterOrganization',
                        'unit',
                        'createdBy',
                        'submittedBy',
                    ]);
            }
        );
    }

    public function approveBroadcast(
        User $actor,
        FallbackRequest $request,
    ): FallbackRequest {
        $this->assertManagerActor(
            $actor
        );

        return DB::transaction(
            function () use (
                $actor,
                $request,
            ): FallbackRequest {
                $currentRequest =
                    FallbackRequest::query()
                        ->whereKey(
                            $request->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertOwner(
                    $actor,
                    $currentRequest
                );

                /*
                 * Repeat approval idempotent.
                 */
                if (
                    $currentRequest->isOpen()
                ) {
                    return $currentRequest;
                }

                if (
                    ! $currentRequest
                        ->isPendingApproval()
                ) {
                    throw ValidationException::withMessages([
                        'status' => (
                            'Hanya Fallback Request '
                            .'PENDING_APPROVAL yang dapat '
                            .'dibroadcast.'
                        ),
                    ]);
                }

                $this->assertMakerChecker(
                    $actor,
                    $currentRequest
                );

                $forecast =
                    DemandForecast::query()
                        ->whereKey(
                            $currentRequest
                                ->forecast_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                /*
                 * PRIMARY relationship, Forecast,
                 * unit, deadline dan Shortfall
                 * semuanya direvalidasi ketika
                 * keputusan Manager dibuat.
                 */
                $this->assertRequestableForecast(
                    $actor,
                    $forecast
                );

                $this->assertStoredRequestMatchesForecast(
                    $currentRequest,
                    $forecast
                );

                $this->assertResponseDeadline(
                    $currentRequest
                        ->response_deadline_at,
                    $forecast
                );

                $this->assertRequestedVolumeWithinCurrentShortfall(
                    $forecast,
                    (string)
                    $currentRequest
                        ->requested_volume
                );

                $before =
                    $this->snapshot(
                        $currentRequest
                    );

                $currentRequest->update([
                    'status' =>
                        FallbackRequestStatus::OPEN,

                    'reviewed_by' =>
                        $actor->id,

                    'reviewed_at' =>
                        now(),

                    'review_reason' =>
                        null,

                    'opened_at' =>
                        now(),
                ]);

                $this->auditService->record(
    actor: $actor,
    source: AuditSource::USER,
    action:
        self::AUDIT_OPENED,
    entity: $currentRequest,
    previousValue: $before,
    newValue:
        $this->snapshot(
            $currentRequest
        ),
);

/*
 * Broadcast notification hanya dijadwalkan
 * setelah Request benar-benar OPEN.
 *
 * NotificationService akan persist setelah
 * outer transaction berhasil commit.
 */
$this->operationalNotificationService
    ->fallbackRequestOpened(
        $currentRequest,
        $forecast
    );

return $currentRequest
    ->refresh()
                    ->load([
                        'forecast',
                        'requesterOrganization',
                        'unit',
                        'createdBy',
                        'submittedBy',
                        'reviewedBy',
                    ]);
            }
        );
    }

    public function rejectBroadcast(
        User $actor,
        FallbackRequest $request,
        string $reviewReason,
    ): FallbackRequest {
        $this->assertManagerActor(
            $actor
        );

        $reviewReason =
            trim(
                $reviewReason
            );

        if ($reviewReason === '') {
            throw ValidationException::withMessages([
                'review_reason' =>
                    'Alasan penolakan Fallback Request wajib diisi.',
            ]);
        }

        return DB::transaction(
            function () use (
                $actor,
                $request,
                $reviewReason,
            ): FallbackRequest {
                $currentRequest =
                    FallbackRequest::query()
                        ->whereKey(
                            $request->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertOwner(
                    $actor,
                    $currentRequest
                );

                if (
                    $currentRequest
                        ->isRejected()
                ) {
                    return $currentRequest;
                }

                if (
                    ! $currentRequest
                        ->isPendingApproval()
                ) {
                    throw ValidationException::withMessages([
                        'status' => (
                            'Hanya Fallback Request '
                            .'PENDING_APPROVAL yang dapat '
                            .'ditolak.'
                        ),
                    ]);
                }

                $this->assertMakerChecker(
                    $actor,
                    $currentRequest
                );

                /*
                 * Reject tetap diperbolehkan walau
                 * Forecast/network berubah.
                 *
                 * Manager harus tetap bisa menutup
                 * pending approval yang sudah tidak
                 * relevan.
                 */
                $before =
                    $this->snapshot(
                        $currentRequest
                    );

                $currentRequest->update([
                    'status' =>
                        FallbackRequestStatus
                            ::REJECTED,

                    'reviewed_by' =>
                        $actor->id,

                    'reviewed_at' =>
                        now(),

                    'review_reason' =>
                        $reviewReason,
                ]);

                $this->auditService->record(
                    actor: $actor,
                    source: AuditSource::USER,
                    action:
                        self::AUDIT_REJECTED,
                    entity: $currentRequest,
                    previousValue: $before,
                    newValue:
                        $this->snapshot(
                            $currentRequest
                        ),
                    reasonNote:
                        $reviewReason,
                );

                return $currentRequest
                    ->refresh()
                    ->load([
                        'forecast',
                        'requesterOrganization',
                        'unit',
                        'createdBy',
                        'submittedBy',
                        'reviewedBy',
                    ]);
            }
        );
    }

    public function cancel(
    User $actor,
    FallbackRequest $request,
    string $reason,
): FallbackRequest {
    $this->assertManagerActor(
        $actor
    );

    /*
     * Fast authorization sebelum mengambil
     * row locks milik organization lain.
     * Tetap direcheck setelah lock.
     */
    $this->assertRequestOwner(
        $actor,
        $request
    );

    $normalizedReason =
        trim($reason);

    if ($normalizedReason === '') {
        throw ValidationException::withMessages([
            'cancellation_reason' =>
                'Alasan pembatalan wajib diisi.',
        ]);
    }

    return DB::transaction(
        function () use (
            $actor,
            $request,
            $normalizedReason,
        ): FallbackRequest {
            /*
             * LOCK ORDER:
             *
             * Offer -> Request -> Source -> Commitment
             *
             * Offer Accept/Reject/Approval path juga
             * dimulai dari Offer lalu Request.
             *
             * Jangan membalik urutan menjadi
             * Request -> Offer karena dapat membuat
             * deadlock dengan Accept yang berjalan
             * bersamaan.
             */
            $availableOffers =
                FallbackOffer::query()
                    ->where(
                        'fallback_request_id',
                        $request->id
                    )
                    ->where(
                        'status',
                        FallbackOfferStatus
                            ::AVAILABLE
                            ->value
                    )
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

            $currentRequest =
                FallbackRequest::query()
                    ->whereKey(
                        $request->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            $this->assertRequestOwner(
                $actor,
                $currentRequest
            );

            /*
             * Repeated cancellation idempotent.
             */
            if ($currentRequest->isCancelled()) {
                return $currentRequest;
            }

            if (
                ! $currentRequest->isDraft()
                && ! $currentRequest->isOpen()
            ) {
                throw ValidationException::withMessages([
                    'status' => (
                        'Fallback Request hanya dapat '
                        .'dibatalkan dari status DRAFT '
                        .'atau OPEN.'
                    ),
                ]);
            }

            /*
             * DRAFT secara valid tidak mungkin
             * mempunyai AVAILABLE Offer.
             */
            if (
                $currentRequest->isDraft()
                && $availableOffers->isNotEmpty()
            ) {
                throw ValidationException::withMessages([
                    'status' => (
                        'Fallback Request DRAFT memiliki '
                        .'state Offer yang tidak konsisten.'
                    ),
                ]);
            }

            $before =
                $this->snapshot(
                    $currentRequest
                );

            /*
             * OPEN -> CANCELLED
             *
             * AVAILABLE Offers mempunyai active
             * reserve, sehingga requester Manager
             * menggunakan transition yang memang
             * dimilikinya:
             *
             * AVAILABLE -> REJECTED.
             *
             * DRAFT/PENDING supplier Offers tidak
             * disentuh karena:
             * - reserve = 0;
             * - requester tidak memiliki authority
             *   terhadap state tersebut.
             */
            foreach ($availableOffers as $offer) {
                $offer
                    ->load('sources');

                $offerBefore =
                    $this->offerSnapshot(
                        $offer
                    );

                $released =
                    $this->reservationService
                        ->releaseOpenReserve(
                            $offer,
                            CarbonImmutable::now()
                        );

                $offerReason =
                    'Parent Fallback Request dibatalkan: '
                    .$normalizedReason;

                $offer->update([
                    'status' =>
                        FallbackOfferStatus::REJECTED,

                    'requester_decided_by' =>
                        $actor->id,

                    'requester_decided_at' =>
                        now(),

                    'requester_decision_reason' =>
                        $offerReason,
                ]);

                $offer
                    ->refresh()
                    ->load('sources');

                if (! $released->isZero()) {
                    $this->auditService->record(
                        actor: $actor,
                        source: AuditSource::USER,
                        action:
                            self::AUDIT_OFFER_RELEASED,
                        entity: $offer,
                        previousValue:
                            $offerBefore,
                        newValue: [
                            ...$this
                                ->offerSnapshot(
                                    $offer
                                ),

                            'released_in_transition' =>
                                $released->toString(),
                        ],
                        reasonNote:
                            $offerReason,
                    );
                }

                $this->auditService->record(
                    actor: $actor,
                    source: AuditSource::USER,
                    action:
                        self::AUDIT_OFFER_REJECTED_REQUESTER,
                    entity: $offer,
                    previousValue:
                        $offerBefore,
                    newValue:
                        $this->offerSnapshot(
                            $offer
                        ),
                    reasonNote:
                        $offerReason,
                );
            }

            $currentRequest->update([
                'status' =>
                    FallbackRequestStatus::CANCELLED,

                'cancelled_at' =>
                    now(),

                'cancellation_reason' =>
                    $normalizedReason,
            ]);

            $currentRequest->refresh();

            $this->auditService->record(
                actor: $actor,
                source: AuditSource::USER,
                action:
                    self::AUDIT_CANCELLED,
                entity: $currentRequest,
                previousValue:
                    $before,
                newValue:
                    $this->snapshot(
                        $currentRequest
                    ),
                reasonNote:
                    $normalizedReason,
            );

            return $currentRequest;
        }
    );
}

public function expire(
    FallbackRequest $request,
    ?CarbonInterface $evaluatedAt = null,
): FallbackRequest {
    $evaluationTime =
        $evaluatedAt === null
            ? CarbonImmutable::now()
            : CarbonImmutable::instance(
                $evaluatedAt
            );

    return DB::transaction(
        function () use (
            $request,
            $evaluationTime,
        ): FallbackRequest {
            /*
             * Pertahankan lock ordering:
             *
             * Offer -> Request -> Source -> Commitment
             */
            $availableOffers =
                FallbackOffer::query()
                    ->where(
                        'fallback_request_id',
                        $request->id
                    )
                    ->where(
                        'status',
                        FallbackOfferStatus
                            ::AVAILABLE
                            ->value
                    )
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

            $currentRequest =
                FallbackRequest::query()
                    ->whereKey(
                        $request->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            if ($currentRequest->isExpired()) {
                return $currentRequest;
            }

            if (! $currentRequest->isOpen()) {
                throw ValidationException::withMessages([
                    'status' => (
                        'Hanya Fallback Request OPEN '
                        .'yang dapat menjadi EXPIRED.'
                    ),
                ]);
            }

            $deadline =
                CarbonImmutable::instance(
                    $currentRequest
                        ->response_deadline_at
                );

            /*
             * Contract M07.1C tetap:
             *
             * tepat pada response deadline Request
             * masih valid.
             *
             * Request EXPIRED hanya ketika:
             * evaluatedAt > deadline.
             */
            if (
                ! $evaluationTime->gt(
                    $deadline
                )
            ) {
                throw ValidationException::withMessages([
                    'response_deadline_at' =>
                        'Fallback Request belum melewati response deadline.',
                ]);
            }

            $before =
                $this->snapshot(
                    $currentRequest
                );

            /*
             * Semua AVAILABLE Offer pasti sudah
             * berada di luar response window karena:
             *
             * offer.expires_at
             * <= request.response_deadline_at
             * < evaluationTime.
             *
             * Jadi AVAILABLE -> EXPIRED sah dan
             * seluruh reserve wajib dilepas.
             */
            foreach ($availableOffers as $offer) {
                $offer
                    ->load('sources');

                $offerBefore =
                    $this->offerSnapshot(
                        $offer
                    );

                $released =
                    $this->reservationService
                        ->releaseOpenReserve(
                            $offer,
                            $evaluationTime
                        );

                $offer->update([
                    'status' =>
                        FallbackOfferStatus::EXPIRED,
                ]);

                $offer
                    ->refresh()
                    ->load('sources');

                $reason =
                    'Parent Fallback Request melewati response deadline.';

                if (! $released->isZero()) {
                    $this->auditService->record(
                        actor: null,
                        source: AuditSource::SYSTEM,
                        action:
                            self::AUDIT_OFFER_RELEASED,
                        entity: $offer,
                        previousValue:
                            $offerBefore,
                        newValue: [
                            ...$this
                                ->offerSnapshot(
                                    $offer
                                ),

                            'released_in_transition' =>
                                $released->toString(),
                        ],
                        reasonNote:
                            $reason,
                    );
                }

                $this->auditService->record(
                    actor: null,
                    source: AuditSource::SYSTEM,
                    action:
                        self::AUDIT_OFFER_EXPIRED,
                    entity: $offer,
                    previousValue:
                        $offerBefore,
                    newValue:
                        $this->offerSnapshot(
                            $offer
                        ),
                    reasonNote:
                        $reason,
                );
            }

            $currentRequest->update([
                'status' =>
                    FallbackRequestStatus::EXPIRED,

                'expired_at' =>
                    $evaluationTime,
            ]);

            $currentRequest->refresh();

            $this->auditService->record(
                actor: null,
                source: AuditSource::SYSTEM,
                action:
                    self::AUDIT_EXPIRED,
                entity: $currentRequest,
                previousValue:
                    $before,
                newValue:
                    $this->snapshot(
                        $currentRequest
                    ),
                reasonNote:
                    'Fallback Request melewati response deadline.',
            );

            return $currentRequest;
        }
    );
}

/**
 * Expire semua OPEN request yang benar-benar
 * sudah melewati response deadline.
 *
 * @return int jumlah request yang berubah menjadi EXPIRED
 */
public function expireDueOpenRequests(
    ?CarbonInterface $evaluatedAt = null,
): int {
    $evaluationTime =
        $evaluatedAt === null
            ? CarbonImmutable::now()
            : CarbonImmutable::instance(
                $evaluatedAt
            );

    /*
     * Gunakan "<", bukan "<=".
     *
     * Equality dengan response deadline masih
     * merupakan boundary valid.
     */
    $requestIds =
        FallbackRequest::query()
            ->where(
                'status',
                FallbackRequestStatus
                    ::OPEN
                    ->value
            )
            ->where(
                'response_deadline_at',
                '<',
                $evaluationTime
            )
            ->orderBy('id')
            ->pluck('id');

    $expiredCount = 0;

    foreach ($requestIds as $requestId) {
        $request =
            FallbackRequest::query()
                ->find(
                    $requestId
                );

        if (! $request) {
            continue;
        }

        try {
            $expired =
                $this->expire(
                    $request,
                    $evaluationTime
                );

            if ($expired->isExpired()) {
                $expiredCount++;
            }
        } catch (ValidationException) {
            /*
             * State mungkin berubah di antara
             * candidate query dan row lock.
             *
             * Jangan mengubah state lain hanya
             * untuk memenuhi batch expiry.
             */
            continue;
        }
    }

    return $expiredCount;
}

    private function validateDraftPayload(
        array $data,
    ): array {
        return Validator::make(
            $data,
            [
                'requested_volume' => [
                    'required',
                    'numeric',
                    'gt:0',
                ],

                'response_deadline_at' => [
                    'required',
                    'date',
                ],

                'broadcast_note' => [
                    'nullable',
                    'string',
                ],
            ]
        )->validate();
    }

    private function normalizeVolume(
        mixed $value,
    ): string {
        try {
            return FixedScaleDecimal::from(
                (string) $value
            )->toString();
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'requested_volume' => (
                    'Requested volume harus menggunakan '
                    .'angka non-negatif dengan maksimal '
                    .'6 digit desimal.'
                ),
            ]);
        }
    }

    private function assertRequestedVolumeWithinCurrentShortfall(
        DemandForecast $forecast,
        string $requestedVolume,
    ): void {
        $requested =
            FixedScaleDecimal::from(
                $requestedVolume
            );

        if ($requested->isZero()) {
            throw ValidationException::withMessages([
                'requested_volume' =>
                    'Requested volume harus lebih besar dari 0.',
            ]);
        }

        /*
         * Canonical M06 adalah satu-satunya
         * authority untuk Shortfall.
         */
        $metrics =
            $this->supplyMetrics
                ->calculate(
                    $forecast
                );

        $shortfall =
            FixedScaleDecimal::from(
                $metrics->shortfall
            );

        if ($shortfall->isZero()) {
            throw ValidationException::withMessages([
                'requested_volume' => (
                    'Fallback Request tidak dapat dibuat '
                    .'karena Forecast tidak memiliki '
                    .'Shortfall saat ini.'
                ),
            ]);
        }

        if (
            $requested->compare(
                $shortfall
            ) > 0
        ) {
            throw ValidationException::withMessages([
                'requested_volume' => (
                    'Requested volume tidak boleh '
                    .'melebihi current Shortfall '
                    .$shortfall->toString().'.'
                ),
            ]);
        }
    }

    private function assertResponseDeadline(
        mixed $deadlineValue,
        DemandForecast $forecast,
    ): void {
        $deadline =
            $deadlineValue instanceof \DateTimeInterface
                ? CarbonImmutable::instance(
                    $deadlineValue
                )
                : CarbonImmutable::parse(
                    (string) $deadlineValue
                );

        $requiredEnd =
            CarbonImmutable::instance(
                $forecast
                    ->required_end_at
            );

        if (
            $deadline->gt(
                $requiredEnd
            )
        ) {
            throw ValidationException::withMessages([
                'response_deadline_at' => (
                    'Response deadline tidak boleh '
                    .'melewati operational boundary '
                    .'Forecast.'
                ),
            ]);
        }

        /*
         * Request dengan response deadline yang
         * sudah lewat tidak boleh diteruskan ke
         * workflow broadcast.
         *
         * M07.1C nanti akan menangani transition
         * EXPIRED secara eksplisit.
         */
        if (
            $deadline->lt(
                CarbonImmutable::now()
            )
        ) {
            throw ValidationException::withMessages([
                'response_deadline_at' => (
                    'Response deadline sudah terlewati.'
                ),
            ]);
        }
    }

    private function assertStoredRequestMatchesForecast(
        FallbackRequest $request,
        DemandForecast $forecast,
    ): void {
        if (
            $request->forecast_id
            !== $forecast->id
        ) {
            throw ValidationException::withMessages([
                'forecast_id' =>
                    'Fallback Request tidak cocok dengan Forecast.',
            ]);
        }

        /*
         * Tidak ada unit conversion pada MVP.
         * Corrupted persisted row harus fail closed.
         */
        if (
            $request->unit_id
            !== $forecast->unit_id
        ) {
            throw ValidationException::withMessages([
                'unit_id' => (
                    'Unit Fallback Request tidak lagi '
                    .'sesuai dengan Forecast.'
                ),
            ]);
        }
    }

    private function assertRequestableForecast(
        User $actor,
        DemandForecast $forecast,
    ): void {
        if (
            $forecast->status
            !== ForecastStatus::PUBLISHED
        ) {
            throw ValidationException::withMessages([
                'forecast_id' => (
                    'Fallback Request hanya dapat '
                    .'diproses untuk Forecast PUBLISHED.'
                ),
            ]);
        }

        $primaryOrganizationIds =
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

        /*
         * Sama seperti M06:
         * corrupted topology tidak boleh dipilih
         * secara arbitrary.
         */
        if (
            $primaryOrganizationIds
                ->count() !== 1
            || (int)
                $primaryOrganizationIds
                    ->first()
                !== $actor->organization_id
        ) {
            throw new AuthorizationException(
                'KDKMP bukan PRIMARY aktif untuk Forecast tersebut.'
            );
        }
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
                'Hanya KDKMP Operator aktif yang dapat menyiapkan Fallback Request.'
            );
        }
    }


    private function assertRequestOwner(
    User $actor,
    FallbackRequest $request,
): void {
    if (
        $actor->organization_id
        !== $request->requester_organization_id
    ) {
        throw new AuthorizationException(
            'Fallback Request bukan milik organization requester pengguna.'
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
                'Hanya KDKMP Manager aktif yang dapat mereview Fallback Request.'
            );
        }
    }

    private function assertOwner(
        User $actor,
        FallbackRequest $request,
    ): void {
        if (
            $actor->organization_id
            !== $request
                ->requester_organization_id
        ) {
            throw new AuthorizationException(
                'Fallback Request bukan milik organization pengguna.'
            );
        }
    }

    private function assertMakerChecker(
        User $actor,
        FallbackRequest $request,
    ): void {
        /*
         * submitted_by adalah maker yang
         * mengirim payload untuk approval.
         */
        if (
            $request->submitted_by === null
            || $request->submitted_by
                === $actor->id
        ) {
            throw new AuthorizationException(
                'Maker tidak boleh menjadi checker untuk Fallback Request yang sama.'
            );
        }
    }

    private function offerSnapshot(
    FallbackOffer $offer,
): array {
    return [
        'fallback_request_id' =>
            $offer->fallback_request_id,

        'supplier_organization_id' =>
            $offer->supplier_organization_id,

        'offered_volume' =>
            (string)
            $offer->offered_volume,

        'accepted_volume' =>
            (string)
            $offer->accepted_volume,

        'unit_id' =>
            $offer->unit_id,

        'expires_at' =>
            $offer
                ->expires_at
                ?->toIso8601String(),

        'status' =>
            $offer->status->value,

        /*
         * Ini internal audit snapshot,
         * bukan requester-facing payload.
         */
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

        'requester_decided_by' =>
            $offer
                ->requester_decided_by,

        'requester_decided_at' =>
            $offer
                ->requester_decided_at
                ?->toIso8601String(),

        'requester_decision_reason' =>
            $offer
                ->requester_decision_reason,
    ];
}

    private function snapshot(
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

            'unit_id' =>
                $request->unit_id,

            'response_deadline_at' =>
                $request
                    ->response_deadline_at
                    ?->toIso8601String(),
                    'fulfilled_at' =>
    $request
        ->fulfilled_at
        ?->toIso8601String(),

'cancelled_at' =>
    $request
        ->cancelled_at
        ?->toIso8601String(),

'cancellation_reason' =>
    $request
        ->cancellation_reason,

'expired_at' =>
    $request
        ->expired_at
        ?->toIso8601String(),

            'status' =>
                $request->status->value,

            'broadcast_note' =>
                $request->broadcast_note,

            'created_by' =>
                $request->created_by,

            'submitted_by' =>
                $request->submitted_by,

            'submitted_at' =>
                $request
                    ->submitted_at
                    ?->toIso8601String(),

            'reviewed_by' =>
                $request->reviewed_by,

            'reviewed_at' =>
                $request
                    ->reviewed_at
                    ?->toIso8601String(),

            'review_reason' =>
                $request->review_reason,

            'opened_at' =>
                $request
                    ->opened_at
                    ?->toIso8601String(),
        ];
    }
}