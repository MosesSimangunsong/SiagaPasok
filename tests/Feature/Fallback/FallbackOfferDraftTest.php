<?php

namespace Tests\Feature\Fallback;

use App\Enums\FallbackOfferStatus;
use App\Enums\FallbackRequestStatus;
use App\Enums\ForecastStatus;
use App\Enums\NetworkRole;
use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Enums\SupplyConfidence;
use App\Models\Commodity;
use App\Models\CommitmentVersion;
use App\Models\DemandForecast;
use App\Models\FallbackRequest;
use App\Models\Organization;
use App\Models\Producer;
use App\Models\SupplyNetworkLink;
use App\Models\Unit;
use App\Models\User;
use App\Services\Commitment\CommitmentWorkflowService;
use App\Services\Fallback\FallbackCapacityService;
use App\Services\Fallback\FallbackOfferService;
use App\Services\Supply\SupplyMetricsService;
use App\Services\Commitment\ConfidenceService;
use App\Services\Forecast\DemandForecastService;
use App\Services\Fallback\FallbackRequestService;
use App\Support\FixedScaleDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FallbackOfferDraftTest extends TestCase
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

    public function test_network_operator_can_create_source_backed_offer_draft(): void
    {
        $context =
            $this->createContext(
                'CREATE'
            );

        $source =
            $this->approvedSource(
                $context,
                '160.000000'
            );

        $offer =
            $this->service()
                ->createDraft(
                    $context[
                        'network_operator'
                    ],
                    $context['request'],
                    [
                        'offered_volume' =>
                            '150.000000',

                        'availability_note' =>
                            'Kapasitas tersedia sesuai periode forecast.',

                        'expires_at' =>
                            '2026-08-18 18:00:00',

                        'source_commitment_ids' => [
                            $source->id,
                        ],
                    ]
                );

        $this->assertSame(
            FallbackOfferStatus::DRAFT,
            $offer->status
        );

        $this->assertSame(
            $context['network']->id,
            $offer
                ->supplier_organization_id
        );

        $this->assertSame(
            '150.000000',
            (string)
            $offer->offered_volume
        );

        $this->assertSame(
            '0.000000',
            (string)
            $offer->accepted_volume
        );

        $this->assertSame(
            $context['unit']->id,
            $offer->unit_id
        );

        $this->assertCount(
            1,
            $offer->sources
        );

        $offerSource =
            $offer->sources->first();

        $this->assertSame(
            $source->id,
            $offerSource
                ->supply_commitment_id
        );

        $this->assertSame(
            '0.000000',
            (string)
            $offerSource
                ->reserved_volume
        );

        $this->assertSame(
            '0.000000',
            (string)
            $offerSource
                ->allocated_volume
        );

        $this->assertSame(
            '0.000000',
            (string)
            $offerSource
                ->released_volume
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'FALLBACK_OFFER_CREATED',

                'entity_id' =>
                    $offer->id,
            ]
        );
    }

    public function test_creating_draft_does_not_reduce_available_capacity(): void
    {
        $context =
            $this->createContext(
                'NO-RESERVE'
            );

        $source =
            $this->approvedSource(
                $context,
                '160.000000'
            );

        $capacityService =
            app(
                FallbackCapacityService::class
            );

        $before =
            $capacityService
                ->availableCapacity(
                    $source,
                    $context['forecast'],
                    $context['network']->id
                );

        $this->service()
            ->createDraft(
                $context[
                    'network_operator'
                ],
                $context['request'],
                $this->offerPayload(
                    [$source->id],
                    '150.000000'
                )
            );

        $after =
            $capacityService
                ->availableCapacity(
                    $source->refresh(),
                    $context['forecast'],
                    $context['network']->id
                );

        $this->assertSame(
            '160.000000',
            $before
        );

        $this->assertSame(
            $before,
            $after
        );
    }

    public function test_offered_volume_cannot_exceed_selected_eligible_capacity(): void
    {
        $context =
            $this->createContext(
                'OVER-CAPACITY'
            );

        $source =
            $this->approvedSource(
                $context,
                '120.000000'
            );

        try {
            $this->service()
                ->createDraft(
                    $context[
                        'network_operator'
                    ],
                    $context['request'],
                    $this->offerPayload(
                        [$source->id],
                        '120.000001'
                    )
                );

            $this->fail(
                'Offered volume di atas eligible capacity harus ditolak.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'offered_volume',
                $exception->errors()
            );
        }
    }

    public function test_multiple_sources_can_back_one_offer(): void
    {
        $context =
            $this->createContext(
                'MULTI-SOURCE'
            );

        $first =
            $this->approvedSource(
                $context,
                '80.000000',
                'A'
            );

        $second =
            $this->approvedSource(
                $context,
                '90.000000',
                'B'
            );

        /*
         * 150 > capacity masing-masing source,
         * tetapi <= combined capacity 170.
         */
        $offer =
            $this->service()
                ->createDraft(
                    $context[
                        'network_operator'
                    ],
                    $context['request'],
                    $this->offerPayload(
                        [
                            $first->id,
                            $second->id,
                        ],
                        '150.000000'
                    )
                );

        $this->assertCount(
            2,
            $offer->sources
        );

foreach ($offer->sources as $source) {
    $this->assertSame(
        '0.000000',
        (string)
        $source->reserved_volume
    );
}
    }

    public function test_non_open_request_cannot_receive_offer(): void
    {
        $context =
            $this->createContext(
                'REQUEST-STATE'
            );

        $source =
            $this->approvedSource(
                $context,
                '160.000000'
            );

        $context['request']->update([
            'status' =>
                FallbackRequestStatus
                    ::CANCELLED,

            'cancelled_at' =>
                now(),

            'cancellation_reason' =>
                'Fixture cancellation',
        ]);

        $this->expectException(
            ValidationException::class
        );

        $this->service()
            ->createDraft(
                $context[
                    'network_operator'
                ],
                $context['request'],
                $this->offerPayload([
                    $source->id,
                ])
            );
    }

    public function test_primary_requester_operator_cannot_create_supplier_offer(): void
    {
        $context =
            $this->createContext(
                'REQUESTER-BLOCK'
            );

        $source =
            $this->approvedSource(
                $context,
                '160.000000'
            );

        $this->expectException(
            AuthorizationException::class
        );

        $this->service()
            ->createDraft(
                $context[
                    'primary_operator'
                ],
                $context['request'],
                $this->offerPayload([
                    $source->id,
                ])
            );
    }

    public function test_source_from_other_organization_is_rejected(): void
    {
        $context =
            $this->createContext(
                'OTHER-ORG'
            );

        $otherNetwork =
            $this->createOrganization(
                OrganizationType::KDKMP,
                'KDKMP-FO-OTHER-ORG-SECONDARY'
            );

        $otherOperator =
            User::factory()->create([
                'organization_id' =>
                    $otherNetwork->id,

                'role' =>
                    UserRole::KDKMP_OPERATOR,

                'is_active' =>
                    true,
            ]);

        $otherManager =
            User::factory()->create([
                'organization_id' =>
                    $otherNetwork->id,

                'role' =>
                    UserRole::KDKMP_MANAGER,

                'is_active' =>
                    true,
            ]);

        $otherProducer =
            $this->createProducer(
                $otherNetwork,
                $otherOperator,
                'PROD-FO-OTHER-ORG'
            );

        SupplyNetworkLink::create([
            'sppg_organization_id' =>
                $context['sppg']->id,

            'kdkmp_organization_id' =>
                $otherNetwork->id,

            'network_role' =>
                NetworkRole::NETWORK,

            'is_active' =>
                true,

            'configured_by' =>
                $context['admin']->id,
        ]);

        $otherContext =
            [
                ...$context,

                'network' =>
                    $otherNetwork,

                'network_operator' =>
                    $otherOperator,

                'network_manager' =>
                    $otherManager,

                'network_producer' =>
                    $otherProducer,
            ];

        $otherSource =
            $this->approvedSource(
                $otherContext,
                '160.000000'
            );

        $this->expectException(
            ValidationException::class
        );

        /*
         * Actor adalah original network,
         * source milik other network.
         */
        $this->service()
            ->createDraft(
                $context[
                    'network_operator'
                ],
                $context['request'],
                $this->offerPayload([
                    $otherSource->id,
                ])
            );
    }

    public function test_yellow_source_is_not_eligible_for_offer(): void
    {
        $context =
            $this->createContext(
                'YELLOW'
            );

        $source =
            $this->approvedSource(
                $context,
                '160.000000'
            );

        $source->update([
            'current_confidence' =>
                'YELLOW',
        ]);

        $this->expectException(
            ValidationException::class
        );

        $this->service()
            ->createDraft(
                $context[
                    'network_operator'
                ],
                $context['request'],
                $this->offerPayload([
                    $source->id,
                ])
            );
    }

    public function test_offer_expiry_cannot_exceed_request_deadline(): void
    {
        $context =
            $this->createContext(
                'EXPIRY'
            );

        $source =
            $this->approvedSource(
                $context,
                '160.000000'
            );

        $payload =
            $this->offerPayload([
                $source->id,
            ]);

        $payload['expires_at'] =
            '2026-08-19 12:00:01';

        try {
            $this->service()
                ->createDraft(
                    $context[
                        'network_operator'
                    ],
                    $context['request'],
                    $payload
                );

            $this->fail(
                'Expiry setelah request deadline harus ditolak.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'expires_at',
                $exception->errors()
            );
        }
    }


    public function test_operator_can_submit_offer_without_reserving_capacity(): void
{
    $context =
        $this->createContext(
            'SUBMIT'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $service =
        $this->service();

    $offer =
        $service->createDraft(
            $context[
                'network_operator'
            ],
            $context['request'],
            $this->offerPayload(
                [$source->id],
                '150.000000'
            )
        );

    $submitted =
        $service->submit(
            $context[
                'network_operator'
            ],
            $offer
        );

    $this->assertSame(
        FallbackOfferStatus
            ::PENDING_APPROVAL,
        $submitted->status
    );

    $this->assertSame(
        $context[
            'network_operator'
        ]->id,
        $submitted->submitted_by
    );

    $this->assertNotNull(
        $submitted->submitted_at
    );

    foreach ($submitted->sources as $offerSource) {
        $this->assertSame(
            '0.000000',
            (string)
            $offerSource
                ->reserved_volume
        );
    }

    $this->assertDatabaseHas(
        'audit_logs',
        [
            'action' =>
                'FALLBACK_OFFER_SUBMITTED',

            'entity_id' =>
                $offer->id,
        ]
    );
}

public function test_direct_and_accepted_fallback_combine_into_total_safe_supply(): void
{
    $context =
        $this->createContext(
            'METRICS-DIRECT-PLUS-FALLBACK'
        );

    /*
     * Buat Producer PRIMARY untuk Direct Supply.
     */
    $primaryProducer =
        $this->createProducer(
            $context['primary'],
            $context[
                'primary_operator'
            ],
            'PROD-FO-METRICS-DIRECT-PRIMARY'
        );

    $workflow =
        app(
            CommitmentWorkflowService::class
        );

    $directCommitment =
        $workflow->createDraft(
            $context['primary_operator'],
            [
                'forecast_id' =>
                    $context['forecast']->id,

                'producer_id' =>
                    $primaryProducer->id,

                'expected_harvest_id' =>
                    null,

                'min_volume' =>
                    '250.000000',

                'max_volume' =>
                    '250.000000',

                'unit_id' =>
                    $context['unit']->id,

                'availability_start_at' =>
                    '2026-08-20 08:00:00',

                'availability_end_at' =>
                    '2026-08-25 17:00:00',

                'notes' =>
                    'Primary direct supply fixture',

                'operator_justification' =>
                    null,
            ]
        );

    $directVersion =
        CommitmentVersion::query()
            ->where(
                'commitment_id',
                $directCommitment->id
            )
            ->firstOrFail();

    $directVersion =
        $workflow->submit(
            $context['primary_operator'],
            $directVersion
        );

    $workflow->approve(
        $context['primary_manager'],
        $directVersion
    );

    /*
     * NETWORK fallback = accepted 150.
     */
    $networkSource =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $offer =
        $this->makeAvailableOffer(
            $context,
            $networkSource,
            '160.000000'
        );

    $this->service()
        ->accept(
            $context['primary_manager'],
            $offer,
            '150.000000'
        );

    $metrics =
        app(
            SupplyMetricsService::class
        )->calculate(
            $context['forecast'],
            CarbonImmutable::parse(
                '2026-08-10 10:00:00'
            )
        );

    $this->assertSame(
        '250.000000',
        $metrics->directSafeSupply
    );

    $this->assertSame(
        '150.000000',
        $metrics->fallbackSafeSupply
    );

    $this->assertSame(
        '400.000000',
        $metrics->totalSafeSupply
    );

    $this->assertSame(
        '0.000000',
        $metrics->shortfall
    );

    $this->assertSame(
        '0.000000',
        $metrics->surplus
    );

    $this->assertSame(
        '100.00',
        $metrics->coveragePercent
    );

    $contributors =
        $metrics
            ->contributorOrganizationIds;

    sort(
        $contributors,
        SORT_NUMERIC
    );

    $expectedContributors = [
        $context['primary']->id,
        $context['network']->id,
    ];

    sort(
        $expectedContributors,
        SORT_NUMERIC
    );

    $this->assertSame(
        $expectedContributors,
        $contributors
    );

    $this->assertTrue(
        $metrics->volumeReady
    );
}

public function test_supplier_manager_approval_reserves_entire_offer_before_available(): void
{
    $context =
        $this->createContext(
            'APPROVE'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $service =
        $this->service();

    $offer =
        $service->createDraft(
            $context[
                'network_operator'
            ],
            $context['request'],
            $this->offerPayload(
                [$source->id],
                '150.000000'
            )
        );

    $offer =
        $service->submit(
            $context[
                'network_operator'
            ],
            $offer
        );

    $available =
        $service
            ->approveForAvailability(
                $context[
                    'network_manager'
                ],
                $offer
            );

    $this->assertSame(
        FallbackOfferStatus::AVAILABLE,
        $available->status
    );

    $this->assertSame(
        $context[
            'network_manager'
        ]->id,
        $available
            ->supplier_reviewed_by
    );

    $this->assertNotNull(
        $available
            ->supplier_reviewed_at
    );

    $this->assertSame(
        '0.000000',
        (string)
        $available->accepted_volume
    );

    $this->assertCount(
        1,
        $available->sources
    );

    $offerSource =
        $available->sources->first();

    $this->assertSame(
        '150.000000',
        (string)
        $offerSource
            ->reserved_volume
    );

    $this->assertSame(
        '0.000000',
        (string)
        $offerSource
            ->allocated_volume
    );

    $this->assertSame(
        '0.000000',
        (string)
        $offerSource
            ->released_volume
    );

    $this->assertNotNull(
        $offerSource->reserved_at
    );

    $this->assertDatabaseHas(
        'audit_logs',
        [
            'action' =>
                'FALLBACK_OFFER_AVAILABLE',

            'entity_id' =>
                $offer->id,
        ]
    );
}

public function test_multi_source_approval_reserves_deterministically_and_completely(): void
{
    $context =
        $this->createContext(
            'RESERVE-MULTI'
        );

    $first =
        $this->approvedSource(
            $context,
            '80.000000',
            'A'
        );

    $second =
        $this->approvedSource(
            $context,
            '90.000000',
            'B'
        );

    $service =
        $this->service();

    $offer =
        $service->createDraft(
            $context[
                'network_operator'
            ],
            $context['request'],
            $this->offerPayload(
                [
                    $first->id,
                    $second->id,
                ],
                '150.000000'
            )
        );

    $offer =
        $service->submit(
            $context[
                'network_operator'
            ],
            $offer
        );

    $offer =
        $service
            ->approveForAvailability(
                $context[
                    'network_manager'
                ],
                $offer
            );

    $sources =
        $offer->sources
            ->sortBy(
                'supply_commitment_id'
            )
            ->values();

    $this->assertSame(
        '80.000000',
        (string)
        $sources[0]
            ->reserved_volume
    );

    $this->assertSame(
        '70.000000',
        (string)
        $sources[1]
            ->reserved_volume
    );

    $this->assertSame(
        '150.000000',
        FixedScaleDecimal::from(
            (string)
            $sources[0]
                ->reserved_volume
        )->add(
            FixedScaleDecimal::from(
                (string)
                $sources[1]
                    ->reserved_volume
            )
        )->toString()
    );
}

public function test_repeat_supplier_approval_is_idempotent_and_does_not_double_reserve(): void
{
    $context =
        $this->createContext(
            'APPROVE-IDEMPOTENT'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $service =
        $this->service();

    $offer =
        $service->createDraft(
            $context[
                'network_operator'
            ],
            $context['request'],
            $this->offerPayload(
                [$source->id],
                '150.000000'
            )
        );

    $offer =
        $service->submit(
            $context[
                'network_operator'
            ],
            $offer
        );

    $first =
        $service
            ->approveForAvailability(
                $context[
                    'network_manager'
                ],
                $offer
            );

    $second =
        $service
            ->approveForAvailability(
                $context[
                    'network_manager'
                ],
                $first
            );

    $this->assertSame(
        FallbackOfferStatus::AVAILABLE,
        $second->status
    );

    $this->assertSame(
        '150.000000',
        (string)
        $second->sources
            ->first()
            ->reserved_volume
    );

    $this->assertSame(
        1,
        \App\Models\AuditLog::query()
            ->where(
                'action',
                'FALLBACK_OFFER_AVAILABLE'
            )
            ->where(
                'entity_id',
                $offer->id
            )
            ->count()
    );
}

public function test_maker_cannot_approve_own_offer_after_role_change(): void
{
    $context =
        $this->createContext(
            'OFFER-MAKER-CHECKER'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $service =
        $this->service();

    $offer =
        $service->createDraft(
            $context[
                'network_operator'
            ],
            $context['request'],
            $this->offerPayload([
                $source->id,
            ])
        );

    $offer =
        $service->submit(
            $context[
                'network_operator'
            ],
            $offer
        );

    /*
     * Simulasi role maker berubah setelah submit.
     * Identity tetap sama, sehingga maker-checker
     * harus tetap menolak.
     */
    $context[
        'network_operator'
    ]->update([
        'role' =>
            UserRole::KDKMP_MANAGER,
    ]);

    $context[
        'network_operator'
    ]->refresh();

    $this->expectException(
        AuthorizationException::class
    );

    $service
        ->approveForAvailability(
            $context[
                'network_operator'
            ],
            $offer
        );
}

public function test_source_degradation_before_manager_approval_blocks_available_transition(): void
{
    $context =
        $this->createContext(
            'DEGRADE-BEFORE-APPROVAL'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $service =
        $this->service();

    $offer =
        $service->createDraft(
            $context[
                'network_operator'
            ],
            $context['request'],
            $this->offerPayload([
                $source->id,
            ])
        );

    $offer =
        $service->submit(
            $context[
                'network_operator'
            ],
            $offer
        );

    $source->update([
        'current_confidence' =>
            'YELLOW',
    ]);

    try {
        $service
            ->approveForAvailability(
                $context[
                    'network_manager'
                ],
                $offer
            );

        $this->fail(
            'Offer dengan source yang tidak lagi GREEN tidak boleh AVAILABLE.'
        );
    } catch (
        ValidationException $exception
    ) {
        $this->assertArrayHasKey(
            'offered_volume',
            $exception->errors()
        );
    }

    $offer->refresh();

    $this->assertSame(
        FallbackOfferStatus
            ::PENDING_APPROVAL,
        $offer->status
    );

    $this->assertSame(
        '0.000000',
        (string)
        $offer->sources()
            ->firstOrFail()
            ->reserved_volume
    );
}

public function test_competing_offer_cannot_reserve_capacity_already_reserved_by_first_offer(): void
{
    $context =
        $this->createContext(
            'COMPETING'
        );

    /*
     * Satu source capacity = 150.
     */
    $source =
        $this->approvedSource(
            $context,
            '150.000000'
        );

    $service =
        $this->service();

    /*
     * Dua DRAFT boleh dibuat karena DRAFT belum
     * mempunyai capacity effect.
     */
    $first =
        $service->createDraft(
            $context[
                'network_operator'
            ],
            $context['request'],
            $this->offerPayload(
                [$source->id],
                '100.000000'
            )
        );

    $second =
        $service->createDraft(
            $context[
                'network_operator'
            ],
            $context['request'],
            $this->offerPayload(
                [$source->id],
                '100.000000'
            )
        );

    $first =
        $service->submit(
            $context[
                'network_operator'
            ],
            $first
        );

    $second =
        $service->submit(
            $context[
                'network_operator'
            ],
            $second
        );

    /*
     * Offer pertama berhasil reserve 100.
     */
    $first =
        $service
            ->approveForAvailability(
                $context[
                    'network_manager'
                ],
                $first
            );

    $this->assertSame(
        FallbackOfferStatus::AVAILABLE,
        $first->status
    );

    /*
     * Source tersisa 50 sehingga second offer
     * sebanyak 100 wajib gagal secara atomik.
     */
    try {
        $service
            ->approveForAvailability(
                $context[
                    'network_manager'
                ],
                $second
            );

        $this->fail(
            'Competing Offer seharusnya gagal karena capacity sudah di-reserve Offer pertama.'
        );
    } catch (
        ValidationException $exception
    ) {
        $this->assertArrayHasKey(
            'offered_volume',
            $exception->errors()
        );
    }

    $second->refresh();

    $this->assertSame(
        FallbackOfferStatus
            ::PENDING_APPROVAL,
        $second->status
    );

    $this->assertSame(
        '0.000000',
        (string)
        $second->sources()
            ->firstOrFail()
            ->reserved_volume
    );

    $availableCapacity =
        app(
            FallbackCapacityService::class
        )->availableCapacity(
            $source->refresh(),
            $context['forecast'],
            $context['network']->id
        );

    $this->assertSame(
        '50.000000',
        $availableCapacity
    );
}


public function test_supplier_manager_can_reject_pending_offer(): void
{
    $context =
        $this->createContext(
            'REJECT-SUPPLIER'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $service =
        $this->service();

    $offer =
        $service->createDraft(
            $context['network_operator'],
            $context['request'],
            $this->offerPayload([
                $source->id,
            ])
        );

    $offer =
        $service->submit(
            $context['network_operator'],
            $offer
        );

    $rejected =
        $service->rejectBySupplierManager(
            $context['network_manager'],
            $offer,
            'Kapasitas tidak jadi dipublikasikan.'
        );

    $this->assertSame(
        FallbackOfferStatus::REJECTED,
        $rejected->status
    );

    $this->assertSame(
        '0.000000',
        (string)
        $rejected->sources
            ->first()
            ->reserved_volume
    );

    $this->assertDatabaseHas(
        'audit_logs',
        [
            'action' =>
                'FALLBACK_OFFER_REJECTED_BY_SUPPLIER',

            'entity_id' =>
                $offer->id,
        ]
    );
}

public function test_requester_manager_rejects_available_offer_and_releases_reserve(): void
{
    $context =
        $this->createContext(
            'REJECT-REQUESTER'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $offer =
        $this->makeAvailableOffer(
            $context,
            $source,
            '150.000000'
        );

    $rejected =
        $this->service()
            ->rejectByRequesterManager(
                $context['primary_manager'],
                $offer,
                'Penawaran tidak dipilih.'
            );

    $this->assertSame(
        FallbackOfferStatus::REJECTED,
        $rejected->status
    );

    $ledger =
        $rejected->sources->first();

    $this->assertSame(
        '150.000000',
        (string)
        $ledger->reserved_volume
    );

    $this->assertSame(
        '150.000000',
        (string)
        $ledger->released_volume
    );

    $this->assertSame(
        '0.000000',
        (string)
        $ledger->allocated_volume
    );

    $this->assertSame(
        '160.000000',
        app(
            FallbackCapacityService::class
        )->availableCapacity(
            $source->refresh(),
            $context['forecast'],
            $context['network']->id
        )
    );

    $this->assertDatabaseHas(
        'audit_logs',
        [
            'action' =>
                'FALLBACK_OFFER_RESERVE_RELEASED',

            'entity_id' =>
                $offer->id,
        ]
    );
}

public function test_supplier_manager_can_withdraw_draft_without_reserve(): void
{
    $context =
        $this->createContext(
            'WITHDRAW-DRAFT'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $offer =
        $this->service()
            ->createDraft(
                $context['network_operator'],
                $context['request'],
                $this->offerPayload([
                    $source->id,
                ])
            );

    $withdrawn =
        $this->service()
            ->withdraw(
                $context['network_manager'],
                $offer,
                'Penawaran dibatalkan sebelum submit.'
            );

    $this->assertSame(
        FallbackOfferStatus::WITHDRAWN,
        $withdrawn->status
    );

    $this->assertSame(
        '0.000000',
        (string)
        $withdrawn->sources
            ->first()
            ->released_volume
    );
}

public function test_supplier_manager_withdraws_available_offer_and_releases_reserve(): void
{
    $context =
        $this->createContext(
            'WITHDRAW-AVAILABLE'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $offer =
        $this->makeAvailableOffer(
            $context,
            $source,
            '150.000000'
        );

    $withdrawn =
        $this->service()
            ->withdraw(
                $context['network_manager'],
                $offer,
                'Supplier menarik penawaran.'
            );

    $this->assertSame(
        FallbackOfferStatus::WITHDRAWN,
        $withdrawn->status
    );

    $this->assertSame(
        '150.000000',
        (string)
        $withdrawn->sources
            ->first()
            ->released_volume
    );

    $this->assertSame(
        '160.000000',
        app(
            FallbackCapacityService::class
        )->availableCapacity(
            $source->refresh(),
            $context['forecast'],
            $context['network']->id
        )
    );
}

public function test_operator_cannot_withdraw_offer(): void
{
    $context =
        $this->createContext(
            'WITHDRAW-ROLE'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $offer =
        $this->service()
            ->createDraft(
                $context['network_operator'],
                $context['request'],
                $this->offerPayload([
                    $source->id,
                ])
            );

    $this->expectException(
        AuthorizationException::class
    );

    $this->service()
        ->withdraw(
            $context['network_operator'],
            $offer
        );
}

public function test_accepted_offer_cannot_be_withdrawn_unilaterally(): void
{
    $context =
        $this->createContext(
            'WITHDRAW-ACCEPTED'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $offer =
        $this->makeAvailableOffer(
            $context,
            $source,
            '150.000000'
        );

    /*
     * State guard regression only.
     * Full ACCEPT allocation akan dibuat pada
     * M07.2E.
     */
    $offer->update([
        'status' =>
            FallbackOfferStatus::ACCEPTED,
    ]);

    $this->expectException(
        ValidationException::class
    );

    $this->service()
        ->withdraw(
            $context['network_manager'],
            $offer->refresh()
        );
}

public function test_available_offer_expires_at_exact_expiry_and_releases_reserve(): void
{
    $context =
        $this->createContext(
            'EXPIRE-EXACT'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $offer =
        $this->makeAvailableOffer(
            $context,
            $source,
            '150.000000'
        );

    $expired =
        $this->service()
            ->expire(
                $offer,
                CarbonImmutable::parse(
                    '2026-08-18 18:00:00'
                )
            );

    $this->assertSame(
        FallbackOfferStatus::EXPIRED,
        $expired->status
    );

    $this->assertSame(
        '150.000000',
        (string)
        $expired->sources
            ->first()
            ->released_volume
    );

    $this->assertSame(
        '160.000000',
        app(
            FallbackCapacityService::class
        )->availableCapacity(
            $source->refresh(),
            $context['forecast'],
            $context['network']->id
        )
    );
}

public function test_available_offer_cannot_expire_before_expiry_time(): void
{
    $context =
        $this->createContext(
            'EXPIRE-EARLY'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $offer =
        $this->makeAvailableOffer(
            $context,
            $source,
            '150.000000'
        );

    try {
        $this->service()
            ->expire(
                $offer,
                CarbonImmutable::parse(
                    '2026-08-18 17:59:59'
                )
            );

        $this->fail(
            'Offer tidak boleh expired sebelum expires_at.'
        );
    } catch (
        ValidationException $exception
    ) {
        $this->assertArrayHasKey(
            'expires_at',
            $exception->errors()
        );
    }

    $this->assertSame(
        FallbackOfferStatus::AVAILABLE,
        $offer->refresh()->status
    );

    $this->assertSame(
        '150.000000',
        (string)
        $offer->sources()
            ->firstOrFail()
            ->reserved_volume
    );
}

public function test_repeated_requester_reject_does_not_release_reserve_twice(): void
{
    $context =
        $this->createContext(
            'REJECT-IDEMPOTENT'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $offer =
        $this->makeAvailableOffer(
            $context,
            $source,
            '150.000000'
        );


    $first =
        $this->service()
            ->rejectByRequesterManager(
                $context['primary_manager'],
                $offer
            );

    $second =
        $this->service()
            ->rejectByRequesterManager(
                $context['primary_manager'],
                $first
            );

    $this->assertSame(
        '150.000000',
        (string)
        $second->sources
            ->first()
            ->released_volume
    );

    $this->assertSame(
        1,
        \App\Models\AuditLog::query()
            ->where(
                'action',
                'FALLBACK_OFFER_RESERVE_RELEASED'
            )
            ->where(
                'entity_id',
                $offer->id
            )
            ->count()
    );
}

public function test_requester_accepts_150_from_160_offer_and_unused_10_is_released(): void
{
    $context =
        $this->createContext(
            'ACCEPT-PARTIAL-OFFER'
        );

    /*
     * Request = 150
     * Offer   = 160
     */
    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $offer =
        $this->makeAvailableOffer(
            $context,
            $source,
            '160.000000'
        );

    $accepted =
        $this->service()
            ->accept(
                $context[
                    'primary_manager'
                ],
                $offer,
                '150.000000'
            );

    $this->assertSame(
        FallbackOfferStatus::ACCEPTED,
        $accepted->status
    );

    $this->assertSame(
        '150.000000',
        (string)
        $accepted->accepted_volume
    );

    $ledger =
        $accepted->sources->first();

    $this->assertSame(
        '160.000000',
        (string)
        $ledger->reserved_volume
    );

    $this->assertSame(
        '150.000000',
        (string)
        $ledger->allocated_volume
    );

    $this->assertSame(
        '10.000000',
        (string)
        $ledger->released_volume
    );

    $request =
        $context['request']->refresh();

    $this->assertSame(
        FallbackRequestStatus::FULFILLED,
        $request->status
    );

    $this->assertNotNull(
        $request->fulfilled_at
    );

    $this->assertSame(
        '150.000000',
        app(
            \App\Services\Fallback\FallbackRequestService::class
        )->calculateAcceptedVolume(
            $request
        )
    );

    $this->assertSame(
        '0.000000',
        app(
            \App\Services\Fallback\FallbackRequestService::class
        )->calculateRemainingVolume(
            $request
        )
    );
}

public function test_accept_less_than_request_remaining_keeps_request_open(): void
{
    $context =
        $this->createContext(
            'ACCEPT-REQUEST-PARTIAL'
        );

    $source =
        $this->approvedSource(
            $context,
            '100.000000'
        );

    $offer =
        $this->makeAvailableOffer(
            $context,
            $source,
            '100.000000'
        );

    $accepted =
        $this->service()
            ->accept(
                $context[
                    'primary_manager'
                ],
                $offer,
                '60.000000'
            );

    $this->assertSame(
        FallbackOfferStatus::ACCEPTED,
        $accepted->status
    );

    $ledger =
        $accepted->sources->first();

    $this->assertSame(
        '60.000000',
        (string)
        $ledger->allocated_volume
    );

    $this->assertSame(
        '40.000000',
        (string)
        $ledger->released_volume
    );

    $request =
        $context['request']->refresh();

    $this->assertSame(
        FallbackRequestStatus::OPEN,
        $request->status
    );

    $this->assertNull(
        $request->fulfilled_at
    );

    $this->assertSame(
        '90.000000',
        app(
            \App\Services\Fallback\FallbackRequestService::class
        )->calculateRemainingVolume(
            $request
        )
    );
}

public function test_accepted_volume_cannot_exceed_offered_volume(): void
{
    $context =
        $this->createContext(
            'ACCEPT-OVER-OFFER'
        );

    $source =
        $this->approvedSource(
            $context,
            '100.000000'
        );

    $offer =
        $this->makeAvailableOffer(
            $context,
            $source,
            '100.000000'
        );

    try {
        $this->service()
            ->accept(
                $context[
                    'primary_manager'
                ],
                $offer,
                '100.000001'
            );

        $this->fail(
            'Accepted volume di atas offered volume harus ditolak.'
        );
    } catch (
        ValidationException $exception
    ) {
        $this->assertArrayHasKey(
            'accepted_volume',
            $exception->errors()
        );
    }

    $this->assertSame(
        FallbackOfferStatus::AVAILABLE,
        $offer->refresh()->status
    );
}

public function test_accepted_volume_cannot_exceed_remaining_request(): void
{
    $context =
        $this->createContext(
            'ACCEPT-OVER-REMAINING'
        );

    $firstSource =
        $this->approvedSource(
            $context,
            '100.000000',
            'A'
        );

    $secondSource =
        $this->approvedSource(
            $context,
            '100.000000',
            'B'
        );

    $first =
        $this->makeAvailableOffer(
            $context,
            $firstSource,
            '100.000000'
        );

    $this->service()
        ->accept(
            $context[
                'primary_manager'
            ],
            $first,
            '100.000000'
        );

    /*
     * Request 150.
     * Setelah Accept pertama 100:
     * remaining = 50.
     */
    $second =
        $this->makeAvailableOffer(
            $context,
            $secondSource,
            '100.000000'
        );

    try {
        $this->service()
            ->accept(
                $context[
                    'primary_manager'
                ],
                $second,
                '50.000001'
            );

        $this->fail(
            'Accepted volume di atas remaining request harus ditolak.'
        );
    } catch (
        ValidationException $exception
    ) {
        $this->assertArrayHasKey(
            'accepted_volume',
            $exception->errors()
        );
    }

    $this->assertSame(
        FallbackOfferStatus::AVAILABLE,
        $second->refresh()->status
    );

    $this->assertSame(
        '50.000000',
        app(
            \App\Services\Fallback\FallbackRequestService::class
        )->calculateRemainingVolume(
            $context['request']->refresh()
        )
    );
}

public function test_multi_source_partial_accept_allocates_deterministically_and_releases_remainder(): void
{
    $context =
        $this->createContext(
            'ACCEPT-MULTI-SOURCE'
        );

    $first =
        $this->approvedSource(
            $context,
            '80.000000',
            'A'
        );

    $second =
        $this->approvedSource(
            $context,
            '80.000000',
            'B'
        );

    $service =
        $this->service();

    $offer =
        $service->createDraft(
            $context['network_operator'],
            $context['request'],
            $this->offerPayload(
                [
                    $first->id,
                    $second->id,
                ],
                '160.000000'
            )
        );

    $offer =
        $service->submit(
            $context['network_operator'],
            $offer
        );

    $offer =
        $service
            ->approveForAvailability(
                $context['network_manager'],
                $offer
            );

    $accepted =
        $service->accept(
            $context['primary_manager'],
            $offer,
            '150.000000'
        );

    $sources =
        $accepted->sources
            ->sortBy(
                'supply_commitment_id'
            )
            ->values();

    $this->assertSame(
        '80.000000',
        (string)
        $sources[0]
            ->allocated_volume
    );

    $this->assertSame(
        '0.000000',
        (string)
        $sources[0]
            ->released_volume
    );

    $this->assertSame(
        '70.000000',
        (string)
        $sources[1]
            ->allocated_volume
    );

    $this->assertSame(
        '10.000000',
        (string)
        $sources[1]
            ->released_volume
    );

    $allocatedTotal =
        FixedScaleDecimal::from(
            (string)
            $sources[0]
                ->allocated_volume
        )->add(
            FixedScaleDecimal::from(
                (string)
                $sources[1]
                    ->allocated_volume
            )
        );

    $this->assertSame(
        '150.000000',
        $allocatedTotal->toString()
    );
}

public function test_source_degradation_after_available_blocks_acceptance(): void
{
    $context =
        $this->createContext(
            'ACCEPT-DEGRADED'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $offer =
        $this->makeAvailableOffer(
            $context,
            $source,
            '150.000000'
        );

    /*
     * Reservation historical masih ada,
     * tetapi source tidak lagi GREEN.
     */
    $source->update([
        'current_confidence' =>
            'YELLOW',
    ]);

    try {
        $this->service()
            ->accept(
                $context[
                    'primary_manager'
                ],
                $offer,
                '150.000000'
            );

        $this->fail(
            'Underlying source yang tidak lagi GREEN harus memblok Acceptance.'
        );
    } catch (
        ValidationException $exception
    ) {
        $this->assertArrayHasKey(
            'source_ledger',
            $exception->errors()
        );
    }

    $offer->refresh();

    $this->assertSame(
        FallbackOfferStatus::AVAILABLE,
        $offer->status
    );

    $ledger =
        $offer->sources()
            ->firstOrFail();

    /*
     * Failed Accept tidak boleh release reserve.
     * Offer masih AVAILABLE.
     */
    $this->assertSame(
        '150.000000',
        (string)
        $ledger->reserved_volume
    );

    $this->assertSame(
        '0.000000',
        (string)
        $ledger->allocated_volume
    );

    $this->assertSame(
        '0.000000',
        (string)
        $ledger->released_volume
    );
}

public function test_offer_cannot_be_accepted_at_exact_expiry(): void
{
    $context =
        $this->createContext(
            'ACCEPT-EXPIRY'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $offer =
        $this->makeAvailableOffer(
            $context,
            $source,
            '150.000000'
        );

    CarbonImmutable::setTestNow(
        CarbonImmutable::parse(
            '2026-08-18 18:00:00'
        )
    );

    try {
        $this->service()
            ->accept(
                $context[
                    'primary_manager'
                ],
                $offer,
                '150.000000'
            );

        $this->fail(
            'Offer pada exact expiry tidak boleh Accepted.'
        );
    } catch (
        ValidationException $exception
    ) {
        $this->assertArrayHasKey(
            'expires_at',
            $exception->errors()
        );
    }

    $this->assertSame(
        FallbackOfferStatus::AVAILABLE,
        $offer->refresh()->status
    );
}

public function test_repeated_accept_with_same_volume_is_idempotent(): void
{
    $context =
        $this->createContext(
            'ACCEPT-IDEMPOTENT'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $offer =
        $this->makeAvailableOffer(
            $context,
            $source,
            '160.000000'
        );

    $first =
        $this->service()
            ->accept(
                $context[
                    'primary_manager'
                ],
                $offer,
                '150.000000'
            );

    $second =
        $this->service()
            ->accept(
                $context[
                    'primary_manager'
                ],
                $first,
                '150.000000'
            );

    $this->assertSame(
        FallbackOfferStatus::ACCEPTED,
        $second->status
    );

    $this->assertSame(
        '150.000000',
        (string)
        $second->accepted_volume
    );

    $this->assertSame(
        1,
        \App\Models\AuditLog::query()
            ->where(
                'action',
                'FALLBACK_OFFER_ACCEPTED'
            )
            ->where(
                'entity_id',
                $offer->id
            )
            ->count()
    );

    $this->assertSame(
        1,
        \App\Models\AuditLog::query()
            ->where(
                'action',
                'FALLBACK_OFFER_CAPACITY_ALLOCATED'
            )
            ->where(
                'entity_id',
                $offer->id
            )
            ->count()
    );
}

public function test_repeated_accept_cannot_change_accepted_volume(): void
{
    $context =
        $this->createContext(
            'ACCEPT-IDEMPOTENT-MISMATCH'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $offer =
        $this->makeAvailableOffer(
            $context,
            $source,
            '160.000000'
        );

    $accepted =
        $this->service()
            ->accept(
                $context[
                    'primary_manager'
                ],
                $offer,
                '150.000000'
            );

    try {
        $this->service()
            ->accept(
                $context[
                    'primary_manager'
                ],
                $accepted,
                '140.000000'
            );

        $this->fail(
            'Repeated Accept tidak boleh mengubah historical accepted volume.'
        );
    } catch (
        ValidationException $exception
    ) {
        $this->assertArrayHasKey(
            'accepted_volume',
            $exception->errors()
        );
    }
}

public function test_available_offer_does_not_enter_safe_supply(): void
{
    $context =
        $this->createContext(
            'METRICS-AVAILABLE'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $offer =
        $this->makeAvailableOffer(
            $context,
            $source,
            '150.000000'
        );

    $this->assertSame(
        FallbackOfferStatus::AVAILABLE,
        $offer->status
    );

    $metrics =
        app(
            SupplyMetricsService::class
        )->calculate(
            $context['forecast'],
            CarbonImmutable::parse(
                '2026-08-10 10:00:00'
            )
        );

    $this->assertSame(
        '0.000000',
        $metrics->fallbackSafeSupply
    );

    $this->assertSame(
        '0.000000',
        $metrics->totalSafeSupply
    );

    $this->assertSame(
        '400.000000',
        $metrics->shortfall
    );

    $this->assertSame(
        [],
        $metrics
            ->contributorOrganizationIds
    );

    $this->assertFalse(
        $metrics->volumeReady
    );
}

public function test_accepted_green_fallback_allocation_enters_canonical_safe_supply(): void
{
    $context =
        $this->createContext(
            'METRICS-ACCEPTED'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $offer =
        $this->makeAvailableOffer(
            $context,
            $source,
            '160.000000'
        );

    $offer =
        $this->service()
            ->accept(
                $context[
                    'primary_manager'
                ],
                $offer,
                '150.000000'
            );

    $this->assertSame(
        FallbackOfferStatus::ACCEPTED,
        $offer->status
    );

    $metrics =
        app(
            SupplyMetricsService::class
        )->calculate(
            $context['forecast'],
            CarbonImmutable::parse(
                '2026-08-10 10:00:00'
            )
        );

    $this->assertSame(
        '0.000000',
        $metrics->directSafeSupply
    );

    $this->assertSame(
        '150.000000',
        $metrics->fallbackSafeSupply
    );

    $this->assertSame(
        '150.000000',
        $metrics->totalSafeSupply
    );

    $this->assertSame(
        '250.000000',
        $metrics->shortfall
    );

    $this->assertSame(
        '37.50',
        $metrics->coveragePercent
    );

    $this->assertSame(
        [
            $context['network']->id,
        ],
        $metrics
            ->contributorOrganizationIds
    );

    $this->assertFalse(
        $metrics->volumeReady
    );
}

public function test_accepted_fallback_degradation_removes_effective_supply_without_rewriting_history(): void
{
    $context =
        $this->createContext(
            'METRICS-DEGRADE'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $offer =
        $this->makeAvailableOffer(
            $context,
            $source,
            '160.000000'
        );

    $offer =
        $this->service()
            ->accept(
                $context[
                    'primary_manager'
                ],
                $offer,
                '150.000000'
            );

    $before =
        app(
            SupplyMetricsService::class
        )->calculate(
            $context['forecast'],
            CarbonImmutable::parse(
                '2026-08-10 10:00:00'
            )
        );

    $this->assertSame(
        '150.000000',
        $before->fallbackSafeSupply
    );

    /*
     * Current supply reality memburuk.
     *
     * Commercial acceptance tidak ditulis ulang.
     */
    $source->update([
        'current_confidence' =>
            'YELLOW',
    ]);

    $after =
        app(
            SupplyMetricsService::class
        )->calculate(
            $context['forecast'],
            CarbonImmutable::parse(
                '2026-08-10 10:00:00'
            )
        );

    $this->assertSame(
        '0.000000',
        $after->fallbackSafeSupply
    );

    $this->assertSame(
        '0.000000',
        $after->totalSafeSupply
    );

    $this->assertSame(
        '400.000000',
        $after->shortfall
    );

    $this->assertSame(
        [],
        $after
            ->contributorOrganizationIds
    );

    $this->assertFalse(
        $after->volumeReady
    );

    /*
     * Historical business decision tetap ada.
     */
    $this->assertSame(
        FallbackOfferStatus::ACCEPTED,
        $offer->refresh()->status
    );

    $this->assertSame(
        '150.000000',
        (string)
        $offer->accepted_volume
    );

    /*
     * FULFILLED request juga tidak dibuka kembali.
     * Recovery berikutnya memakai fallback cycle
     * baru bila Shortfall muncul.
     */
    $this->assertSame(
        FallbackRequestStatus::FULFILLED,
        $context['request']
            ->refresh()
            ->status
    );
}

public function test_same_network_supplier_across_multiple_accepted_offers_is_one_contributor(): void
{
    $context =
        $this->createContext(
            'METRICS-CONTRIBUTOR-UNIQUE'
        );

    $firstSource =
        $this->approvedSource(
            $context,
            '80.000000',
            'A'
        );

    $secondSource =
        $this->approvedSource(
            $context,
            '90.000000',
            'B'
        );

    $first =
        $this->makeAvailableOffer(
            $context,
            $firstSource,
            '80.000000'
        );

    $this->service()
        ->accept(
            $context[
                'primary_manager'
            ],
            $first,
            '60.000000'
        );

    /*
     * Request = 150.
     * Remaining setelah first = 90.
     */
    $second =
        $this->makeAvailableOffer(
            $context,
            $secondSource,
            '90.000000'
        );

    $this->service()
        ->accept(
            $context[
                'primary_manager'
            ],
            $second,
            '90.000000'
        );

    $metrics =
        app(
            SupplyMetricsService::class
        )->calculate(
            $context['forecast'],
            CarbonImmutable::parse(
                '2026-08-10 10:00:00'
            )
        );

    $this->assertSame(
        '150.000000',
        $metrics->fallbackSafeSupply
    );

    $this->assertSame(
        [
            $context['network']->id,
        ],
        $metrics
            ->contributorOrganizationIds
    );
}

public function test_forecast_cancel_is_blocked_after_fallback_allocation_is_accepted(): void
{
    $context =
        $this->createContext(
            'C29-CANCEL'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $offer =
        $this->makeAvailableOffer(
            $context,
            $source,
            '160.000000'
        );

    $this->service()
        ->accept(
            $context['primary_manager'],
            $offer,
            '150.000000'
        );

    $forecast =
        $context['forecast']
            ->refresh();

    try {
        app(
            DemandForecastService::class
        )->cancel(
            $context['sppg_user'],
            $forecast,
            'Forecast dibatalkan.',
            $forecast->version
        );

        $this->fail(
            'Forecast dengan accepted fallback allocation harus menolak cancellation.'
        );
    } catch (
        ValidationException $exception
    ) {
        $this->assertArrayHasKey(
            'fallback',
            $exception->errors()
        );
    }

    $this->assertSame(
        ForecastStatus::PUBLISHED,
        $forecast
            ->refresh()
            ->status
    );
}

public function test_recovery_to_green_is_blocked_when_revised_minimum_is_below_existing_fallback_exposure(): void
{
    $context =
        $this->createContext(
            'C19-RECOVERY'
        );

    /*
     * Initial source minimum = 160.
     */
    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    /*
     * Accepted allocation = 150.
     */
    $offer =
        $this->makeAvailableOffer(
            $context,
            $source,
            '160.000000'
        );

    $this->service()
        ->accept(
            $context['primary_manager'],
            $offer,
            '150.000000'
        );

    /*
     * Supply reality memburuk.
     *
     * Accepted historical allocation tetap 150,
     * tetapi current contribution langsung keluar
     * dari Safe Supply ketika confidence YELLOW.
     */
    app(
        ConfidenceService::class
    )->downgrade(
        $context['network_operator'],
        $source,
        SupplyConfidence::YELLOW,
        'CAPACITY_REVISED',
        'Kapasitas aktual turun.'
    );

    $workflow =
        app(
            CommitmentWorkflowService::class
        );

    /*
     * Revision boleh mencatat minimum aktual 100.
     * Ini sengaja TIDAK diblokir.
     */
    $revision =
        $workflow->createRevision(
            $context['network_operator'],
            $source->refresh(),
            [
                'min_volume' =>
                    '100.000000',

                'max_volume' =>
                    '100.000000',

                'unit_id' =>
                    $context['unit']->id,

                'availability_start_at' =>
                    '2026-08-20 08:00:00',

                'availability_end_at' =>
                    '2026-08-25 17:00:00',

                'notes' =>
                    'Revisi kapasitas aktual.',

                'change_reason' =>
                    'Kapasitas turun setelah allocation.',

                'operator_justification' =>
                    null,
            ]
        );

    $revision =
        $workflow->submit(
            $context['network_operator'],
            $revision
        );

    $workflow->approve(
        $context['network_manager'],
        $revision
    );

    $source->refresh();

    $this->assertSame(
        SupplyConfidence::YELLOW,
        $source->current_confidence
    );

    $this->assertSame(
        '100.000000',
        (string)
        $source->activeVersion
            ->min_volume
    );

    /*
     * Operator boleh mengajukan Recovery.
     */
    $recovery =
        app(
            ConfidenceService::class
        )->requestRecovery(
            $context['network_operator'],
            $source,
            'Pasokan telah diverifikasi kembali.'
        );

    /*
     * Manager tidak boleh membuat GREEN karena:
     *
     * current min = 100
     * exposure    = 150
     */
    try {
        app(
            ConfidenceService::class
        )->approveRecovery(
            $context['network_manager'],
            $recovery
        );

        $this->fail(
            'Recovery seharusnya gagal ketika active minimum lebih kecil dari fallback exposure.'
        );
    } catch (
        ValidationException $exception
    ) {
        $this->assertArrayHasKey(
            'recovery',
            $exception->errors()
        );
    }

    $this->assertSame(
        SupplyConfidence::YELLOW,
        $source
            ->refresh()
            ->current_confidence
    );
}

public function test_cancelling_open_request_rejects_available_offer_and_releases_reserve(): void
{
    $context =
        $this->createContext(
            'REQUEST-CANCEL-RELEASE'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $offer =
        $this->makeAvailableOffer(
            $context,
            $source,
            '150.000000'
        );


$cancelled =
    app(
        FallbackRequestService::class
    )->cancel(
        $context['primary_manager'],
        $context['request'],
        'Kebutuhan fallback sudah tidak diperlukan.'
    );

    $this->assertSame(
        FallbackRequestStatus::CANCELLED,
        $cancelled->status
    );

    $offer->refresh()
        ->load('sources');

    $this->assertSame(
        FallbackOfferStatus::REJECTED,
        $offer->status
    );

    $ledger =
        $offer->sources->first();

    $this->assertSame(
        '150.000000',
        (string)
        $ledger->reserved_volume
    );

    $this->assertSame(
        '0.000000',
        (string)
        $ledger->allocated_volume
    );

    $this->assertSame(
        '150.000000',
        (string)
        $ledger->released_volume
    );

    $this->assertSame(
        '160.000000',
        app(
            FallbackCapacityService::class
        )->availableCapacity(
            $source->refresh(),
            $context['forecast'],
            $context['network']->id
        )
    );

    $this->assertDatabaseHas(
        'audit_logs',
        [
            'action' =>
                'FALLBACK_OFFER_RESERVE_RELEASED',

            'entity_id' =>
                $offer->id,
        ]
    );

    $this->assertDatabaseHas(
        'audit_logs',
        [
            'action' =>
                'FALLBACK_OFFER_REJECTED_BY_REQUESTER',

            'entity_id' =>
                $offer->id,
        ]
    );
}

public function test_cancelling_partially_fulfilled_open_request_preserves_accepted_allocation_and_releases_other_available_offer(): void
{
    $context =
        $this->createContext(
            'REQUEST-CANCEL-PARTIAL'
        );

    $acceptedSource =
        $this->approvedSource(
            $context,
            '100.000000',
            'ACCEPTED'
        );

    $availableSource =
        $this->approvedSource(
            $context,
            '90.000000',
            'AVAILABLE'
        );

    /*
     * Request = 150.
     * Accept 60, sehingga Request masih OPEN
     * dengan remaining 90.
     */
    $acceptedOffer =
        $this->makeAvailableOffer(
            $context,
            $acceptedSource,
            '100.000000'
        );

    $acceptedOffer =
        $this->service()
            ->accept(
                $context['primary_manager'],
                $acceptedOffer,
                '60.000000'
            );

    $this->assertSame(
        FallbackRequestStatus::OPEN,
        $context['request']
            ->refresh()
            ->status
    );

    /*
     * Supplier kedua sudah mempunyai AVAILABLE
     * offer untuk remaining requirement.
     */
    $availableOffer =
        $this->makeAvailableOffer(
            $context,
            $availableSource,
            '90.000000'
        );

    app(
        FallbackRequestService::class
    )->cancel(
        $context['primary_manager'],
        $context['request']->refresh(),
        'Requester memilih menghentikan sisa recovery.'
    );

    /*
     * Historical Accepted allocation tidak boleh
     * dibatalkan oleh parent Request cancellation.
     */
    $acceptedOffer->refresh()
        ->load('sources');

    $this->assertSame(
        FallbackOfferStatus::ACCEPTED,
        $acceptedOffer->status
    );

    $this->assertSame(
        '60.000000',
        (string)
        $acceptedOffer
            ->accepted_volume
    );

    $this->assertSame(
        '60.000000',
        (string)
        $acceptedOffer
            ->sources
            ->first()
            ->allocated_volume
    );

    /*
     * AVAILABLE yang belum Accepted harus
     * direject dan release.
     */
    $availableOffer->refresh()
        ->load('sources');

    $this->assertSame(
        FallbackOfferStatus::REJECTED,
        $availableOffer->status
    );

    $this->assertSame(
        '90.000000',
        (string)
        $availableOffer
            ->sources
            ->first()
            ->released_volume
    );

    $this->assertSame(
        FallbackRequestStatus::CANCELLED,
        $context['request']
            ->refresh()
            ->status
    );

    /*
     * Accepted allocation tetap commercial truth
     * dan tetap dapat menjadi effective Safe Supply
     * selama underlying source masih valid.
     */
    $metrics =
        app(
            SupplyMetricsService::class
        )->calculate(
            $context['forecast'],
            CarbonImmutable::parse(
                '2026-08-10 10:00:00'
            )
        );

    $this->assertSame(
        '60.000000',
        $metrics->fallbackSafeSupply
    );
}

public function test_expiring_open_request_expires_available_offer_and_releases_reserve(): void
{
    $context =
        $this->createContext(
            'REQUEST-EXPIRE-RELEASE'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $offer =
        $this->makeAvailableOffer(
            $context,
            $source,
            '150.000000'
        );

    $expired =
        app(
            FallbackRequestService::class
        )->expire(
            $context['request'],
            CarbonImmutable::parse(
                '2026-08-19 12:00:01'
            )
        );

    $this->assertSame(
        FallbackRequestStatus::EXPIRED,
        $expired->status
    );

    $offer->refresh()
        ->load('sources');

    $this->assertSame(
        FallbackOfferStatus::EXPIRED,
        $offer->status
    );

    $this->assertSame(
        '150.000000',
        (string)
        $offer->sources
            ->first()
            ->released_volume
    );

    $this->assertSame(
        '160.000000',
        app(
            FallbackCapacityService::class
        )->availableCapacity(
            $source->refresh(),
            $context['forecast'],
            $context['network']->id
        )
    );
}

public function test_request_cancellation_does_not_impersonate_supplier_authority_over_pending_offer(): void
{
    $context =
        $this->createContext(
            'REQUEST-CANCEL-PENDING'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $service =
        $this->service();

    $offer =
        $service->createDraft(
            $context['network_operator'],
            $context['request'],
            $this->offerPayload([
                $source->id,
            ])
        );

    $offer =
        $service->submit(
            $context['network_operator'],
            $offer
        );

    $this->assertSame(
        FallbackOfferStatus::PENDING_APPROVAL,
        $offer->status
    );

    app(
        FallbackRequestService::class
    )->cancel(
        $context['primary_manager'],
        $context['request'],
        'Recovery dihentikan.'
    );

    /*
     * Requester Manager tidak mengambil alih
     * supplier review authority.
     */
    $offer->refresh()
        ->load('sources');

    $this->assertSame(
        FallbackOfferStatus::PENDING_APPROVAL,
        $offer->status
    );

    $this->assertSame(
        '0.000000',
        (string)
        $offer->sources
            ->first()
            ->reserved_volume
    );

    /*
     * Tetapi Supplier Manager juga tidak dapat
     * mempublikasikannya lagi karena parent
     * Request sudah bukan OPEN.
     */
    try {
        $service
            ->approveForAvailability(
                $context['network_manager'],
                $offer
            );

        $this->fail(
            'PENDING Offer tidak boleh menjadi AVAILABLE setelah parent Request CANCELLED.'
        );
    } catch (
        ValidationException $exception
    ) {
        $this->assertArrayHasKey(
            'fallback_request_id',
            $exception->errors()
        );
    }
}

public function test_sppg_user_cannot_accept_available_fallback_offer(): void
{
    $context =
        $this->createContext(
            'AUTH-SPPG-ACCEPT'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $offer =
        $this->makeAvailableOffer(
            $context,
            $source,
            '150.000000'
        );

    $this->expectException(
        AuthorizationException::class
    );

    $this->service()
        ->accept(
            $context['sppg_user'],
            $offer,
            '150.000000'
        );
}

public function test_system_admin_cannot_accept_available_fallback_offer(): void
{
    $context =
        $this->createContext(
            'AUTH-ADMIN-ACCEPT'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $offer =
        $this->makeAvailableOffer(
            $context,
            $source,
            '150.000000'
        );

    $this->expectException(
        AuthorizationException::class
    );

    $this->service()
        ->accept(
            $context['admin'],
            $offer,
            '150.000000'
        );
}

public function test_supplier_manager_cannot_accept_own_available_offer_as_requester(): void
{
    $context =
        $this->createContext(
            'AUTH-SUPPLIER-ACCEPT'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $offer =
        $this->makeAvailableOffer(
            $context,
            $source,
            '150.000000'
        );

    try {
        $this->service()
            ->accept(
                $context['network_manager'],
                $offer,
                '150.000000'
            );

        $this->fail(
            'Supplier Manager tidak boleh mengambil keputusan Acceptance milik requester.'
        );
    } catch (
        AuthorizationException $exception
    ) {
        $this->assertSame(
            FallbackOfferStatus::AVAILABLE,
            $offer->refresh()->status
        );
    }
}

public function test_manager_from_unrelated_kdkmp_cannot_accept_available_offer(): void
{
    $context =
        $this->createContext(
            'AUTH-OTHER-MANAGER'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $offer =
        $this->makeAvailableOffer(
            $context,
            $source,
            '150.000000'
        );

    $otherOrganization =
        $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-FO-AUTH-OTHER-MANAGER'
        );

    $otherManager =
        User::factory()->create([
            'organization_id' =>
                $otherOrganization->id,

            'role' =>
                UserRole::KDKMP_MANAGER,

            'is_active' =>
                true,
        ]);

    try {
        $this->service()
            ->accept(
                $otherManager,
                $offer,
                '150.000000'
            );

        $this->fail(
            'Manager organisasi lain tidak boleh menerima Offer milik requester.'
        );
    } catch (
        AuthorizationException $exception
    ) {
        $this->assertSame(
            FallbackOfferStatus::AVAILABLE,
            $offer->refresh()->status
        );
    }
}

public function test_multiple_accepted_offers_cumulatively_fulfil_request(): void
{
    $context =
        $this->createContext(
            'CUMULATIVE-FULFIL'
        );

    $firstSource =
        $this->approvedSource(
            $context,
            '100.000000',
            'A'
        );

    $secondSource =
        $this->approvedSource(
            $context,
            '100.000000',
            'B'
        );

    /*
     * Request = 150.
     *
     * First acceptance = 60.
     */
    $firstOffer =
        $this->makeAvailableOffer(
            $context,
            $firstSource,
            '100.000000'
        );

    $this->service()
        ->accept(
            $context['primary_manager'],
            $firstOffer,
            '60.000000'
        );

    $request =
        $context['request']->refresh();

    $this->assertSame(
        FallbackRequestStatus::OPEN,
        $request->status
    );

    $this->assertSame(
        '90.000000',
        app(
            FallbackRequestService::class
        )->calculateRemainingVolume(
            $request
        )
    );

    /*
     * Second acceptance = remaining 90.
     */
    $secondOffer =
        $this->makeAvailableOffer(
            $context,
            $secondSource,
            '90.000000'
        );

    $this->service()
        ->accept(
            $context['primary_manager'],
            $secondOffer,
            '90.000000'
        );

    $request->refresh();

    $this->assertSame(
        FallbackRequestStatus::FULFILLED,
        $request->status
    );

    $this->assertNotNull(
        $request->fulfilled_at
    );

    $requestService =
        app(
            FallbackRequestService::class
        );

    $this->assertSame(
        '150.000000',
        $requestService
            ->calculateAcceptedVolume(
                $request
            )
    );

    $this->assertSame(
        '0.000000',
        $requestService
            ->calculateRemainingVolume(
                $request
            )
    );

    $this->assertSame(
        2,
        \App\Models\FallbackOffer::query()
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
            ->count()
    );
}

public function test_repeated_offer_expiry_does_not_release_capacity_or_audit_twice(): void
{
    $context =
        $this->createContext(
            'EXPIRE-IDEMPOTENT'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $offer =
        $this->makeAvailableOffer(
            $context,
            $source,
            '150.000000'
        );

    $evaluationTime =
        CarbonImmutable::parse(
            '2026-08-18 18:00:00'
        );

    $first =
        $this->service()
            ->expire(
                $offer,
                $evaluationTime
            );

    $second =
        $this->service()
            ->expire(
                $first,
                $evaluationTime
            );

    $this->assertSame(
        FallbackOfferStatus::EXPIRED,
        $second->status
    );

    $ledger =
        $second->sources
            ->first();

    $this->assertSame(
        '150.000000',
        (string)
        $ledger->reserved_volume
    );

    $this->assertSame(
        '0.000000',
        (string)
        $ledger->allocated_volume
    );

    $this->assertSame(
        '150.000000',
        (string)
        $ledger->released_volume
    );

    $this->assertSame(
        1,
        \App\Models\AuditLog::query()
            ->where(
                'action',
                'FALLBACK_OFFER_EXPIRED'
            )
            ->where(
                'entity_id',
                $offer->id
            )
            ->count()
    );

    $this->assertSame(
        1,
        \App\Models\AuditLog::query()
            ->where(
                'action',
                'FALLBACK_OFFER_RESERVE_RELEASED'
            )
            ->where(
                'entity_id',
                $offer->id
            )
            ->count()
    );
}

public function test_repeated_available_offer_withdrawal_does_not_release_capacity_twice(): void
{
    $context =
        $this->createContext(
            'WITHDRAW-IDEMPOTENT'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $offer =
        $this->makeAvailableOffer(
            $context,
            $source,
            '150.000000'
        );

    $first =
        $this->service()
            ->withdraw(
                $context['network_manager'],
                $offer,
                'Supplier menarik Offer.'
            );

    $second =
        $this->service()
            ->withdraw(
                $context['network_manager'],
                $first,
                'Supplier menarik Offer.'
            );

    $this->assertSame(
        FallbackOfferStatus::WITHDRAWN,
        $second->status
    );

    $ledger =
        $second->sources
            ->first();

    $this->assertSame(
        '150.000000',
        (string)
        $ledger->reserved_volume
    );

    $this->assertSame(
        '150.000000',
        (string)
        $ledger->released_volume
    );

    $this->assertSame(
        1,
        \App\Models\AuditLog::query()
            ->where(
                'action',
                'FALLBACK_OFFER_WITHDRAWN'
            )
            ->where(
                'entity_id',
                $offer->id
            )
            ->count()
    );

    $this->assertSame(
        1,
        \App\Models\AuditLog::query()
            ->where(
                'action',
                'FALLBACK_OFFER_RESERVE_RELEASED'
            )
            ->where(
                'entity_id',
                $offer->id
            )
            ->count()
    );
}

public function test_fulfilled_request_remains_terminal_when_accepted_source_later_degrades(): void
{
    $context =
        $this->createContext(
            'FULFILLED-TERMINAL-DEGRADE'
        );

    $source =
        $this->approvedSource(
            $context,
            '160.000000'
        );

    $offer =
        $this->makeAvailableOffer(
            $context,
            $source,
            '150.000000'
        );

    $this->service()
        ->accept(
            $context['primary_manager'],
            $offer,
            '150.000000'
        );

    $this->assertSame(
        FallbackRequestStatus::FULFILLED,
        $context['request']
            ->refresh()
            ->status
    );

    $source->update([
        'current_confidence' =>
            SupplyConfidence::YELLOW,
    ]);

    $metrics =
        app(
            SupplyMetricsService::class
        )->calculate(
            $context['forecast'],
            CarbonImmutable::parse(
                '2026-08-10 10:00:00'
            )
        );

    $this->assertSame(
        '0.000000',
        $metrics->fallbackSafeSupply
    );

    $this->assertSame(
        '400.000000',
        $metrics->shortfall
    );

    /*
     * Historical recovery cycle tetap terminal.
     * Shortfall baru ditangani melalui recovery
     * supply atau Fallback Request baru.
     */
    $this->assertSame(
        FallbackRequestStatus::FULFILLED,
        $context['request']
            ->refresh()
            ->status
    );

    $this->assertSame(
        FallbackOfferStatus::ACCEPTED,
        $offer
            ->refresh()
            ->status
    );
}
    private function service(): FallbackOfferService
    {
        return app(
            FallbackOfferService::class
        );
    }

    private function offerPayload(
        array $sourceIds,
        string $volume = '150.000000',
    ): array {
        return [
            'offered_volume' =>
                $volume,

            'availability_note' =>
                'Eligible fallback capacity fixture',

            'expires_at' =>
                '2026-08-18 18:00:00',

            'source_commitment_ids' =>
                $sourceIds,
        ];
    }


    private function makeAvailableOffer(
    array $context,
    $source,
    string $volume,
) {
    $service =
        $this->service();

    $offer =
        $service->createDraft(
            $context['network_operator'],
            $context['request'],
            $this->offerPayload(
                [
                    $source->id,
                ],
                $volume
            )
        );

    $offer =
        $service->submit(
            $context['network_operator'],
            $offer
        );

    return $service
        ->approveForAvailability(
            $context['network_manager'],
            $offer
        );
}
    private function approvedSource(
        array $context,
        string $minimum,
        string $suffix = 'SRC',
    ) {
        $workflow =
            app(
                CommitmentWorkflowService::class
            );

        $commitment =
            $workflow
                ->createFallbackSourceDraft(
                    $context[
                        'network_operator'
                    ],
                    $context['request'],
                    [
                        'producer_id' =>
                            $context[
                                'network_producer'
                            ]->id,

                        'expected_harvest_id' =>
                            null,

                        'min_volume' =>
                            $minimum,

                        'max_volume' =>
                            $minimum,

                        'unit_id' =>
                            $context['unit']->id,

                        'availability_start_at' =>
                            '2026-08-20 08:00:00',

                        'availability_end_at' =>
                            '2026-08-25 17:00:00',

                        'notes' =>
                            "Offer source {$suffix}",

                        'operator_justification' =>
                            null,
                    ]
                );

        $version =
            CommitmentVersion::query()
                ->where(
                    'commitment_id',
                    $commitment->id
                )
                ->firstOrFail();

        $version =
            $workflow->submit(
                $context[
                    'network_operator'
                ],
                $version
            );

        $workflow->approve(
            $context[
                'network_manager'
            ],
            $version
        );

        return $commitment->refresh();
    }

    private function createContext(
        string $suffix,
    ): array {
        $unit =
            Unit::create([
                'code' =>
                    "KG-FO-{$suffix}",

                'name' =>
                    "Kilogram FO {$suffix}",

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
                    "COM-FO-{$suffix}",

                'name' =>
                    "Commodity FO {$suffix}",

                'default_unit_id' =>
                    $unit->id,

                'harvest_behavior' =>
                    null,

                'notes' =>
                    null,

                'is_active' =>
                    true,
            ]);

        $sppg =
            $this->createOrganization(
                OrganizationType::SPPG,
                "SPPG-FO-{$suffix}"
            );

        $primary =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-FO-PRIMARY-{$suffix}"
            );

        $network =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-FO-NETWORK-{$suffix}"
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

        $sppgUser =
            User::factory()->create([
                'organization_id' =>
                    $sppg->id,

                'role' =>
                    UserRole::SPPG_USER,

                'is_active' =>
                    true,
            ]);

        $primaryOperator =
            User::factory()->create([
                'organization_id' =>
                    $primary->id,

                'role' =>
                    UserRole::KDKMP_OPERATOR,

                'is_active' =>
                    true,
            ]);

        $primaryManager =
    User::factory()->create([
        'organization_id' =>
            $primary->id,

        'role' =>
            UserRole::KDKMP_MANAGER,

        'is_active' =>
            true,
    ]);

        $networkOperator =
            User::factory()->create([
                'organization_id' =>
                    $network->id,

                'role' =>
                    UserRole::KDKMP_OPERATOR,

                'is_active' =>
                    true,
            ]);

        $networkManager =
            User::factory()->create([
                'organization_id' =>
                    $network->id,

                'role' =>
                    UserRole::KDKMP_MANAGER,

                'is_active' =>
                    true,
            ]);

        $networkProducer =
            $this->createProducer(
                $network,
                $networkOperator,
                "PROD-FO-{$suffix}"
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

        $forecast =
            DemandForecast::create([
                'sppg_organization_id' =>
                    $sppg->id,

                'commodity_id' =>
                    $commodity->id,

                'unit_id' =>
                    $unit->id,

                'forecast_code' =>
                    "FC-FO-{$suffix}",

                'target_volume' =>
                    '400.000000',

                'required_start_at' =>
                    '2026-08-20 08:00:00',

                'required_end_at' =>
                    '2026-08-25 17:00:00',

                'freshness_interval_hours' =>
                    24,

                'status' =>
                    ForecastStatus::PUBLISHED,

                'published_at' =>
                    '2026-08-10 08:00:00',

                'version' =>
                    1,

                'created_by' =>
                    $sppgUser->id,

                'updated_by' =>
                    $sppgUser->id,
            ]);

        $request =
            FallbackRequest::create([
                'forecast_id' =>
                    $forecast->id,

                'requester_organization_id' =>
                    $primary->id,

                'requested_volume' =>
                    '150.000000',

                'unit_id' =>
                    $unit->id,

                'response_deadline_at' =>
                    '2026-08-19 12:00:00',

                'status' =>
                    FallbackRequestStatus::OPEN,

                'broadcast_note' =>
                    'Aggregate offer fixture',

                'created_by' =>
                    $primaryOperator->id,

                'submitted_by' =>
                    $primaryOperator->id,

                'submitted_at' =>
                    '2026-08-10 08:30:00',

                'reviewed_at' =>
                    '2026-08-10 09:00:00',

                'opened_at' =>
                    '2026-08-10 09:00:00',
            ]);

        return [
            'unit' =>
                $unit,

            'commodity' =>
                $commodity,

            'sppg' =>
                $sppg,

            'primary' =>
                $primary,

            'network' =>
                $network,

            'admin' =>
                $admin,

            'primary_operator' =>
                $primaryOperator,

            'network_operator' =>
                $networkOperator,

            'network_manager' =>
                $networkManager,

            'network_producer' =>
                $networkProducer,

            'forecast' =>
                $forecast,

            'request' =>
                $request,
            
            'primary_manager' =>
                $primaryManager,
            
                'sppg_user' =>
    $sppgUser,
        ];
    }

    private function createOrganization(
        OrganizationType $type,
        string $code,
    ): Organization {
        return Organization::create([
            'code' =>
                $code,

            'name' =>
                $code,

            'organization_type' =>
                $type,

            'is_active' =>
                true,

            'general_location' =>
                'Lokasi Test Fallback Offer',
        ]);
    }

    private function createProducer(
        Organization $organization,
        User $creator,
        string $code,
    ): Producer {
        return Producer::create([
            'organization_id' =>
                $organization->id,

            'producer_code' =>
                $code,

            'name' =>
                "Produsen {$code}",

            'village' =>
                'Desa Test',

            'district' =>
                'Kecamatan Test',

            'contact_phone' =>
                '081234567890',

            'notes' =>
                'Fallback offer producer fixture',

            'is_active' =>
                true,

            'created_by' =>
                $creator->id,
        ]);
    }
}