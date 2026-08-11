<?php

namespace App\Services\Demo;

use App\Enums\SupplyConfidence;
use App\Models\DemandForecast;
use App\Models\Organization;
use App\Models\Producer;
use App\Models\SupplyCommitment;
use App\Models\User;
use App\Services\Commitment\ConfidenceService;
use App\Services\Supply\SupplyMetricsResult;
use App\Services\Supply\SupplyMetricsService;
use App\Support\Demo\DemoIdentifiers;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class DemoSupplyRiskService
{
    public const REASON_CODE =
        'DEMO_SUPPLY_RISK';

    private const REASON_NOTE =
        'SIMULASI TERKENDALI — Commitment demo 150 kg dilaporkan berisiko untuk menunjukkan perubahan Safe Supply menjadi Shortfall.';

    public function __construct(
        private readonly ConfidenceService $confidenceService,
        private readonly SupplyMetricsService $supplyMetricsService,
    ) {
    }

    public function apply(
        User $actor
    ): SupplyMetricsResult {
        $actor->loadMissing(
            'organization'
        );

        $this->assertDemoOperator(
            $actor
        );

        $forecast = DemandForecast::query()
            ->where(
                'forecast_code',
                DemoIdentifiers::FORECAST_CODE
            )
            ->first();

        if (! $forecast) {
            $this->fail(
                'Forecast baseline demo belum tersedia.'
            );
        }

        $primaryOrganization =
            Organization::query()
                ->where(
                    'code',
                    DemoIdentifiers
                        ::PRIMARY_KDKMP_CODE
                )
                ->where(
                    'is_active',
                    true
                )
                ->first();

        if (! $primaryOrganization) {
            $this->fail(
                'KDKMP PRIMARY demo belum tersedia atau tidak aktif.'
            );
        }

        $producer = Producer::query()
            ->where(
                'organization_id',
                $primaryOrganization->id
            )
            ->where(
                'producer_code',
                DemoIdentifiers
                    ::PRIMARY_RISK_PRODUCER_CODE
            )
            ->where(
                'is_active',
                true
            )
            ->first();

        if (! $producer) {
            $this->fail(
                'Producer target gangguan pasokan demo belum tersedia.'
            );
        }

        $commitment =
            SupplyCommitment::query()
                ->with(
                    'activeVersion'
                )
                ->where(
                    'forecast_id',
                    $forecast->id
                )
                ->where(
                    'organization_id',
                    $primaryOrganization->id
                )
                ->where(
                    'producer_id',
                    $producer->id
                )
                ->first();

        if (! $commitment) {
            $this->fail(
                'Commitment target 150 kg demo belum tersedia.'
            );
        }

        $activeVersion =
            $commitment->activeVersion;

        if (
            ! $activeVersion
            || ! $activeVersion->isApproved()
            || (string) $activeVersion->min_volume
                !== DemoIdentifiers::PRIMARY_RISK_VOLUME
            || (string) $activeVersion->max_volume
                !== DemoIdentifiers::PRIMARY_RISK_VOLUME
        ) {
            $this->fail(
                'Commitment target demo tidak lagi sesuai baseline 150 kg APPROVED.'
            );
        }

        if (
            $commitment->current_confidence
            === SupplyConfidence::YELLOW
        ) {
            $metrics =
                $this->supplyMetricsService
                    ->calculate(
                        $forecast
                    );

            $this->assertDisruptedMetrics(
                $metrics,
                $primaryOrganization->id
            );

            return $metrics;
        }

        if (
            $commitment->current_confidence
            !== SupplyConfidence::GREEN
        ) {
            $this->fail(
                'Gangguan pasokan demo hanya dapat dimulai dari Commitment GREEN baseline.'
            );
        }

        $before =
            $this->supplyMetricsService
                ->calculate(
                    $forecast
                );

        $this->assertBaselineMetrics(
            $before,
            $primaryOrganization->id
        );

        $this->confidenceService
            ->downgrade(
                actor: $actor,
                commitment: $commitment,
                toConfidence:
                    SupplyConfidence::YELLOW,
                reasonCode:
                    self::REASON_CODE,
                reasonNote:
                    self::REASON_NOTE,
            );

        $after =
            $this->supplyMetricsService
                ->calculate(
                    $forecast->refresh()
                );

        $this->assertDisruptedMetrics(
            $after,
            $primaryOrganization->id
        );

        return $after;
    }

    private function assertDemoOperator(
        User $actor
    ): void {
        if (
            ! $actor->isKdkmpOperator()
            || ! $actor->hasValidIdentityContext()
            || $actor->email
                !== DemoIdentifiers
                    ::PRIMARY_OPERATOR_EMAIL
            || $actor->organization?->code
                !== DemoIdentifiers
                    ::PRIMARY_KDKMP_CODE
        ) {
            throw new AuthorizationException(
                'Controlled supply-risk scenario hanya dapat dijalankan oleh Operator demo KDKMP Tani Sejahtera.'
            );
        }
    }

    private function assertBaselineMetrics(
        SupplyMetricsResult $metrics,
        int $primaryOrganizationId
    ): void {
        if (
            $metrics->demandTarget
                !== '400.000000'
            || $metrics->directSafeSupply
                !== '400.000000'
            || $metrics->atRiskSupply
                !== '0.000000'
            || $metrics->fallbackSafeSupply
                !== '0.000000'
            || $metrics->totalSafeSupply
                !== '400.000000'
            || $metrics->coveragePercent
                !== '100.00'
            || $metrics->shortfall
                !== '0.000000'
            || $metrics->surplus
                !== '0.000000'
            || ! $metrics->volumeReady
            || $metrics->contributorOrganizationIds
                !== [
                    $primaryOrganizationId,
                ]
            || $metrics
                ->contributorSafeSupplyByOrganization
                !== [
                    $primaryOrganizationId =>
                        '400.000000',
                ]
        ) {
            $this->fail(
                'Controlled supply-risk scenario memerlukan baseline Demand 400 / Safe 400 / At-Risk 0 / Shortfall 0.'
            );
        }
    }

    private function assertDisruptedMetrics(
        SupplyMetricsResult $metrics,
        int $primaryOrganizationId
    ): void {
        if (
            $metrics->demandTarget
                !== '400.000000'
            || $metrics->directSafeSupply
                !== '250.000000'
            || $metrics->atRiskSupply
                !== '150.000000'
            || $metrics->fallbackSafeSupply
                !== '0.000000'
            || $metrics->totalSafeSupply
                !== '250.000000'
            || $metrics->coveragePercent
                !== '62.50'
            || $metrics->shortfall
                !== '150.000000'
            || $metrics->surplus
                !== '0.000000'
            || $metrics->volumeReady
            || $metrics->contributorOrganizationIds
                !== [
                    $primaryOrganizationId,
                ]
            || $metrics
                ->contributorSafeSupplyByOrganization
                !== [
                    $primaryOrganizationId =>
                        '250.000000',
                ]
        ) {
            $this->fail(
                'Canonical metrics tidak menghasilkan expected disrupted state Safe 250 / At-Risk 150 / Shortfall 150.'
            );
        }
    }

    private function fail(
        string $message
    ): never {
        throw ValidationException::withMessages([
            'demo_scenario' =>
                $message,
        ]);
    }
}