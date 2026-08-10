<?php

namespace Tests\Feature\Commitment;

use App\Enums\CommitmentApprovalStatus;
use App\Enums\CommitmentLifecycleStatus;
use App\Enums\NetworkRole;
use App\Enums\OrganizationType;
use App\Enums\SupplyConfidence;
use App\Enums\UserRole;
use App\Models\Commodity;
use App\Models\CommitmentConfidenceEvent;
use App\Models\CommitmentVersion;
use App\Models\DemandForecast;
use App\Models\ExpectedHarvest;
use App\Models\Organization;
use App\Models\Producer;
use App\Models\SupplyCommitment;
use App\Models\SupplyNetworkLink;
use App\Models\Unit;
use App\Models\User;
use App\Services\Commitment\CommitmentWorkflowService;
use App\Services\Commitment\ConfidenceService;
use App\Services\Forecast\DemandForecastService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SupplyCommitmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_create_commitment_draft_for_own_organization(): void
    {
        $context =
            $this->createOperationalContext(
                'CREATE'
            );

        $response =
            $this
                ->actingAs(
                    $context['operator']
                )
                ->post(
                    '/kdkmp/commitments',
                    $this->commitmentPayload(
                        $context
                    )
                );

        $commitment =
            SupplyCommitment::query()
                ->firstOrFail();

        $version =
            CommitmentVersion::query()
                ->where(
                    'commitment_id',
                    $commitment->id
                )
                ->firstOrFail();

        $response->assertRedirect(
            route(
                'kdkmp.commitments.show',
                $commitment
            )
        );

        $this->assertSame(
            $context['kdkmp']->id,
            $commitment->organization_id
        );

        $this->assertSame(
            $context['forecast']->id,
            $commitment->forecast_id
        );

        $this->assertSame(
            $context['producer']->id,
            $commitment->producer_id
        );

        $this->assertSame(
            $context['expectedHarvest']->id,
            $commitment->expected_harvest_id
        );

        $this->assertSame(
            $context['commodity']->id,
            $commitment->commodity_id
        );

        $this->assertSame(
            CommitmentLifecycleStatus::ACTIVE,
            $commitment->lifecycle_status
        );

        $this->assertNull(
            $commitment->active_version_id
        );

        $this->assertNull(
            $commitment->current_confidence
        );

        $this->assertSame(
            1,
            $version->version_no
        );

        $this->assertSame(
            CommitmentApprovalStatus::DRAFT,
            $version->approval_status
        );

        $this->assertSame(
            '80.000000',
            (string) $version->min_volume
        );

        $this->assertSame(
            '120.000000',
            (string) $version->max_volume
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'entity_id' =>
                    $commitment->id,

                'actor_user_id' =>
                    $context['operator']->id,
            ]
        );
    }

    public function test_inactive_producer_cannot_source_new_commitment(): void
    {
        $context =
            $this->createOperationalContext(
                'INACTIVE'
            );

        $context['producer']->update([
            'is_active' => false,
        ]);

        $this->actingAs(
            $context['operator']
        )
            ->from(
                '/kdkmp/commitments/create'
            )
            ->post(
                '/kdkmp/commitments',
                $this->commitmentPayload(
                    $context
                )
            )
            ->assertSessionHasErrors(
                'producer_id'
            );

        $this->assertDatabaseCount(
            'supply_commitments',
            0
        );

        $this->assertDatabaseCount(
            'commitment_versions',
            0
        );
    }

    public function test_commitment_above_expected_harvest_is_soft_warning_but_requires_justification(): void
    {
        $context =
            $this->createOperationalContext(
                'SOFT-WARNING'
            );

        $payload =
            $this->commitmentPayload(
                $context
            );

        $payload['max_volume'] = 175;
        $payload['operator_justification'] =
            null;

        $this->actingAs(
            $context['operator']
        )
            ->from(
                '/kdkmp/commitments/create'
            )
            ->post(
                '/kdkmp/commitments',
                $payload
            )
            ->assertSessionHasErrors(
                'operator_justification'
            );

        $this->assertDatabaseCount(
            'supply_commitments',
            0
        );

        $payload['operator_justification'] =
            'Tambahan volume berasal dari koordinasi produksi yang belum masuk pembaruan Expected Harvest.';

        $this->actingAs(
            $context['operator']
        )
            ->post(
                '/kdkmp/commitments',
                $payload
            )
            ->assertRedirect();

        $commitment =
            SupplyCommitment::query()
                ->firstOrFail();

        $version =
            $commitment
                ->versions()
                ->firstOrFail();

        $this->assertSame(
            '175.000000',
            (string) $version->max_volume
        );

        $this->assertSame(
            $payload[
                'operator_justification'
            ],
            $version->operator_justification
        );
    }

    public function test_cross_organization_commitment_access_is_blocked_and_manager_cannot_mutate_payload(): void
    {
        $context =
            $this->createOperationalContext(
                'PRIVATE'
            );

        $commitment =
            app(
                CommitmentWorkflowService::class
            )->createDraft(
                $context['operator'],
                $this->commitmentPayload(
                    $context
                )
            );

        $version =
            $commitment
                ->versions()
                ->firstOrFail();

        $otherKdkmp =
            $this->createOrganization(
                OrganizationType::KDKMP,
                'KDKMP-COMMIT-OTHER'
            );

        $otherOperator =
            $this->createKdkmpUser(
                $otherKdkmp,
                UserRole::KDKMP_OPERATOR
            );

        $this->actingAs(
            $otherOperator
        )
            ->get(
                "/kdkmp/commitments/{$commitment->id}"
            )
            ->assertForbidden();

        $this->actingAs(
            $context['manager']
        )
            ->post(
                '/kdkmp/commitments',
                $this->commitmentPayload(
                    $context
                )
            )
            ->assertForbidden();

        $this->actingAs(
            $context['manager']
        )
            ->put(
                "/kdkmp/commitments/{$commitment->id}/versions/{$version->id}",
                [
                    ...$this->draftPayload(
                        $context
                    ),
                    'max_volume' => 999,
                ]
            )
            ->assertForbidden();

        $this->assertSame(
            '120.000000',
            (string) $version
                ->fresh()
                ->max_volume
        );
    }

    public function test_submitted_commitment_can_be_approved_and_initial_approval_creates_green_active_version(): void
    {
        $context =
            $this->createOperationalContext(
                'APPROVE'
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

        $this->actingAs(
            $context['operator']
        )
            ->post(
                "/kdkmp/commitments/{$commitment->id}/versions/{$version->id}/submit"
            )
            ->assertRedirect(
                route(
                    'kdkmp.commitments.show',
                    $commitment
                )
            );

        $version->refresh();

        $this->assertSame(
            CommitmentApprovalStatus
                ::PENDING_APPROVAL,
            $version->approval_status
        );

        $this->assertSame(
            $context['operator']->id,
            $version->submitted_by
        );

        $this->assertNotNull(
            $version->submitted_at
        );

        $this->actingAs(
            $context['manager']
        )
            ->post(
                "/kdkmp/manager/approvals/{$commitment->id}/versions/{$version->id}/approve"
            )
            ->assertRedirect(
                route(
                    'kdkmp.manager.approvals.index'
                )
            );

        $commitment->refresh();
        $version->refresh();

        $this->assertSame(
            CommitmentApprovalStatus
                ::APPROVED,
            $version->approval_status
        );

        $this->assertSame(
            $version->id,
            $commitment->active_version_id
        );

        $this->assertSame(
            SupplyConfidence::GREEN,
            $commitment->current_confidence
        );

        $this->assertNotNull(
            $commitment
                ->last_confidence_verified_at
        );

        $this->assertSame(
            $context['manager']->id,
            $version->reviewed_by
        );

        $this->assertNotNull(
            $version->approved_at
        );

        $event =
            CommitmentConfidenceEvent::query()
                ->where(
                    'commitment_id',
                    $commitment->id
                )
                ->firstOrFail();

        $this->assertNull(
            $event->from_confidence
        );

        $this->assertSame(
            SupplyConfidence::GREEN,
            $event->to_confidence
        );

        $this->assertSame(
            $context['manager']->id,
            $event->actor_user_id
        );
    }

    public function test_maker_checker_blocks_same_user_from_approving_own_commitment(): void
    {
        $context =
            $this->createOperationalContext(
                'MAKER-CHECKER'
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

        /*
         * Simulasikan user yang sama kemudian
         * memperoleh role Manager.
         *
         * Backend maker-checker tetap harus
         * menolak karena actor merupakan maker /
         * submitter version tersebut.
         */
        $context['operator']->update([
            'role' =>
                UserRole::KDKMP_MANAGER,
        ]);

        $sameActor =
            $context['operator']->fresh();

        try {
            $workflow->approve(
                $sameActor,
                $version->fresh()
            );

            $this->fail(
                'Maker berhasil menyetujui Commitment miliknya sendiri.'
            );
        } catch (
            AuthorizationException |
            ValidationException $exception
        ) {
            $this->assertTrue(true);
        }

        $version->refresh();
        $commitment->refresh();

        $this->assertSame(
            CommitmentApprovalStatus
                ::PENDING_APPROVAL,
            $version->approval_status
        );

        $this->assertNull(
            $commitment->active_version_id
        );

        $this->assertNull(
            $commitment->current_confidence
        );
    }

    public function test_approved_payload_is_immutable_and_revision_requires_known_risk(): void
    {
        $context =
            $this->createOperationalContext(
                'REVISION'
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

        $versionOne =
            $commitment
                ->versions()
                ->firstOrFail();

        $workflow->submit(
            $context['operator'],
            $versionOne
        );

        $workflow->approve(
            $context['manager'],
            $versionOne
        );

        $commitment->refresh();
        $versionOne->refresh();

        $this->assertSame(
            SupplyConfidence::GREEN,
            $commitment->current_confidence
        );

        /*
         * Approved Version tidak dapat
         * diedit lagi.
         */
        try {
            $workflow->updateDraft(
                $context['operator'],
                $versionOne,
                [
                    ...$this->draftPayload(
                        $context
                    ),
                    'max_volume' => 180,
                ]
            );

            $this->fail(
                'Approved Commitment Version berhasil diubah.'
            );
        } catch (
            ValidationException |
            AuthorizationException $exception
        ) {
            $this->assertTrue(true);
        }

        $this->assertSame(
            '120.000000',
            (string) $versionOne
                ->fresh()
                ->max_volume
        );

        /*
         * GREEN tidak boleh langsung membuat
         * revision. Risk harus diketahui lebih
         * dahulu.
         */
        try {
            $workflow->createRevision(
                $context['operator'],
                $commitment->fresh(),
                $this->revisionPayload(
                    $context
                )
            );

            $this->fail(
                'Revision dibuat ketika Confidence masih GREEN.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertTrue(true);
        }

        $confidence->downgrade(
            actor:
                $context['operator'],

            commitment:
                $commitment->fresh(),

            toConfidence:
                SupplyConfidence::YELLOW,

            reasonCode:
                'VOLUME_RISK',

            reasonNote:
                'Perkiraan volume aktual menurun dan commitment perlu direvisi.'
        );

        $commitment->refresh();

        $this->assertSame(
            SupplyConfidence::YELLOW,
            $commitment->current_confidence
        );

        $versionTwo =
            $workflow->createRevision(
                $context['operator'],
                $commitment,
                $this->revisionPayload(
                    $context
                )
            );

        $this->assertSame(
            2,
            $versionTwo->version_no
        );

        $this->assertSame(
            CommitmentApprovalStatus::DRAFT,
            $versionTwo->approval_status
        );

        $this->assertSame(
            '110.000000',
            (string)
            $versionTwo->max_volume
        );

        /*
         * Version 1 tidak berubah.
         */
        $this->assertSame(
            '120.000000',
            (string) $versionOne
                ->fresh()
                ->max_volume
        );

        $workflow->submit(
            $context['operator'],
            $versionTwo
        );

        $workflow->approve(
            $context['manager'],
            $versionTwo
        );

        $commitment->refresh();
        $versionTwo->refresh();

        $this->assertSame(
            CommitmentApprovalStatus
                ::APPROVED,
            $versionTwo->approval_status
        );

        $this->assertSame(
            $versionTwo->id,
            $commitment->active_version_id
        );

        /*
         * Revision approval TIDAK boleh
         * memulihkan Confidence secara otomatis.
         */
        $this->assertSame(
            SupplyConfidence::YELLOW,
            $commitment->current_confidence
        );

        $this->assertSame(
            2,
            $commitment
                ->versions()
                ->count()
        );
    }

    public function test_rejected_initial_commitment_can_continue_as_new_version_without_active_version(): void
    {
        $context =
            $this->createOperationalContext(
                'REJECTED'
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

        $versionOne =
            $commitment
                ->versions()
                ->firstOrFail();

        $workflow->submit(
            $context['operator'],
            $versionOne
        );

        $workflow->reject(
            $context['manager'],
            $versionOne,
            'Range belum didukung bukti operasional yang cukup.'
        );

        $versionOne->refresh();
        $commitment->refresh();

        $this->assertSame(
            CommitmentApprovalStatus
                ::REJECTED,
            $versionOne->approval_status
        );

        $this->assertNull(
            $commitment->active_version_id
        );

        $this->assertNull(
            $commitment->current_confidence
        );

        $versionTwo =
            $workflow->createRevision(
                $context['operator'],
                $commitment,
                [
                    ...$this->revisionPayload(
                        $context
                    ),

                    'change_reason' =>
                        'Menindaklanjuti penolakan Manager dengan range yang lebih konservatif.',
                ]
            );

        $this->assertSame(
            2,
            $versionTwo->version_no
        );

        $this->assertSame(
            CommitmentApprovalStatus::DRAFT,
            $versionTwo->approval_status
        );

        $this->assertNull(
            $commitment
                ->fresh()
                ->active_version_id
        );
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
                "SPPG-COMMIT-{$suffix}"
            );

        $kdkmp =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-COMMIT-{$suffix}"
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
                        'Forecast Commitment test',
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
                    "PROD-COMMIT-{$suffix}",

                'name' =>
                    "Produsen {$suffix}",

                'village' =>
                    'Desa Test',

                'district' =>
                    'Kecamatan Test',

                'contact_phone' =>
                    '081234567890',

                'notes' =>
                    'Producer Commitment fixture',

                'is_active' =>
                    true,

                'created_by' =>
                    $operator->id,
            ]);

        $expectedHarvest =
            ExpectedHarvest::create([
                'organization_id' =>
                    $kdkmp->id,

                'producer_id' =>
                    $producer->id,

                'commodity_id' =>
                    $commodity->id,

                'unit_id' =>
                    $unit->id,

                'expected_min_volume' =>
                    90,

                'expected_max_volume' =>
                    150,

                'harvest_start_at' =>
                    '2026-08-20 06:00:00',

                'harvest_end_at' =>
                    '2026-08-20 14:00:00',

                'notes' =>
                    'Expected Harvest Commitment fixture',

                'last_updated_by' =>
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

            'expectedHarvest' =>
                $expectedHarvest,
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
                $context[
                    'expectedHarvest'
                ]->id,

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
                'Supply Commitment test',

            'operator_justification' =>
                null,
        ];
    }

    private function draftPayload(
        array $context
    ): array {
        return [
            'producer_id' =>
                $context['producer']->id,

            'expected_harvest_id' =>
                $context[
                    'expectedHarvest'
                ]->id,

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
                'Updated Commitment test',

            'change_reason' =>
                null,

            'operator_justification' =>
                null,
        ];
    }

    private function revisionPayload(
        array $context
    ): array {
        return [
            'min_volume' =>
                70,

            'max_volume' =>
                110,

            'unit_id' =>
                $context['unit']->id,

            'availability_start_at' =>
                '2026-08-20 08:00:00',

            'availability_end_at' =>
                '2026-08-20 12:00:00',

            'notes' =>
                'Revised Commitment test',

            'change_reason' =>
                'Perubahan kondisi volume aktual.',

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
                    "kg-commit-{$suffix}",

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
                    "BAYAM-COMMIT-{$suffix}",

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