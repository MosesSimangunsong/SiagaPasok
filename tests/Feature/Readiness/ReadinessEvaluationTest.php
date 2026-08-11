<?php

namespace Tests\Feature\Readiness;

use App\Enums\CommitmentApprovalStatus;
use App\Enums\CommitmentLifecycleStatus;
use App\Enums\ForecastStatus;
use App\Enums\NetworkRole;
use App\Enums\OrganizationType;
use App\Enums\ReadinessApprovalStatus;
use App\Enums\ReadinessType;
use App\Enums\RequirementScope;
use App\Enums\SupplyConfidence;
use App\Enums\UserRole;
use App\Enums\AuditSource;
use App\Enums\DocumentStatus;
use App\Models\AuditLog;
use Illuminate\Validation\ValidationException;
use App\Models\Commodity;
use App\Models\CommitmentVersion;
use App\Models\DemandForecast;
use App\Models\DocumentRecord;
use App\Models\Organization;
use App\Models\Producer;
use App\Models\ReadinessChecklist;
use App\Models\ReadinessRequirement;
use App\Models\SupplyCommitment;
use App\Models\SupplyNetworkLink;
use App\Models\Unit;
use App\Models\User;
use App\Services\Readiness\DocumentRecordService;
use App\Services\Readiness\ReadinessChecklistPreparationService;
use App\Services\Readiness\ReadinessChecklistReviewService;
use App\Services\Readiness\ReadinessChecklistRevisionService;
use App\Services\Readiness\ReadinessChecklistWorkflowService;
use App\Services\Readiness\ReadinessEvaluationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadinessEvaluationTest extends TestCase
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

    public function test_approved_logistics_and_valid_approved_document_make_contributor_fully_ready(): void
    {
        $context =
            $this->createOperationalContext(
                'ALL-READY'
            );

        $this->createApprovedLogisticsChecklist(
            $context,
            'LOG-ALL-READY'
        );

        $documentContext =
            $this->createApprovedDocumentChecklist(
                $context,
                'DOC-ALL-READY'
            );

        /*
         * Equality pada required_end_at
         * masih valid.
         */
        $this->assertTrue(
            $documentContext[
                'document'
            ]
                ->expires_at
                ->equalTo(
                    $context[
                        'forecast'
                    ]->required_end_at
                )
        );

        $result =
            $this->evaluationService()
                ->evaluateContributor(
                    $context['forecast'],
                    $context['kdkmp']->id,
                    CarbonImmutable::parse(
                        '2026-08-10 10:00:00'
                    )
                );

        $this->assertTrue(
            $result->isContributor
        );

        $this->assertTrue(
            $result->logisticsReady
        );

        $this->assertTrue(
            $result->documentReady
        );

        $this->assertTrue(
            $result->allReady()
        );

        $this->assertSame(
            [],
            $result->logisticsReasonCodes
        );

        $this->assertSame(
            [],
            $result->documentReasonCodes
        );
    }


    public function test_document_expiry_lifecycle_is_strictly_after_expiry_and_idempotent(): void
{
    $context =
        $this->createOperationalContext(
            'DOCUMENT-EXPIRY-LIFECYCLE'
        );

    $documentContext =
        $this->createApprovedDocumentChecklist(
            $context,
            'DOC-EXPIRY-LIFECYCLE'
        );

    $document =
        $documentContext[
            'document'
        ];

    $originalRevision =
        $document->revision_no;

    /*
     * Equality masih VALID.
     */
    $changedAtBoundary =
        $this->documentService()
            ->expireIfDue(
                $document,
                CarbonImmutable::parse(
                    '2026-08-25 17:00:00'
                )
            );

    $this->assertFalse(
        $changedAtBoundary
    );

    $this->assertSame(
        DocumentStatus::VALID,
        $document
            ->fresh()
            ->status
    );

    /*
     * Satu detik setelah expiry:
     * lifecycle materialized EXPIRED.
     */
    $changedAfterBoundary =
        $this->documentService()
            ->expireIfDue(
                $document->fresh(),
                CarbonImmutable::parse(
                    '2026-08-25 17:00:01'
                )
            );

    $this->assertTrue(
        $changedAfterBoundary
    );

    $document->refresh();

    $this->assertSame(
        DocumentStatus::EXPIRED,
        $document->status
    );

    /*
     * Time expiry bukan payload revision.
     */
    $this->assertSame(
        $originalRevision,
        $document->revision_no
    );

    $audit =
        AuditLog::query()
            ->where(
                'entity_id',
                $document->id
            )
            ->where(
                'action',
                'DOCUMENT_RECORD_EXPIRED'
            )
            ->firstOrFail();

    $this->assertSame(
        AuditSource::SYSTEM,
        $audit->source
    );

    $this->assertNull(
        $audit->actor_user_id
    );

    /*
     * Retry tidak menghasilkan expiry/audit kedua.
     */
    $changedAgain =
        $this->documentService()
            ->expireIfDue(
                $document,
                CarbonImmutable::parse(
                    '2026-08-25 18:00:00'
                )
            );

    $this->assertFalse(
        $changedAgain
    );

    $this->assertSame(
        1,
        AuditLog::query()
            ->where(
                'entity_id',
                $document->id
            )
            ->where(
                'action',
                'DOCUMENT_RECORD_EXPIRED'
            )
            ->count()
    );

    /*
     * EXPIRED tidak boleh diubah kembali menjadi
     * VALID tanpa metadata revision baru.
     */
    try {
        $this->documentService()
            ->markValid(
                $context['operator'],
                $document
            );

        $this->fail(
            'Document EXPIRED berhasil divalidasi '
            .'ulang tanpa update metadata.'
        );
    } catch (
        ValidationException $exception
    ) {
        $this->assertArrayHasKey(
            'status',
            $exception->errors()
        );
    }
}
    public function test_draft_pending_and_rejected_logistics_are_not_ready(): void
    {
        foreach (
            [
                'DRAFT',
                'PENDING',
                'REJECTED',
            ]
            as $state
        ) {
            $context =
                $this->createOperationalContext(
                    "STATUS-{$state}"
                );

            $requirement =
                $this->createRequirement(
                    context: $context,
                    type:
                        ReadinessType::LOGISTICS,
                    code:
                        "LOG-STATUS-{$state}"
                );

            $checklist =
                $this->preparationService()
                    ->createInitialDraft(
                        $context['operator'],
                        $context['forecast'],
                        ReadinessType::LOGISTICS
                    );

            if ($state !== 'DRAFT') {
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
                        ]
                    );

                $checklist =
                    $this->workflowService()
                        ->submit(
                            $context['operator'],
                            $checklist
                        );
            }

            if ($state === 'REJECTED') {
                $checklist =
                    $this->reviewService()
                        ->reject(
                            $context['manager'],
                            $checklist,
                            'Readiness belum dapat disetujui.'
                        );
            }

            $result =
                $this->evaluationService()
                    ->evaluateContributor(
                        $context['forecast'],
                        $context['kdkmp']->id,
                        CarbonImmutable::parse(
                            '2026-08-10 10:00:00'
                        )
                    );

            $this->assertTrue(
                $result->isContributor
            );

            $this->assertFalse(
                $result->logisticsReady,
                "Logistics {$state} tidak boleh READY."
            );

            $this->assertContains(
                'CHECKLIST_NOT_APPROVED',
                $result->logisticsReasonCodes
            );
        }
    }

    public function test_new_current_revision_immediately_invalidates_historical_approved_logistics(): void
    {
        $context =
            $this->createOperationalContext(
                'REVISION-INVALIDATES'
            );

        $versionOne =
            $this->createApprovedLogisticsChecklist(
                $context,
                'LOG-REVISION-INVALIDATES'
            );

        $before =
            $this->evaluationService()
                ->evaluateContributor(
                    $context['forecast'],
                    $context['kdkmp']->id
                );

        $this->assertTrue(
            $before->logisticsReady
        );

        $versionTwo =
            $this->revisionService()
                ->createRevision(
                    $context['operator'],
                    $versionOne
                );

        $versionOne->refresh();

        $this->assertSame(
            ReadinessApprovalStatus::APPROVED,
            $versionOne->status
        );

        $this->assertFalse(
            $versionOne->is_current_version
        );

        $this->assertSame(
            ReadinessApprovalStatus::DRAFT,
            $versionTwo->status
        );

        $this->assertTrue(
            $versionTwo->is_current_version
        );

        $after =
            $this->evaluationService()
                ->evaluateContributor(
                    $context['forecast'],
                    $context['kdkmp']->id
                );

        $this->assertFalse(
            $after->logisticsReady
        );

        $this->assertContains(
            'CHECKLIST_NOT_APPROVED',
            $after->logisticsReasonCodes
        );
    }

    public function test_forecast_revision_invalidates_approved_readiness_snapshot(): void
    {
        $context =
            $this->createOperationalContext(
                'FORECAST-STALE'
            );

        $checklist =
            $this->createApprovedLogisticsChecklist(
                $context,
                'LOG-FORECAST-STALE'
            );

        $this->assertSame(
            1,
            $checklist->forecast_version
        );

        /*
         * Supply masih overlap dan tetap GREEN.
         * Yang berubah hanya Forecast business
         * version.
         */
        $context['forecast']->update([
            'target_volume' =>
                '250.000000',

            'version' =>
                2,
        ]);

        $result =
            $this->evaluationService()
                ->evaluateContributor(
                    $context['forecast'],
                    $context['kdkmp']->id
                );

        $this->assertTrue(
            $result->isContributor
        );

        $this->assertFalse(
            $result->logisticsReady
        );

        $this->assertContains(
            'FORECAST_VERSION_STALE',
            $result->logisticsReasonCodes
        );
    }

    public function test_losing_effective_safe_supply_removes_contributor_and_both_readiness_results_fail_closed(): void
    {
        $context =
            $this->createOperationalContext(
                'CONTRIBUTOR-LOSS'
            );

        $this->createApprovedLogisticsChecklist(
            $context,
            'LOG-CONTRIBUTOR-LOSS'
        );

        $this->createApprovedDocumentChecklist(
            $context,
            'DOC-CONTRIBUTOR-LOSS'
        );

        $before =
            $this->evaluationService()
                ->evaluateContributor(
                    $context['forecast'],
                    $context['kdkmp']->id
                );

        $this->assertTrue(
            $before->allReady()
        );

        /*
         * YELLOW keluar dari Safe Supply dan
         * hanya menjadi At-Risk.
         */
        $context['commitment']->update([
            'current_confidence' =>
                SupplyConfidence::YELLOW,
        ]);

        $after =
            $this->evaluationService()
                ->evaluateContributor(
                    $context['forecast'],
                    $context['kdkmp']->id
                );

        $this->assertFalse(
            $after->isContributor
        );

        $this->assertFalse(
            $after->logisticsReady
        );

        $this->assertFalse(
            $after->documentReady
        );

        $this->assertFalse(
            $after->allReady()
        );

        $this->assertContains(
            'NOT_CURRENT_CONTRIBUTOR',
            $after->logisticsReasonCodes
        );

        $this->assertContains(
            'NOT_CURRENT_CONTRIBUTOR',
            $after->documentReasonCodes
        );
    }

    public function test_required_item_that_no_longer_remains_satisfied_makes_logistics_not_ready(): void
    {
        $context =
            $this->createOperationalContext(
                'ITEM-INVALID'
            );

        $checklist =
            $this->createApprovedLogisticsChecklist(
                $context,
                'LOG-ITEM-INVALID'
            );

        $before =
            $this->evaluationService()
                ->evaluateContributor(
                    $context['forecast'],
                    $context['kdkmp']->id
                );

        $this->assertTrue(
            $before->logisticsReady
        );

        /*
         * Defensive persisted-state test.
         *
         * Normal service tidak mengizinkan
         * approved payload diedit. Ini
         * mensimulasikan corrupted/external
         * persisted mutation agar evaluator
         * tetap fail closed.
         */
        $item =
            $checklist
                ->items
                ->firstOrFail();

        $item->update([
            'is_satisfied' =>
                false,
        ]);

        $after =
            $this->evaluationService()
                ->evaluateContributor(
                    $context['forecast'],
                    $context['kdkmp']->id
                );

        $this->assertFalse(
            $after->logisticsReady
        );

        $this->assertContains(
            'REQUIRED_ITEM_UNSATISFIED',
            $after->logisticsReasonCodes
        );
    }

    public function test_revoked_required_document_immediately_makes_document_readiness_false(): void
    {
        $context =
            $this->createOperationalContext(
                'DOCUMENT-REVOKED'
            );

        $documentContext =
            $this->createApprovedDocumentChecklist(
                $context,
                'DOC-REVOKED'
            );

        $before =
            $this->evaluationService()
                ->evaluateContributor(
                    $context['forecast'],
                    $context['kdkmp']->id
                );

        $this->assertTrue(
            $before->documentReady
        );

        $document =
            $this->documentService()
                ->revoke(
                    $context['operator'],
                    $documentContext[
                        'document'
                    ],
                    'Dokumen tidak lagi berlaku.'
                );

        $this->assertSame(
            \App\Enums\DocumentStatus::REVOKED,
            $document->status
        );

        $after =
            $this->evaluationService()
                ->evaluateContributor(
                    $context['forecast'],
                    $context['kdkmp']->id
                );

        $this->assertFalse(
            $after->documentReady
        );

        $this->assertContains(
            'DOCUMENT_INVALID',
            $after->documentReasonCodes
        );
    }

    public function test_changed_then_revalidated_document_still_requires_new_readiness_approval(): void
    {
        $context =
            $this->createOperationalContext(
                'DOCUMENT-REVISION'
            );

        $documentContext =
            $this->createApprovedDocumentChecklist(
                $context,
                'DOC-REVISION'
            );

        $document =
            $documentContext[
                'document'
            ];

        $item =
            $documentContext[
                'checklist'
            ]
                ->items
                ->firstOrFail();

        $this->assertSame(
            2,
            $document->revision_no
        );

        $this->assertSame(
            2,
            $item->document_record_revision_no
        );

        /*
         * Edit:
         * revision 2 → 3
         * VALID → PENDING
         */
        $document =
            $this->documentService()
                ->update(
                    $context['operator'],
                    $document,
                    [
                        'notes' =>
                            'Metadata diperbarui.',
                    ]
                );

        /*
         * Revalidate:
         * revision 3 → 4
         * PENDING → VALID
         */
        $document =
            $this->documentService()
                ->markValid(
                    $context['operator'],
                    $document
                );

        $this->assertSame(
            4,
            $document->revision_no
        );

        $this->assertSame(
            \App\Enums\DocumentStatus::VALID,
            $document->status
        );

        /*
         * Dokumen saat ini valid, tetapi old
         * readiness approval membuktikan
         * revision 2.
         *
         * Jadi tetap FALSE sampai membuat
         * readiness revision baru.
         */
        $result =
            $this->evaluationService()
                ->evaluateContributor(
                    $context['forecast'],
                    $context['kdkmp']->id
                );

        $this->assertFalse(
            $result->documentReady
        );

        $this->assertContains(
            'DOCUMENT_INVALID',
            $result->documentReasonCodes
        );
    }

    public function test_required_document_expiring_before_forecast_end_makes_document_readiness_false(): void
    {
        $context =
            $this->createOperationalContext(
                'DOCUMENT-EXPIRY'
            );

        $documentContext =
            $this->createApprovedDocumentChecklist(
                $context,
                'DOC-EXPIRY'
            );

        $before =
            $this->evaluationService()
                ->evaluateContributor(
                    $context['forecast'],
                    $context['kdkmp']->id
                );

        $this->assertTrue(
            $before->documentReady
        );

        /*
         * Defensive persisted-state test.
         *
         * Bypass DocumentRecordService untuk
         * membuktikan evaluator tidak hanya
         * bergantung pada revision counter.
         *
         * revision_no tetap sama + status VALID,
         * tetapi validity window sekarang
         * tidak menutup Forecast required window.
         */
        $documentContext[
            'document'
        ]->update([
            'expires_at' =>
                '2026-08-24 23:59:59',
        ]);

        $document =
            $documentContext[
                'document'
            ]->fresh();

        $this->assertSame(
            \App\Enums\DocumentStatus::VALID,
            $document->status
        );

        $this->assertSame(
            2,
            $document->revision_no
        );

        $after =
            $this->evaluationService()
                ->evaluateContributor(
                    $context['forecast'],
                    $context['kdkmp']->id
                );

        $this->assertFalse(
            $after->documentReady
        );

        $this->assertContains(
            'DOCUMENT_INVALID',
            $after->documentReasonCodes
        );
    }

    public function test_required_operational_boundary_expiry_fails_both_readiness_results_closed(): void
    {
        $context =
            $this->createOperationalContext(
                'FORECAST-END'
            );

        $this->createApprovedLogisticsChecklist(
            $context,
            'LOG-FORECAST-END'
        );

        $this->createApprovedDocumentChecklist(
            $context,
            'DOC-FORECAST-END'
        );

        /*
         * Equality pada required_end_at masih
         * berada dalam operational boundary.
         */
        $atBoundary =
            $this->evaluationService()
                ->evaluateContributor(
                    $context['forecast'],
                    $context['kdkmp']->id,
                    CarbonImmutable::parse(
                        '2026-08-25 17:00:00'
                    )
                );

        $this->assertTrue(
            $atBoundary->logisticsReady
        );

        $this->assertTrue(
            $atBoundary->documentReady
        );

        /*
         * Satu detik setelah required boundary:
         * readiness current operational truth
         * harus fail closed.
         */
        $afterBoundary =
            $this->evaluationService()
                ->evaluateContributor(
                    $context['forecast'],
                    $context['kdkmp']->id,
                    CarbonImmutable::parse(
                        '2026-08-25 17:00:01'
                    )
                );

        $this->assertFalse(
            $afterBoundary->logisticsReady
        );

        $this->assertFalse(
            $afterBoundary->documentReady
        );

        $this->assertFalse(
            $afterBoundary->allReady()
        );

        $this->assertContains(
            'FORECAST_WINDOW_ENDED',
            $afterBoundary->logisticsReasonCodes
        );

        $this->assertContains(
            'FORECAST_WINDOW_ENDED',
            $afterBoundary->documentReasonCodes
        );
    }

    private function evaluationService():
        ReadinessEvaluationService
    {
        return app(
            ReadinessEvaluationService::class
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

    private function revisionService():
        ReadinessChecklistRevisionService
    {
        return app(
            ReadinessChecklistRevisionService::class
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

                        /*
                         * Exact required_end boundary.
                         */
                        'expires_at' =>
                            '2026-08-25 17:00:00',

                        'notes' =>
                            'Readiness evaluation fixture.',
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
                    "kg-readiness-eval-{$suffix}",

                'name' =>
                    "Kilogram Readiness Eval {$suffix}",

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
                    "COM-READINESS-EVAL-{$suffix}",

                'name' =>
                    "Commodity Readiness Eval {$suffix}",

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
                "SPPG-READINESS-EVAL-{$suffix}"
            );

        $kdkmp =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-READINESS-EVAL-{$suffix}"
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
                    "FRC-READINESS-EVAL-{$suffix}",

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
                    'Readiness evaluation fixture.',

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
                    "PROD-READINESS-EVAL-{$suffix}",

                'name' =>
                    "Producer Eval {$suffix}",

                'village' =>
                    'Desa Test',

                'district' =>
                    'Kecamatan Test',

                'contact_phone' =>
                    '081234567890',

                'notes' =>
                    'Evaluation fixture producer.',

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