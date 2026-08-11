<?php

namespace Tests\Feature\Fallback;

use App\Enums\CommitmentApprovalStatus;
use App\Enums\CommitmentLifecycleStatus;
use App\Enums\FallbackOfferStatus;
use App\Enums\FallbackRequestStatus;
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
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FallbackOfferHttpContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_network_operator_can_create_source_backed_draft_and_server_derives_protected_fields(): void
    {
        $context =
            $this->createOpenRequestContext(
                'CREATE'
            );

        $source =
            $this->createEligibleSource(
                $context,
                $context['network'],
                $context['networkOperator'],
                'CREATE-SOURCE'
            );

        $otherUnit =
            Unit::create([
                'code' =>
                    'OTHER-OFFER-UNIT',

                'name' =>
                    'Other Offer Unit',

                'symbol' =>
                    'oth',

                'decimal_precision' =>
                    6,

                'is_active' =>
                    true,
            ]);

        $this->actingAs(
            $context['networkOperator']
        )
            ->post(
                '/kdkmp/fallback-network/'
                .$context['request']->id
                .'/offers',
                [
                    'offered_volume' =>
                        '160.000000',

                    'availability_note' =>
                        'Eligible supplier capacity.',

                    'expires_at' =>
                        '2026-08-19 12:00:00',

                    'source_commitment_ids' => [
                        $source->id,
                    ],

                    /*
                     * Malicious protected fields.
                     *
                     * Tidak termasuk validated()
                     * FormRequest sehingga service
                     * menerima server-controlled
                     * payload saja.
                     */
                    'fallback_request_id' =>
                        999999,

                    'supplier_organization_id' =>
                        $context['primary']->id,

                    'unit_id' =>
                        $otherUnit->id,

                    'accepted_volume' =>
                        '999.000000',

                    'status' =>
                        FallbackOfferStatus
                            ::ACCEPTED
                            ->value,
                ]
            );

        $offer =
            FallbackOffer::query()
                ->where(
                    'created_by',
                    $context[
                        'networkOperator'
                    ]->id
                )
                ->firstOrFail();

        $this->assertSame(
            $context['request']->id,
            $offer->fallback_request_id
        );

        $this->assertSame(
            $context['network']->id,
            $offer
                ->supplier_organization_id
        );

        $this->assertSame(
            $context['unit']->id,
            $offer->unit_id
        );

        $this->assertSame(
            FallbackOfferStatus::DRAFT,
            $offer->status
        );

        $this->assertSame(
            '0.000000',
            (string)
            $offer->accepted_volume
        );

        $this->assertDatabaseHas(
            'fallback_offer_sources',
            [
                'fallback_offer_id' =>
                    $offer->id,

                'supply_commitment_id' =>
                    $source->id,

                'reserved_volume' =>
                    '0.000000',

                'allocated_volume' =>
                    '0.000000',

                'released_volume' =>
                    '0.000000',
            ]
        );
    }

    public function test_offer_store_rejects_cross_organization_source_commitment_injection(): void
    {
        $context =
            $this->createOpenRequestContext(
                'SOURCE-INJECTION'
            );

        $unrelated =
            $this->createOrganization(
                OrganizationType::KDKMP,
                'KDKMP-OFFER-UNRELATED-SOURCE'
            );

        $unrelatedOperator =
            $this->createKdkmpUser(
                $unrelated,
                UserRole::KDKMP_OPERATOR
            );

        $foreignSource =
            $this->createEligibleSource(
                $context,
                $unrelated,
                $unrelatedOperator,
                'FOREIGN-SOURCE'
            );

        $this->actingAs(
            $context['networkOperator']
        )
            ->from(
                '/kdkmp/fallback-network/'
                .$context['request']->id
            )
            ->post(
                '/kdkmp/fallback-network/'
                .$context['request']->id
                .'/offers',
                [
                    'offered_volume' =>
                        '50.000000',

                    'availability_note' =>
                        'Cross tenant attempt.',

                    'expires_at' =>
                        '2026-08-19 12:00:00',

                    'source_commitment_ids' => [
                        $foreignSource->id,
                    ],
                ]
            )
            ->assertSessionHasErrors(
                'source_commitment_ids.0'
            );

        $this->assertDatabaseMissing(
            'fallback_offers',
            [
                'created_by' =>
                    $context[
                        'networkOperator'
                    ]->id,
            ]
        );
    }

    public function test_network_operator_can_submit_own_draft_but_manager_cannot_use_operator_submit_command(): void
    {
        $context =
            $this->createDraftOfferContext(
                'SUBMIT'
            );

        $this->actingAs(
            $context['networkManager']
        )
            ->post(
                '/kdkmp/fallback-offers/'
                .$context['offer']->id
                .'/submit'
            )
            ->assertForbidden();

        $this->assertSame(
            FallbackOfferStatus::DRAFT,
            $context['offer']
                ->fresh()
                ->status
        );

        $this->actingAs(
            $context['networkOperator']
        )
            ->post(
                '/kdkmp/fallback-offers/'
                .$context['offer']->id
                .'/submit'
            )
            ->assertRedirect(
                route(
                    'kdkmp.fallback-offers.show',
                    $context['offer']
                )
            );

        $offer =
            $context['offer']
                ->fresh();

        $this->assertSame(
            FallbackOfferStatus
                ::PENDING_APPROVAL,
            $offer->status
        );

        $this->assertSame(
            $context['networkOperator']->id,
            $offer->submitted_by
        );

        /*
         * Submit belum reserve.
         */
        $source =
            $offer
                ->sources()
                ->firstOrFail();

        $this->assertSame(
            '0.000000',
            (string)
            $source->reserved_volume
        );
    }

    public function test_supplier_manager_review_queue_contains_only_own_pending_offers(): void
    {
        $contextA =
            $this->createPendingOfferContext(
                'QUEUE-A'
            );

        $contextB =
            $this->createPendingOfferContext(
                'QUEUE-B'
            );

        $this->actingAs(
            $contextA['networkManager']
        )
            ->get(
                '/kdkmp/manager/outgoing-offers'
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'Kdkmp/Manager/OutgoingOffers/Index'
                        )
                        ->has(
                            'offers',
                            1
                        )
                        ->where(
                            'offers.0.id',
                            $contextA[
                                'offer'
                            ]->id
                        )
            );

        $this->assertDatabaseHas(
            'fallback_offers',
            [
                'id' =>
                    $contextB['offer']->id,
            ]
        );
    }

    public function test_supplier_private_offer_detail_exposes_own_source_context_but_requester_cannot_open_it(): void
    {
        $context =
            $this->createDraftOfferContext(
                'SUPPLIER-PRIVATE'
            );

        $producerName =
            $context['source']
                ->producer
                ->name;

        $this->actingAs(
            $context['networkOperator']
        )
            ->get(
                '/kdkmp/fallback-offers/'
                .$context['offer']->id
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'Kdkmp/FallbackOffers/Show'
                        )
                        ->where(
                            'offer.id',
                            $context['offer']->id
                        )
                        ->has(
                            'offer.sources',
                            1
                        )
                        ->where(
                            'offer.sources.0.supply_commitment_id',
                            $context['source']->id
                        )
                        ->where(
                            'offer.sources.0.producer.name',
                            $producerName
                        )
                        ->has(
                            'offer.sources.0.ledger'
                        )
            );

        /*
         * Requester memang dapat melihat aggregate
         * AVAILABLE Offer lewat incoming surface,
         * tetapi tidak supplier-private detail.
         */
        $this->actingAs(
            $context['manager']
        )
            ->get(
                '/kdkmp/fallback-offers/'
                .$context['offer']->id
            )
            ->assertForbidden();
    }

    public function test_supplier_manager_approval_reserves_full_offer_before_available(): void
    {
        $context =
            $this->createPendingOfferContext(
                'APPROVE'
            );

        $this->actingAs(
            $context['networkManager']
        )
            ->post(
                '/kdkmp/manager/outgoing-offers/'
                .$context['offer']->id
                .'/approve'
            )
            ->assertRedirect(
                route(
                    'kdkmp.fallback-offers.show',
                    $context['offer']
                )
            );

        $offer =
            $context['offer']
                ->fresh();

        $this->assertSame(
            FallbackOfferStatus::AVAILABLE,
            $offer->status
        );

        $this->assertSame(
            $context['networkManager']->id,
            $offer->supplier_reviewed_by
        );

        $source =
            FallbackOfferSource::query()
                ->where(
                    'fallback_offer_id',
                    $offer->id
                )
                ->firstOrFail();

        $this->assertSame(
            '160.000000',
            (string)
            $source->reserved_volume
        );

        $this->assertSame(
            '0.000000',
            (string)
            $source->allocated_volume
        );

        $this->assertSame(
            '0.000000',
            (string)
            $source->released_volume
        );

        $this->assertNotNull(
            $source->reserved_at
        );
    }

    public function test_operator_cannot_use_supplier_manager_approval_or_reject_commands(): void
    {
        $context =
            $this->createPendingOfferContext(
                'SUPPLIER-ROLE'
            );

        $base =
            '/kdkmp/manager/outgoing-offers/'
            .$context['offer']->id;

        $this->actingAs(
            $context['networkOperator']
        )
            ->post(
                $base.'/approve'
            )
            ->assertForbidden();

        $this->actingAs(
            $context['networkOperator']
        )
            ->post(
                $base.'/reject',
                [
                    'supplier_review_reason' =>
                        'Operator tidak boleh review.',
                ]
            )
            ->assertForbidden();

        $this->assertSame(
            FallbackOfferStatus
                ::PENDING_APPROVAL,
            $context['offer']
                ->fresh()
                ->status
        );
    }

    public function test_requester_incoming_queue_contains_only_available_offers_for_own_requests(): void
    {
        $context =
            $this->createAvailableOfferContext(
                'INCOMING-QUEUE'
            );

        /*
         * Offer kedua untuk Request yang sama
         * dibiarkan PENDING_APPROVAL.
         *
         * Existing AVAILABLE reserve 160 dari
         * source minimum 200, jadi remaining
         * source capacity masih 40.
         */
        $pendingOffer =
            app(
                FallbackOfferService::class
            )->createDraft(
                $context['networkOperator'],
                $context['request'],
                [
                    'offered_volume' =>
                        '30.000000',

                    'availability_note' =>
                        'Pending offer tidak boleh masuk incoming.',

                    'expires_at' =>
                        '2026-08-19 13:00:00',

                    'source_commitment_ids' => [
                        $context['source']->id,
                    ],
                ]
            );

        $pendingOffer =
            app(
                FallbackOfferService::class
            )->submit(
                $context['networkOperator'],
                $pendingOffer
            );

        $this->actingAs(
            $context['manager']
        )
            ->get(
                '/kdkmp/manager/incoming-offers'
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'Kdkmp/Manager/IncomingOffers/Index'
                        )
                        ->has(
                            'offers',
                            1
                        )
                        ->where(
                            'offers.0.id',
                            $context['offer']->id
                        )
            );

        $this->assertSame(
            FallbackOfferStatus
                ::PENDING_APPROVAL,
            $pendingOffer
                ->fresh()
                ->status
        );
    }

    public function test_requester_incoming_detail_never_exposes_supplier_source_commitment_producer_or_ledger(): void
    {
        $context =
            $this->createAvailableOfferContext(
                'REQUESTER-PRIVACY'
            );

        $producerName =
            $context['source']
                ->producer
                ->name;

        $response =
            $this->actingAs(
                $context['manager']
            )
                ->get(
                    '/kdkmp/manager/incoming-offers/'
                    .$context['offer']->id
                );

        $response
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'Kdkmp/Manager/IncomingOffers/Show'
                        )
                        ->where(
                            'offer.id',
                            $context['offer']->id
                        )
                        ->where(
                            'offer.supplier_organization.id',
                            $context['network']->id
                        )
                        ->where(
                            'offer.offered_volume',
                            '160.000000'
                        )
                        ->where(
                            'can.accept',
                            true
                        )
                        ->where(
                            'can.reject',
                            true
                        )
                        ->missing(
                            'offer.sources'
                        )
                        ->missing(
                            'offer.source_commitment_ids'
                        )
                        ->missing(
                            'offer.producer'
                        )
                        ->missing(
                            'offer.ledger'
                        )
                        ->missing(
                            'offer.reserved_volume'
                        )
                        ->missing(
                            'offer.allocated_volume'
                        )
                        ->missing(
                            'offer.released_volume'
                        )
            );

        /*
         * Producer supplier juga tidak boleh
         * terselip sebagai text serialized prop.
         */
        $response->assertDontSee(
            $producerName
        );
    }

    public function test_requester_manager_can_partially_accept_available_offer_and_unused_reserve_is_released(): void
    {
        $context =
            $this->createAvailableOfferContext(
                'PARTIAL-ACCEPT'
            );

        /*
         * Request = 150
         * Offer   = 160
         *
         * Accepted 150.
         * Unused reserve 10 harus release.
         */
        $this->actingAs(
            $context['manager']
        )
            ->post(
                '/kdkmp/manager/incoming-offers/'
                .$context['offer']->id
                .'/accept',
                [
                    'accepted_volume' =>
                        '150.000000',
                ]
            )
            ->assertRedirect(
                route(
                    'kdkmp.manager.incoming-offers.index'
                )
            );

        $offer =
            $context['offer']
                ->fresh();

        $request =
            $context['request']
                ->fresh();

        $source =
            FallbackOfferSource::query()
                ->where(
                    'fallback_offer_id',
                    $offer->id
                )
                ->firstOrFail();

        $this->assertSame(
            FallbackOfferStatus::ACCEPTED,
            $offer->status
        );

        $this->assertSame(
            '150.000000',
            (string)
            $offer->accepted_volume
        );

        $this->assertSame(
            $context['manager']->id,
            $offer->requester_decided_by
        );

        $this->assertSame(
            '160.000000',
            (string)
            $source->reserved_volume
        );

        $this->assertSame(
            '150.000000',
            (string)
            $source->allocated_volume
        );

        $this->assertSame(
            '10.000000',
            (string)
            $source->released_volume
        );

        $this->assertSame(
            FallbackRequestStatus::FULFILLED,
            $request->status
        );

        $this->assertNotNull(
            $request->fulfilled_at
        );
    }

    public function test_requester_reject_releases_available_reserve(): void
    {
        $context =
            $this->createAvailableOfferContext(
                'REQUESTER-REJECT'
            );

        $this->actingAs(
            $context['manager']
        )
            ->post(
                '/kdkmp/manager/incoming-offers/'
                .$context['offer']->id
                .'/reject',
                [
                    'requester_decision_reason' =>
                        'Pasokan alternatif sudah tersedia.',
                ]
            )
            ->assertRedirect(
                route(
                    'kdkmp.manager.incoming-offers.index'
                )
            );

        $offer =
            $context['offer']
                ->fresh();

        $source =
            FallbackOfferSource::query()
                ->where(
                    'fallback_offer_id',
                    $offer->id
                )
                ->firstOrFail();

        $this->assertSame(
            FallbackOfferStatus::REJECTED,
            $offer->status
        );

        $this->assertSame(
            '160.000000',
            (string)
            $source->released_volume
        );

        $this->assertSame(
            '0.000000',
            (string)
            $source->allocated_volume
        );

        $this->assertSame(
            'Pasokan alternatif sudah tersedia.',
            $offer
                ->requester_decision_reason
        );
    }

    public function test_supplier_manager_withdraws_available_offer_and_releases_reserve(): void
    {
        $context =
            $this->createAvailableOfferContext(
                'WITHDRAW'
            );

        $this->actingAs(
            $context['networkManager']
        )
            ->post(
                '/kdkmp/manager/outgoing-offers/'
                .$context['offer']->id
                .'/withdraw',
                [
                    'withdrawal_reason' =>
                        'Kapasitas dialihkan karena perubahan operasional.',
                ]
            )
            ->assertRedirect(
                route(
                    'kdkmp.fallback-offers.show',
                    $context['offer']
                )
            );

        $offer =
            $context['offer']
                ->fresh();

        $source =
            FallbackOfferSource::query()
                ->where(
                    'fallback_offer_id',
                    $offer->id
                )
                ->firstOrFail();

        $this->assertSame(
            FallbackOfferStatus::WITHDRAWN,
            $offer->status
        );

        $this->assertSame(
            '160.000000',
            (string)
            $source->released_volume
        );

        $this->assertSame(
            $context['networkManager']->id,
            $offer->withdrawn_by
        );
    }

    public function test_unrelated_manager_and_supplier_manager_cannot_accept_offer_for_requester(): void
    {
        $context =
            $this->createAvailableOfferContext(
                'ACCEPT-AUTH'
            );

        $unrelated =
            $this->createOrganization(
                OrganizationType::KDKMP,
                'KDKMP-OFFER-UNRELATED-MANAGER'
            );

        $unrelatedManager =
            $this->createKdkmpUser(
                $unrelated,
                UserRole::KDKMP_MANAGER
            );

        $acceptUrl =
            '/kdkmp/manager/incoming-offers/'
            .$context['offer']->id
            .'/accept';

        $this->actingAs(
            $unrelatedManager
        )
            ->post(
                $acceptUrl,
                [
                    'accepted_volume' =>
                        '150.000000',
                ]
            )
            ->assertForbidden();

        $this->actingAs(
            $context['networkManager']
        )
            ->post(
                $acceptUrl,
                [
                    'accepted_volume' =>
                        '150.000000',
                ]
            )
            ->assertForbidden();

        $this->assertSame(
            FallbackOfferStatus::AVAILABLE,
            $context['offer']
                ->fresh()
                ->status
        );

        $this->assertSame(
            '0.000000',
            (string)
            $context['offer']
                ->fresh()
                ->accepted_volume
        );
    }

    public function test_system_admin_and_sppg_have_no_operational_fallback_offer_access(): void
    {
        $context =
            $this->createAvailableOfferContext(
                'NON-OPERATIONAL'
            );

        $this->actingAs(
            $context['admin']
        )
            ->get(
                '/kdkmp/fallback-offers'
            )
            ->assertForbidden();

        $this->actingAs(
            $context['sppgUser']
        )
            ->get(
                '/kdkmp/fallback-offers'
            )
            ->assertForbidden();

        $this->actingAs(
            $context['admin']
        )
            ->post(
                '/kdkmp/manager/incoming-offers/'
                .$context['offer']->id
                .'/accept',
                [
                    'accepted_volume' =>
                        '150.000000',
                ]
            )
            ->assertForbidden();

        $this->actingAs(
            $context['sppgUser']
        )
            ->post(
                '/kdkmp/manager/incoming-offers/'
                .$context['offer']->id
                .'/accept',
                [
                    'accepted_volume' =>
                        '150.000000',
                ]
            )
            ->assertForbidden();

        $this->assertSame(
            FallbackOfferStatus::AVAILABLE,
            $context['offer']
                ->fresh()
                ->status
        );
    }

    public function test_repeated_http_approval_and_same_volume_acceptance_are_idempotent(): void
    {
        $context =
            $this->createPendingOfferContext(
                'HTTP-IDEMPOTENT'
            );

        $approveUrl =
            '/kdkmp/manager/outgoing-offers/'
            .$context['offer']->id
            .'/approve';

        /*
         * First approval.
         */
        $this->actingAs(
            $context['networkManager']
        )
            ->post(
                $approveUrl
            )
            ->assertRedirect();

        /*
         * Retry approval.
         */
        $this->actingAs(
            $context['networkManager']
        )
            ->post(
                $approveUrl
            )
            ->assertRedirect();

        $source =
            FallbackOfferSource::query()
                ->where(
                    'fallback_offer_id',
                    $context['offer']->id
                )
                ->firstOrFail();

        /*
         * Reserve tidak menjadi 320.
         */
        $this->assertSame(
            '160.000000',
            (string)
            $source->reserved_volume
        );

        $acceptUrl =
            '/kdkmp/manager/incoming-offers/'
            .$context['offer']->id
            .'/accept';

        /*
         * First Accept.
         */
        $this->actingAs(
            $context['manager']
        )
            ->post(
                $acceptUrl,
                [
                    'accepted_volume' =>
                        '150.000000',
                ]
            )
            ->assertRedirect();

        /*
         * Same-volume retry setelah Request bahkan
         * sudah FULFILLED tetap idempotent.
         */
        $this->actingAs(
            $context['manager']
        )
            ->post(
                $acceptUrl,
                [
                    'accepted_volume' =>
                        '150.000000',
                ]
            )
            ->assertRedirect();

        $offer =
            $context['offer']
                ->fresh();

        $source->refresh();

        $this->assertSame(
            FallbackOfferStatus::ACCEPTED,
            $offer->status
        );

        $this->assertSame(
            '150.000000',
            (string)
            $offer->accepted_volume
        );

        $this->assertSame(
            '160.000000',
            (string)
            $source->reserved_volume
        );

        $this->assertSame(
            '150.000000',
            (string)
            $source->allocated_volume
        );

        $this->assertSame(
            '10.000000',
            (string)
            $source->released_volume
        );
    }


    public function test_accepted_network_supplier_can_open_contributor_readiness_without_primary_forecast_access(): void
{
    $context =
        $this->createAvailableOfferContext(
            'NETWORK-CONTRIBUTOR-READINESS'
        );

    /*
     * AVAILABLE Offer mempunyai source-backed
     * reserved capacity dari NETWORK KDKMP.
     *
     * Setelah requester Manager menerima 150 kg,
     * source allocation menjadi effective
     * Fallback Safe Supply sehingga NETWORK
     * organization masuk canonical contributor set.
     */
    $this->actingAs(
        $context['manager']
    )
        ->post(
            '/kdkmp/manager/incoming-offers/'
            .$context['offer']->id
            .'/accept',
            [
                'accepted_volume' =>
                    '150.000000',
            ]
        )
        ->assertRedirect(
            route(
                'kdkmp.manager.incoming-offers.index'
            )
        );

    $this->assertSame(
        FallbackOfferStatus::ACCEPTED,
        $context['offer']
            ->fresh()
            ->status
    );

    /*
     * NETWORK contributor tidak memperoleh
     * PRIMARY planning surface.
     */
    $this->actingAs(
        $context['networkOperator']
    )
        ->get(
            '/kdkmp/forecasts/'
            .$context['forecast']->id
        )
        ->assertForbidden();

    /*
     * Tetapi NETWORK yang sekarang merupakan
     * current effective contributor wajib
     * mempunyai jalur menuju readiness miliknya.
     */
    $this->actingAs(
        $context['networkOperator']
    )
        ->get(
            '/kdkmp/contributor-readiness/'
            .$context['forecast']->id
        )
        ->assertOk()
        ->assertInertia(
            fn (
                Assert $page
            ) =>
                $page
                    ->component(
                        'Kdkmp/ContributorReadiness/Show'
                    )
                    ->where(
                        'forecast.id',
                        $context['forecast']->id
                    )
                    ->where(
                        'readiness.organization_id',
                        $context['network']->id
                    )
                    ->where(
                        'readiness.is_contributor',
                        true
                    )
                    ->where(
                        'readiness.logistics_ready',
                        false
                    )
                    ->where(
                        'readiness.document_ready',
                        false
                    )
                    ->where(
                        'checklists.logistics',
                        null
                    )
                    ->where(
                        'checklists.document',
                        null
                    )
        );
}
    public function test_fallback_offer_http_surface_has_only_explicit_lifecycle_commands(): void
    {
        $routes =
            collect(
                Route::getRoutes()
            )
                ->filter(
                    fn ($route) =>
                        str_starts_with(
                            $route->uri(),
                            'kdkmp/fallback-offers'
                        )
                        || str_starts_with(
                            $route->uri(),
                            'kdkmp/fallback-network'
                        )
                        || str_starts_with(
                            $route->uri(),
                            'kdkmp/manager/outgoing-offers'
                        )
                        || str_starts_with(
                            $route->uri(),
                            'kdkmp/manager/incoming-offers'
                        )
                );

        /*
         * Tidak ada generic PUT/PATCH/DELETE
         * untuk critical Offer lifecycle.
         */
        $this->assertFalse(
            $routes->contains(
                fn ($route) =>
                    in_array(
                        'PUT',
                        $route->methods(),
                        true
                    )
                    || in_array(
                        'PATCH',
                        $route->methods(),
                        true
                    )
                    || in_array(
                        'DELETE',
                        $route->methods(),
                        true
                    )
            )
        );

        $this->assertTrue(
            Route::has(
                'kdkmp.fallback-offers.store'
            )
        );

        $this->assertTrue(
            Route::has(
                'kdkmp.fallback-offers.submit'
            )
        );

        $this->assertTrue(
            Route::has(
                'kdkmp.manager.outgoing-offers.approve'
            )
        );

        $this->assertTrue(
            Route::has(
                'kdkmp.manager.outgoing-offers.reject'
            )
        );

        $this->assertTrue(
            Route::has(
                'kdkmp.manager.outgoing-offers.withdraw'
            )
        );

        $this->assertTrue(
            Route::has(
                'kdkmp.manager.incoming-offers.accept'
            )
        );

        $this->assertTrue(
            Route::has(
                'kdkmp.manager.incoming-offers.reject'
            )
        );

        $this->assertFalse(
            Route::has(
                'kdkmp.fallback-offers.destroy'
            )
        );
    }

    private function createDraftOfferContext(
        string $suffix
    ): array {
        $context =
            $this->createOpenRequestContext(
                $suffix
            );

        $source =
            $this->createEligibleSource(
                $context,
                $context['network'],
                $context['networkOperator'],
                "{$suffix}-SOURCE"
            );

        $offer =
            app(
                FallbackOfferService::class
            )->createDraft(
                $context['networkOperator'],
                $context['request'],
                [
                    'offered_volume' =>
                        '160.000000',

                    'availability_note' =>
                        'HTTP Offer fixture.',

                    'expires_at' =>
                        '2026-08-19 12:00:00',

                    'source_commitment_ids' => [
                        $source->id,
                    ],
                ]
            );

        return [
            ...$context,

            'source' =>
                $source,

            'offer' =>
                $offer,
        ];
    }

    private function createPendingOfferContext(
        string $suffix
    ): array {
        $context =
            $this->createDraftOfferContext(
                $suffix
            );

        $offer =
            app(
                FallbackOfferService::class
            )->submit(
                $context['networkOperator'],
                $context['offer']
            );

        return [
            ...$context,

            'offer' =>
                $offer,
        ];
    }

    private function createAvailableOfferContext(
        string $suffix
    ): array {
        $context =
            $this->createPendingOfferContext(
                $suffix
            );

        $offer =
            app(
                FallbackOfferService::class
            )->approveForAvailability(
                $context['networkManager'],
                $context['offer']
            );

        return [
            ...$context,

            'offer' =>
                $offer,
        ];
    }

    private function createOpenRequestContext(
        string $suffix
    ): array {
        $context =
            $this->createBaseContext(
                $suffix
            );

        $service =
            app(
                FallbackRequestService::class
            );

        $request =
            $service->createDraft(
                $context['operator'],
                $context['forecast'],
                [
                    'requested_volume' =>
                        '150.000000',

                    'response_deadline_at' =>
                        '2026-08-19 18:00:00',

                    'broadcast_note' =>
                        'Fallback Offer HTTP fixture.',
                ]
            );

        $request =
            $service->submit(
                $context['operator'],
                $request
            );

        $request =
            $service->approveBroadcast(
                $context['manager'],
                $request
            );

        return [
            ...$context,

            'request' =>
                $request,
        ];
    }

    private function createBaseContext(
        string $suffix
    ): array {
        [$unit, $commodity] =
            $this->createReferenceData(
                $suffix
            );

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
                "SPPG-OFFER-{$suffix}"
            );

        $primary =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-OFFER-PRIMARY-{$suffix}"
            );

        $network =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-OFFER-NETWORK-{$suffix}"
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
                        'Fallback Offer HTTP Forecast.',
                ]
            );

        $forecast =
            $forecastService->publish(
                $sppgUser,
                $forecast,
                $forecast->version
            );

        return [
            'admin' =>
                $admin,

            'sppg' =>
                $sppg,

            'primary' =>
                $primary,

            'network' =>
                $network,

            'sppgUser' =>
                $sppgUser,

            'operator' =>
                $operator,

            'manager' =>
                $manager,

            'networkOperator' =>
                $networkOperator,

            'networkManager' =>
                $networkManager,

            'unit' =>
                $unit,

            'commodity' =>
                $commodity,

            'forecast' =>
                $forecast,
        ];
    }

    private function createEligibleSource(
        array $context,
        Organization $organization,
        User $creator,
        string $suffix,
        string $minimum = '200.000000',
    ): SupplyCommitment {
        $producer =
            Producer::create([
                'organization_id' =>
                    $organization->id,

                'producer_code' =>
                    "PROD-OFFER-{$suffix}",

                'name' =>
                    "Produsen {$suffix}",

                'village' =>
                    'Desa Test',

                'district' =>
                    'Kecamatan Test',

                'contact_phone' =>
                    '081234567890',

                'notes' =>
                    'Fallback source fixture.',

                'is_active' =>
                    true,

                'created_by' =>
                    $creator->id,
            ]);

        $commitment =
            SupplyCommitment::create([
                'forecast_id' =>
                    $context['forecast']->id,

                'organization_id' =>
                    $organization->id,

                'producer_id' =>
                    $producer->id,

                'expected_harvest_id' =>
                    null,

                'commodity_id' =>
                    $context['commodity']->id,

                'active_version_id' =>
                    null,

                'lifecycle_status' =>
                    CommitmentLifecycleStatus::ACTIVE,

                'current_confidence' =>
                    SupplyConfidence::GREEN,

                'last_confidence_verified_at' =>
                    now(),

                'created_by' =>
                    $creator->id,
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
                    '220.000000',

                'unit_id' =>
                    $context['unit']->id,

                'availability_start_at' =>
                    '2026-08-20 07:00:00',

                'availability_end_at' =>
                    '2026-08-20 13:00:00',

                'notes' =>
                    'Approved NETWORK fallback source.',

                'approval_status' =>
                    CommitmentApprovalStatus::APPROVED,

                'change_reason' =>
                    null,

                'operator_justification' =>
                    null,

                'created_by' =>
                    $creator->id,

                'submitted_by' =>
                    $creator->id,

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
            ->fresh([
                'producer',
                'activeVersion',
            ]);
    }

    private function createReferenceData(
        string $suffix
    ): array {
        $unit =
            Unit::create([
                'code' =>
                    "KG-OFFER-{$suffix}",

                'name' =>
                    "Kilogram {$suffix}",

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
                    "COM-OFFER-{$suffix}",

                'name' =>
                    "Commodity {$suffix}",

                'default_unit_id' =>
                    $unit->id,

                'harvest_behavior' =>
                    null,

                'notes' =>
                    null,

                'is_active' =>
                    true,
            ]);

        return [
            $unit,
            $commodity,
        ];
    }

    private function createOrganization(
        OrganizationType $type,
        string $code
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
        UserRole $role
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