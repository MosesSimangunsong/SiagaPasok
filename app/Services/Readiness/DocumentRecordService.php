<?php

namespace App\Services\Readiness;

use App\Enums\AuditSource;
use App\Enums\DocumentStatus;
use App\Enums\ReadinessType;
use App\Enums\RequirementScope;
use App\Enums\ReadinessApprovalStatus;
use App\Models\DemandForecast;
use App\Models\ReadinessChecklist;
use App\Models\DocumentRecord;
use App\Models\ReadinessRequirement;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Notification\DerivedForecastStateObservationService;
use App\Services\Notification\OperationalNotificationService;
use Illuminate\Support\Collection;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

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
    
    private const AUDIT_EXPIRED =
    'DOCUMENT_RECORD_EXPIRED';

    public function __construct(
    private readonly AuditService
        $auditService,

    private readonly ReadinessEvaluationService
        $readinessEvaluationService,

    private readonly OperationalNotificationService
        $operationalNotificationService,

    private readonly DerivedForecastStateObservationService
        $derivedStateObservationService,
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

                $readyChecklistsBeforeMutation =
                    $this
                        ->resolveCurrentlyReadyDocumentChecklists(
                            $current
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

                foreach (
                    $readyChecklistsBeforeMutation
                    as $checklist
                ) {
                    $this->operationalNotificationService
                        ->readinessDependencyInvalidated(
                            checklist:
                                $checklist,

                            causeKey:
                                'document-'
                                .$current->id
                                .'-revision-'
                                .$current->revision_no,

                            message:
                                'Document Readiness perlu '
                                .'persetujuan ulang karena '
                                .'Document Record yang menjadi '
                                .'evidence telah diperbarui.',
                        );
                }

                /*
                 * revision_no berubah dan status kembali PENDING.
                 *
                 * Approved checklist yang membekukan revision lama
                 * langsung menjadi invalid secara derived.
                 */
                $this->observeAffectedForecastsAfterCommit(
                    $current
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
                if (
    $current->status
    === DocumentStatus::EXPIRED
) {
    throw ValidationException::withMessages([
        'status' => (
            'Document Record yang sudah EXPIRED '
            .'harus diperbarui terlebih dahulu '
            .'sebelum dapat divalidasi kembali.'
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

                /*
 * markValid juga menaikkan revision_no.
 *
 * Existing frozen readiness tidak otomatis direbase
 * ke revision terbaru.
 */
$this->observeAffectedForecastsAfterCommit(
    $current
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

                $readyChecklistsBeforeMutation =
                    $this
                        ->resolveCurrentlyReadyDocumentChecklists(
                            $current
                        );

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

                foreach (
                    $readyChecklistsBeforeMutation
                    as $checklist
                ) {
                    $this->operationalNotificationService
                        ->readinessDependencyInvalidated(
                            checklist:
                                $checklist,

                            causeKey:
                                'document-'
                                .$current->id
                                .'-revoked-revision-'
                                .$current->revision_no,

                            message:
                                'Document Readiness tidak lagi '
                                .'valid karena Document Record '
                                .'yang menjadi evidence telah '
                                .'dicabut.',
                        );
                }

                $this->observeAffectedForecastsAfterCommit(
                    $current
                );

                return $current->refresh();
            }
        );
    }


  public function expireIfDue(
    DocumentRecord $documentRecord,
    ?CarbonInterface $evaluatedAt = null,
): bool {
    $evaluationTime =
        $evaluatedAt === null
            ? CarbonImmutable::now()
            : CarbonImmutable::instance(
                $evaluatedAt
            );

    return DB::transaction(
        function () use (
            $documentRecord,
            $evaluationTime,
        ): bool {
            $current =
                DocumentRecord::query()
                    ->whereKey(
                        $documentRecord
                            ->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            /*
             * Idempotent terminal materialization.
             */
            if (
                $current->status
                === DocumentStatus::EXPIRED
            ) {
                return false;
            }

            /*
             * Hanya VALID record yang mengalami
             * automatic time expiry.
             *
             * PENDING/REVOKED tidak perlu diubah
             * menjadi EXPIRED karena sudah tidak
             * efektif secara operasional.
             */
            if (
                $current->status
                !== DocumentStatus::VALID
            ) {
                return false;
            }

            if (
                $current->expires_at === null
            ) {
                return false;
            }

            $expiresAt =
                CarbonImmutable::instance(
                    $current->expires_at
                );

            /*
             * Equality masih valid.
             *
             * EXPIRED baru materialized ketika
             * server evaluation time benar-benar
             * melewati expires_at.
             */
            if (
                ! $evaluationTime->gt(
                    $expiresAt
                )
            ) {
                return false;
            }

            /*
             * Resolve actionable readiness impact
             * SEBELUM status diubah.
             *
             * Canonical M08 time evaluation sudah
             * dapat melihat document invalid pada
             * evaluationTime ini meskipun persisted
             * status masih VALID.
             */
            $affectedChecklists =
                $this
                    ->resolveOperationalExpiryInvalidations(
                        $current,
                        $evaluationTime
                    );

            $before =
                $this->snapshot(
                    $current
                );

            /*
             * Expiry bukan payload revision.
             *
             * revision_no sengaja TIDAK dinaikkan.
             * Perubahan metadata berikutnya melalui
             * update() yang akan membuat revision baru.
             */
            $current->status =
                DocumentStatus::EXPIRED;

            $current->save();

            $reason =
                'Document Record otomatis EXPIRED '
                .'karena waktu evaluasi server '
                .'telah melewati expires_at '
                .$expiresAt
                    ->toIso8601String()
                .'.';

            $this->auditService->record(
                actor:
                    null,

                source:
                    AuditSource::SYSTEM,

                action:
                    self::AUDIT_EXPIRED,

                entity:
                    $current,

                previousValue:
                    $before,

                newValue:
                    $this->snapshot(
                        $current
                    ),

                reasonNote:
                    $reason,
            );

            foreach (
                $affectedChecklists
                as $checklist
            ) {
                $this
                    ->operationalNotificationService
                    ->readinessDependencyInvalidated(
                        checklist:
                            $checklist,

                        causeKey:
                            'document-'
                            .$current->id
                            .'-expired-revision-'
                            .$current
                                ->revision_no,

                        message:
                            'Document Readiness tidak '
                            .'lagi valid karena Document '
                            .'Record yang menjadi evidence '
                            .'telah kedaluwarsa.',
                    );
            }

            /*
             * Dapat menghasilkan:
             * - DOCUMENT_NOT_READY;
             * - RFP TRUE -> FALSE;
             * - atau no-op bila Forecast terkait
             *   sudah tidak operational/current.
             */
            $this
                ->observeAffectedForecastsAfterCommit(
                    $current
                );

            return true;
        }
    );
}

/**
 * @return Collection<int, ReadinessChecklist>
 */
private function resolveOperationalExpiryInvalidations(
    DocumentRecord $documentRecord,
    CarbonImmutable $evaluationTime,
): Collection {
    $checklists =
        ReadinessChecklist::query()
            ->where(
                'readiness_type',
                ReadinessType::DOCUMENT
                    ->value
            )
            ->where(
                'status',
                ReadinessApprovalStatus
                    ::APPROVED
                    ->value
            )
            ->where(
                'is_current_version',
                true
            )
            ->whereHas(
                'items',
                fn ($query) =>
                    $query->where(
                        'document_record_id',
                        $documentRecord->id
                    )
            )
            ->with([
                'forecast',
                'items',
            ])
            ->orderBy('id')
            ->get();

    return $checklists
        ->filter(
            function (
                ReadinessChecklist $checklist
            ) use (
                $documentRecord,
                $evaluationTime,
            ): bool {
                $forecast =
                    $checklist->forecast;

                if (
                    ! $forecast
                    || ! $forecast->isPublished()
                ) {
                    return false;
                }

                /*
                 * Setelah Forecast boundary lewat,
                 * tidak ada lagi readiness recovery
                 * action yang berguna untuk Forecast
                 * tersebut.
                 */
                if (
                    $evaluationTime->gt(
                        CarbonImmutable::instance(
                            $forecast
                                ->required_end_at
                        )
                    )
                ) {
                    return false;
                }

                if (
                    $checklist
                        ->forecast_version
                    !== $forecast->version
                ) {
                    return false;
                }

                /*
                 * Pastikan checklist benar-benar
                 * membekukan revision Document Record
                 * yang sedang expired.
                 */
                $referencesRevision =
                    $checklist
                        ->items
                        ->contains(
                            fn ($item): bool =>
                                (int)
                                $item
                                    ->document_record_id
                                === $documentRecord->id
                                && (int)
                                $item
                                    ->document_record_revision_no
                                === $documentRecord
                                    ->revision_no
                        );

                if (! $referencesRevision) {
                    return false;
                }

                $readiness =
                    $this
                        ->readinessEvaluationService
                        ->evaluateContributor(
                            $forecast,
                            $checklist
                                ->organization_id,
                            $evaluationTime
                        );

                /*
                 * Hanya current contributor dengan
                 * actual document validity failure
                 * yang menerima invalidation action.
                 *
                 * Forecast-ended / contributor-lost
                 * tidak menghasilkan alert tambahan.
                 */
                return
                    $readiness->isContributor
                    && ! $readiness
                        ->documentReady
                    && in_array(
                        'DOCUMENT_INVALID',
                        $readiness
                            ->documentReasonCodes,
                        true
                    );
            }
        )
        ->values();
}

    /**
     * @return Collection<int, ReadinessChecklist>
     */
    private function resolveCurrentlyReadyDocumentChecklists(
        DocumentRecord $documentRecord,
    ): Collection {
        $checklists =
            ReadinessChecklist::query()
            ->where(
                'readiness_type',
                ReadinessType::DOCUMENT
                    ->value
            )
            ->where(
                'status',
                ReadinessApprovalStatus
                    ::APPROVED
                    ->value
            )
            ->where(
                'is_current_version',
                true
            )
            ->whereHas(
                'items',
                fn ($query) =>
                    $query->where(
                        'document_record_id',
                        $documentRecord->id
                    )
            )
            ->with('forecast')
            ->orderBy('id')
            ->get();

        return $checklists
            ->filter(
            function (
                ReadinessChecklist $checklist
            ): bool {
                $forecast =
                    $checklist->forecast;

                if (! $forecast) {
                    return false;
                }

                $readiness =
                    $this
                        ->readinessEvaluationService
                        ->evaluateContributor(
                            $forecast,
                            $checklist
                                ->organization_id
                        );

                return $readiness
                    ->documentReady;
            }
            )
            ->values();
    }

    private function observeAffectedForecastsAfterCommit(
    DocumentRecord $documentRecord,
): void {
    /*
     * Hanya approved CURRENT Document Readiness
     * yang saat ini dapat memengaruhi canonical M08.
     *
     * Historical/PENDING/REJECTED checklists bukan
     * current readiness truth.
     */
    $forecastIds =
        ReadinessChecklist::query()
            ->where(
                'readiness_type',
                ReadinessType::DOCUMENT
                    ->value
            )
            ->where(
                'status',
                ReadinessApprovalStatus
                    ::APPROVED
                    ->value
            )
            ->where(
                'is_current_version',
                true
            )
            ->whereHas(
                'items',
                fn ($query) =>
                    $query->where(
                        'document_record_id',
                        $documentRecord->id
                    )
            )
            ->orderBy('forecast_id')
            ->pluck('forecast_id')
            ->map(
                static fn ($id): int =>
                    (int) $id
            )
            ->unique()
            ->values();

    foreach ($forecastIds as $forecastId) {
        $forecast =
            DemandForecast::query()
                ->find(
                    $forecastId
                );

        if (! $forecast) {
            continue;
        }

        $this->derivedStateObservationService
            ->observeAfterCommit(
                $forecast
            );
    }
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