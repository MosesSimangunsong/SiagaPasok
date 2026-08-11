<?php

namespace Tests\Feature\Readiness;

use App\Enums\CommitmentApprovalStatus;
use App\Enums\CommitmentLifecycleStatus;
use App\Enums\DocumentStatus;
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
use App\Models\SupplyCommitment;
use App\Models\SupplyNetworkLink;
use App\Models\Unit;
use App\Models\User;
use App\Services\Readiness\DocumentRecordService;
use App\Services\Readiness\ReadyForProcurementEvaluationService;
use App\Services\Readiness\ReadinessChecklistPreparationService;
use App\Services\Readiness\ReadinessChecklistReviewService;
use App\Services\Readiness\ReadinessChecklistWorkflowService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadyForProcurementEvaluationTest extends TestCase
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

    public function test_published_covered_forecast_with_fully_ready_contributor_is_ready_for_procurement(): void
    {
        $context =
            $this->createOperationalContext(
                'ALL-READY'
            );

        $this->createApprovedLogisticsChecklist(
            $context,
            'LOG-RFP-ALL-READY'
        );

        $this->createApprovedDocumentChecklist(
            $context,
            'DOC-RFP-ALL-READY'
        );

        $evaluatedAt =
            CarbonImmutable::parse(
                '2026-08-10 10:00:00'
            );

        $result =
            $this->evaluationService()
                ->evaluate(
                    $context['forecast'],
                    $evaluatedAt
                );

        $this->assertSame(
            $context['forecast']->id,
            $result->forecastId
        );

        $this->assertTrue(
            $result->forecastPublished
        );

        $this->assertTrue(
            $result->operationallyValid
        );

        $this->assertTrue(
            $result->volumeReady
        );

        $this->assertSame(
    '0.000000',
    $result->atRiskSupply
);

        $this->assertSame(
            [
                $context['kdkmp']->id,
            ],
            $result->contributorOrganizationIds
        );

        $this->assertCount(
            1,
            $result->contributorReadinessResults
        );

        $this->assertTrue(
            $result->allContributorsLogisticsReady
        );

        $this->assertTrue(
            $result->allContributorsDocumentReady
        );

        $this->assertTrue(
            $result->readyForProcurement
        );

        $this->assertSame(
            [],
            $result->reasonCodes
        );

        $this->assertTrue(
            $result->evaluatedAt
                ->equalTo(
                    $evaluatedAt
                )
        );

        $contributorResult =
            $result
                ->contributorReadinessResults[0];

        $this->assertTrue(
            $contributorResult
                ->evaluatedAt
                ->equalTo(
                    $evaluatedAt
                )
        );
    }

    public function test_insufficient_safe_supply_makes_ready_for_procurement_false(): void
    {
        $context =
            $this->createOperationalContext(
                'VOLUME-NOT-READY'
            );

        $this->createApprovedLogisticsChecklist(
            $context,
            'LOG-RFP-VOLUME-NOT-READY'
        );

        $this->createApprovedDocumentChecklist(
            $context,
            'DOC-RFP-VOLUME-NOT-READY'
        );

        /*
         * Approved GREEN commitment tetap 300 kg,
         * demand naik menjadi 400 kg.
         */
        $context['forecast']->update([
            'target_volume' =>
                '400.000000',
        ]);

        $result =
            $this->evaluationService()
                ->evaluate(
                    $context['forecast']
                );

        $this->assertFalse(
            $result->volumeReady
        );

        /*
         * PRIMARY tetap contributor karena
         * effective Safe Supply masih > 0.
         */
        $this->assertSame(
            [
                $context['kdkmp']->id,
            ],
            $result->contributorOrganizationIds
        );

        $this->assertTrue(
            $result->allContributorsLogisticsReady
        );

        $this->assertTrue(
            $result->allContributorsDocumentReady
        );

        $this->assertFalse(
            $result->readyForProcurement
        );

        $this->assertContains(
            'VOLUME_NOT_READY',
            $result->reasonCodes
        );
    }

    public function test_zero_effective_safe_supply_produces_empty_contributor_set_and_fails_closed(): void
    {
        $context =
            $this->createOperationalContext(
                'NO-CONTRIBUTOR'
            );

        $this->createApprovedLogisticsChecklist(
            $context,
            'LOG-RFP-NO-CONTRIBUTOR'
        );

        $this->createApprovedDocumentChecklist(
            $context,
            'DOC-RFP-NO-CONTRIBUTOR'
        );

        /*
         * YELLOW menjadi At-Risk.
         * Tidak masuk Safe Supply dan tidak
         * menjadikan PRIMARY contributor.
         */
        $context['commitment']->update([
            'current_confidence' =>
                SupplyConfidence::YELLOW,
        ]);

        $result =
            $this->evaluationService()
                ->evaluate(
                    $context['forecast']
                );

        $this->assertFalse(
            $result->volumeReady
        );

        $this->assertSame(
    '300.000000',
    $result->atRiskSupply
);

        $this->assertSame(
            [],
            $result->contributorOrganizationIds
        );

        $this->assertSame(
            [],
            $result->contributorReadinessResults
        );

        /*
         * Empty set tidak boleh lolos melalui
         * vacuous truth.
         */
        $this->assertFalse(
            $result->allContributorsLogisticsReady
        );

        $this->assertFalse(
            $result->allContributorsDocumentReady
        );

        $this->assertFalse(
            $result->readyForProcurement
        );

        $this->assertContains(
            'VOLUME_NOT_READY',
            $result->reasonCodes
        );

        $this->assertContains(
            'NO_EFFECTIVE_CONTRIBUTORS',
            $result->reasonCodes
        );
    }

    public function test_missing_logistics_readiness_blocks_ready_for_procurement(): void
    {
        $context =
            $this->createOperationalContext(
                'LOGISTICS-MISSING'
            );

        /*
         * Hanya Document Readiness yang approved.
         */
        $this->createApprovedDocumentChecklist(
            $context,
            'DOC-RFP-LOGISTICS-MISSING'
        );

        $result =
            $this->evaluationService()
                ->evaluate(
                    $context['forecast']
                );

        $this->assertTrue(
            $result->volumeReady
        );

        $this->assertFalse(
            $result->allContributorsLogisticsReady
        );

        $this->assertTrue(
            $result->allContributorsDocumentReady
        );

        $this->assertFalse(
            $result->readyForProcurement
        );

        $this->assertContains(
            'LOGISTICS_NOT_READY',
            $result->reasonCodes
        );

        $this->assertNotContains(
            'DOCUMENT_NOT_READY',
            $result->reasonCodes
        );

        $contributorResult =
            $result
                ->contributorReadinessResults[0];

        $this->assertContains(
            'CHECKLIST_MISSING',
            $contributorResult
                ->logisticsReasonCodes
        );
    }

    public function test_missing_document_readiness_blocks_ready_for_procurement(): void
    {
        $context =
            $this->createOperationalContext(
                'DOCUMENT-MISSING'
            );

        /*
         * Hanya Logistics Readiness yang approved.
         */
        $this->createApprovedLogisticsChecklist(
            $context,
            'LOG-RFP-DOCUMENT-MISSING'
        );

        $result =
            $this->evaluationService()
                ->evaluate(
                    $context['forecast']
                );

        $this->assertTrue(
            $result->volumeReady
        );

        $this->assertTrue(
            $result->allContributorsLogisticsReady
        );

        $this->assertFalse(
            $result->allContributorsDocumentReady
        );

        $this->assertFalse(
            $result->readyForProcurement
        );

        $this->assertContains(
            'DOCUMENT_NOT_READY',
            $result->reasonCodes
        );

        $this->assertNotContains(
            'LOGISTICS_NOT_READY',
            $result->reasonCodes
        );

        $contributorResult =
            $result
                ->contributorReadinessResults[0];

        $this->assertContains(
            'CHECKLIST_MISSING',
            $contributorResult
                ->documentReasonCodes
        );
    }

    public function test_forecast_business_revision_invalidates_previously_approved_readiness(): void
    {
        $context =
            $this->createOperationalContext(
                'FORECAST-REVISION'
            );

        $this->createApprovedLogisticsChecklist(
            $context,
            'LOG-RFP-FORECAST-REVISION'
        );

        $this->createApprovedDocumentChecklist(
            $context,
            'DOC-RFP-FORECAST-REVISION'
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
         * Supply tetap cukup.
         *
         * Hanya Forecast business version berubah,
         * sehingga old readiness snapshot stale.
         */
        $context['forecast']->update([
            'version' =>
                2,
        ]);

        $after =
            $this->evaluationService()
                ->evaluate(
                    $context['forecast']
                );

        $this->assertTrue(
            $after->volumeReady
        );

        $this->assertFalse(
            $after->allContributorsLogisticsReady
        );

        $this->assertFalse(
            $after->allContributorsDocumentReady
        );

        $this->assertFalse(
            $after->readyForProcurement
        );

        $this->assertContains(
            'LOGISTICS_NOT_READY',
            $after->reasonCodes
        );

        $this->assertContains(
            'DOCUMENT_NOT_READY',
            $after->reasonCodes
        );

        $contributorResult =
            $after
                ->contributorReadinessResults[0];

        $this->assertContains(
            'FORECAST_VERSION_STALE',
            $contributorResult
                ->logisticsReasonCodes
        );

        $this->assertContains(
            'FORECAST_VERSION_STALE',
            $contributorResult
                ->documentReasonCodes
        );
    }

    public function test_revoked_required_document_invalidates_ready_for_procurement_immediately(): void
    {
        $context =
            $this->createOperationalContext(
                'DOCUMENT-REVOKED'
            );

        $this->createApprovedLogisticsChecklist(
            $context,
            'LOG-RFP-DOCUMENT-REVOKED'
        );

        $documentContext =
            $this->createApprovedDocumentChecklist(
                $context,
                'DOC-RFP-DOCUMENT-REVOKED'
            );

        $before =
            $this->evaluationService()
                ->evaluate(
                    $context['forecast']
                );

        $this->assertTrue(
            $before->readyForProcurement
        );

        $document =
            $this->documentService()
                ->revoke(
                    $context['operator'],
                    $documentContext['document'],
                    'Dokumen dicabut.'
                );

        $this->assertSame(
            DocumentStatus::REVOKED,
            $document->status
        );

        $after =
            $this->evaluationService()
                ->evaluate(
                    $context['forecast']
                );

        $this->assertTrue(
            $after->volumeReady
        );

        $this->assertTrue(
            $after->allContributorsLogisticsReady
        );

        $this->assertFalse(
            $after->allContributorsDocumentReady
        );

        $this->assertFalse(
            $after->readyForProcurement
        );

        $this->assertContains(
            'DOCUMENT_NOT_READY',
            $after->reasonCodes
        );

        $this->assertContains(
            'DOCUMENT_INVALID',
            $after
                ->contributorReadinessResults[0]
                ->documentReasonCodes
        );
    }

    public function test_supply_confidence_downgrade_invalidates_ready_for_procurement_without_manual_rfp_action(): void
    {
        $context =
            $this->createOperationalContext(
                'SUPPLY-DOWNGRADE'
            );

        $this->createApprovedLogisticsChecklist(
            $context,
            'LOG-RFP-SUPPLY-DOWNGRADE'
        );

        $this->createApprovedDocumentChecklist(
            $context,
            'DOC-RFP-SUPPLY-DOWNGRADE'
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
         * Tidak ada update terhadap RFP.
         *
         * Hanya canonical supply truth yang berubah.
         */
        $context['commitment']->update([
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

        $this->assertSame(
            [],
            $after->contributorOrganizationIds
        );

        $this->assertFalse(
            $after->readyForProcurement
        );

        $this->assertContains(
            'VOLUME_NOT_READY',
            $after->reasonCodes
        );

        $this->assertContains(
            'NO_EFFECTIVE_CONTRIBUTORS',
            $after->reasonCodes
        );
    }

    public function test_equality_at_required_end_boundary_can_still_be_ready_for_procurement(): void
    {
        $context =
            $this->createOperationalContext(
                'AT-BOUNDARY'
            );

        $this->createApprovedLogisticsChecklist(
            $context,
            'LOG-RFP-AT-BOUNDARY'
        );

        $this->createApprovedDocumentChecklist(
            $context,
            'DOC-RFP-AT-BOUNDARY'
        );

        $result =
            $this->evaluationService()
                ->evaluate(
                    $context['forecast'],
                    CarbonImmutable::parse(
                        '2026-08-25 17:00:00'
                    )
                );

        $this->assertTrue(
            $result->forecastPublished
        );

        $this->assertTrue(
            $result->operationallyValid
        );

        $this->assertTrue(
            $result->volumeReady
        );

        $this->assertTrue(
            $result->readyForProcurement
        );

        $this->assertSame(
            [],
            $result->reasonCodes
        );
    }

    public function test_after_required_end_boundary_ready_for_procurement_fails_closed(): void
    {
        $context =
            $this->createOperationalContext(
                'AFTER-BOUNDARY'
            );

        $this->createApprovedLogisticsChecklist(
            $context,
            'LOG-RFP-AFTER-BOUNDARY'
        );

        $this->createApprovedDocumentChecklist(
            $context,
            'DOC-RFP-AFTER-BOUNDARY'
        );

        $result =
            $this->evaluationService()
                ->evaluate(
                    $context['forecast'],
                    CarbonImmutable::parse(
                        '2026-08-25 17:00:01'
                    )
                );

        $this->assertTrue(
            $result->forecastPublished
        );

        $this->assertFalse(
            $result->operationallyValid
        );

        $this->assertFalse(
            $result->volumeReady
        );

        $this->assertSame(
    '0.000000',
    $result->atRiskSupply
);

        $this->assertSame(
            [],
            $result->contributorOrganizationIds
        );

        $this->assertFalse(
            $result->readyForProcurement
        );

        $this->assertContains(
            'FORECAST_WINDOW_ENDED',
            $result->reasonCodes
        );
    }

    public function test_draft_forecast_is_not_ready_for_procurement(): void
    {
        $context =
            $this->createOperationalContext(
                'DRAFT'
            );

        $context['forecast']->update([
            'status' =>
                ForecastStatus::DRAFT,
        ]);

        $result =
            $this->evaluationService()
                ->evaluate(
                    $context['forecast']
                );

        $this->assertFalse(
            $result->forecastPublished
        );

        $this->assertNull(
    $result->atRiskSupply
);

        $this->assertTrue(
            $result->operationallyValid
        );

        $this->assertFalse(
            $result->readyForProcurement
        );

        $this->assertContains(
            'FORECAST_NOT_PUBLISHED',
            $result->reasonCodes
        );
    }

    public function test_closed_forecast_is_not_ready_for_procurement(): void
    {
        $context =
            $this->createOperationalContext(
                'CLOSED'
            );

        $context['forecast']->update([
            'status' =>
                ForecastStatus::CLOSED,

            'closed_at' =>
                '2026-08-10 09:30:00',
        ]);

        $result =
            $this->evaluationService()
                ->evaluate(
                    $context['forecast']
                );

        $this->assertFalse(
            $result->forecastPublished
        );

        $this->assertFalse(
            $result->readyForProcurement
        );

        $this->assertContains(
            'FORECAST_NOT_PUBLISHED',
            $result->reasonCodes
        );
    }

    public function test_cancelled_forecast_is_not_ready_for_procurement(): void
    {
        $context =
            $this->createOperationalContext(
                'CANCELLED'
            );

        $context['forecast']->update([
            'status' =>
                ForecastStatus::CANCELLED,

            'cancelled_at' =>
                '2026-08-10 09:30:00',

            'cancellation_reason' =>
                'Forecast dibatalkan untuk pengujian.',
        ]);

        $result =
            $this->evaluationService()
                ->evaluate(
                    $context['forecast']
                );

        $this->assertFalse(
            $result->forecastPublished
        );

        $this->assertFalse(
            $result->readyForProcurement
        );

        $this->assertContains(
            'FORECAST_NOT_PUBLISHED',
            $result->reasonCodes
        );
    }

    public function test_result_serialization_exposes_derived_contract_without_editable_state(): void
    {
        $context =
            $this->createOperationalContext(
                'SERIALIZATION'
            );

        $this->createApprovedLogisticsChecklist(
            $context,
            'LOG-RFP-SERIALIZATION'
        );

        $this->createApprovedDocumentChecklist(
            $context,
            'DOC-RFP-SERIALIZATION'
        );

        $result =
            $this->evaluationService()
                ->evaluate(
                    $context['forecast']
                );

        $payload =
            $result->toArray();

        $this->assertSame(
            $context['forecast']->id,
            $payload['forecast_id']
        );

        $this->assertTrue(
            $payload['volume_ready']
        );

        $this->assertArrayHasKey(
    'at_risk_supply',
    $payload
);

$this->assertSame(
    '0.000000',
    $payload['at_risk_supply']
);

        $this->assertTrue(
            $payload[
                'all_contributors_logistics_ready'
            ]
        );

        $this->assertTrue(
            $payload[
                'all_contributors_document_ready'
            ]
        );

        $this->assertTrue(
            $payload['ready_for_procurement']
        );

        $this->assertSame(
            [
                $context['kdkmp']->id,
            ],
            $payload[
                'contributor_organization_ids'
            ]
        );

        $this->assertCount(
            1,
            $payload['contributor_readiness']
        );

        $this->assertSame(
            [],
            $payload['reason_codes']
        );
    }

    private function evaluationService():
        ReadyForProcurementEvaluationService
    {
        return app(
            ReadyForProcurementEvaluationService::class
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

    private function createApprovedLogisticsChecklist(
        array $context,
        string $requirementCode,
    ): ReadinessChecklist {
        $this->createRequirement(
            context: $context,
            type:
                ReadinessType::LOGISTICS,
            code:
                $requirementCode
        );

        $checklist =
            $this->preparationService()
                ->createInitialDraft(
                    $context['operator'],
                    $context['forecast'],
                    ReadinessType::LOGISTICS
                );

        $item =
            $checklist
                ->items
                ->firstOrFail();

        $this->workflowService()
            ->updateItem(
                $context['operator'],
                $checklist,
                $item,
                [
                    'is_satisfied' =>
                        true,

                    'note' =>
                        'Logistics requirement satisfied.',
                ]
            );

        $checklist =
            $this->workflowService()
                ->submit(
                    $context['operator'],
                    $checklist
                );

        return $this->reviewService()
            ->approve(
                $context['manager'],
                $checklist
            );
    }

    private function createApprovedDocumentChecklist(
        array $context,
        string $requirementCode,
    ): array {
        $requirement =
            $this->createRequirement(
                context: $context,
                type:
                    ReadinessType::DOCUMENT,
                code:
                    $requirementCode,
                scope:
                    RequirementScope::ORGANIZATION
            );

        $document =
            $this->documentService()
                ->create(
                    $context['operator'],
                    $requirement,
                    [
                        'document_name' =>
                            'Dokumen Operasional',

                        'reference_number' =>
                            "REF-{$requirementCode}",

                        'valid_from' =>
                            '2026-08-01 00:00:00',

                        'expires_at' =>
                            '2026-08-25 17:00:00',

                        'notes' =>
                            'RFP evaluation fixture.',
                    ]
                );

        $document =
            $this->documentService()
                ->markValid(
                    $context['operator'],
                    $document
                );

        $checklist =
            $this->preparationService()
                ->createInitialDraft(
                    $context['operator'],
                    $context['forecast'],
                    ReadinessType::DOCUMENT
                );

        $item =
            $checklist
                ->items
                ->firstOrFail();

        $this->workflowService()
            ->updateItem(
                $context['operator'],
                $checklist,
                $item,
                [
                    'is_satisfied' =>
                        true,

                    'document_record_id' =>
                        $document->id,

                    'note' =>
                        'Document requirement satisfied.',
                ]
            );

        $checklist =
            $this->workflowService()
                ->submit(
                    $context['operator'],
                    $checklist
                );

        $checklist =
            $this->reviewService()
                ->approve(
                    $context['manager'],
                    $checklist
                );

        return [
            'requirement' =>
                $requirement,

            'document' =>
                $document->fresh(),

            'checklist' =>
                $checklist,
        ];
    }

    private function createOperationalContext(
        string $suffix,
    ): array {
        $unit =
            Unit::create([
                'code' =>
                    "kg-rfp-eval-{$suffix}",

                'name' =>
                    "Kilogram RFP Eval {$suffix}",

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
                    "COM-RFP-EVAL-{$suffix}",

                'name' =>
                    "Commodity RFP Eval {$suffix}",

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
            User::factory()->create();

        $sppg =
            $this->createOrganization(
                OrganizationType::SPPG,
                "SPPG-RFP-EVAL-{$suffix}"
            );

        $kdkmp =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-RFP-EVAL-{$suffix}"
            );

        SupplyNetworkLink::create([
            'sppg_organization_id' =>
                $sppg->id,

            'kdkmp_organization_id' =>
                $kdkmp->id,

            'network_role' =>
                NetworkRole::PRIMARY,

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
                $kdkmp,
                UserRole::KDKMP_OPERATOR
            );

        $manager =
            $this->createKdkmpUser(
                $kdkmp,
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
                    "FRC-RFP-EVAL-{$suffix}",

                'target_volume' =>
                    '300.000000',

                'required_start_at' =>
                    '2026-08-20 08:00:00',

                'required_end_at' =>
                    '2026-08-25 17:00:00',

                'freshness_interval_hours' =>
                    24,

                'status' =>
                    ForecastStatus::PUBLISHED,

                'notes' =>
                    'Ready for Procurement fixture.',

                'published_at' =>
                    '2026-08-10 08:00:00',

                'version' =>
                    1,

                'created_by' =>
                    $sppgUser->id,

                'updated_by' =>
                    $sppgUser->id,
            ]);

        $producer =
            Producer::create([
                'organization_id' =>
                    $kdkmp->id,

                'producer_code' =>
                    "PROD-RFP-EVAL-{$suffix}",

                'name' =>
                    "Producer RFP Eval {$suffix}",

                'village' =>
                    'Desa Test',

                'district' =>
                    'Kecamatan Test',

                'contact_phone' =>
                    '081234567890',

                'notes' =>
                    'RFP fixture producer.',

                'is_active' =>
                    true,

                'created_by' =>
                    $operator->id,
            ]);

        $commitment =
            SupplyCommitment::create([
                'forecast_id' =>
                    $forecast->id,

                'organization_id' =>
                    $kdkmp->id,

                'producer_id' =>
                    $producer->id,

                'expected_harvest_id' =>
                    null,

                'commodity_id' =>
                    $commodity->id,

                'active_version_id' =>
                    null,

                'lifecycle_status' =>
                    CommitmentLifecycleStatus::ACTIVE,

                'current_confidence' =>
                    SupplyConfidence::GREEN,

                'last_confidence_verified_at' =>
                    '2026-08-10 09:00:00',

                'created_by' =>
                    $operator->id,

                'cancelled_at' =>
                    null,

                'cancellation_reason' =>
                    null,

                'expired_at' =>
                    null,
            ]);

        $commitmentVersion =
            CommitmentVersion::create([
                'commitment_id' =>
                    $commitment->id,

                'version_no' =>
                    1,

                'min_volume' =>
                    '300.000000',

                'max_volume' =>
                    '350.000000',

                'unit_id' =>
                    $unit->id,

                'availability_start_at' =>
                    '2026-08-20 07:00:00',

                'availability_end_at' =>
                    '2026-08-25 18:00:00',

                'notes' =>
                    'Approved Safe Supply fixture.',

                'approval_status' =>
                    CommitmentApprovalStatus::APPROVED,

                'change_reason' =>
                    null,

                'operator_justification' =>
                    null,

                'created_by' =>
                    $operator->id,

                'submitted_by' =>
                    $operator->id,

                'submitted_at' =>
                    '2026-08-10 08:30:00',

                'reviewed_by' =>
                    $manager->id,

                'reviewed_at' =>
                    '2026-08-10 09:00:00',

                'review_reason' =>
                    null,

                'approved_at' =>
                    '2026-08-10 09:00:00',

                'created_at' =>
                    '2026-08-10 08:00:00',
            ]);

        $commitment->update([
            'active_version_id' =>
                $commitmentVersion->id,
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

            'kdkmp' =>
                $kdkmp,

            'sppgUser' =>
                $sppgUser,

            'operator' =>
                $operator,

            'manager' =>
                $manager,

            'forecast' =>
                $forecast,

            'producer' =>
                $producer,

            'commitment' =>
                $commitment,

            'commitmentVersion' =>
                $commitmentVersion,
        ];
    }

    private function createRequirement(
        array $context,
        ReadinessType $type,
        string $code,
        RequirementScope $scope =
            RequirementScope::FORECAST,
    ): ReadinessRequirement {
        return ReadinessRequirement::create([
            'readiness_type' =>
                $type,

            'requirement_code' =>
                $code,

            'label' =>
                "Requirement {$code}",

            'requirement_scope' =>
                $scope,

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