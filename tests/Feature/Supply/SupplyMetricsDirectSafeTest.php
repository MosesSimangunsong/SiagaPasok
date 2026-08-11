<?php

namespace Tests\Feature\Supply;

use App\Enums\CommitmentApprovalStatus;
use App\Enums\CommitmentLifecycleStatus;
use App\Enums\ForecastStatus;
use App\Enums\NetworkRole;
use App\Enums\OrganizationType;
use App\Enums\SupplyConfidence;
use App\Enums\UserRole;
use App\Models\Commodity;
use App\Models\CommitmentVersion;
use App\Models\DemandForecast;
use App\Models\Organization;
use App\Models\Producer;
use App\Models\SupplyCommitment;
use App\Models\SupplyNetworkLink;
use App\Models\Unit;
use App\Models\User;
use App\Models\ExpectedHarvest;
use Illuminate\Support\Facades\Schema;
use App\Services\Supply\SupplyMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SupplyMetricsDirectSafeTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_safe_sums_minimum_of_eligible_green_commitments_exactly(): void
    {
        $context =
            $this->createContext(
                'SUM'
            );

        $this->createCommitment(
            context: $context,
            minimum: '120.123456',
            maximum: '200.000000',
        );

        $this->createCommitment(
            context: $context,
            minimum: '30.100000',
            maximum: '90.000000',
        );

        $result =
            $this->service()
                ->calculateDirectSafeSupply(
                    $context['forecast'],
                    CarbonImmutable::parse(
                        '2026-08-10 10:00:00'
                    )
                );

        $this->assertSame(
            '150.223456',
            $result
        );
    }

    public function test_yellow_and_red_are_not_direct_safe_supply(): void
    {
        $context =
            $this->createContext(
                'CONF'
            );

        $this->createCommitment(
            context: $context,
            minimum: '50.000000',
            confidence:
                SupplyConfidence::GREEN,
        );

        $this->createCommitment(
            context: $context,
            minimum: '70.000000',
            confidence:
                SupplyConfidence::YELLOW,
        );

        $this->createCommitment(
            context: $context,
            minimum: '80.000000',
            confidence:
                SupplyConfidence::RED,
        );

        $this->assertSame(
            '50.000000',
            $this->service()
                ->calculateDirectSafeSupply(
                    $context['forecast'],
                    CarbonImmutable::parse(
                        '2026-08-10 10:00:00'
                    )
                )
        );
    }

    public function test_network_organization_cannot_be_counted_as_direct_safe_supply(): void
    {
        $context =
            $this->createContext(
                'NETWORK'
            );

        $networkKdkmp =
    $this->createOrganization(
        OrganizationType::KDKMP,
        'KDKMP-M06-NETWORK-SECONDARY'
    );

$networkOperator =
    $this->createUser(
        $networkKdkmp,
        UserRole::KDKMP_OPERATOR
    );

$networkProducer =
    $this->createProducer(
        $networkKdkmp,
        $networkOperator,
        'PROD-M06-NETWORK-SECONDARY'
    );

        SupplyNetworkLink::create([
            'sppg_organization_id' =>
                $context['sppg']->id,

            'kdkmp_organization_id' =>
                $networkKdkmp->id,

            'network_role' =>
                NetworkRole::NETWORK,

            'is_active' =>
                true,

            'configured_by' =>
                $context['admin']->id,
        ]);

        $this->createCommitment(
            context: $context,
            minimum: '25.000000',
        );

        $this->createCommitment(
            context: $context,
            minimum: '100.000000',
            organization: $networkKdkmp,
            producer: $networkProducer,
            creator: $networkOperator,
        );

        $this->assertSame(
            '25.000000',
            $this->service()
                ->calculateDirectSafeSupply(
                    $context['forecast'],
                    CarbonImmutable::parse(
                        '2026-08-10 10:00:00'
                    )
                )
        );
    }

    public function test_only_active_approved_version_is_counted(): void
    {
        $context =
            $this->createContext(
                'VERSION'
            );

        $commitment =
            $this->createLogicalCommitment(
                context: $context,
                confidence:
                    SupplyConfidence::GREEN,
            );

        $oldVersion =
            $this->createVersion(
                commitment: $commitment,
                unit: $context['unit'],
                creator: $context['operator'],
                versionNo: 1,
                minimum: '100.000000',
                maximum: '120.000000',
                approvalStatus:
                    CommitmentApprovalStatus::APPROVED,
            );

        $newVersion =
            $this->createVersion(
                commitment: $commitment,
                unit: $context['unit'],
                creator: $context['operator'],
                versionNo: 2,
                minimum: '40.000000',
                maximum: '60.000000',
                approvalStatus:
                    CommitmentApprovalStatus::APPROVED,
            );

        $commitment->update([
            'active_version_id' =>
                $newVersion->id,
        ]);

        $this->assertNotSame(
            $oldVersion->id,
            $commitment
                ->fresh()
                ->active_version_id
        );

        $this->assertSame(
            '40.000000',
            $this->service()
                ->calculateDirectSafeSupply(
                    $context['forecast'],
                    CarbonImmutable::parse(
                        '2026-08-10 10:00:00'
                    )
                )
        );
    }

    public function test_non_approved_active_version_is_not_counted(): void
    {
        foreach ([
            CommitmentApprovalStatus::DRAFT,
            CommitmentApprovalStatus::PENDING_APPROVAL,
            CommitmentApprovalStatus::REJECTED,
        ] as $index => $status) {
            $context =
                $this->createContext(
                    'APPROVAL-'.$index
                );

            $this->createCommitment(
                context: $context,
                minimum: '100.000000',
                approvalStatus: $status,
            );

            $this->assertSame(
                '0.000000',
                $this->service()
                    ->calculateDirectSafeSupply(
                        $context['forecast'],
                        CarbonImmutable::parse(
                            '2026-08-10 10:00:00'
                        )
                    ),
                "Status {$status->value} tidak boleh masuk Direct Safe."
            );
        }
    }

    public function test_cancelled_and_expired_commitments_are_not_counted(): void
    {
        $context =
            $this->createContext(
                'LIFECYCLE'
            );

        $this->createCommitment(
            context: $context,
            minimum: '50.000000',
            lifecycle:
                CommitmentLifecycleStatus::CANCELLED,
        );

        $this->createCommitment(
            context: $context,
            minimum: '70.000000',
            lifecycle:
                CommitmentLifecycleStatus::EXPIRED,
        );

        $this->assertSame(
            '0.000000',
            $this->service()
                ->calculateDirectSafeSupply(
                    $context['forecast'],
                    CarbonImmutable::parse(
                        '2026-08-10 10:00:00'
                    )
                )
        );
    }

    public function test_future_availability_is_valid_until_its_end_boundary(): void
    {
        $context =
            $this->createContext(
                'TIME'
            );

        $this->createCommitment(
            context: $context,
            minimum: '90.000000',
            availabilityStart:
                '2026-08-20 08:00:00',
            availabilityEnd:
                '2026-08-22 17:00:00',
        );

        /*
         * Future commitment tetap valid untuk
         * forward planning.
         */
        $this->assertSame(
            '90.000000',
            $this->service()
                ->calculateDirectSafeSupply(
                    $context['forecast'],
                    CarbonImmutable::parse(
                        '2026-08-10 10:00:00'
                    )
                )
        );

        /*
         * Equality pada end boundary masih valid.
         */
        $this->assertSame(
            '90.000000',
            $this->service()
                ->calculateDirectSafeSupply(
                    $context['forecast'],
                    CarbonImmutable::parse(
                        '2026-08-22 17:00:00'
                    )
                )
        );

        /*
         * Satu detik setelah availability berakhir
         * sudah tidak aman.
         */
        $this->assertSame(
            '0.000000',
            $this->service()
                ->calculateDirectSafeSupply(
                    $context['forecast'],
                    CarbonImmutable::parse(
                        '2026-08-22 17:00:01'
                    )
                )
        );
    }

    public function test_forecast_revision_can_remove_previously_valid_commitment_from_direct_safe(): void
    {
        $context =
            $this->createContext(
                'REVISION'
            );

        $this->createCommitment(
            context: $context,
            minimum: '75.000000',
            availabilityStart:
                '2026-08-20 08:00:00',
            availabilityEnd:
                '2026-08-22 17:00:00',
        );

        $this->assertSame(
            '75.000000',
            $this->service()
                ->calculateDirectSafeSupply(
                    $context['forecast'],
                    CarbonImmutable::parse(
                        '2026-08-10 10:00:00'
                    )
                )
        );

        /*
         * Simulasikan current persisted Forecast
         * yang telah direvisi SPPG.
         *
         * Availability lama tidak lagi overlap
         * dengan requirement baru.
         */
        $context['forecast']->update([
            'required_start_at' =>
                '2026-08-24 08:00:00',

            'required_end_at' =>
                '2026-08-25 17:00:00',

            'version' =>
                $context['forecast']->version + 1,
        ]);

        /*
         * Service menerima instance lama sekalipun,
         * tetapi harus membaca current DB state.
         */
        $this->assertSame(
            '0.000000',
            $this->service()
                ->calculateDirectSafeSupply(
                    $context['forecast'],
                    CarbonImmutable::parse(
                        '2026-08-10 10:00:00'
                    )
                )
        );
    }

    public function test_wrong_commodity_or_unit_fails_closed(): void
    {
        $context =
            $this->createContext(
                'INTEGRITY'
            );

        $otherUnit =
            Unit::create([
                'code' =>
                    'gram-M06-INTEGRITY',

                'name' =>
                    'Gram M06 Integrity',

                'symbol' =>
                    'g',

                'decimal_precision' =>
                    2,

                'is_active' =>
                    true,
            ]);

        $otherCommodity =
            Commodity::create([
                'code' =>
                    'OTHER-M06-INTEGRITY',

                'name' =>
                    'Komoditas Lain M06',

                'default_unit_id' =>
                    $context['unit']->id,

                'harvest_behavior' =>
                    null,

                'notes' =>
                    null,

                'is_active' =>
                    true,
            ]);

        $this->createCommitment(
            context: $context,
            minimum: '50.000000',
            commodity: $otherCommodity,
        );

        $this->createCommitment(
            context: $context,
            minimum: '70.000000',
            unit: $otherUnit,
        );

        $this->assertSame(
            '0.000000',
            $this->service()
                ->calculateDirectSafeSupply(
                    $context['forecast'],
                    CarbonImmutable::parse(
                        '2026-08-10 10:00:00'
                    )
                )
        );
    }

    public function test_non_published_forecast_is_not_operationally_calculable(): void
    {
        $context =
            $this->createContext(
                'STATUS'
            );

        $context['forecast']->update([
            'status' =>
                ForecastStatus::DRAFT,
        ]);

        $this->expectException(
            ValidationException::class
        );

        $this->service()
            ->calculateDirectSafeSupply(
                $context['forecast'],
                CarbonImmutable::parse(
                    '2026-08-10 10:00:00'
                )
            );
    }


    public function test_yellow_minimum_volume_is_counted_as_at_risk(): void
{
    $context =
        $this->createContext(
            'AT-RISK'
        );

    $this->createCommitment(
        context: $context,
        minimum: '25.123456',
        maximum: '90.000000',
        confidence:
            SupplyConfidence::GREEN,
    );

    $this->createCommitment(
        context: $context,
        minimum: '70.111111',
        maximum: '120.000000',
        confidence:
            SupplyConfidence::YELLOW,
    );

    $this->createCommitment(
        context: $context,
        minimum: '20.222222',
        maximum: '80.000000',
        confidence:
            SupplyConfidence::YELLOW,
    );

    $this->createCommitment(
        context: $context,
        minimum: '500.000000',
        confidence:
            SupplyConfidence::RED,
    );

    $evaluationTime =
        CarbonImmutable::parse(
            '2026-08-10 10:00:00'
        );

    $this->assertSame(
        '25.123456',
        $this->service()
            ->calculateDirectSafeSupply(
                $context['forecast'],
                $evaluationTime
            )
    );

    $this->assertSame(
        '90.333333',
        $this->service()
            ->calculateAtRiskSupply(
                $context['forecast'],
                $evaluationTime
            )
    );
}

