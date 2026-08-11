<?php

namespace Tests\Feature\Notification;

use App\Enums\AuditSource;
use App\Enums\CommitmentApprovalStatus;
use App\Enums\CommitmentLifecycleStatus;
use App\Enums\DocumentStatus;
use App\Enums\ForecastStatus;
use App\Enums\NetworkRole;
use App\Enums\NotificationPriority;
use App\Enums\NotificationType;
use App\Enums\OrganizationType;
use App\Enums\ReadinessType;
use App\Enums\RequirementScope;
use App\Enums\SupplyConfidence;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Commodity;
use App\Models\CommitmentVersion;
use App\Models\DemandForecast;
use App\Models\ForecastDerivedStateObservation;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Producer;
use App\Models\ReadinessChecklist;
use App\Models\ReadinessRequirement;
use App\Models\SupplyCommitment;
use App\Models\SupplyNetworkLink;
use App\Models\Unit;
use App\Models\User;
use App\Services\Notification\DerivedForecastStateObservationService;
use App\Services\Readiness\DocumentRecordService;
use App\Services\Readiness\ReadinessChecklistPreparationService;
use App\Services\Readiness\ReadinessChecklistReviewService;
use App\Services\Readiness\ReadinessChecklistWorkflowService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DerivedForecastStateObservationTest extends TestCase
{
    use DatabaseMigrations;

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

    public function test_first_positive_shortfall_establishes_baseline_without_notification(): void
    {
        $context =
            $this->createOperationalContext(
                'BASELINE-SHORTFALL'
            );

        /*
         * Initial state dapat mempunyai supply 0.
         *
         * Ini baseline orchestration, bukan
         * "shortfall membesar" transition.
         */
        $context['commitment']->update([
            'current_confidence' =>
                SupplyConfidence::YELLOW,
        ]);

        Notification::query()->delete();

        $observation =
            $this->observer()->observe(
                $context['forecast']
            );

        $this->assertSame(
            '300.000000',
            (string) $observation->shortfall
        );

        $this->assertFalse(
            $observation
                ->ready_for_procurement
        );

        $this->assertDatabaseCount(
            'forecast_derived_state_observations',
            1
        );

        $this->assertSame(
            0,
            Notification::query()
                ->where(
                    'notification_type',
                    NotificationType::SHORTFALL
                        ->value
                )
                ->count()
        );
    }

    public function test_shortfall_growth_notifies_primary_operator_and_manager(): void
    {
        $context =
            $this->createOperationalContext(
                'SHORTFALL-GROWTH'
            );

        /*
         * 300 safe / 400 demand
         * = baseline shortfall 100.
         */
        $context['forecast']->update([
            'target_volume' =>
                '400.000000',
        ]);

        $first =
            $this->observer()->observe(
                $context['forecast']
            );

        $this->assertSame(
            '100.000000',
            (string) $first->shortfall
        );

        Notification::query()->delete();

        /*
         * Demand naik lagi:
         * 300 safe / 500 demand
         * = shortfall 200.
         */
        $context['forecast']->update([
            'target_volume' =>
                '500.000000',

            'version' =>
                2,
        ]);

        $second =
            $this->observer()->observe(
                $context['forecast']
            );

        $this->assertSame(
            '200.000000',
            (string) $second->shortfall
        );

        $dedupeKey =
            'derived-observation:'
            .$second->id
            .':shortfall';

        foreach (
            [
                $context['operator'],
                $context['manager'],
            ]
            as $recipient
        ) {
            $notification =
                Notification::query()
                    ->where(
                        'recipient_user_id',
                        $recipient->id
                    )
                    ->where(
                        'deduplication_key',
                        $dedupeKey
                    )
                    ->firstOrFail();

            $this->assertSame(
                NotificationType::SHORTFALL,
                $notification
                    ->notification_type
            );

            $this->assertSame(
                NotificationPriority::WARNING,
                $notification->priority
            );

            $this->assertSame(
                '/kdkmp/forecasts/'
                .$context['forecast']->id,
                $notification->action_url
            );
        }

        /*
         * SPPG tidak menerima KDKMP shortfall
         * action notification.
         */
        $this->assertDatabaseMissing(
            'notifications',
            [
                'recipient_user_id' =>
                    $context['sppgUser']->id,

                'deduplication_key' =>
                    $dedupeKey,
            ]
        );
    }

    public function test_first_ready_observation_records_and_notifies_rfp_reached(): void
    {
        $context =
            $this->createReadyContext(
                'RFP-REACHED'
            );

        Notification::query()->delete();

        $observation =
            $this->observer()->observe(
                $context['forecast']
            );

        $this->assertTrue(
            $observation
                ->ready_for_procurement
        );

        $this->assertSame(
            [],
            $observation->reason_codes
        );

        $audit =
            AuditLog::query()
                ->where(
                    'entity_type',
                    $context['forecast']
                        ->getMorphClass()
                )
                ->where(
                    'entity_id',
                    $context['forecast']->id
                )
                ->where(
                    'action',
                    'READY_FOR_PROCUREMENT_REACHED'
                )
                ->firstOrFail();

        $this->assertSame(
            AuditSource::SYSTEM,
            $audit->source
        );

        $this->assertNull(
            $audit->actor_user_id
        );

        $this->assertFalse(
            $audit
                ->previous_value_json[
                    'ready_for_procurement'
                ]
        );

        $this->assertTrue(
            $audit
                ->new_value_json[
                    'ready_for_procurement'
                ]
        );

        $dedupeKey =
            'derived-observation:'
            .$observation->id
            .':rfp-reached';

        foreach (
            [
                $context['sppgUser'],
                $context['manager'],
            ]
            as $recipient
        ) {
            $notification =
                Notification::query()
                    ->where(
                        'recipient_user_id',
                        $recipient->id
                    )
                    ->where(
                        'deduplication_key',
                        $dedupeKey
                    )
                    ->firstOrFail();

            $this->assertSame(
                NotificationType::RFP,
                $notification
                    ->notification_type
            );

            $this->assertSame(
                NotificationPriority
                    ::INFORMATION,
                $notification->priority
            );
        }

        $this->assertDatabaseMissing(
            'notifications',
            [
                'recipient_user_id' =>
                    $context['operator']->id,

                'deduplication_key' =>
                    $dedupeKey,
            ]
        );
    }

    public function test_rfp_loss_notifies_sppg_and_previous_contributor_manager(): void
    {
        $context =
            $this->createReadyContext(
                'RFP-LOST'
            );

        $ready =
            $this->observer()->observe(
                $context['forecast']
            );

        $this->assertTrue(
            $ready
                ->ready_for_procurement
        );

        Notification::query()->delete();

        /*
         * Contributor hilang dari current set:
         * GREEN -> YELLOW.
         *
         * Direct DB mutation disengaja di test
         * observer agar Supply Risk notification
         * dari ConfidenceService tidak menjadi
         * noise terhadap assertion M10 observer.
         */
        $context['commitment']->update([
            'current_confidence' =>
                SupplyConfidence::YELLOW,
        ]);

        $lost =
            $this->observer()->observe(
                $context['forecast']
            );

        $this->assertFalse(
            $lost
                ->ready_for_procurement
        );

        $this->assertSame(
            [],
            $lost
                ->contributor_organization_ids
        );

        $this->assertContains(
            'VOLUME_NOT_READY',
            $lost->reason_codes
        );

        $audit =
            AuditLog::query()
                ->where(
                    'entity_type',
                    $context['forecast']
                        ->getMorphClass()
                )
                ->where(
                    'entity_id',
                    $context['forecast']->id
                )
                ->where(
                    'action',
                    'READY_FOR_PROCUREMENT_LOST'
                )
                ->firstOrFail();

        $this->assertSame(
            AuditSource::SYSTEM,
            $audit->source
        );

        $this->assertTrue(
            $audit
                ->previous_value_json[
                    'ready_for_procurement'
                ]
        );

        $this->assertFalse(
            $audit
                ->new_value_json[
                    'ready_for_procurement'
                ]
        );

        $dedupeKey =
            'derived-observation:'
            .$lost->id
            .':rfp-lost';

        /*
         * Current contributor set kosong.
         * Manager tetap menerima notification
         * karena recipient menggunakan union
         * previous + current contributors.
         */
        $managerNotification =
            Notification::query()
                ->where(
                    'recipient_user_id',
                    $context['manager']->id
                )
                ->where(
                    'deduplication_key',
                    $dedupeKey
                )
                ->firstOrFail();

        $this->assertSame(
            NotificationType::RFP,
            $managerNotification
                ->notification_type
        );

        $sppgNotification =
            Notification::query()
                ->where(
                    'recipient_user_id',
                    $context['sppgUser']->id
                )
                ->where(
                    'deduplication_key',
                    $dedupeKey
                )
                ->firstOrFail();

        $this->assertSame(
            NotificationType::RFP,
            $sppgNotification
                ->notification_type
        );
    }

    public function test_unchanged_derived_state_does_not_create_second_observation_or_events(): void
    {
        $context =
            $this->createReadyContext(
                'UNCHANGED'
            );

        $first =
            $this->observer()->observe(
                $context['forecast']
            );

        $observationCount =
            ForecastDerivedStateObservation
                ::query()
                ->count();

        $auditCount =
            AuditLog::query()
                ->whereIn(
                    'action',
                    [
                        'READY_FOR_PROCUREMENT_REACHED',
                        'READY_FOR_PROCUREMENT_LOST',
                    ]
                )
                ->count();

        $notificationCount =
            Notification::query()
                ->where(
                    'notification_type',
                    NotificationType::RFP
                        ->value
                )
                ->count();

        $second =
            $this->observer()->observe(
                $context['forecast'],
                CarbonImmutable::parse(
                    '2026-08-10 10:05:00'
                )
            );

        $this->assertSame(
            $first->id,
            $second->id
        );

        $this->assertSame(
            $observationCount,
            ForecastDerivedStateObservation
                ::query()
                ->count()
        );

        $this->assertSame(
            $auditCount,
            AuditLog::query()
                ->whereIn(
                    'action',
                    [
                        'READY_FOR_PROCUREMENT_REACHED',
                        'READY_FOR_PROCUREMENT_LOST',
                    ]
                )
                ->count()
        );

        $this->assertSame(
            $notificationCount,
            Notification::query()
                ->where(
                    'notification_type',
                    NotificationType::RFP
                        ->value
                )
                ->count()
        );
    }

    public function test_scheduled_observer_detects_rfp_loss_after_required_boundary(): void
    {
        $context =
            $this->createReadyContext(
                'TIME-BOUNDARY'
            );

        /*
         * Equality masih operationally valid.
         */
        $ready =
            $this->observer()->observe(
                $context['forecast'],
                CarbonImmutable::parse(
                    '2026-08-25 17:00:00'
                )
            );

        $this->assertTrue(
            $ready
                ->ready_for_procurement
        );

        Notification::query()->delete();

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse(
                '2026-08-25 17:00:01'
            )
        );

        $exitCode =
            Artisan::call(
                'forecasts:observe-derived-state'
            );

        $this->assertSame(
            Command::SUCCESS,
            $exitCode
        );

        $latest =
            ForecastDerivedStateObservation
                ::query()
                ->where(
                    'forecast_id',
                    $context['forecast']->id
                )
                ->latest('id')
                ->firstOrFail();

        $this->assertFalse(
            $latest
                ->ready_for_procurement
        );

        $this->assertContains(
            'FORECAST_WINDOW_ENDED',
            $latest->reason_codes
        );

        $this->assertSame(
            2,
            ForecastDerivedStateObservation
                ::query()
                ->where(
                    'forecast_id',
                    $context['forecast']->id
                )
                ->count()
        );

        $this->assertSame(
            1,
            AuditLog::query()
                ->where(
                    'entity_id',
                    $context['forecast']->id
                )
                ->where(
                    'action',
                    'READY_FOR_PROCUREMENT_LOST'
                )
                ->count()
        );
    }

    private function observer():
        DerivedForecastStateObservationService
    {
        return app(
            DerivedForecastStateObservationService::class
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

    private function createReadyContext(
        string $suffix,
    ): array {
        $context =
            $this->createOperationalContext(
                $suffix
            );

        $this->createApprovedLogisticsChecklist(
            $context,
            "LOG-OBS-{$suffix}"
        );

        $this->createApprovedDocumentChecklist(
            $context,
            "DOC-OBS-{$suffix}"
        );

        return $context;
    }

    private function createApprovedLogisticsChecklist(
        array $context,
        string $requirementCode,
    ): ReadinessChecklist {
        $this->createRequirement(
            context:
                $context,

            type:
                ReadinessType::LOGISTICS,

            code:
                $requirementCode,
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
                        'Logistics observation fixture.',
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
                context:
                    $context,

                type:
                    ReadinessType::DOCUMENT,

                code:
                    $requirementCode,

                scope:
                    RequirementScope::ORGANIZATION,
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
                            'Derived observation fixture.',
                    ]
                );

        $document =
            $this->documentService()
                ->markValid(
                    $context['operator'],
                    $document
                );

        $this->assertSame(
            DocumentStatus::VALID,
            $document->status
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
                        'Document observation fixture.',
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
                    "kg-derived-{$suffix}",

                'name' =>
                    "Kilogram Derived {$suffix}",

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
                    "COM-DERIVED-{$suffix}",

                'name' =>
                    "Commodity Derived {$suffix}",

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
                "SPPG-DERIVED-{$suffix}"
            );

        $kdkmp =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-DERIVED-{$suffix}"
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
                    "FRC-DERIVED-{$suffix}",

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
                    'Derived observation fixture.',

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
                    "PROD-DERIVED-{$suffix}",

                'name' =>
                    "Producer Derived {$suffix}",

                'village' =>
                    'Desa Test',

                'district' =>
                    'Kecamatan Test',

                'contact_phone' =>
                    '081234567890',

                'notes' =>
                    'Derived observation fixture.',

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
                    CommitmentLifecycleStatus
                        ::ACTIVE,

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
                    CommitmentApprovalStatus
                        ::APPROVED,

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
                'Lokasi Derived Observation Test',
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