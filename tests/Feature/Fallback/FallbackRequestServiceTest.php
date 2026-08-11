<?php

namespace Tests\Feature\Fallback;

use App\Enums\FallbackRequestStatus;
use App\Enums\ForecastStatus;
use App\Enums\NetworkRole;
use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Models\Commodity;
use App\Models\DemandForecast;
use App\Models\Organization;
use App\Models\SupplyNetworkLink;
use App\Models\Unit;
use App\Models\User;
use App\Services\Fallback\FallbackRequestService;
use App\Services\Notification\OperationalNotificationService;
use App\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FallbackRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_operator_can_create_fallback_request_draft(): void
    {
        $context =
            $this->createContext(
                'CREATE'
            );

        $request =
            $this->service()
                ->createDraft(
                    $context['operator'],
                    $context['forecast'],
                    $this->validPayload()
                );

        $this->assertSame(
            FallbackRequestStatus::DRAFT,
            $request->status
        );

        $this->assertSame(
            $context['forecast']->id,
            $request->forecast_id
        );

        $this->assertSame(
            $context['primary']->id,
            $request
                ->requester_organization_id
        );

        $this->assertSame(
            $context['unit']->id,
            $request->unit_id
        );

        $this->assertSame(
            '150.000000',
            (string)
            $request->requested_volume
        );

        $this->assertSame(
            $context['operator']->id,
            $request->created_by
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'FALLBACK_REQUEST_CREATED',

                'entity_id' =>
                    $request->id,
            ]
        );
    }

    public function test_requested_volume_cannot_exceed_current_shortfall(): void
    {
        $context =
            $this->createContext(
                'OVER-SHORTFALL'
            );

        /*
         * Demand = 400
         * Safe = 0
         * Shortfall = 400
         */
        $this->expectException(
            ValidationException::class
        );

        $this->service()
            ->createDraft(
                $context['operator'],
                $context['forecast'],
                [
                    ...$this->validPayload(),
                    'requested_volume' =>
                        '400.000001',
                ]
            );
    }

    public function test_response_deadline_cannot_exceed_forecast_boundary(): void
    {
        $context =
            $this->createContext(
                'DEADLINE'
            );

        $this->expectException(
            ValidationException::class
        );

        $this->service()
            ->createDraft(
                $context['operator'],
                $context['forecast'],
                [
                    ...$this->validPayload(),
                    'response_deadline_at' =>
                        '2026-08-26 10:00:00',
                ]
            );
    }

    public function test_network_operator_cannot_create_requester_fallback_request(): void
    {
        $context =
            $this->createContext(
                'NETWORK'
            );

        $network =
    $this->createOrganization(
        OrganizationType::KDKMP,
        'KDKMP-FR-NETWORK-SECONDARY'
    );

        $networkOperator =
            User::factory()->create([
                'organization_id' =>
                    $network->id,

                'role' =>
                    UserRole::KDKMP_OPERATOR,

                'is_active' =>
                    true,
            ]);

        SupplyNetworkLink::create([
            'sppg_organization_id' =>
                $context['sppg']->id,

            'kdkmp_organization_id' =>
                $network->id,

            'network_role' =>
                NetworkRole::NETWORK,

            'is_active' =>
                true,

            'configured_by' =>
                $context['admin']->id,
        ]);

        $this->expectException(
            AuthorizationException::class
        );

        $this->service()
            ->createDraft(
                $networkOperator,
                $context['forecast'],
                $this->validPayload()
            );
    }

    public function test_operator_submit_moves_draft_to_pending_approval(): void
    {
        $context =
            $this->createContext(
                'SUBMIT'
            );

        $service =
            $this->service();

        $request =
            $service->createDraft(
                $context['operator'],
                $context['forecast'],
                $this->validPayload()
            );

        $submitted =
            $service->submit(
                $context['operator'],
                $request
            );

        $this->assertSame(
            FallbackRequestStatus
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

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'FALLBACK_REQUEST_SUBMITTED',

                'entity_id' =>
                    $request->id,
            ]
        );
    }

    public function test_repeat_submit_is_idempotent(): void
    {
        $context =
            $this->createContext(
                'SUBMIT-IDEMPOTENT'
            );

        $service =
            $this->service();

        $request =
            $service->createDraft(
                $context['operator'],
                $context['forecast'],
                $this->validPayload()
            );

        $first =
            $service->submit(
                $context['operator'],
                $request
            );

        $second =
            $service->submit(
                $context['operator'],
                $first
            );

        $this->assertSame(
            $first->id,
            $second->id
        );

        $this->assertSame(
            FallbackRequestStatus
                ::PENDING_APPROVAL,
            $second->status
        );

        $this->assertDatabaseCount(
            'audit_logs',
            2
        );
    }

    public function test_manager_can_approve_pending_request_to_open(): void
    {
        $context =
            $this->createContext(
                'APPROVE'
            );

        $service =
            $this->service();

        $request =
            $service->createDraft(
                $context['operator'],
                $context['forecast'],
                $this->validPayload()
            );

        $request =
            $service->submit(
                $context['operator'],
                $request
            );

        $opened =
            $service->approveBroadcast(
                $context['manager'],
                $request
            );

        $this->assertSame(
            FallbackRequestStatus::OPEN,
            $opened->status
        );

        $this->assertSame(
            $context['manager']->id,
            $opened->reviewed_by
        );

        $this->assertNotNull(
            $opened->reviewed_at
        );

        $this->assertNotNull(
            $opened->opened_at
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'FALLBACK_REQUEST_OPENED',

                'entity_id' =>
                    $request->id,
            ]
        );
    }

    public function test_manager_approval_revalidates_current_shortfall(): void
    {
        $context =
            $this->createContext(
                'REVALIDATE-SHORTFALL'
            );

        $service =
            $this->service();

        $request =
            $service->createDraft(
                $context['operator'],
                $context['forecast'],
                $this->validPayload()
            );

        $request =
            $service->submit(
                $context['operator'],
                $request
            );

        /*
         * Request = 150.
         *
         * Demand direvisi dari 400 ke 100.
         * Safe tetap 0.
         * Current Shortfall sekarang hanya 100.
         */
        $context['forecast']->update([
            'target_volume' =>
                '100.000000',
        ]);

        try {
            $service->approveBroadcast(
                $context['manager'],
                $request
            );

            $this->fail(
                'Approval seharusnya gagal karena requested volume melebihi current Shortfall.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'requested_volume',
                $exception->errors()
            );
        }

        $this->assertSame(
            FallbackRequestStatus
                ::PENDING_APPROVAL,
            $request->refresh()->status
        );
    }

    public function test_manager_approval_revalidates_forecast_boundary(): void
    {
        $context =
            $this->createContext(
                'REVALIDATE-DEADLINE'
            );

        $service =
            $this->service();

        $request =
            $service->createDraft(
                $context['operator'],
                $context['forecast'],
                [
                    ...$this->validPayload(),
                    'response_deadline_at' =>
                        '2026-08-24 10:00:00',
                ]
            );

        $request =
            $service->submit(
                $context['operator'],
                $request
            );

        /*
         * Forecast period direvisi sehingga
         * response deadline lama berada di luar
         * operational boundary.
         */
        $context['forecast']->update([
            'required_end_at' =>
                '2026-08-23 17:00:00',
        ]);

        $this->expectException(
            ValidationException::class
        );

        $service->approveBroadcast(
            $context['manager'],
            $request
        );
    }

    public function test_maker_cannot_approve_own_request_after_role_change(): void
    {
        $context =
            $this->createContext(
                'MAKER-CHECKER'
            );

        $service =
            $this->service();

        $request =
            $service->createDraft(
                $context['operator'],
                $context['forecast'],
                $this->validPayload()
            );

        $request =
            $service->submit(
                $context['operator'],
                $request
            );

        /*
         * Simulasi role actor berubah setelah
         * submit. ID maker tetap sama.
         */
        $context['operator']->update([
            'role' =>
                UserRole::KDKMP_MANAGER,
        ]);

        $context['operator']->refresh();

        $this->expectException(
            AuthorizationException::class
        );

        $service->approveBroadcast(
            $context['operator'],
            $request
        );
    }

    public function test_manager_from_other_organization_cannot_review_request(): void
    {
        $context =
            $this->createContext(
                'CROSS-ORG'
            );

        $service =
            $this->service();

        $request =
            $service->createDraft(
                $context['operator'],
                $context['forecast'],
                $this->validPayload()
            );

        $request =
            $service->submit(
                $context['operator'],
                $request
            );

        $otherOrganization =
            $this->createOrganization(
                OrganizationType::KDKMP,
                'KDKMP-FR-OTHER'
            );

        $otherManager =
            User::factory()->create([
                'organization_id' =>
                    $otherOrganization->id,

                'role' =>
                    UserRole::KDKMP_MANAGER,

                'is_active' =>
                    true,
            ]);

        $this->expectException(
            AuthorizationException::class
        );

        $service->approveBroadcast(
            $otherManager,
            $request
        );
    }

    public function test_manager_can_reject_pending_request_with_reason(): void
    {
        $context =
            $this->createContext(
                'REJECT'
            );

        $service =
            $this->service();

        $request =
            $service->createDraft(
                $context['operator'],
                $context['forecast'],
                $this->validPayload()
            );

        $request =
            $service->submit(
                $context['operator'],
                $request
            );
        
        $notificationService =
    $this->mock(
        OperationalNotificationService::class
    );

$notificationService
    ->shouldReceive(
        'fallbackRequestRejected'
    )
    ->once()
    ->withArgs(
        fn (
            $notifiedRequest
        ): bool =>
            $notifiedRequest->id
            === $request->id
    );

        $rejected =
    $this->service()
        ->rejectBroadcast(
                $context['manager'],
                $request,
                'Kebutuhan perlu dikaji ulang.'
            );

        $this->assertSame(
            FallbackRequestStatus::REJECTED,
            $rejected->status
        );

        $this->assertSame(
            'Kebutuhan perlu dikaji ulang.',
            $rejected->review_reason
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'FALLBACK_REQUEST_REJECTED',

                'entity_id' =>
                    $request->id,
            ]
        );
    }

    public function test_reject_requires_reason(): void
    {
        $context =
            $this->createContext(
                'REJECT-REASON'
            );

        $service =
            $this->service();

        $request =
            $service->createDraft(
                $context['operator'],
                $context['forecast'],
                $this->validPayload()
            );

        $request =
            $service->submit(
                $context['operator'],
                $request
            );

        $this->expectException(
            ValidationException::class
        );

        $service->rejectBroadcast(
            $context['manager'],
            $request,
            '   '
        );
    }

    public function test_non_published_forecast_cannot_start_fallback_request(): void
    {
        $context =
            $this->createContext(
                'STATUS'
            );

        $context['forecast']->update([
            'status' =>
                ForecastStatus::CANCELLED,
        ]);

        $this->expectException(
            ValidationException::class
        );

        $this->service()
            ->createDraft(
                $context['operator'],
                $context['forecast'],
                $this->validPayload()
            );
    }

    public function test_manager_requester_can_cancel_draft_request(): void
{
    $context =
        $this->createContext(
            'CANCEL-DRAFT'
        );

    $service =
        $this->service();

    $request =
        $service->createDraft(
            $context['operator'],
            $context['forecast'],
            $this->validPayload()
        );

    $cancelled =
        $service->cancel(
            $context['manager'],
            $request,
            'Kebutuhan fallback tidak lagi diperlukan.'
        );

    $this->assertSame(
        FallbackRequestStatus::CANCELLED,
        $cancelled->status
    );

    $this->assertNotNull(
        $cancelled->cancelled_at
    );

    $this->assertSame(
        'Kebutuhan fallback tidak lagi diperlukan.',
        $cancelled->cancellation_reason
    );

    $this->assertDatabaseHas(
        'audit_logs',
        [
            'action' =>
                'FALLBACK_REQUEST_CANCELLED',

            'entity_id' =>
                $request->id,
        ]
    );
}