public function test_at_risk_uses_minimum_not_maximum_volume(): void
{
    $context =
        $this->createContext(
            'AT-RISK-MIN'
        );

    $this->createCommitment(
        context: $context,
        minimum: '35.000000',
        maximum: '500.000000',
        confidence:
            SupplyConfidence::YELLOW,
    );

    $this->assertSame(
        '35.000000',
        $this->service()
            ->calculateAtRiskSupply(
                $context['forecast'],
                CarbonImmutable::parse(
                    '2026-08-10 10:00:00'
                )
            )
    );
}

public function test_invalid_yellow_commitments_do_not_enter_at_risk(): void
{
    $context =
        $this->createContext(
            'AT-RISK-VALIDITY'
        );

    /*
     * Valid YELLOW.
     */
    $this->createCommitment(
        context: $context,
        minimum: '30.000000',
        confidence:
            SupplyConfidence::YELLOW,
    );

    /*
     * CANCELLED YELLOW.
     */
    $this->createCommitment(
        context: $context,
        minimum: '40.000000',
        confidence:
            SupplyConfidence::YELLOW,
        lifecycle:
            CommitmentLifecycleStatus::CANCELLED,
    );

    /*
     * EXPIRED YELLOW.
     */
    $this->createCommitment(
        context: $context,
        minimum: '50.000000',
        confidence:
            SupplyConfidence::YELLOW,
        lifecycle:
            CommitmentLifecycleStatus::EXPIRED,
    );

    /*
     * Availability sudah berakhir.
     */
    $this->createCommitment(
        context: $context,
        minimum: '60.000000',
        confidence:
            SupplyConfidence::YELLOW,
        availabilityStart:
            '2026-08-01 08:00:00',
        availabilityEnd:
            '2026-08-09 17:00:00',
    );

    $this->assertSame(
        '30.000000',
        $this->service()
            ->calculateAtRiskSupply(
                $context['forecast'],
                CarbonImmutable::parse(
                    '2026-08-10 10:00:00'
                )
            )
    );
}

