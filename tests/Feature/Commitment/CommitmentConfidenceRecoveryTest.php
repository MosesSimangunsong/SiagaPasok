<?php

namespace Tests\Feature\Commitment;

use App\Enums\CommitmentApprovalStatus;
use App\Enums\NetworkRole;
use App\Enums\OrganizationType;
use App\Enums\RecoveryRequestStatus;
use App\Enums\SupplyConfidence;
use App\Enums\UserRole;
use App\Models\Commodity;
use App\Models\CommitmentConfidenceEvent;
use App\Models\ConfidenceRecoveryRequest;
use App\Models\DemandForecast;
use App\Models\ExpectedHarvest;
use App\Models\Organization;
use App\Models\Producer;
use App\Models\SupplyCommitment;
use App\Models\SupplyNetworkLink;
use App\Models\Unit;
use App\Models\User;
use App\Services\Commitment\CommitmentWorkflowService;
use App\Services\Commitment\ConfidenceService;
use App\Services\Forecast\DemandForecastService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CommitmentConfidenceRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_green_can_be_downgraded_to_yellow_immediately(): void
    {
        $context =
            $this->createApprovedContext(
                'GREEN-YELLOW'
            );

        $commitment =
            $context['commitment'];

        $this->assertSame(
            SupplyConfidence::GREEN,
            $commitment->current_confidence
        );

        app(
            ConfidenceService::class
        )->downgrade(
            actor:
                $context['operator'],

            commitment:
                $commitment,

            toConfidence:
                SupplyConfidence::YELLOW,

            reasonCode:
                'VOLUME_RISK',

            reasonNote:
                'Volume aktual diperkirakan menurun.'
        );

        $commitment->refresh();

        $this->assertSame(
            SupplyConfidence::YELLOW,
            $commitment->current_confidence
        );

        $event =
            CommitmentConfidenceEvent::query()
                ->where(
                    'commitment_id',
                    $commitment->id
                )
                ->latest('id')
                ->firstOrFail();

        $this->assertSame(
            SupplyConfidence::GREEN,
            $event->from_confidence
        );

        $this->assertSame(
            SupplyConfidence::YELLOW,
            $event->to_confidence
        );

        $this->assertSame(
            $context['operator']->id,
            $event->actor_user_id
        );

        $this->assertSame(
            'VOLUME_RISK',
            $event->reason_code
        );
    }

    public function test_green_can_be_downgraded_directly_to_red_and_red_is_terminal(): void
    {
        $context =
            $this->createApprovedContext(
                'GREEN-RED'
            );

        $confidence =
            app(
                ConfidenceService::class
            );

        $confidence->downgrade(
            actor:
                $context['operator'],

            commitment:
                $context['commitment'],

            toConfidence:
                SupplyConfidence::RED,

            reasonCode:
                'SUPPLY_FAILURE',

            reasonNote:
                'Produsen tidak dapat memenuhi commitment.'
        );

        $commitment =
            $context['commitment']
                ->fresh();

        $this->assertSame(
            SupplyConfidence::RED,
            $commitment->current_confidence
        );

        try {
            $confidence->requestRecovery(
                $context['operator'],
                $commitment,
                'Pasokan diklaim tersedia kembali.'
            );

            $this->fail(
                'Recovery berhasil dibuat untuk Commitment RED.'
            );
        } catch (
            ValidationException |
            AuthorizationException $exception
        ) {
            $this->assertTrue(true);
        }

        try {
            app(
                CommitmentWorkflowService::class
            )->createRevision(
                $context['operator'],
                $commitment,
                $this->revisionPayload(
                    $context
                )
            );

            $this->fail(
                'Revision berhasil dibuat untuk Commitment RED.'
            );
        } catch (
            ValidationException |
            AuthorizationException $exception
        ) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseCount(
            'confidence_recovery_requests',
            0
        );

        $this->assertSame(
            1,
            $commitment
                ->versions()
                ->count()
        );
    }

    public function test_yellow_can_be_downgraded_to_red(): void
    {
        $context =
            $this->createApprovedContext(
                'YELLOW-RED'
            );

        $confidence =
            app(
                ConfidenceService::class
            );

        $confidence->downgrade(
            actor:
                $context['operator'],

            commitment:
                $context['commitment'],

            toConfidence:
                SupplyConfidence::YELLOW,

            reasonCode:
                'WEATHER_RISK',

            reasonNote:
                'Cuaca meningkatkan risiko produksi.'
        );

        $confidence->downgrade(
            actor:
                $context['operator'],

            commitment:
                $context['commitment']->fresh(),

            toConfidence:
                SupplyConfidence::RED,

            reasonCode:
                'SUPPLY_FAILURE',

            reasonNote:
                'Risiko berkembang menjadi kegagalan pasokan.'
        );

        $this->assertSame(
            SupplyConfidence::RED,
            $context['commitment']
                ->fresh()
                ->current_confidence
        );

        $events =
            CommitmentConfidenceEvent::query()
                ->where(
                    'commitment_id',
                    $context['commitment']->id
                )
                ->orderBy('id')
                ->get();

        /*
         * Event 1 = initial approval:
         * NULL → GREEN.
         *
         * Event 2 = GREEN → YELLOW.
         *
         * Event 3 = YELLOW → RED.
         */
        $this->assertCount(
            3,
            $events
        );

        $this->assertSame(
            SupplyConfidence::YELLOW,
            $events[2]->from_confidence
        );

        $this->assertSame(
            SupplyConfidence::RED,
            $events[2]->to_confidence
        );
    }

    public function test_repeated_same_confidence_downgrade_is_idempotent(): void
    {
        $context =
            $this->createApprovedContext(
                'IDEMPOTENT'
            );

        $confidence =
            app(
                ConfidenceService::class
            );

        $confidence->downgrade(
            actor:
                $context['operator'],

            commitment:
                $context['commitment'],

            toConfidence:
                SupplyConfidence::YELLOW,

            reasonCode:
                'RISK',

            reasonNote:
                'Risiko pertama.'
        );

        $eventCountAfterFirst =
            CommitmentConfidenceEvent::query()
                ->where(
                    'commitment_id',
                    $context['commitment']->id
                )
                ->count();

        $confidence->downgrade(
            actor:
                $context['operator'],

            commitment:
                $context['commitment']->fresh(),

            toConfidence:
                SupplyConfidence::YELLOW,

            reasonCode:
                'RISK_REPEAT',

            reasonNote:
                'Pemanggilan ulang terhadap state yang sama.'
        );

        $this->assertSame(
            SupplyConfidence::YELLOW,
            $context['commitment']
                ->fresh()
                ->current_confidence
        );

        $this->assertSame(
            $eventCountAfterFirst,
            CommitmentConfidenceEvent::query()
                ->where(
                    'commitment_id',
                    $context['commitment']->id
                )
                ->count()
        );
    }

    public function test_recovery_can_only_be_requested_from_yellow_and_only_one_can_be_pending(): void
    {
        $context =
            $this->createApprovedContext(
                'RECOVERY-PENDING'
            );

        $confidence =
            app(
                ConfidenceService::class
            );

        /*
         * GREEN tidak dapat meminta Recovery.
         */
        try {
            $confidence->requestRecovery(
                $context['operator'],
                $context['commitment'],
                'Recovery tidak diperlukan saat GREEN.'
            );

            $this->fail(
                'Recovery berhasil dibuat ketika Commitment masih GREEN.'
            );
        } catch (
            ValidationException |
            AuthorizationException $exception
        ) {
            $this->assertTrue(true);
        }

        $confidence->downgrade(
            actor:
                $context['operator'],

            commitment:
                $context['commitment']->fresh(),

            toConfidence:
                SupplyConfidence::YELLOW,

            reasonCode:
                'VOLUME_RISK',

            reasonNote:
                'Volume aktual sempat menurun.'
        );

        $recovery =
            $confidence->requestRecovery(
                $context['operator'],
                $context['commitment']->fresh(),
                'Volume telah dikonfirmasi kembali oleh Operator.'
            );

        $this->assertSame(
            RecoveryRequestStatus
                ::PENDING_APPROVAL,
            $recovery->status
        );

        $this->assertSame(
            $context['commitment']
                ->fresh()
                ->active_version_id,
            $recovery
                ->commitment_version_id
        );

        $this->assertSame(
            $context['operator']->id,
            $recovery->requested_by
        );

        try {
            $confidence->requestRecovery(
                $context['operator'],
                $context['commitment']->fresh(),
                'Recovery kedua seharusnya tidak diperbolehkan.'
            );

            $this->fail(
                'Lebih dari satu pending Recovery berhasil dibuat.'
            );
        } catch (
            ValidationException |
            AuthorizationException $exception
        ) {
            $this->assertTrue(true);
        }

        $this->assertSame(
            1,
            ConfidenceRecoveryRequest::query()
                ->where(
                    'commitment_id',
                    $context['commitment']->id
                )
                ->where(
                    'status',
                    RecoveryRequestStatus
                        ::PENDING_APPROVAL
                        ->value
                )
                ->count()
        );
    }

    public function test_manager_can_approve_recovery_and_green_verification_time_is_refreshed(): void
    {
        Carbon::setTestNow(
            '2026-08-10 08:00:00'
        );

        try {
            $context =
                $this->createApprovedContext(
                    'RECOVERY-APPROVE'
                );

            $commitment =
                $context['commitment']
                    ->fresh();

            $originalVerifiedAt =
                $commitment
                    ->last_confidence_verified_at
                    ->copy();

            $confidence =
                app(
                    ConfidenceService::class
                );

            $confidence->downgrade(
                actor:
                    $context['operator'],

                commitment:
                    $commitment,

                toConfidence:
                    SupplyConfidence::YELLOW,

                reasonCode:
                    'LOGISTICS_RISK',

                reasonNote:
                    'Transportasi sempat tidak tersedia.'
            );

            $recovery =
                $confidence->requestRecovery(
                    $context['operator'],
                    $commitment->fresh(),
                    'Transportasi pengganti telah dikonfirmasi.'
                );

            Carbon::setTestNow(
                '2026-08-10 12:00:00'
            );

            $this->actingAs(
                $context['manager']
            )
                ->post(
                    "/kdkmp/manager/recoveries/{$recovery->id}/approve",
                    [
                        'review_reason' =>
                            'Evidence operasional memadai.',
                    ]
                )
                ->assertRedirect(
                    route(
                        'kdkmp.manager.recoveries.index'
                    )
                );

            $commitment->refresh();
            $recovery->refresh();

            $this->assertSame(
                RecoveryRequestStatus
                    ::APPROVED,
                $recovery->status
            );

            $this->assertSame(
                SupplyConfidence::GREEN,
                $commitment->current_confidence
            );

            $this->assertSame(
                $context['manager']->id,
                $recovery->reviewed_by
            );

            $this->assertSame(
                'Evidence operasional memadai.',
                $recovery->review_reason
            );

            $this->assertTrue(
                $commitment
                    ->last_confidence_verified_at
                    ->greaterThan(
                        $originalVerifiedAt
                    )
            );

            $this->assertSame(
                '2026-08-10 12:00:00',
                $commitment
                    ->last_confidence_verified_at
                    ->format(
                        'Y-m-d H:i:s'
                    )
            );

            $event =
                CommitmentConfidenceEvent::query()
                    ->where(
                        'commitment_id',
                        $commitment->id
                    )
                    ->latest('id')
                    ->firstOrFail();

            $this->assertSame(
                SupplyConfidence::YELLOW,
                $event->from_confidence
            );

            $this->assertSame(
                SupplyConfidence::GREEN,
                $event->to_confidence
            );

            $this->assertSame(
                $context['manager']->id,
                $event->actor_user_id
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_rejected_recovery_keeps_commitment_yellow(): void
    {
        $context =
            $this->createApprovedContext(
                'RECOVERY-REJECT'
            );

        $confidence =
            app(
                ConfidenceService::class
            );

        $confidence->downgrade(
            actor:
                $context['operator'],

            commitment:
                $context['commitment'],

            toConfidence:
                SupplyConfidence::YELLOW,

            reasonCode:
                'VOLUME_RISK',

            reasonNote:
                'Volume belum stabil.'
        );

        $recovery =
            $confidence->requestRecovery(
                $context['operator'],
                $context['commitment']->fresh(),
                'Operator menyatakan volume sudah membaik.'
            );

        $this->actingAs(
            $context['manager']
        )
            ->post(
                "/kdkmp/manager/recoveries/{$recovery->id}/reject",
                [
                    'review_reason' =>
                        'Bukti belum cukup untuk mengembalikan confidence.',
                ]
            )
            ->assertRedirect(
                route(
                    'kdkmp.manager.recoveries.index'
                )
            );

        $recovery->refresh();

        $this->assertSame(
            RecoveryRequestStatus
                ::REJECTED,
            $recovery->status
        );

        $this->assertSame(
            SupplyConfidence::YELLOW,
            $context['commitment']
                ->fresh()
                ->current_confidence
        );

        $this->assertSame(
            'Bukti belum cukup untuk mengembalikan confidence.',
            $recovery->review_reason
        );
    }

    public function test_recovery_is_blocked_while_revision_draft_is_open(): void
    {
        $context =
            $this->createApprovedContext(
                'RECOVERY-REVISION'
            );

        $confidence =
            app(
                ConfidenceService::class
            );

        $workflow =
            app(
                CommitmentWorkflowService::class
            );

        $confidence->downgrade(
            actor:
                $context['operator'],

            commitment:
                $context['commitment'],

            toConfidence:
                SupplyConfidence::YELLOW,

            reasonCode:
                'VOLUME_RISK',

            reasonNote:
                'Range commitment perlu dikoreksi.'
        );

        $revision =
            $workflow->createRevision(
                $context['operator'],
                $context['commitment']->fresh(),
                $this->revisionPayload(
                    $context
                )
            );

        $this->assertSame(
            CommitmentApprovalStatus::DRAFT,
            $revision->approval_status
        );

        try {
            $confidence->requestRecovery(
                $context['operator'],
                $context['commitment']->fresh(),
                'Tidak boleh recovery ketika revision masih terbuka.'
            );

            $this->fail(
                'Recovery berhasil dibuat ketika Revision DRAFT masih terbuka.'
            );
        } catch (
            ValidationException |
            AuthorizationException $exception
        ) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseCount(
            'confidence_recovery_requests',
            0
        );
    }

    public function test_recovery_maker_cannot_approve_own_request_even_if_role_changes(): void
    {
        $context =
            $this->createApprovedContext(
                'RECOVERY-MAKER'
            );

        $confidence =
            app(
                ConfidenceService::class
            );

        $confidence->downgrade(
            actor:
                $context['operator'],

            commitment:
                $context['commitment'],

            toConfidence:
                SupplyConfidence::YELLOW,

            reasonCode:
                'RISK',

            reasonNote:
                'Kondisi membutuhkan recovery.'
        );

        $recovery =
            $confidence->requestRecovery(
                $context['operator'],
                $context['commitment']->fresh(),
                'Kondisi dinyatakan membaik.'
            );

        /*
         * Adversarial case:
         * requester yang sama kemudian memperoleh
         * role Manager.
         */
        $context['operator']->update([
            'role' =>
                UserRole::KDKMP_MANAGER,
        ]);

        $sameActor =
            $context['operator']
                ->fresh();

        try {
            $confidence->approveRecovery(
                $sameActor,
                $recovery->fresh(),
                'Self approval tidak boleh.'
            );

            $this->fail(
                'Requester berhasil menyetujui Recovery miliknya sendiri.'
            );
        } catch (
            ValidationException |
            AuthorizationException $exception
        ) {
            $this->assertTrue(true);
        }

        $this->assertSame(
            RecoveryRequestStatus
                ::PENDING_APPROVAL,
            $recovery
                ->fresh()
                ->status
        );

        $this->assertSame(
            SupplyConfidence::YELLOW,
            $context['commitment']
                ->fresh()
                ->current_confidence
        );
    }

    public function test_recovery_is_private_to_own_kdkmp_organization(): void
    {
        $context =
            $this->createApprovedContext(
                'RECOVERY-PRIVATE'
            );

        $confidence =
            app(
                ConfidenceService::class
            );

        $confidence->downgrade(
            actor:
                $context['operator'],

            commitment:
                $context['commitment'],

            toConfidence:
                SupplyConfidence::YELLOW,

            reasonCode:
                'RISK',

            reasonNote:
                'Kondisi supply berubah.'
        );

        $recovery =
            $confidence->requestRecovery(
                $context['operator'],
                $context['commitment']->fresh(),
                'Evidence pemulihan.'
            );

        $otherKdkmp =
            $this->createOrganization(
                OrganizationType::KDKMP,
                'KDKMP-RECOVERY-OTHER'
            );

        $otherManager =
            $this->createKdkmpUser(
                $otherKdkmp,
                UserRole::KDKMP_MANAGER
            );

        $this->actingAs(
            $otherManager
        )
            ->get(
                "/kdkmp/manager/recoveries/{$recovery->id}"
            )
            ->assertForbidden();

        $this->actingAs(
            $otherManager
        )
            ->post(
                "/kdkmp/manager/recoveries/{$recovery->id}/approve",
                [
                    'review_reason' =>
                        'Tidak boleh lintas organisasi.',
                ]
            )
            ->assertForbidden();

        $this->assertSame(
            RecoveryRequestStatus
                ::PENDING_APPROVAL,
            $recovery
                ->fresh()
                ->status
        );

        $this->assertSame(
            SupplyConfidence::YELLOW,
            $context['commitment']
                ->fresh()
                ->current_confidence
        );
    }

    public function test_operator_cannot_access_manager_recovery_decision_routes(): void
    {
        $context =
            $this->createApprovedContext(
                'RECOVERY-ROLE'
            );

        $confidence =
            app(
                ConfidenceService::class
            );

        $confidence->downgrade(
            actor:
                $context['operator'],

            commitment:
                $context['commitment'],

            toConfidence:
                SupplyConfidence::YELLOW,

            reasonCode:
                'RISK',

            reasonNote:
                'Recovery test.'
        );

        $recovery =
            $confidence->requestRecovery(
                $context['operator'],
                $context['commitment']->fresh(),
                'Operator mengajukan recovery.'
            );

        $this->actingAs(
            $context['operator']
        )
            ->post(
                "/kdkmp/manager/recoveries/{$recovery->id}/approve",
                [
                    'review_reason' =>
                        'Operator tidak boleh approve.',
                ]
            )
            ->assertForbidden();

        $this->actingAs(
            $context['operator']
        )
            ->post(
                "/kdkmp/manager/recoveries/{$recovery->id}/reject",
                [
                    'review_reason' =>
                        'Operator tidak boleh reject.',
                ]
            )
            ->assertForbidden();

        $this->assertSame(
            RecoveryRequestStatus
                ::PENDING_APPROVAL,
            $recovery
                ->fresh()
                ->status
        );
    }

    private function createApprovedContext(
        string $suffix
    ): array {
        $context =
            $this->createOperationalContext(
                $suffix
            );

        $workflow =
            app(
                CommitmentWorkflowService::class
            );

        $commitment =
            $workflow->createDraft(
                $context['operator'],
                $this->commitmentPayload(
                    $context
                )
            );

        $version =
            $commitment
                ->versions()
                ->firstOrFail();

        $workflow->submit(
            $context['operator'],
            $version
        );

        $workflow->approve(
            $context['manager'],
            $version
        );

        $commitment->refresh();
        $version->refresh();

        $this->assertSame(
            CommitmentApprovalStatus::APPROVED,
            $version->approval_status
        );

        $this->assertSame(
            SupplyConfidence::GREEN,
            $commitment->current_confidence
        );

        return [
            ...$context,

            'commitment' =>
                $commitment,

            'version' =>
                $version,
        ];
    }

    private function createOperationalContext(
        string $suffix
    ): array {
        [$unit, $commodity] =
            $this->createReferenceData(
                $suffix
            );

        $admin =
            User::factory()->create();

        $sppg =
            $this->createOrganization(
                OrganizationType::SPPG,
                "SPPG-CONFIDENCE-{$suffix}"
            );

        $kdkmp =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-CONFIDENCE-{$suffix}"
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
                        200,

                    'required_start_at' =>
                        '2026-08-20 08:00:00',

                    'required_end_at' =>
                        '2026-08-20 12:00:00',

                    'freshness_interval_hours' =>
                        24,

                    'notes' =>
                        'Confidence test forecast',
                ]
            );

        $forecast =
            $forecastService->publish(
                $sppgUser,
                $forecast,
                $forecast->version
            );

        $producer =
            Producer::create([
                'organization_id' =>
                    $kdkmp->id,

                'producer_code' =>
                    "PROD-CONFIDENCE-{$suffix}",

                'name' =>
                    "Produsen {$suffix}",

                'village' =>
                    'Desa Test',

                'district' =>
                    'Kecamatan Test',

                'contact_phone' =>
                    '081234567890',

                'notes' =>
                    'Producer confidence fixture',

                'is_active' =>
                    true,

                'created_by' =>
                    $operator->id,
            ]);

        $expectedHarvest =
            ExpectedHarvest::create([
                'organization_id' =>
                    $kdkmp->id,

                'producer_id' =>
                    $producer->id,

                'commodity_id' =>
                    $commodity->id,

                'unit_id' =>
                    $unit->id,

                'expected_min_volume' =>
                    90,

                'expected_max_volume' =>
                    150,

                'harvest_start_at' =>
                    '2026-08-20 06:00:00',

                'harvest_end_at' =>
                    '2026-08-20 14:00:00',

                'notes' =>
                    'Expected Harvest confidence fixture',

                'last_updated_by' =>
                    $operator->id,
            ]);

        return [
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

            'unit' =>
                $unit,

            'commodity' =>
                $commodity,

            'forecast' =>
                $forecast,

            'producer' =>
                $producer,

            'expectedHarvest' =>
                $expectedHarvest,
        ];
    }

    private function commitmentPayload(
        array $context
    ): array {
        return [
            'forecast_id' =>
                $context['forecast']->id,

            'producer_id' =>
                $context['producer']->id,

            'expected_harvest_id' =>
                $context[
                    'expectedHarvest'
                ]->id,

            'min_volume' =>
                80,

            'max_volume' =>
                120,

            'unit_id' =>
                $context['unit']->id,

            'availability_start_at' =>
                '2026-08-20 07:00:00',

            'availability_end_at' =>
                '2026-08-20 13:00:00',

            'notes' =>
                'Confidence test commitment',

            'operator_justification' =>
                null,
        ];
    }

    private function revisionPayload(
        array $context
    ): array {
        return [
            'min_volume' =>
                70,

            'max_volume' =>
                110,

            'unit_id' =>
                $context['unit']->id,

            'availability_start_at' =>
                '2026-08-20 08:00:00',

            'availability_end_at' =>
                '2026-08-20 12:00:00',

            'notes' =>
                'Revision during confidence test',

            'change_reason' =>
                'Range perlu diperbarui karena kondisi pasokan berubah.',

            'operator_justification' =>
                null,
        ];
    }

    private function createReferenceData(
        string $suffix
    ): array {
        $unit =
            Unit::create([
                'code' =>
                    "kg-confidence-{$suffix}",

                'name' =>
                    "Kilogram {$suffix}",

                'symbol' =>
                    'kg',

                'decimal_precision' =>
                    2,

                'is_active' =>
                    true,
            ]);

        $commodity =
            Commodity::create([
                'code' =>
                    "BAYAM-CONFIDENCE-{$suffix}",

                'name' =>
                    "Bayam {$suffix}",

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