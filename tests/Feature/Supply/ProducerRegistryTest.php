<?php

namespace Tests\Feature\Supply;

use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProducerRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_create_producer_for_own_organization(): void
    {
        $kdkmpA = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-PROD-A'
        );

        $kdkmpB = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-PROD-B'
        );

        $operator = $this->createKdkmpUser(
            $kdkmpA,
            UserRole::KDKMP_OPERATOR
        );

        $otherUser = $this->createKdkmpUser(
            $kdkmpB,
            UserRole::KDKMP_OPERATOR
        );

        $response = $this
            ->actingAs($operator)
            ->post(
                '/kdkmp/producers',
                [
                    ...$this->validPayload(),
                    'organization_id' =>
                        $kdkmpB->id,
                    'created_by' =>
                        $otherUser->id,
                    'is_active' =>
                        false,
                ]
            );

        $producer = Producer::query()
            ->firstOrFail();

        $response->assertRedirect(
            route(
                'kdkmp.producers.show',
                $producer
            )
        );

        $this->assertSame(
            $kdkmpA->id,
            $producer->organization_id
        );

        $this->assertSame(
            $operator->id,
            $producer->created_by
        );

        $this->assertTrue(
            $producer->is_active
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'PRODUCER_CREATED',

                'entity_id' =>
                    $producer->id,

                'actor_user_id' =>
                    $operator->id,

                'actor_organization_id' =>
                    $kdkmpA->id,
            ]
        );
    }

    public function test_producer_code_is_unique_within_organization_only(): void
    {
        $kdkmpA = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-PROD-UNIQ-A'
        );

        $kdkmpB = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-PROD-UNIQ-B'
        );

        $operatorA = $this->createKdkmpUser(
            $kdkmpA,
            UserRole::KDKMP_OPERATOR
        );

        $operatorB = $this->createKdkmpUser(
            $kdkmpB,
            UserRole::KDKMP_OPERATOR
        );

        $payload = $this->validPayload();

        $this->actingAs($operatorA)
            ->post(
                '/kdkmp/producers',
                $payload
            )
            ->assertRedirect();

        $this->actingAs($operatorA)
            ->from(
                '/kdkmp/producers/create'
            )
            ->post(
                '/kdkmp/producers',
                $payload
            )
            ->assertRedirect(
                '/kdkmp/producers/create'
            )
            ->assertSessionHasErrors(
                'producer_code'
            );

        $this->actingAs($operatorB)
            ->post(
                '/kdkmp/producers',
                $payload
            )
            ->assertRedirect();

        $this->assertSame(
            2,
            Producer::query()
                ->where(
                    'producer_code',
                    $payload['producer_code']
                )
                ->count()
        );
    }

    public function test_operator_sees_only_own_organization_producers(): void
    {
        $kdkmpA = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-PROD-VIS-A'
        );

        $kdkmpB = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-PROD-VIS-B'
        );

        $operatorA = $this->createKdkmpUser(
            $kdkmpA,
            UserRole::KDKMP_OPERATOR
        );

        $operatorB = $this->createKdkmpUser(
            $kdkmpB,
            UserRole::KDKMP_OPERATOR
        );

        $producerA = $this->createProducer(
            $kdkmpA,
            $operatorA,
            'PROD-VIS-A'
        );

        $producerB = $this->createProducer(
            $kdkmpB,
            $operatorB,
            'PROD-VIS-B'
        );

        $this->actingAs($operatorA)
            ->get('/kdkmp/producers')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'Kdkmp/Producers/Index'
                        )
                        ->has(
                            'producers',
                            1
                        )
                        ->where(
                            'producers.0.id',
                            $producerA->id
                        )
            );

        $this->actingAs($operatorA)
            ->get(
                "/kdkmp/producers/{$producerA->id}"
            )
            ->assertOk();

        $this->actingAs($operatorA)
            ->get(
                "/kdkmp/producers/{$producerB->id}"
            )
            ->assertForbidden();
    }

    public function test_sppg_and_system_admin_cannot_access_producer_detail(): void
    {
        $kdkmp = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-PROD-PRIVATE'
        );

        $operator = $this->createKdkmpUser(
            $kdkmp,
            UserRole::KDKMP_OPERATOR
        );

        $producer = $this->createProducer(
            $kdkmp,
            $operator,
            'PROD-PRIVATE'
        );

        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-PROD-PRIVATE'
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
                "/kdkmp/producers/{$producer->id}"
            )
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(
                "/kdkmp/producers/{$producer->id}"
            )
            ->assertForbidden();
    }

    public function test_manager_has_read_only_access_to_own_producer_context(): void
    {
        $kdkmp = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-PROD-MGR'
        );

        $operator = $this->createKdkmpUser(
            $kdkmp,
            UserRole::KDKMP_OPERATOR
        );

        $manager = $this->createKdkmpUser(
            $kdkmp,
            UserRole::KDKMP_MANAGER
        );

        $producer = $this->createProducer(
            $kdkmp,
            $operator,
            'PROD-MGR'
        );

        $this->actingAs($manager)
            ->get('/kdkmp/producers')
            ->assertOk();

        $this->actingAs($manager)
            ->get(
                "/kdkmp/producers/{$producer->id}"
            )
            ->assertOk();

        $this->actingAs($manager)
            ->post(
                '/kdkmp/producers',
                $this->validPayload(
                    'PROD-MGR-NEW'
                )
            )
            ->assertForbidden();

        $this->actingAs($manager)
            ->put(
                "/kdkmp/producers/{$producer->id}",
                [
                    ...$this->validPayload(
                        $producer->producer_code
                    ),
                    'name' =>
                        'Manager tidak boleh mengubah',
                ]
            )
            ->assertForbidden();

        $this->actingAs($manager)
            ->patch(
                "/kdkmp/producers/{$producer->id}/active-state",
                [
                    'is_active' => false,
                ]
            )
            ->assertForbidden();

        $this->assertSame(
            'Produsen Test',
            $producer->fresh()->name
        );

        $this->assertTrue(
            $producer->fresh()->is_active
        );
    }

    public function test_operator_can_update_active_producer_and_change_is_audited(): void
    {
        $kdkmp = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-PROD-UPDATE'
        );

        $operator = $this->createKdkmpUser(
            $kdkmp,
            UserRole::KDKMP_OPERATOR
        );

        $producer = $this->createProducer(
            $kdkmp,
            $operator,
            'PROD-UPDATE'
        );

        $this->actingAs($operator)
            ->put(
                "/kdkmp/producers/{$producer->id}",
                [
                    ...$this->validPayload(
                        'PROD-UPDATE'
                    ),
                    'name' =>
                        'Produsen Diperbarui',
                ]
            )
            ->assertRedirect(
                route(
                    'kdkmp.producers.show',
                    $producer
                )
            );

        $producer->refresh();

        $this->assertSame(
            'Produsen Diperbarui',
            $producer->name
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'PRODUCER_UPDATED',

                'entity_id' =>
                    $producer->id,

                'actor_user_id' =>
                    $operator->id,
            ]
        );
    }

    public function test_operator_can_deactivate_and_reactivate_producer_without_deleting_it(): void
    {
        $kdkmp = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-PROD-STATE'
        );

        $operator = $this->createKdkmpUser(
            $kdkmp,
            UserRole::KDKMP_OPERATOR
        );

        $producer = $this->createProducer(
            $kdkmp,
            $operator,
            'PROD-STATE'
        );

        $this->actingAs($operator)
            ->patch(
                "/kdkmp/producers/{$producer->id}/active-state",
                [
                    'is_active' => false,
                ]
            )
            ->assertRedirect();

        $this->assertFalse(
            $producer->fresh()->is_active
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'PRODUCER_DEACTIVATED',

                'entity_id' =>
                    $producer->id,
            ]
        );

        $this->assertDatabaseHas(
            'producers',
            [
                'id' => $producer->id,
            ]
        );

        $this->actingAs($operator)
            ->patch(
                "/kdkmp/producers/{$producer->id}/active-state",
                [
                    'is_active' => true,
                ]
            )
            ->assertRedirect();

        $this->assertTrue(
            $producer->fresh()->is_active
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'PRODUCER_ACTIVATED',

                'entity_id' =>
                    $producer->id,
            ]
        );
    }

    public function test_producer_hard_delete_route_does_not_exist(): void
    {
        $this->assertFalse(
            Route::has(
                'kdkmp.producers.destroy'
            )
        );

        $routes = collect(
            Route::getRoutes()
        )->filter(
            fn ($route) =>
                str_starts_with(
                    $route->uri(),
                    'kdkmp/producers'
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
    }

    private function validPayload(
        string $producerCode = 'PROD-001'
    ): array {
        return [
            'producer_code' => $producerCode,
            'name' => 'Produsen Test',
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'contact_phone' => '081234567890',
            'notes' => 'Producer registry test',
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

            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function createProducer(
        Organization $organization,
        User $creator,
        string $code
    ): Producer {
        return Producer::create([
            'organization_id' =>
                $organization->id,

            'producer_code' =>
                $code,

            'name' =>
                'Produsen Test',

            'village' =>
                'Desa Test',

            'district' =>
                'Kecamatan Test',

            'contact_phone' =>
                '081234567890',

            'notes' =>
                'Producer fixture',

            'is_active' =>
                true,

            'created_by' =>
                $creator->id,
        ]);
    }
}