public function test_non_approved_yellow_active_version_is_not_at_risk(): void
{
    foreach ([
        CommitmentApprovalStatus::DRAFT,
        CommitmentApprovalStatus::PENDING_APPROVAL,
        CommitmentApprovalStatus::REJECTED,
    ] as $index => $status) {
        $context =
            $this->createContext(
                'AT-RISK-APPROVAL-'.$index
            );

        $this->createCommitment(
            context: $context,
            minimum: '100.000000',
            confidence:
                SupplyConfidence::YELLOW,
            approvalStatus:
                $status,
        );

        $this->assertSame(
            '0.000000',
            $this->service()
                ->calculateAtRiskSupply(
                    $context['forecast'],
                    CarbonImmutable::parse(
                        '2026-08-10 10:00:00'
                    )
                ),
            "YELLOW {$status->value} tidak boleh masuk At-Risk."
        );
    }
}

public function test_fallback_safe_is_zero_before_m07_even_when_network_supply_exists(): void
{
    $context =
        $this->createContext(
            'FALLBACK-BOUNDARY'
        );

    /*
     * Direct PRIMARY supply tetap ada.
     */
    $this->createCommitment(
        context: $context,
        minimum: '40.000000',
        confidence:
            SupplyConfidence::GREEN,
    );

    /*
     * Buat organization NETWORK dengan supply
     * yang secara biologis/commitment terlihat
     * tersedia.
     *
     * Ini TIDAK boleh otomatis menjadi fallback.
     */
    $networkKdkmp =
        $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-M06-FALLBACK-NETWORK'
        );

    $networkOperator =
        $this->createUser(
            $networkKdkmp,
            UserRole::KDKMP_OPERATOR
        );

    $networkProducer =
        $this->createProducer(
            $networkKdkmp,
            $networkOperator,
            'PROD-M06-FALLBACK-NETWORK'
        );

    SupplyNetworkLink::create([
        'sppg_organization_id' =>
            $context['sppg']->id,

        'kdkmp_organization_id' =>
            $networkKdkmp->id,

        'network_role' =>
            NetworkRole::NETWORK,

        'is_active' =>
            true,

        'configured_by' =>
            $context['admin']->id,
    ]);

    $this->createCommitment(
        context: $context,
        minimum: '500.000000',
        organization:
            $networkKdkmp,
        producer:
            $networkProducer,
        creator:
            $networkOperator,
        confidence:
            SupplyConfidence::GREEN,
    );

    $evaluationTime =
        CarbonImmutable::parse(
            '2026-08-10 10:00:00'
        );

    $this->assertSame(
        '40.000000',
        $this->service()
            ->calculateDirectSafeSupply(
                $context['forecast'],
                $evaluationTime
            )
    );

    $this->assertSame(
        '0.000000',
        $this->service()
            ->calculateFallbackSafeSupply(
                $context['forecast'],
                $evaluationTime
            )
    );
}

public function test_fallback_boundary_is_only_available_for_published_forecast(): void
{
    $context =
        $this->createContext(
            'FALLBACK-STATUS'
        );

    $context['forecast']->update([
        'status' =>
            ForecastStatus::CANCELLED,

        'cancelled_at' =>
            '2026-08-10 09:00:00',

        'cancellation_reason' =>
            'Fixture cancellation',
    ]);

    $this->expectException(
        ValidationException::class
    );

    $this->service()
        ->calculateFallbackSafeSupply(
            $context['forecast'],
            CarbonImmutable::parse(
                '2026-08-10 10:00:00'
            )
        );
}

public function test_total_safe_equals_direct_plus_fallback(): void
{
    $context =
        $this->createContext(
            'TOTAL-SAFE'
        );

    $this->createCommitment(
        context: $context,
        minimum: '125.123456',
        confidence:
            SupplyConfidence::GREEN,
    );

    $evaluationTime =
        CarbonImmutable::parse(
            '2026-08-10 10:00:00'
        );

    $this->assertSame(
        '125.123456',
        $this->service()
            ->calculateDirectSafeSupply(
                $context['forecast'],
                $evaluationTime
            )
    );

    $this->assertSame(
        '0.000000',
        $this->service()
            ->calculateFallbackSafeSupply(
                $context['forecast'],
                $evaluationTime
            )
    );

    $this->assertSame(
        '125.123456',
        $this->service()
            ->calculateTotalSafeSupply(
                $context['forecast'],
                $evaluationTime
            )
    );
}

public function test_safe_below_demand_creates_shortfall_and_not_surplus(): void
{
    $context =
        $this->createContext(
            'SHORTFALL'
        );

    /*
     * Fixture Demand Target = 400.
     */
    $this->createCommitment(
        context: $context,
        minimum: '150.000000',
        confidence:
            SupplyConfidence::GREEN,
    );

    $evaluationTime =
        CarbonImmutable::parse(
            '2026-08-10 10:00:00'
        );

    $this->assertSame(
        '150.000000',
        $this->service()
            ->calculateTotalSafeSupply(
                $context['forecast'],
                $evaluationTime
            )
    );

    $this->assertSame(
        '250.000000',
        $this->service()
            ->calculateShortfall(
                $context['forecast'],
                $evaluationTime
            )
    );

    $this->assertSame(
        '0.000000',
        $this->service()
            ->calculateSurplus(
                $context['forecast'],
                $evaluationTime
            )
    );

    $this->assertFalse(
        $this->service()
            ->calculateVolumeReady(
                $context['forecast'],
                $evaluationTime
            )
    );
}

public function test_safe_equal_demand_is_volume_ready_with_zero_shortfall_and_surplus(): void
{
    $context =
        $this->createContext(
            'EXACT-DEMAND'
        );

    $this->createCommitment(
        context: $context,
        minimum: '400.000000',
        confidence:
            SupplyConfidence::GREEN,
    );

    $evaluationTime =
        CarbonImmutable::parse(
            '2026-08-10 10:00:00'
        );

    $this->assertSame(
        '400.000000',
        $this->service()
            ->calculateTotalSafeSupply(
                $context['forecast'],
                $evaluationTime
            )
    );

    $this->assertSame(
        '0.000000',
        $this->service()
            ->calculateShortfall(
                $context['forecast'],
                $evaluationTime
            )
    );

    $this->assertSame(
        '0.000000',
        $this->service()
            ->calculateSurplus(
                $context['forecast'],
                $evaluationTime
            )
    );

    $this->assertTrue(
        $this->service()
            ->calculateVolumeReady(
                $context['forecast'],
                $evaluationTime
            )
    );
}

