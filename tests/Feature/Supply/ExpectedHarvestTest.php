<?php

namespace Tests\Feature\Supply;

use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Commodity;
use App\Models\ExpectedHarvest;
use App\Models\Organization;
use App\Models\Producer;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExpectedHarvestTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_create_expected_harvest_for_own_organization(): void
    {
        [$unit, $commodity] =
            $this->createReferenceData();

        $kdkmpA = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-HARV-A'
        );

        $kdkmpB = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-HARV-B'
        );

        $operatorA = $this->createKdkmpUser(
            $kdkmpA,
            UserRole::KDKMP_OPERATOR
        );

        $operatorB = $this->createKdkmpUser(
            $kdkmpB,
            UserRole::KDKMP_OPERATOR
        );

        $producer = $this->createProducer(
            $kdkmpA,
            $operatorA,
            'PROD-HARV-A'
        );

        $response = $this
            ->actingAs($operatorA)
            ->post(
                '/kdkmp/expected-harvests',
                [
                    ...$this->validPayload(
                        $producer,
                        $commodity,
                        $unit
                    ),

                    'organization_id' =>
                        $kdkmpB->id,

                    'last_updated_by' =>
                        $operatorB->id,
                ]
            );

        $expectedHarvest =
            ExpectedHarvest::query()
                ->firstOrFail();

        $response->assertRedirect(
            route(
                'kdkmp.expected-harvests.show',
                $expectedHarvest
            )
        );

        $this->assertSame(
            $kdkmpA->id,
            $expectedHarvest->organization_id
        );

        $this->assertSame(
            $operatorA->id,
            $expectedHarvest->last_updated_by
        );

        $this->assertSame(
            $producer->id,
            $expectedHarvest->producer_id
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'EXPECTED_HARVEST_CREATED',

                'entity_id' =>
                    $expectedHarvest->id,

                'actor_user_id' =>
                    $operatorA->id,

                'actor_organization_id' =>
                    $kdkmpA->id,
            ]
        );
    }

    public function test_expected_min_volume_must_be_greater_than_zero(): void
    {
        [
            $operator,
            $producer,
            $commodity,
            $unit,
        ] = $this->createOperationalContext(
            'MIN'
        );

        $payload = $this->validPayload(
            $producer,
            $commodity,
            $unit
        );

        $payload['expected_min_volume'] = 0;

        $this->actingAs($operator)
            ->from(
                '/kdkmp/expected-harvests/create'
            )
            ->post(
                '/kdkmp/expected-harvests',
                $payload
            )
            ->assertRedirect(
                '/kdkmp/expected-harvests/create'
            )
            ->assertSessionHasErrors(
                'expected_min_volume'
            );

        $this->assertDatabaseCount(
            'expected_harvests',
            0
        );
    }

    public function test_expected_max_volume_cannot_be_lower_than_minimum(): void
    {
        [
            $operator,
            $producer,
            $commodity,
            $unit,
        ] = $this->createOperationalContext(
            'MAX'
        );

        $payload = $this->validPayload(
            $producer,
            $commodity,
            $unit
        );

        $payload['expected_min_volume'] = 100;
        $payload['expected_max_volume'] = 80;

        $this->actingAs($operator)
            ->from(
                '/kdkmp/expected-harvests/create'
            )
            ->post(
                '/kdkmp/expected-harvests',
                $payload
            )
            ->assertSessionHasErrors(
                'expected_max_volume'
            );

        $this->assertDatabaseCount(
            'expected_harvests',
            0
        );
    }

    public function test_harvest_end_cannot_be_before_harvest_start(): void
    {
        [
            $operator,
            $producer,
            $commodity,
            $unit,
        ] = $this->createOperationalContext(
            'WINDOW'
        );

        $payload = $this->validPayload(
            $producer,
            $commodity,
            $unit
        );

        $payload['harvest_start_at'] =
            '2026-08-25 12:00:00';

        $payload['harvest_end_at'] =
            '2026-08-25 08:00:00';

        $this->actingAs($operator)
            ->from(
                '/kdkmp/expected-harvests/create'
            )
            ->post(
                '/kdkmp/expected-harvests',
                $payload
            )
            ->assertSessionHasErrors(
                'harvest_end_at'
            );

        $this->assertDatabaseCount(
            'expected_harvests',
            0
        );
    }

    public function test_operator_cannot_use_producer_from_another_kdkmp(): void
    {
        [$unit, $commodity] =
            $this->createReferenceData(
                'CROSS'
            );

        $kdkmpA = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-HARV-CROSS-A'
        );

        $kdkmpB = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-HARV-CROSS-B'
        );

        $operatorA = $this->createKdkmpUser(
            $kdkmpA,
            UserRole::KDKMP_OPERATOR
        );

        $operatorB = $this->createKdkmpUser(
            $kdkmpB,
            UserRole::KDKMP_OPERATOR
        );

        $producerB = $this->createProducer(
            $kdkmpB,
            $operatorB,
            'PROD-HARV-CROSS-B'
        );

        $this->actingAs($operatorA)
            ->from(
                '/kdkmp/expected-harvests/create'
            )
            ->post(
                '/kdkmp/expected-harvests',
                $this->validPayload(
                    $producerB,
                    $commodity,
                    $unit
                )
            )
            ->assertSessionHasErrors(
                'producer_id'
            );

        $this->assertDatabaseCount(
            'expected_harvests',
            0
        );
    }

    public function test_cross_organization_expected_harvest_detail_is_blocked(): void
    {
        [$unit, $commodity] =
            $this->createReferenceData(
                'PRIVATE'
            );

        $kdkmpA = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-HARV-PRIVATE-A'
        );

        $kdkmpB = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-HARV-PRIVATE-B'
        );

        $operatorA = $this->createKdkmpUser(
            $kdkmpA,
            UserRole::KDKMP_OPERATOR
        );

        $operatorB = $this->createKdkmpUser(
            $kdkmpB,
            UserRole::KDKMP_OPERATOR
        );

        $producerB = $this->createProducer(
            $kdkmpB,
            $operatorB,
            'PROD-HARV-PRIVATE-B'
        );

        $harvestB =
            $this->createExpectedHarvest(
                $kdkmpB,
                $producerB,
                $commodity,
                $unit,
                $operatorB
            );

        $this->actingAs($operatorA)
            ->get(
                "/kdkmp/expected-harvests/{$harvestB->id}"
            )
            ->assertForbidden();
    }

    public function test_inactive_producer_is_excluded_from_create_options_and_cannot_be_used(): void
    {
        [$unit, $commodity] =
            $this->createReferenceData(
                'INACTIVE-P'
            );

        $kdkmp = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-HARV-INACTIVE-P'
        );

        $operator = $this->createKdkmpUser(
            $kdkmp,
            UserRole::KDKMP_OPERATOR
        );

        $activeProducer =
            $this->createProducer(
                $kdkmp,
                $operator,
                'PROD-ACTIVE'
            );

        $inactiveProducer =
            $this->createProducer(
                $kdkmp,
                $operator,
                'PROD-INACTIVE',
                false
            );

        $this->actingAs($operator)
            ->get(
                '/kdkmp/expected-harvests/create'
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'Kdkmp/ExpectedHarvests/Create'
                        )
                        ->has(
                            'producers',
                            1
                        )
                        ->where(
                            'producers.0.id',
                            $activeProducer->id
                        )
            );

        $this->actingAs($operator)
            ->from(
                '/kdkmp/expected-harvests/create'
            )
            ->post(
                '/kdkmp/expected-harvests',
                $this->validPayload(
                    $inactiveProducer,
                    $commodity,
                    $unit
                )
            )
            ->assertSessionHasErrors(
                'producer_id'
            );

        $this->assertDatabaseCount(
            'expected_harvests',
            0
        );
    }

    public function test_inactive_commodity_and_unit_cannot_be_used_for_new_expected_harvest(): void
    {
        [
            $operator,
            $producer,
            $commodity,
            $unit,
        ] = $this->createOperationalContext(
            'INACTIVE-REF'
        );

        $commodity->update([
            'is_active' => false,
        ]);

        $this->actingAs($operator)
            ->from(
                '/kdkmp/expected-harvests/create'
            )
            ->post(
                '/kdkmp/expected-harvests',
                $this->validPayload(
                    $producer,
                    $commodity,
                    $unit
                )
            )
            ->assertSessionHasErrors(
                'commodity_id'
            );

        $commodity->update([
            'is_active' => true,
        ]);

        $unit->update([
            'is_active' => false,
        ]);

        $this->actingAs($operator)
            ->from(
                '/kdkmp/expected-harvests/create'
            )
            ->post(
                '/kdkmp/expected-harvests',
                $this->validPayload(
                    $producer,
                    $commodity,
                    $unit
                )
            )
            ->assertSessionHasErrors(
                'unit_id'
            );

        $this->assertDatabaseCount(
            'expected_harvests',
            0
        );
    }

    public function test_manager_can_read_own_expected_harvest_but_cannot_mutate_it(): void
    {
        [
            $operator,
            $producer,
            $commodity,
            $unit,
            $kdkmp,
        ] = $this->createOperationalContext(
            'MANAGER'
        );

        $manager = $this->createKdkmpUser(
            $kdkmp,
            UserRole::KDKMP_MANAGER
        );

        $harvest =
            $this->createExpectedHarvest(
                $kdkmp,
                $producer,
                $commodity,
                $unit,
                $operator
            );

        $this->actingAs($manager)
            ->get(
                '/kdkmp/expected-harvests'
            )
            ->assertOk();

        $this->actingAs($manager)
            ->get(
                "/kdkmp/expected-harvests/{$harvest->id}"
            )
            ->assertOk();

        $this->actingAs($manager)
            ->post(
                '/kdkmp/expected-harvests',
                $this->validPayload(
                    $producer,
                    $commodity,
                    $unit
                )
            )
            ->assertForbidden();

        $this->actingAs($manager)
            ->put(
                "/kdkmp/expected-harvests/{$harvest->id}",
                [
                    ...$this->validPayload(
                        $producer,
                        $commodity,
                        $unit
                    ),
                    'expected_max_volume' => 999,
                ]
            )
            ->assertForbidden();

        $this->assertSame(
            '150.000000',
            (string) $harvest
                ->fresh()
                ->expected_max_volume
        );
    }

    public function test_sppg_and_system_admin_cannot_access_expected_harvest_detail(): void
    {
        [
            $operator,
            $producer,
            $commodity,
            $unit,
            $kdkmp,
        ] = $this->createOperationalContext(
            'PRIVATE-ACTORS'
        );

        $harvest =
            $this->createExpectedHarvest(
                $kdkmp,
                $producer,
                $commodity,
                $unit,
                $operator
            );

        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-HARV-PRIVATE'
        );

        $sppgUser = User::factory()->create([
            'organization_id' => $sppg->id,
            'role' => UserRole::SPPG_USER,
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'organization_id' => null,
            'role' => UserRole::SYSTEM_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($sppgUser)
            ->get(
                "/kdkmp/expected-harvests/{$harvest->id}"
            )
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(
                "/kdkmp/expected-harvests/{$harvest->id}"
            )
            ->assertForbidden();
    }

    public function test_expected_harvest_update_records_before_and_after_audit_values(): void
    {
        [
            $operator,
            $producer,
            $commodity,
            $unit,
            $kdkmp,
        ] = $this->createOperationalContext(
            'AUDIT'
        );

        $harvest =
            $this->createExpectedHarvest(
                $kdkmp,
                $producer,
                $commodity,
                $unit,
                $operator
            );

        $oldMinimum =
            (string) $harvest
                ->expected_min_volume;

        $newMinimum =
            '120.000000';

        $this->actingAs($operator)
            ->put(
                "/kdkmp/expected-harvests/{$harvest->id}",
                [
                    ...$this->validPayload(
                        $producer,
                        $commodity,
                        $unit
                    ),

                    'expected_min_volume' =>
                        $newMinimum,

                    'expected_max_volume' =>
                        '180.000000',

                    'notes' =>
                        'Estimasi diperbarui dari kunjungan lapangan.',
                ]
            )
            ->assertRedirect(
                route(
                    'kdkmp.expected-harvests.show',
                    $harvest
                )
            );

        $harvest->refresh();

        $this->assertSame(
            $newMinimum,
            (string) $harvest
                ->expected_min_volume
        );

        $this->assertSame(
            $operator->id,
            $harvest->last_updated_by
        );

        $audit = AuditLog::query()
            ->where(
                'entity_id',
                $harvest->id
            )
            ->where(
                'action',
                'EXPECTED_HARVEST_UPDATED'
            )
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            $oldMinimum,
            $audit
                ->previous_value_json[
                    'expected_min_volume'
                ]
        );

        $this->assertSame(
            $newMinimum,
            $audit
                ->new_value_json[
                    'expected_min_volume'
                ]
        );
    }

    public function test_expected_harvest_has_no_approval_or_delete_routes(): void
    {
        $this->assertFalse(
            Route::has(
                'kdkmp.expected-harvests.approve'
            )
        );

        $this->assertFalse(
            Route::has(
                'kdkmp.expected-harvests.reject'
            )
        );

        $this->assertFalse(
            Route::has(
                'kdkmp.expected-harvests.submit'
            )
        );

        $this->assertFalse(
            Route::has(
                'kdkmp.expected-harvests.destroy'
            )
        );

        $routes = collect(
            Route::getRoutes()
        )->filter(
            fn ($route) =>
                str_starts_with(
                    $route->uri(),
                    'kdkmp/expected-harvests'
                )
        );

        $this->assertFalse(
            $routes->contains(
                fn ($route) =>
                    in_array(
                        'DELETE',
                        $route->methods(),
                        true
                    )
            )
        );

        $this->assertFalse(
            $routes->contains(
                fn ($route) =>
                    str_contains(
                        $route->uri(),
                        'approve'
                    )
                    || str_contains(
                        $route->uri(),
                        'reject'
                    )
                    || str_contains(
                        $route->uri(),
                        'submit'
                    )
            )
        );
    }

    public function test_expected_harvest_schema_does_not_store_safe_supply_or_approval_state(): void
    {
        foreach ([
            'safe_supply',
            'safe_volume',
            'committed_volume',
            'approval_status',
            'approved_by',
            'submitted_at',
        ] as $column) {
            $this->assertFalse(
                Schema::hasColumn(
                    'expected_harvests',
                    $column
                ),
                "Unexpected M04 column found: {$column}"
            );
        }
    }

    private function createOperationalContext(
        string $suffix
    ): array {
        [$unit, $commodity] =
            $this->createReferenceData(
                $suffix
            );

        $kdkmp = $this->createOrganization(
            OrganizationType::KDKMP,
            "KDKMP-HARV-{$suffix}"
        );

        $operator = $this->createKdkmpUser(
            $kdkmp,
            UserRole::KDKMP_OPERATOR
        );

        $producer = $this->createProducer(
            $kdkmp,
            $operator,
            "PROD-HARV-{$suffix}"
        );

        return [
            $operator,
            $producer,
            $commodity,
            $unit,
            $kdkmp,
        ];
    }

    private function createReferenceData(
        string $suffix = ''
    ): array {
        $normalized = $suffix !== ''
            ? "-{$suffix}"
            : '';

        $unit = Unit::create([
            'code' =>
                "kg{$normalized}",

            'name' =>
                "Kilogram {$suffix}",

            'symbol' =>
                'kg',

            'decimal_precision' =>
                2,

            'is_active' =>
                true,
        ]);

        $commodity = Commodity::create([
            'code' =>
                "BAYAM{$normalized}",

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

    private function validPayload(
        Producer $producer,
        Commodity $commodity,
        Unit $unit
    ): array {
        return [
            'producer_id' =>
                $producer->id,

            'commodity_id' =>
                $commodity->id,

            'unit_id' =>
                $unit->id,

            'expected_min_volume' =>
                100,

            'expected_max_volume' =>
                150,

            'harvest_start_at' =>
                '2026-08-25 08:00:00',

            'harvest_end_at' =>
                '2026-08-27 17:00:00',

            'notes' =>
                'Expected Harvest test',
        ];
    }

    private function createOrganization(
        OrganizationType $type,
        string $code
    ): Organization {
        return Organization::create([
            'code' => $code,
            'name' => "Organization {$code}",
            'organization_type' => $type,
            'is_active' => true,
            'general_location' => 'Lokasi Test',
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

    private function createProducer(
        Organization $organization,
        User $creator,
        string $code,
        bool $isActive = true
    ): Producer {
        return Producer::create([
            'organization_id' =>
                $organization->id,

            'producer_code' =>
                $code,

            'name' =>
                "Produsen {$code}",

            'village' =>
                'Desa Test',

            'district' =>
                'Kecamatan Test',

            'contact_phone' =>
                '081234567890',

            'notes' =>
                'Producer fixture',

            'is_active' =>
                $isActive,

            'created_by' =>
                $creator->id,
        ]);
    }

    private function createExpectedHarvest(
        Organization $organization,
        Producer $producer,
        Commodity $commodity,
        Unit $unit,
        User $operator
    ): ExpectedHarvest {
        return ExpectedHarvest::create([
            'organization_id' =>
                $organization->id,

            'producer_id' =>
                $producer->id,

            'commodity_id' =>
                $commodity->id,

            'unit_id' =>
                $unit->id,

            'expected_min_volume' =>
                100,

            'expected_max_volume' =>
                150,

            'harvest_start_at' =>
                '2026-08-25 08:00:00',

            'harvest_end_at' =>
                '2026-08-27 17:00:00',

            'notes' =>
                'Expected Harvest fixture',

            'last_updated_by' =>
                $operator->id,
        ]);
    }
}