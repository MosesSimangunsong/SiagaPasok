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
use App\Services\Readiness\ReadinessChecklistPreparationService;
use App\Services\Readiness\ReadinessChecklistWorkflowService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReadinessChecklistWorkflowTest extends TestCase
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

    public function test_initial_logistics_draft_snapshots_only_current_applicable_requirements(): void
    {
        $context =
            $this->createOperationalContext(
                'SNAPSHOT'
            );

        $globalRequirement =
            $this->createRequirement(
                context: $context,
                type:
                    ReadinessType::LOGISTICS,
                code:
                    'LOG-SNAPSHOT-GLOBAL',
                sortOrder:
                    10
            );

        $commodityRequirement =
            $this->createRequirement(
                context: $context,
                type:
                    ReadinessType::LOGISTICS,
                code:
                    'LOG-SNAPSHOT-COMMODITY',
                commodityId:
                    $context['commodity']->id,
                sortOrder:
                    20
            );

        $otherCommodity =
            Commodity::create([
                'code' =>
                    'OTHER-READINESS-SNAPSHOT',

                'name' =>
                    'Other Commodity Snapshot',

                'default_unit_id' =>
                    $context['unit']->id,

                'harvest_behavior' =>
                    null,

                'notes' =>
                    null,

                'is_active' =>
                    true,
            ]);

        $this->createRequirement(
            context: $context,
            type:
                ReadinessType::LOGISTICS,
            code:
                'LOG-SNAPSHOT-OTHER-COMMODITY',
            commodityId:
                $otherCommodity->id,
            sortOrder:
                30
        );

        $inactiveRequirement =
            $this->createRequirement(
                context: $context,
                type:
                    ReadinessType::LOGISTICS,
                code:
                    'LOG-SNAPSHOT-INACTIVE',
                sortOrder:
                    40
            );

        $inactiveRequirement->update([
            'is_active' =>
                false,
        ]);

        /*
         * Different readiness type tidak boleh
         * masuk Logistics Checklist.
         */
        $this->createRequirement(
            context: $context,
            type:
                ReadinessType::DOCUMENT,
            code:
                'DOC-SNAPSHOT',
            sortOrder:
                50
        );

        $checklist =
            $this->preparationService()
                ->createInitialDraft(
                    $context['operator'],
                    $context['forecast'],
                    ReadinessType::LOGISTICS
                );

        $this->assertSame(
            $context['forecast']->id,
            $checklist->forecast_id
        );

        $this->assertSame(
            $context['kdkmp']->id,
            $checklist->organization_id
        );

        $this->assertSame(
            ReadinessType::LOGISTICS,
            $checklist->readiness_type
        );

        $this->assertSame(
            $context['forecast']->version,
            $checklist->forecast_version
        );

        $this->assertSame(
            1,
            $checklist->version_no
        );

        $this->assertSame(
            ReadinessApprovalStatus::DRAFT,
            $checklist->status
        );

        $this->assertTrue(
            $checklist->is_current_version
        );

        $this->assertSame(
            $context['operator']->id,
            $checklist->prepared_by
        );

        $this->assertNull(
            $checklist->submitted_by
        );

        $this->assertCount(
            2,
            $checklist->items
        );

        $actualRequirementIds =
            $checklist
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
            $globalRequirement->id,
            $commodityRequirement->id,
        ];

        sort(
            $expectedRequirementIds,
            SORT_NUMERIC
        );

        $this->assertSame(
            $expectedRequirementIds,
            $actualRequirementIds
        );

        foreach (
            $checklist->items
            as $item
        ) {
            $this->assertTrue(
                $item->is_required
            );

            $this->assertFalse(
                $item->is_satisfied
            );

            $this->assertNull(
                $item->document_record_id
            );
        }

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'entity_id' =>
                    $checklist->id,

                'actor_user_id' =>
                    $context['operator']->id,

                'action' =>
                    'READINESS_CHECKLIST_CREATED',
            ]
        );
    }

    public function test_non_contributor_cannot_create_readiness_checklist(): void
    {
        $context =
            $this->createOperationalContext(
                'NON-CONTRIBUTOR'
            );

        $otherKdkmp =
    $this->createOrganization(
        OrganizationType::KDKMP,
        'KDKMP-READINESS-NON-CONTRIBUTOR-OTHER'
    );

        $otherOperator =
            $this->createKdkmpUser(
                $otherKdkmp,
                UserRole::KDKMP_OPERATOR
            );

        try {
            $this->preparationService()
                ->createInitialDraft(
                    $otherOperator,
                    $context['forecast'],
                    ReadinessType::LOGISTICS
                );

            $this->fail(
                'Non-contributor berhasil membuat '
                .'Readiness Checklist.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'contributor',
                $exception->errors()
            );
        }

        $this->assertDatabaseCount(
            'readiness_checklists',
            0
        );
    }

    public function test_manager_cannot_prepare_readiness_checklist(): void
    {
        $context =
            $this->createOperationalContext(
                'MANAGER-PREPARE'
            );

        try {
            $this->preparationService()
                ->createInitialDraft(
                    $context['manager'],
                    $context['forecast'],
                    ReadinessType::LOGISTICS
                );

            $this->fail(
                'KDKMP Manager berhasil membuat '
                .'Readiness Checklist sebagai maker.'
            );
        } catch (
            AuthorizationException $exception
        ) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseCount(
            'readiness_checklists',
            0
        );
    }

    public function test_non_published_forecast_cannot_receive_readiness_checklist(): void
    {
        $context =
            $this->createOperationalContext(
                'NON-PUBLISHED'
            );

        $context['forecast']->update([
            'status' =>
                ForecastStatus::DRAFT,

            'published_at' =>
                null,
        ]);

        try {
            $this->preparationService()
                ->createInitialDraft(
                    $context['operator'],
                    $context['forecast'],
                    ReadinessType::LOGISTICS
                );

            $this->fail(
                'Readiness berhasil dibuat untuk '
                .'Forecast non-PUBLISHED.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'forecast',
                $exception->errors()
            );
        }

        $this->assertDatabaseCount(
            'readiness_checklists',
            0
        );
    }

    public function test_duplicate_initial_checklist_for_same_tuple_is_rejected(): void
    {
        $context =
            $this->createOperationalContext(
                'DUPLICATE'
            );

        $this->createRequirement(
            context: $context,
            type:
                ReadinessType::LOGISTICS,
            code:
                'LOG-DUPLICATE'
        );

        $first =
            $this->preparationService()
                ->createInitialDraft(
                    $context['operator'],
                    $context['forecast'],
                    ReadinessType::LOGISTICS
                );

        $this->assertSame(
            1,
            $first->version_no
        );

        try {
            $this->preparationService()
                ->createInitialDraft(
                    $context['operator'],
                    $context['forecast'],
                    ReadinessType::LOGISTICS
                );

            $this->fail(
                'Duplicate initial Readiness Checklist '
                .'berhasil dibuat.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'readiness',
                $exception->errors()
            );
        }

        $this->assertDatabaseCount(
            'readiness_checklists',
            1
        );
    }

    public function test_operator_can_update_own_current_draft_item(): void
    {
        $context =
            $this->createOperationalContext(
                'UPDATE-DRAFT'
            );

        $this->createRequirement(
            context: $context,
            type:
                ReadinessType::LOGISTICS,
            code:
                'LOG-UPDATE-DRAFT'
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

        $updated =
            $this->workflowService()
                ->updateItem(
                    $context['operator'],
                    $checklist,
                    $item,
                    [
                        'is_satisfied' =>
                            true,

                        'note' =>
                            'Logistik telah dikonfirmasi.',

                        'value_json' => [
                            'confirmation' =>
                                'confirmed',
                        ],
                    ]
                );

        $this->assertTrue(
            $updated->is_satisfied
        );

        $this->assertSame(
            'Logistik telah dikonfirmasi.',
            $updated->note
        );

        $this->assertSame(
            [
                'confirmation' =>
                    'confirmed',
            ],
            $updated->value_json
        );

        $this->assertSame(
            $context['operator']->id,
            $updated->updated_by
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'entity_id' =>
                    $updated->id,

                'actor_user_id' =>
                    $context['operator']->id,

                'action' =>
                    'READINESS_ITEM_UPDATED',
            ]
        );
    }

    public function test_item_from_different_checklist_cannot_be_mutated_through_another_checklist(): void
    {
        $context =
            $this->createOperationalContext(
                'FOREIGN-ITEM'
            );

        $this->createRequirement(
            context: $context,
            type:
                ReadinessType::LOGISTICS,
            code:
                'LOG-FOREIGN-ITEM'
        );

        $this->createRequirement(
            context: $context,
            type:
                ReadinessType::DOCUMENT,
            code:
                'DOC-FOREIGN-ITEM'
        );

        $logistics =
            $this->preparationService()
                ->createInitialDraft(
                    $context['operator'],
                    $context['forecast'],
                    ReadinessType::LOGISTICS
                );

        $document =
            $this->preparationService()
                ->createInitialDraft(
                    $context['operator'],
                    $context['forecast'],
                    ReadinessType::DOCUMENT
                );

        $documentItem =
            $document
                ->items
                ->firstOrFail();

        try {
            $this->workflowService()
                ->updateItem(
                    $context['operator'],
                    $logistics,
                    $documentItem,
                    [
                        'is_satisfied' =>
                            true,
                    ]
                );

            $this->fail(
                'Item dari Checklist lain berhasil '
                .'dimutasi.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'item',
                $exception->errors()
            );
        }

        $this->assertFalse(
            $documentItem
                ->fresh()
                ->is_satisfied
        );
    }

    public function test_required_unsatisfied_item_blocks_submission(): void
    {
        $context =
            $this->createOperationalContext(
                'UNSATISFIED'
            );

        $this->createRequirement(
            context: $context,
            type:
                ReadinessType::LOGISTICS,
            code:
                'LOG-UNSATISFIED'
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

        try {
            $this->workflowService()
                ->submit(
                    $context['operator'],
                    $checklist
                );

            $this->fail(
                'Checklist dengan required item '
                .'belum satisfied berhasil disubmit.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'items.'.$item->id,
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

    public function test_satisfied_draft_can_be_submitted_and_repeat_submit_is_idempotent(): void
    {
        $context =
            $this->createOperationalContext(
                'SUBMIT'
            );

        $this->createRequirement(
            context: $context,
            type:
                ReadinessType::LOGISTICS,
            code:
                'LOG-SUBMIT'
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
                        'Requirement terpenuhi.',
                ]
            );

        $submitted =
            $this->workflowService()
                ->submit(
                    $context['operator'],
                    $checklist
                );

        $this->assertSame(
            ReadinessApprovalStatus
                ::PENDING_APPROVAL,
            $submitted->status
        );

        $this->assertSame(
            $context['operator']->id,
            $submitted->submitted_by
        );

        $this->assertNotNull(
            $submitted->submitted_at
        );

        $repeated =
            $this->workflowService()
                ->submit(
                    $context['operator'],
                    $checklist
                );

        $this->assertSame(
            $submitted->id,
            $repeated->id
        );

        $this->assertSame(
            ReadinessApprovalStatus
                ::PENDING_APPROVAL,
            $repeated->status
        );

        $submitAuditCount =
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
                    'READINESS_SUBMITTED'
                )
                ->count();

        $this->assertSame(
            1,
            $submitAuditCount
        );
    }

    public function test_submitted_checklist_payload_is_frozen_against_item_edit(): void
    {
        $context =
            $this->createOperationalContext(
                'FROZEN'
            );

        $this->createRequirement(
            context: $context,
            type:
                ReadinessType::LOGISTICS,
            code:
                'LOG-FROZEN'
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
                        'Frozen payload.',
                ]
            );

        $this->workflowService()
            ->submit(
                $context['operator'],
                $checklist
            );

        try {
            $this->workflowService()
                ->updateItem(
                    $context['operator'],
                    $checklist,
                    $item,
                    [
                        'is_satisfied' =>
                            false,

                        'note' =>
                            'Illegal mutation.',
                    ]
                );

            $this->fail(
                'PENDING_APPROVAL payload berhasil '
                .'diubah oleh Operator.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'status',
                $exception->errors()
            );
        }

        $item->refresh();

        $this->assertTrue(
            $item->is_satisfied
        );

        $this->assertSame(
            'Frozen payload.',
            $item->note
        );
    }

    public function test_forecast_revision_makes_existing_draft_stale_and_blocks_submit(): void
    {
        $context =
            $this->createOperationalContext(
                'STALE-FORECAST'
            );

        $this->createRequirement(
            context: $context,
            type:
                ReadinessType::LOGISTICS,
            code:
                'LOG-STALE-FORECAST'
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
                ]
            );

        $context['forecast']->update([
            'target_volume' =>
                '350.000000',

            'version' =>
                $context['forecast']->version
                + 1,
        ]);

        try {
            $this->workflowService()
                ->submit(
                    $context['operator'],
                    $checklist
                );

            $this->fail(
                'Checklist dengan stale Forecast '
                .'version berhasil disubmit.'
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
            ReadinessApprovalStatus::DRAFT,
            $checklist
                ->fresh()
                ->status
        );
    }

    public function test_organization_that_loses_effective_contributor_status_cannot_submit(): void
    {
        $context =
            $this->createOperationalContext(
                'CONTRIBUTOR-LOSS'
            );

        $this->createRequirement(
            context: $context,
            type:
                ReadinessType::LOGISTICS,
            code:
                'LOG-CONTRIBUTOR-LOSS'
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
                ]
            );

        /*
         * PRIMARY kehilangan effective Safe Supply.
         *
         * YELLOW hanya menjadi At-Risk dan tidak
         * lagi membentuk ContributorSet.
         */
        $context['commitment']->update([
            'current_confidence' =>
                SupplyConfidence::YELLOW,
        ]);

        try {
            $this->workflowService()
                ->submit(
                    $context['operator'],
                    $checklist
                );

            $this->fail(
                'Organization yang sudah kehilangan '
                .'effective contributor status '
                .'berhasil submit Readiness.'
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
            ReadinessApprovalStatus::DRAFT,
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

    private function createOperationalContext(
        string $suffix,
    ): array {
        $unit =
            Unit::create([
                'code' =>
                    "kg-readiness-{$suffix}",

                'name' =>
                    "Kilogram Readiness {$suffix}",

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
                    "COM-READINESS-{$suffix}",

                'name' =>
                    "Commodity Readiness {$suffix}",

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
                "SPPG-READINESS-{$suffix}"
            );

        $kdkmp =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-READINESS-{$suffix}"
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
                    "FRC-READINESS-{$suffix}",

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
                    'Readiness workflow fixture.',

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
                    "PROD-READINESS-{$suffix}",

                'name' =>
                    "Producer Readiness {$suffix}",

                'village' =>
                    'Desa Test',

                'district' =>
                    'Kecamatan Test',

                'contact_phone' =>
                    '081234567890',

                'notes' =>
                    'Readiness fixture producer.',

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

        $version =
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
                $version->id,
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
                $version,
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