public function test_safe_above_demand_creates_surplus_and_is_volume_ready(): void
{
    $context =
        $this->createContext(
            'SURPLUS'
        );

    $this->createCommitment(
        context: $context,
        minimum: '450.250000',
        confidence:
            SupplyConfidence::GREEN,
    );

    $evaluationTime =
        CarbonImmutable::parse(
            '2026-08-10 10:00:00'
        );

    $this->assertSame(
        '450.250000',
        $this->service()
            ->calculateTotalSafeSupply(
                $context['forecast'],
                $evaluationTime
            )
    );

    $this->assertSame(
        '0.000000',
        $this->service()
            ->calculateShortfall(
                $context['forecast'],
                $evaluationTime
            )
    );

    $this->assertSame(
        '50.250000',
        $this->service()
            ->calculateSurplus(
                $context['forecast'],
                $evaluationTime
            )
    );

    $this->assertTrue(
        $this->service()
            ->calculateVolumeReady(
                $context['forecast'],
                $evaluationTime
            )
    );
}

public function test_at_risk_supply_does_not_reduce_shortfall(): void
{
    $context =
        $this->createContext(
            'AT-RISK-SHORTFALL'
        );

    /*
     * Demand = 400.
     *
     * GREEN = 100 Safe.
     * YELLOW = 250 At-Risk.
     *
     * Shortfall harus tetap 300,
     * bukan 50.
     */
    $this->createCommitment(
        context: $context,
        minimum: '100.000000',
        confidence:
            SupplyConfidence::GREEN,
    );

    $this->createCommitment(
        context: $context,
        minimum: '250.000000',
        confidence:
            SupplyConfidence::YELLOW,
    );

    $evaluationTime =
        CarbonImmutable::parse(
            '2026-08-10 10:00:00'
        );

    $this->assertSame(
        '250.000000',
        $this->service()
            ->calculateAtRiskSupply(
                $context['forecast'],
                $evaluationTime
            )
    );

    $this->assertSame(
        '100.000000',
        $this->service()
            ->calculateTotalSafeSupply(
                $context['forecast'],
                $evaluationTime
            )
    );

    $this->assertSame(
        '300.000000',
        $this->service()
            ->calculateShortfall(
                $context['forecast'],
                $evaluationTime
            )
    );
}

public function test_demand_revision_immediately_changes_derived_metrics(): void
{
    $context =
        $this->createContext(
            'DEMAND-REVISION'
        );

    /*
     * Initial Demand = 400.
     * Safe = 300.
     */
    $this->createCommitment(
        context: $context,
        minimum: '300.000000',
        confidence:
            SupplyConfidence::GREEN,
    );

    $evaluationTime =
        CarbonImmutable::parse(
            '2026-08-10 10:00:00'
        );

    $this->assertSame(
        '100.000000',
        $this->service()
            ->calculateShortfall(
                $context['forecast'],
                $evaluationTime
            )
    );

    $this->assertFalse(
        $this->service()
            ->calculateVolumeReady(
                $context['forecast'],
                $evaluationTime
            )
    );

    /*
     * SPPG merevisi target.
     *
     * Caller tetap boleh memegang instance lama;
     * service harus membaca current persisted
     * Forecast.
     */
    $context['forecast']->update([
        'target_volume' =>
            '250.000000',

        'version' =>
            $context['forecast']->version + 1,
    ]);

    $this->assertSame(
        '0.000000',
        $this->service()
            ->calculateShortfall(
                $context['forecast'],
                $evaluationTime
            )
    );

    $this->assertSame(
        '50.000000',
        $this->service()
            ->calculateSurplus(
                $context['forecast'],
                $evaluationTime
            )
    );

    $this->assertTrue(
        $this->service()
            ->calculateVolumeReady(
                $context['forecast'],
                $evaluationTime
            )
    );
}

public function test_volume_ready_uses_exact_six_decimal_comparison(): void
{
    $context =
        $this->createContext(
            'READY-PRECISION'
        );

    /*
     * Demand = 400.000000.
     *
     * Kekurangan hanya 0.000001 tetap harus
     * menghasilkan NOT READY.
     */
    $this->createCommitment(
        context: $context,
        minimum: '399.999999',
        confidence:
            SupplyConfidence::GREEN,
    );

    $evaluationTime =
        CarbonImmutable::parse(
            '2026-08-10 10:00:00'
        );

    $this->assertSame(
        '0.000001',
        $this->service()
            ->calculateShortfall(
                $context['forecast'],
                $evaluationTime
            )
    );

    $this->assertFalse(
        $this->service()
            ->calculateVolumeReady(
                $context['forecast'],
                $evaluationTime
            )
    );
}

public function test_coverage_is_proportional_when_safe_is_below_demand(): void
{
    $context =
        $this->createContext(
            'COVERAGE'
        );

    /*
     * Demand fixture = 400.
     * Safe = 125.
     *
     * Coverage = 31.25%.
     */
    $this->createCommitment(
        context: $context,
        minimum: '125.000000',
        confidence:
            SupplyConfidence::GREEN,
    );

    $this->assertSame(
        '31.25',
        $this->service()
            ->calculateCoveragePercent(
                $context['forecast'],
                CarbonImmutable::parse(
                    '2026-08-10 10:00:00'
                )
            )
    );
}

public function test_zero_safe_supply_has_zero_coverage(): void
{
    $context =
        $this->createContext(
            'COVERAGE-ZERO'
        );

    $this->assertSame(
        '0.00',
        $this->service()
            ->calculateCoveragePercent(
                $context['forecast'],
                CarbonImmutable::parse(
                    '2026-08-10 10:00:00'
                )
            )
    );
}

public function test_coverage_is_capped_at_one_hundred_when_supply_exceeds_demand(): void
{
    $context =
        $this->createContext(
            'COVERAGE-CAP'
        );

    /*
     * Demand = 400.
     * Safe = 500.
     *
     * Coverage tetap 100.00.
     * Surplus membawa informasi kelebihan supply.
     */
    $this->createCommitment(
        context: $context,
        minimum: '500.000000',
        confidence:
            SupplyConfidence::GREEN,
    );

    $evaluationTime =
        CarbonImmutable::parse(
            '2026-08-10 10:00:00'
        );

    $this->assertSame(
        '100.00',
        $this->service()
            ->calculateCoveragePercent(
                $context['forecast'],
                $evaluationTime
            )
    );

    $this->assertSame(
        '100.000000',
        $this->service()
            ->calculateSurplus(
                $context['forecast'],
                $evaluationTime
            )
    );

    $this->assertTrue(
        $this->service()
            ->calculateVolumeReady(
                $context['forecast'],
                $evaluationTime
            )
    );
}

