<?php

namespace Tests\Feature\Fulfilment;

use App\Enums\CommitmentApprovalStatus;
use App\Enums\CommitmentLifecycleStatus;
use App\Enums\ForecastStatus;
use App\Enums\FulfilmentResult;
use App\Enums\OrganizationType;
use App\Enums\SupplyConfidence;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Commodity;
use App\Models\CommitmentVersion;
use App\Models\DemandForecast;
use App\Models\ForecastDerivedStateObservation;
use App\Models\Organization;
use App\Models\Producer;
use App\Models\SupplyCommitment;
use App\Models\Unit;
use App\Models\User;
use App\Services\Fulfilment\FulfilmentFeedbackService;
use App\Support\FixedScaleDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FulfilmentFeedbackServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse(
                '2026-08-26 10:00:00'
            )
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_sppg_records_fulfilled_feedback_from_historical_rfp_handoff_snapshot(): void
    {
        $context =
            $this->createContext(
                'FULFILLED'
            );

        $handoff =
            $this->createRfpHandoff(
                $context,
                '100.000000'
            );

        $feedback =
            $this->service()
                ->record(
                    $context['sppg_user'],
                    $context['forecast'],
                    $context[
                        'contributor'
                    ]->id,
                    [
                        'delivered_volume' =>
                            '110.000000',

                        'fulfilment_date' =>
                            '2026-08-25',

                        'reason_note' =>
                            null,
                    ]
                );

        $this->assertSame(
            '100.000000',
            (string)
            $feedback
                ->planned_volume_snapshot
        );

        $this->assertSame(
            '110.000000',
            (string)
            $feedback
                ->delivered_volume
        );

        $this->assertSame(
            FulfilmentResult::FULFILLED,
            $feedback->result
        );

        $this->assertSame(
            $context['unit']->id,
            $feedback->unit_id
        );

        $this->assertSame(
            $context['sppg_user']->id,
            $feedback->recorded_by
        );

        $audit =
            AuditLog::query()
                ->where(
                    'action',
                    FulfilmentFeedbackService
                        ::AUDIT_RECORDED
                )
                ->where(
                    'entity_id',
                    $feedback->id
                )
                ->firstOrFail();

        $this->assertSame(
            $context['sppg_user']->id,
            $audit->actor_user_id
        );

        $this->assertSame(
            '100.000000',
            $audit
                ->new_value_json[
                    'planned_volume_snapshot'
                ]
        );

        $this->assertSame(
            FulfilmentResult
                ::FULFILLED
                ->value,
            $audit
                ->new_value_json[
                    'result'
                ]
        );

        $this->assertSame(
            $handoff->id,
            $audit
                ->new_value_json[
                    'source_rfp_observation_id'
                ]
        );
    }

    public function test_partial_feedback_requires_reason_and_result_is_server_derived(): void
    {
        $context =
            $this->createContext(
                'PARTIAL'
            );

        $this->createRfpHandoff(
            $context,
            '100.000000'
        );

        try {
            $this->service()
                ->record(
                    $context['sppg_user'],
                    $context['forecast'],
                    $context[
                        'contributor'
                    ]->id,
                    [
                        'delivered_volume' =>
                            '60.000000',

                        'fulfilment_date' =>
                            '2026-08-25',

                        /*
                         * Client mencoba mengirim
                         * result sendiri.
                         *
                         * Service harus mengabaikannya.
                         */
                        'result' =>
                            FulfilmentResult
                                ::FULFILLED
                                ->value,
                    ]
                );

            $this->fail(
                'PARTIAL tanpa alasan harus ditolak.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'reason_note',
                $exception->errors()
            );
        }

        $feedback =
            $this->service()
                ->record(
                    $context['sppg_user'],
                    $context['forecast'],
                    $context[
                        'contributor'
                    ]->id,
                    [
                        'delivered_volume' =>
                            '60.000000',

                        'fulfilment_date' =>
                            '2026-08-25',

                        'reason_note' =>
                            'Sebagian volume belum terealisasi.',

                        'result' =>
                            FulfilmentResult
                                ::FULFILLED
                                ->value,
                    ]
                );

        $this->assertSame(
            FulfilmentResult::PARTIAL,
            $feedback->result
        );

        $this->assertSame(
            'Sebagian volume belum terealisasi.',
            $feedback->reason_note
        );
    }

    public function test_zero_delivery_is_failed_and_requires_reason(): void
    {
        $context =
            $this->createContext(
                'FAILED'
            );

        $this->createRfpHandoff(
            $context,
            '100.000000'
        );

        try {
            $this->service()
                ->record(
                    $context['sppg_user'],
                    $context['forecast'],
                    $context[
                        'contributor'
                    ]->id,
                    [
                        'delivered_volume' =>
                            '0',

                        'fulfilment_date' =>
                            '2026-08-25',
                    ]
                );

            $this->fail(
                'FAILED tanpa alasan harus ditolak.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'reason_note',
                $exception->errors()
            );
        }

        $feedback =
            $this->service()
                ->record(
                    $context['sppg_user'],
                    $context['forecast'],
                    $context[
                        'contributor'
                    ]->id,
                    [
                        'delivered_volume' =>
                            '0',

                        'fulfilment_date' =>
                            '2026-08-25',

                        'reason_note' =>
                            'Realisasi tidak terjadi.',
                    ]
                );

        $this->assertSame(
            FulfilmentResult::FAILED,
            $feedback->result
        );

        $this->assertSame(
            '0.000000',
            (string)
            $feedback->delivered_volume
        );
    }

    public function test_only_sppg_user_can_record_feedback(): void
    {
        $context =
            $this->createContext(
                'AUTH'
            );

        $this->createRfpHandoff(
            $context,
            '100.000000'
        );

        foreach (
            [
                $context[
                    'kdkmp_operator'
                ],
                $context['admin'],
            ]
            as $actor
        ) {
            try {
                $this->service()
                    ->record(
                        $actor,
                        $context['forecast'],
                        $context[
                            'contributor'
                        ]->id,
                        $this->validPayload()
                    );

                $this->fail(
                    'Role non-SPPG tidak boleh mencatat fulfilment.'
                );
            } catch (
                AuthorizationException
            ) {
                $this->assertTrue(true);
            }
        }

        $this->assertDatabaseCount(
            'fulfilment_feedbacks',
            0
        );
    }

    public function test_feedback_requires_closed_forecast_after_official_process(): void
{
    $context =
        $this->createContext(
            'NOT-CLOSED'
        );

    $this->createRfpHandoff(
        $context,
        '100.000000'
    );

    /*
     * Historical RFP pernah tercapai, tetapi
     * Forecast dibuka kembali sebagai PUBLISHED
     * fixture untuk memastikan handoff saja
     * tidak cukup memberi write authority.
     */
    $context['forecast']->update([
        'status' =>
            ForecastStatus::PUBLISHED,

        'closed_at' =>
            null,
    ]);

    try {
        $this->service()
            ->record(
                $context['sppg_user'],
                $context['forecast'],
                $context[
                    'contributor'
                ]->id,
                $this->validPayload()
            );

        $this->fail(
            'Fulfilment sebelum Forecast CLOSED harus ditolak.'
        );
    } catch (
        ValidationException $exception
    ) {
        $this->assertArrayHasKey(
            'status',
            $exception->errors()
        );
    }

    $this->assertDatabaseCount(
        'fulfilment_feedbacks',
        0
    );
}

    public function test_feedback_rejects_organization_that_was_not_contributor_at_latest_handoff(): void
    {
        $context =
            $this->createContext(
                'NON-CONTRIBUTOR'
            );

        $this->createRfpHandoff(
            $context,
            '100.000000'
        );

        try {
            $this->service()
                ->record(
                    $context['sppg_user'],
                    $context['forecast'],
                    $context[
                        'other_kdkmp'
                    ]->id,
                    $this->validPayload()
                );

            $this->fail(
                'Non-contributor tidak boleh menerima fulfilment feedback.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'contributor_organization_id',
                $exception->errors()
            );
        }

        $this->assertDatabaseCount(
            'fulfilment_feedbacks',
            0
        );
    }

    public function test_one_feedback_per_forecast_and_contributor(): void
    {
        $context =
            $this->createContext(
                'UNIQUE'
            );

        $this->createRfpHandoff(
            $context,
            '100.000000'
        );

        $this->service()
            ->record(
                $context['sppg_user'],
                $context['forecast'],
                $context[
                    'contributor'
                ]->id,
                $this->validPayload()
            );

        try {
            $this->service()
                ->record(
                    $context['sppg_user'],
                    $context['forecast'],
                    $context[
                        'contributor'
                    ]->id,
                    $this->validPayload()
                );

            $this->fail(
                'Feedback kedua untuk contributor yang sama harus ditolak.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'contributor_organization_id',
                $exception->errors()
            );
        }

        $this->assertDatabaseCount(
            'fulfilment_feedbacks',
            1
        );
    }

    public function test_planned_snapshot_remains_first_snapshot_of_same_rfp_ready_episode(): void
    {
        $context =
            $this->createContext(
                'FREEZE'
            );

        $this->createRfpHandoff(
            $context,
            '100.000000'
        );

        /*
         * RFP tetap TRUE tetapi allocation berubah.
         *
         * Ini observation baru dari M12-02,
         * bukan handoff baru.
         */
        $this->createObservation(
            $context,
            true,
            [
                $context[
                    'contributor'
                ]->id =>
                    '120.000000',
            ]
        );

        $feedback =
            $this->service()
                ->record(
                    $context['sppg_user'],
                    $context['forecast'],
                    $context[
                        'contributor'
                    ]->id,
                    [
                        'delivered_volume' =>
                            '100.000000',

                        'fulfilment_date' =>
                            '2026-08-25',
                    ]
                );

        $this->assertSame(
            '100.000000',
            (string)
            $feedback
                ->planned_volume_snapshot
        );

        $this->assertSame(
            FulfilmentResult::FULFILLED,
            $feedback->result
        );
    }

    public function test_latest_rfp_ready_episode_replaces_older_handoff_snapshot(): void
    {
        $context =
            $this->createContext(
                'NEW-EPISODE'
            );

        $this->createRfpHandoff(
            $context,
            '100.000000'
        );

        /*
         * RFP hilang.
         */
        $this->createObservation(
            $context,
            false,
            []
        );

        /*
         * Recovery selesai dan RFP tercapai lagi.
         * Ini handoff baru.
         */
        $this->createObservation(
            $context,
            true,
            [
                $context[
                    'contributor'
                ]->id =>
                    '80.000000',
            ]
        );

        $feedback =
            $this->service()
                ->record(
                    $context['sppg_user'],
                    $context['forecast'],
                    $context[
                        'contributor'
                    ]->id,
                    [
                        'delivered_volume' =>
                            '80.000000',

                        'fulfilment_date' =>
                            '2026-08-25',
                    ]
                );

        $this->assertSame(
            '80.000000',
            (string)
            $feedback
                ->planned_volume_snapshot
        );
    }

    public function test_feedback_does_not_mutate_approved_commitment_history(): void
    {
        $context =
            $this->createContext(
                'NO-MUTATION'
            );

        $commitment =
            $this->createApprovedCommitment(
                $context
            );

        $version =
            $commitment
                ->activeVersion()
                ->firstOrFail();

        $beforeCommitment = [
            'active_version_id' =>
                $commitment
                    ->active_version_id,

            'lifecycle_status' =>
                $commitment
                    ->lifecycle_status,

            'current_confidence' =>
                $commitment
                    ->current_confidence,
        ];

        $beforeVersion = [
            'min_volume' =>
                (string)
                $version->min_volume,

            'max_volume' =>
                (string)
                $version->max_volume,

            'approval_status' =>
                $version
                    ->approval_status,
        ];

        $this->createRfpHandoff(
            $context,
            '100.000000'
        );

        $this->service()
            ->record(
                $context['sppg_user'],
                $context['forecast'],
                $context[
                    'contributor'
                ]->id,
                $this->validPayload()
            );

        $commitment->refresh();
        $version->refresh();

        $this->assertSame(
            $beforeCommitment[
                'active_version_id'
            ],
            $commitment
                ->active_version_id
        );

        $this->assertSame(
            $beforeCommitment[
                'lifecycle_status'
            ],
            $commitment
                ->lifecycle_status
        );

        $this->assertSame(
            $beforeCommitment[
                'current_confidence'
            ],
            $commitment
                ->current_confidence
        );

        $this->assertSame(
            $beforeVersion[
                'min_volume'
            ],
            (string)
            $version->min_volume
        );

        $this->assertSame(
            $beforeVersion[
                'max_volume'
            ],
            (string)
            $version->max_volume
        );

        $this->assertSame(
            $beforeVersion[
                'approval_status'
            ],
            $version
                ->approval_status
        );
    }

    public function test_fulfilment_schema_has_no_score_ranking_or_penalty_fields(): void
    {
        foreach (
            [
                'score',
                'rating',
                'rank',
                'ranking',
                'penalty',
            ]
            as $column
        ) {
            $this->assertFalse(
                Schema::hasColumn(
                    'fulfilment_feedbacks',
                    $column
                )
            );
        }

        $this->assertFalse(
            Schema::hasTable(
                'farmer_scores'
            )
        );

        $this->assertFalse(
            Schema::hasTable(
                'farmer_ratings'
            )
        );
    }

    private function service():
        FulfilmentFeedbackService
    {
        return app(
            FulfilmentFeedbackService::class
        );
    }

    private function validPayload(): array
    {
        return [
            'delivered_volume' =>
                '100.000000',

            'fulfilment_date' =>
                '2026-08-25',

            'reason_note' =>
                null,
        ];
    }

    private function createRfpHandoff(
        array $context,
        string $plannedVolume,
    ): ForecastDerivedStateObservation {
        /*
         * Baseline non-ready observation.
         */
        $this->createObservation(
            $context,
            false,
            []
        );

        /*
         * First TRUE observation = handoff.
         */
        return $this->createObservation(
            $context,
            true,
            [
                $context[
                    'contributor'
                ]->id =>
                    $plannedVolume,
            ]
        );
    }

    private function createObservation(
        array $context,
        bool $ready,
        array $contributorVolumes,
    ): ForecastDerivedStateObservation {
        $totalSafe =
            FixedScaleDecimal::zero();

        foreach (
            $contributorVolumes
            as $volume
        ) {
            $totalSafe =
                $totalSafe->add(
                    FixedScaleDecimal::from(
                        (string) $volume
                    )
                );
        }

        $contributorIds =
            array_map(
                'intval',
                array_keys(
                    $contributorVolumes
                )
            );

        sort(
            $contributorIds,
            SORT_NUMERIC
        );

        return ForecastDerivedStateObservation
            ::create([
                'forecast_id' =>
                    $context['forecast']->id,

                'forecast_version' =>
                    $context[
                        'forecast'
                    ]->version,

                'demand_target' =>
                    '100.000000',

                'total_safe_supply' =>
                    $ready
                        ? $totalSafe
                            ->toString()
                        : '0.000000',

                'shortfall' =>
                    $ready
                        ? '0.000000'
                        : '100.000000',

                'ready_for_procurement' =>
                    $ready,

                'contributor_organization_ids' =>
                    $contributorIds,

                'contributor_safe_supply_by_organization' =>
                    $contributorVolumes,

                'reason_codes' =>
                    $ready
                        ? []
                        : [
                            'VOLUME_NOT_READY',
                        ],

                'evaluated_at' =>
                    CarbonImmutable::now(),

                'created_at' =>
                    CarbonImmutable::now(),
            ]);
    }

    private function createApprovedCommitment(
        array $context,
    ): SupplyCommitment {
        $producer =
            Producer::create([
                'organization_id' =>
                    $context[
                        'contributor'
                    ]->id,

                'producer_code' =>
                    'PROD-FUL-'
                    .$context['suffix'],

                'name' =>
                    'Produsen Fulfilment '
                    .$context['suffix'],

                'village' =>
                    'Desa Test',

                'district' =>
                    'Kecamatan Test',

                'contact_phone' =>
                    '081234567890',

                'notes' =>
                    'Fulfilment fixture',

                'is_active' =>
                    true,

                'created_by' =>
                    $context[
                        'kdkmp_operator'
                    ]->id,
            ]);

        $commitment =
            SupplyCommitment::create([
                'forecast_id' =>
                    $context['forecast']->id,

                'organization_id' =>
                    $context[
                        'contributor'
                    ]->id,

                'producer_id' =>
                    $producer->id,

                'expected_harvest_id' =>
                    null,

                'commodity_id' =>
                    $context[
                        'commodity'
                    ]->id,

                'active_version_id' =>
                    null,

                'lifecycle_status' =>
                    CommitmentLifecycleStatus
                        ::ACTIVE,

                'current_confidence' =>
                    SupplyConfidence::GREEN,

                'last_confidence_verified_at' =>
                    '2026-08-25 09:00:00',

                'created_by' =>
                    $context[
                        'kdkmp_operator'
                    ]->id,

                'cancelled_at' =>
                    null,

                'cancellation_reason' =>
                    null,

                'expired_at' =>
                    null,
            ]);

        $version =
            CommitmentVersion::create([
                'commitment_id' =>
                    $commitment->id,

                'version_no' =>
                    1,

                'min_volume' =>
                    '100.000000',

                'max_volume' =>
                    '100.000000',

                'unit_id' =>
                    $context['unit']->id,

                'availability_start_at' =>
                    '2026-08-20 08:00:00',

                'availability_end_at' =>
                    '2026-08-25 17:00:00',

                'notes' =>
                    'Approved fulfilment fixture',

                'approval_status' =>
                    CommitmentApprovalStatus
                        ::APPROVED,

                'change_reason' =>
                    null,

                'operator_justification' =>
                    null,

                'created_by' =>
                    $context[
                        'kdkmp_operator'
                    ]->id,

                'submitted_by' =>
                    $context[
                        'kdkmp_operator'
                    ]->id,

                'submitted_at' =>
                    '2026-08-20 08:00:00',

                'reviewed_by' =>
                    $context[
                        'kdkmp_manager'
                    ]->id,

                'reviewed_at' =>
                    '2026-08-20 09:00:00',

                'review_reason' =>
                    null,

                'approved_at' =>
                    '2026-08-20 09:00:00',

                'created_at' =>
                    '2026-08-20 08:00:00',
            ]);

        $commitment->update([
            'active_version_id' =>
                $version->id,
        ]);

        return $commitment->refresh();
    }

    private function createContext(
        string $suffix,
    ): array {
        $unit =
            Unit::create([
                'code' =>
                    "KG-FUL-{$suffix}",

                'name' =>
                    "Kilogram Fulfilment {$suffix}",

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
                    "COM-FUL-{$suffix}",

                'name' =>
                    "Komoditas Fulfilment {$suffix}",

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
                "SPPG-FUL-{$suffix}"
            );

        $contributor =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-FUL-{$suffix}"
            );

        $otherKdkmp =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-FUL-OTHER-{$suffix}"
            );

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
            User::factory()->create([
                'organization_id' =>
                    $contributor->id,

                'role' =>
                    UserRole::KDKMP_OPERATOR,

                'is_active' =>
                    true,
            ]);

        $manager =
            User::factory()->create([
                'organization_id' =>
                    $contributor->id,

                'role' =>
                    UserRole::KDKMP_MANAGER,

                'is_active' =>
                    true,
            ]);

        $admin =
            User::factory()->create([
                'organization_id' =>
                    null,

                'role' =>
                    UserRole::SYSTEM_ADMIN,

                'is_active' =>
                    true,
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
                    "FRC-FUL-{$suffix}",

                'target_volume' =>
                    '100.000000',

                'required_start_at' =>
                    '2026-08-20 08:00:00',

                'required_end_at' =>
                    '2026-08-25 17:00:00',

                'freshness_interval_hours' =>
                    24,

'status' =>
    ForecastStatus::CLOSED,

                'notes' =>
                    'Fulfilment fixture',

                'published_at' =>
                    '2026-08-10 08:00:00',
                  

                'closed_at' =>
    '2026-08-25 18:00:00',

                'version' =>
                    1,

                'created_by' =>
                    $sppgUser->id,

                'updated_by' =>
                    $sppgUser->id,
            ]);

        return [
            'suffix' =>
                $suffix,

            'unit' =>
                $unit,

            'commodity' =>
                $commodity,

            'sppg' =>
                $sppg,

            'contributor' =>
                $contributor,

            'other_kdkmp' =>
                $otherKdkmp,

            'sppg_user' =>
                $sppgUser,

            'kdkmp_operator' =>
                $operator,

            'kdkmp_manager' =>
                $manager,

            'admin' =>
                $admin,

            'forecast' =>
                $forecast,
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
                'Lokasi Test Fulfilment',
        ]);
    }
}