<?php

namespace Tests\Feature\Readiness;

use App\Enums\DocumentStatus;
use App\Enums\OrganizationType;
use App\Enums\ReadinessType;
use App\Enums\RequirementScope;
use App\Enums\UserRole;
use App\Models\Commodity;
use App\Models\DocumentRecord;
use App\Models\Organization;
use App\Models\ReadinessRequirement;
use App\Models\Unit;
use App\Models\User;
use App\Services\Readiness\DocumentRecordService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DocumentRecordTest extends TestCase
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

    public function test_operator_can_create_pending_document_record_with_initial_revision(): void
    {
        $context =
            $this->createContext(
                'CREATE'
            );

        $requirement =
            $this->createRequirement(
                context: $context,
                code: 'DOC-CREATE'
            );

        $record =
            $this->service()
                ->create(
                    $context['operator'],
                    $requirement,
                    [
                        'document_name' =>
                            'Dokumen Legal Koperasi',

                        'reference_number' =>
                            'LEGAL-001',

                        'valid_from' =>
                            '2026-08-01 00:00:00',

                        'expires_at' =>
                            '2026-12-31 23:59:59',

                        'notes' =>
                            'Document fixture.',
                    ]
                );

        $this->assertSame(
            $context['kdkmp']->id,
            $record->organization_id
        );

        $this->assertSame(
            $requirement->id,
            $record->requirement_id
        );

        $this->assertSame(
            DocumentStatus::PENDING,
            $record->status
        );

        $this->assertSame(
            1,
            $record->revision_no
        );

        $this->assertSame(
            $context['operator']->id,
            $record->created_by
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'entity_type' =>
                    DocumentRecord::class,

                'entity_id' =>
                    $record->id,

                'actor_user_id' =>
                    $context['operator']->id,

                'action' =>
                    'DOCUMENT_RECORD_CREATED',
            ]
        );
    }

    public function test_manager_cannot_create_document_record(): void
    {
        $context =
            $this->createContext(
                'MANAGER-CREATE'
            );

        $requirement =
            $this->createRequirement(
                context: $context,
                code: 'DOC-MANAGER-CREATE'
            );

        try {
            $this->service()
                ->create(
                    $context['manager'],
                    $requirement,
                    [
                        'document_name' =>
                            'Illegal Manager Document',
                    ]
                );

            $this->fail(
                'KDKMP Manager berhasil membuat '
                .'Document Record.'
            );
        } catch (
            AuthorizationException $exception
        ) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseCount(
            'document_records',
            0
        );
    }

    public function test_document_record_can_only_be_created_for_active_organization_level_document_requirement(): void
    {
        $context =
            $this->createContext(
                'REQUIREMENT'
            );

        $logisticsRequirement =
            $this->createRequirement(
                context: $context,
                code: 'LOG-WRONG-TYPE',
                type: ReadinessType::LOGISTICS
            );

        $forecastDocumentRequirement =
            $this->createRequirement(
                context: $context,
                code: 'DOC-FORECAST-SCOPE',
                scope: RequirementScope::FORECAST
            );

        $inactiveRequirement =
            $this->createRequirement(
                context: $context,
                code: 'DOC-INACTIVE'
            );

        $inactiveRequirement->update([
            'is_active' =>
                false,
        ]);

        foreach (
            [
                $logisticsRequirement,
                $forecastDocumentRequirement,
                $inactiveRequirement,
            ]
            as $requirement
        ) {
            try {
                $this->service()
                    ->create(
                        $context['operator'],
                        $requirement,
                        [
                            'document_name' =>
                                'Invalid Requirement Document',
                        ]
                    );

                $this->fail(
                    'Document Record berhasil dibuat '
                    .'dengan requirement yang tidak valid.'
                );
            } catch (
                ValidationException $exception
            ) {
                $this->assertArrayHasKey(
                    'requirement',
                    $exception->errors()
                );
            }
        }

        $this->assertDatabaseCount(
            'document_records',
            0
        );
    }

    public function test_mark_valid_changes_status_and_increments_revision_once(): void
    {
        $context =
            $this->createContext(
                'VALID'
            );

        $requirement =
            $this->createRequirement(
                context: $context,
                code: 'DOC-VALID'
            );

        $record =
            $this->createRecord(
                $context,
                $requirement
            );

        $this->assertSame(
            1,
            $record->revision_no
        );

        $record =
            $this->service()
                ->markValid(
                    $context['operator'],
                    $record
                );

        $this->assertSame(
            DocumentStatus::VALID,
            $record->status
        );

        $this->assertSame(
            2,
            $record->revision_no
        );

        /*
         * Idempotent repeat.
         */
        $repeated =
            $this->service()
                ->markValid(
                    $context['operator'],
                    $record
                );

        $this->assertSame(
            DocumentStatus::VALID,
            $repeated->status
        );

        $this->assertSame(
            2,
            $repeated->revision_no
        );

        $this->assertSame(
            1,
            \App\Models\AuditLog::query()
                ->where(
                    'entity_type',
                    DocumentRecord::class
                )
                ->where(
                    'entity_id',
                    $record->id
                )
                ->where(
                    'action',
                    'DOCUMENT_RECORD_VALIDATED'
                )
                ->count()
        );
    }

    public function test_editing_valid_document_invalidates_it_to_pending_and_increments_revision(): void
    {
        $context =
            $this->createContext(
                'UPDATE'
            );

        $requirement =
            $this->createRequirement(
                context: $context,
                code: 'DOC-UPDATE'
            );

        $record =
            $this->createValidRecord(
                $context,
                $requirement
            );

        $this->assertSame(
            DocumentStatus::VALID,
            $record->status
        );

        $this->assertSame(
            2,
            $record->revision_no
        );

        $record =
            $this->service()
                ->update(
                    $context['operator'],
                    $record,
                    [
                        'reference_number' =>
                            'LEGAL-UPDATED',

                        'notes' =>
                            'Updated metadata.',
                    ]
                );

        $this->assertSame(
            DocumentStatus::PENDING,
            $record->status
        );

        $this->assertSame(
            3,
            $record->revision_no
        );

        $this->assertSame(
            'LEGAL-UPDATED',
            $record->reference_number
        );

        $this->assertSame(
            'Updated metadata.',
            $record->notes
        );
    }

    public function test_noop_update_does_not_increment_revision(): void
    {
        $context =
            $this->createContext(
                'NOOP'
            );

        $requirement =
            $this->createRequirement(
                context: $context,
                code: 'DOC-NOOP'
            );

        $record =
            $this->createValidRecord(
                $context,
                $requirement
            );

        $originalRevision =
            $record->revision_no;

        $originalUpdatedAt =
            $record->updated_at;

        $result =
            $this->service()
                ->update(
                    $context['operator'],
                    $record,
                    [
                        'document_name' =>
                            $record->document_name,

                        'reference_number' =>
                            $record->reference_number,

                        'valid_from' =>
                            $record
                                ->valid_from
                                ?->toDateTimeString(),

                        'expires_at' =>
                            $record
                                ->expires_at
                                ?->toDateTimeString(),

                        'notes' =>
                            $record->notes,
                    ]
                );

        $this->assertSame(
            $originalRevision,
            $result->revision_no
        );

        $this->assertSame(
            DocumentStatus::VALID,
            $result->status
        );

        $this->assertTrue(
            $result->updated_at->equalTo(
                $originalUpdatedAt
            )
        );
    }

    public function test_cross_organization_document_mutation_is_blocked(): void
    {
        $context =
            $this->createContext(
                'CROSS-ORG'
            );

        $requirement =
            $this->createRequirement(
                context: $context,
                code: 'DOC-CROSS-ORG'
            );

        $record =
            $this->createValidRecord(
                $context,
                $requirement
            );

        $otherOrganization =
            $this->createOrganization(
                OrganizationType::KDKMP,
                'KDKMP-DOCUMENT-CROSS-ORG-OTHER'
            );

        $otherOperator =
            $this->createKdkmpUser(
                $otherOrganization,
                UserRole::KDKMP_OPERATOR
            );

        try {
            $this->service()
                ->update(
                    $otherOperator,
                    $record,
                    [
                        'notes' =>
                            'Cross organization mutation.',
                    ]
                );

            $this->fail(
                'Operator organisasi lain berhasil '
                .'mengubah Document Record.'
            );
        } catch (
            AuthorizationException $exception
        ) {
            $this->assertTrue(true);
        }

        try {
            $this->service()
                ->revoke(
                    $otherOperator,
                    $record,
                    'Cross organization revoke.'
                );

            $this->fail(
                'Operator organisasi lain berhasil '
                .'merevoke Document Record.'
            );
        } catch (
            AuthorizationException $exception
        ) {
            $this->assertTrue(true);
        }

        $record->refresh();

        $this->assertSame(
            DocumentStatus::VALID,
            $record->status
        );

        $this->assertSame(
            2,
            $record->revision_no
        );
    }

    public function test_revoke_requires_reason_and_is_terminal(): void
    {
        $context =
            $this->createContext(
                'REVOKE'
            );

        $requirement =
            $this->createRequirement(
                context: $context,
                code: 'DOC-REVOKE'
            );

        $record =
            $this->createValidRecord(
                $context,
                $requirement
            );

        try {
            $this->service()
                ->revoke(
                    $context['operator'],
                    $record,
                    '   '
                );

            $this->fail(
                'Document Record berhasil direvoke '
                .'tanpa reason.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'reason',
                $exception->errors()
            );
        }

        $record->refresh();

        $this->assertSame(
            DocumentStatus::VALID,
            $record->status
        );

        $record =
            $this->service()
                ->revoke(
                    $context['operator'],
                    $record,
                    'Dokumen dinyatakan tidak berlaku.'
                );

        $this->assertSame(
            DocumentStatus::REVOKED,
            $record->status
        );

        $this->assertSame(
            3,
            $record->revision_no
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'entity_type' =>
                    DocumentRecord::class,

                'entity_id' =>
                    $record->id,

                'action' =>
                    'DOCUMENT_RECORD_REVOKED',

                'reason_note' =>
                    'Dokumen dinyatakan tidak berlaku.',
            ]
        );

        /*
         * Repeat revoke idempotent.
         */
        $repeated =
            $this->service()
                ->revoke(
                    $context['operator'],
                    $record,
                    'Second revoke.'
                );

        $this->assertSame(
            3,
            $repeated->revision_no
        );

        /*
         * REVOKED terminal.
         */
        try {
            $this->service()
                ->update(
                    $context['operator'],
                    $record,
                    [
                        'notes' =>
                            'Illegal edit after revoke.',
                    ]
                );

            $this->fail(
                'REVOKED Document Record berhasil diedit.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'status',
                $exception->errors()
            );
        }

        try {
            $this->service()
                ->markValid(
                    $context['operator'],
                    $record
                );

            $this->fail(
                'REVOKED Document Record berhasil '
                .'dikembalikan menjadi VALID.'
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

    public function test_expiry_cannot_be_before_valid_from(): void
    {
        $context =
            $this->createContext(
                'DATES'
            );

        $requirement =
            $this->createRequirement(
                context: $context,
                code: 'DOC-DATES'
            );

        try {
            $this->service()
                ->create(
                    $context['operator'],
                    $requirement,
                    [
                        'document_name' =>
                            'Invalid Date Document',

                        'valid_from' =>
                            '2026-09-01 00:00:00',

                        'expires_at' =>
                            '2026-08-01 00:00:00',
                    ]
                );

            $this->fail(
                'Document Record dengan expires_at '
                .'sebelum valid_from berhasil dibuat.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'expires_at',
                $exception->errors()
            );
        }

        $this->assertDatabaseCount(
            'document_records',
            0
        );
    }

    private function service():
        DocumentRecordService
    {
        return app(
            DocumentRecordService::class
        );
    }

    private function createContext(
        string $suffix,
    ): array {
        $unit =
            Unit::create([
                'code' =>
                    "kg-document-{$suffix}",

                'name' =>
                    "Kilogram Document {$suffix}",

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
                    "COM-DOCUMENT-{$suffix}",

                'name' =>
                    "Commodity Document {$suffix}",

                'default_unit_id' =>
                    $unit->id,

                'harvest_behavior' =>
                    null,

                'notes' =>
                    null,

                'is_active' =>
                    true,
            ]);

        $kdkmp =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-DOCUMENT-{$suffix}"
            );

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

        return [
            'unit' =>
                $unit,

            'commodity' =>
                $commodity,

            'kdkmp' =>
                $kdkmp,

            'operator' =>
                $operator,

            'manager' =>
                $manager,
        ];
    }

    private function createRequirement(
        array $context,
        string $code,
        ReadinessType $type =
            ReadinessType::DOCUMENT,
        RequirementScope $scope =
            RequirementScope::ORGANIZATION,
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

            'commodity_id' =>
                null,

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

    private function createRecord(
        array $context,
        ReadinessRequirement $requirement,
    ): DocumentRecord {
        return $this->service()
            ->create(
                $context['operator'],
                $requirement,
                [
                    'document_name' =>
                        'Dokumen Operasional',

                    'reference_number' =>
                        'DOC-001',

                    'valid_from' =>
                        '2026-08-01 00:00:00',

                    'expires_at' =>
                        '2026-12-31 23:59:59',

                    'notes' =>
                        'Document test fixture.',
                ]
            );
    }

    private function createValidRecord(
        array $context,
        ReadinessRequirement $requirement,
    ): DocumentRecord {
        $record =
            $this->createRecord(
                $context,
                $requirement
            );

        return $this->service()
            ->markValid(
                $context['operator'],
                $record
            );
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