<?php

namespace Tests\Feature\Fallback;

use App\Enums\CommitmentApprovalStatus;
use App\Enums\CommitmentLifecycleStatus;
use App\Enums\FallbackRequestStatus;
use App\Enums\ForecastStatus;
use App\Enums\NetworkRole;
use App\Enums\OrganizationType;
use App\Enums\SupplyConfidence;
use App\Enums\UserRole;
use App\Models\Commodity;
use App\Models\CommitmentVersion;
use App\Models\DemandForecast;
use App\Models\FallbackRequest;
use App\Models\Organization;
use App\Models\Producer;
use App\Models\SupplyNetworkLink;
use App\Models\Unit;
use App\Models\User;
use App\Services\Commitment\CommitmentWorkflowService;
use App\Services\Supply\SupplyMetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FallbackSourceCommitmentTest extends TestCase
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

    public function test_network_operator_can_create_source_commitment_from_open_request(): void
    {
        $context =
            $this->createContext(
                'CREATE'
            );

        $commitment =
            $this->workflow()
                ->createFallbackSourceDraft(
                    $context[
                        'network_operator'
                    ],
                    $context['request'],
                    $this->sourcePayload(
                        $context
                    )
                );

        $this->assertSame(
            $context['forecast']->id,
            $commitment->forecast_id
        );

        $this->assertSame(
            $context['network']->id,
            $commitment->organization_id
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

        $version =
            CommitmentVersion::query()
                ->where(
                    'commitment_id',
                    $commitment->id
                )
                ->firstOrFail();

        $this->assertSame(
            CommitmentApprovalStatus::DRAFT,
            $version->approval_status
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'FALLBACK_SOURCE_COMMITMENT_CREATED',

                'entity_id' =>
                    $commitment->id,
            ]
        );
    }

    public function test_network_operator_still_cannot_use_normal_direct_create_path(): void
    {
        $context =
            $this->createContext(
                'DIRECT-BLOCK'
            );

        $this->expectException(
            AuthorizationException::class
        );

        $this->workflow()
            ->createDraft(
                $context[
                    'network_operator'
                ],
                [
                    'forecast_id' =>
                        $context['forecast']->id,

                    ...$this->sourcePayload(
                        $context
                    ),
                ]
            );
    }

    public function test_source_commitment_requires_open_fallback_request(): void
    {
        $context =
            $this->createContext(
                'REQUEST-STATE'
            );

        $context['request']->update([
            'status' =>
                FallbackRequestStatus
                    ::CANCELLED,

            'cancelled_at' =>
                now(),

            'cancellation_reason' =>
                'Fixture cancellation',
        ]);

        $this->expectException(
            ValidationException::class
        );

        $this->workflow()
            ->createFallbackSourceDraft(
                $context[
                    'network_operator'
                ],
                $context['request'],
                $this->sourcePayload(
                    $context
                )
            );
    }

    public function test_primary_requester_cannot_use_network_source_entry_path(): void
    {
        $context =
            $this->createContext(
                'REQUESTER-BLOCK'
            );

        $primaryProducer =
            $this->createProducer(
                $context['primary'],
                $context[
                    'primary_operator'
                ],
                'PROD-FS-REQUESTER-BLOCK-PRIMARY'
            );

        $payload =
            $this->sourcePayload(
                $context
            );

        $payload['producer_id'] =
            $primaryProducer->id;

        $this->expectException(
            AuthorizationException::class
        );

        $this->workflow()
            ->createFallbackSourceDraft(
                $context[
                    'primary_operator'
                ],
                $context['request'],
                $payload
            );
    }

    public function test_network_source_commitment_can_complete_standard_approval_workflow(): void
    {
        $context =
            $this->createContext(
                'APPROVAL'
            );

        $workflow =
            $this->workflow();

        $commitment =
            $workflow
                ->createFallbackSourceDraft(
                    $context[
                        'network_operator'
                    ],
                    $context['request'],
                    $this->sourcePayload(
                        $context
                    )
                );

        $version =
            CommitmentVersion::query()
                ->where(
                    'commitment_id',
                    $commitment->id
                )
                ->firstOrFail();

        $version =
            $workflow->submit(
                $context[
                    'network_operator'
                ],
                $version
            );

        $this->assertSame(
            CommitmentApprovalStatus
                ::PENDING_APPROVAL,
            $version->approval_status
        );

        $version =
            $workflow->approve(
                $context[
                    'network_manager'
                ],
                $version
            );

        $commitment->refresh();

        $this->assertSame(
            CommitmentApprovalStatus::APPROVED,
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
    }

    public function test_approved_green_network_source_does_not_enter_direct_safe_supply(): void
    {
        $context =
            $this->createContext(
                'NOT-DIRECT-SAFE'
            );

        $workflow =
            $this->workflow();

        $commitment =
            $workflow
                ->createFallbackSourceDraft(
                    $context[
                        'network_operator'
                    ],
                    $context['request'],
                    $this->sourcePayload(
                        $context,
                        '160.000000'
                    )
                );

        $version =
            CommitmentVersion::query()
                ->where(
                    'commitment_id',
                    $commitment->id
                )
                ->firstOrFail();

        $version =
            $workflow->submit(
                $context[
                    'network_operator'
                ],
                $version
            );

        $workflow->approve(
            $context[
                'network_manager'
            ],
            $version
        );

        $metrics =
            app(
                SupplyMetricsService::class
            )->calculate(
                $context['forecast'],
                CarbonImmutable::parse(
                    '2026-08-10 10:00:00'
                )
            );

        /*
         * NETWORK APPROVED + GREEN tetap 0
         * terhadap Direct Safe.
         *
         * Contribution baru muncul setelah
         * ACCEPTED fallback allocation.
         */
        $this->assertSame(
            '0.000000',
            $metrics->directSafeSupply
        );

        $this->assertSame(
            '0.000000',
            $metrics->fallbackSafeSupply
        );

        $this->assertSame(
            '0.000000',
            $metrics->totalSafeSupply
        );

        $this->assertSame(
            '400.000000',
            $metrics->shortfall
        );
    }

    public function test_other_network_organization_cannot_mutate_supplier_commitment(): void
    {
        $context =
            $this->createContext(
                'CROSS-ORG'
            );

        $workflow =
            $this->workflow();

        $commitment =
            $workflow
                ->createFallbackSourceDraft(
                    $context[
                        'network_operator'
                    ],
                    $context['request'],
                    $this->sourcePayload(
                        $context
                    )
                );

        $version =
            CommitmentVersion::query()
                ->where(
                    'commitment_id',
                    $commitment->id
                )
                ->firstOrFail();

        $otherNetwork =
            $this->createOrganization(
                OrganizationType::KDKMP,
                'KDKMP-FS-CROSS-ORG-OTHER'
            );

        $otherOperator =
            User::factory()->create([
                'organization_id' =>
                    $otherNetwork->id,

                'role' =>
                    UserRole::KDKMP_OPERATOR,

                'is_active' =>
                    true,
            ]);

        SupplyNetworkLink::create([
            'sppg_organization_id' =>
                $context['sppg']->id,

            'kdkmp_organization_id' =>
                $otherNetwork->id,

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

        $workflow->submit(
            $otherOperator,
            $version
        );
    }

    private function workflow(): CommitmentWorkflowService
    {
        return app(
            CommitmentWorkflowService::class
        );
    }

    private function sourcePayload(
        array $context,
        string $minimum = '120.000000',
    ): array {
        return [
            'producer_id' =>
                $context[
                    'network_producer'
                ]->id,

            'expected_harvest_id' =>
                null,

            'min_volume' =>
                $minimum,

            'max_volume' =>
                '180.000000',

            'unit_id' =>
                $context['unit']->id,

            'availability_start_at' =>
                '2026-08-20 08:00:00',

            'availability_end_at' =>
                '2026-08-25 17:00:00',

            'notes' =>
                'Fallback source capacity fixture',

            'operator_justification' =>
                null,
        ];
    }

    private function createContext(
        string $suffix,
    ): array {
        $unit =
            Unit::create([
                'code' =>
                    "KG-FS-{$suffix}",

                'name' =>
                    "Kilogram FS {$suffix}",

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
                    "COM-FS-{$suffix}",

                'name' =>
                    "Commodity FS {$suffix}",

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
                "SPPG-FS-{$suffix}"
            );

        $primary =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-FS-PRIMARY-{$suffix}"
            );

        $network =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-FS-NETWORK-{$suffix}"
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

        $primaryOperator =
            User::factory()->create([
                'organization_id' =>
                    $primary->id,

                'role' =>
                    UserRole::KDKMP_OPERATOR,

                'is_active' =>
                    true,
            ]);

        $networkOperator =
            User::factory()->create([
                'organization_id' =>
                    $network->id,

                'role' =>
                    UserRole::KDKMP_OPERATOR,

                'is_active' =>
                    true,
            ]);

        $networkManager =
            User::factory()->create([
                'organization_id' =>
                    $network->id,

                'role' =>
                    UserRole::KDKMP_MANAGER,

                'is_active' =>
                    true,
            ]);

        $networkProducer =
            $this->createProducer(
                $network,
                $networkOperator,
                "PROD-FS-{$suffix}"
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

        $forecast =
            DemandForecast::create([
                'sppg_organization_id' =>
                    $sppg->id,

                'commodity_id' =>
                    $commodity->id,

                'unit_id' =>
                    $unit->id,

                'forecast_code' =>
                    "FC-FS-{$suffix}",

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

                'published_at' =>
                    '2026-08-10 08:00:00',

                'version' =>
                    1,

                'created_by' =>
                    $sppgUser->id,

                'updated_by' =>
                    $sppgUser->id,
            ]);

        $request =
            FallbackRequest::create([
                'forecast_id' =>
                    $forecast->id,

                'requester_organization_id' =>
                    $primary->id,

                'requested_volume' =>
                    '150.000000',

                'unit_id' =>
                    $unit->id,

                'response_deadline_at' =>
                    '2026-08-19 12:00:00',

                'status' =>
                    FallbackRequestStatus::OPEN,

                'broadcast_note' =>
                    'Aggregate fallback fixture',

                'created_by' =>
                    $primaryOperator->id,

                'submitted_by' =>
                    $primaryOperator->id,

                'submitted_at' =>
                    '2026-08-10 08:30:00',

                'reviewed_at' =>
                    '2026-08-10 09:00:00',

                'opened_at' =>
                    '2026-08-10 09:00:00',
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

            'admin' =>
                $admin,

            'primary_operator' =>
                $primaryOperator,

            'network_operator' =>
                $networkOperator,

            'network_manager' =>
                $networkManager,

            'network_producer' =>
                $networkProducer,

            'forecast' =>
                $forecast,

            'request' =>
                $request,
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
                'Lokasi Test Fallback Source',
        ]);
    }

    private function createProducer(
        Organization $organization,
        User $creator,
        string $code,
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
                'Fallback source producer fixture',

            'is_active' =>
                true,

            'created_by' =>
                $creator->id,
        ]);
    }
}