public function test_coverage_uses_deterministic_half_up_rounding(): void
{
    $context =
        $this->createContext(
            'COVERAGE-ROUND'
        );

    /*
     * Ubah Demand menjadi 3.
     * Safe = 2.
     *
     * 2 / 3 * 100
     * = 66.666...
     * → 66.67
     */
    $context['forecast']->update([
        'target_volume' =>
            '3.000000',
    ]);

    $this->createCommitment(
        context: $context,
        minimum: '2.000000',
        maximum: '2.000000',
        confidence:
            SupplyConfidence::GREEN,
    );

    $this->assertSame(
        '66.67',
        $this->service()
            ->calculateCoveragePercent(
                $context['forecast'],
                CarbonImmutable::parse(
                    '2026-08-10 10:00:00'
                )
            )
    );
}

public function test_rounded_coverage_cannot_make_volume_ready_true(): void
{
    $context =
        $this->createContext(
            'COVERAGE-READY'
        );

    /*
     * Demand = 400.000000
     * Safe   = 399.999999
     *
     * Percentage secara presentation dapat
     * membulat menjadi 100.00.
     *
     * Tetapi exact quantity masih kurang
     * 0.000001 sehingga Volume Ready harus FALSE.
     */
    $this->createCommitment(
        context: $context,
        minimum: '399.999999',
        maximum: '399.999999',
        confidence:
            SupplyConfidence::GREEN,
    );

    $evaluationTime =
        CarbonImmutable::parse(
            '2026-08-10 10:00:00'
        );

    $this->assertSame(
        '100.00',
        $this->service()
            ->calculateCoveragePercent(
                $context['forecast'],
                $evaluationTime
            )
    );

    $this->assertSame(
        '0.000001',
        $this->service()
            ->calculateShortfall(
                $context['forecast'],
                $evaluationTime
            )
    );

    $this->assertFalse(
        $this->service()
            ->calculateVolumeReady(
                $context['forecast'],
                $evaluationTime
            )
    );
}

public function test_zero_demand_returns_null_coverage_defensively(): void
{
    $context =
        $this->createContext(
            'COVERAGE-NO-DEMAND'
        );

    /*
     * Normal application flow tidak mengizinkan
     * Demand <= 0.
     *
     * Test ini hanya membuktikan defensive
     * behaviour jika persisted data rusak.
     */
    $context['forecast']->update([
        'target_volume' =>
            '0.000000',
    ]);

    $this->assertNull(
        $this->service()
            ->calculateCoveragePercent(
                $context['forecast'],
                CarbonImmutable::parse(
                    '2026-08-10 10:00:00'
                )
            )
    );
}


public function test_primary_with_positive_direct_safe_supply_is_contributor(): void
{
    $context =
        $this->createContext(
            'CONTRIBUTOR'
        );

    $this->createCommitment(
        context: $context,
        minimum: '80.000000',
        confidence:
            SupplyConfidence::GREEN,
    );

    $contributors =
        $this->service()
            ->calculateContributorOrganizationIds(
                $context['forecast'],
                CarbonImmutable::parse(
                    '2026-08-10 10:00:00'
                )
            );

    $this->assertSame(
        [
            $context[
                'primary_kdkmp'
            ]->id,
        ],
        $contributors
    );
}

public function test_multiple_safe_commitments_from_same_primary_create_only_one_contributor(): void
{
    $context =
        $this->createContext(
            'CONTRIBUTOR-UNIQUE'
        );

    $this->createCommitment(
        context: $context,
        minimum: '40.000000',
        confidence:
            SupplyConfidence::GREEN,
    );

    $this->createCommitment(
        context: $context,
        minimum: '60.000000',
        confidence:
            SupplyConfidence::GREEN,
    );

    $this->createCommitment(
        context: $context,
        minimum: '20.000000',
        confidence:
            SupplyConfidence::GREEN,
    );

    $contributors =
        $this->service()
            ->calculateContributorOrganizationIds(
                $context['forecast'],
                CarbonImmutable::parse(
                    '2026-08-10 10:00:00'
                )
            );

    $this->assertCount(
        1,
        $contributors
    );

    $this->assertSame(
        $context[
            'primary_kdkmp'
        ]->id,
        $contributors[0]
    );
}

public function test_yellow_only_primary_is_not_contributor(): void
{
    $context =
        $this->createContext(
            'CONTRIBUTOR-YELLOW'
        );

    $this->createCommitment(
        context: $context,
        minimum: '300.000000',
        confidence:
            SupplyConfidence::YELLOW,
    );

    $evaluationTime =
        CarbonImmutable::parse(
            '2026-08-10 10:00:00'
        );

    $this->assertSame(
        '300.000000',
        $this->service()
            ->calculateAtRiskSupply(
                $context['forecast'],
                $evaluationTime
            )
    );

    $this->assertSame(
        [],
        $this->service()
            ->calculateContributorOrganizationIds(
                $context['forecast'],
                $evaluationTime
            )
    );
}

public function test_red_cancelled_and_expired_supply_do_not_create_contributor(): void
{
    $context =
        $this->createContext(
            'CONTRIBUTOR-INVALID'
        );

    $this->createCommitment(
        context: $context,
        minimum: '100.000000',
        confidence:
            SupplyConfidence::RED,
    );

    $this->createCommitment(
        context: $context,
        minimum: '100.000000',
        confidence:
            SupplyConfidence::GREEN,
        lifecycle:
            CommitmentLifecycleStatus::CANCELLED,
    );

    $this->createCommitment(
        context: $context,
        minimum: '100.000000',
        confidence:
            SupplyConfidence::GREEN,
        lifecycle:
            CommitmentLifecycleStatus::EXPIRED,
    );

    $this->assertSame(
        [],
        $this->service()
            ->calculateContributorOrganizationIds(
                $context['forecast'],
                CarbonImmutable::parse(
                    '2026-08-10 10:00:00'
                )
            )
    );
}

public function test_network_green_supply_is_not_contributor_before_accepted_fallback_exists(): void
{
    $context =
        $this->createContext(
            'CONTRIBUTOR-NETWORK'
        );

    $networkKdkmp =
        $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-M06-CONTRIBUTOR-NETWORK-SECONDARY'
        );

    $networkOperator =
        $this->createUser(
            $networkKdkmp,
            UserRole::KDKMP_OPERATOR
        );

    $networkProducer =
        $this->createProducer(
            $networkKdkmp,
            $networkOperator,
            'PROD-M06-CONTRIBUTOR-NETWORK-SECONDARY'
        );

    SupplyNetworkLink::create([
        'sppg_organization_id' =>
            $context['sppg']->id,

        'kdkmp_organization_id' =>
            $networkKdkmp->id,

        'network_role' =>
            NetworkRole::NETWORK,

        'is_active' =>
            true,

        'configured_by' =>
            $context['admin']->id,
    ]);

    $this->createCommitment(
        context: $context,
        minimum: '500.000000',
        organization:
            $networkKdkmp,
        producer:
            $networkProducer,
        creator:
            $networkOperator,
        confidence:
            SupplyConfidence::GREEN,
    );

    $this->assertSame(
        [],
        $this->service()
            ->calculateContributorOrganizationIds(
                $context['forecast'],
                CarbonImmutable::parse(
                    '2026-08-10 10:00:00'
                )
            )
    );
}

