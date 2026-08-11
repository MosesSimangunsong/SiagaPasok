<?php

namespace App\Services\Readiness;

use App\Enums\AuditSource;
use App\Enums\ReadinessApprovalStatus;
use App\Enums\ReadinessType;
use App\Models\DemandForecast;
use App\Models\ReadinessChecklist;
use App\Models\ReadinessItem;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Supply\SupplyMetricsService;
use App\Services\Notification\DerivedForecastStateObservationService;
use App\Services\Notification\OperationalNotificationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReadinessChecklistRevisionService
{
    private const AUDIT_REVISION_CREATED =
        'READINESS_REVISION_CREATED';

    public function __construct(
    private readonly SupplyMetricsService
        $supplyMetricsService,

    private readonly ReadinessRequirementResolver
        $requirementResolver,

    private readonly ReadinessEvaluationService
        $readinessEvaluationService,

    private readonly AuditService
        $auditService,

    private readonly OperationalNotificationService
        $operationalNotificationService,

    private readonly DerivedForecastStateObservationService
        $derivedStateObservationService,
) {
}

    public function createRevision(
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
                /*
                 * Lock current checklist first.
                 *
                 * Concurrent revision terhadap
                 * version yang sama akan serialize.
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

                if (
                    ! $currentChecklist
                        ->is_current_version
                ) {
                    throw ValidationException::withMessages([
                        'version' => (
                            'Historical Readiness Checklist '
                            .'tidak dapat direvisi.'
                        ),
                    ]);
                }

                /*
                 * Pending payload sedang berada
                 * dalam Manager review.
                 *
                 * Operator tidak boleh menghindari
                 * approval queue dengan membuat
                 * revision paralel.
                 */
                if (
                    $currentChecklist
                        ->isPendingApproval()
                ) {
                    throw ValidationException::withMessages([
                        'status' => (
                            'Readiness Checklist yang sedang '
                            .'menunggu persetujuan tidak dapat '
                            .'direvisi. Tunggu keputusan Manager.'
                        ),
                    ]);
                }

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
                            'Revision Readiness hanya dapat '
                            .'dibuat untuk Forecast PUBLISHED.'
                        ),
                    ]);
                }

                /*
                 * DRAFT normal masih editable.
                 *
                 * Revision DRAFT hanya diperlukan
                 * jika Forecast sudah berubah dan
                 * snapshot forecast_version lama
                 * tidak lagi dapat disubmit.
                 */
                if (
                    $currentChecklist->isDraft()
                    && $currentChecklist
                        ->forecast_version
                        === $forecast->version
                ) {
                    throw ValidationException::withMessages([
                        'status' => (
                            'Readiness Checklist masih DRAFT '
                            .'dan sesuai Forecast terbaru. '
                            .'Edit DRAFT yang ada tanpa '
                            .'membuat revision.'
                        ),
                    ]);
                }

                $this->assertCurrentContributor(
                    $forecast,
                    $currentChecklist
                        ->organization_id
                );

                /*
 * Ambil canonical M08 state SEBELUM current
 * checklist diganti.
 *
 * Tidak cukup hanya mengecek status APPROVED:
 * Document dapat sudah invalid/expired dan Forecast
 * version dapat sudah stale.
 */
$readinessBeforeRevision =
    $this->readinessEvaluationService
        ->evaluateContributor(
            $forecast,
            $currentChecklist
                ->organization_id
        );

