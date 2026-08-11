<?php

namespace App\Services\Readiness;

use App\Enums\AuditSource;
use App\Enums\ReadinessApprovalStatus;
use App\Enums\ReadinessType;
use App\Models\DemandForecast;
use App\Models\Organization;
use App\Models\ReadinessChecklist;
use App\Models\ReadinessItem;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Supply\SupplyMetricsService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReadinessChecklistPreparationService
{
    private const AUDIT_CREATED =
        'READINESS_CHECKLIST_CREATED';

    public function __construct(
        private readonly SupplyMetricsService
            $supplyMetricsService,

        private readonly ReadinessRequirementResolver
            $requirementResolver,

        private readonly AuditService
            $auditService,
    ) {
    }

    public function createInitialDraft(
        User $actor,
        DemandForecast $forecast,
        ReadinessType $readinessType,
    ): ReadinessChecklist {
        $this->assertOperator($actor);

        return DB::transaction(
            function () use (
                $actor,
                $forecast,
                $readinessType,
            ): ReadinessChecklist {
                /*
                 * Forecast menjadi serialization
                 * point untuk initial checklist
                 * creation pada Forecast tersebut.
                 */
                $currentForecast =
                    DemandForecast::query()
                        ->whereKey(
                            $forecast->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (! $currentForecast->isPublished()) {
                    throw ValidationException::withMessages([
                        'forecast' => (
                            'Readiness hanya dapat disiapkan '
                            .'untuk Forecast PUBLISHED.'
                        ),
                    ]);
                }

                $organization =
                    Organization::query()
                        ->whereKey(
                            $actor->organization_id
                        )
                        ->firstOrFail();

                $this->assertActiveKdkmp(
                    $organization
                );

                $this->assertCurrentContributor(
                    $currentForecast,
                    $organization
                );

                /*
                 * Initial creation hanya berlaku jika
                 * tuple Forecast + Organization + Type
                 * belum pernah mempunyai checklist.
                 *
                 * Perubahan setelah checklist ada
                 * wajib menggunakan revision workflow,
                 * bukan membuat V1/Vn lain diam-diam.
                 */
                $alreadyExists =
                    ReadinessChecklist::query()
                        ->where(
                            'forecast_id',
                            $currentForecast->id
                        )
                        ->where(
                            'organization_id',
                            $organization->id
                        )
                        ->where(
                            'readiness_type',
                            $readinessType->value
                        )
                        ->exists();

                if ($alreadyExists) {
                    throw ValidationException::withMessages([
                        'readiness' => (
                            'Checklist readiness untuk Forecast, '
                            .'organisasi, dan tipe tersebut sudah '
                            .'pernah dibuat. Gunakan workflow '
                            .'revision untuk perubahan berikutnya.'
                        ),
                    ]);
                }

                $requirements =
                    $this->requirementResolver
                        ->resolve(
                            $currentForecast,
                            $organization,
                            $readinessType
                        );

                $checklist =
                    ReadinessChecklist::create([
                        'forecast_id' =>
                            $currentForecast->id,

                        'organization_id' =>
                            $organization->id,

                        'readiness_type' =>
                            $readinessType,

                        /*
                         * Snapshot current Forecast
                         * business version.
                         */
                        'forecast_version' =>
                            $currentForecast->version,

                        'version_no' =>
                            1,

                        'supersedes_checklist_id' =>
                            null,

                        'status' =>
                            ReadinessApprovalStatus::DRAFT,

                        'is_current_version' =>
                            true,

                        'prepared_by' =>
                            $actor->id,
                    ]);

                foreach ($requirements as $requirement) {
                    ReadinessItem::create([
                        'readiness_checklist_id' =>
                            $checklist->id,

                        'requirement_id' =>
                            $requirement->id,

                        /*
                         * Snapshot default requirement
                         * pada saat checklist version
                         * dibuat.
                         */
                        'is_required' =>
                            $requirement
                                ->is_required_default,

                        'is_satisfied' =>
                            false,

                        'note' =>
                            null,

                        'document_record_id' =>
                            null,

                        'value_json' =>
                            null,

                        'updated_by' =>
                            $actor->id,
                    ]);
                }

                $checklist->load([
                    'items.requirement',
                ]);

                $this->auditService->record(
                    actor: $actor,
                    source: AuditSource::USER,
                    action: self::AUDIT_CREATED,
                    entity: $checklist,
                    previousValue: null,
                    newValue: $this->snapshot(
                        $checklist
                    ),
                );

                return $checklist;
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
                .'dapat menyiapkan readiness.'
            );
        }
    }

    private function assertActiveKdkmp(
        Organization $organization,
    ): void {
        if (
            ! $organization->is_active
            || ! $organization->isKdkmp()
        ) {
            throw new AuthorizationException(
                'Readiness hanya dapat disiapkan '
                .'oleh organisasi KDKMP aktif.'
            );
        }
    }

    private function assertCurrentContributor(
        DemandForecast $forecast,
        Organization $organization,
    ): void {
        $contributorOrganizationIds =
            $this->supplyMetricsService
                ->calculateContributorOrganizationIds(
                    $forecast
                );

        if (
            ! in_array(
                $organization->id,
                $contributorOrganizationIds,
                true
            )
        ) {
            throw ValidationException::withMessages([
                'contributor' => (
                    'Organisasi Anda bukan current effective '
                    .'Contributor untuk Forecast tersebut.'
                ),
            ]);
        }
    }

    private function snapshot(
        ReadinessChecklist $checklist,
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
                $checklist->forecast_version,

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

            'requirement_ids' =>
                $checklist
                    ->items
                    ->pluck(
                        'requirement_id'
                    )
                    ->map(
                        fn ($id): int =>
                            (int) $id
                    )
                    ->values()
                    ->all(),
        ];
    }
}