<?php

namespace Tests\Feature\Notification;

use App\Enums\CommitmentApprovalStatus;
use App\Enums\CommitmentLifecycleStatus;
use App\Enums\FallbackOfferStatus;
use App\Enums\ForecastStatus;
use App\Enums\NetworkRole;
use App\Enums\NotificationPriority;
use App\Enums\NotificationType;
use App\Enums\OrganizationType;
use App\Enums\ReadinessApprovalStatus;
use App\Enums\ReadinessType;
use App\Enums\RequirementScope;
use App\Enums\SupplyConfidence;
use App\Enums\UserRole;
use App\Models\Commodity;
use App\Models\CommitmentVersion;
use App\Models\DemandForecast;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Producer;
use App\Models\ReadinessRequirement;
use App\Models\SupplyCommitment;
use App\Models\SupplyNetworkLink;
use App\Models\Unit;
use App\Models\User;
use App\Services\Commitment\CommitmentWorkflowService;
use App\Services\Fallback\FallbackOfferService;
use App\Services\Fallback\FallbackRequestService;
use App\Services\Readiness\ReadinessChecklistPreparationService;
use App\Services\Readiness\ReadinessChecklistWorkflowService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationalNotificationIntegrationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse(
                '2026-08-11 11:00:00'
            )
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_commitment_submission_notifies_same_organization_manager_once(): void
    {
        $context =
            $this->createPrimaryContext(
                'COMMITMENT'
            );

        $producer =
            $this->createProducer(
                $context['primary'],
                $context['operator'],
                'PROD-NOTIF-COMMITMENT'
            );

        $service =
            app(
                CommitmentWorkflowService::class
            );

        $commitment =
            $service->createDraft(
                $context['operator'],
                [
                    'forecast_id' =>
                        $context['forecast']->id,

                    'producer_id' =>
                        $producer->id,

                    'expected_harvest_id' =>
                        null,

                    'min_volume' =>
                        '300.000000',

                    'max_volume' =>
                        '320.000000',

                    'unit_id' =>
                        $context['unit']->id,

                    'availability_start_at' =>
                        '2026-08-20 08:00:00',

                    'availability_end_at' =>
                        '2026-08-25 17:00:00',

                    'notes' =>
                        'Notification commitment fixture.',

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

        Notification::query()->delete();

        $submitted =
            $service->submit(
                $context['operator'],
                $version
            );

        $this->assertSame(
            CommitmentApprovalStatus
                ::PENDING_APPROVAL,
            $submitted->approval_status
        );

        $notification =
            Notification::query()
                ->where(
                    'recipient_user_id',
                    $context['manager']->id
                )
                ->firstOrFail();

        $this->assertSame(
            NotificationType
                ::APPROVAL_REQUIRED,
            $notification
                ->notification_type
        );

        $this->assertSame(
            NotificationPriority::ACTION,
            $notification->priority
        );

        $this->assertSame(
            $submitted->getMorphClass(),
            $notification
                ->related_entity_type
        );

        $this->assertSame(
            $submitted->id,
            $notification
                ->related_entity_id
        );

        $this->assertSame(
            '/kdkmp/manager/approvals/'
            .$commitment->id
            .'/versions/'
            .$submitted->id,
            $notification->action_url
        );

        $this->assertSame(
            'commitment-version:'
            .$submitted->id
            .':approval-required',
            $notification
                ->deduplication_key
        );

        /*
         * Retry submit tidak boleh membuat
         * notification kedua.
         */
        $retry =
            $service->submit(
                $context['operator'],
                $submitted
            );

        $this->assertSame(
            CommitmentApprovalStatus
                ::PENDING_APPROVAL,
            $retry->approval_status
        );

        $this->assertSame(
            1,
            Notification::query()
                ->where(
                    'recipient_user_id',
                    $context['manager']->id
                )
                ->where(
                    'deduplication_key',
                    'commitment-version:'
                    .$submitted->id
                    .':approval-required'
                )
                ->count()
        );
    }

    public function test_fallback_offer_available_notifies_requester_manager_not_supplier_manager(): void
    {
        $context =
            $this->createFallbackContext(
                'FALLBACK-AVAILABLE'
            );

        $requestService =
            app(
                FallbackRequestService::class
            );

        $request =
            $requestService->createDraft(
                $context['primaryOperator'],
                $context['forecast'],
                [
                    'requested_volume' =>
                        '150.000000',

                    'response_deadline_at' =>
                        '2026-08-19 18:00:00',

                    'broadcast_note' =>
                        'Notification fallback fixture.',
                ]
            );

        $request =
            $requestService->submit(
                $context['primaryOperator'],
                $request
            );

        $request =
            $requestService->approveBroadcast(
                $context['primaryManager'],
                $request
            );

        $source =
            $this->createApprovedFallbackSource(
                $context,
                $request,
                '160.000000'
            );

        $offerService =
            app(
                FallbackOfferService::class
            );

        $offer =
            $offerService->createDraft(
                $context['networkOperator'],
                $request,
                [
                    'offered_volume' =>
                        '150.000000',

                    'availability_note' =>
                        'Notification Offer fixture.',

                    'expires_at' =>
                        '2026-08-18 18:00:00',

                    'source_commitment_ids' => [
                        $source->id,
                    ],
                ]
            );

        $offer =
            $offerService->submit(
                $context['networkOperator'],
                $offer
            );

        /*
         * Source Commitment submit menghasilkan
         * notification approval sendiri.
         *
         * Bersihkan fixture noise sebelum event
         * AVAILABLE yang sedang diuji.
         */
        Notification::query()->delete();

        $available =
            $offerService
                ->approveForAvailability(
                    $context['networkManager'],
                    $offer
                );

        $this->assertSame(
            FallbackOfferStatus::AVAILABLE,
            $available->status
        );

        $requesterNotification =
            Notification::query()
                ->where(
                    'recipient_user_id',
                    $context[
                        'primaryManager'
                    ]->id
                )
                ->firstOrFail();

        $this->assertSame(
            NotificationType
                ::FALLBACK_OFFER_DECISION,
            $requesterNotification
                ->notification_type
        );

        $this->assertSame(
            NotificationPriority::ACTION,
            $requesterNotification
                ->priority
        );

        $this->assertSame(
            $available->getMorphClass(),
            $requesterNotification
                ->related_entity_type
        );

        $this->assertSame(
            $available->id,
            $requesterNotification
                ->related_entity_id
        );

        $this->assertSame(
            '/kdkmp/manager/incoming-offers/'
            .$available->id,
            $requesterNotification
                ->action_url
        );

        $this->assertSame(
            'fallback-offer:'
            .$available->id
            .':available',
            $requesterNotification
                ->deduplication_key
        );

        /*
         * Supplier Manager tidak boleh menerima
         * CTA requester.
         */
        $this->assertDatabaseMissing(
            'notifications',
            [
                'recipient_user_id' =>
                    $context[
                        'networkManager'
                    ]->id,

                'deduplication_key' =>
                    'fallback-offer:'
                    .$available->id
                    .':available',
            ]
        );

        /*
         * Idempotent approval retry.
         */
        $offerService
            ->approveForAvailability(
                $context['networkManager'],
                $available
            );

        $this->assertSame(
            1,
            Notification::query()
                ->where(
                    'deduplication_key',
                    'fallback-offer:'
                    .$available->id
                    .':available'
                )
                ->count()
        );
    }

    public function test_readiness_submission_notifies_only_same_organization_manager(): void
    {
        $context =
            $this->createPrimaryContext(
                'READINESS'
            );

        /*
         * Seed canonical current Safe Supply
         * langsung agar fixture tidak menghasilkan
         * Commitment notification tambahan.
         */
        $this->createApprovedDirectSupply(
            $context,
            '300.000000'
        );

        $this->createReadinessRequirement(
            $context,
            'LOG-NOTIF-READINESS'
        );

        $otherOrganization =
            $this->createOrganization(
                OrganizationType::KDKMP,
                'KDKMP-NOTIF-READINESS-OTHER'
            );

        $otherManager =
            $this->createKdkmpUser(
                $otherOrganization,
                UserRole::KDKMP_MANAGER
            );

        $preparationService =
            app(
                ReadinessChecklistPreparationService::class
            );

        $workflowService =
            app(
                ReadinessChecklistWorkflowService::class
            );

        $checklist =
            $preparationService
                ->createInitialDraft(
                    $context['operator'],
                    $context['forecast'],
                    ReadinessType::LOGISTICS
                );

        $item =
            $checklist
                ->items
                ->firstOrFail();

        $workflowService->updateItem(
            $context['operator'],
            $checklist,
            $item,
            [
                'is_satisfied' =>
                    true,

                'note' =>
                    'Logistics siap.',
            ]
        );

        Notification::query()->delete();

        $submitted =
            $workflowService->submit(
                $context['operator'],
                $checklist
            );

        $this->assertSame(
            ReadinessApprovalStatus
                ::PENDING_APPROVAL,
            $submitted->status
        );

        $notification =
            Notification::query()
                ->where(
                    'recipient_user_id',
                    $context['manager']->id
                )
                ->firstOrFail();

        $this->assertSame(
            NotificationType::READINESS,
            $notification
                ->notification_type
        );

        $this->assertSame(
            NotificationPriority::ACTION,
            $notification->priority
        );

        $this->assertSame(
            $submitted->getMorphClass(),
            $notification
                ->related_entity_type
        );

        $this->assertSame(
            $submitted->id,
            $notification
                ->related_entity_id
        );

        $this->assertSame(
            '/kdkmp/manager/readiness/'
            .$submitted->id,
            $notification->action_url
        );

        $this->assertDatabaseMissing(
            'notifications',
            [
                'recipient_user_id' =>
                    $otherManager->id,

                'deduplication_key' =>
                    'readiness-checklist:'
                    .$submitted->id
                    .':submitted',
            ]
        );

        /*
         * Retry submit tidak duplicate.
         */
        $workflowService->submit(
            $context['operator'],
            $submitted
        );

        $this->assertSame(
            1,
            Notification::query()
                ->where(
                    'deduplication_key',
                    'readiness-checklist:'
                    .$submitted->id
                    .':submitted'
                )
                ->count()
        );
    }

    public function test_outer_rollback_prevents_commitment_notification_and_business_transition(): void
    {
        $context =
            $this->createPrimaryContext(
                'ROLLBACK'
            );

        $producer =
            $this->createProducer(
                $context['primary'],
                $context['operator'],
                'PROD-NOTIF-ROLLBACK'
            );

        $service =
            app(
                CommitmentWorkflowService::class
            );

        $commitment =
            $service->createDraft(
                $context['operator'],
                [
                    'forecast_id' =>
                        $context['forecast']->id,

                    'producer_id' =>
                        $producer->id,

                    'expected_harvest_id' =>
                        null,

                    'min_volume' =>
                        '300.000000',

                    'max_volume' =>
                        '300.000000',

                    'unit_id' =>
                        $context['unit']->id,

                    'availability_start_at' =>
                        '2026-08-20 08:00:00',

                    'availability_end_at' =>
                        '2026-08-25 17:00:00',

                    'notes' =>
                        'Outer rollback fixture.',

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

        Notification::query()->delete();

        DB::beginTransaction();

        try {
            $service->submit(
                $context['operator'],
                $version
            );

            /*
             * afterCommit callback belum boleh
             * menjadi visible selama root
             * transaction masih terbuka.
             */
            $this->assertDatabaseMissing(
                'notifications',
                [
                    'deduplication_key' =>
                        'commitment-version:'
                        .$version->id
                        .':approval-required',
                ]
            );
        } finally {
            DB::rollBack();
        }

        /*
         * Business mutation dan notification
         * keduanya hilang.
         */
        $this->assertSame(
            CommitmentApprovalStatus::DRAFT,
            CommitmentVersion::query()
                ->findOrFail(
                    $version->id
                )
                ->approval_status
        );

        $this->assertDatabaseMissing(
            'notifications',
            [
                'deduplication_key' =>
                    'commitment-version:'
                    .$version->id
                    .':approval-required',
            ]
        );
    }

    private function createPrimaryContext(
        string $suffix,
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
                "SPPG-NOTIF-{$suffix}"
            );

        $primary =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-NOTIF-PRIMARY-{$suffix}"
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

        $forecast =
            DemandForecast::create([
                'sppg_organization_id' =>
                    $sppg->id,

                'commodity_id' =>
                    $commodity->id,

                'unit_id' =>
                    $unit->id,

                'forecast_code' =>
                    "FC-NOTIF-{$suffix}",

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
                    'Notification integration fixture.',

                'published_at' =>
                    '2026-08-11 09:00:00',

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

            'sppgUser' =>
                $sppgUser,

            'operator' =>
                $operator,

            'manager' =>
                $manager,

            'forecast' =>
                $forecast,
        ];
    }

    private function createFallbackContext(
        string $suffix,
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
                "SPPG-NOTIF-FB-{$suffix}"
            );

        $primary =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-NOTIF-FB-PRIMARY-{$suffix}"
            );

        $network =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-NOTIF-FB-NETWORK-{$suffix}"
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
                    "FC-NOTIF-FB-{$suffix}",

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
                    'Fallback notification fixture.',

                'published_at' =>
                    '2026-08-11 09:00:00',

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

    private function createApprovedFallbackSource(
        array $context,
        $request,
        string $minimum,
    ): SupplyCommitment {
        $producer =
            $this->createProducer(
                $context['network'],
                $context['networkOperator'],
                'PROD-NOTIF-FALLBACK-'
                .$request->id
            );

        $workflow =
            app(
                CommitmentWorkflowService::class
            );

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
                            'Fallback notification source.',

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

    private function createApprovedDirectSupply(
        array $context,
        string $minimum,
    ): SupplyCommitment {
        $producer =
            $this->createProducer(
                $context['primary'],
                $context['operator'],
                'PROD-NOTIF-DIRECT-'
                .$context['forecast']->id
            );

        $commitment =
            SupplyCommitment::create([
                'forecast_id' =>
                    $context['forecast']->id,

                'organization_id' =>
                    $context['primary']->id,

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
                    $context['operator']->id,
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
                    $minimum,

                'unit_id' =>
                    $context['unit']->id,

                'availability_start_at' =>
                    '2026-08-20 07:00:00',

                'availability_end_at' =>
                    '2026-08-25 18:00:00',

                'notes' =>
                    'Approved direct supply fixture.',

                'approval_status' =>
                    CommitmentApprovalStatus::APPROVED,

                'change_reason' =>
                    null,

                'operator_justification' =>
                    null,

                'created_by' =>
                    $context['operator']->id,

                'submitted_by' =>
                    $context['operator']->id,

                'submitted_at' =>
                    now(),

                'reviewed_by' =>
                    $context['manager']->id,

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

        return $commitment->refresh();
    }

    private function createReadinessRequirement(
        array $context,
        string $code,
    ): ReadinessRequirement {
        return ReadinessRequirement::create([
            'readiness_type' =>
                ReadinessType::LOGISTICS,

            'requirement_code' =>
                $code,

            'label' =>
                "Requirement {$code}",

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
    }

    private function createReferenceData(
        string $suffix,
    ): array {
        $unit =
            Unit::create([
                'code' =>
                    "KG-NOTIF-{$suffix}",

                'name' =>
                    "Kilogram Notification {$suffix}",

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
                    "COM-NOTIF-{$suffix}",

                'name' =>
                    "Commodity Notification {$suffix}",

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
                'Lokasi Notification Integration Test',
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
                'Notification integration producer.',

            'is_active' =>
                true,

            'created_by' =>
                $creator->id,
        ]);
    }
}