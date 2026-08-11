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
use App\Services\Readiness\ReadinessChecklistWorkflowService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReadinessChecklistReviewTest extends TestCase
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

    public function test_manager_can_approve_submitted_readiness_and_repeat_approval_is_idempotent(): void
    {
        $context =
            $this->createOperationalContext(
                'APPROVE'
            );

        $checklist =
            $this->createSubmittedChecklist(
                $context,
                ReadinessType::LOGISTICS,
                'LOG-REVIEW-APPROVE'
            );

        $approved =
            $this->reviewService()
                ->approve(
                    $context['manager'],
                    $checklist
                );

        $this->assertSame(
            ReadinessApprovalStatus::APPROVED,
            $approved->status
        );

        $this->assertSame(
            $context['manager']->id,
            $approved->reviewed_by
        );

        $this->assertNotNull(
            $approved->reviewed_at
        );

        $this->assertNotNull(
            $approved->approved_at
        );

        $this->assertNull(
            $approved->review_reason
        );

        /*
         * Manager review tidak boleh mengubah
         * frozen payload Operator.
         */
        $item =
            $approved
                ->items
                ->firstOrFail();

        $this->assertTrue(
            $item->is_satisfied
        );

        $this->assertSame(
            'Requirement confirmed.',
            $item->note
        );

        /*
         * Repeat approval harus idempotent.
         */
        $repeated =
            $this->reviewService()
                ->approve(
                    $context['manager'],
                    $checklist
                );

        $this->assertSame(
            $approved->id,
            $repeated->id
        );

        $this->assertSame(
            ReadinessApprovalStatus::APPROVED,
            $repeated->status
        );

        $auditCount =
            AuditLog::query()
                ->where(
                    'entity_type',
                    ReadinessChecklist::class
                )
                ->where(
                    'entity_id',
                    $checklist->id
                )
                ->where(
                    'action',
                    'READINESS_APPROVED'
                )
                ->count();

        $this->assertSame(
            1,
            $auditCount
        );
    }

    public function test_operator_cannot_approve_readiness(): void
    {
        $context =
            $this->createOperationalContext(
                'OPERATOR-APPROVE'
            );

        $checklist =
            $this->createSubmittedChecklist(
                $context,
                ReadinessType::LOGISTICS,
                'LOG-OPERATOR-APPROVE'
            );

        try {
            $this->reviewService()
                ->approve(
                    $context['operator'],
                    $checklist
                );

            $this->fail(
                'KDKMP Operator berhasil '
                .'meng-approve Readiness.'
            );
        } catch (
            AuthorizationException $exception
        ) {
            $this->assertTrue(true);
        }

        $this->assertSame(
            ReadinessApprovalStatus
                ::PENDING_APPROVAL,
            $checklist
                ->fresh()
                ->status
        );
    }

    public function test_manager_from_other_organization_cannot_approve_readiness(): void
    {
        $context =
            $this->createOperationalContext(
                'OTHER-MANAGER'
            );

        $checklist =
            $this->createSubmittedChecklist(
                $context,
                ReadinessType::LOGISTICS,
                'LOG-OTHER-MANAGER'
            );

        $otherKdkmp =
            $this->createOrganization(
                OrganizationType::KDKMP,
                'KDKMP-READINESS-OTHER-MANAGER-SECOND'
            );

        $otherManager =
            $this->createKdkmpUser(
                $otherKdkmp,
                UserRole::KDKMP_MANAGER
            );

        try {
            $this->reviewService()
                ->approve(
                    $otherManager,
                    $checklist
                );

            $this->fail(
                'Manager organisasi lain berhasil '
                .'meng-approve Readiness.'
            );
        } catch (
            AuthorizationException $exception
        ) {
            $this->assertTrue(true);
        }

        $this->assertSame(
            ReadinessApprovalStatus
                ::PENDING_APPROVAL,
            $checklist
                ->fresh()
                ->status
        );
    }

    public function test_maker_checker_blocks_same_user_after_role_change(): void
    {
        $context =
            $this->createOperationalContext(
                'MAKER-CHECKER'
            );

        $checklist =
            $this->createSubmittedChecklist(
                $context,
                ReadinessType::LOGISTICS,
                'LOG-MAKER-CHECKER'
            );

        /*
         * Simulasikan maker kemudian memperoleh
         * role Manager.
         *
         * Role saja tidak boleh cukup untuk
         * melewati maker-checker.
         */
        $context['operator']->update([
            'role' =>
                UserRole::KDKMP_MANAGER,
        ]);

        $sameActor =
            $context['operator']->fresh();

        try {
            $this->reviewService()
                ->approve(
                    $sameActor,
                    $checklist
                );

            $this->fail(
                'Maker berhasil meng-approve '
                .'Readiness miliknya sendiri.'
            );
        } catch (
            AuthorizationException $exception
        ) {
            $this->assertTrue(true);
        }

        $checklist->refresh();

        $this->assertSame(
            ReadinessApprovalStatus
                ::PENDING_APPROVAL,
            $checklist->status
        );

        $this->assertNull(
            $checklist->reviewed_by
        );

        $this->assertNull(
            $checklist->approved_at
        );
    }

    public function test_draft_checklist_cannot_be_approved_directly(): void
    {
        $context =
            $this->createOperationalContext(
                'DRAFT-APPROVAL'
            );

        $this->createRequirement(
            context: $context,
            type:
                ReadinessType::LOGISTICS,
            code:
                'LOG-DRAFT-APPROVAL'
        );

        $checklist =
            $this->preparationService()
                ->createInitialDraft(
                    $context['operator'],
                    $context['forecast'],
                    ReadinessType::LOGISTICS
                );

        try {
            $this->reviewService()
                ->approve(
                    $context['manager'],
                    $checklist
                );

            $this->fail(
                'DRAFT Readiness berhasil '
                .'di-approve tanpa submission.'
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
            ReadinessApprovalStatus::DRAFT,
            $checklist
                ->fresh()
                ->status
        );
    }

    public function test_reject_requires_reason(): void
    {
        $context =
            $this->createOperationalContext(
                'REJECT-REASON'
            );

        $checklist =
            $this->createSubmittedChecklist(
                $context,
                ReadinessType::LOGISTICS,
                'LOG-REJECT-REASON'
            );

        try {
            $this->reviewService()
                ->reject(
                    $context['manager'],
                    $checklist,
                    '   '
                );

            $this->fail(
                'Readiness berhasil ditolak '
                .'tanpa reason.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'review_reason',
                $exception->errors()
            );
        }

        $this->assertSame(
            ReadinessApprovalStatus
                ::PENDING_APPROVAL,
            $checklist
                ->fresh()
                ->status
        );
    }

    public function test_manager_can_reject_submitted_readiness_with_reason(): void
    {
        $context =
            $this->createOperationalContext(
                'REJECT'
            );

        $checklist =
            $this->createSubmittedChecklist(
                $context,
                ReadinessType::LOGISTICS,
                'LOG-REJECT'
            );

        $reason =
            'Jadwal pengiriman belum cukup jelas.';

        $rejected =
            $this->reviewService()
                ->reject(
                    $context['manager'],
                    $checklist,
                    $reason
                );

        $this->assertSame(
            ReadinessApprovalStatus::REJECTED,
            $rejected->status
        );

        $this->assertSame(
            $context['manager']->id,
            $rejected->reviewed_by
        );

        $this->assertNotNull(
            $rejected->reviewed_at
        );

        $this->assertSame(
            $reason,
            $rejected->review_reason
        );

        $this->assertNull(
            $rejected->approved_at
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'entity_type' =>
                    ReadinessChecklist::class,

                'entity_id' =>
                    $checklist->id,

                'actor_user_id' =>
                    $context['manager']->id,

                'action' =>
                    'READINESS_REJECTED',

                'reason_note' =>
                    $reason,
            ]
        );
    }

    public function test_forecast_revision_blocks_manager_approval_of_stale_checklist(): void
    {
        $context =
            $this->createOperationalContext(
                'STALE-FORECAST'
            );

        $checklist =
            $this->createSubmittedChecklist(
                $context,
                ReadinessType::LOGISTICS,
                'LOG-STALE-FORECAST-REVIEW'
            );

        /*
         * Published Forecast berubah setelah
         * Operator submit.
         */
        $context['forecast']->update([
            'target_volume' =>
                '250.000000',

            'version' =>
                $context['forecast']->version
                + 1,
        ]);

        try {
            $this->reviewService()
                ->approve(
                    $context['manager'],
                    $checklist
                );

            $this->fail(
                'Manager berhasil menyetujui '
                .'Readiness dengan stale '
                .'Forecast version.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'forecast_version',
                $exception->errors()
            );
        }

        $this->assertSame(
            ReadinessApprovalStatus
                ::PENDING_APPROVAL,
            $checklist
                ->fresh()
                ->status
        );
    }

    public function test_loss_of_effective_contributor_status_blocks_manager_approval(): void
    {
        $context =
            $this->createOperationalContext(
                'CONTRIBUTOR-LOSS'
            );

        $checklist =
            $this->createSubmittedChecklist(
                $context,
                ReadinessType::LOGISTICS,
                'LOG-CONTRIBUTOR-LOSS-REVIEW'
            );

        /*
         * PRIMARY sekarang hanya At-Risk.
         *
         * Ia tidak lagi termasuk canonical
         * ContributorSet.
         */
        $context['commitment']->update([
            'current_confidence' =>
                SupplyConfidence::YELLOW,
        ]);

        try {
            $this->reviewService()
                ->approve(
                    $context['manager'],
                    $checklist
                );

            $this->fail(
                'Manager berhasil menyetujui '
                .'Readiness untuk organization '
                .'yang bukan lagi contributor.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'contributor',
                $exception->errors()
            );
        }

        $this->assertSame(
            ReadinessApprovalStatus
                ::PENDING_APPROVAL,
            $checklist
                ->fresh()
                ->status
        );
    }

    public function test_document_changed_after_submit_blocks_manager_approval(): void
    {
        $context =
            $this->createOperationalContext(
                'DOCUMENT-CHANGED'
            );

        $requirement =
            $this->createRequirement(
                context: $context,
                type:
                    ReadinessType::DOCUMENT,
                code:
                    'DOC-CHANGED-AFTER-SUBMIT',
                scope:
                    RequirementScope::ORGANIZATION
            );

        $documentRecord =
            $this->documentService()
                ->create(
                    $context['operator'],
                    $requirement,
                    [
                        'document_name' =>
                            'Dokumen Operasional',

                        'reference_number' =>
                            'DOC-001',

                        'valid_from' =>
                            '2026-08-01 00:00:00',

                        'expires_at' =>
                            '2026-08-31 23:59:59',

                        'notes' =>
                            'Initial valid record.',
                    ]
                );

        $documentRecord =
            $this->documentService()
                ->markValid(
                    $context['operator'],
                    $documentRecord
                );

        $this->assertSame(
            2,
            $documentRecord->revision_no
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
                        $documentRecord->id,

                    'note' =>
                        'Dokumen tersedia.',
                ]
            );

        $checklist =
            $this->workflowService()
                ->submit(
                    $context['operator'],
                    $checklist
                );

        $item->refresh();

        $this->assertSame(
            2,
            $item->document_record_revision_no
        );

        /*
         * Document berubah setelah submission.
         *
         * revision 2 → 3 dan VALID → PENDING.
         */
        $documentRecord =
            $this->documentService()
                ->update(
                    $context['operator'],
                    $documentRecord,
                    [
                        'notes' =>
                            'Metadata changed after submit.',
                    ]
                );

        $this->assertSame(
            3,
            $documentRecord->revision_no
        );

        try {
            $this->reviewService()
                ->approve(
                    $context['manager'],
                    $checklist
                );

            $this->fail(
                'Manager berhasil menyetujui '
                .'Readiness dengan Document Record '
                .'yang berubah setelah submit.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'document_record_id',
                $exception->errors()
            );
        }

        $this->assertSame(
            ReadinessApprovalStatus
                ::PENDING_APPROVAL,
            $checklist
                ->fresh()
                ->status
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

    private function createSubmittedChecklist(
        array $context,
        ReadinessType $type,
        string $requirementCode,
    ): ReadinessChecklist {
        $this->createRequirement(
            context: $context,
            type: $type,
            code: $requirementCode
        );

        $checklist =
            $this->preparationService()
                ->createInitialDraft(
                    $context['operator'],
                    $context['forecast'],
                    $type
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
                        'Requirement confirmed.',
                ]
            );

        return $this->workflowService()
            ->submit(
                $context['operator'],
                $checklist
            );
    }

    private function createOperationalContext(
        string $suffix,
    ): array {
        $unit =
            Unit::create([
                'code' =>
                    "kg-readiness-review-{$suffix}",

                'name' =>
                    "Kilogram Readiness Review {$suffix}",

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
                    "COM-READINESS-REVIEW-{$suffix}",

                'name' =>
                    "Commodity Readiness Review {$suffix}",

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
                "SPPG-READINESS-REVIEW-{$suffix}"
            );

        $kdkmp =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-READINESS-REVIEW-{$suffix}"
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
                    "FRC-READINESS-REVIEW-{$suffix}",

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
                    'Readiness review fixture.',

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
                    "PROD-READINESS-REVIEW-{$suffix}",

                'name' =>
                    "Producer Review {$suffix}",

                'village' =>
                    'Desa Test',

                'district' =>
                    'Kecamatan Test',

                'contact_phone' =>
                    '081234567890',

                'notes' =>
                    'Readiness review fixture.',

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