public function test_zero_effective_safe_supply_does_not_create_contributor(): void
{
    $context =
        $this->createContext(
            'CONTRIBUTOR-ZERO'
        );

    /*
     * Simulasi corrupted persisted quantity.
     * Normal M05 workflow tidak mengizinkan min 0,
     * tetapi calculator tetap harus menggunakan
     * rule contribution > 0.
     */
    $this->createCommitment(
        context: $context,
        minimum: '0.000000',
        maximum: '1.000000',
        confidence:
            SupplyConfidence::GREEN,
    );

    $this->assertSame(
        [],
        $this->service()
            ->calculateContributorOrganizationIds(
                $context['forecast'],
                CarbonImmutable::parse(
                    '2026-08-10 10:00:00'
                )
            )
    );
}

public function test_calculate_returns_complete_canonical_supply_metrics_result(): void
{
    $context =
        $this->createContext(
            'CANONICAL'
        );

    /*
     * Demand = 400.
     *
     * GREEN:
     * 100 + 50 = 150 Safe.
     *
     * YELLOW:
     * 75 At-Risk.
     *
     * Fallback M06:
     * 0.
     */
    $this->createCommitment(
        context: $context,
        minimum: '100.000000',
        confidence:
            SupplyConfidence::GREEN,
    );

    $this->createCommitment(
        context: $context,
        minimum: '50.000000',
        confidence:
            SupplyConfidence::GREEN,
    );

    $this->createCommitment(
        context: $context,
        minimum: '75.000000',
        confidence:
            SupplyConfidence::YELLOW,
    );

    $evaluationTime =
        CarbonImmutable::parse(
            '2026-08-10 10:00:00'
        );

    $result =
        $this->service()
            ->calculate(
                $context['forecast'],
                $evaluationTime
            );

    $this->assertSame(
        $context['forecast']->id,
        $result->forecastId
    );

    $this->assertSame(
        $context['unit']->id,
        $result->unitId
    );

    $this->assertSame(
        '400.000000',
        $result->demandTarget
    );

    $this->assertSame(
        '150.000000',
        $result->directSafeSupply
    );

    $this->assertSame(
        '75.000000',
        $result->atRiskSupply
    );

    $this->assertSame(
        '0.000000',
        $result->fallbackSafeSupply
    );

    $this->assertSame(
        '150.000000',
        $result->totalSafeSupply
    );

    $this->assertSame(
        '37.50',
        $result->coveragePercent
    );

    $this->assertSame(
        '250.000000',
        $result->shortfall
    );

    $this->assertSame(
        '0.000000',
        $result->surplus
    );

    $this->assertSame(
        [
            $context[
                'primary_kdkmp'
            ]->id,
        ],
        $result
            ->contributorOrganizationIds
    );

    $this->assertFalse(
        $result->volumeReady
    );

    $this->assertTrue(
        $result->evaluatedAt
            ->equalTo(
                $evaluationTime
            )
    );
}

public function test_canonical_result_array_uses_stable_external_field_names(): void
{
    $context =
        $this->createContext(
            'CANONICAL-ARRAY'
        );

    $this->createCommitment(
        context: $context,
        minimum: '400.000000',
        confidence:
            SupplyConfidence::GREEN,
    );

    $result =
        $this->service()
            ->calculate(
                $context['forecast'],
                CarbonImmutable::parse(
                    '2026-08-10 10:00:00'
                )
            )
            ->toArray();

    $this->assertSame(
        [
    'forecast_id',
    'evaluated_at',
    'unit_id',
    'demand_target',
    'direct_safe_supply',
    'at_risk_supply',
    'fallback_safe_supply',
    'total_safe_supply',
    'coverage_percent',
    'shortfall',
    'surplus',
    'contributor_organization_ids',
    'contributor_safe_supply_by_organization',
    'volume_ready',
],
        array_keys($result)
    );

    $this->assertSame(
        '400.000000',
        $result[
            'total_safe_supply'
        ]
    );

    $this->assertSame(
        '100.00',
        $result[
            'coverage_percent'
        ]
    );

    $this->assertSame(
        '0.000000',
        $result['shortfall']
    );

    $this->assertSame(
        '0.000000',
        $result['surplus']
    );

    $this->assertTrue(
        $result['volume_ready']
    );
}

public function test_canonical_result_does_not_expose_producer_level_identity(): void
{
    $context =
        $this->createContext(
            'CANONICAL-PRIVACY'
        );

    $this->createCommitment(
        context: $context,
        minimum: '100.000000',
        confidence:
            SupplyConfidence::GREEN,
    );

    $result =
        $this->service()
            ->calculate(
                $context['forecast'],
                CarbonImmutable::parse(
                    '2026-08-10 10:00:00'
                )
            )
            ->toArray();

    $this->assertArrayNotHasKey(
        'producer_id',
        $result
    );

    $this->assertArrayNotHasKey(
        'producers',
        $result
    );

    $this->assertArrayNotHasKey(
        'commitments',
        $result
    );

    $this->assertSame(
        [
            $context[
                'primary_kdkmp'
            ]->id,
        ],
        $result[
            'contributor_organization_ids'
        ]
    );
}

public function test_red_contributes_to_neither_direct_safe_nor_at_risk(): void
{
    $context =
        $this->createContext(
            'EXIT-RED'
        );

    $this->createCommitment(
        context: $context,
        minimum: '350.000000',
        confidence:
            SupplyConfidence::RED,
    );

    $evaluationTime =
        CarbonImmutable::parse(
            '2026-08-10 10:00:00'
        );

    $result =
        $this->service()
            ->calculate(
                $context['forecast'],
                $evaluationTime
            );

    $this->assertSame(
        '0.000000',
        $result->directSafeSupply
    );

    $this->assertSame(
        '0.000000',
        $result->atRiskSupply
    );

    $this->assertSame(
        '0.000000',
        $result->totalSafeSupply
    );

    $this->assertSame(
        [],
        $result
            ->contributorOrganizationIds
    );
}

public function test_expected_harvest_never_changes_safe_supply(): void
{
    $context =
        $this->createContext(
            'EXIT-HARVEST'
        );

    $harvest =
        ExpectedHarvest::create([
            'organization_id' =>
                $context[
                    'primary_kdkmp'
                ]->id,

            'producer_id' =>
                $context['producer']->id,

            'commodity_id' =>
                $context['commodity']->id,

            'unit_id' =>
                $context['unit']->id,

            'expected_min_volume' =>
                '10.000000',

            'expected_max_volume' =>
                '20.000000',

            'harvest_start_at' =>
                '2026-08-20 08:00:00',

            'harvest_end_at' =>
                '2026-08-25 17:00:00',

            'notes' =>
                'M06 Expected Harvest exclusion fixture',

            'last_updated_by' =>
                $context['operator']->id,
        ]);

    $commitment =
        $this->createCommitment(
            context: $context,
            minimum: '75.000000',
            maximum: '100.000000',
            confidence:
                SupplyConfidence::GREEN,
        );

    /*
     * Expected Harvest hanya traceability/context.
     */
    $commitment->update([
        'expected_harvest_id' =>
            $harvest->id,
    ]);

    $evaluationTime =
        CarbonImmutable::parse(
            '2026-08-10 10:00:00'
        );

    $this->assertSame(
        '75.000000',
        $this->service()
            ->calculate(
                $context['forecast'],
                $evaluationTime
            )
            ->totalSafeSupply
    );

    /*
     * Ubah Expected Harvest secara ekstrem.
     *
     * Safe Supply tidak boleh ikut berubah karena
     * numeric source tetap active Commitment Version.
     */
    $harvest->update([
        'expected_min_volume' =>
            '900.000000',

        'expected_max_volume' =>
            '1200.000000',
    ]);

    $result =
        $this->service()
            ->calculate(
                $context['forecast'],
                $evaluationTime
            );

    $this->assertSame(
        '75.000000',
        $result->directSafeSupply
    );

    $this->assertSame(
        '75.000000',
        $result->totalSafeSupply
    );
}

