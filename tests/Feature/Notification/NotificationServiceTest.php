<?php

namespace Tests\Feature\Notification;

use App\Enums\NotificationPriority;
use App\Enums\NotificationType;
use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
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

    public function test_notification_is_persisted_immediately_when_no_transaction_is_active(): void
    {
        $context =
            $this->createContext(
                'IMMEDIATE'
            );

        $this->service()
            ->send(
                recipient:
                    $context['recipient'],

                type:
                    NotificationType
                        ::APPROVAL_REQUIRED,

                priority:
                    NotificationPriority
                        ::ACTION,

                title:
                    'Commitment perlu persetujuan',

                message:
                    'Tinjau commitment yang menunggu keputusan.',

                relatedEntity:
                    $context['entity'],

                actionUrl:
                    '/kdkmp/manager/commitments/1',

                deduplicationKey:
                    'test:immediate:1',
            );

        $notification =
            Notification::query()
                ->firstOrFail();

        $this->assertSame(
            $context['recipient']->id,
            $notification
                ->recipient_user_id
        );

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

        $this->assertTrue(
            $notification->isUnread()
        );

        $this->assertSame(
            '/kdkmp/manager/commitments/1',
            $notification->action_url
        );
    }

    public function test_notification_inside_transaction_is_created_only_after_commit(): void
    {
        $context =
            $this->createContext(
                'AFTER-COMMIT'
            );

        DB::beginTransaction();

        $this->service()
            ->send(
                recipient:
                    $context['recipient'],

                type:
                    NotificationType::RFP,

                priority:
                    NotificationPriority
                        ::INFORMATION,

                title:
                    'Ready for Procurement tercapai',

                message:
                    'Forecast telah memenuhi seluruh gate.',

                relatedEntity:
                    $context['entity'],

                actionUrl:
                    '/sppg/forecasts/1',

                deduplicationKey:
                    'test:after-commit:1',
            );

        $this->assertDatabaseMissing(
            'notifications',
            [
                'deduplication_key' =>
                    'test:after-commit:1',
            ]
        );

        DB::commit();

        $this->assertDatabaseHas(
            'notifications',
            [
                'recipient_user_id' =>
                    $context[
                        'recipient'
                    ]->id,

                'deduplication_key' =>
                    'test:after-commit:1',
            ]
        );
    }

    public function test_rolled_back_transaction_creates_no_notification(): void
    {
        $context =
            $this->createContext(
                'ROLLBACK'
            );

        DB::beginTransaction();

        $this->service()
            ->send(
                recipient:
                    $context['recipient'],

                type:
                    NotificationType
                        ::SUPPLY_RISK,

                priority:
                    NotificationPriority
                        ::WARNING,

                title:
                    'Risiko pasokan',

                message:
                    'Confidence pasokan menurun.',

                relatedEntity:
                    $context['entity'],

                deduplicationKey:
                    'test:rollback:1',
            );

        DB::rollBack();

        $this->assertDatabaseMissing(
            'notifications',
            [
                'deduplication_key' =>
                    'test:rollback:1',
            ]
        );
    }

    public function test_repeated_same_event_does_not_duplicate_notification_for_same_recipient(): void
    {
        $context =
            $this->createContext(
                'DEDUPE'
            );

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $this->service()
                ->send(
                    recipient:
                        $context[
                            'recipient'
                        ],

                    type:
                        NotificationType
                            ::READINESS,

                    priority:
                        NotificationPriority
                            ::ACTION,

                    title:
                        'Readiness perlu ditinjau',

                    message:
                        'Checklist menunggu persetujuan.',

                    relatedEntity:
                        $context['entity'],

                    deduplicationKey:
                        'readiness:99:submitted',
                );
        }

        $this->assertSame(
            1,
            Notification::query()
                ->where(
                    'recipient_user_id',
                    $context[
                        'recipient'
                    ]->id
                )
                ->where(
                    'deduplication_key',
                    'readiness:99:submitted'
                )
                ->count()
        );
    }

    public function test_recipient_can_mark_own_notification_read(): void
    {
        $context =
            $this->createContext(
                'READ'
            );

        $this->service()
            ->send(
                recipient:
                    $context['recipient'],

                type:
                    NotificationType::RFP,

                priority:
                    NotificationPriority
                        ::INFORMATION,

                title:
                    'RFP tercapai',

                message:
                    'Forecast siap menuju proses resmi.',

                relatedEntity:
                    $context['entity'],
            );

        $notification =
            Notification::query()
                ->firstOrFail();

        $notification =
            $this->service()
                ->markRead(
                    $context['recipient'],
                    $notification
                );

        $this->assertTrue(
            $notification->isRead()
        );

        $this->assertNotNull(
            $notification->read_at
        );
    }

    public function test_other_user_cannot_mark_notification_read(): void
    {
        $context =
            $this->createContext(
                'CROSS-RECIPIENT'
            );

        $other =
            User::factory()->create([
                'organization_id' =>
                    $context[
                        'organization'
                    ]->id,

                'role' =>
                    UserRole::KDKMP_MANAGER,

                'is_active' =>
                    true,
            ]);

        $this->service()
            ->send(
                recipient:
                    $context['recipient'],

                type:
                    NotificationType::RFP,

                priority:
                    NotificationPriority
                        ::WARNING,

                title:
                    'RFP hilang',

                message:
                    'Salah satu dependency tidak lagi valid.',

                relatedEntity:
                    $context['entity'],
            );

        $notification =
            Notification::query()
                ->firstOrFail();

        try {
            $this->service()
                ->markRead(
                    $other,
                    $notification
                );

            $this->fail(
                'User lain tidak boleh mengubah notification recipient.'
            );
        } catch (
            AuthorizationException $exception
        ) {
            $this->assertTrue(
                $notification
                    ->fresh()
                    ->isUnread()
            );
        }
    }

    private function service():
        NotificationService
    {
        return app(
            NotificationService::class
        );
    }

    private function createContext(
        string $suffix,
    ): array {
        $organization =
            Organization::create([
                'code' =>
                    "ORG-NOTIF-{$suffix}",

                'name' =>
                    "Organization Notification {$suffix}",

                'organization_type' =>
                    OrganizationType::KDKMP,

                'is_active' =>
                    true,

                'general_location' =>
                    'Lokasi Notification Test',
            ]);

        $recipient =
            User::factory()->create([
                'organization_id' =>
                    $organization->id,

                'role' =>
                    UserRole::KDKMP_MANAGER,

                'is_active' =>
                    true,
            ]);

        /*
         * Organization cukup sebagai generic
         * persisted related entity untuk menguji
         * notification infrastructure.
         */
        $entity =
            Organization::create([
                'code' =>
                    "ENTITY-NOTIF-{$suffix}",

                'name' =>
                    "Related Entity {$suffix}",

                'organization_type' =>
                    OrganizationType::KDKMP,

                'is_active' =>
                    true,

                'general_location' =>
                    'Lokasi Related Entity',
            ]);

        return [
            'organization' =>
                $organization,

            'recipient' =>
                $recipient,

            'entity' =>
                $entity,
        ];
    }
}