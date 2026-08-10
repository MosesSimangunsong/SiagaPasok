<?php

namespace Tests\Feature\Commitment;

use App\Enums\CommitmentApprovalStatus;
use App\Enums\NetworkRole;
use App\Enums\OrganizationType;
use App\Enums\RecoveryRequestStatus;
use App\Enums\SupplyConfidence;
use App\Enums\UserRole;
use App\Models\Commodity;
use App\Models\ConfidenceRecoveryRequest;
use App\Models\Organization;
use App\Models\Producer;
use App\Models\SupplyNetworkLink;
use App\Models\Unit;
use App\Models\User;
use App\Services\Commitment\CommitmentWorkflowService;
use App\Services\Commitment\ConfidenceService;
use App\Services\Forecast\DemandForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommitmentHttpContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_approval_queue_contains_only_own_organization_pending_versions(): void
    {
        $contextA =
            $this->createPendingCommitmentContext(
                'QUEUE-A'
            );

        $contextB =
            $this->createPendingCommitmentContext(
                'QUEUE-B'
            );

        $this->actingAs(
            $contextA['manager']
        )
            ->get(
                '/kdkmp/manager/approvals'
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'Kdkmp/Manager/Approvals/Index'
                        )
                        ->has(
                            'versions',
                            1
                        )
                        ->where(
                            'versions.0.id',
                            $contextA[
                                'version'
                            ]->id
                        )
                        ->where(
                            'versions.0.commitment_id',
                            $contextA[
                                'commitment'
                            ]->id
                        )
            );

        $this->actingAs(
            $contextA['manager']
        )
            ->get(
                "/kdkmp/manager/approvals/"
                .$contextB['commitment']->id
                .'/versions/'
                .$contextB['version']->id
            )
            ->assertForbidden();
    }

    public function test_commitment_reject_requires_reason_and_does_not_change_state_when_validation_fails(): void
    {
        $context =
            $this->createPendingCommitmentContext(
                'REJECT-REASON'
            );

        $this->actingAs(
            $context['manager']
        )
            ->from(
                "/kdkmp/manager/approvals/"
                .$context['commitment']->id
                .'/versions/'
                .$context['version']->id
            )
            ->post(
                "/kdkmp/manager/approvals/"
                .$context['commitment']->id
                .'/versions/'
                .$context['version']->id
                .'/reject',
                []
            )
            ->assertSessionHas(
                'errors'
            );

        $version =
            $context['version']
                ->fresh();

        $this->assertSame(
            CommitmentApprovalStatus
                ::PENDING_APPROVAL,
            $version->approval_status
        );

        $this->assertNull(
            $version->reviewed_by
        );

        $this->assertNull(
            $version->reviewed_at
        );

        $this->assertNull(
            $version->review_reason
        );

        $this->assertNull(
            $context['commitment']
                ->fresh()
                ->active_version_id
        );
    }

    public function test_operator_cannot_use_manager_commitment_decision_endpoints(): void
    {
        $context =
            $this->createPendingCommitmentContext(
                'ROLE-SEPARATION'
            );

        $this->actingAs(
            $context['operator']
        )
            ->post(
                "/kdkmp/manager/approvals/"
                .$context['commitment']->id
                .'/versions/'
                .$context['version']->id
                .'/approve'
            )
            ->assertForbidden();

        $this->actingAs(
            $context['operator']
        )
            ->post(
                "/kdkmp/manager/approvals/"
                .$context['commitment']->id
                .'/versions/'
                .$context['version']->id
                .'/reject',
                [
                    'review_reason' =>
                        'Operator tidak boleh mereview.',
                ]
            )
            ->assertForbidden();

        $this->assertSame(
            CommitmentApprovalStatus
                ::PENDING_APPROVAL,
            $context['version']
                ->fresh()
                ->approval_status
        );

        $this->assertNull(
            $context['commitment']
                ->fresh()
                ->active_version_id
        );
    }

    public function test_recovery_queue_contains_only_own_organization_pending_requests(): void
    {
        $contextA =
            $this->createPendingRecoveryContext(
                'RECOVERY-QUEUE-A'
            );

        $contextB =
            $this->createPendingRecoveryContext(
                'RECOVERY-QUEUE-B'
            );

        $this->actingAs(
            $contextA['manager']
        )
            ->get(
                '/kdkmp/manager/recoveries'
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'Kdkmp/Manager/Recoveries/Index'
                        )
                        ->has(
                            'recoveries',
                            1
                        )
                        ->where(
                            'recoveries.0.id',
                            $contextA[
                                'recovery'
                            ]->id
                        )
                        ->where(
                            'recoveries.0.commitment_id',
                            $contextA[
                                'commitment'
                            ]->id
                        )
            );

        $this->actingAs(
            $contextA['manager']
        )
            ->get(
                '/kdkmp/manager/recoveries/'
                .$contextB['recovery']->id
            )
            ->assertForbidden();
    }

    public function test_recovery_reject_requires_reason_and_keeps_commitment_yellow_on_validation_failure(): void
    {
        $context =
            $this->createPendingRecoveryContext(
                'RECOVERY-REASON'
            );

        $this->actingAs(
            $context['manager']
        )
            ->from(
                '/kdkmp/manager/recoveries/'
                .$context['recovery']->id
            )
            ->post(
                '/kdkmp/manager/recoveries/'
                .$context['recovery']->id
                .'/reject',
                []
            )
            ->assertSessionHas(
                'errors'
            );

        $this->assertSame(
            RecoveryRequestStatus
                ::PENDING_APPROVAL,
            $context['recovery']
                ->fresh()
                ->status
        );

        $this->assertSame(
            SupplyConfidence::YELLOW,
            $context['commitment']
                ->fresh()
                ->current_confidence
        );

        $this->assertNull(
            $context['recovery']
                ->fresh()
                ->reviewed_by
        );

        $this->assertNull(
            $context['recovery']
                ->fresh()
                ->review_reason
        );
    }

    public function test_system_admin_has_no_operational_access_to_commitment_workspace(): void
    {
        $context =
            $this->createPendingCommitmentContext(
                'ADMIN-DENIAL'
            );

        $admin =
            User::factory()->create();

        $this->actingAs(
            $admin
        )
            ->get(
                "/kdkmp/commitments/"
                .$context['commitment']->id
            )
            ->assertForbidden();

        $this->actingAs(
            $admin
        )
            ->get(
                '/kdkmp/manager/approvals'
            )
            ->assertForbidden();

        $this->actingAs(
            $admin
        )
            ->post(
                "/kdkmp/manager/approvals/"
                .$context['commitment']->id
                .'/versions/'
                .$context['version']->id
                .'/approve'
            )
            ->assertForbidden();

        $this->assertSame(
            CommitmentApprovalStatus
                ::PENDING_APPROVAL,
            $context['version']
                ->fresh()
                ->approval_status
        );
    }

    public function test_manager_http_surface_has_no_generic_payload_mutation_endpoint(): void
    {
        $managerRoutes =
            collect(
                Route::getRoutes()
            )
                ->filter(
                    fn ($route) =>
                        str_starts_with(
                            $route->uri(),
                            'kdkmp/manager/approvals'
                        )
                        || str_starts_with(
                            $route->uri(),
                            'kdkmp/manager/recoveries'
                        )
                );

        /*
         * Manager review surface hanya:
         *
         * GET review/queue
         * POST explicit approve/reject commands.
         *
         * Tidak ada generic PUT/PATCH payload.
         */
        $this->assertFalse(
            $managerRoutes->contains(
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
                'kdkmp.manager.approvals.approve'
            )
        );

        $this->assertTrue(
            Route::has(
                'kdkmp.manager.approvals.reject'
            )
        );

        $this->assertTrue(
            Route::has(
                'kdkmp.manager.recoveries.approve'
            )
        );

        $this->assertTrue(
            Route::has(
                'kdkmp.manager.recoveries.reject'
            )
        );
    }

    public function test_supply_commitment_has_no_hard_delete_route(): void
    {
        $routes =
            collect(
                Route::getRoutes()
            )
                ->filter(
                    fn ($route) =>
                        str_starts_with(
                            $route->uri(),
                            'kdkmp/commitments'
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
            Route::has(
                'kdkmp.commitments.destroy'
            )
        );
    }

    private function createPendingCommitmentContext(
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

        $version->refresh();

        $this->assertSame(
            CommitmentApprovalStatus
                ::PENDING_APPROVAL,
            $version->approval_status
        );

        return [
            ...$context,

            'commitment' =>
                $commitment->fresh(),

            'version' =>
                $version,
        ];
    }

    private function createPendingRecoveryContext(
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

        $confidence =
            app(
                ConfidenceService::class
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

        $confidence->downgrade(
            actor:
                $context['operator'],

            commitment:
                $commitment,

            toConfidence:
                SupplyConfidence::YELLOW,

            reasonCode:
                'RISK',

            reasonNote:
                'Kondisi supply memerlukan verifikasi ulang.'
        );

        $recovery =
            $confidence->requestRecovery(
                $context['operator'],
                $commitment->fresh(),
                'Kondisi supply telah dikonfirmasi kembali.'
            );

        $this->assertSame(
            RecoveryRequestStatus
                ::PENDING_APPROVAL,
            $recovery->status
        );

        return [
            ...$context,

            'commitment' =>
                $commitment->fresh(),

            'version' =>
                $version->fresh(),

            'recovery' =>
                $recovery,
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
                "SPPG-HTTP-{$suffix}"
            );

        $kdkmp =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-HTTP-{$suffix}"
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
                        'HTTP contract test Forecast',
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
                    "PROD-HTTP-{$suffix}",

                'name' =>
                    "Produsen {$suffix}",

                'village' =>
                    'Desa Test',

                'district' =>
                    'Kecamatan Test',

                'contact_phone' =>
                    '081234567890',

                'notes' =>
                    'HTTP contract fixture',

                'is_active' =>
                    true,

                'created_by' =>
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
                null,

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
                'HTTP contract test Commitment',

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
                    "kg-http-{$suffix}",

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
                    "BAYAM-HTTP-{$suffix}",

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