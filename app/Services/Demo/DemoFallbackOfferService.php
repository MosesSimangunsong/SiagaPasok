<?php

namespace App\Services\Demo;

use App\Models\DemandForecast;
use App\Models\FallbackOffer;
use App\Models\FallbackRequest;
use App\Models\Organization;
use App\Models\Producer;
use App\Models\SupplyCommitment;
use App\Models\User;
use App\Services\Fallback\FallbackOfferService;
use App\Services\Fallback\FallbackRequestService;
use App\Services\Supply\SupplyMetricsService;
use App\Support\Demo\DemoIdentifiers;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use App\Enums\SupplyConfidence;

final class DemoFallbackOfferService
{
    public function __construct(
        private readonly FallbackOfferService $fallbackOfferService,
        private readonly FallbackRequestService $fallbackRequestService,
        private readonly SupplyMetricsService $supplyMetricsService,
    ) {
    }

    public function prepareAndSubmit(
        User $actor
    ): FallbackOffer {
        $actor->loadMissing(
            'organization'
        );

        $this->assertNetworkOperator(
            $actor
        );

        [
            $forecast,
            $request,
            $source,
        ] = $this->resolveContext(
            requireOpenRequest: true
        );

        $this->assertSourceReady(
            $source
        );

        $offer =
            $this->resolveOffer(
                $request,
                $actor->organization_id
            );

        if ($offer) {
            $this->assertOfferIdentity(
                $offer,
                $request,
                $source
            );

            if ($offer->isDraft()) {
                return $this
                    ->fallbackOfferService
                    ->submit(
                        $actor,
                        $offer
                    );
            }

            if (
                $offer->isPendingApproval()
                || $offer->isAvailable()
                || $offer->isAccepted()
            ) {
                return $offer
                    ->refresh()
                    ->load('sources');
            }

            $this->fail(
                'Fallback Offer demo berada pada terminal state yang tidak kompatibel. Gunakan Demo Reset untuk memulai ulang scenario.'
            );
        }

        $offer =
            $this->fallbackOfferService
                ->createDraft(
                    $actor,
                    $request,
                    [
                        'offered_volume' =>
                            DemoIdentifiers
                                ::FALLBACK_OFFER_VOLUME,

                        'source_commitment_ids' => [
                            $source->id,
                        ],

                        'expires_at' =>
                            $request
                                ->response_deadline_at
                                ->toDateTimeString(),

                        'availability_note' =>
                            DemoIdentifiers
                                ::FALLBACK_OFFER_NOTE,
                    ]
                );

        return $this
            ->fallbackOfferService
            ->submit(
                $actor,
                $offer
            );
    }

    public function approveForAvailability(
        User $actor
    ): FallbackOffer {
        $actor->loadMissing(
            'organization'
        );

        $this->assertNetworkManager(
            $actor
        );

        [
            ,
            $request,
            $source,
        ] = $this->resolveContext(
            requireOpenRequest: true
        );

        $offer =
            $this->resolveOffer(
                $request,
                $actor->organization_id
            );

        if (! $offer) {
            $this->fail(
                'Fallback Offer demo 160 kg belum disiapkan oleh Operator Mitra Lestari.'
            );
        }

        $this->assertOfferIdentity(
            $offer,
            $request,
            $source
        );

        if ($offer->isAvailable()) {
            $this->assertAvailableLedger(
                $offer
            );

            return $offer
                ->refresh()
                ->load('sources');
        }

        if (! $offer->isPendingApproval()) {
            $this->fail(
                'Fallback Offer demo belum berada pada PENDING_APPROVAL.'
            );
        }

        $offer =
            $this->fallbackOfferService
                ->approveForAvailability(
                    $actor,
                    $offer
                );

        $this->assertAvailableLedger(
            $offer
        );

        return $offer;
    }

    public function accept(
        User $actor
    ): FallbackOffer {
        $actor->loadMissing(
            'organization'
        );

        $this->assertPrimaryManager(
            $actor
        );

        [
            $forecast,
            $request,
            $source,
        ] = $this->resolveContext(
            requireOpenRequest: false
        );

        $offer =
            $this->resolveOffer(
                $request,
                $source->organization_id
            );

        if (! $offer) {
            $this->fail(
                'Fallback Offer demo 160 kg belum tersedia.'
            );
        }

        $this->assertOfferIdentity(
            $offer,
            $request,
            $source
        );

        /*
         * FallbackOfferService::accept() sendiri
         * menangani retry ACCEPTED dengan volume
         * yang sama secara idempotent.
         */
        $offer =
            $this->fallbackOfferService
                ->accept(
                    $actor,
                    $offer,
                    DemoIdentifiers
                        ::FALLBACK_ACCEPTED_VOLUME
                );

        $this->assertRecoveredState(
            $forecast,
            $request,
            $offer
        );

        return $offer;
    }

