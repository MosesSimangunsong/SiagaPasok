<?php

namespace Tests\Feature\Readiness;

use App\Enums\CommitmentApprovalStatus;
use App\Enums\CommitmentLifecycleStatus;
use App\Enums\DocumentStatus;
use App\Enums\ForecastStatus;
use App\Enums\NetworkRole;
use App\Enums\OrganizationType;
use App\Enums\ReadinessApprovalStatus;
use App\Enums\ReadinessType;
use App\Enums\RequirementScope;
use App\Enums\SupplyConfidence;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Commodity;
use App\Models\CommitmentVersion;
use App\Models\DemandForecast;
use App\Models\DocumentRecord;
use App\Models\Organization;
use App\Models\Producer;
use App\Models\ReadinessChecklist;
use App\Models\ReadinessRequirement;
use App\Models\SupplyCommitment;
use App\Models\SupplyNetworkLink;
use App\Models\Unit;
use App\Models\User;
use App\Services\Readiness\DocumentRecordService;
use App\Services\Readiness\ReadinessChecklistPreparationService;
use App\Services\Readiness\ReadinessChecklistReviewService;
use App\Services\Readiness\ReadinessChecklistWorkflowService;
use App\Services\Readiness\ReadinessEvaluationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\FallbackConcurrencyDatabase;
use Tests\TestCase;

class ReadinessRealDatabaseConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (
            ! FallbackConcurrencyDatabase
                ::isConfigured()
        ) {
            $this->markTestSkipped(
                'Real PostgreSQL concurrency gate belum '
                .'diaktifkan. Set '
                .'SIAGAPASOK_REAL_DB_CONCURRENCY=true.'
            );
        }

        FallbackConcurrencyDatabase
            ::configure();

        /*
         * Dedicated disposable PostgreSQL
         * database only.
         *
         * Helper menolak db_siagapasok.
         */
        Artisan::call(
            'migrate:fresh',
            [
                '--database' =>
                    FallbackConcurrencyDatabase
                        ::CONNECTION,

                '--force' =>
                    true,
            ]
        );

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

    public function test_concurrent_revisions_create_exactly_one_new_current_version(): void
    {
        $context =
            $this->createOperationalContext(
                'REVISION-RACE'
            );

        $versionOne =
            $this->createApprovedLogisticsChecklist(
                $context,
                'LOG-REVISION-RACE'
            );

        $this->assertSame(
            ReadinessApprovalStatus::APPROVED,
            $versionOne->status
        );

        $this->assertTrue(
            $versionOne->is_current_version
        );

        /*
         * Dua child PostgreSQL sessions mencoba
         * createRevision terhadap exact same V1.
         */
        $results =
            $this->runWorkers([
                'revision-worker-1' => [
                    'operation' =>
                        'revision',

                    'actor_id' =>
                        $context['operator']->id,

                    'target_id' =>
                        $versionOne->id,
                ],

                'revision-worker-2' => [
                    'operation' =>
                        'revision',

                    'actor_id' =>
                        $context['operator']->id,

                    'target_id' =>
                        $versionOne->id,
                ],
            ]);

        $statuses =
            collect(
                $results
            )
                ->pluck(
                    'status'
                )
                ->sort()
                ->values()
                ->all();

        /*
         * First worker switches current V1 → V2.
         *
         * Second worker kemudian mendapatkan
         * V1 lock dan harus melihat bahwa V1
         * sudah historical.
         */
        $this->assertSame(
            [
                'ok',
                'validation',
            ],
            $statuses
        );

        $checklists =
            ReadinessChecklist::query()
                ->where(
                    'forecast_id',
                    $context['forecast']->id
                )
                ->where(
                    'organization_id',
                    $context['kdkmp']->id
                )
                ->where(
                    'readiness_type',
                    ReadinessType::LOGISTICS->value
                )
                ->orderBy(
                    'version_no'
                )
                ->get();

        $this->assertCount(
            2,
            $checklists
        );

        $this->assertSame(
            [
                1,
                2,
            ],
            $checklists
                ->pluck(
                    'version_no'
                )
                ->map(
                    fn ($value): int =>
                        (int) $value
                )
                ->all()
        );

        $versionOne =
            $checklists[0];

        $versionTwo =
            $checklists[1];

        /*
         * Historical approval tidak berubah.
         */
        $this->assertSame(
            ReadinessApprovalStatus::APPROVED,
            $versionOne->status
        );

        $this->assertFalse(
            $versionOne->is_current_version
        );

        /*
         * Exactly one V2 DRAFT menjadi current.
         */
        $this->assertSame(
            ReadinessApprovalStatus::DRAFT,
            $versionTwo->status
        );

        $this->assertTrue(
            $versionTwo->is_current_version
        );

        $this->assertSame(
            $versionOne->id,
            $versionTwo->supersedes_checklist_id
        );

        $this->assertSame(
            1,
            ReadinessChecklist::query()
                ->where(
                    'forecast_id',
                    $context['forecast']->id
                )
                ->where(
                    'organization_id',
                    $context['kdkmp']->id
                )
                ->where(
                    'readiness_type',
                    ReadinessType::LOGISTICS->value
                )
                ->where(
                    'is_current_version',
                    true
                )
                ->count()
        );
    }

    public function test_concurrent_manager_approvals_are_idempotent_and_create_one_approval_audit(): void
    {
        $context =
            $this->createOperationalContext(
                'DOUBLE-APPROVE'
            );

        $checklist =
            $this->createSubmittedLogisticsChecklist(
                $context,
                'LOG-DOUBLE-APPROVE'
            );

        $this->assertSame(
            ReadinessApprovalStatus
                ::PENDING_APPROVAL,
            $checklist->status
        );

        $results =
            $this->runWorkers([
                'approve-worker-1' => [
                    'operation' =>
                        'approve',

                    'actor_id' =>
                        $context['manager']->id,

                    'target_id' =>
                        $checklist->id,
                ],

                'approve-worker-2' => [
                    'operation' =>
                        'approve',

                    'actor_id' =>
                        $context['manager']->id,

                    'target_id' =>
                        $checklist->id,
                ],
            ]);

        /*
         * approve() idempotent setelah serialized
         * row lock.
         *
         * Worker kedua boleh mengembalikan
         * existing APPROVED result.
         */
        $this->assertSame(
            [
                'ok',
                'ok',
            ],
            collect(
                $results
            )
                ->pluck(
                    'status'
                )
                ->sort()
                ->values()
                ->all()
        );

        $checklist->refresh();

        $this->assertSame(
            ReadinessApprovalStatus::APPROVED,
            $checklist->status
        );

        $this->assertSame(
            $context['manager']->id,
            $checklist->reviewed_by
        );

        /*
         * Hanya transition pertama yang menulis
         * audit approval.
         */
        $this->assertSame(
            1,
            AuditLog::query()
                ->where(
                    'entity_type',
                    ReadinessChecklist::class
                )
                ->where(
                    'entity_id',
                    $checklist->id
                )
                ->where(
                    'action',
                    'READINESS_APPROVED'
                )
                ->count()
        );
    }

    public function test_document_mutation_and_manager_approval_cannot_leave_stale_document_ready_true(): void
    {
        $context =
            $this->createOperationalContext(
                'DOCUMENT-RACE'
            );

        $requirement =
            $this->createRequirement(
                context: $context,
                type:
                    ReadinessType::DOCUMENT,
                code:
                    'DOC-DOCUMENT-RACE',
                scope:
                    RequirementScope::ORGANIZATION
            );

        $document =
            app(
                DocumentRecordService::class
            )->create(
                $context['operator'],
                $requirement,
                [
                    'document_name' =>
                        'Dokumen Race',

                    'reference_number' =>
                        'DOC-RACE-001',

                    'valid_from' =>
                        '2026-08-01 00:00:00',

                    'expires_at' =>
                        '2026-08-31 23:59:59',

                    'notes' =>
                        'Initial race document.',
                ]
            );

        $document =
            app(
                DocumentRecordService::class
            )->markValid(
                $context['operator'],
                $document
            );

        $this->assertSame(
            DocumentStatus::VALID,
            $document->status
        );

        $this->assertSame(
            2,
            $document->revision_no
        );

        $checklist =
            app(
                ReadinessChecklistPreparationService::class
            )->createInitialDraft(
                $context['operator'],
                $context['forecast'],
                ReadinessType::DOCUMENT
            );

        $item =
            $checklist
                ->items
                ->firstOrFail();

        app(
            ReadinessChecklistWorkflowService::class
        )->updateItem(
            $context['operator'],
            $checklist,
            $item,
            [
                'is_satisfied' =>
                    true,

                'document_record_id' =>
                    $document->id,

                'note' =>
                    'Document linked before race.',
            ]
        );

        $checklist =
            app(
                ReadinessChecklistWorkflowService::class
            )->submit(
                $context['operator'],
                $checklist
            );

        $item->refresh();

        $this->assertSame(
            2,
            $item->document_record_revision_no
        );

        /*
         * Competing operations:
         *
         * A: Manager approval.
         * B: Operator changes exact referenced
         *    Document Record.
         *
         * Legal serializations:
         *
         * A wins:
         *   approval succeeds, then document
         *   revision changes → derived ready FALSE.
         *
         * B wins:
         *   document changes first, approval sees
         *   revision mismatch → validation failure.
         */
        $results =
            $this->runWorkers([
                'approve-worker' => [
                    'operation' =>
                        'approve',

                    'actor_id' =>
                        $context['manager']->id,

                    'target_id' =>
                        $checklist->id,
                ],

                'document-worker' => [
                    'operation' =>
                        'document_update',

                    'actor_id' =>
                        $context['operator']->id,

                    'target_id' =>
                        $document->id,

                    'value' =>
                        'Concurrent document mutation.',
                ],
            ]);

        $this->assertSame(
            'ok',
            $results[
                'document-worker'
            ]['status']
        );

        $this->assertContains(
            $results[
                'approve-worker'
            ]['status'],
            [
                'ok',
                'validation',
            ]
        );

        $document->refresh();
        $checklist->refresh();

        /*
         * Mutation selalu harus berhasil.
         *
         * revision 2 → 3
         * VALID → PENDING
         */
        $this->assertSame(
            3,
            $document->revision_no
        );

        $this->assertSame(
            DocumentStatus::PENDING,
            $document->status
        );

        if (
            $results[
                'approve-worker'
            ]['status']
            === 'ok'
        ) {
            /*
             * Approval memperoleh Document lock
             * lebih dulu.
             *
             * Approval historis sah pada revision
             * 2, tetapi mutation sesudahnya
             * membuat current readiness invalid.
             */
            $this->assertSame(
                ReadinessApprovalStatus::APPROVED,
                $checklist->status
            );
        } else {
            /*
             * Document mutation menang lebih dulu.
             *
             * Approval harus melihat revision
             * mismatch/PENDING document dan gagal.
             */
            $this->assertSame(
                ReadinessApprovalStatus
                    ::PENDING_APPROVAL,
                $checklist->status
            );
        }

        /*
         * Critical invariant:
         *
         * Tidak peduli serialization mana yang
         * menang, current Document Ready tidak
         * pernah boleh TRUE terhadap document
         * revision yang sudah berubah.
         */
        $readiness =
            app(
                ReadinessEvaluationService::class
            )->evaluateContributor(
                $context['forecast'],
                $context['kdkmp']->id,
                CarbonImmutable::parse(
                    '2026-08-10 10:00:00'
                )
            );

        $this->assertTrue(
            $readiness->isContributor
        );

        $this->assertFalse(
            $readiness->documentReady
        );
    }

    private function runWorkers(
        array $workerDefinitions,
    ): array {
        $barrierDirectory =
            storage_path(
                'framework/testing/'
                .'readiness-concurrency-'
                .Str::uuid()
            );

        File::ensureDirectoryExists(
            $barrierDirectory
        );

        $processes = [];

        try {
            foreach (
                $workerDefinitions
                as $workerId => $definition
            ) {
                $arguments = [
                    PHP_BINARY,

                    base_path(
                        'tests/Support/'
                        .'ReadinessConcurrencyWorker.php'
                    ),

                    $definition[
                        'operation'
                    ],

                    (string)
                    $definition[
                        'actor_id'
                    ],

                    (string)
                    $definition[
                        'target_id'
                    ],

                    $barrierDirectory,

                    $workerId,

                    CarbonImmutable::now()
                        ->format(
                            'Y-m-d H:i:s'
                        ),
                ];

                if (
                    array_key_exists(
                        'value',
                        $definition
                    )
                ) {
                    $arguments[] =
                        (string)
                        $definition[
                            'value'
                        ];
                }

                $process =
                    new Process(
                        $arguments
                    );

                $process->setTimeout(
                    30
                );

                $process->start();

                $processes[
                    $workerId
                ] =
                    $process;
            }

            $this->waitUntilWorkersReady(
                $barrierDirectory,
                array_keys(
                    $processes
                )
            );

            /*
             * Release all PostgreSQL sessions
             * from same filesystem barrier.
             */
            file_put_contents(
                $barrierDirectory
                .DIRECTORY_SEPARATOR
                .'go',
                'go'
            );

            foreach (
                $processes
                as $process
            ) {
                $process->wait();
            }

            $results = [];

            foreach (
                $processes
                as $workerId => $process
            ) {
                $resultPath =
                    $barrierDirectory
                    .DIRECTORY_SEPARATOR
                    .$workerId
                    .'.result.json';

                $this->assertFileExists(
                    $resultPath,
                    "Worker {$workerId} tidak "
                    ."menghasilkan result file.\n"
                    ."STDOUT:\n"
                    .$process->getOutput()
                    ."\nSTDERR:\n"
                    .$process->getErrorOutput()
                );

                $decoded =
                    json_decode(
                        (string)
                        file_get_contents(
                            $resultPath
                        ),
                        true
                    );

                $this->assertIsArray(
                    $decoded
                );

                /*
                 * Raw PostgreSQL/lock/bootstrap
                 * errors bukan business outcome.
                 */
                $this->assertNotSame(
                    'error',
                    $decoded[
                        'status'
                    ] ?? null,
                    'Worker database error: '
                    .json_encode(
                        $decoded
                    )
                );

                $this->assertNotSame(
                    'worker_timeout',
                    $decoded[
                        'status'
                    ] ?? null,
                    'Readiness concurrency '
                    .'barrier timeout.'
                );

                $results[
                    $workerId
                ] =
                    $decoded;
            }

            return $results;
        } finally {
            foreach (
                $processes
                as $process
            ) {
                if ($process->isRunning()) {
                    $process->stop(
                        1
                    );
                }
            }

            File::deleteDirectory(
                $barrierDirectory
            );
        }
    }

    private function waitUntilWorkersReady(
        string $barrierDirectory,
        array $workerIds,
    ): void {
        $deadline =
            microtime(true)
            + 15.0;

        while (true) {
            $allReady =
                collect(
                    $workerIds
                )
                    ->every(
                        fn (
                            string $workerId
                        ): bool =>
                            file_exists(
                                $barrierDirectory
                                .DIRECTORY_SEPARATOR
                                .$workerId
                                .'.ready'
                            )
                    );

            if ($allReady) {
                return;
            }

            if (
                microtime(true)
                >= $deadline
            ) {
                $this->fail(
                    'Readiness concurrency workers '
                    .'tidak mencapai barrier READY.'
                );
            }

            usleep(
                10_000
            );
        }
    }

    private function createSubmittedLogisticsChecklist(
        array $context,
        string $requirementCode,
    ): ReadinessChecklist {
        $this->createRequirement(
            context: $context,
            type:
                ReadinessType::LOGISTICS,
            code:
                $requirementCode
        );

        $checklist =
            app(
                ReadinessChecklistPreparationService::class
            )->createInitialDraft(
                $context['operator'],
                $context['forecast'],
                ReadinessType::LOGISTICS
            );

        $item =
            $checklist
                ->items
                ->firstOrFail();

        app(
            ReadinessChecklistWorkflowService::class
        )->updateItem(
            $context['operator'],
            $checklist,
            $item,
            [
                'is_satisfied' =>
                    true,

                'note' =>
                    'Concurrency fixture satisfied.',
            ]
        );

        return app(
            ReadinessChecklistWorkflowService::class
        )->submit(
            $context['operator'],
            $checklist
        );
    }

    private function createApprovedLogisticsChecklist(
        array $context,
        string $requirementCode,
    ): ReadinessChecklist {
        $checklist =
            $this->createSubmittedLogisticsChecklist(
                $context,
                $requirementCode
            );

        return app(
            ReadinessChecklistReviewService::class
        )->approve(
            $context['manager'],
            $checklist
        );
    }

    private function createOperationalContext(
        string $suffix,
    ): array {
        $unit =
            Unit::create([
                'code' =>
                    "kg-readiness-race-{$suffix}",

                'name' =>
                    "Kilogram Readiness Race {$suffix}",

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
                    "COM-READINESS-RACE-{$suffix}",

                'name' =>
                    "Commodity Readiness Race {$suffix}",

                'default_unit_id' =>
                    $unit->id,

                'harvest_behavior' =>
                    null,

                'notes' =>
                    null,

                'is_active' =>
                    true,
            ]);

        $admin =
            User::factory()->create();

        $sppg =
            $this->createOrganization(
                OrganizationType::SPPG,
                "SPPG-READINESS-RACE-{$suffix}"
            );

        $kdkmp =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-READINESS-RACE-{$suffix}"
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

        $forecast =
            DemandForecast::create([
                'sppg_organization_id' =>
                    $sppg->id,

                'commodity_id' =>
                    $commodity->id,

                'unit_id' =>
                    $unit->id,

                'forecast_code' =>
                    "FRC-READINESS-RACE-{$suffix}",

                'target_volume' =>
                    '300.000000',

                'required_start_at' =>
                    '2026-08-20 08:00:00',

                'required_end_at' =>
                    '2026-08-25 17:00:00',

                'freshness_interval_hours' =>
                    24,

                'status' =>
                    ForecastStatus::PUBLISHED,

                'notes' =>
                    'Real PostgreSQL readiness race fixture.',

                'published_at' =>
                    '2026-08-10 08:00:00',

                'version' =>
                    1,

                'created_by' =>
                    $sppgUser->id,

                'updated_by' =>
                    $sppgUser->id,
            ]);

        $producer =
            Producer::create([
                'organization_id' =>
                    $kdkmp->id,

                'producer_code' =>
                    "PROD-READINESS-RACE-{$suffix}",

                'name' =>
                    "Producer Race {$suffix}",

                'village' =>
                    'Desa Test',

                'district' =>
                    'Kecamatan Test',

                'contact_phone' =>
                    '081234567890',

                'notes' =>
                    'Readiness concurrency fixture.',

                'is_active' =>
                    true,

                'created_by' =>
                    $operator->id,
            ]);

        $commitment =
            SupplyCommitment::create([
                'forecast_id' =>
                    $forecast->id,

                'organization_id' =>
                    $kdkmp->id,

                'producer_id' =>
                    $producer->id,

                'expected_harvest_id' =>
                    null,

                'commodity_id' =>
                    $commodity->id,

                'active_version_id' =>
                    null,

                'lifecycle_status' =>
                    CommitmentLifecycleStatus::ACTIVE,

                'current_confidence' =>
                    SupplyConfidence::GREEN,

                'last_confidence_verified_at' =>
                    '2026-08-10 09:00:00',

                'created_by' =>
                    $operator->id,
            ]);

        $version =
            CommitmentVersion::create([
                'commitment_id' =>
                    $commitment->id,

                'version_no' =>
                    1,

                'min_volume' =>
                    '300.000000',

                'max_volume' =>
                    '350.000000',

                'unit_id' =>
                    $unit->id,

                'availability_start_at' =>
                    '2026-08-20 07:00:00',

                'availability_end_at' =>
                    '2026-08-25 18:00:00',

                'notes' =>
                    'Approved contributor fixture.',

                'approval_status' =>
                    CommitmentApprovalStatus::APPROVED,

                'change_reason' =>
                    null,

                'operator_justification' =>
                    null,

                'created_by' =>
                    $operator->id,

                'submitted_by' =>
                    $operator->id,

                'submitted_at' =>
                    '2026-08-10 08:30:00',

                'reviewed_by' =>
                    $manager->id,

                'reviewed_at' =>
                    '2026-08-10 09:00:00',

                'review_reason' =>
                    null,

                'approved_at' =>
                    '2026-08-10 09:00:00',

                'created_at' =>
                    '2026-08-10 08:00:00',
            ]);

        $commitment->update([
            'active_version_id' =>
                $version->id,
        ]);

        return [
            'unit' =>
                $unit,

            'commodity' =>
                $commodity,

            'admin' =>
                $admin,

            'sppg' =>
                $sppg,

            'kdkmp' =>
                $kdkmp,

            'operator' =>
                $operator,

            'manager' =>
                $manager,

            'forecast' =>
                $forecast,

            'commitment' =>
                $commitment,
        ];
    }

    private function createRequirement(
        array $context,
        ReadinessType $type,
        string $code,
        RequirementScope $scope =
            RequirementScope::FORECAST,
    ): ReadinessRequirement {
        return ReadinessRequirement::create([
            'readiness_type' =>
                $type,

            'requirement_code' =>
                $code,

            'label' =>
                "Requirement {$code}",

            'requirement_scope' =>
                $scope,

            'applies_to_organization_type' =>
                OrganizationType::KDKMP,

            /*
             * Commodity scoped supaya fixture
             * antartest tidak saling applicable.
             */
            'commodity_id' =>
                $context['commodity']->id,

            'is_required_default' =>
                true,

            'is_active' =>
                true,

            'sort_order' =>
                10,

            'config_json' =>
                null,
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
                'Lokasi Test',
        ]);
    }

    private function createKdkmpUser(
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
}