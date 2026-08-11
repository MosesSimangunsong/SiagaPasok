<?php

namespace App\Services\Readiness;

use App\Enums\ReadinessType;
use App\Enums\RequirementScope;
use App\Models\DemandForecast;
use App\Models\ReadinessChecklist;
use App\Services\Supply\SupplyMetricsService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class ReadinessEvaluationService
{
    private const REASON_FORECAST_NOT_PUBLISHED =
        'FORECAST_NOT_PUBLISHED';

    private const REASON_FORECAST_WINDOW_ENDED =
        'FORECAST_WINDOW_ENDED';

    private const REASON_NOT_CURRENT_CONTRIBUTOR =
        'NOT_CURRENT_CONTRIBUTOR';

    private const REASON_CHECKLIST_MISSING =
        'CHECKLIST_MISSING';

    private const REASON_CHECKLIST_NOT_APPROVED =
        'CHECKLIST_NOT_APPROVED';

    private const REASON_FORECAST_VERSION_STALE =
        'FORECAST_VERSION_STALE';

    private const REASON_EMPTY_CHECKLIST =
        'EMPTY_CHECKLIST';

    private const REASON_REQUIRED_ITEM_UNSATISFIED =
        'REQUIRED_ITEM_UNSATISFIED';

    private const REASON_DOCUMENT_MISSING =
        'DOCUMENT_MISSING';

    private const REASON_DOCUMENT_REVISION_MISSING =
        'DOCUMENT_REVISION_MISSING';

    private const REASON_DOCUMENT_INVALID =
        'DOCUMENT_INVALID';

    public function __construct(
        private readonly SupplyMetricsService
            $supplyMetricsService,

        private readonly DocumentRecordValidityService
            $documentRecordValidityService,
    ) {
    }

    public function evaluateContributor(
        DemandForecast $forecast,
        int $organizationId,
        ?CarbonInterface $evaluatedAt = null,
    ): ContributorReadinessResult {
        $currentForecast =
            DemandForecast::query()
                ->whereKey(
                    $forecast->getKey()
                )
                ->firstOrFail();

        $evaluationTime =
            $evaluatedAt === null
                ? CarbonImmutable::now()
                : CarbonImmutable::instance(
                    $evaluatedAt
                );

        if (! $currentForecast->isPublished()) {
            return $this->failedResult(
                $currentForecast,
                $organizationId,
                $evaluationTime,
                self::REASON_FORECAST_NOT_PUBLISHED
            );
        }

        if (
            $evaluationTime->gt(
                CarbonImmutable::instance(
                    $currentForecast
                        ->required_end_at
                )
            )
        ) {
            return $this->failedResult(
                $currentForecast,
                $organizationId,
                $evaluationTime,
                self::REASON_FORECAST_WINDOW_ENDED
            );
        }

        $contributorIds =
            $this->supplyMetricsService
                ->calculateContributorOrganizationIds(
                    $currentForecast,
                    $evaluationTime
                );

        if (
            ! in_array(
                $organizationId,
                $contributorIds,
                true
            )
        ) {
            return $this->failedResult(
                $currentForecast,
                $organizationId,
                $evaluationTime,
                self::REASON_NOT_CURRENT_CONTRIBUTOR
            );
        }

        [
    $logisticsReady,
    $logisticsReasons,
] = $this->evaluateType(
    $currentForecast,
    $organizationId,
    ReadinessType::LOGISTICS,
    $evaluationTime
);

[
    $documentReady,
    $documentReasons,
] = $this->evaluateType(
    $currentForecast,
    $organizationId,
    ReadinessType::DOCUMENT,
    $evaluationTime
);

        return new ContributorReadinessResult(
            forecastId:
                $currentForecast->id,

            organizationId:
                $organizationId,

            evaluatedAt:
                $evaluationTime,

            isContributor:
                true,

            logisticsReady:
                $logisticsReady,

            documentReady:
                $documentReady,

            logisticsReasonCodes:
                $logisticsReasons,

            documentReasonCodes:
                $documentReasons,
        );
    }

    /**
     * @return array{0: bool, 1: array<int, string>}
     */
private function evaluateType(
    DemandForecast $forecast,
    int $organizationId,
    ReadinessType $type,
    CarbonImmutable $evaluationTime,
): array {
        $checklist =
            ReadinessChecklist::query()
                ->where(
                    'forecast_id',
                    $forecast->id
                )
                ->where(
                    'organization_id',
                    $organizationId
                )
                ->where(
                    'readiness_type',
                    $type->value
                )
                ->where(
                    'is_current_version',
                    true
                )
                ->with([
                    'items.requirement',
                    'items.documentRecord',
                ])
                ->first();

        if (! $checklist) {
            return [
                false,
                [
                    self::REASON_CHECKLIST_MISSING,
                ],
            ];
        }

        if (! $checklist->isApproved()) {
            return [
                false,
                [
                    self::REASON_CHECKLIST_NOT_APPROVED,
                ],
            ];
        }

        if (
            $checklist->forecast_version
            !== $forecast->version
        ) {
            return [
                false,
                [
                    self::REASON_FORECAST_VERSION_STALE,
                ],
            ];
        }

        if ($checklist->items->isEmpty()) {
            return [
                false,
                [
                    self::REASON_EMPTY_CHECKLIST,
                ],
            ];
        }

        $reasons = [];

        foreach (
            $checklist->items
            as $item
        ) {
            /*
             * Optional item tidak menjadi gate.
             */
            if (! $item->is_required) {
                continue;
            }

            if (! $item->is_satisfied) {
                $reasons[] =
                    self::REASON_REQUIRED_ITEM_UNSATISFIED;

                continue;
            }

            if (
                $type
                !== ReadinessType::DOCUMENT
            ) {
                continue;
            }

            if (! $item->requirement) {
                $reasons[] =
                    self::REASON_REQUIRED_ITEM_UNSATISFIED;

                continue;
            }

            /*
             * Organization-level document
             * requirement wajib mempunyai
             * reusable Document Record.
             *
             * Forecast-specific item boleh
             * menggunakan value/note evidence.
             */
            if (
                $item
                    ->requirement
                    ->requirement_scope
                === RequirementScope::ORGANIZATION
                && $item->document_record_id
                    === null
            ) {
                $reasons[] =
                    self::REASON_DOCUMENT_MISSING;

                continue;
            }

            if (
                $item->document_record_id
                === null
            ) {
                continue;
            }

            if (
                $item->document_record_revision_no
                === null
            ) {
                $reasons[] =
                    self::REASON_DOCUMENT_REVISION_MISSING;

                continue;
            }

            $documentRecord =
                $item->documentRecord;

            if (! $documentRecord) {
                $reasons[] =
                    self::REASON_DOCUMENT_MISSING;

                continue;
            }

            if (
                ! $this
                    ->documentRecordValidityService
->isEffectiveForForecast(
    $documentRecord,
    $item,
    $checklist,
    $forecast,
    $item
        ->document_record_revision_no,
    $evaluationTime
)
            ) {
                $reasons[] =
                    self::REASON_DOCUMENT_INVALID;
            }
        }

        $reasons =
            array_values(
                array_unique(
                    $reasons
                )
            );

        return [
            $reasons === [],
            $reasons,
        ];
    }

    private function failedResult(
        DemandForecast $forecast,
        int $organizationId,
        CarbonImmutable $evaluatedAt,
        string $reasonCode,
    ): ContributorReadinessResult {
        return new ContributorReadinessResult(
            forecastId:
                $forecast->id,

            organizationId:
                $organizationId,

            evaluatedAt:
                $evaluatedAt,

            isContributor:
                false,

            logisticsReady:
                false,

            documentReady:
                false,

            logisticsReasonCodes: [
                $reasonCode,
            ],

            documentReasonCodes: [
                $reasonCode,
            ],
        );
    }
}