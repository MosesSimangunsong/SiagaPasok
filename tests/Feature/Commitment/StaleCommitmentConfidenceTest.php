<?php

namespace Tests\Feature\Commitment;

use App\Enums\AuditSource;
use App\Enums\NetworkRole;
use App\Enums\OrganizationType;
use App\Enums\SupplyConfidence;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Commodity;
use App\Models\CommitmentConfidenceEvent;
use App\Models\Organization;
use App\Models\Producer;
use App\Models\SupplyNetworkLink;
use App\Models\Unit;
use App\Models\User;
use App\Services\Commitment\CommitmentWorkflowService;
use App\Services\Commitment\ConfidenceService;
use App\Services\Forecast\DemandForecastService;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class StaleCommitmentConfidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_green_is_downgraded_to_yellow_by_system_at_threshold(): void
    {
        Carbon::setTestNow(
            '2026-08-10 12:00:00'
        );

        try {
            $context =
                $this->createApprovedContext(
                    'AT-THRESHOLD',
                    4
                );

            $commitment =
                $context['commitment'];

            $verifiedAt =
                now()->subHours(4);

            $commitment
                ->last_confidence_verified_at =
                $verifiedAt;

            $commitment->save();

            $changed =
                app(
                    ConfidenceService::class
                )->downgradeStaleIfDue(
                    $commitment->fresh(),
                    4
                );

            $this->assertTrue(
                $changed
            );

            $commitment->refresh();

            $this->assertSame(
                SupplyConfidence::YELLOW,
                $commitment->current_confidence
            );

            /*
             * Stale downgrade bukan verifikasi baru.
             * Timestamp freshness lama harus tetap
             * dipertahankan.
             */
            $this->assertSame(
                $verifiedAt->format(
                    'Y-m-d H:i:s'
                ),
                $commitment
                    ->last_confidence_verified_at
                    ->format(
                        'Y-m-d H:i:s'
                    )
            );

            $event =
                CommitmentConfidenceEvent::query()
                    ->where(
                        'commitment_id',
                        $commitment->id
                    )
                    ->where(
                        'reason_code',
                        'STALE_DATA'
                    )
                    ->firstOrFail();

            $this->assertSame(
                SupplyConfidence::GREEN,
                $event->from_confidence
            );

            $this->assertSame(
                SupplyConfidence::YELLOW,
                $event->to_confidence
            );

            $this->assertSame(
                AuditSource::SYSTEM,
                $event->source
            );

            $this->assertNull(
                $event->actor_user_id
            );

            $this->assertSame(
                '2026-08-10 12:00:00',
                $event
                    ->occurred_at
                    ->format(
                        'Y-m-d H:i:s'
                    )
            );

            $audit =
                AuditLog::query()
                    ->where(
                        'entity_id',
                        $commitment->id
                    )
                    ->where(
                        'source',
                        AuditSource::SYSTEM
                            ->value
                    )
                    ->whereNull(
                        'actor_user_id'
                    )
                    ->latest('id')
                    ->firstOrFail();

            $this->assertSame(
                AuditSource::SYSTEM,
                $audit->source
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_command_uses_freshness_interval_of_each_forecast(): void
    {
        Carbon::setTestNow(
            '2026-08-10 12:00:00'
        );

        try {
            /*
             * Kedua Commitment memiliki
             * last verification yang sama:
             * 3 jam lalu.
             *
             * Forecast A threshold 2 jam
             * → stale.
             *
             * Forecast B threshold 4 jam
             * → belum stale.
             */
            $staleContext =
                $this->createApprovedContext(
                    'PER-FORECAST-A',
                    2
                );

            $freshContext =
                $this->createApprovedContext(
                    'PER-FORECAST-B',
                    4
                );

            foreach ([
                $staleContext['commitment'],
                $freshContext['commitment'],
            ] as $commitment) {
                $commitment
                    ->last_confidence_verified_at =
                    now()->subHours(3);

                $commitment->save();
            }

            $exitCode =
                Artisan::call(
                    'commitments:evaluate-stale-confidence'
                );

            $this->assertSame(
                Command::SUCCESS,
                $exitCode
            );

            $this->assertSame(
                SupplyConfidence::YELLOW,
                $staleContext[
                    'commitment'
                ]
                    ->fresh()
                    ->current_confidence
            );

            $this->assertSame(
                SupplyConfidence::GREEN,
                $freshContext[
                    'commitment'
                ]
                    ->fresh()
                    ->current_confidence
            );

            $this->assertSame(
                1,
                CommitmentConfidenceEvent::query()
                    ->where(
                        'commitment_id',
                        $staleContext[
                            'commitment'
                        ]->id
                    )
                    ->where(
                        'reason_code',
                        'STALE_DATA'
                    )
                    ->count()
            );

            $this->assertSame(
                0,
                CommitmentConfidenceEvent::query()
                    ->where(
                        'commitment_id',
                        $freshContext[
                            'commitment'
                        ]->id
                    )
                    ->where(
                        'reason_code',
                        'STALE_DATA'
                    )
                    ->count()
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_forecast_without_freshness_configuration_is_skipped(): void
    {
        Carbon::setTestNow(
            '2026-08-10 12:00:00'
        );

        try {
            $context =
                $this->createApprovedContext(
                    'NO-CONFIG',
                    null
                );

            $commitment =
                $context['commitment'];

            /*
             * Sangat lama, tetapi Forecast tidak
             * mempunyai freshness configuration.
             *
             * Tidak boleh ada fallback threshold
             * global seperti 168 jam.
             */
            $commitment
                ->last_confidence_verified_at =
                now()->subDays(30);

            $commitment->save();

            $exitCode =
                Artisan::call(
                    'commitments:evaluate-stale-confidence'
                );

            $this->assertSame(
                Command::SUCCESS,
                $exitCode
            );

            $this->assertSame(
                SupplyConfidence::GREEN,
                $commitment
                    ->fresh()
                    ->current_confidence
            );

            $this->assertSame(
                0,
                CommitmentConfidenceEvent::query()
                    ->where(
                        'commitment_id',
                        $commitment->id
                    )
                    ->where(
                        'reason_code',
                        'STALE_DATA'
                    )
                    ->count()
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_stale_command_is_idempotent_and_does_not_duplicate_event_or_audit(): void
    {
        Carbon::setTestNow(
            '2026-08-10 12:00:00'
        );

        try {
            $context =
                $this->createApprovedContext(
                    'IDEMPOTENT',
                    1
                );

            $commitment =
                $context['commitment'];

            $commitment
                ->last_confidence_verified_at =
                now()->subHours(2);

            $commitment->save();

            $firstExitCode =
                Artisan::call(
                    'commitments:evaluate-stale-confidence'
                );

            $this->assertSame(
                Command::SUCCESS,
                $firstExitCode
            );

            $this->assertSame(
                SupplyConfidence::YELLOW,
                $commitment
                    ->fresh()
                    ->current_confidence
            );

            $staleEventCount =
                CommitmentConfidenceEvent::query()
                    ->where(
                        'commitment_id',
                        $commitment->id
                    )
                    ->where(
                        'reason_code',
                        'STALE_DATA'
                    )
                    ->count();

            $systemAuditCount =
                AuditLog::query()
                    ->where(
                        'entity_id',
                        $commitment->id
                    )
                    ->where(
                        'source',
                        AuditSource::SYSTEM
                            ->value
                    )
                    ->whereNull(
                        'actor_user_id'
                    )
                    ->count();

            $this->assertSame(
                1,
                $staleEventCount
            );

            $this->assertSame(
                1,
                $systemAuditCount
            );

            /*
             * Run evaluator kedua terhadap state
             * database yang sama.
             */
            $secondExitCode =
                Artisan::call(
                    'commitments:evaluate-stale-confidence'
                );

            $this->assertSame(
                Command::SUCCESS,
                $secondExitCode
            );

            $this->assertSame(
                1,
                CommitmentConfidenceEvent::query()
                    ->where(
                        'commitment_id',
                        $commitment->id
                    )
                    ->where(
                        'reason_code',
                        'STALE_DATA'
                    )
                    ->count()
            );

            $this->assertSame(
                1,
                AuditLog::query()
                    ->where(
                        'entity_id',
                        $commitment->id
                    )
                    ->where(
                        'source',
                        AuditSource::SYSTEM
                            ->value
                    )
                    ->whereNull(
                        'actor_user_id'
                    )
                    ->count()
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_stale_command_does_not_change_yellow_or_red_commitments(): void
    {
        Carbon::setTestNow(
            '2026-08-10 12:00:00'
        );

        try {
            $yellowContext =
                $this->createApprovedContext(
                    'ALREADY-YELLOW',
                    1
                );

            $redContext =
                $this->createApprovedContext(
                    'ALREADY-RED',
                    1
                );

            $confidence =
                app(
                    ConfidenceService::class
                );

            $confidence->downgrade(
                actor:
                    $yellowContext[
                        'operator'
                    ],

                commitment:
                    $yellowContext[
                        'commitment'
                    ],

                toConfidence:
                    SupplyConfidence::YELLOW,

                reasonCode:
                    'KNOWN_RISK',

                reasonNote:
                    'Commitment sudah diketahui berisiko.'
            );

            $confidence->downgrade(
                actor:
                    $redContext[
                        'operator'
                    ],

                commitment:
                    $redContext[
                        'commitment'
                    ],

                toConfidence:
                    SupplyConfidence::RED,

                reasonCode:
                    'SUPPLY_FAILURE',

                reasonNote:
                    'Commitment sudah gagal.'
            );

            foreach ([
                $yellowContext[
                    'commitment'
                ],
                $redContext[
                    'commitment'
                ],
            ] as $commitment) {
                $commitment =
                    $commitment->fresh();

                $commitment
                    ->last_confidence_verified_at =
                    now()->subDays(10);

                $commitment->save();
            }

            $exitCode =
                Artisan::call(
                    'commitments:evaluate-stale-confidence'
                );

            $this->assertSame(
                Command::SUCCESS,
                $exitCode
            );

            $this->assertSame(
                SupplyConfidence::YELLOW,
                $yellowContext[
                    'commitment'
                ]
                    ->fresh()
                    ->current_confidence
            );

            $this->assertSame(
                SupplyConfidence::RED,
                $redContext[
                    'commitment'
                ]
                    ->fresh()
                    ->current_confidence
            );

            $this->assertSame(
                0,
                CommitmentConfidenceEvent::query()
                    ->where(
                        'commitment_id',
                        $yellowContext[
                            'commitment'
                        ]->id
                    )
                    ->where(
                        'reason_code',
                        'STALE_DATA'
                    )
                    ->count()
            );

            $this->assertSame(
                0,
                CommitmentConfidenceEvent::query()
                    ->where(
                        'commitment_id',
                        $redContext[
                            'commitment'
                        ]->id
                    )
                    ->where(
                        'reason_code',
                        'STALE_DATA'
                    )
                    ->count()
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_green_before_threshold_is_not_downgraded(): void
    {
        Carbon::setTestNow(
            '2026-08-10 12:00:00'
        );

        try {
            $context =
                $this->createApprovedContext(
                    'NOT-DUE',
                    6
                );

            $commitment =
                $context['commitment'];

            $commitment
                ->last_confidence_verified_at =
                now()->subHours(5);

            $commitment->save();

            $changed =
                app(
                    ConfidenceService::class
                )->downgradeStaleIfDue(
                    $commitment->fresh(),
                    6
                );

            $this->assertFalse(
                $changed
            );

            $this->assertSame(
                SupplyConfidence::GREEN,
                $commitment
                    ->fresh()
                    ->current_confidence
            );

            $this->assertSame(
                0,
                CommitmentConfidenceEvent::query()
                    ->where(
                        'commitment_id',
                        $commitment->id
                    )
                    ->where(
                        'reason_code',
                        'STALE_DATA'
                    )
                    ->count()
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    private function createApprovedContext(
        string $suffix,
        ?int $freshnessIntervalHours
    ): array {
        $context =
            $this->createOperationalContext(
                $suffix,
                $freshnessIntervalHours
            );

        $workflow =
            app(
                CommitmentWorkflowService::class
            );

        $commitment =
            $workflow->createDraft(
                $context['operator'],
                [
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
                        'Stale Confidence test',

                    'operator_justification' =>
                        null,
                ]
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

        $this->assertSame(
            SupplyConfidence::GREEN,
            $commitment->current_confidence
        );

        $this->assertNotNull(
            $commitment
                ->last_confidence_verified_at
        );

        return [
            ...$context,

            'commitment' =>
                $commitment,

            'version' =>
                $version->fresh(),
        ];
    }

    private function createOperationalContext(
        string $suffix,
        ?int $freshnessIntervalHours
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
                "SPPG-STALE-{$suffix}"
            );

        $kdkmp =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-STALE-{$suffix}"
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
                        $freshnessIntervalHours,

                    'notes' =>
                        'Stale evaluator Forecast test',
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
                    "PROD-STALE-{$suffix}",

                'name' =>
                    "Produsen {$suffix}",

                'village' =>
                    'Desa Test',

                'district' =>
                    'Kecamatan Test',

                'contact_phone' =>
                    '081234567890',

                'notes' =>
                    'Producer stale fixture',

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

    private function createReferenceData(
        string $suffix
    ): array {
        $unit =
            Unit::create([
                'code' =>
                    "kg-stale-{$suffix}",

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
                    "BAYAM-STALE-{$suffix}",

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