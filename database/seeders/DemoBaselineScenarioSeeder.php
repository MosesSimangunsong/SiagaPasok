<?php

namespace Database\Seeders;

use App\Enums\ForecastStatus;
use App\Enums\SupplyConfidence;
use App\Models\Commodity;
use App\Models\CommitmentVersion;
use App\Models\DemandForecast;
use App\Models\ExpectedHarvest;
use App\Models\Organization;
use App\Models\Producer;
use App\Models\SupplyCommitment;
use App\Models\Unit;
use App\Models\User;
use App\Services\Commitment\CommitmentWorkflowService;
use App\Services\Forecast\DemandForecastService;
use App\Services\Supply\ExpectedHarvestService;
use App\Services\Supply\SupplyMetricsService;
use App\Support\Demo\DemoIdentifiers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoBaselineScenarioSeeder extends Seeder
{
    private const FORECAST_FRESHNESS_HOURS = 2160;

    public function run(): void
    {
        if (
            ! (bool) config(
                'siagapasok.demo.enabled',
                false
            )
        ) {
            throw new RuntimeException(
                'DemoBaselineScenarioSeeder hanya boleh dijalankan ketika SiagaPasok demo mode aktif.'
            );
        }

        DB::transaction(function (): void {
            $sppgOrganization = $this->resolveOrganization(
                DemoIdentifiers::SPPG_CODE
            );

            $primaryOrganization = $this->resolveOrganization(
                DemoIdentifiers::PRIMARY_KDKMP_CODE
            );

            $sppgUser = $this->resolveUser(
                DemoIdentifiers::SPPG_EMAIL
            );

            $primaryOperator = $this->resolveUser(
                DemoIdentifiers::PRIMARY_OPERATOR_EMAIL
            );

            $primaryManager = $this->resolveUser(
                DemoIdentifiers::PRIMARY_MANAGER_EMAIL
            );

            $this->assertActorOrganization(
                $sppgUser,
                $sppgOrganization
            );

            $this->assertActorOrganization(
                $primaryOperator,
                $primaryOrganization
            );

            $this->assertActorOrganization(
                $primaryManager,
                $primaryOrganization
            );

            $commodity = Commodity::query()
                ->where('code', 'KANGKUNG')
                ->where('is_active', true)
                ->first();

            if (! $commodity) {
                throw new RuntimeException(
                    'Commodity KANGKUNG aktif belum tersedia.'
                );
            }

            $unit = Unit::query()
                ->where('code', 'kg')
                ->where('is_active', true)
                ->first();

            if (! $unit) {
                throw new RuntimeException(
                    'Unit kg aktif belum tersedia.'
                );
            }

            $forecast = $this->ensureForecast(
                sppgUser: $sppgUser,
                sppgOrganization: $sppgOrganization,
                commodity: $commodity,
                unit: $unit,
            );

            $this->ensureApprovedCommitment(
                forecast: $forecast,
                organization: $primaryOrganization,
                operator: $primaryOperator,
                manager: $primaryManager,
                commodity: $commodity,
                unit: $unit,
                producerCode:
                    DemoIdentifiers
                        ::PRIMARY_BASELINE_PRODUCER_CODE,
                volume:
                    DemoIdentifiers
                        ::PRIMARY_BASELINE_VOLUME,
            );

            $this->ensureApprovedCommitment(
                forecast: $forecast,
                organization: $primaryOrganization,
                operator: $primaryOperator,
                manager: $primaryManager,
                commodity: $commodity,
                unit: $unit,
                producerCode:
                    DemoIdentifiers
                        ::PRIMARY_RISK_PRODUCER_CODE,
                volume:
                    DemoIdentifiers
                        ::PRIMARY_RISK_VOLUME,
            );

            $this->assertCanonicalBaseline(
                forecast: $forecast->refresh(),
                primaryOrganization:
                    $primaryOrganization,
            );
        });
    }

    private function ensureForecast(
        User $sppgUser,
        Organization $sppgOrganization,
        Commodity $commodity,
        Unit $unit
    ): DemandForecast {
        $forecast = DemandForecast::query()
            ->where(
                'forecast_code',
                DemoIdentifiers::FORECAST_CODE
            )
            ->first();

        if (! $forecast) {
            $requiredStart = now()
                ->addDays(30)
                ->startOfDay()
                ->addHours(6);

            $requiredEnd = $requiredStart
                ->copy()
                ->addHours(6);

            /*
             * Forecast DRAFT dibuat langsung hanya untuk
             * mempertahankan stable technical identifier
             * yang diperlukan oleh demo/reset.
             *
             * Business transition DRAFT -> PUBLISHED tetap
             * melalui DemandForecastService.
             */
            $forecast = DemandForecast::query()->create([
                'sppg_organization_id' =>
                    $sppgOrganization->id,
                'commodity_id' =>
                    $commodity->id,
                'unit_id' =>
                    $unit->id,
                'forecast_code' =>
                    DemoIdentifiers::FORECAST_CODE,
                'target_volume' =>
                    DemoIdentifiers
                        ::FORECAST_TARGET_VOLUME,
                'required_start_at' =>
                    $requiredStart,
                'required_end_at' =>
                    $requiredEnd,
                'freshness_interval_hours' =>
                    self::FORECAST_FRESHNESS_HOURS,
                'status' =>
                    ForecastStatus::DRAFT,
                'notes' =>
                    'CONTROLLED DEMO SIMULATION — Forecast Kangkung 400 kg.',
                'version' => 1,
                'created_by' =>
                    $sppgUser->id,
                'updated_by' =>
                    $sppgUser->id,
            ]);
        }

        $this->assertForecastIdentity(
            forecast: $forecast,
            sppgOrganization: $sppgOrganization,
            commodity: $commodity,
            unit: $unit,
        );

        if ($forecast->isDraft()) {
            $forecast = app(
                DemandForecastService::class
            )->publish(
                actor: $sppgUser,
                forecast: $forecast,
                expectedVersion: $forecast->version,
            );
        }

        if (! $forecast->isPublished()) {
            throw new RuntimeException(
                'Forecast demo baseline sudah berada pada lifecycle yang tidak kompatibel. Gunakan demo reset untuk membangun ulang scenario.'
            );
        }

        if (
            $forecast->required_end_at
                ->lessThanOrEqualTo(now())
        ) {
            throw new RuntimeException(
                'Periode Forecast demo baseline sudah berakhir. Gunakan demo reset untuk membangun ulang scenario dengan periode baru.'
            );
        }

        return $forecast->refresh();
    }

    private function assertForecastIdentity(
        DemandForecast $forecast,
        Organization $sppgOrganization,
        Commodity $commodity,
        Unit $unit
    ): void {
        if (
            $forecast->sppg_organization_id
                !== $sppgOrganization->id
            || $forecast->commodity_id
                !== $commodity->id
            || $forecast->unit_id
                !== $unit->id
            || (string) $forecast->target_volume
                !== DemoIdentifiers
                    ::FORECAST_TARGET_VOLUME
        ) {
            throw new RuntimeException(
                'Forecast dengan stable demo code ditemukan tetapi payload-nya tidak sesuai baseline M13.'
            );
        }
    }

    private function ensureApprovedCommitment(
        DemandForecast $forecast,
        Organization $organization,
        User $operator,
        User $manager,
        Commodity $commodity,
        Unit $unit,
        string $producerCode,
        string $volume
    ): SupplyCommitment {
        $producer = Producer::query()
            ->where(
                'organization_id',
                $organization->id
            )
            ->where(
                'producer_code',
                $producerCode
            )
            ->where(
                'is_active',
                true
            )
            ->first();

        if (! $producer) {
            throw new RuntimeException(
                "Producer demo {$producerCode} belum tersedia."
            );
        }

        $existingCommitment = SupplyCommitment::query()
            ->where(
                'forecast_id',
                $forecast->id
            )
            ->where(
                'organization_id',
                $organization->id
            )
            ->where(
                'producer_id',
                $producer->id
            )
            ->first();

        if ($existingCommitment) {
            $this->assertApprovedCommitment(
                commitment: $existingCommitment,
                volume: $volume,
            );

            return $existingCommitment
                ->refresh()
                ->load('activeVersion');
        }

        $expectedHarvest = $this->ensureExpectedHarvest(
            forecast: $forecast,
            organization: $organization,
            operator: $operator,
            producer: $producer,
            commodity: $commodity,
            unit: $unit,
            volume: $volume,
        );

        $workflow = app(
            CommitmentWorkflowService::class
        );

        $commitment = $workflow->createDraft(
            $operator,
            [
                'forecast_id' =>
                    $forecast->id,
                'producer_id' =>
                    $producer->id,
                'expected_harvest_id' =>
                    $expectedHarvest->id,
                'min_volume' =>
                    $volume,
                'max_volume' =>
                    $volume,
                'unit_id' =>
                    $unit->id,
                'availability_start_at' =>
                    $forecast
                        ->required_start_at
                        ->toDateTimeString(),
                'availability_end_at' =>
                    $forecast
                        ->required_end_at
                        ->toDateTimeString(),
                'notes' =>
                    "CONTROLLED DEMO SIMULATION — {$producerCode} — {$volume} kg.",
                'operator_justification' =>
                    null,
            ]
        );

        $draftVersion = CommitmentVersion::query()
            ->where(
                'commitment_id',
                $commitment->id
            )
            ->where(
                'version_no',
                1
            )
            ->firstOrFail();

        $pendingVersion = $workflow->submit(
            $operator,
            $draftVersion
        );

        $workflow->approve(
            $manager,
            $pendingVersion
        );

        $commitment = $commitment
            ->refresh()
            ->load('activeVersion');

        $this->assertApprovedCommitment(
            commitment: $commitment,
            volume: $volume,
        );

        return $commitment;
    }

    private function ensureExpectedHarvest(
        DemandForecast $forecast,
        Organization $organization,
        User $operator,
        Producer $producer,
        Commodity $commodity,
        Unit $unit,
        string $volume
    ): ExpectedHarvest {
        $marker = sprintf(
            'CONTROLLED DEMO SIMULATION — EXPECTED HARVEST — %s — %s kg.',
            $producer->producer_code,
            $volume
        );

        $existing = ExpectedHarvest::query()
            ->where(
                'organization_id',
                $organization->id
            )
            ->where(
                'producer_id',
                $producer->id
            )
            ->where(
                'commodity_id',
                $commodity->id
            )
            ->where(
                'unit_id',
                $unit->id
            )
            ->where(
                'notes',
                $marker
            )
            ->first();

        if ($existing) {
            if (
                (string)
                    $existing->expected_min_volume
                    !== $volume
                || (string)
                    $existing->expected_max_volume
                    !== $volume
            ) {
                throw new RuntimeException(
                    "Expected Harvest demo {$producer->producer_code} memiliki volume yang tidak sesuai baseline."
                );
            }

            return $existing;
        }

        return app(
            ExpectedHarvestService::class
        )->create(
            $operator,
            [
                'producer_id' =>
                    $producer->id,
                'commodity_id' =>
                    $commodity->id,
                'unit_id' =>
                    $unit->id,
                'expected_min_volume' =>
                    $volume,
                'expected_max_volume' =>
                    $volume,
                'harvest_start_at' =>
                    $forecast
                        ->required_start_at
                        ->toDateTimeString(),
                'harvest_end_at' =>
                    $forecast
                        ->required_end_at
                        ->toDateTimeString(),
                'notes' =>
                    $marker,
            ]
        );
    }

    private function assertApprovedCommitment(
        SupplyCommitment $commitment,
        string $volume
    ): void {
        $commitment->loadMissing(
            'activeVersion'
        );

        $activeVersion =
            $commitment->activeVersion;

        if (
            ! $commitment->isActive()
            || $commitment->current_confidence
                !== SupplyConfidence::GREEN
            || ! $activeVersion
            || ! $activeVersion->isApproved()
            || (string) $activeVersion->min_volume
                !== $volume
            || (string) $activeVersion->max_volume
                !== $volume
        ) {
            throw new RuntimeException(
                'Commitment demo baseline sudah ada tetapi state/volume-nya tidak kompatibel. Gunakan demo reset sebelum melakukan reseed.'
            );
        }
    }

    private function assertCanonicalBaseline(
        DemandForecast $forecast,
        Organization $primaryOrganization
    ): void {
        $metrics = app(
            SupplyMetricsService::class
        )->calculate(
            $forecast
        );

        if (
            $metrics->demandTarget
                !== DemoIdentifiers
                    ::FORECAST_TARGET_VOLUME
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
                !== [$primaryOrganization->id]
            || $metrics
                ->contributorSafeSupplyByOrganization
                !== [
                    $primaryOrganization->id =>
                        '400.000000',
                ]
        ) {
            throw new RuntimeException(
                'Canonical M06 metrics tidak menghasilkan baseline demo Demand 400 / Safe 400 / Shortfall 0.'
            );
        }
    }

    private function resolveOrganization(
        string $code
    ): Organization {
        $organization = Organization::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (! $organization) {
            throw new RuntimeException(
                "Demo organization {$code} belum tersedia atau tidak aktif."
            );
        }

        return $organization;
    }

    private function resolveUser(
        string $email
    ): User {
        $user = User::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->first();

        if (
            ! $user
            || ! $user->hasValidIdentityContext()
        ) {
            throw new RuntimeException(
                "Demo user {$email} belum tersedia atau identity context-nya tidak valid."
            );
        }

        return $user;
    }

    private function assertActorOrganization(
        User $user,
        Organization $organization
    ): void {
        if (
            $user->organization_id
            !== $organization->id
        ) {
            throw new RuntimeException(
                "Demo user {$user->email} tidak berada pada organization yang diharapkan."
            );
        }
    }
}