    /**
     * @return array{
     *     0: DemandForecast,
     *     1: FallbackRequest,
     *     2: SupplyCommitment
     * }
     */
    private function resolveContext(
        bool $requireOpenRequest
    ): array {
        $forecast =
            DemandForecast::query()
                ->where(
                    'forecast_code',
                    DemoIdentifiers::FORECAST_CODE
                )
                ->first();

        if (
            ! $forecast
            || ! $forecast->isPublished()
        ) {
            $this->fail(
                'Forecast demo Kangkung 400 kg tidak tersedia atau tidak lagi PUBLISHED.'
            );
        }

        $request =
            FallbackRequest::query()
                ->where(
                    'forecast_id',
                    $forecast->id
                )
                ->where(
                    'broadcast_note',
                    DemoIdentifiers
                        ::FALLBACK_REQUEST_NOTE
                )
                ->orderByDesc('id')
                ->first();

        if (! $request) {
            $this->fail(
                'Fallback Request demo belum tersedia.'
            );
        }

        if (
            $requireOpenRequest
            && ! $request->isOpen()
        ) {
            $this->fail(
                'Fallback Request demo harus OPEN pada tahap ini.'
            );
        }

        $networkOrganization =
            Organization::query()
                ->where(
                    'code',
                    DemoIdentifiers
                        ::NETWORK_KDKMP_CODE
                )
                ->where(
                    'is_active',
                    true
                )
                ->first();

        if (! $networkOrganization) {
            $this->fail(
                'KDKMP Mitra Lestari demo tidak tersedia atau tidak aktif.'
            );
        }

        $producer =
            Producer::query()
                ->where(
                    'organization_id',
                    $networkOrganization->id
                )
                ->where(
                    'producer_code',
                    DemoIdentifiers
                        ::NETWORK_SOURCE_PRODUCER_CODE
                )
                ->first();

        if (! $producer) {
            $this->fail(
                'Producer source fallback demo tidak ditemukan.'
            );
        }

        $source =
            SupplyCommitment::query()
                ->with('activeVersion')
                ->where(
                    'forecast_id',
                    $forecast->id
                )
                ->where(
                    'organization_id',
                    $networkOrganization->id
                )
                ->where(
                    'producer_id',
                    $producer->id
                )
                ->first();

        if (! $source) {
            $this->fail(
                'Source Commitment fallback demo 160 kg belum tersedia.'
            );
        }

        return [
            $forecast,
            $request,
            $source,
        ];
    }

    private function resolveOffer(
        FallbackRequest $request,
        int $supplierOrganizationId
    ): ?FallbackOffer {
        $offers =
            FallbackOffer::query()
                ->where(
                    'fallback_request_id',
                    $request->id
                )
                ->where(
                    'supplier_organization_id',
                    $supplierOrganizationId
                )
                ->where(
                    'availability_note',
                    DemoIdentifiers
                        ::FALLBACK_OFFER_NOTE
                )
                ->orderBy('id')
                ->get();

        if ($offers->count() > 1) {
            $this->fail(
                'Lebih dari satu Fallback Offer dengan marker demo yang sama ditemukan.'
            );
        }

        return $offers->first();
    }

    private function assertSourceReady(
        SupplyCommitment $source
    ): void {
        $source->loadMissing(
            'activeVersion'
        );

if (
    ! $source->isActive()
    || ! $source->activeVersion
    || ! $source
        ->activeVersion
        ->isApproved()
    || $source->current_confidence
        !== SupplyConfidence::GREEN
    || (string) $source
        ->activeVersion
        ->min_volume
        !== DemoIdentifiers
            ::NETWORK_SOURCE_VOLUME
) {
            $this->fail(
                'Source Commitment Mitra Lestari belum ACTIVE + APPROVED + GREEN dengan minimum 160 kg.'
            );
        }
    }

    private function assertOfferIdentity(
        FallbackOffer $offer,
        FallbackRequest $request,
        SupplyCommitment $source
    ): void {
        $offer->loadMissing(
            'sources'
        );

        if (
            $offer->fallback_request_id
                !== $request->id
            || $offer->supplier_organization_id
                !== $source->organization_id
            || (string) $offer->offered_volume
                !== DemoIdentifiers
                    ::FALLBACK_OFFER_VOLUME
            || $offer->unit_id
                !== $request->unit_id
            || $offer->availability_note
                !== DemoIdentifiers
                    ::FALLBACK_OFFER_NOTE
            || $offer->sources->count() !== 1
            || $offer->sources
                ->first()
                ?->supply_commitment_id
                !== $source->id
        ) {
            $this->fail(
                'Fallback Offer demo ditemukan tetapi payload/source-nya tidak sesuai locked scenario 160 kg.'
            );
        }
    }

