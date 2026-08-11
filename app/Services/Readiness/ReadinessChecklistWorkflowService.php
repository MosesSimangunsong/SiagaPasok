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
use App\Services\Notification\OperationalNotificationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ReadinessChecklistWorkflowService
{
    private const AUDIT_ITEM_UPDATED =
        'READINESS_ITEM_UPDATED';

    private const AUDIT_SUBMITTED =
        'READINESS_SUBMITTED';

    public function __construct(
    private readonly SupplyMetricsService
        $supplyMetricsService,

    private readonly DocumentRecordValidityService
        $documentRecordValidityService,

    private readonly AuditService
        $auditService,

    private readonly OperationalNotificationService
        $operationalNotificationService,
) {
}

    public function updateItem(
        User $actor,
        ReadinessChecklist $checklist,
        ReadinessItem $item,
        array $data,
    ): ReadinessItem {
        $this->assertOperator(
            $actor
        );

        return DB::transaction(
            function () use (
                $actor,
                $checklist,
                $item,
                $data,
            ): ReadinessItem {
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

                $this->assertCurrentVersion(
                    $currentChecklist
                );

                if (
                    ! $currentChecklist->isDraft()
                ) {
                    throw ValidationException::withMessages([
                        'status' => (
                            'Hanya Readiness Checklist DRAFT '
                            .'yang dapat diubah.'
                        ),
                    ]);
                }

                $currentItem =
                    ReadinessItem::query()
                        ->whereKey(
                            $item->getKey()
                        )
                        ->where(
                            'readiness_checklist_id',
                            $currentChecklist->id
                        )
                        ->lockForUpdate()
                        ->first();

                if (! $currentItem) {
                    throw ValidationException::withMessages([
                        'item' => (
                            'Readiness Item tidak ditemukan '
                            .'pada Checklist tersebut.'
                        ),
                    ]);
                }

                $currentItem->load(
                    'requirement'
                );

                if (! $currentItem->requirement) {
                    throw ValidationException::withMessages([
                        'requirement' => (
                            'Readiness Item tidak memiliki '
                            .'requirement yang valid.'
                        ),
                    ]);
                }

                /*
                 * Mendukung partial item update tanpa
                 * membuat field yang tidak dikirim
                 * menjadi null secara tidak sengaja.
                 */
                $candidate = [
                    'is_satisfied' =>
                        array_key_exists(
                            'is_satisfied',
                            $data
                        )
                            ? $data['is_satisfied']
                            : $currentItem
                                ->is_satisfied,

                    'note' =>
                        array_key_exists(
                            'note',
                            $data
                        )
                            ? $data['note']
                            : $currentItem->note,

                    'document_record_id' =>
                        array_key_exists(
                            'document_record_id',
                            $data
                        )
                            ? $data[
                                'document_record_id'
                            ]
                            : $currentItem
                                ->document_record_id,

                    'value_json' =>
                        array_key_exists(
                            'value_json',
                            $data
                        )
                            ? $data['value_json']
                            : $currentItem
                                ->value_json,
                ];

                $validated =
                    Validator::make(
                        $candidate,
                        [
                            'is_satisfied' => [
                                'required',
                                'boolean',
                            ],

                            'note' => [
                                'nullable',
                                'string',
                            ],

                            'document_record_id' => [
                                'nullable',
                                'integer',
                            ],

                            'value_json' => [
                                'nullable',
                                'array',
                            ],
                        ]
                    )->validate();

                /*
                 * Logistics Readiness tidak boleh
                 * membawa Document Record.
                 */
                if (
                    $currentChecklist
                        ->readiness_type
                    !== ReadinessType::DOCUMENT
                    && $validated[
                        'document_record_id'
                    ] !== null
                ) {
                    throw ValidationException::withMessages([
                        'document_record_id' => (
                            'Document Record hanya dapat '
                            .'digunakan pada Document Readiness.'
                        ),
                    ]);
                }

                if (
                    $validated[
                        'document_record_id'
                    ] !== null
                ) {
                    /*
                     * Query organization-scoped.
                     *
                     * Record organization lain tidak
                     * pernah menjadi candidate valid.
                     */
                    $documentRecord =
                        DocumentRecord::query()
                            ->whereKey(
                                $validated[
                                    'document_record_id'
                                ]
                            )
                            ->where(
                                'organization_id',
                                $currentChecklist
                                    ->organization_id
                            )
                            ->first();

                    if (! $documentRecord) {
                        throw ValidationException::withMessages([
                            'document_record_id' => (
                                'Document Record tidak tersedia '
                                .'untuk organisasi Anda.'
                            ),
                        ]);
                    }

                    $this
                        ->documentRecordValidityService
                        ->assertMatchesItem(
                            $documentRecord,
                            $currentItem,
                            $currentChecklist
                        );
                }

                $before =
                    $this->snapshotItem(
                        $currentItem
                    );

                $currentItem->fill([
                    'is_satisfied' =>
                        $validated[
                            'is_satisfied'
                        ],

                    'note' =>
                        $validated['note'],

                    'document_record_id' =>
                        $validated[
                            'document_record_id'
                        ],

                    'value_json' =>
                        $validated[
                            'value_json'
                        ],

                    'updated_by' =>
                        $actor->id,
                ]);

                if (! $currentItem->isDirty()) {
                    return $currentItem;
                }

                $currentItem->save();

                $this->auditService->record(
                    actor: $actor,
                    source: AuditSource::USER,
                    action: self::AUDIT_ITEM_UPDATED,
                    entity: $currentItem,
                    previousValue: $before,
                    newValue: $this->snapshotItem(
                        $currentItem
                    ),
                );

                return $currentItem->refresh();
            }
        );
    }

    public function submit(
        User $actor,
        ReadinessChecklist $checklist,
    ): ReadinessChecklist {
        $this->assertOperator(
            $actor
        );

        return DB::transaction(
            function () use (
                $actor,
                $checklist,
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

                $this->assertCurrentVersion(
                    $currentChecklist
                );

                /*
                 * Repeat submit aman dan tidak
                 * menghasilkan audit duplicate.
                 */
                if (
                    $currentChecklist
                        ->isPendingApproval()
                ) {
                    return $currentChecklist;
                }

                if (
                    ! $currentChecklist->isDraft()
                ) {
                    throw ValidationException::withMessages([
                        'status' => (
                            'Hanya Readiness Checklist DRAFT '
                            .'yang dapat disubmit.'
                        ),
                    ]);
                }

                /*
                 * Forecast row menjadi lock boundary
                 * terhadap concurrent Forecast revision.
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
                            'Readiness hanya dapat disubmit '
                            .'untuk Forecast PUBLISHED.'
                        ),
                    ]);
                }

                /*
                 * Checklist dibuat dalam konteks
                 * Forecast version tertentu.
                 *
                 * Jangan diam-diam rebase payload
                 * lama ke Forecast baru.
                 */
                if (
                    $currentChecklist
                        ->forecast_version
                    !== $forecast->version
                ) {
                    throw ValidationException::withMessages([
                        'forecast_version' => (
                            'Forecast telah direvisi sejak '
                            .'Checklist dibuat. Buat revision '
                            .'Readiness untuk Forecast terbaru.'
                        ),
                    ]);
                }

                $this->assertCurrentContributor(
                    $forecast,
                    $currentChecklist
                        ->organization_id
                );

                /*
                 * Checklist lock membuat updateItem()
                 * tidak dapat mengubah payload saat
                 * proses submit berjalan.
                 */
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

                /*
                 * Fail closed.
                 *
                 * Checklist kosong tidak boleh bergerak
                 * ke approval dan akhirnya memperoleh
                 * readiness TRUE secara vacuous.
                 */
                if ($items->isEmpty()) {
                    throw ValidationException::withMessages([
                        'requirements' => (
                            'Checklist belum memiliki requirement '
                            .'yang dapat dievaluasi.'
                        ),
                    ]);
                }

                $documentRecords =
                    $this->lockReferencedDocuments(
                        $items
                    );

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
                        $currentChecklist
                            ->readiness_type
                        === ReadinessType::LOGISTICS
                        && $item->document_record_id
                            !== null
                    ) {
                        throw ValidationException::withMessages([
                            'items.'.$item->id => (
                                'Logistics Readiness tidak dapat '
                                .'menggunakan Document Record.'
                            ),
                        ]);
                    }

                    if (
                        $currentChecklist
                            ->readiness_type
                        === ReadinessType::DOCUMENT
                    ) {
                        /*
                         * Organization-scope document
                         * requirement harus dibuktikan
                         * dengan reusable Document Record.
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

                        /*
                         * Setiap Document Record yang
                         * direferensikan harus valid,
                         * termasuk item optional.
                         */
                        if (
    $item->document_record_id
    === null
    && $item->document_record_revision_no
        !== null
) {
    $item->document_record_revision_no =
        null;

    $item->updated_by =
        $actor->id;

    $item->save();
}

                        if (
                            $item->document_record_id
                            !== null
                        ) {
                            $documentRecord =
                                $documentRecords->get(
                                    $item
                                        ->document_record_id
                                );

                            if (! $documentRecord) {
                                throw ValidationException::withMessages([
                                    'items.'.$item->id => (
                                        'Document Record yang '
                                        .'direferensikan tidak ditemukan.'
                                    ),
                                ]);
                            }

                            $item->document_record_revision_no =
    $documentRecord->revision_no;

$item->updated_by =
    $actor->id;

$item->save();

                            $this
    ->documentRecordValidityService
    ->assertEffectiveForForecast(
        $documentRecord,
        $item,
        $currentChecklist,
        $forecast,
        $item->document_record_revision_no
    );
                        }
                    }
                }

                $before =
                    $this->snapshotChecklist(
                        $currentChecklist,
                        $items
                    );

                $submittedAt =
                    now();

                $currentChecklist->update([
                    'status' =>
                        ReadinessApprovalStatus
                            ::PENDING_APPROVAL,

                    'submitted_by' =>
                        $actor->id,

                    'submitted_at' =>
                        $submittedAt,

                    /*
                     * Defensive reset.
                     */
                    'reviewed_by' =>
                        null,

                    'reviewed_at' =>
                        null,

                    'review_reason' =>
                        null,

                    'approved_at' =>
                        null,
                ]);

                $this->auditService->record(
    actor: $actor,
    source: AuditSource::USER,
    action: self::AUDIT_SUBMITTED,
    entity: $currentChecklist,
    previousValue: $before,
    newValue:
        $this->snapshotChecklist(
            $currentChecklist,
            $items
        ),
);

$this->operationalNotificationService
    ->readinessApprovalRequired(
        $currentChecklist
    );

return $currentChecklist
    ->refresh()
    ->load([
        'items.requirement',
    ]);
            }
        );
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
                .'dapat mengelola Readiness.'
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
        if (! $checklist->is_current_version) {
            throw ValidationException::withMessages([
                'version' => (
                    'Historical Readiness Checklist tidak '
                    .'dapat diubah atau disubmit.'
                ),
            ]);
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
                    'Organisasi tidak lagi menjadi current '
                    .'effective Contributor untuk Forecast ini.'
                ),
            ]);
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

        if ($documentRecordIds->isEmpty()) {
            return collect();
        }

        /*
         * Deterministic lock order mengurangi
         * risiko deadlock apabila beberapa
         * Document Record dipakai bersama.
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

    private function snapshotItem(
        ReadinessItem $item,
    ): array {
        return [
            'id' =>
                $item->id,

            'readiness_checklist_id' =>
                $item
                    ->readiness_checklist_id,
            
            'document_record_revision_no' =>
    $item->document_record_revision_no,

            'requirement_id' =>
                $item->requirement_id,

            'is_required' =>
                $item->is_required,

            'is_satisfied' =>
                $item->is_satisfied,

            'note' =>
                $item->note,

            'document_record_id' =>
                $item->document_record_id,

            'value_json' =>
                $item->value_json,

            'updated_by' =>
                $item->updated_by,
        ];
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
                $checklist
                    ->prepared_by,

            'submitted_by' =>
                $checklist
                    ->submitted_by,

            'submitted_at' =>
                $checklist
                    ->submitted_at
                    ?->toIso8601String(),

            'items' =>
                $items
                    ->map(
                        fn (
                            ReadinessItem $item
                        ): array =>
                            $this->snapshotItem(
                                $item
                            )
                    )
                    ->values()
                    ->all(),
        ];
    }
}