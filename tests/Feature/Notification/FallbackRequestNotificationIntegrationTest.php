<?php

namespace Tests\Feature\Notification;

use App\Enums\FallbackRequestStatus;
use App\Enums\ForecastStatus;
use App\Enums\NetworkRole;
use App\Enums\NotificationPriority;
use App\Enums\NotificationType;
use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Models\Commodity;
use App\Models\DemandForecast;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\SupplyNetworkLink;
use App\Models\Unit;
use App\Models\User;
use App\Services\Fallback\FallbackRequestService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class FallbackRequestNotificationIntegrationTest extends TestCase
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

    public function test_submit_notifies_requester_manager_once(): void
    {
        $context =
            $this->createContext(
                'SUBMIT'
            );

        $service =
            app(
                FallbackRequestService::class
            );

        $request =
            $service->createDraft(
                $context['primaryOperator'],
                $context['forecast'],
                [
                    'requested_volume' =>
                        '150.000000',

                    'response_deadline_at' =>
                        '2026-08-19 18:00:00',

                    'broadcast_note' =>
                        'Fallback notification test.',
                ]
            );

        $submitted =
            $service->submit(
                $context['primaryOperator'],
                $request
            );

        $this->assertSame(
            FallbackRequestStatus
                ::PENDING_APPROVAL,
            $submitted->status
        );

        $notification =
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
            '/kdkmp/manager/fallback-requests/'
            .$submitted->id,
            $notification
                ->action_url
        );

        $this->assertSame(
            'fallback-request:'
            .$submitted->id
            .':approval-required',
            $notification
                ->deduplication_key
        );

        /*
         * Operator requester tidak menerima
         * approval CTA untuk payload miliknya.
         */
        $this->assertDatabaseMissing(
            'notifications',
            [
                'recipient_user_id' =>
                    $context[
                        'primaryOperator'
                    ]->id,

                'deduplication_key' =>
                    'fallback-request:'
                    .$submitted->id
                    .':approval-required',
            ]
        );

        /*
         * Retry submit idempotent.
         */
        $service->submit(
            $context['primaryOperator'],
            $submitted
        );

        $this->assertSame(
            1,
            Notification::query()
                ->where(
                    'deduplication_key',
                    'fallback-request:'
                    .$submitted->id
                    .':approval-required'
                )
                ->count()
        );
    }

    public function test_open_broadcast_notifies_active_network_operator_and_manager_only(): void
    {
        $context =
            $this->createContext(
                'OPEN'
            );

        $service =
            app(
                FallbackRequestService::class
            );

        $request =
            $service->createDraft(
                $context['primaryOperator'],
                $context['forecast'],
                [
                    'requested_volume' =>
                        '150.000000',

                    'response_deadline_at' =>
                        '2026-08-19 18:00:00',

                    'broadcast_note' =>
                        'Network broadcast test.',
                ]
            );

        $request =
            $service->submit(
                $context['primaryOperator'],
                $request
            );

        /*
         * Isolasi event OPEN dari approval
         * notification sebelumnya.
         */
        Notification::query()->delete();

        $opened =
            $service->approveBroadcast(
                $context['primaryManager'],
                $request
            );

        $this->assertSame(
            FallbackRequestStatus::OPEN,
            $opened->status
        );

        $dedupeKey =
            'fallback-request:'
            .$opened->id
            .':opened';

        foreach (
            [
                $context['networkOperator'],
                $context['networkManager'],
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
                    ::FALLBACK_REQUEST,
                $notification
                    ->notification_type
            );

            $this->assertSame(
                NotificationPriority
                    ::ACTION,
                $notification
                    ->priority
            );

            $this->assertSame(
                '/kdkmp/fallback-network/'
                .$opened->id,
                $notification
                    ->action_url
            );
        }

        /*
         * Requester organization bukan
         * broadcast supplier recipient.
         */
        $this->assertDatabaseMissing(
            'notifications',
            [
                'recipient_user_id' =>
                    $context[
                        'primaryManager'
                    ]->id,

                'deduplication_key' =>
                    $dedupeKey,
            ]
        );

        $this->assertDatabaseMissing(
            'notifications',
            [
                'recipient_user_id' =>
                    $context[
                        'primaryOperator'
                    ]->id,

                'deduplication_key' =>
                    $dedupeKey,
            ]
        );

        /*
         * Inactive NETWORK link tidak menerima.
         */
        $this->assertDatabaseMissing(
            'notifications',
            [
                'recipient_user_id' =>
                    $context[
                        'inactiveNetworkOperator'
                    ]->id,

                'deduplication_key' =>
                    $dedupeKey,
            ]
        );

        /*
         * KDKMP yang tidak mempunyai active
         * NETWORK link juga tidak menerima.
         */
        $this->assertDatabaseMissing(
            'notifications',
            [
                'recipient_user_id' =>
                    $context[
                        'unrelatedManager'
                    ]->id,

                'deduplication_key' =>
                    $dedupeKey,
            ]
        );

        /*
         * Repeated approval tidak duplicate.
         */
        $service->approveBroadcast(
            $context['primaryManager'],
            $opened
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
    }

    private function createContext(
        string $suffix,
    ): array {
        $unit =
            Unit::create([
                'code' =>
                    "KG-FR-NOTIF-{$suffix}",

                'name' =>
                    "Kilogram FR Notification {$suffix}",

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
                    "COM-FR-NOTIF-{$suffix}",

                'name' =>
                    "Commodity FR Notification {$suffix}",

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
                "SPPG-FR-NOTIF-{$suffix}"
            );

        $primary =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-FR-PRIMARY-{$suffix}"
            );

        $network =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-FR-NETWORK-{$suffix}"
            );

        $inactiveNetwork =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-FR-INACTIVE-NET-{$suffix}"
            );

        $unrelated =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-FR-UNRELATED-{$suffix}"
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

        SupplyNetworkLink::create([
            'sppg_organization_id' =>
                $sppg->id,

            'kdkmp_organization_id' =>
                $inactiveNetwork->id,

            'network_role' =>
                NetworkRole::NETWORK,

            'is_active' =>
                false,

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

        $inactiveNetworkOperator =
            $this->createKdkmpUser(
                $inactiveNetwork,
                UserRole::KDKMP_OPERATOR
            );

        $unrelatedManager =
            $this->createKdkmpUser(
                $unrelated,
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
                    "FC-FR-NOTIF-{$suffix}",

                /*
                 * Tidak ada Safe Supply fixture,
                 * sehingga canonical Shortfall = 300.
                 */
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
                    'Fallback Request notification fixture.',

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

            'sppg' =>
                $sppg,

            'primary' =>
                $primary,

            'network' =>
                $network,

            'primaryOperator' =>
                $primaryOperator,

            'primaryManager' =>
                $primaryManager,

            'networkOperator' =>
                $networkOperator,

            'networkManager' =>
                $networkManager,

            'inactiveNetworkOperator' =>
                $inactiveNetworkOperator,

            'unrelatedManager' =>
                $unrelatedManager,

            'forecast' =>
                $forecast,
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