    private function assertAvailableLedger(
        FallbackOffer $offer
    ): void {
        $offer->loadMissing(
            'sources'
        );

        $source =
            $offer->sources->first();

        if (
            ! $offer->isAvailable()
            || ! $source
            || (string) $source->reserved_volume
                !== DemoIdentifiers
                    ::FALLBACK_OFFER_VOLUME
            || (string) $source->allocated_volume
                !== '0.000000'
            || (string) $source->released_volume
                !== '0.000000'
        ) {
            $this->fail(
                'Fallback Offer demo belum memiliki reserve 160 kg yang valid.'
            );
        }
    }

    private function assertRecoveredState(
        DemandForecast $forecast,
        FallbackRequest $request,
        FallbackOffer $offer
    ): void {
        $offer
            ->refresh()
            ->load('sources');

        $request->refresh();

        $source =
            $offer->sources->first();

        if (
            ! $offer->isAccepted()
            || (string) $offer->accepted_volume
                !== DemoIdentifiers
                    ::FALLBACK_ACCEPTED_VOLUME
            || ! $source
            || (string) $source->reserved_volume
                !== '160.000000'
            || (string) $source->allocated_volume
                !== '150.000000'
            || (string) $source->released_volume
                !== '10.000000'
            || ! $request->isFulfilled()
        ) {
            $this->fail(
                'Partial acceptance demo tidak menghasilkan ledger 160 reserved / 150 allocated / 10 released.'
            );
        }

        $remaining =
            $this->fallbackRequestService
                ->calculateRemainingVolume(
                    $request
                );

        if ($remaining !== '0.000000') {
            $this->fail(
                'Fallback Request demo masih memiliki remaining requirement setelah Accept 150 kg.'
            );
        }

        $metrics =
            $this->supplyMetricsService
                ->calculate(
                    $forecast->refresh()
                );

        $primary =
            Organization::query()
                ->where(
                    'code',
                    DemoIdentifiers
                        ::PRIMARY_KDKMP_CODE
                )
                ->firstOrFail();

        $network =
            Organization::query()
                ->where(
                    'code',
                    DemoIdentifiers
                        ::NETWORK_KDKMP_CODE
                )
                ->firstOrFail();

        $contributorIds = [
            $primary->id,
            $network->id,
        ];

        sort(
            $contributorIds
        );

        $breakdown = [
            $primary->id =>
                '250.000000',

            $network->id =>
                '150.000000',
        ];

        ksort(
            $breakdown
        );

        if (
            $metrics->demandTarget
                !== '400.000000'
            || $metrics->directSafeSupply
                !== '250.000000'
            || $metrics->atRiskSupply
                !== '150.000000'
            || $metrics->fallbackSafeSupply
                !== '150.000000'
            || $metrics->totalSafeSupply
                !== '400.000000'
            || $metrics->coveragePercent
                !== '100.00'
            || $metrics->shortfall
                !== '0.000000'
            || $metrics->surplus
                !== '0.000000'
            || ! $metrics->volumeReady
            || $metrics
                ->contributorOrganizationIds
                !== $contributorIds
            || $metrics
                ->contributorSafeSupplyByOrganization
                !== $breakdown
        ) {
            $this->fail(
                'Canonical M06 metrics tidak menghasilkan recovered state Safe 400 / Shortfall 0.'
            );
        }
    }

    private function assertNetworkOperator(
        User $actor
    ): void {
        if (
            ! $actor->isKdkmpOperator()
            || ! $actor->hasValidIdentityContext()
            || $actor->email
                !== DemoIdentifiers
                    ::NETWORK_OPERATOR_EMAIL
            || $actor->organization?->code
                !== DemoIdentifiers
                    ::NETWORK_KDKMP_CODE
        ) {
            throw new AuthorizationException(
                'Fallback Offer demo hanya dapat disiapkan oleh Operator demo KDKMP Mitra Lestari.'
            );
        }
    }

    private function assertNetworkManager(
        User $actor
    ): void {
        if (
            ! $actor->isKdkmpManager()
            || ! $actor->hasValidIdentityContext()
            || $actor->email
                !== DemoIdentifiers
                    ::NETWORK_MANAGER_EMAIL
            || $actor->organization?->code
                !== DemoIdentifiers
                    ::NETWORK_KDKMP_CODE
        ) {
            throw new AuthorizationException(
                'Fallback Offer demo hanya dapat disetujui oleh Manager demo KDKMP Mitra Lestari.'
            );
        }
    }

    private function assertPrimaryManager(
        User $actor
    ): void {
        if (
            ! $actor->isKdkmpManager()
            || ! $actor->hasValidIdentityContext()
            || $actor->email
                !== DemoIdentifiers
                    ::PRIMARY_MANAGER_EMAIL
            || $actor->organization?->code
                !== DemoIdentifiers
                    ::PRIMARY_KDKMP_CODE
        ) {
            throw new AuthorizationException(
                'Fallback Offer demo hanya dapat diterima oleh Manager demo KDKMP Tani Sejahtera.'
            );
        }
    }

    private function fail(
        string $message
    ): never {
        throw ValidationException::withMessages([
            'demo_scenario' =>
                $message,
        ]);
    }
}