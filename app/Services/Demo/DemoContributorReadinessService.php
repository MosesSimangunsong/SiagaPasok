<?php

namespace App\Services\Demo;

use App\Enums\OrganizationType;
use App\Enums\ReadinessType;
use App\Enums\RequirementScope;
use App\Models\DemandForecast;
use App\Models\FallbackOffer;
use App\Models\FallbackRequest;
use App\Models\Organization;
use App\Models\ReadinessChecklist;
use App\Models\User;
use App\Services\Readiness\ReadinessChecklistPreparationService;
use App\Services\Readiness\ReadinessChecklistReviewService;
use App\Services\Readiness\ReadinessChecklistWorkflowService;
use App\Services\Readiness\ReadyForProcurementEvaluationService;
use App\Services\Readiness\ReadyForProcurementResult;
use App\Services\Supply\SupplyMetricsService;
use App\Support\Demo\DemoIdentifiers;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class DemoContributorReadinessService
{
    public function __construct(
        private readonly ReadinessChecklistPreparationService
            $preparationService,

        private readonly ReadinessChecklistWorkflowService
            $workflowService,

        private readonly ReadinessChecklistReviewService
            $reviewService,

        private readonly SupplyMetricsService
            $supplyMetricsService,

        private readonly ReadyForProcurementEvaluationService
            $readyForProcurementEvaluationService,
    ) {
    }

    public function prepareAndSubmit(
        User $actor,
        ReadinessType $readinessType
    ): ReadinessChecklist {
        $actor->loadMissing(
            'organization'
        );

        $this->assertDemoOperator(
            $actor
        );

        $forecast =
            $this->resolveRecoveredForecast();

        $checklist =
            $this->resolveChecklist(
                $forecast,
                $actor->organization_id,
                $readinessType
            );

        if (! $checklist) {
            $checklist =
                $this->preparationService
                    ->createInitialDraft(
                        $actor,
                        $forecast,
                        $readinessType
                    );
        }

        $checklist->loadMissing(
            'items.requirement'
        );

        $this->assertDemoChecklist(
            checklist: $checklist,
            forecast: $forecast,
            readinessType: $readinessType,
            expectedOrganizationCode:
                $actor->organization->code,
        );

        if (
            $checklist->isPendingApproval()
            || $checklist->isApproved()
        ) {
            return $checklist->refresh();
        }

        if (! $checklist->isDraft()) {
            $this->fail(
                'Readiness demo berada pada state yang tidak kompatibel. Gunakan Demo Reset untuk memulai ulang scenario.'
            );
        }

        $item =
            $checklist->items->first();

        $expectedNote =
            $readinessType
                === ReadinessType::LOGISTICS
                    ? DemoIdentifiers
                        ::LOGISTICS_ITEM_NOTE
                    : DemoIdentifiers
                        ::DOCUMENT_ITEM_NOTE;

        $this->workflowService
            ->updateItem(
                $actor,
                $checklist,
                $item,
                [
                    'is_satisfied' => true,
                    'note' => $expectedNote,
                ]
            );

        return $this->workflowService
            ->submit(
                $actor,
                $checklist
            )
            ->refresh();
    }

    public function approve(
        User $actor,
        ReadinessType $readinessType
    ): ReadinessChecklist {
        $actor->loadMissing(
            'organization'
        );

        $this->assertDemoManager(
            $actor
        );

        $forecast =
            $this->resolveRecoveredForecast();

        $checklist =
            $this->resolveChecklist(
                $forecast,
                $actor->organization_id,
                $readinessType
            );

        if (! $checklist) {
            $this->fail(
                'Readiness demo belum disiapkan oleh Operator contributor.'
            );
        }

        $checklist->loadMissing(
            'items.requirement'
        );

        $this->assertDemoChecklist(
            checklist: $checklist,
            forecast: $forecast,
            readinessType: $readinessType,
            expectedOrganizationCode:
                $actor->organization->code,
        );

        if ($checklist->isApproved()) {
            return $checklist->refresh();
        }

        if (
            ! $checklist->isPendingApproval()
        ) {
            $this->fail(
                'Readiness demo belum berada pada PENDING_APPROVAL.'
            );
        }

        return $this->reviewService
            ->approve(
                $actor,
                $checklist
            )
            ->refresh();
    }

    public function evaluate():
        ReadyForProcurementResult {
        $forecast =
            DemandForecast::query()
                ->where(
                    'forecast_code',
                    DemoIdentifiers::FORECAST_CODE
                )
                ->firstOrFail();

        return $this
            ->readyForProcurementEvaluationService
            ->evaluate(
                $forecast
            );
    }

    private function resolveRecoveredForecast():
        DemandForecast {
        $forecast =
            DemandForecast::query()
                ->where(
                    'forecast_code',
                    DemoIdentifiers::FORECAST_CODE
                )
                ->first();

        if (
            ! $forecast
            || ! $forecast->isPublished()
        ) {
            $this->fail(
                'Forecast demo Kangkung 400 kg tidak tersedia atau tidak lagi PUBLISHED.'
            );
        }

        $request =
            FallbackRequest::query()
                ->where(
                    'forecast_id',
                    $forecast->id
                )
                ->where(
                    'broadcast_note',
                    DemoIdentifiers
                        ::FALLBACK_REQUEST_NOTE
                )
                ->orderByDesc('id')
                ->first();

        if (
            ! $request
            || ! $request->isFulfilled()
        ) {
            $this->fail(
                'Readiness demo hanya dapat dimulai setelah Fallback Request terpenuhi.'
            );
        }

        $offer =
            FallbackOffer::query()
                ->where(
                    'fallback_request_id',
                    $request->id
                )
                ->where(
                    'availability_note',
                    DemoIdentifiers
                        ::FALLBACK_OFFER_NOTE
                )
                ->orderByDesc('id')
                ->first();

        if (
            ! $offer
            || ! $offer->isAccepted()
            || (string) $offer->accepted_volume
                !== DemoIdentifiers
                    ::FALLBACK_ACCEPTED_VOLUME
        ) {
            $this->fail(
                'Readiness demo membutuhkan Fallback Offer ACCEPTED 150 kg.'
            );
        }

        $primary =
            $this->resolveOrganization(
                DemoIdentifiers
                    ::PRIMARY_KDKMP_CODE
            );

        $network =
            $this->resolveOrganization(
                DemoIdentifiers
                    ::NETWORK_KDKMP_CODE
            );

        $metrics =
            $this->supplyMetricsService
                ->calculate(
                    $forecast
                );

        $expectedContributorIds = [
            $primary->id,
            $network->id,
        ];

        sort(
            $expectedContributorIds
        );

        $actualContributorIds =
            $metrics
                ->contributorOrganizationIds;

        sort(
            $actualContributorIds
        );

        $expectedBreakdown = [
            $primary->id =>
                '250.000000',

            $network->id =>
                '150.000000',
        ];

        ksort(
            $expectedBreakdown
        );

        $actualBreakdown =
            $metrics
                ->contributorSafeSupplyByOrganization;

        ksort(
            $actualBreakdown
        );

        if (
            $metrics->demandTarget
                !== '400.000000'
            || $metrics->directSafeSupply
                !== '250.000000'
            || $metrics->atRiskSupply
                !== '150.000000'
            || $metrics->fallbackSafeSupply
                !== '150.000000'
            || $metrics->totalSafeSupply
                !== '400.000000'
            || $metrics->coveragePercent
                !== '100.00'
            || $metrics->shortfall
                !== '0.000000'
            || ! $metrics->volumeReady
            || $actualContributorIds
                !== $expectedContributorIds
            || $actualBreakdown
                !== $expectedBreakdown
        ) {
            $this->fail(
                'Readiness demo membutuhkan recovered state Safe 400 / Shortfall 0 dengan dua contributor efektif.'
            );
        }

        return $forecast;
    }

    private function resolveChecklist(
        DemandForecast $forecast,
        int $organizationId,
        ReadinessType $readinessType
    ): ?ReadinessChecklist {
        return ReadinessChecklist::query()
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
                $readinessType->value
            )
            ->where(
                'is_current_version',
                true
            )
            ->with(
                'items.requirement'
            )
            ->first();
    }

    private function assertDemoChecklist(
        ReadinessChecklist $checklist,
        DemandForecast $forecast,
        ReadinessType $readinessType,
        string $expectedOrganizationCode
    ): void {
        $checklist->loadMissing(
            'items.requirement'
        );

        if (
            $checklist->items->count() !== 1
        ) {
            $this->fail(
                'Controlled demo mengharapkan tepat satu requirement readiness aktif untuk tipe tersebut. Requirement tambahan tidak akan ditandai terpenuhi secara otomatis.'
            );
        }

        $item =
            $checklist->items->first();

        $requirement =
            $item->requirement;

        $expectedRequirementCode =
            $readinessType
                === ReadinessType::LOGISTICS
                    ? DemoIdentifiers
                        ::LOGISTICS_REQUIREMENT_CODE
                    : DemoIdentifiers
                        ::DOCUMENT_REQUIREMENT_CODE;

        $expectedOperatorEmail =
            $expectedOrganizationCode
                === DemoIdentifiers
                    ::PRIMARY_KDKMP_CODE
                    ? DemoIdentifiers
                        ::PRIMARY_OPERATOR_EMAIL
                    : DemoIdentifiers
                        ::NETWORK_OPERATOR_EMAIL;

        $expectedOperatorId =
            User::query()
                ->where(
                    'email',
                    $expectedOperatorEmail
                )
                ->value('id');

        if (
            ! $requirement
            || $requirement->requirement_code
                !== $expectedRequirementCode
            || $requirement->readiness_type
                !== $readinessType
            || $requirement->requirement_scope
                !== RequirementScope::FORECAST
            || $requirement
                ->applies_to_organization_type
                !== OrganizationType::KDKMP
            || $requirement->commodity_id
                !== $forecast->commodity_id
            || ! $requirement->is_active
            || ! $item->is_required
            || $checklist->prepared_by
                !== $expectedOperatorId
        ) {
            $this->fail(
                'Readiness Checklist demo tidak sesuai deterministic scenario contract.'
            );
        }
    }

    private function resolveOrganization(
        string $code
    ): Organization {
        $organization =
            Organization::query()
                ->where(
                    'code',
                    $code
                )
                ->where(
                    'is_active',
                    true
                )
                ->first();

        if (! $organization) {
            $this->fail(
                "Demo organization {$code} tidak tersedia atau tidak aktif."
            );
        }

        return $organization;
    }

    private function assertDemoOperator(
        User $actor
    ): void {
        $allowed = [
            DemoIdentifiers
                ::PRIMARY_OPERATOR_EMAIL =>
                    DemoIdentifiers
                        ::PRIMARY_KDKMP_CODE,

            DemoIdentifiers
                ::NETWORK_OPERATOR_EMAIL =>
                    DemoIdentifiers
                        ::NETWORK_KDKMP_CODE,
        ];

        $expectedOrganizationCode =
            $allowed[$actor->email] ?? null;

        if (
            ! $actor->isKdkmpOperator()
            || ! $actor->hasValidIdentityContext()
            || ! $expectedOrganizationCode
            || $actor->organization?->code
                !== $expectedOrganizationCode
        ) {
            throw new AuthorizationException(
                'Contributor Readiness demo hanya dapat disiapkan oleh Operator demo contributor yang sesuai.'
            );
        }
    }

    private function assertDemoManager(
        User $actor
    ): void {
        $allowed = [
            DemoIdentifiers
                ::PRIMARY_MANAGER_EMAIL =>
                    DemoIdentifiers
                        ::PRIMARY_KDKMP_CODE,

            DemoIdentifiers
                ::NETWORK_MANAGER_EMAIL =>
                    DemoIdentifiers
                        ::NETWORK_KDKMP_CODE,
        ];

        $expectedOrganizationCode =
            $allowed[$actor->email] ?? null;

        if (
            ! $actor->isKdkmpManager()
            || ! $actor->hasValidIdentityContext()
            || ! $expectedOrganizationCode
            || $actor->organization?->code
                !== $expectedOrganizationCode
        ) {
            throw new AuthorizationException(
                'Contributor Readiness demo hanya dapat disetujui oleh Manager demo contributor yang sesuai.'
            );
        }
    }

    private function fail(
        string $message
    ): never {
        throw ValidationException::withMessages([
            'demo_scenario' =>
                $message,
        ]);
    }
}