public function test_required_end_boundary_is_inclusive_then_expires(): void
{
    $context =
        $this->createContext(
            'EXIT-REQUIRED-END'
        );

    /*
     * Availability lebih panjang dari
     * Forecast requirement.
     *
     * Jadi test ini secara spesifik menguji
     * Forecast required_end_at.
     */
    $this->createCommitment(
        context: $context,
        minimum: '100.000000',
        confidence:
            SupplyConfidence::GREEN,
        availabilityStart:
            '2026-08-20 08:00:00',
        availabilityEnd:
            '2026-08-30 17:00:00',
    );

    /*
     * Fixture Forecast required_end_at:
     * 2026-08-25 17:00:00
     *
     * Equality masih valid.
     */
    $this->assertSame(
        '100.000000',
        $this->service()
            ->calculate(
                $context['forecast'],
                CarbonImmutable::parse(
                    '2026-08-25 17:00:00'
                )
            )
            ->directSafeSupply
    );

    /*
     * Satu detik setelah Forecast boundary,
     * operational contribution = 0.
     */
    $result =
        $this->service()
            ->calculate(
                $context['forecast'],
                CarbonImmutable::parse(
                    '2026-08-25 17:00:01'
                )
            );

    $this->assertSame(
        '0.000000',
        $result->directSafeSupply
    );

    $this->assertSame(
        '400.000000',
        $result->shortfall
    );

    $this->assertFalse(
        $result->volumeReady
    );
}

public function test_inactive_primary_network_fails_closed(): void
{
    $context =
        $this->createContext(
            'EXIT-INACTIVE-PRIMARY'
        );

    $this->createCommitment(
        context: $context,
        minimum: '400.000000',
        confidence:
            SupplyConfidence::GREEN,
    );

    SupplyNetworkLink::query()
        ->where(
            'sppg_organization_id',
            $context['sppg']->id
        )
        ->where(
            'network_role',
            NetworkRole::PRIMARY->value
        )
        ->update([
            'is_active' => false,
        ]);

    $result =
        $this->service()
            ->calculate(
                $context['forecast'],
                CarbonImmutable::parse(
                    '2026-08-10 10:00:00'
                )
            );

    $this->assertSame(
        '0.000000',
        $result->directSafeSupply
    );

    $this->assertSame(
        '0.000000',
        $result->atRiskSupply
    );

    $this->assertSame(
        [],
        $result
            ->contributorOrganizationIds
    );

    $this->assertSame(
        '400.000000',
        $result->shortfall
    );

    $this->assertFalse(
        $result->volumeReady
    );
}

public function test_multiple_active_primary_links_fail_closed(): void
{
    $context =
        $this->createContext(
            'EXIT-DUPLICATE-PRIMARY'
        );

    $this->createCommitment(
        context: $context,
        minimum: '400.000000',
        confidence:
            SupplyConfidence::GREEN,
    );

    /*
     * Bypass normal SupplyNetworkService untuk
     * mensimulasikan corrupted persisted state.
     *
     * Normal service-level invariant memang
     * melarang lebih dari satu PRIMARY aktif.
     */
    $secondPrimary =
        $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-M06-EXIT-DUPLICATE-PRIMARY-SECOND'
        );

    SupplyNetworkLink::create([
        'sppg_organization_id' =>
            $context['sppg']->id,

        'kdkmp_organization_id' =>
            $secondPrimary->id,

        'network_role' =>
            NetworkRole::PRIMARY,

        'is_active' =>
            true,

        'configured_by' =>
            $context['admin']->id,
    ]);

    $result =
        $this->service()
            ->calculate(
                $context['forecast'],
                CarbonImmutable::parse(
                    '2026-08-10 10:00:00'
                )
            );

    /*
     * Calculator tidak memilih PRIMARY secara
     * arbitrary. Invalid topology = fail closed.
     */
    $this->assertSame(
        '0.000000',
        $result->directSafeSupply
    );

    $this->assertSame(
        '0.000000',
        $result->atRiskSupply
    );

    $this->assertSame(
        [],
        $result
            ->contributorOrganizationIds
    );

    $this->assertFalse(
        $result->volumeReady
    );
}

public function test_canonical_calculation_rejects_all_non_published_forecast_states(): void
{
    foreach ([
        ForecastStatus::DRAFT,
        ForecastStatus::CLOSED,
        ForecastStatus::CANCELLED,
    ] as $index => $status) {
        $context =
            $this->createContext(
                'EXIT-STATUS-'.$index
            );

        $context['forecast']->update([
            'status' =>
                $status,
        ]);

        try {
            $this->service()
                ->calculate(
                    $context['forecast'],
                    CarbonImmutable::parse(
                        '2026-08-10 10:00:00'
                    )
                );

            $this->fail(
                "Forecast {$status->value} seharusnya tidak dapat dihitung sebagai operational metrics."
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'status',
                $exception->errors()
            );
        }
    }
}

public function test_repeated_calculation_with_same_state_and_time_is_deterministic(): void
{
    $context =
        $this->createContext(
            'EXIT-DETERMINISTIC'
        );

    $this->createCommitment(
        context: $context,
        minimum: '123.123456',
        confidence:
            SupplyConfidence::GREEN,
    );

    $this->createCommitment(
        context: $context,
        minimum: '50.500000',
        confidence:
            SupplyConfidence::YELLOW,
    );

    $evaluationTime =
        CarbonImmutable::parse(
            '2026-08-10 10:00:00'
        );

    $first =
        $this->service()
            ->calculate(
                $context['forecast'],
                $evaluationTime
            )
            ->toArray();

    $second =
        $this->service()
            ->calculate(
                $context['forecast'],
                $evaluationTime
            )
            ->toArray();

    $this->assertSame(
        $first,
        $second
    );
}

