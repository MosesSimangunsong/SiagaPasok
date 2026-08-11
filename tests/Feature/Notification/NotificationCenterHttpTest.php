<?php

namespace Tests\Feature\Notification;

use App\Enums\NotificationPriority;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\Notification;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationCenterHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse(
                '2026-08-11 15:00:00'
            )
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_notification_center_only_returns_authenticated_recipients_notifications(): void
    {
        $actor =
            $this->createUser();

        $otherUser =
            $this->createUser();

        $older =
            $this->createNotification(
                recipient:
                    $actor,

                title:
                    'Notification lama',

                createdAt:
                    '2026-08-11 13:00:00'
            );

        $newer =
            $this->createNotification(
                recipient:
                    $actor,

                title:
                    'Notification terbaru',

                createdAt:
                    '2026-08-11 14:00:00'
            );

        $foreign =
            $this->createNotification(
                recipient:
                    $otherUser,

                title:
                    'Notification user lain',

                createdAt:
                    '2026-08-11 14:30:00'
            );

        $response =
            $this
                ->actingAs(
                    $actor
                )
                ->get(
                    route(
                        'notifications.index'
                    )
                );

        $response
            ->assertOk()
            ->assertInertia(
                fn (
                    Assert $page
                ) =>
                    $page
                        ->component(
                            'Notifications/Index'
                        )
                        ->has(
                            'notifications.data',
                            2
                        )
                        ->where(
                            'notifications.data.0.id',
                            $newer->id
                        )
                        ->where(
                            'notifications.data.1.id',
                            $older->id
                        )
                        ->missing(
                            'notifications.data.2'
                        )
            );

        $this->assertNotSame(
            $foreign->recipient_user_id,
            $actor->id
        );
    }

    public function test_shared_unread_count_only_counts_current_user_notifications(): void
    {
        $actor =
            $this->createUser();

        $otherUser =
            $this->createUser();

        $this->createNotification(
            recipient:
                $actor,

            title:
                'Unread 1'
        );

        $this->createNotification(
            recipient:
                $actor,

            title:
                'Unread 2'
        );

        $read =
            $this->createNotification(
                recipient:
                    $actor,

            title:
                'Sudah dibaca'
        );

        $read->update([
            'read_at' =>
                now(),
        ]);

        $this->createNotification(
            recipient:
                $otherUser,

            title:
                'Foreign unread'
        );

        $this
            ->actingAs(
                $actor
            )
            ->get(
                route(
                    'notifications.index'
                )
            )
            ->assertInertia(
                fn (
                    Assert $page
                ) =>
                    $page
                        ->where(
                            'notification_center.unread_count',
                            2
                        )
                        ->where(
                            'notification_center.href',
                            route(
                                'notifications.index'
                            )
                        )
            );
    }

    public function test_recipient_can_mark_notification_read_idempotently(): void
    {
        $actor =
            $this->createUser();

        $notification =
            $this->createNotification(
                recipient:
                    $actor,

                title:
                    'Perlu dibaca'
            );

        $this->assertNull(
            $notification->read_at
        );

        $this
            ->actingAs(
                $actor
            )
            ->patch(
                route(
                    'notifications.read',
                    $notification
                )
            )
            ->assertRedirect();

        $notification->refresh();

        $this->assertNotNull(
            $notification->read_at
        );

        $firstReadAt =
            $notification
                ->read_at
                ->toIso8601String();

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse(
                '2026-08-11 16:00:00'
            )
        );

        $this
            ->actingAs(
                $actor
            )
            ->patch(
                route(
                    'notifications.read',
                    $notification
                )
            )
            ->assertRedirect();

        $this->assertSame(
            $firstReadAt,
            $notification
                ->fresh()
                ->read_at
                ->toIso8601String()
        );
    }

    public function test_user_cannot_mark_another_users_notification_read(): void
    {
        $actor =
            $this->createUser();

        $otherUser =
            $this->createUser();

        $notification =
            $this->createNotification(
                recipient:
                    $otherUser,

                title:
                    'Private notification'
            );

        $this
            ->actingAs(
                $actor
            )
            ->patch(
                route(
                    'notifications.read',
                    $notification
                )
            )
            ->assertForbidden();

        $this->assertNull(
            $notification
                ->fresh()
                ->read_at
        );
    }

    private function createUser(): User
    {
        return User::factory()
            ->create([
                'organization_id' =>
                    null,

                'role' =>
                    UserRole::SYSTEM_ADMIN,

                'is_active' =>
                    true,
            ]);
    }

    private function createNotification(
        User $recipient,
        string $title,
        string $createdAt =
            '2026-08-11 15:00:00',
    ): Notification {
        return Notification::create([
            'recipient_user_id' =>
                $recipient->id,

            'notification_type' =>
                NotificationType::RFP,

            'priority' =>
                NotificationPriority
                    ::INFORMATION,

            'title' =>
                $title,

            'message' =>
                'Notification HTTP test.',

            'related_entity_type' =>
                null,

            'related_entity_id' =>
                null,

            'action_url' =>
                '/',

            'deduplication_key' =>
                null,

            'read_at' =>
                null,

            'created_at' =>
                CarbonImmutable::parse(
                    $createdAt
                ),
        ]);
    }
}