$wasCurrentTypeReady =
    match (
        $currentChecklist
            ->readiness_type
    ) {
        ReadinessType::LOGISTICS =>
            $readinessBeforeRevision
                ->logisticsReady,

        ReadinessType::DOCUMENT =>
            $readinessBeforeRevision
                ->documentReady,
    };

                $organization =
                    $actor->organization;

                if (
                    ! $organization
                    || ! $organization->isKdkmp()
                    || ! $organization->is_active
                ) {
                    throw new AuthorizationException(
                        'Revision Readiness hanya dapat '
                        .'dibuat oleh KDKMP aktif.'
                    );
                }

                /*
                 * Historical item payload dikunci
                 * karena sebagian nilai akan disalin
                 * sebagai starting point revision.
                 */
                $oldItems =
                    ReadinessItem::query()
                        ->where(
                            'readiness_checklist_id',
                            $currentChecklist->id
                        )
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                $oldItemsByRequirement =
                    $oldItems->keyBy(
                        'requirement_id'
                    );

                /*
                 * Resolve CURRENT requirement set.
                 *
                 * Ini penting jika requirement master
                 * berubah sejak version sebelumnya:
                 * - requirement baru ikut V(n+1);
                 * - inactive/non-applicable requirement
                 *   tidak otomatis diwariskan.
                 */
                $requirements =
                    $this->requirementResolver
                        ->resolve(
                            $forecast,
                            $organization,
                            $currentChecklist
                                ->readiness_type
                        );

                $before =
                    $this->snapshotChecklist(
                        $currentChecklist,
                        $oldItems
                    );

                /*
                 * version_no dihitung pada tuple
                 * Forecast + Organization + Type.
                 *
                 * Current checklist lock +
                 * partial unique current index
                 * menjaga concurrent switch.
                 */
                $latestVersionNo =
                    (int)
                    ReadinessChecklist::query()
                        ->where(
                            'forecast_id',
                            $currentChecklist
                                ->forecast_id
                        )
                        ->where(
                            'organization_id',
                            $currentChecklist
                                ->organization_id
                        )
                        ->where(
                            'readiness_type',
                            $currentChecklist
                                ->readiness_type
                                ->value
                        )
                        ->max(
                            'version_no'
                        );

                $nextVersionNo =
                    $latestVersionNo + 1;

                /*
                 * Begitu revision dibuat,
                 * approval lama tidak lagi current.
                 *
                 * Historical row tetap utuh.
                 */
                $currentChecklist
                    ->is_current_version =
                    false;

                $currentChecklist->save();

                $newChecklist =
                    ReadinessChecklist::create([
                        'forecast_id' =>
                            $forecast->id,

                        'organization_id' =>
                            $currentChecklist
                                ->organization_id,

                        'readiness_type' =>
                            $currentChecklist
                                ->readiness_type,

                        'forecast_version' =>
                            $forecast->version,

                        'version_no' =>
                            $nextVersionNo,

                        'supersedes_checklist_id' =>
                            $currentChecklist->id,

                        'status' =>
                            ReadinessApprovalStatus::DRAFT,

                        'is_current_version' =>
                            true,

                        'prepared_by' =>
                            $actor->id,

                        'submitted_by' =>
                            null,

                        'submitted_at' =>
                            null,

                        'reviewed_by' =>
                            null,

                        'reviewed_at' =>
                            null,

                        'review_reason' =>
                            null,

                        'approved_at' =>
                            null,
                    ]);

                foreach (
                    $requirements
                    as $requirement
                ) {
                    $previousItem =
                        $oldItemsByRequirement
                            ->get(
                                $requirement->id
                            );

                    ReadinessItem::create([
                        'readiness_checklist_id' =>
                            $newChecklist->id,

                        'requirement_id' =>
                            $requirement->id,

                        /*
                         * Required flag memakai
                         * CURRENT requirement master,
                         * bukan snapshot lama.
                         */
                        'is_required' =>
                            $requirement
                                ->is_required_default,

                        /*
                         * Matching requirement dapat
                         * memakai previous answer
                         * sebagai starting point.
                         *
                         * V(n+1) tetap DRAFT sehingga
                         * tidak otomatis menjadi Ready.
                         */
                        'is_satisfied' =>
                            $previousItem
                                ? $previousItem
                                    ->is_satisfied
                                : false,

                        'note' =>
                            $previousItem
                                ? $previousItem->note
                                : null,

                        'document_record_id' =>
                            $previousItem
                                ? $previousItem
                                    ->document_record_id
                                : null,

                        'document_record_revision_no' =>
    null,

                        'value_json' =>
                            $previousItem
                                ? $previousItem
                                    ->value_json
                                : null,

                        'updated_by' =>
                            $actor->id,
                    ]);
                }

                $newChecklist->load([
                    'items.requirement',
                ]);

                $this->auditService->record(
                    actor: $actor,
                    source: AuditSource::USER,
                    action:
                        self::AUDIT_REVISION_CREATED,
                    entity: $newChecklist,
                    previousValue: $before,
                    newValue:
                        $this->snapshotChecklist(
                            $newChecklist,
                            $newChecklist->items
                        ),
                );

                /*
 * Hanya actual TRUE -> FALSE readiness transition
 * yang menghasilkan invalidation warning.
 *
 * Revision dari REJECTED atau already-invalid
 * checklist tidak boleh menghasilkan false alarm.
 */
if ($wasCurrentTypeReady) {
    $this->operationalNotificationService
        ->readinessRevisionInvalidated(
            $newChecklist
        );
}

/*
 * Current checklist telah berubah menjadi DRAFT.
 * M09 evaluation dilakukan setelah root transaction
 * commit supaya PostgreSQL dapat membentuk canonical
 * REPEATABLE READ snapshot-nya sendiri.
 */
$this->derivedStateObservationService
    ->observeAfterCommit(
        $forecast
    );

                return $newChecklist;
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
                .'dapat membuat revision Readiness.'
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

    private function snapshotChecklist(
        ReadinessChecklist $checklist,
        iterable $items,
    ): array {
        $itemSnapshots = [];

        foreach ($items as $item) {
            $itemSnapshots[] = [
                'id' =>
                    $item->id,

                'requirement_id' =>
                    $item->requirement_id,

                'is_required' =>
                    $item->is_required,

                'is_satisfied' =>
                    $item->is_satisfied,

                'document_record_id' =>
                    $item
                        ->document_record_id,

                'note' =>
                    $item->note,

                'value_json' =>
                    $item->value_json,
            ];
        }

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

            'supersedes_checklist_id' =>
                $checklist
                    ->supersedes_checklist_id,

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
                $itemSnapshots,
        ];
    }
}