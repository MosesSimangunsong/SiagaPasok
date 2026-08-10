<?php

namespace Tests\Feature\Fallback;

use App\Enums\NetworkRole;
use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Models\Commodity;
use App\Models\Organization;
use App\Models\SupplyNetworkLink;
use App\Models\Unit;
use App\Models\User;
use App\Services\Fallback\FallbackRequestService;
use App\Services\Forecast\DemandForecastService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FallbackBroadcastHttpContractTest extends TestCase
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

    public function test_active_network_operator_can_see_open_broadcast(): void
    {
        $context =
            $this->createOpenContext(
                'VISIBLE'
            );

        $this->actingAs(
            $context['networkOperator']
        )
            ->get(
                '/kdkmp/fallback-network'
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'Kdkmp/FallbackNetwork/Index'
                        )
                        ->has(
                            'requests',
                            1
                        )
                        ->where(
                            'requests.0.id',
                            $context[
                                'request'
                            ]->id
                        )
                        ->where(
                            'requests.0.requested_volume',
                            '150.000000'
                        )
                        ->where(
                            'requests.0.remaining_volume',
                            '150.000000'
                        )
            );
    }

    public function test_active_network_manager_can_read_broadcast_detail(): void
    {
        $context =
            $this->createOpenContext(
                'MANAGER-VIEW'
            );

        $this->actingAs(
            $context['networkManager']
        )
            ->get(
                '/kdkmp/fallback-network/'
                .$context['request']->id
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'Kdkmp/FallbackNetwork/Show'
                        )
                        ->where(
                            'request.id',
                            $context[
                                'request'
                            ]->id
                        )
                        ->where(
                            'can.createOffer',
                            false
                        )
            );
    }

    public function test_broadcast_payload_contains_only_aggregate_safe_request_data(): void
    {
        $context =
            $this->createOpenContext(
                'PRIVACY'
            );

        $this->actingAs(
            $context['networkOperator']
        )
            ->get(
                '/kdkmp/fallback-network/'
                .$context['request']->id
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'Kdkmp/FallbackNetwork/Show'
                        )
                        ->where(
                            'request.requester_organization.id',
                            $context['primary']->id
                        )
                        ->where(
                            'request.commodity.id',
                            $context['commodity']->id
                        )
                        ->where(
                            'request.requested_volume',
                            '150.000000'
                        )
                        ->where(
                            'request.remaining_volume',
                            '150.000000'
                        )
                        ->missing(
                            'request.created_by'
                        )
                        ->missing(
                            'request.submitted_by'
                        )
                        ->missing(
                            'request.reviewed_by'
                        )
                        ->missing(
                            'request.offers'
                        )
                        ->missing(
                            'request.sources'
                        )
                        ->missing(
                            'request.producers'
                        )
                        ->missing(
                            'request.commitments'
                        )
                        ->missing(
                            'request.source_commitment_ids'
                        )
            );
    }

    public function test_network_does_not_see_request_until_it_is_open(): void
    {
        $context =
            $this->createBaseContext(
                'NOT-OPEN'
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
                        'Belum approved.',
                ]
            );

        $this->actingAs(
            $context['networkOperator']
        )
            ->get(
                '/kdkmp/fallback-network'
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page->has(
                        'requests',
                        0
                    )
            );

        $this->actingAs(
            $context['networkOperator']
        )
            ->get(
                '/kdkmp/fallback-network/'
                .$request->id
            )
            ->assertForbidden();
    }

    public function test_unrelated_kdkmp_cannot_read_broadcast_detail(): void
    {
        $context =
            $this->createOpenContext(
                'UNRELATED'
            );

        $unrelated =
            $this->createOrganization(
                OrganizationType::KDKMP,
                'KDKMP-BROADCAST-UNRELATED'
            );

        $unrelatedOperator =
            $this->createKdkmpUser(
                $unrelated,
                UserRole::KDKMP_OPERATOR
            );

        $this->actingAs(
            $unrelatedOperator
        )
            ->get(
                '/kdkmp/fallback-network/'
                .$context['request']->id
            )
            ->assertForbidden();
    }

    public function test_requester_primary_does_not_see_its_own_request_as_network_supply_opportunity(): void
    {
        $context =
            $this->createOpenContext(
                'SELF'
            );

        $this->actingAs(
            $context['operator']
        )
            ->get(
                '/kdkmp/fallback-network'
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page->has(
                        'requests',
                        0
                    )
            );

        $this->actingAs(
            $context['operator']
        )
            ->get(
                '/kdkmp/fallback-network/'
                .$context['request']->id
            )
            ->assertForbidden();
    }

    public function test_system_admin_and_sppg_have_no_fallback_network_access(): void
    {
        $context =
            $this->createOpenContext(
                'NON-KDKMP'
            );

        $this->actingAs(
            $context['admin']
        )
            ->get(
                '/kdkmp/fallback-network'
            )
            ->assertForbidden();

        $this->actingAs(
            $context['sppgUser']
        )
            ->get(
                '/kdkmp/fallback-network'
            )
            ->assertForbidden();

        $this->actingAs(
            $context['sppgUser']
        )
            ->get(
                '/kdkmp/fallback-network/'
                .$context['request']->id
            )
            ->assertForbidden();
    }

    private function createOpenContext(
        string $suffix
    ): array {
        $context =
            $this->createBaseContext(
                $suffix
            );

        $service =
            app(
                FallbackRequestService::class
            );

        $request =
            $service->createDraft(
                $context['operator'],
                $context['forecast'],
                [
                    'requested_volume' =>
                        '150.000000',

                    'response_deadline_at' =>
                        '2026-08-19 12:00:00',

                    'broadcast_note' =>
                        'Broadcast-safe aggregate note.',
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

        return [
            ...$context,

            'request' =>
                $request,
        ];
    }

    private function createBaseContext(
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
                "SPPG-BROADCAST-{$suffix}"
            );

        $primary =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-BROADCAST-PRIMARY-{$suffix}"
            );

        $network =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-BROADCAST-NETWORK-{$suffix}"
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
                        'Fallback Broadcast HTTP fixture.',
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

    private function createReferenceData(
        string $suffix
    ): array {
        $unit =
            Unit::create([
                'code' =>
                    "KG-BROADCAST-{$suffix}",

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
                    "COM-BROADCAST-{$suffix}",

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