public function test_manager_requester_can_cancel_open_request(): void
{
    $context =
        $this->createContext(
            'CANCEL-OPEN'
        );

    $service =
        $this->service();

    $request =
        $service->createDraft(
            $context['operator'],
            $context['forecast'],
            $this->validPayload()
        );

    $request =
        $service->submit(
            $context['operator'],
            $request
        );

    $request =
        $service->approveBroadcast(
            $context['manager'],
            $request
        );

    $cancelled =
        $service->cancel(
            $context['manager'],
            $request,
            'Broadcast fallback dihentikan.'
        );

    $this->assertSame(
        FallbackRequestStatus::CANCELLED,
        $cancelled->status
    );
}

public function test_pending_request_cannot_be_cancelled(): void
{
    $context =
        $this->createContext(
            'CANCEL-PENDING'
        );

    $service =
        $this->service();

    $request =
        $service->createDraft(
            $context['operator'],
            $context['forecast'],
            $this->validPayload()
        );

    $request =
        $service->submit(
            $context['operator'],
            $request
        );

    try {
        $service->cancel(
            $context['manager'],
            $request,
            'Batalkan pending.'
        );

        $this->fail(
            'PENDING_APPROVAL tidak boleh menuju CANCELLED.'
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
        FallbackRequestStatus
            ::PENDING_APPROVAL,
        $request->refresh()->status
    );
}

public function test_operator_cannot_cancel_fallback_request(): void
{
    $context =
        $this->createContext(
            'CANCEL-ROLE'
        );

    $request =
        $this->service()
            ->createDraft(
                $context['operator'],
                $context['forecast'],
                $this->validPayload()
            );

    $this->expectException(
        AuthorizationException::class
    );

    $this->service()
        ->cancel(
            $context['operator'],
            $request,
            'Operator mencoba membatalkan.'
        );
}

public function test_cancel_requires_reason(): void
{
    $context =
        $this->createContext(
            'CANCEL-REASON'
        );

    $request =
        $this->service()
            ->createDraft(
                $context['operator'],
                $context['forecast'],
                $this->validPayload()
            );

    $this->expectException(
        ValidationException::class
    );

    $this->service()
        ->cancel(
            $context['manager'],
            $request,
            '   '
        );
}

public function test_repeat_cancellation_is_idempotent(): void
{
    $context =
        $this->createContext(
            'CANCEL-IDEMPOTENT'
        );

    $service =
        $this->service();

    $request =
        $service->createDraft(
            $context['operator'],
            $context['forecast'],
            $this->validPayload()
        );

    $first =
        $service->cancel(
            $context['manager'],
            $request,
            'Tidak diperlukan.'
        );

    $second =
        $service->cancel(
            $context['manager'],
            $first,
            'Tidak diperlukan.'
        );

    $this->assertSame(
        FallbackRequestStatus::CANCELLED,
        $second->status
    );

    $this->assertSame(
        1,
        AuditLog::query()
            ->where(
                'action',
                'FALLBACK_REQUEST_CANCELLED'
            )
            ->where(
                'entity_id',
                $request->id
            )
            ->count()
    );
}

public function test_open_request_expires_only_after_response_deadline(): void
{
    $context =
        $this->createContext(
            'EXPIRE'
        );

    $service =
        $this->service();

    $request =
        $service->createDraft(
            $context['operator'],
            $context['forecast'],
            [
                ...$this->validPayload(),

                'response_deadline_at' =>
                    '2026-08-19 12:00:00',
            ]
        );

    $request =
        $service->submit(
            $context['operator'],
            $request
        );

    $request =
        $service->approveBroadcast(
            $context['manager'],
            $request
        );

    $expired =
        $service->expire(
            $request,
            CarbonImmutable::parse(
                '2026-08-19 12:00:01'
            )
        );

    $this->assertSame(
        FallbackRequestStatus::EXPIRED,
        $expired->status
    );

    $this->assertSame(
        '2026-08-19 12:00:01',
        $expired->expired_at
            ->format(
                'Y-m-d H:i:s'
            )
    );

    $audit =
        AuditLog::query()
            ->where(
                'action',
                'FALLBACK_REQUEST_EXPIRED'
            )
            ->where(
                'entity_id',
                $request->id
            )
            ->firstOrFail();

    $this->assertNull(
        $audit->actor_user_id
    );

    $this->assertSame(
        'SYSTEM',
        $audit->source->value
    );
}

public function test_exact_response_deadline_is_still_valid(): void
{
    $context =
        $this->createContext(
            'EXPIRE-BOUNDARY'
        );

    $service =
        $this->service();

    $request =
        $service->createDraft(
            $context['operator'],
            $context['forecast'],
            [
                ...$this->validPayload(),

                'response_deadline_at' =>
                    '2026-08-19 12:00:00',
            ]
        );

    $request =
        $service->submit(
            $context['operator'],
            $request
        );

    $request =
        $service->approveBroadcast(
            $context['manager'],
            $request
        );

    $expiredCount =
        $service->expireDueOpenRequests(
            CarbonImmutable::parse(
                '2026-08-19 12:00:00'
            )
        );

    $this->assertSame(
        0,
        $expiredCount
    );

    $this->assertSame(
        FallbackRequestStatus::OPEN,
        $request->refresh()->status
    );
}

public function test_batch_expiry_only_expires_due_open_requests(): void
{
    $dueContext =
        $this->createContext(
            'EXPIRE-BATCH-DUE'
        );

    $futureContext =
        $this->createContext(
            'EXPIRE-BATCH-FUTURE'
        );

    $service =
        $this->service();

    $due =
        $service->createDraft(
            $dueContext['operator'],
            $dueContext['forecast'],
            [
                ...$this->validPayload(),

                'response_deadline_at' =>
                    '2026-08-18 12:00:00',
            ]
        );

    $due =
        $service->submit(
            $dueContext['operator'],
            $due
        );

    $due =
        $service->approveBroadcast(
            $dueContext['manager'],
            $due
        );

    $future =
        $service->createDraft(
            $futureContext['operator'],
            $futureContext['forecast'],
            [
                ...$this->validPayload(),

                'response_deadline_at' =>
                    '2026-08-20 12:00:00',
            ]
        );

    $future =
        $service->submit(
            $futureContext['operator'],
            $future
        );

    $future =
        $service->approveBroadcast(
            $futureContext['manager'],
            $future
        );

    $expiredCount =
        $service->expireDueOpenRequests(
            CarbonImmutable::parse(
                '2026-08-19 12:00:00'
            )
        );

    $this->assertSame(
        1,
        $expiredCount
    );

    $this->assertSame(
        FallbackRequestStatus::EXPIRED,
        $due->refresh()->status
    );

    $this->assertSame(
        FallbackRequestStatus::OPEN,
        $future->refresh()->status
    );
}
    private function service(): FallbackRequestService
    {
        return app(
            FallbackRequestService::class
        );
    }

    private function validPayload(): array
    {
        return [
            'requested_volume' =>
                '150.000000',

            'response_deadline_at' =>
                '2026-08-19 12:00:00',

            'broadcast_note' =>
                'Dibutuhkan dukungan pasokan agregat.',
        ];
    }

    private function createContext(
        string $suffix,
    ): array {
        $unit =
            Unit::create([
                'code' =>
                    "KG-FR-{$suffix}",

                'name' =>
                    "Kilogram FR {$suffix}",

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
                    "COM-FR-{$suffix}",

                'name' =>
                    "Commodity FR {$suffix}",

                'default_unit_id' =>
                    $unit->id,

                'harvest_behavior' =>
                    null,

                'notes' =>
                    null,

                'is_active' =>
                    true,
            ]);

        $sppg =
            $this->createOrganization(
                OrganizationType::SPPG,
                "SPPG-FR-{$suffix}"
            );

        $primary =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-FR-{$suffix}"
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
            User::factory()->create([
                'organization_id' =>
                    $primary->id,

                'role' =>
                    UserRole::KDKMP_OPERATOR,

                'is_active' =>
                    true,
            ]);

        $manager =
            User::factory()->create([
                'organization_id' =>
                    $primary->id,

                'role' =>
                    UserRole::KDKMP_MANAGER,

                'is_active' =>
                    true,
            ]);

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

        $forecast =
            DemandForecast::create([
                'sppg_organization_id' =>
                    $sppg->id,

                'commodity_id' =>
                    $commodity->id,

                'unit_id' =>
                    $unit->id,

                'forecast_code' =>
                    "FC-FR-{$suffix}",

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
                    null,

                'published_at' =>
                    '2026-08-10 08:00:00',

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

            'admin' =>
                $admin,

            'sppg_user' =>
                $sppgUser,

            'operator' =>
                $operator,

            'manager' =>
                $manager,

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
                $code,

            'organization_type' =>
                $type,

            'is_active' =>
                true,

            'general_location' =>
                'Lokasi Test Fallback',
        ]);
    }
}