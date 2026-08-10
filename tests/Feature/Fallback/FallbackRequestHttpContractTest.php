<?php

namespace Tests\Feature\Fallback;

use App\Enums\FallbackRequestStatus;
use App\Enums\NetworkRole;
use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Models\Commodity;
use App\Models\FallbackRequest;
use App\Models\Organization;
use App\Models\SupplyNetworkLink;
use App\Models\Unit;
use App\Models\User;
use App\Services\Fallback\FallbackRequestService;
use App\Services\Forecast\DemandForecastService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FallbackRequestHttpContractTest extends TestCase
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

    public function test_requester_index_contains_only_requests_from_own_organization(): void
    {
        $contextA =
            $this->createPendingRequestContext(
                'INDEX-A'
            );

        $contextB =
            $this->createPendingRequestContext(
                'INDEX-B'
            );

        $this->actingAs(
            $contextA['operator']
        )
            ->get(
                '/kdkmp/fallback-requests'
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'Kdkmp/FallbackRequests/Index'
                        )
                        ->has(
                            'requests',
                            1
                        )
                        ->where(
                            'requests.0.id',
                            $contextA[
                                'request'
                            ]->id
                        )
                        ->where(
                            'requests.0.requested_volume',
                            '150.000000'
                        )
            );

        /*
         * Pastikan Request B benar-benar ada,
         * tetapi tidak ikut terbawa pada tenant A.
         */
        $this->assertDatabaseHas(
            'fallback_requests',
            [
                'id' =>
                    $contextB[
                        'request'
                    ]->id,
            ]
        );
    }

    public function test_requester_cannot_open_other_organization_private_request_detail(): void
    {
        $contextA =
            $this->createPendingRequestContext(
                'PRIVATE-A'
            );

        $contextB =
            $this->createPendingRequestContext(
                'PRIVATE-B'
            );

        $this->actingAs(
            $contextA['manager']
        )
            ->get(
                '/kdkmp/fallback-requests/'
                .$contextB['request']->id
            )
            ->assertForbidden();
    }

    public function test_active_network_supplier_cannot_open_requester_private_detail_endpoint(): void
    {
        $context =
            $this->createOpenRequestContext(
                'NETWORK-PRIVATE'
            );

        /*
         * NETWORK memang boleh membaca broadcast-safe
         * Request nanti pada supplier broadcast
         * controller.
         *
         * Tetapi endpoint requester-private ini
         * menggunakan ability viewRequester.
         */
        $this->actingAs(
            $context['networkOperator']
        )
            ->get(
                '/kdkmp/fallback-requests/'
                .$context['request']->id
            )
            ->assertForbidden();

        $this->actingAs(
            $context['networkManager']
        )
            ->get(
                '/kdkmp/fallback-requests/'
                .$context['request']->id
            )
            ->assertForbidden();
    }

    public function test_requester_private_detail_does_not_expose_offer_or_source_internals(): void
    {
        $context =
            $this->createOpenRequestContext(
                'PRIVATE-PAYLOAD'
            );

        $this->actingAs(
            $context['manager']
        )
            ->get(
                '/kdkmp/fallback-requests/'
                .$context['request']->id
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'Kdkmp/FallbackRequests/Show'
                        )
                        ->where(
                            'fallbackRequest.id',
                            $context[
                                'request'
                            ]->id
                        )
                        ->where(
                            'fallbackRequest.requested_volume',
                            '150.000000'
                        )
                        ->missing(
                            'fallbackRequest.offers'
                        )
                        ->missing(
                            'fallbackRequest.sources'
                        )
                        ->missing(
                            'fallbackRequest.source_commitment_ids'
                        )
            );
    }

    public function test_operator_store_derives_requester_and_unit_from_server_context(): void
    {
        $context =
            $this->createOperationalContext(
                'STORE-DERIVED'
            );

        $attackerOrganization =
            $this->createOrganization(
                OrganizationType::KDKMP,
                'KDKMP-HTTP-FR-ATTACKER'
            );

        $otherUnit =
            Unit::create([
                'code' =>
                    'KG-HTTP-FR-OTHER',

                'name' =>
                    'Other Unit',

                'symbol' =>
                    'other',

                'decimal_precision' =>
                    6,

                'is_active' =>
                    true,
            ]);

        $response =
            $this->actingAs(
                $context['operator']
            )
                ->post(
                    '/kdkmp/fallback-requests',
                    [
                        'forecast_id' =>
                            $context[
                                'forecast'
                            ]->id,

                        'requested_volume' =>
                            '150.000000',

                        'response_deadline_at' =>
                            '2026-08-19 12:00:00',

                        'broadcast_note' =>
                            'HTTP derived-field test.',

                        /*
                         * Malicious fields.
                         *
                         * FormRequest tidak memasukkan
                         * ini ke validated payload.
                         */
                        'requester_organization_id' =>
                            $attackerOrganization->id,

                        'unit_id' =>
                            $otherUnit->id,

                        'status' =>
                            FallbackRequestStatus
                                ::OPEN
                                ->value,
                    ]
                );

        $fallbackRequest =
            FallbackRequest::query()
                ->where(
                    'created_by',
                    $context['operator']->id
                )
                ->firstOrFail();

        $response->assertRedirect(
            route(
                'kdkmp.fallback-requests.show',
                $fallbackRequest
            )
        );

        $this->assertSame(
            $context['primary']->id,
            $fallbackRequest
                ->requester_organization_id
        );

        $this->assertSame(
            $context['unit']->id,
            $fallbackRequest->unit_id
        );

        $this->assertSame(
            FallbackRequestStatus::DRAFT,
            $fallbackRequest->status
        );
    }

    public function test_network_operator_cannot_create_request_against_forecast_where_it_is_not_primary(): void
    {
        $context =
            $this->createOperationalContext(
                'NETWORK-CREATE-DENIAL'
            );

        $this->actingAs(
            $context['networkOperator']
        )
            ->from(
                '/kdkmp/fallback-requests/create'
            )
            ->post(
                '/kdkmp/fallback-requests',
                $this->requestPayload(
                    $context
                )
            )
            ->assertForbidden();

        $this->assertDatabaseMissing(
            'fallback_requests',
            [
                'created_by' =>
                    $context[
                        'networkOperator'
                    ]->id,
            ]
        );
    }

    public function test_operator_can_submit_own_draft_request(): void
    {
        $context =
            $this->createDraftRequestContext(
                'SUBMIT'
            );

        $this->actingAs(
            $context['operator']
        )
            ->post(
                '/kdkmp/fallback-requests/'
                .$context['request']->id
                .'/submit'
            )
            ->assertRedirect(
                route(
                    'kdkmp.fallback-requests.show',
                    $context['request']
                )
            );

        $request =
            $context['request']
                ->fresh();

        $this->assertSame(
            FallbackRequestStatus
                ::PENDING_APPROVAL,
            $request->status
        );

        $this->assertSame(
            $context['operator']->id,
            $request->submitted_by
        );

        $this->assertNotNull(
            $request->submitted_at
        );
    }

    public function test_manager_cannot_use_operator_submit_endpoint(): void
    {
        $context =
            $this->createDraftRequestContext(
                'MANAGER-SUBMIT-DENIAL'
            );

        $this->actingAs(
            $context['manager']
        )
            ->post(
                '/kdkmp/fallback-requests/'
                .$context['request']->id
                .'/submit'
            )
            ->assertForbidden();

        $this->assertSame(
            FallbackRequestStatus::DRAFT,
            $context['request']
                ->fresh()
                ->status
        );
    }

    public function test_manager_approval_queue_contains_only_own_pending_requests(): void
    {
        $contextA =
            $this->createPendingRequestContext(
                'QUEUE-A'
            );

        $contextB =
            $this->createPendingRequestContext(
                'QUEUE-B'
            );

        $this->actingAs(
            $contextA['manager']
        )
            ->get(
                '/kdkmp/manager/fallback-requests'
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'Kdkmp/Manager/FallbackRequests/Index'
                        )
                        ->has(
                            'requests',
                            1
                        )
                        ->where(
                            'requests.0.id',
                            $contextA[
                                'request'
                            ]->id
                        )
            );

        $this->actingAs(
            $contextA['manager']
        )
            ->get(
                '/kdkmp/manager/fallback-requests/'
                .$contextB['request']->id
            )
            ->assertForbidden();
    }

    public function test_operator_cannot_use_manager_broadcast_decision_endpoints(): void
    {
        $context =
            $this->createPendingRequestContext(
                'ROLE-SEPARATION'
            );

        $base =
            '/kdkmp/manager/fallback-requests/'
            .$context['request']->id;

        $this->actingAs(
            $context['operator']
        )
            ->post(
                $base.'/approve'
            )
            ->assertForbidden();

        $this->actingAs(
            $context['operator']
        )
            ->post(
                $base.'/reject',
                [
                    'review_reason' =>
                        'Operator tidak boleh review.',
                ]
            )
            ->assertForbidden();

        $this->assertSame(
            FallbackRequestStatus
                ::PENDING_APPROVAL,
            $context['request']
                ->fresh()
                ->status
        );
    }

    public function test_manager_reject_requires_reason_and_preserves_pending_state_on_validation_failure(): void
    {
        $context =
            $this->createPendingRequestContext(
                'REJECT-REASON'
            );

        $this->actingAs(
            $context['manager']
        )
            ->from(
                '/kdkmp/manager/fallback-requests/'
                .$context['request']->id
            )
            ->post(
                '/kdkmp/manager/fallback-requests/'
                .$context['request']->id
                .'/reject',
                []
            )
            ->assertSessionHas(
                'errors'
            );

        $request =
            $context['request']
                ->fresh();

        $this->assertSame(
            FallbackRequestStatus
                ::PENDING_APPROVAL,
            $request->status
        );

        $this->assertNull(
            $request->reviewed_by
        );

        $this->assertNull(
            $request->reviewed_at
        );

        $this->assertNull(
            $request->review_reason
        );
    }

    public function test_manager_can_approve_pending_request_to_open(): void
    {
        $context =
            $this->createPendingRequestContext(
                'APPROVE'
            );

        $this->actingAs(
            $context['manager']
        )
            ->post(
                '/kdkmp/manager/fallback-requests/'
                .$context['request']->id
                .'/approve'
            )
            ->assertRedirect(
                route(
                    'kdkmp.manager.fallback-requests.index'
                )
            );

        $request =
            $context['request']
                ->fresh();

        $this->assertSame(
            FallbackRequestStatus::OPEN,
            $request->status
        );

        $this->assertSame(
            $context['manager']->id,
            $request->reviewed_by
        );

        $this->assertNotNull(
            $request->opened_at
        );
    }

    public function test_operator_cannot_cancel_request(): void
    {
        $context =
            $this->createOpenRequestContext(
                'OPERATOR-CANCEL-DENIAL'
            );

        $this->actingAs(
            $context['operator']
        )
            ->post(
                '/kdkmp/fallback-requests/'
                .$context['request']->id
                .'/cancel',
                [
                    'cancellation_reason' =>
                        'Operator mencoba membatalkan.',
                ]
            )
            ->assertForbidden();

        $this->assertSame(
            FallbackRequestStatus::OPEN,
            $context['request']
                ->fresh()
                ->status
        );
    }

    public function test_manager_cancel_requires_reason_and_preserves_open_state_on_validation_failure(): void
    {
        $context =
            $this->createOpenRequestContext(
                'CANCEL-REASON'
            );

        $this->actingAs(
            $context['manager']
        )
            ->from(
                '/kdkmp/fallback-requests/'
                .$context['request']->id
            )
            ->post(
                '/kdkmp/fallback-requests/'
                .$context['request']->id
                .'/cancel',
                []
            )
            ->assertSessionHas(
                'errors'
            );

        $this->assertSame(
            FallbackRequestStatus::OPEN,
            $context['request']
                ->fresh()
                ->status
        );
    }

    public function test_manager_from_other_organization_cannot_cancel_request(): void
    {
        $contextA =
            $this->createOpenRequestContext(
                'CANCEL-A'
            );

        $contextB =
            $this->createOpenRequestContext(
                'CANCEL-B'
            );

        $this->actingAs(
            $contextA['manager']
        )
            ->post(
                '/kdkmp/fallback-requests/'
                .$contextB['request']->id
                .'/cancel',
                [
                    'cancellation_reason' =>
                        'Cross-organization attempt.',
                ]
            )
            ->assertForbidden();

        $this->assertSame(
            FallbackRequestStatus::OPEN,
            $contextB['request']
                ->fresh()
                ->status
        );
    }

    public function test_system_admin_has_no_operational_access_to_fallback_request_workspace(): void
    {
        $context =
            $this->createPendingRequestContext(
                'ADMIN-DENIAL'
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

        $this->actingAs(
            $admin
        )
            ->get(
                '/kdkmp/fallback-requests'
            )
            ->assertForbidden();

        $this->actingAs(
            $admin
        )
            ->get(
                '/kdkmp/fallback-requests/'
                .$context['request']->id
            )
            ->assertForbidden();

        $this->actingAs(
            $admin
        )
            ->post(
                '/kdkmp/manager/fallback-requests/'
                .$context['request']->id
                .'/approve'
            )
            ->assertForbidden();

        $this->assertSame(
            FallbackRequestStatus
                ::PENDING_APPROVAL,
            $context['request']
                ->fresh()
                ->status
        );
    }

    public function test_sppg_user_has_no_operational_access_to_fallback_request_workspace(): void
    {
        $context =
            $this->createPendingRequestContext(
                'SPPG-DENIAL'
            );

        $this->actingAs(
            $context['sppgUser']
        )
            ->get(
                '/kdkmp/fallback-requests'
            )
            ->assertForbidden();

        $this->actingAs(
            $context['sppgUser']
        )
            ->get(
                '/kdkmp/fallback-requests/'
                .$context['request']->id
            )
            ->assertForbidden();

        $this->actingAs(
            $context['sppgUser']
        )
            ->post(
                '/kdkmp/manager/fallback-requests/'
                .$context['request']->id
                .'/approve'
            )
            ->assertForbidden();

        $this->assertSame(
            FallbackRequestStatus
                ::PENDING_APPROVAL,
            $context['request']
                ->fresh()
                ->status
        );
    }

    public function test_fallback_request_http_surface_has_no_generic_lifecycle_mutation_endpoint(): void
    {
        $routes =
            collect(
                Route::getRoutes()
            )
                ->filter(
                    fn ($route) =>
                        str_starts_with(
                            $route->uri(),
                            'kdkmp/fallback-requests'
                        )
                        || str_starts_with(
                            $route->uri(),
                            'kdkmp/manager/fallback-requests'
                        )
                );

        /*
         * Lifecycle mutation hanya melalui
         * explicit POST command.
         *
         * Tidak ada generic PUT/PATCH/DELETE.
         */
        $this->assertFalse(
            $routes->contains(
                fn ($route) =>
                    in_array(
                        'PUT',
                        $route->methods(),
                        true
                    )
                    || in_array(
                        'PATCH',
                        $route->methods(),
                        true
                    )
                    || in_array(
                        'DELETE',
                        $route->methods(),
                        true
                    )
            )
        );

        $this->assertTrue(
            Route::has(
                'kdkmp.fallback-requests.store'
            )
        );

        $this->assertTrue(
            Route::has(
                'kdkmp.fallback-requests.submit'
            )
        );

        $this->assertTrue(
            Route::has(
                'kdkmp.fallback-requests.cancel'
            )
        );

        $this->assertTrue(
            Route::has(
                'kdkmp.manager.fallback-requests.approve'
            )
        );

        $this->assertTrue(
            Route::has(
                'kdkmp.manager.fallback-requests.reject'
            )
        );

        $this->assertFalse(
            Route::has(
                'kdkmp.fallback-requests.destroy'
            )
        );
    }

    private function createDraftRequestContext(
        string $suffix
    ): array {
        $context =
            $this->createOperationalContext(
                $suffix
            );

        $request =
            app(
                FallbackRequestService::class
            )->createDraft(
                $context['operator'],
                $context['forecast'],
                [
                    'requested_volume' =>
                        '150.000000',

                    'response_deadline_at' =>
                        '2026-08-19 12:00:00',

                    'broadcast_note' =>
                        'HTTP fallback fixture.',
                ]
            );

        return [
            ...$context,

            'request' =>
                $request,
        ];
    }

    private function createPendingRequestContext(
        string $suffix
    ): array {
        $context =
            $this->createDraftRequestContext(
                $suffix
            );

        $request =
            app(
                FallbackRequestService::class
            )->submit(
                $context['operator'],
                $context['request']
            );

        return [
            ...$context,

            'request' =>
                $request,
        ];
    }

    private function createOpenRequestContext(
        string $suffix
    ): array {
        $context =
            $this->createPendingRequestContext(
                $suffix
            );

        $request =
            app(
                FallbackRequestService::class
            )->approveBroadcast(
                $context['manager'],
                $context['request']
            );

        return [
            ...$context,

            'request' =>
                $request,
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
                "SPPG-HTTP-FR-{$suffix}"
            );

        $primary =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-HTTP-FR-PRIMARY-{$suffix}"
            );

        $network =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-HTTP-FR-NETWORK-{$suffix}"
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
                        '200.000000',

                    'required_start_at' =>
                        '2026-08-20 08:00:00',

                    'required_end_at' =>
                        '2026-08-20 12:00:00',

                    'freshness_interval_hours' =>
                        24,

                    'notes' =>
                        'Fallback HTTP contract Forecast.',
                ]
            );

        $forecast =
            $forecastService->publish(
                $sppgUser,
                $forecast,
                $forecast->version
            );

        return [
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

            'operator' =>
                $operator,

            'manager' =>
                $manager,

            'networkOperator' =>
                $networkOperator,

            'networkManager' =>
                $networkManager,

            'unit' =>
                $unit,

            'commodity' =>
                $commodity,

            'forecast' =>
                $forecast,
        ];
    }

    private function requestPayload(
        array $context
    ): array {
        return [
            'forecast_id' =>
                $context['forecast']->id,

            'requested_volume' =>
                '150.000000',

            'response_deadline_at' =>
                '2026-08-19 12:00:00',

            'broadcast_note' =>
                'Fallback HTTP request.',
        ];
    }

    private function createReferenceData(
        string $suffix
    ): array {
        $unit =
            Unit::create([
                'code' =>
                    "KG-HTTP-FR-{$suffix}",

                'name' =>
                    "Kilogram {$suffix}",

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
                    "COM-HTTP-FR-{$suffix}",

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