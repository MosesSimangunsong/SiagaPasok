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
use App\Models\AuditLog;
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
use App\Services\Readiness\ReadinessChecklistPreparationService;
use App\Services\Readiness\ReadinessChecklistReviewService;
use App\Services\Readiness\ReadinessChecklistRevisionService;
use App\Services\Readiness\ReadinessChecklistWorkflowService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReadinessChecklistRevisionTest extends TestCase
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

    public function test_approved_checklist_revision_creates_new_current_draft_and_preserves_history(): void
    {
        $context =
            $this->createOperationalContext(
                'APPROVED-REVISION'
            );

        $versionOne =
            $this->createApprovedLogisticsChecklist(
                $context,
                'LOG-APPROVED-REVISION'
            );

        $this->assertSame(
            ReadinessApprovalStatus::APPROVED,
            $versionOne->status
        );

        $this->assertTrue(
            $versionOne->is_current_version
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
            1,
            $versionOne->version_no
        );

        $this->assertSame(
            ReadinessApprovalStatus::DRAFT,
            $versionTwo->status
        );

        $this->assertTrue(
            $versionTwo->is_current_version
        );

        $this->assertSame(
            2,
            $versionTwo->version_no
        );

        $this->assertSame(
            $versionOne->id,
            $versionTwo->supersedes_checklist_id
        );

        $this->assertSame(
            $context['forecast']->version,
            $versionTwo->forecast_version
        );

        $this->assertSame(
            $context['operator']->id,
            $versionTwo->prepared_by
        );

        $this->assertNull(
            $versionTwo->submitted_by
        );

        $this->assertNull(
            $versionTwo->submitted_at
        );

        $this->assertNull(
            $versionTwo->reviewed_by
        );

        $this->assertNull(
            $versionTwo->reviewed_at
        );

        $this->assertNull(
            $versionTwo->approved_at
        );

        $this->assertSame(
            2,
            ReadinessChecklist::query()
                ->where(
                    'forecast_id',
                    $context['forecast']->id
                )
                ->where(
                    'organization_id',
                    $context['kdkmp']->id
                )
                ->where(
                    'readiness_type',
                    ReadinessType::LOGISTICS->value
                )
                ->count()
        );

        $this->assertSame(
            1,
            ReadinessChecklist::query()
                ->where(
                    'forecast_id',
                    $context['forecast']->id
                )
                ->where(
                    'organization_id',
                    $context['kdkmp']->id
                )
                ->where(
                    'readiness_type',
                    ReadinessType::LOGISTICS->value
                )
                ->where(
                    'is_current_version',
                    true
                )
                ->count()
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'entity_type' =>
                    ReadinessChecklist::class,

                'entity_id' =>
                    $versionTwo->id,

                'actor_user_id' =>
                    $context['operator']->id,

                'action' =>
                    'READINESS_REVISION_CREATED',
            ]
        );
    }

    public function test_rejected_checklist_can_continue_as_new_draft_revision(): void
    {
        $context =
            $this->createOperationalContext(
                'REJECTED-REVISION'
            );

        $versionOne =
            $this->createRejectedLogisticsChecklist(
                $context,
                'LOG-REJECTED-REVISION'
            );

        $this->assertSame(
            ReadinessApprovalStatus::REJECTED,
            $versionOne->status
        );

        $versionTwo =
            $this->revisionService()
                ->createRevision(
                    $context['operator'],
                    $versionOne
                );

        $versionOne->refresh();

        $this->assertSame(
            ReadinessApprovalStatus::REJECTED,
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

        $this->assertSame(
            2,
            $versionTwo->version_no
        );

        $this->assertSame(
            $versionOne->id,
            $versionTwo->supersedes_checklist_id
        );
    }

    public function test_pending_approval_checklist_cannot_be_revised(): void
    {
        $context =
            $this->createOperationalContext(
                'PENDING-REVISION'
            );

        $versionOne =
            $this->createSubmittedLogisticsChecklist(
                $context,
                'LOG-PENDING-REVISION'
            );

        try {
            $this->revisionService()
                ->createRevision(
                    $context['operator'],
                    $versionOne
                );

            $this->fail(
                'PENDING_APPROVAL Readiness berhasil '
                .'direvisi sebelum Manager mengambil '
                .'keputusan.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'status',
                $exception->errors()
            );
        }

        $versionOne->refresh();

        $this->assertSame(
            ReadinessApprovalStatus
                ::PENDING_APPROVAL,
            $versionOne->status
        );

        $this->assertTrue(
            $versionOne->is_current_version
        );

        $this->assertDatabaseCount(
            'readiness_checklists',
            1
        );
    }

    public function test_current_draft_matching_current_forecast_does_not_create_unnecessary_revision(): void
    {
        $context =
            $this->createOperationalContext(
                'FRESH-DRAFT'
            );

        $this->createRequirement(
            context: $context,
            type:
                ReadinessType::LOGISTICS,
            code:
                'LOG-FRESH-DRAFT'
        );

        $versionOne =
            $this->preparationService()
                ->createInitialDraft(
                    $context['operator'],
                    $context['forecast'],
                    ReadinessType::LOGISTICS
                );

        try {
            $this->revisionService()
                ->createRevision(
                    $context['operator'],
                    $versionOne
                );

            $this->fail(
                'Fresh DRAFT berhasil menghasilkan '
                .'revision yang tidak diperlukan.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'status',
                $exception->errors()
            );
        }

        $this->assertSame(
            1,
            ReadinessChecklist::query()
                ->count()
        );

        $this->assertTrue(
            $versionOne
                ->fresh()
                ->is_current_version
        );
    }

    public function test_stale_draft_after_forecast_revision_can_be_rebased_as_new_revision(): void
    {
        $context =
            $this->createOperationalContext(
                'STALE-DRAFT'
            );

        $this->createRequirement(
            context: $context,
            type:
                ReadinessType::LOGISTICS,
            code:
                'LOG-STALE-DRAFT'
        );

        $versionOne =
            $this->preparationService()
                ->createInitialDraft(
                    $context['operator'],
                    $context['forecast'],
                    ReadinessType::LOGISTICS
                );

        $this->assertSame(
            1,
            $versionOne->forecast_version
        );

        /*
         * Simulasi valid Published Forecast
         * revision.
         */
        $context['forecast']->update([
            'target_volume' =>
                '250.000000',

            'version' =>
                2,
        ]);

        $versionTwo =
            $this->revisionService()
                ->createRevision(
                    $context['operator'],
                    $versionOne
                );

        $versionOne->refresh();

        $this->assertFalse(
            $versionOne->is_current_version
        );

        $this->assertSame(
            1,
            $versionOne->forecast_version
        );

        $this->assertTrue(
            $versionTwo->is_current_version
        );

        $this->assertSame(
            2,
            $versionTwo->version_no
        );

        $this->assertSame(
            2,
            $versionTwo->forecast_version
        );

        $this->assertSame(
            ReadinessApprovalStatus::DRAFT,
            $versionTwo->status
        );
    }

    public function test_revision_resolves_current_requirement_set_and_does_not_mutate_history(): void
    {
        $context =
            $this->createOperationalContext(
                'REQUIREMENT-RESOLVE'
            );

        $keptRequirement =
            $this->createRequirement(
                context: $context,
                type:
                    ReadinessType::LOGISTICS,
                code:
                    'LOG-REVISION-KEEP',
                required:
                    true,
                sortOrder:
                    10
            );

        $removedRequirement =
            $this->createRequirement(
                context: $context,
                type:
                    ReadinessType::LOGISTICS,
                code:
                    'LOG-REVISION-REMOVE',
                required:
                    true,
                sortOrder:
                    20
            );

        $versionOne =
            $this->preparationService()
                ->createInitialDraft(
                    $context['operator'],
                    $context['forecast'],
                    ReadinessType::LOGISTICS
                );

        $keptItemV1 =
            $versionOne
                ->items
                ->firstWhere(
                    'requirement_id',
                    $keptRequirement->id
                );

        $removedItemV1 =
            $versionOne
                ->items
                ->firstWhere(
                    'requirement_id',
                    $removedRequirement->id
                );

        $this->assertNotNull(
            $keptItemV1
        );

        $this->assertNotNull(
            $removedItemV1
        );

        $this->workflowService()
            ->updateItem(
                $context['operator'],
                $versionOne,
                $keptItemV1,
                [
                    'is_satisfied' =>
                        true,

                    'note' =>
                        'Previous answer retained.',
                ]
            );

        $this->workflowService()
            ->updateItem(
                $context['operator'],
                $versionOne,
                $removedItemV1,
                [
                    'is_satisfied' =>
                        true,

                    'note' =>
                        'Historical removed requirement.',
                ]
            );

        $versionOne =
            $this->workflowService()
                ->submit(
                    $context['operator'],
                    $versionOne
                );

        $versionOne =
            $this->reviewService()
                ->approve(
                    $context['manager'],
                    $versionOne
                );

        /*
         * Master requirement berubah SETELAH
         * V1 approved.
         *
         * Historical V1 tidak boleh berubah.
         */
        $keptRequirement->update([
            'is_required_default' =>
                false,
        ]);

        $removedRequirement->update([
            'is_active' =>
                false,
        ]);

        $newRequirement =
            $this->createRequirement(
                context: $context,
                type:
                    ReadinessType::LOGISTICS,
                code:
                    'LOG-REVISION-NEW',
                required:
                    true,
                sortOrder:
                    30
            );

        $versionTwo =
            $this->revisionService()
                ->createRevision(
                    $context['operator'],
                    $versionOne
                );

        $versionTwo->load(
            'items'
        );

        $actualRequirementIds =
            $versionTwo
                ->items
                ->pluck(
                    'requirement_id'
                )
                ->map(
                    fn ($id): int =>
                        (int) $id
                )
                ->sort()
                ->values()
                ->all();

        $expectedRequirementIds = [
            $keptRequirement->id,
            $newRequirement->id,
        ];

        sort(
            $expectedRequirementIds,
            SORT_NUMERIC
        );

        $this->assertSame(
            $expectedRequirementIds,
            $actualRequirementIds
        );

        $this->assertFalse(
            $versionTwo
                ->items
                ->contains(
                    'requirement_id',
                    $removedRequirement->id
                )
        );

        $keptItemV2 =
            $versionTwo
                ->items
                ->firstWhere(
                    'requirement_id',
                    $keptRequirement->id
                );

        $newItemV2 =
            $versionTwo
                ->items
                ->firstWhere(
                    'requirement_id',
                    $newRequirement->id
                );

        /*
         * Current master menentukan required flag
         * V2.
         */
        $this->assertFalse(
            $keptItemV2->is_required
        );

        /*
         * Matching requirement boleh memakai
         * payload lama sebagai starting point.
         */
        $this->assertTrue(
            $keptItemV2->is_satisfied
        );

        $this->assertSame(
            'Previous answer retained.',
            $keptItemV2->note
        );

        /*
         * Requirement baru dimulai unsatisfied.
         */
        $this->assertTrue(
            $newItemV2->is_required
        );

        $this->assertFalse(
            $newItemV2->is_satisfied
        );

        /*
         * Historical snapshot V1 tetap utuh.
         */
        $keptItemV1->refresh();
        $removedItemV1->refresh();

        $this->assertTrue(
            $keptItemV1->is_required
        );

        $this->assertTrue(
            $removedItemV1->is_required
        );

        $this->assertTrue(
            $removedItemV1->is_satisfied
        );

        $this->assertSame(
            ReadinessApprovalStatus::APPROVED,
            $versionOne
                ->fresh()
                ->status
        );
    }

    public function test_document_revision_link_can_be_inherited_but_frozen_revision_snapshot_is_reset(): void
    {
        $context =
            $this->createOperationalContext(
                'DOCUMENT-SNAPSHOT'
            );

        $requirement =
            $this->createRequirement(
                context: $context,
                type:
                    ReadinessType::DOCUMENT,
                code:
                    'DOC-REVISION-SNAPSHOT',
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
                            'Dokumen Legal',

                        'reference_number' =>
                            'LEGAL-001',

                        'valid_from' =>
                            '2026-08-01 00:00:00',

                        'expires_at' =>
                            '2026-08-31 23:59:59',

                        'notes' =>
                            'Document revision fixture.',
                    ]
                );

        $document =
            $this->documentService()
                ->markValid(
                    $context['operator'],
                    $document
                );

        $this->assertSame(
            2,
            $document->revision_no
        );

        $versionOne =
            $this->preparationService()
                ->createInitialDraft(
                    $context['operator'],
                    $context['forecast'],
                    ReadinessType::DOCUMENT
                );

        $itemV1 =
            $versionOne
                ->items
                ->firstOrFail();

        $this->workflowService()
            ->updateItem(
                $context['operator'],
                $versionOne,
                $itemV1,
                [
                    'is_satisfied' =>
                        true,

                    'document_record_id' =>
                        $document->id,

                    'note' =>
                        'Document linked.',
                ]
            );

        $versionOne =
            $this->workflowService()
                ->submit(
                    $context['operator'],
                    $versionOne
                );

        $itemV1->refresh();

        $this->assertSame(
            $document->id,
            $itemV1->document_record_id
        );

        $this->assertSame(
            2,
            $itemV1->document_record_revision_no
        );

        $versionOne =
            $this->reviewService()
                ->approve(
                    $context['manager'],
                    $versionOne
                );

        $versionTwo =
            $this->revisionService()
                ->createRevision(
                    $context['operator'],
                    $versionOne
                );

        $itemV2 =
            $versionTwo
                ->items
                ->firstOrFail();

        /*
         * Link document boleh menjadi starting
         * point Operator.
         */
        $this->assertSame(
            $document->id,
            $itemV2->document_record_id
        );

        /*
         * Tetapi evidence revision lama TIDAK
         * boleh diwariskan.
         *
         * V2 harus mengambil snapshot revision
         * terbaru ketika disubmit.
         */
        $this->assertNull(
            $itemV2->document_record_revision_no
        );

        $this->assertSame(
            ReadinessApprovalStatus::DRAFT,
            $versionTwo->status
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

    private function createSubmittedLogisticsChecklist(
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
                        'Revision fixture satisfied.',
                ]
            );

        return $this->workflowService()
            ->submit(
                $context['operator'],
                $checklist
            );
    }

    private function createApprovedLogisticsChecklist(
        array $context,
        string $requirementCode,
    ): ReadinessChecklist {
        $checklist =
            $this->createSubmittedLogisticsChecklist(
                $context,
                $requirementCode
            );

        return $this->reviewService()
            ->approve(
                $context['manager'],
                $checklist
            );
    }

    private function createRejectedLogisticsChecklist(
        array $context,
        string $requirementCode,
    ): ReadinessChecklist {
        $checklist =
            $this->createSubmittedLogisticsChecklist(
                $context,
                $requirementCode
            );

        return $this->reviewService()
            ->reject(
                $context['manager'],
                $checklist,
                'Revision diperlukan setelah penolakan.'
            );
    }

    private function createOperationalContext(
        string $suffix,
    ): array {
        $unit =
            Unit::create([
                'code' =>
                    "kg-readiness-revision-{$suffix}",

                'name' =>
                    "Kilogram Readiness Revision {$suffix}",

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
                    "COM-READINESS-REVISION-{$suffix}",

                'name' =>
                    "Commodity Readiness Revision {$suffix}",

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
                "SPPG-READINESS-REVISION-{$suffix}"
            );

        $kdkmp =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-READINESS-REVISION-{$suffix}"
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
                    "FRC-READINESS-REVISION-{$suffix}",

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
                    'Readiness revision fixture.',

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
                    "PROD-READINESS-REVISION-{$suffix}",

                'name' =>
                    "Producer Revision {$suffix}",

                'village' =>
                    'Desa Test',

                'district' =>
                    'Kecamatan Test',

                'contact_phone' =>
                    '081234567890',

                'notes' =>
                    'Revision fixture producer.',

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
        ?int $commodityId = null,
        bool $required = true,
        int $sortOrder = 10,
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
                $commodityId,

            'is_required_default' =>
                $required,

            'is_active' =>
                true,

            'sort_order' =>
                $sortOrder,

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