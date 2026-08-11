<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditSource;
use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Services\Audit\AuditService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class AuditFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse(
                '2026-08-11 11:00:00'
            )
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_audit_records_actor_snapshots_before_after_reason_and_time(): void
    {
        $organization =
            $this->createOrganization(
                'AUDIT-ACTOR'
            );

        $actor =
            User::factory()->create([
                'organization_id' =>
                    $organization->id,

                'role' =>
                    UserRole::KDKMP_OPERATOR,

                'is_active' =>
                    true,
            ]);

        $entity =
            $this->createOrganization(
                'AUDIT-ENTITY'
            );

        $log =
            $this->service()
                ->record(
                    actor:
                        $actor,

                    source:
                        AuditSource::USER,

                    action:
                        'TEST_CHANGED',

                    entity:
                        $entity,

                    previousValue: [
                        'state' =>
                            'BEFORE',
                    ],

                    newValue: [
                        'state' =>
                            'AFTER',
                    ],

                    reasonNote:
                        'Audit foundation test.',
                );

        $this->assertSame(
            $actor->id,
            $log->actor_user_id
        );

        $this->assertSame(
            UserRole::KDKMP_OPERATOR
                ->value,
            $log
                ->actor_role_snapshot
        );

        $this->assertSame(
            $organization->id,
            $log
                ->actor_organization_id
        );

        $this->assertSame(
            AuditSource::USER,
            $log->source
        );

        $this->assertSame(
            'TEST_CHANGED',
            $log->action
        );

        $this->assertSame(
            [
                'state' =>
                    'BEFORE',
            ],
            $log->previous_value_json
        );

        $this->assertSame(
            [
                'state' =>
                    'AFTER',
            ],
            $log->new_value_json
        );

        $this->assertSame(
            'Audit foundation test.',
            $log->reason_note
        );

        $this->assertTrue(
            $log
                ->occurred_at
                ->equalTo(
                    CarbonImmutable::now()
                )
        );

        /*
         * Snapshot role tidak mengikuti
         * perubahan actor setelah event.
         */
        $actor->update([
            'role' =>
                UserRole::KDKMP_MANAGER,
        ]);

        $this->assertSame(
            UserRole::KDKMP_OPERATOR
                ->value,
            $log
                ->fresh()
                ->actor_role_snapshot
        );
    }

    public function test_system_audit_supports_null_actor(): void
    {
        $entity =
            $this->createOrganization(
                'AUDIT-SYSTEM'
            );

        $log =
            $this->service()
                ->record(
                    actor:
                        null,

                    source:
                        AuditSource::SYSTEM,

                    action:
                        'SYSTEM_TEST_EVENT',

                    entity:
                        $entity,

                    previousValue:
                        null,

                    newValue: [
                        'state' =>
                            'UPDATED',
                    ],
                );

        $this->assertNull(
            $log->actor_user_id
        );

        $this->assertNull(
            $log
                ->actor_role_snapshot
        );

        $this->assertNull(
            $log
                ->actor_organization_id
        );

        $this->assertSame(
            AuditSource::SYSTEM,
            $log->source
        );
    }

    public function test_failed_transaction_rolls_back_audit_row(): void
    {
        $entity =
            $this->createOrganization(
                'AUDIT-ROLLBACK'
            );

        try {
            DB::transaction(
                function () use (
                    $entity
                ): void {
                    $this->service()
                        ->record(
                            actor:
                                null,

                            source:
                                AuditSource::SYSTEM,

                            action:
                                'ROLLBACK_TEST',

                            entity:
                                $entity,

                            newValue: [
                                'state' =>
                                    'SHOULD_ROLLBACK',
                            ],
                        );

                    throw new RuntimeException(
                        'Force rollback.'
                    );
                }
            );

            $this->fail(
                'Transaction seharusnya rollback.'
            );
        } catch (
            RuntimeException $exception
        ) {
            $this->assertSame(
                'Force rollback.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseMissing(
            'audit_logs',
            [
                'action' =>
                    'ROLLBACK_TEST',
            ]
        );
    }

    public function test_audit_log_cannot_be_updated(): void
    {
        $entity =
            $this->createOrganization(
                'AUDIT-UPDATE-BLOCK'
            );

        $log =
            $this->service()
                ->record(
                    actor:
                        null,

                    source:
                        AuditSource::SYSTEM,

                    action:
                        'IMMUTABLE_TEST',

                    entity:
                        $entity,
                );

        $this->expectException(
            LogicException::class
        );

        $log->update([
            'action' =>
                'TAMPERED',
        ]);
    }

    public function test_audit_log_cannot_be_deleted(): void
    {
        $entity =
            $this->createOrganization(
                'AUDIT-DELETE-BLOCK'
            );

        $log =
            $this->service()
                ->record(
                    actor:
                        null,

                    source:
                        AuditSource::SYSTEM,

                    action:
                        'IMMUTABLE_TEST',

                    entity:
                        $entity,
                );

        $this->expectException(
            LogicException::class
        );

        $log->delete();
    }

    private function service():
        AuditService
    {
        return app(
            AuditService::class
        );
    }

    private function createOrganization(
        string $suffix,
    ): Organization {
        return Organization::create([
            'code' =>
                "ORG-{$suffix}",

            'name' =>
                "Organization {$suffix}",

            'organization_type' =>
                OrganizationType::KDKMP,

            'is_active' =>
                true,

            'general_location' =>
                'Lokasi Audit Test',
        ]);
    }
}