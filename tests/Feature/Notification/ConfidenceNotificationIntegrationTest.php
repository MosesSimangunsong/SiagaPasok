<?php

namespace Tests\Feature\Notification;

use App\Enums\CommitmentApprovalStatus;
use App\Enums\NetworkRole;
use App\Enums\NotificationPriority;
use App\Enums\NotificationType;
use App\Enums\OrganizationType;
use App\Enums\RecoveryRequestStatus;
use App\Enums\SupplyConfidence;
use App\Enums\UserRole;
use App\Models\Commodity;
use App\Models\CommitmentConfidenceEvent;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Producer;
use App\Models\SupplyNetworkLink;
use App\Models\Unit;
use App\Models\User;
use App\Services\Commitment\CommitmentWorkflowService;
use App\Services\Commitment\ConfidenceService;
use App\Services\Forecast\DemandForecastService;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConfidenceNotificationIntegrationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            '2026-08-10 12:00:00'
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_manual_confidence_downgrade_notifies_operator_and_manager_once(): void
    {
        $context =
            $this->createApprovedContext(
                'RISK'
            );

        /*
         * createApprovedContext melakukan
         * Commitment submit sehingga approval
         * notification fixture sudah pernah dibuat.
         *
         * Hapus noise tersebut sebelum menguji
         * Supply Risk event.
         */
        Notification::query()->delete();

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
                'Volume aktual diperkirakan menurun.'
        );

        $commitment =
            $context['commitment']
                ->fresh();

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
                ->where(
                    'reason_code',
                    'VOLUME_RISK'
                )
                ->latest('id')
                ->firstOrFail();

        $dedupeKey =
            'confidence-event:'
            .$event->id
            .':supply-risk';

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
                NotificationType
                    ::SUPPLY_RISK,
                $notification
                    ->notification_type
            );

            $this->assertSame(
                NotificationPriority
                    ::WARNING,
                $notification->priority
            );

            $this->assertSame(
                $commitment
                    ->getMorphClass(),
                $notification
                    ->related_entity_type
            );

            $this->assertSame(
                $commitment->id,
                $notification
                    ->related_entity_id
            );

            $this->assertSame(
                '/kdkmp/commitments/'
                .$commitment->id,
                $notification
                    ->action_url
            );
        }

        /*
         * SPPG bukan recipient internal
         * Commitment risk notification.
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

        /*
         * Retry command dengan target state sama
         * return idempotent sebelum event baru.
         */
        $confidence->downgrade(
            actor:
                $context['operator'],

            commitment:
                $commitment,

            toConfidence:
                SupplyConfidence::YELLOW,

            reasonCode:
                'VOLUME_RISK_REPEAT',

            reasonNote:
                'Retry terhadap state yang sama.'
        );

        $this->assertSame(
            2,
            Notification::query()
                ->where(
                    'deduplication_key',
                    $dedupeKey
                )
                ->count()
        );

        $this->assertSame(
            1,
            CommitmentConfidenceEvent::query()
                ->where(
                    'commitment_id',
                    $commitment->id
                )
                ->where(
                    'reason_code',
                    'VOLUME_RISK'
                )
                ->count()
        );

        $this->assertSame(
            0,
            CommitmentConfidenceEvent::query()
                ->where(
                    'commitment_id',
                    $commitment->id
                )
                ->where(
                    'reason_code',
                    'VOLUME_RISK_REPEAT'
                )
                ->count()
        );
    }

    public function test_stale_command_notifies_operator_only_and_is_idempotent(): void
    {
        $context =
            $this->createApprovedContext(
                'STALE',
                1
            );

        $commitment =
            $context['commitment'];

        $commitment
            ->last_confidence_verified_at =
            now()->subHours(2);

        $commitment->save();

        Notification::query()->delete();

        $exitCode =
            Artisan::call(
                'commitments:evaluate-stale-confidence'
            );

        $this->assertSame(
            Command::SUCCESS,
            $exitCode
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
                ->where(
                    'reason_code',
                    'STALE_DATA'
                )
                ->firstOrFail();

        $dedupeKey =
            'confidence-event:'
            .$event->id
            .':stale';

        $notification =
            Notification::query()
                ->where(
                    'recipient_user_id',
                    $context['operator']->id
                )
                ->where(
                    'deduplication_key',
                    $dedupeKey
                )
                ->firstOrFail();

        $this->assertSame(
            NotificationType
                ::STALE_COMMITMENT,
            $notification
                ->notification_type
        );

        $this->assertSame(
            NotificationPriority::WARNING,
            $notification->priority
        );

        $this->assertSame(
            '/kdkmp/commitments/'
            .$commitment->id,
            $notification->action_url
        );

        /*
         * Foundation stale notification diarahkan
         * ke Operator untuk verifikasi supply.
         */
        $this->assertDatabaseMissing(
            'notifications',
            [
                'recipient_user_id' =>
                    $context['manager']->id,

                'deduplication_key' =>
                    $dedupeKey,
            ]
        );

        /*
         * Command kedua tidak lagi memilih
         * Commitment karena state sudah YELLOW.
         */
        $secondExitCode =
            Artisan::call(
                'commitments:evaluate-stale-confidence'
            );

        $this->assertSame(
            Command::SUCCESS,
            $secondExitCode
        );

        $this->assertSame(
            1,
            Notification::query()
                ->where(
                    'deduplication_key',
                    $dedupeKey
                )
                ->count()
        );

        $this->assertSame(
            1,
            CommitmentConfidenceEvent::query()
                ->where(
                    'commitment_id',
                    $commitment->id
                )
                ->where(
                    'reason_code',
                    'STALE_DATA'
                )
                ->count()
        );
    }

    public function test_recovery_request_notifies_same_organization_manager_only_once(): void
    {
        $context =
            $this->createApprovedContext(
                'RECOVERY'
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
                'LOGISTICS_RISK',

            reasonNote:
                'Transportasi belum stabil.'
        );

        /*
         * Isolasi event Recovery Request dari
         * Supply Risk notification sebelumnya.
         */
        Notification::query()->delete();

        $recovery =
            $confidence->requestRecovery(
                $context['operator'],
                $context['commitment']
                    ->fresh(),
                'Transportasi pengganti telah dikonfirmasi.'
            );

        $this->assertSame(
            RecoveryRequestStatus
                ::PENDING_APPROVAL,
            $recovery->status
        );

        $dedupeKey =
            'confidence-recovery:'
            .$recovery->id
            .':approval-required';

        $notification =
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
            $recovery->getMorphClass(),
            $notification
                ->related_entity_type
        );

        $this->assertSame(
            $recovery->id,
            $notification
                ->related_entity_id
        );

        $this->assertSame(
            '/kdkmp/manager/recoveries/'
            .$recovery->id,
            $notification
                ->action_url
        );

        $this->assertDatabaseMissing(
            'notifications',
            [
                'recipient_user_id' =>
                    $context['operator']->id,

                'deduplication_key' =>
                    $dedupeKey,
            ]
        );

        /*
         * Service tidak mengizinkan dua pending
         * Recovery untuk Commitment yang sama.
         */
        try {
            $confidence->requestRecovery(
                $context['operator'],
                $context['commitment']
                    ->fresh(),
                'Recovery duplicate.'
            );

            $this->fail(
                'Recovery kedua seharusnya ditolak.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertTrue(true);
        }

        $this->assertSame(
            1,
            Notification::query()
                ->where(
                    'deduplication_key',
                    $dedupeKey
                )
                ->count()
        );
    }

    private function createApprovedContext(
        string $suffix,
        ?int $freshnessIntervalHours = 24,
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
                "SPPG-CN-{$suffix}"
            );

        $kdkmp =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-CN-{$suffix}"
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
                        $freshnessIntervalHours,

                    'notes' =>
                        'Confidence notification Forecast.',
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
                    "PROD-CN-{$suffix}",

                'name' =>
                    "Produsen {$suffix}",

                'village' =>
                    'Desa Test',

                'district' =>
                    'Kecamatan Test',

                'contact_phone' =>
                    '081234567890',

                'notes' =>
                    'Confidence notification fixture.',

                'is_active' =>
                    true,

                'created_by' =>
                    $operator->id,
            ]);

        $workflow =
            app(
                CommitmentWorkflowService::class
            );

        $commitment =
            $workflow->createDraft(
                $operator,
                [
                    'forecast_id' =>
                        $forecast->id,

                    'producer_id' =>
                        $producer->id,

                    'expected_harvest_id' =>
                        null,

                    'min_volume' =>
                        80,

                    'max_volume' =>
                        120,

                    'unit_id' =>
                        $unit->id,

                    'availability_start_at' =>
                        '2026-08-20 07:00:00',

                    'availability_end_at' =>
                        '2026-08-20 13:00:00',

                    'notes' =>
                        'Confidence notification Commitment.',

                    'operator_justification' =>
                        null,
                ]
            );

        $version =
            $commitment
                ->versions()
                ->firstOrFail();

        $version =
            $workflow->submit(
                $operator,
                $version
            );

        $version =
            $workflow->approve(
                $manager,
                $version
            );

        $commitment->refresh();

        $this->assertSame(
            CommitmentApprovalStatus::APPROVED,
            $version->approval_status
        );

        $this->assertSame(
            SupplyConfidence::GREEN,
            $commitment->current_confidence
        );

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

            'commitment' =>
                $commitment,

            'version' =>
                $version,
        ];
    }

    private function createReferenceData(
        string $suffix,
    ): array {
        $unit =
            Unit::create([
                'code' =>
                    "KG-CN-{$suffix}",

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
                    "COM-CN-{$suffix}",

                'name' =>
                    "Commodity {$suffix}",

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
                'Lokasi Notification Test',
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