public function test_m06_metrics_are_not_persisted_as_forecast_truth_columns(): void
{
    foreach ([
        'safe_supply',
        'direct_safe_supply',
        'at_risk_supply',
        'fallback_safe_supply',
        'total_safe_supply',
        'coverage',
        'coverage_percent',
        'shortfall',
        'surplus',
        'volume_ready',
        'is_volume_ready',
        'is_ready_for_procurement',
    ] as $column) {
        $this->assertFalse(
            Schema::hasColumn(
                'demand_forecasts',
                $column
            ),
            "M06 derived metric tidak boleh menjadi stored Forecast truth: {$column}"
        );
    }
}
    private function service(): SupplyMetricsService
    {
        return app(
            SupplyMetricsService::class
        );
    }

    private function createContext(
        string $suffix,
    ): array {
        $unit =
            Unit::create([
                'code' =>
                    "kg-M06-{$suffix}",

                'name' =>
                    "Kilogram M06 {$suffix}",

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
                    "KANGKUNG-M06-{$suffix}",

                'name' =>
                    "Kangkung M06 {$suffix}",

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
                "SPPG-M06-{$suffix}"
            );

        $primaryKdkmp =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-M06-{$suffix}"
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
            $this->createUser(
                $sppg,
                UserRole::SPPG_USER
            );

        $operator =
            $this->createUser(
                $primaryKdkmp,
                UserRole::KDKMP_OPERATOR
            );

        $producer =
            $this->createProducer(
                $primaryKdkmp,
                $operator,
                "PROD-M06-{$suffix}"
            );

        SupplyNetworkLink::create([
            'sppg_organization_id' =>
                $sppg->id,

            'kdkmp_organization_id' =>
                $primaryKdkmp->id,

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
                    "FRC-M06-{$suffix}",

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
                    'M06 calculation fixture',

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

            'primary_kdkmp' =>
                $primaryKdkmp,

            'admin' =>
                $admin,

            'sppg_user' =>
                $sppgUser,

            'operator' =>
                $operator,

            'producer' =>
                $producer,

            'forecast' =>
                $forecast,
        ];
    }

    private function createCommitment(
        array $context,
        string $minimum,
        string $maximum = '999.000000',
        SupplyConfidence $confidence =
            SupplyConfidence::GREEN,
        CommitmentLifecycleStatus $lifecycle =
            CommitmentLifecycleStatus::ACTIVE,
        CommitmentApprovalStatus $approvalStatus =
            CommitmentApprovalStatus::APPROVED,
        ?Organization $organization = null,
        ?Producer $producer = null,
        ?User $creator = null,
        ?Commodity $commodity = null,
        ?Unit $unit = null,
        string $availabilityStart =
            '2026-08-20 08:00:00',
        string $availabilityEnd =
            '2026-08-25 17:00:00',
    ): SupplyCommitment {
        $organization ??=
            $context['primary_kdkmp'];

        $producer ??=
            $context['producer'];

        $creator ??=
            $context['operator'];

        $commodity ??=
            $context['commodity'];

        $unit ??=
            $context['unit'];

        $commitment =
            $this->createLogicalCommitment(
                context: $context,
                confidence: $confidence,
                lifecycle: $lifecycle,
                organization: $organization,
                producer: $producer,
                creator: $creator,
                commodity: $commodity,
            );

        $version =
            $this->createVersion(
                commitment: $commitment,
                unit: $unit,
                creator: $creator,
                versionNo: 1,
                minimum: $minimum,
                maximum: $maximum,
                approvalStatus: $approvalStatus,
                availabilityStart:
                    $availabilityStart,
                availabilityEnd:
                    $availabilityEnd,
            );

        $commitment->update([
            'active_version_id' =>
                $version->id,
        ]);

        return $commitment->refresh();
    }

    private function createLogicalCommitment(
        array $context,
        SupplyConfidence $confidence,
        CommitmentLifecycleStatus $lifecycle =
            CommitmentLifecycleStatus::ACTIVE,
        ?Organization $organization = null,
        ?Producer $producer = null,
        ?User $creator = null,
        ?Commodity $commodity = null,
    ): SupplyCommitment {
        $organization ??=
            $context['primary_kdkmp'];

        $producer ??=
            $context['producer'];

        $creator ??=
            $context['operator'];

        $commodity ??=
            $context['commodity'];

        return SupplyCommitment::create([
            'forecast_id' =>
                $context['forecast']->id,

            'organization_id' =>
                $organization->id,

            'producer_id' =>
                $producer->id,

            'expected_harvest_id' =>
                null,

            'commodity_id' =>
                $commodity->id,

            'active_version_id' =>
                null,

            'lifecycle_status' =>
                $lifecycle,

            'current_confidence' =>
                $confidence,

            'last_confidence_verified_at' =>
                '2026-08-10 08:00:00',

            'created_by' =>
                $creator->id,

            'cancelled_at' =>
                $lifecycle
                    === CommitmentLifecycleStatus::CANCELLED
                    ? '2026-08-10 09:00:00'
                    : null,

            'cancellation_reason' =>
                $lifecycle
                    === CommitmentLifecycleStatus::CANCELLED
                    ? 'Fixture cancelled'
                    : null,

            'expired_at' =>
                $lifecycle
                    === CommitmentLifecycleStatus::EXPIRED
                    ? '2026-08-10 09:00:00'
                    : null,
        ]);
    }

    private function createVersion(
        SupplyCommitment $commitment,
        Unit $unit,
        User $creator,
        int $versionNo,
        string $minimum,
        string $maximum,
        CommitmentApprovalStatus $approvalStatus,
        string $availabilityStart =
            '2026-08-20 08:00:00',
        string $availabilityEnd =
            '2026-08-25 17:00:00',
    ): CommitmentVersion {
        $isApproved =
            $approvalStatus
            === CommitmentApprovalStatus::APPROVED;

        return CommitmentVersion::create([
            'commitment_id' =>
                $commitment->id,

            'version_no' =>
                $versionNo,

            'min_volume' =>
                $minimum,

            'max_volume' =>
                $maximum,

            'unit_id' =>
                $unit->id,

            'availability_start_at' =>
                $availabilityStart,

            'availability_end_at' =>
                $availabilityEnd,

            'notes' =>
                'M06 calculation fixture',

            'approval_status' =>
                $approvalStatus,

            'change_reason' =>
                $versionNo > 1
                    ? 'Revision fixture'
                    : null,

            'operator_justification' =>
                null,

            'created_by' =>
                $creator->id,

            'submitted_by' =>
                $creator->id,

            'submitted_at' =>
                '2026-08-10 08:30:00',

            'reviewed_by' =>
                null,

            'reviewed_at' =>
                $isApproved
                    ? '2026-08-10 09:00:00'
                    : null,

            'review_reason' =>
                null,

            'approved_at' =>
                $isApproved
                    ? '2026-08-10 09:00:00'
                    : null,

            'created_at' =>
                '2026-08-10 08:00:00',
        ]);
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
                'Lokasi Test M06',
        ]);
    }

    private function createUser(
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
                'Producer fixture M06',

            'is_active' =>
                true,

            'created_by' =>
                $creator->id,
        ]);
    }
}