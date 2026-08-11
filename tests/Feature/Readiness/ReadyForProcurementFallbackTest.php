<?php

namespace Tests\Feature\Readiness;

use App\Enums\ForecastStatus;
use App\Enums\NetworkRole;
use App\Enums\OrganizationType;
use App\Enums\ReadinessType;
use App\Enums\RequirementScope;
use App\Enums\SupplyConfidence;
use App\Enums\UserRole;
use App\Models\Commodity;
use App\Models\CommitmentVersion;
use App\Models\DemandForecast;
use App\Models\Organization;
use App\Models\Producer;
use App\Models\ReadinessChecklist;
use App\Models\ReadinessRequirement;
use App\Models\SupplyNetworkLink;
use App\Models\Unit;
use App\Models\User;
use App\Services\Commitment\CommitmentWorkflowService;
use App\Services\Fallback\FallbackOfferService;
use App\Services\Fallback\FallbackRequestService;
use App\Services\Readiness\DocumentRecordService;
use App\Services\Readiness\ReadyForProcurementEvaluationService;
use App\Services\Readiness\ReadinessChecklistPreparationService;
use App\Services\Readiness\ReadinessChecklistReviewService;
use App\Services\Readiness\ReadinessChecklistWorkflowService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadyForProcurementFallbackTest extends TestCase
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

    public function test_primary_and_accepted_fallback_contributor_must_both_be_ready(): void
    {
        $context =
            $this->createContext(
                'MULTI-CONTRIBUTOR'
            );

        /*
         * Demand = 400
         *
         * PRIMARY direct = 250
         * NETWORK accepted fallback = 150
         *
         * Total Safe Supply = 400.
         */
        $this->createApprovedPrimaryCommitment(
            $context,
            '250.000000',
            'PRIMARY-A'
        );

        $request =
            $this->createOpenFallbackRequest(
                $context,
                '150.000000'
            );

        $fallback =
            $this->createAcceptedFallback(
                $context,
                $request,
                '160.000000',
                '160.000000',
                '150.000000'
            );

        $this->createRequirements(
            $context,
            'MULTI-CONTRIBUTOR'
        );

        /*
         * Hanya PRIMARY yang Ready.
         * NETWORK sudah contributor tetapi
         * belum memiliki readiness.
         */
        $this->createFullyReadyContributor(
            $context,
            $context['primary'],
            $context['primaryOperator'],
            $context['primaryManager']
        );

        $before =
            $this->evaluationService()
                ->evaluate(
                    $context['forecast']
                );

        $this->assertTrue(
            $before->volumeReady
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
            $before
                ->contributorOrganizationIds
        );

        $this->assertFalse(
            $before
                ->allContributorsLogisticsReady
        );

        $this->assertFalse(
            $before
                ->allContributorsDocumentReady
        );

        $this->assertFalse(
            $before
                ->readyForProcurement
        );

        $this->assertContains(
            'LOGISTICS_NOT_READY',
            $before->reasonCodes
        );

        $this->assertContains(
            'DOCUMENT_NOT_READY',
            $before->reasonCodes
        );

        /*
         * Setelah NETWORK contributor juga
         * menyelesaikan readiness, conjunction
         * seluruh contributor terpenuhi.
         */
        $this->createFullyReadyContributor(
            $context,
            $context['network'],
            $context['networkOperator'],
            $context['networkManager']
        );

        $after =
            $this->evaluationService()
                ->evaluate(
                    $context['forecast']
                );

        $this->assertTrue(
            $after->volumeReady
        );

        $this->assertSame(
            $expectedContributors,
            $after
                ->contributorOrganizationIds
        );

        $this->assertTrue(
            $after
                ->allContributorsLogisticsReady
        );

        $this->assertTrue(
            $after
                ->allContributorsDocumentReady
        );

        $this->assertTrue(
            $after
                ->readyForProcurement
        );

        $this->assertSame(
            [],
            $after->reasonCodes
        );

        /*
         * Commercial fallback truth tetap
         * ACCEPTED 150.
         */
        $this->assertSame(
            '150.000000',
            (string)
            $fallback['offer']
                ->fresh()
                ->accepted_volume
        );
    }

    public function test_partial_accepted_fallback_counts_as_contribution_when_total_safe_supply_reaches_demand(): void
    {
        $context =
            $this->createContext(
                'PARTIAL-FALLBACK'
            );

        /*
         * Demand = 400
         *
         * PRIMARY = 340
         * Shortfall = 60
         */
        $this->createApprovedPrimaryCommitment(
            $context,
            '340.000000',
            'PRIMARY-PARTIAL'
        );

        $request =
            $this->createOpenFallbackRequest(
                $context,
                '60.000000'
            );

        /*
         * NETWORK offers 100,
         * requester hanya accepts 60.
         *
         * Effective fallback contribution = 60.
         */
        $this->createAcceptedFallback(
            $context,
            $request,
            '100.000000',
            '100.000000',
            '60.000000'
        );

        $this->createRequirements(
            $context,
            'PARTIAL-FALLBACK'
        );

        $this->createFullyReadyContributor(
            $context,
            $context['primary'],
            $context['primaryOperator'],
            $context['primaryManager']
        );

        $this->createFullyReadyContributor(
            $context,
            $context['network'],
            $context['networkOperator'],
            $context['networkManager']
        );

        $result =
            $this->evaluationService()
                ->evaluate(
                    $context['forecast']
                );

        $this->assertTrue(
            $result->volumeReady
        );

        $this->assertCount(
            2,
            $result
                ->contributorOrganizationIds
        );

        $this->assertTrue(
            $result
                ->allContributorsLogisticsReady
        );

        $this->assertTrue(
            $result
                ->allContributorsDocumentReady
        );

        $this->assertTrue(
            $result->readyForProcurement
        );

        $this->assertSame(
            [],
            $result->reasonCodes
        );
    }

    public function test_available_but_not_accepted_network_offer_is_not_a_contributor_and_does_not_block_rfp(): void
    {
        $context =
            $this->createContext(
                'AVAILABLE-NOT-CONTRIBUTOR'
            );

        /*
         * Awalnya PRIMARY hanya 250.
         * Shortfall memungkinkan fallback request.
         */
        $this->createApprovedPrimaryCommitment(
            $context,
            '250.000000',
            'PRIMARY-INITIAL'
        );

        $request =
            $this->createOpenFallbackRequest(
                $context,
                '150.000000'
            );

        /*
         * NETWORK Offer sampai AVAILABLE,
         * tetapi belum ACCEPTED.
         *
         * Reserve bukan Safe Supply.
         */
        $this->createAvailableFallback(
            $context,
            $request,
            '160.000000',
            '150.000000'
        );

        /*
         * Kondisi PRIMARY kemudian membaik /
         * sumber PRIMARY lain menjadi approved.
         *
         * Total direct PRIMARY sekarang 400.
         */
        $this->createApprovedPrimaryCommitment(
            $context,
            '150.000000',
            'PRIMARY-RECOVERY'
        );

        $this->createRequirements(
            $context,
            'AVAILABLE-NOT-CONTRIBUTOR'
        );

        /*
         * Hanya PRIMARY yang diberikan readiness.
         *
         * NETWORK dengan AVAILABLE Offer tidak
         * boleh ikut gate.
         */
        $this->createFullyReadyContributor(
            $context,
            $context['primary'],
            $context['primaryOperator'],
            $context['primaryManager']
        );

        $result =
            $this->evaluationService()
                ->evaluate(
                    $context['forecast']
                );

        $this->assertTrue(
            $result->volumeReady
        );

        $this->assertSame(
            [
                $context['primary']->id,
            ],
            $result
                ->contributorOrganizationIds
        );

        $this->assertCount(
            1,
            $result
                ->contributorReadinessResults
        );

        $this->assertTrue(
            $result
                ->allContributorsLogisticsReady
        );

        $this->assertTrue(
            $result
                ->allContributorsDocumentReady
        );

        $this->assertTrue(
            $result->readyForProcurement
        );
    }

    public function test_network_kdkmp_without_effective_contribution_never_blocks_ready_for_procurement(): void
    {
        $context =
            $this->createContext(
                'NON-CONTRIBUTOR'
            );

        /*
         * PRIMARY sendiri menutup seluruh Demand.
         *
         * NETWORK tetap anggota supply network,
         * tetapi tidak mempunyai effective
         * contribution.
         */
        $this->createApprovedPrimaryCommitment(
            $context,
            '400.000000',
            'PRIMARY-FULL'
        );

        $this->createRequirements(
            $context,
            'NON-CONTRIBUTOR'
        );

        $this->createFullyReadyContributor(
            $context,
            $context['primary'],
            $context['primaryOperator'],
            $context['primaryManager']
        );

        /*
         * NETWORK sengaja tidak memiliki
         * Logistics maupun Document checklist.
         */
        $result =
            $this->evaluationService()
                ->evaluate(
                    $context['forecast']
                );

        $this->assertSame(
            [
                $context['primary']->id,
            ],
            $result
                ->contributorOrganizationIds
        );

        $this->assertTrue(
            $result->volumeReady
        );

        $this->assertTrue(
            $result
                ->allContributorsLogisticsReady
        );

        $this->assertTrue(
            $result
                ->allContributorsDocumentReady
        );

        $this->assertTrue(
            $result->readyForProcurement
        );

        $this->assertSame(
            [],
            $result->reasonCodes
        );
    }

    public function test_degrading_accepted_fallback_source_recalculates_contributor_and_invalidates_rfp(): void
    {
        $context =
            $this->createContext(
                'FALLBACK-DEGRADE'
            );

        $this->createApprovedPrimaryCommitment(
            $context,
            '250.000000',
            'PRIMARY-DEGRADE'
        );

        $request =
            $this->createOpenFallbackRequest(
                $context,
                '150.000000'
            );

        $fallback =
            $this->createAcceptedFallback(
                $context,
                $request,
                '160.000000',
                '160.000000',
                '150.000000'
            );

        $this->createRequirements(
            $context,
            'FALLBACK-DEGRADE'
        );

        $this->createFullyReadyContributor(
            $context,
            $context['primary'],
            $context['primaryOperator'],
            $context['primaryManager']
        );

        $this->createFullyReadyContributor(
            $context,
            $context['network'],
            $context['networkOperator'],
            $context['networkManager']
        );

        $before =
            $this->evaluationService()
                ->evaluate(
                    $context['forecast']
                );

        $this->assertTrue(
            $before->readyForProcurement
        );

        /*
         * Historical ACCEPTED decision tidak
         * ditulis ulang.
         *
         * Tetapi current source reality menjadi
         * YELLOW, sehingga M06 mengeluarkan
         * accepted allocation tersebut dari
         * effective Safe Supply.
         */
        $fallback['source']->update([
            'current_confidence' =>
                SupplyConfidence::YELLOW,
        ]);

        $after =
            $this->evaluationService()
                ->evaluate(
                    $context['forecast']
                );

        $this->assertFalse(
            $after->volumeReady
        );

        /*
         * NETWORK tidak lagi contributor.
         * PRIMARY masih memiliki 250 Safe Supply.
         */
        $this->assertSame(
            [
                $context['primary']->id,
            ],
            $after
                ->contributorOrganizationIds
        );

        /*
         * PRIMARY readiness masih valid.
         * Failure sekarang murni volume.
         */
        $this->assertTrue(
            $after
                ->allContributorsLogisticsReady
        );

        $this->assertTrue(
            $after
                ->allContributorsDocumentReady
        );

        $this->assertFalse(
            $after->readyForProcurement
        );

        $this->assertContains(
            'VOLUME_NOT_READY',
            $after->reasonCodes
        );

        $this->assertNotContains(
            'LOGISTICS_NOT_READY',
            $after->reasonCodes
        );

        $this->assertNotContains(
            'DOCUMENT_NOT_READY',
            $after->reasonCodes
        );

        /*
         * Historical fallback tetap ACCEPTED.
         */
        $offer =
            $fallback['offer']->fresh();

        $this->assertSame(
            \App\Enums\FallbackOfferStatus::ACCEPTED,
            $offer->status
        );

        $this->assertSame(
            '150.000000',
            (string)
            $offer->accepted_volume
        );
    }

    private function evaluationService():
        ReadyForProcurementEvaluationService
    {
        return app(
            ReadyForProcurementEvaluationService::class
        );
    }

    private function commitmentWorkflow():
        CommitmentWorkflowService
    {
        return app(
            CommitmentWorkflowService::class
        );
    }

    private function fallbackRequestService():
        FallbackRequestService
    {
        return app(
            FallbackRequestService::class
        );
    }

    private function fallbackOfferService():
        FallbackOfferService
    {
        return app(
            FallbackOfferService::class
        );
    }

    private function preparationService():
        ReadinessChecklistPreparationService
    {
        return app(
            ReadinessChecklistPreparationService::class
        );
    }

    private function workflowService():
        ReadinessChecklistWorkflowService
    {
        return app(
            ReadinessChecklistWorkflowService::class
        );
    }

    private function reviewService():
        ReadinessChecklistReviewService
    {
        return app(
            ReadinessChecklistReviewService::class
        );
    }

    private function documentService():
        DocumentRecordService
    {
        return app(
            DocumentRecordService::class
        );
    }

    private function createApprovedPrimaryCommitment(
        array $context,
        string $minimum,
        string $suffix,
    ) {
        $producer =
            $this->createProducer(
                $context['primary'],
                $context['primaryOperator'],
                "PROD-RFP-PRIMARY-{$suffix}"
            );

        $workflow =
            $this->commitmentWorkflow();

        $commitment =
            $workflow->createDraft(
                $context['primaryOperator'],
                [
                    'forecast_id' =>
                        $context['forecast']->id,

                    'producer_id' =>
                        $producer->id,

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
                        "Primary RFP source {$suffix}",

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
                $context['primaryOperator'],
                $version
            );

        $workflow->approve(
            $context['primaryManager'],
            $version
        );

        return $commitment->refresh();
    }

    private function createOpenFallbackRequest(
        array $context,
        string $requestedVolume,
    ) {
        $service =
            $this->fallbackRequestService();

        $request =
            $service->createDraft(
                $context['primaryOperator'],
                $context['forecast'],
                [
                    'requested_volume' =>
                        $requestedVolume,

                    'response_deadline_at' =>
                        '2026-08-19 12:00:00',

                    'broadcast_note' =>
                        'RFP fallback integration fixture.',
                ]
            );

        $request =
            $service->submit(
                $context['primaryOperator'],
                $request
            );

        return $service->approveBroadcast(
            $context['primaryManager'],
            $request
        );
    }

    private function createAcceptedFallback(
        array $context,
        $request,
        string $sourceMinimum,
        string $offeredVolume,
        string $acceptedVolume,
    ): array {
        $source =
            $this->createApprovedFallbackSource(
                $context,
                $request,
                $sourceMinimum
            );

        $offer =
            $this->createAvailableFallbackFromSource(
                $context,
                $request,
                $source,
                $offeredVolume
            );

        $offer =
            $this->fallbackOfferService()
                ->accept(
                    $context['primaryManager'],
                    $offer,
                    $acceptedVolume
                );

        return [
            'source' =>
                $source,

            'offer' =>
                $offer,
        ];
    }

    private function createAvailableFallback(
        array $context,
        $request,
        string $sourceMinimum,
        string $offeredVolume,
    ): array {
        $source =
            $this->createApprovedFallbackSource(
                $context,
                $request,
                $sourceMinimum
            );

        $offer =
            $this->createAvailableFallbackFromSource(
                $context,
                $request,
                $source,
                $offeredVolume
            );

        return [
            'source' =>
                $source,

            'offer' =>
                $offer,
        ];
    }

    private function createApprovedFallbackSource(
        array $context,
        $request,
        string $minimum,
    ) {
        $producer =
            $this->createProducer(
                $context['network'],
                $context['networkOperator'],
                'PROD-RFP-FALLBACK-'
                .$request->id
                .'-'
                .$minimum
            );

        $workflow =
            $this->commitmentWorkflow();

        $commitment =
            $workflow
                ->createFallbackSourceDraft(
                    $context['networkOperator'],
                    $request,
                    [
                        'producer_id' =>
                            $producer->id,

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
                            'Accepted fallback RFP source.',

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
                $context['networkOperator'],
                $version
            );

        $workflow->approve(
            $context['networkManager'],
            $version
        );

        return $commitment->refresh();
    }

    private function createAvailableFallbackFromSource(
        array $context,
        $request,
        $source,
        string $offeredVolume,
    ) {
        $service =
            $this->fallbackOfferService();

        $offer =
            $service->createDraft(
                $context['networkOperator'],
                $request,
                [
                    'offered_volume' =>
                        $offeredVolume,

                    'availability_note' =>
                        'RFP fallback offer fixture.',

                    'expires_at' =>
                        '2026-08-18 18:00:00',

                    'source_commitment_ids' => [
                        $source->id,
                    ],
                ]
            );

        $offer =
            $service->submit(
                $context['networkOperator'],
                $offer
            );

        return $service
            ->approveForAvailability(
                $context['networkManager'],
                $offer
            );
    }

    private function createRequirements(
        array $context,
        string $suffix,
    ): void {
        ReadinessRequirement::create([
            'readiness_type' =>
                ReadinessType::LOGISTICS,

            'requirement_code' =>
                "LOG-RFP-FALLBACK-{$suffix}",

            'label' =>
                "Logistics RFP {$suffix}",

            'requirement_scope' =>
                RequirementScope::FORECAST,

            'applies_to_organization_type' =>
                OrganizationType::KDKMP,

            'commodity_id' =>
                $context['commodity']->id,

            'is_required_default' =>
                true,

            'is_active' =>
                true,

            'sort_order' =>
                10,

            'config_json' =>
                null,
        ]);

        ReadinessRequirement::create([
            'readiness_type' =>
                ReadinessType::DOCUMENT,

            'requirement_code' =>
                "DOC-RFP-FALLBACK-{$suffix}",

            'label' =>
                "Document RFP {$suffix}",

            'requirement_scope' =>
                RequirementScope::ORGANIZATION,

            'applies_to_organization_type' =>
                OrganizationType::KDKMP,

            'commodity_id' =>
                $context['commodity']->id,

            'is_required_default' =>
                true,

            'is_active' =>
                true,

            'sort_order' =>
                10,

            'config_json' =>
                null,
        ]);
    }

    private function createFullyReadyContributor(
        array $context,
        Organization $organization,
        User $operator,
        User $manager,
    ): void {
        $this->createApprovedLogisticsChecklist(
            $context,
            $operator,
            $manager
        );

        $this->createApprovedDocumentChecklist(
            $context,
            $organization,
            $operator,
            $manager
        );
    }

    private function createApprovedLogisticsChecklist(
        array $context,
        User $operator,
        User $manager,
    ): ReadinessChecklist {
        $checklist =
            $this->preparationService()
                ->createInitialDraft(
                    $operator,
                    $context['forecast'],
                    ReadinessType::LOGISTICS
                );

        $item =
            $checklist
                ->items
                ->firstOrFail();

        $this->workflowService()
            ->updateItem(
                $operator,
                $checklist,
                $item,
                [
                    'is_satisfied' =>
                        true,

                    'note' =>
                        'Logistics contributor ready.',
                ]
            );

        $checklist =
            $this->workflowService()
                ->submit(
                    $operator,
                    $checklist
                );

        return $this->reviewService()
            ->approve(
                $manager,
                $checklist
            );
    }

    private function createApprovedDocumentChecklist(
        array $context,
        Organization $organization,
        User $operator,
        User $manager,
    ): ReadinessChecklist {
        $requirement =
            ReadinessRequirement::query()
                ->where(
                    'readiness_type',
                    ReadinessType::DOCUMENT
                        ->value
                )
                ->where(
                    'commodity_id',
                    $context['commodity']->id
                )
                ->firstOrFail();

        $document =
            $this->documentService()
                ->create(
                    $operator,
                    $requirement,
                    [
                        'document_name' =>
                            'Dokumen Operasional '
                            .$organization->code,

                        'reference_number' =>
                            'REF-RFP-'
                            .$organization->id
                            .'-'
                            .$context['forecast']->id,

                        'valid_from' =>
                            '2026-08-01 00:00:00',

                        'expires_at' =>
                            '2026-08-25 17:00:00',

                        'notes' =>
                            'RFP contributor document.',
                    ]
                );

        $document =
            $this->documentService()
                ->markValid(
                    $operator,
                    $document
                );

        $checklist =
            $this->preparationService()
                ->createInitialDraft(
                    $operator,
                    $context['forecast'],
                    ReadinessType::DOCUMENT
                );

        $item =
            $checklist
                ->items
                ->firstOrFail();

        $this->workflowService()
            ->updateItem(
                $operator,
                $checklist,
                $item,
                [
                    'is_satisfied' =>
                        true,

                    'document_record_id' =>
                        $document->id,

                    'note' =>
                        'Document contributor ready.',
                ]
            );

        $checklist =
            $this->workflowService()
                ->submit(
                    $operator,
                    $checklist
                );

        return $this->reviewService()
            ->approve(
                $manager,
                $checklist
            );
    }

    private function createContext(
        string $suffix,
    ): array {
        $unit =
            Unit::create([
                'code' =>
                    "KG-RFP-FB-{$suffix}",

                'name' =>
                    "Kilogram RFP Fallback {$suffix}",

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
                    "COM-RFP-FB-{$suffix}",

                'name' =>
                    "Commodity RFP Fallback {$suffix}",

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
                "SPPG-RFP-FB-{$suffix}"
            );

        $primary =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-RFP-PRIMARY-{$suffix}"
            );

        $network =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-RFP-NETWORK-{$suffix}"
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

        $primaryOperator =
            $this->createKdkmpUser(
                $primary,
                UserRole::KDKMP_OPERATOR
            );

        $primaryManager =
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

        $forecast =
            DemandForecast::create([
                'sppg_organization_id' =>
                    $sppg->id,

                'commodity_id' =>
                    $commodity->id,

                'unit_id' =>
                    $unit->id,

                'forecast_code' =>
                    "FC-RFP-FB-{$suffix}",

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

                'notes' =>
                    'RFP fallback integration fixture.',

                'published_at' =>
                    '2026-08-10 08:00:00',

                'version' =>
                    1,

                'created_by' =>
                    $sppgUser->id,

                'updated_by' =>
                    $sppgUser->id,
            ]);

        return [
            'unit' =>
                $unit,

            'commodity' =>
                $commodity,

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

            'primaryOperator' =>
                $primaryOperator,

            'primaryManager' =>
                $primaryManager,

            'networkOperator' =>
                $networkOperator,

            'networkManager' =>
                $networkManager,

            'forecast' =>
                $forecast,
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
                "Organization {$code}",

            'organization_type' =>
                $type,

            'is_active' =>
                true,

            'general_location' =>
                'Lokasi Test RFP Fallback',
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
                "Producer {$code}",

            'village' =>
                'Desa Test',

            'district' =>
                'Kecamatan Test',

            'contact_phone' =>
                '081234567890',

            'notes' =>
                'RFP fallback producer fixture.',

            'is_active' =>
                true,

            'created_by' =>
                $creator->id,
        ]);
    }
}