<?php

namespace App\Services\Readiness;

use App\Enums\AuditSource;
use App\Enums\ReadinessApprovalStatus;
use App\Enums\ReadinessType;
use App\Enums\RequirementScope;
use App\Models\DemandForecast;
use App\Models\DocumentRecord;
use App\Models\ReadinessChecklist;
use App\Models\ReadinessItem;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Supply\SupplyMetricsService;
use App\Services\Notification\DerivedForecastStateObservationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReadinessChecklistReviewService
{
    private const AUDIT_APPROVED =
        'READINESS_APPROVED';

    private const AUDIT_REJECTED =
        'READINESS_REJECTED';

    public function __construct(
    private readonly SupplyMetricsService
        $supplyMetricsService,

    private readonly DocumentRecordValidityService
        $documentRecordValidityService,

    private readonly AuditService
        $auditService,

    private readonly DerivedForecastStateObservationService
        $derivedStateObservationService,
) {
}

    public function approve(
        User $actor,
        ReadinessChecklist $checklist,
    ): ReadinessChecklist {
        $this->assertManager(
            $actor
        );

        return DB::transaction(
            function () use (
                $actor,
                $checklist,
            ): ReadinessChecklist {
                /*
                 * Semua operasi readiness yang
                 * menyentuh checklist memakai
                 * checklist row sebagai lock pertama.
                 *
                 * Ini konsisten dengan submit flow.
                 */
                $currentChecklist =
                    ReadinessChecklist::query()
                        ->whereKey(
                            $checklist->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertOwnedByActor(
                    $actor,
                    $currentChecklist
                );

                /*
                 * Repeat approval bersifat idempotent.
                 * Tidak membuat audit row kedua.
                 */
                if (
                    $currentChecklist
                        ->isApproved()
                ) {
                    return $currentChecklist;
                }

                $this->assertCurrentVersion(
                    $currentChecklist
                );

                if (
                    ! $currentChecklist
                        ->isPendingApproval()
                ) {
                    throw ValidationException::withMessages([
                        'status' => (
                            'Hanya Readiness Checklist '
                            .'PENDING_APPROVAL yang dapat '
                            .'disetujui.'
                        ),
                    ]);
                }

                $this->assertMakerChecker(
                    $actor,
                    $currentChecklist
                );

                if (
                    $currentChecklist
                        ->submitted_at
                    === null
                    || $currentChecklist
                        ->submitted_by
                    === null
                ) {
                    throw ValidationException::withMessages([
                        'submission' => (
                            'Readiness Checklist memiliki '
                            .'submission state yang tidak valid.'
                        ),
                    ]);
                }

                /*
                 * Forecast row dikunci setelah
                 * checklist, sama dengan submit.
                 *
                 * Approval tidak boleh lolos bersamaan
                 * dengan Forecast revision tanpa
                 * melihat state/version terbaru.
                 */
                $forecast =
                    DemandForecast::query()
                        ->whereKey(
                            $currentChecklist
                                ->forecast_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (! $forecast->isPublished()) {
                    throw ValidationException::withMessages([
                        'forecast' => (
                            'Readiness hanya dapat disetujui '
                            .'untuk Forecast PUBLISHED.'
                        ),
                    ]);
                }

                if (
                    $currentChecklist
                        ->forecast_version
                    !== $forecast->version
                ) {
                    throw ValidationException::withMessages([
                        'forecast_version' => (
                            'Forecast telah direvisi setelah '
                            .'Readiness Checklist dibuat. '
                            .'Checklist ini tidak dapat '
                            .'disetujui.'
                        ),
                    ]);
                }

                /*
                 * Contributor dapat berubah karena
                 * confidence / fallback berubah.
                 *
                 * Approval harus memakai contributor
                 * truth terbaru dari M06/M07.
                 */
                $this->assertCurrentContributor(
                    $forecast,
                    $currentChecklist
                        ->organization_id
                );

                $items =
                    ReadinessItem::query()
                        ->where(
                            'readiness_checklist_id',
                            $currentChecklist->id
                        )
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                $items->load(
                    'requirement'
                );

                if ($items->isEmpty()) {
                    throw ValidationException::withMessages([
                        'requirements' => (
                            'Checklist tidak memiliki '
                            .'requirement yang dapat '
                            .'dievaluasi.'
                        ),
                    ]);
                }

                $documentRecords =
                    $this->lockReferencedDocuments(
                        $items
                    );

                /*
                 * Revalidate seluruh frozen payload.
                 *
                 * Manager tidak mempercayai hasil
                 * validasi lama ketika Operator submit.
                 */
                $this->assertItemsApprovable(
                    $currentChecklist,
                    $forecast,
                    $items,
                    $documentRecords
                );

                $before =
                    $this->snapshotChecklist(
                        $currentChecklist,
                        $items
                    );

                $reviewedAt =
                    now();

                $currentChecklist->update([
                    'status' =>
                        ReadinessApprovalStatus
                            ::APPROVED,

                    'reviewed_by' =>
                        $actor->id,

                    'reviewed_at' =>
                        $reviewedAt,

                    'review_reason' =>
                        null,

                    'approved_at' =>
                        $reviewedAt,
                ]);

                $this->auditService->record(
                    actor: $actor,
                    source: AuditSource::USER,
                    action: self::AUDIT_APPROVED,
                    entity: $currentChecklist,
                    previousValue: $before,
                    newValue:
                        $this->snapshotChecklist(
                            $currentChecklist,
                            $items
                        ),
                );

                /*
 * Readiness becomes canonical only after Manager
 * approval. Submit/PENDING_APPROVAL must not trigger
 * RFP recalculation as if it were approved.
 */
$this->derivedStateObservationService
    ->observeAfterCommit(
        $forecast
    );

                return $currentChecklist
                    ->refresh()
                    ->load([
                        'items.requirement',
                    ]);
            }
        );
    }

    public function reject(
        User $actor,
        ReadinessChecklist $checklist,
        string $reason,
    ): ReadinessChecklist {
        $this->assertManager(
            $actor
        );

        $reason =
            trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'review_reason' => (
                    'Alasan penolakan Readiness '
                    .'wajib diisi.'
                ),
            ]);
        }

        return DB::transaction(
            function () use (
                $actor,
                $checklist,
                $reason,
            ): ReadinessChecklist {
                $currentChecklist =
                    ReadinessChecklist::query()
                        ->whereKey(
                            $checklist->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertOwnedByActor(
                    $actor,
                    $currentChecklist
                );

                /*
                 * Repeat rejection bersifat idempotent.
                 */
                if (
                    $currentChecklist
                        ->isRejected()
                ) {
                    return $currentChecklist;
                }

                $this->assertCurrentVersion(
                    $currentChecklist
                );

                if (
                    ! $currentChecklist
                        ->isPendingApproval()
                ) {
                    throw ValidationException::withMessages([
                        'status' => (
                            'Hanya Readiness Checklist '
                            .'PENDING_APPROVAL yang dapat '
                            .'ditolak.'
                        ),
                    ]);
                }

                $this->assertMakerChecker(
                    $actor,
                    $currentChecklist
                );

                $items =
                    ReadinessItem::query()
                        ->where(
                            'readiness_checklist_id',
                            $currentChecklist->id
                        )
                        ->orderBy('id')
                        ->get();

                $before =
                    $this->snapshotChecklist(
                        $currentChecklist,
                        $items
                    );

                $currentChecklist->update([
                    'status' =>
                        ReadinessApprovalStatus
                            ::REJECTED,

                    'reviewed_by' =>
                        $actor->id,

                    'reviewed_at' =>
                        now(),

                    'review_reason' =>
                        $reason,

                    'approved_at' =>
                        null,
                ]);

                $this->auditService->record(
                    actor: $actor,
                    source: AuditSource::USER,
                    action: self::AUDIT_REJECTED,
                    entity: $currentChecklist,
                    previousValue: $before,
                    newValue:
                        $this->snapshotChecklist(
                            $currentChecklist,
                            $items
                        ),
                    reasonNote: $reason,
                );

                return $currentChecklist
                    ->refresh()
                    ->load([
                        'items.requirement',
                    ]);
            }
        );
    }

    private function assertManager(
        User $actor,
    ): void {
        if (
            ! $actor->isKdkmpManager()
            || ! $actor->hasValidIdentityContext()
        ) {
            throw new AuthorizationException(
                'Hanya KDKMP Manager aktif yang '
                .'dapat mereview Readiness.'
            );
        }
    }

    private function assertOwnedByActor(
        User $actor,
        ReadinessChecklist $checklist,
    ): void {
        if (
            $actor->organization_id
            !== $checklist->organization_id
        ) {
            throw new AuthorizationException(
                'Readiness Checklist tersebut bukan '
                .'milik organisasi Anda.'
            );
        }
    }

    private function assertCurrentVersion(
        ReadinessChecklist $checklist,
    ): void {
        if (
            ! $checklist->is_current_version
        ) {
            throw ValidationException::withMessages([
                'version' => (
                    'Historical Readiness Checklist '
                    .'tidak dapat direview.'
                ),
            ]);
        }
    }

    private function assertMakerChecker(
        User $actor,
        ReadinessChecklist $checklist,
    ): void {
        /*
         * Role boundary seharusnya sudah membuat
         * Operator dan Manager berbeda.
         *
         * Guard eksplisit tetap dipertahankan
         * sebagai domain invariant.
         */
        if (
            $checklist->prepared_by
                === $actor->id
            || $checklist->submitted_by
                === $actor->id
        ) {
            throw new AuthorizationException(
                'Pembuat/pengaju Readiness tidak '
                .'boleh menjadi reviewer.'
            );
        }
    }

    private function assertCurrentContributor(
        DemandForecast $forecast,
        int $organizationId,
    ): void {
        $contributorOrganizationIds =
            $this->supplyMetricsService
                ->calculateContributorOrganizationIds(
                    $forecast
                );

        if (
            ! in_array(
                $organizationId,
                $contributorOrganizationIds,
                true
            )
        ) {
            throw ValidationException::withMessages([
                'contributor' => (
                    'Organisasi tidak lagi menjadi '
                    .'current effective Contributor '
                    .'untuk Forecast tersebut.'
                ),
            ]);
        }
    }

    /**
     * @param Collection<int, ReadinessItem> $items
     * @param Collection<int, DocumentRecord> $documentRecords
     */
    private function assertItemsApprovable(
        ReadinessChecklist $checklist,
        DemandForecast $forecast,
        Collection $items,
        Collection $documentRecords,
    ): void {
        foreach ($items as $item) {
            if (! $item->requirement) {
                throw ValidationException::withMessages([
                    'requirements' => (
                        'Checklist memiliki Readiness Item '
                        .'tanpa requirement yang valid.'
                    ),
                ]);
            }

            if (
                $item->is_required
                && ! $item->is_satisfied
            ) {
                throw ValidationException::withMessages([
                    'items.'.$item->id => (
                        'Requirement wajib "'
                        .$item->requirement->label
                        .'" belum dipenuhi.'
                    ),
                ]);
            }

            if (
                $checklist->readiness_type
                === ReadinessType::LOGISTICS
            ) {
                if (
                    $item->document_record_id
                    !== null
                ) {
                    throw ValidationException::withMessages([
                        'items.'.$item->id => (
                            'Logistics Readiness tidak '
                            .'dapat menggunakan '
                            .'Document Record.'
                        ),
                    ]);
                }

                continue;
            }

            /*
             * DOCUMENT readiness.
             *
             * Organization-scoped required item
             * wajib mempunyai reusable document.
             */
            if (
                $item->is_required
                && $item->is_satisfied
                && $item
                    ->requirement
                    ->requirement_scope
                    === RequirementScope::ORGANIZATION
                && $item->document_record_id
                    === null
            ) {
                throw ValidationException::withMessages([
                    'items.'.$item->id => (
                        'Requirement organisasi "'
                        .$item->requirement->label
                        .'" membutuhkan Document Record.'
                    ),
                ]);
            }

            if (
                $item->document_record_id
                === null
            ) {
                continue;
            }

            $documentRecord =
                $documentRecords->get(
                    $item->document_record_id
                );

            if (! $documentRecord) {
                throw ValidationException::withMessages([
                    'items.'.$item->id => (
                        'Document Record yang '
                        .'direferensikan tidak ditemukan.'
                    ),
                ]);
            }

            /*
             * Selain validity window/status,
             * dokumen tidak boleh berubah setelah
             * Operator membekukan payload pada submit.
             */
            if (
    $item->document_record_revision_no
    === null
) {
    throw ValidationException::withMessages([
        'items.'.$item->id => (
            'Document Record belum memiliki '
            .'revision snapshot dari submission.'
        ),
    ]);
}

$this
    ->documentRecordValidityService
    ->assertEffectiveForForecast(
        $documentRecord,
        $item,
        $checklist,
        $forecast,
        $item->document_record_revision_no
    );
        }
    }

    /**
     * @param Collection<int, ReadinessItem> $items
     *
     * @return Collection<int, DocumentRecord>
     */
    private function lockReferencedDocuments(
        Collection $items,
    ): Collection {
        $documentRecordIds =
            $items
                ->pluck(
                    'document_record_id'
                )
                ->filter(
                    fn ($id): bool =>
                        $id !== null
                )
                ->map(
                    fn ($id): int =>
                        (int) $id
                )
                ->unique()
                ->sort()
                ->values();

        if (
            $documentRecordIds->isEmpty()
        ) {
            return collect();
        }

        /*
         * Deterministic locking order.
         */
        return DocumentRecord::query()
            ->whereIn(
                'id',
                $documentRecordIds->all()
            )
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * @param Collection<int, ReadinessItem> $items
     */
    private function snapshotChecklist(
        ReadinessChecklist $checklist,
        Collection $items,
    ): array {
        return [
            'id' =>
                $checklist->id,

            'forecast_id' =>
                $checklist->forecast_id,
            
                

            'organization_id' =>
                $checklist->organization_id,

            'readiness_type' =>
                $checklist
                    ->readiness_type
                    ->value,

            'forecast_version' =>
                $checklist
                    ->forecast_version,

            'version_no' =>
                $checklist->version_no,

            'status' =>
                $checklist
                    ->status
                    ->value,

            'is_current_version' =>
                $checklist
                    ->is_current_version,

            'prepared_by' =>
                $checklist->prepared_by,

            'submitted_by' =>
                $checklist->submitted_by,

            'submitted_at' =>
                $checklist
                    ->submitted_at
                    ?->toIso8601String(),

            'reviewed_by' =>
                $checklist->reviewed_by,

            'reviewed_at' =>
                $checklist
                    ->reviewed_at
                    ?->toIso8601String(),

            'review_reason' =>
                $checklist
                    ->review_reason,

            'approved_at' =>
                $checklist
                    ->approved_at
                    ?->toIso8601String(),

            'items' =>
                $items
                    ->map(
                        fn (
                            ReadinessItem $item
                        ): array => [
                            'id' =>
                                $item->id,

                            'requirement_id' =>
                                $item->requirement_id,

                            'is_required' =>
                                $item->is_required,
                            
                                'document_record_revision_no' =>
    $item->document_record_revision_no,

                            'is_satisfied' =>
                                $item->is_satisfied,

                            'document_record_id' =>
                                $item
                                    ->document_record_id,

                            'note' =>
                                $item->note,

                            'value_json' =>
                                $item->value_json,
                        ]
                    )
                    ->values()
                    ->all(),
        ];
    }
}