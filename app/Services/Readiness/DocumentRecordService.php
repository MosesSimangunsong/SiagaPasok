<?php

namespace App\Services\Readiness;

use App\Enums\AuditSource;
use App\Enums\DocumentStatus;
use App\Enums\ReadinessType;
use App\Enums\RequirementScope;
use App\Models\DocumentRecord;
use App\Models\ReadinessRequirement;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class DocumentRecordService
{
    private const AUDIT_CREATED =
        'DOCUMENT_RECORD_CREATED';

    private const AUDIT_UPDATED =
        'DOCUMENT_RECORD_UPDATED';

    private const AUDIT_VALIDATED =
        'DOCUMENT_RECORD_VALIDATED';

    private const AUDIT_REVOKED =
        'DOCUMENT_RECORD_REVOKED';

    public function __construct(
        private readonly AuditService
            $auditService,
    ) {
    }

    public function create(
        User $actor,
        ReadinessRequirement $requirement,
        array $data,
    ): DocumentRecord {
        $this->assertOperator(
            $actor
        );

        $this->assertUsableRequirement(
            $actor,
            $requirement
        );

        $validated =
            $this->validatePayload(
                $data
            );

        return DB::transaction(
            function () use (
                $actor,
                $requirement,
                $validated,
            ): DocumentRecord {
                $record =
                    DocumentRecord::create([
                        'organization_id' =>
                            $actor->organization_id,

                        'requirement_id' =>
                            $requirement->id,

                        'document_name' =>
                            $validated[
                                'document_name'
                            ],

                        'reference_number' =>
                            $validated[
                                'reference_number'
                            ] ?? null,

                        'valid_from' =>
                            $validated[
                                'valid_from'
                            ] ?? null,

                        'expires_at' =>
                            $validated[
                                'expires_at'
                            ] ?? null,

                        /*
                         * Record baru belum dianggap
                         * valid secara operasional.
                         */
                        'status' =>
                            DocumentStatus::PENDING,

                        'notes' =>
                            $validated[
                                'notes'
                            ] ?? null,

                        'created_by' =>
                            $actor->id,

                        'revision_no' =>
                            1,
                    ]);

                $this->auditService->record(
                    actor: $actor,
                    source: AuditSource::USER,
                    action: self::AUDIT_CREATED,
                    entity: $record,
                    previousValue: null,
                    newValue:
                        $this->snapshot(
                            $record
                        ),
                );

                return $record;
            }
        );
    }

    public function update(
        User $actor,
        DocumentRecord $documentRecord,
        array $data,
    ): DocumentRecord {
        $this->assertOperator(
            $actor
        );

        return DB::transaction(
            function () use (
                $actor,
                $documentRecord,
                $data,
            ): DocumentRecord {
                $current =
                    DocumentRecord::query()
                        ->whereKey(
                            $documentRecord
                                ->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertOwnedByActor(
                    $actor,
                    $current
                );

                if (
                    $current->status
                    === DocumentStatus::REVOKED
                ) {
                    throw ValidationException::withMessages([
                        'status' => (
                            'Document Record yang sudah '
                            .'REVOKED tidak dapat diedit.'
                        ),
                    ]);
                }

                $candidate = [
                    'document_name' =>
                        array_key_exists(
                            'document_name',
                            $data
                        )
                            ? $data[
                                'document_name'
                            ]
                            : $current
                                ->document_name,

                    'reference_number' =>
                        array_key_exists(
                            'reference_number',
                            $data
                        )
                            ? $data[
                                'reference_number'
                            ]
                            : $current
                                ->reference_number,

                    'valid_from' =>
                        array_key_exists(
                            'valid_from',
                            $data
                        )
                            ? $data[
                                'valid_from'
                            ]
                            : $current
                                ->valid_from
                                ?->toDateTimeString(),

                    'expires_at' =>
                        array_key_exists(
                            'expires_at',
                            $data
                        )
                            ? $data[
                                'expires_at'
                            ]
                            : $current
                                ->expires_at
                                ?->toDateTimeString(),

                    'notes' =>
                        array_key_exists(
                            'notes',
                            $data
                        )
                            ? $data['notes']
                            : $current->notes,
                ];

                $validated =
                    $this->validatePayload(
                        $candidate
                    );

                $before =
                    $this->snapshot(
                        $current
                    );

                $current->fill([
                    'document_name' =>
                        $validated[
                            'document_name'
                        ],

                    'reference_number' =>
                        $validated[
                            'reference_number'
                        ] ?? null,

                    'valid_from' =>
                        $validated[
                            'valid_from'
                        ] ?? null,

                    'expires_at' =>
                        $validated[
                            'expires_at'
                        ] ?? null,

                    'notes' =>
                        $validated[
                            'notes'
                        ] ?? null,
                ]);

                if (! $current->isDirty()) {
                    return $current;
                }

                /*
                 * Perubahan metadata membatalkan
                 * validasi record sebelumnya.
                 *
                 * Operator harus menandai VALID
                 * kembali sebelum record dapat
                 * dipakai pada submit readiness.
                 */
                $current->status =
                    DocumentStatus::PENDING;

                $current->revision_no++;

                $current->save();

                $this->auditService->record(
                    actor: $actor,
                    source: AuditSource::USER,
                    action: self::AUDIT_UPDATED,
                    entity: $current,
                    previousValue: $before,
                    newValue:
                        $this->snapshot(
                            $current
                        ),
                );

                return $current->refresh();
            }
        );
    }

    public function markValid(
        User $actor,
        DocumentRecord $documentRecord,
    ): DocumentRecord {
        $this->assertOperator(
            $actor
        );

        return DB::transaction(
            function () use (
                $actor,
                $documentRecord,
            ): DocumentRecord {
                $current =
                    DocumentRecord::query()
                        ->whereKey(
                            $documentRecord
                                ->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertOwnedByActor(
                    $actor,
                    $current
                );

                if (
                    $current->status
                    === DocumentStatus::VALID
                ) {
                    return $current;
                }

                if (
                    $current->status
                    === DocumentStatus::REVOKED
                ) {
                    throw ValidationException::withMessages([
                        'status' => (
                            'Document Record yang sudah '
                            .'REVOKED tidak dapat '
                            .'dikembalikan menjadi VALID.'
                        ),
                    ]);
                }

                /*
                 * Revalidate persisted metadata.
                 */
                $this->validatePayload([
                    'document_name' =>
                        $current->document_name,

                    'reference_number' =>
                        $current->reference_number,

                    'valid_from' =>
                        $current
                            ->valid_from
                            ?->toDateTimeString(),

                    'expires_at' =>
                        $current
                            ->expires_at
                            ?->toDateTimeString(),

                    'notes' =>
                        $current->notes,
                ]);

                $before =
                    $this->snapshot(
                        $current
                    );

                $current->status =
                    DocumentStatus::VALID;

                $current->revision_no++;

                $current->save();

                $this->auditService->record(
                    actor: $actor,
                    source: AuditSource::USER,
                    action: self::AUDIT_VALIDATED,
                    entity: $current,
                    previousValue: $before,
                    newValue:
                        $this->snapshot(
                            $current
                        ),
                );

                return $current->refresh();
            }
        );
    }

    public function revoke(
        User $actor,
        DocumentRecord $documentRecord,
        string $reason,
    ): DocumentRecord {
        $this->assertOperator(
            $actor
        );

        $reason =
            trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => (
                    'Alasan revoke Document Record '
                    .'wajib diisi.'
                ),
            ]);
        }

        return DB::transaction(
            function () use (
                $actor,
                $documentRecord,
                $reason,
            ): DocumentRecord {
                $current =
                    DocumentRecord::query()
                        ->whereKey(
                            $documentRecord
                                ->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertOwnedByActor(
                    $actor,
                    $current
                );

                if (
                    $current->status
                    === DocumentStatus::REVOKED
                ) {
                    return $current;
                }

                $before =
                    $this->snapshot(
                        $current
                    );

                $current->status =
                    DocumentStatus::REVOKED;

                $current->revision_no++;

                $current->save();

                $this->auditService->record(
                    actor: $actor,
                    source: AuditSource::USER,
                    action: self::AUDIT_REVOKED,
                    entity: $current,
                    previousValue: $before,
                    newValue:
                        $this->snapshot(
                            $current
                        ),
                    reasonNote: $reason,
                );

                return $current->refresh();
            }
        );
    }

    private function validatePayload(
        array $data,
    ): array {
        return Validator::make(
            $data,
            [
                'document_name' => [
                    'required',
                    'string',
                ],

                'reference_number' => [
                    'nullable',
                    'string',
                ],

                'valid_from' => [
                    'nullable',
                    'date',
                ],

                'expires_at' => [
                    'nullable',
                    'date',
                ],

                'notes' => [
                    'nullable',
                    'string',
                ],
            ]
        )
            ->after(
                function ($validator) use (
                    $data,
                ): void {
                    if (
                        empty($data['valid_from'])
                        || empty($data['expires_at'])
                    ) {
                        return;
                    }

                    if (
                        strtotime(
                            $data['expires_at']
                        )
                        < strtotime(
                            $data['valid_from']
                        )
                    ) {
                        $validator->errors()->add(
                            'expires_at',
                            'Tanggal akhir berlaku tidak '
                            .'boleh sebelum tanggal mulai.'
                        );
                    }
                }
            )
            ->validate();
    }

    private function assertOperator(
        User $actor,
    ): void {
        if (
            ! $actor->isKdkmpOperator()
            || ! $actor->hasValidIdentityContext()
        ) {
            throw new AuthorizationException(
                'Hanya KDKMP Operator aktif yang '
                .'dapat mengelola Document Record.'
            );
        }
    }

    private function assertUsableRequirement(
        User $actor,
        ReadinessRequirement $requirement,
    ): void {
        if (
            ! $requirement->is_active
            || $requirement->readiness_type
                !== ReadinessType::DOCUMENT
            || $requirement->requirement_scope
                !== RequirementScope::ORGANIZATION
        ) {
            throw ValidationException::withMessages([
                'requirement' => (
                    'Document Record hanya dapat dibuat '
                    .'untuk requirement DOCUMENT '
                    .'organization-level yang aktif.'
                ),
            ]);
        }

        $organization =
            $actor->organization;

        if (
            ! $organization
            || ! $organization->is_active
            || ! $organization->isKdkmp()
            || $requirement
                ->applies_to_organization_type
                !== $organization
                    ->organization_type
        ) {
            throw new AuthorizationException(
                'Requirement tidak berlaku untuk '
                .'organisasi Anda.'
            );
        }
    }

    private function assertOwnedByActor(
        User $actor,
        DocumentRecord $record,
    ): void {
        if (
            $actor->organization_id
            !== $record->organization_id
        ) {
            throw new AuthorizationException(
                'Document Record tersebut bukan '
                .'milik organisasi Anda.'
            );
        }
    }

    private function snapshot(
        DocumentRecord $record,
    ): array {
        return [
            'id' =>
                $record->id,

            'organization_id' =>
                $record->organization_id,

            'requirement_id' =>
                $record->requirement_id,

            'document_name' =>
                $record->document_name,

            'reference_number' =>
                $record->reference_number,

            'valid_from' =>
                $record
                    ->valid_from
                    ?->toIso8601String(),

            'expires_at' =>
                $record
                    ->expires_at
                    ?->toIso8601String(),

            'status' =>
                $record
                    ->status
                    ->value,

            'revision_no' =>
                $record->revision_no,

            'notes' =>
                $record->notes,

            'created_by' =>
                $record->created_by,
        ];
    }
}