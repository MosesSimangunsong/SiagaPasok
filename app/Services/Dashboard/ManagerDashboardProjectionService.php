<?php

namespace App\Services\Dashboard;

use App\Enums\CommitmentApprovalStatus;
use App\Enums\CommitmentLifecycleStatus;
use App\Enums\FallbackOfferStatus;
use App\Enums\FallbackRequestStatus;
use App\Enums\ForecastStatus;
use App\Enums\NetworkRole;
use App\Enums\ReadinessApprovalStatus;
use App\Enums\RecoveryRequestStatus;
use App\Enums\SupplyConfidence;
use App\Models\CommitmentVersion;
use App\Models\ConfidenceRecoveryRequest;
use App\Models\DemandForecast;
use App\Models\FallbackOffer;
use App\Models\FallbackRequest;
use App\Models\ReadinessChecklist;
use App\Models\SupplyCommitment;
use App\Models\SupplyNetworkLink;
use App\Models\User;
use App\Services\Readiness\ReadyForProcurementEvaluationService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class ManagerDashboardProjectionService
{
    public function __construct(
        private readonly ReadyForProcurementEvaluationService
            $readyForProcurementEvaluationService,
    ) {
    }

    public function build(
        User $user,
        ?CarbonInterface $evaluatedAt = null,
    ): array {
        $organizationId =
            (int) $user->organization_id;

        $evaluationTime =
            $evaluatedAt === null
                ? CarbonImmutable::now()
                : CarbonImmutable::instance(
                    $evaluatedAt
                );

        $commitmentVersions =
            $this->pendingCommitmentVersions(
                $organizationId
            );

        $recoveries =
            $this->pendingRecoveries(
                $organizationId
            );

        $fallbackRequests =
            $this->pendingFallbackRequests(
                $organizationId
            );

        $outgoingOffers =
            $this->pendingOutgoingOffers(
                $organizationId
            );

        $incomingOffers =
            $this->availableIncomingOffers(
                $organizationId
            );

        $readinessChecklists =
            $this->pendingReadiness(
                $organizationId
            );

        $supplyRisks =
            $this->supplyRisks(
                $organizationId
            );

        $primaryForecasts =
            $this->primaryForecasts(
                $organizationId
            )
                ->map(
                    fn (
                        DemandForecast $forecast
                    ): array => [
                        'forecast' =>
                            $this->serializeForecast(
                                $forecast
                            ),

                        'procurement_state' =>
                            $this
                                ->readyForProcurementEvaluationService
                                ->evaluate(
                                    $forecast,
                                    $evaluationTime
                                )
                                ->toArray(),
                    ]
                )
                ->values();

        $decisionGroups = [
            [
                'key' =>
                    'commitments',

                'label' =>
                    'Approval Komitmen',

                'href' =>
                    '/kdkmp/manager/approvals',

                'items' =>
                    $commitmentVersions
                        ->map(
                            fn (
                                CommitmentVersion $version
                            ): array =>
                                $this
                                    ->serializeCommitmentDecision(
                                        $version
                                    )
                        )
                        ->values(),
            ],

            [
                'key' =>
                    'recoveries',

                'label' =>
                    'Recovery Confidence',

                'href' =>
                    '/kdkmp/manager/recoveries',

                'items' =>
                    $recoveries
                        ->map(
                            fn (
                                ConfidenceRecoveryRequest $recovery
                            ): array =>
                                $this
                                    ->serializeRecoveryDecision(
                                        $recovery
                                    )
                        )
                        ->values(),
            ],

            [
                'key' =>
                    'fallback_requests',

                'label' =>
                    'Fallback Request',

                'href' =>
                    '/kdkmp/manager/fallback-requests',

                'items' =>
                    $fallbackRequests
                        ->map(
                            fn (
                                FallbackRequest $request
                            ): array =>
                                $this
                                    ->serializeFallbackRequestDecision(
                                        $request
                                    )
                        )
                        ->values(),
            ],

            [
                'key' =>
                    'outgoing_offers',

                'label' =>
                    'Outgoing Offer',

                'href' =>
                    '/kdkmp/manager/outgoing-offers',

                'items' =>
                    $outgoingOffers
                        ->map(
                            fn (
                                FallbackOffer $offer
                            ): array =>
                                $this
                                    ->serializeOutgoingOfferDecision(
                                        $offer
                                    )
                        )
                        ->values(),
            ],

            [
                'key' =>
                    'incoming_offers',

                'label' =>
                    'Incoming Offer',

                'href' =>
                    '/kdkmp/manager/incoming-offers',

                'items' =>
                    $incomingOffers
                        ->map(
                            fn (
                                FallbackOffer $offer
                            ): array =>
                                $this
                                    ->serializeIncomingOfferDecision(
                                        $offer
                                    )
                        )
                        ->values(),
            ],

            [
                'key' =>
                    'readiness',

                'label' =>
                    'Readiness Approval',

                'href' =>
                    '/kdkmp/manager/readiness',

                'items' =>
                    $readinessChecklists
                        ->map(
                            fn (
                                ReadinessChecklist $checklist
                            ): array =>
                                $this
                                    ->serializeReadinessDecision(
                                        $checklist
                                    )
                        )
                        ->values(),
            ],
        ];

        $totalPendingDecisions =
            $commitmentVersions->count()
            + $recoveries->count()
            + $fallbackRequests->count()
            + $outgoingOffers->count()
            + $incomingOffers->count()
            + $readinessChecklists->count();

        $readyForecastCount =
            $primaryForecasts
                ->filter(
                    fn (array $item): bool =>
                        (bool)
                        $item[
                            'procurement_state'
                        ][
                            'ready_for_procurement'
                        ]
                )
                ->count();

        return [
            'evaluatedAt' =>
                $evaluationTime
                    ->toIso8601String(),

            'organization' => [
                'id' =>
                    $user
                        ->organization
                        ->id,

                'code' =>
                    $user
                        ->organization
                        ->code,

                'name' =>
                    $user
                        ->organization
                        ->name,

                'general_location' =>
                    $user
                        ->organization
                        ->general_location,
            ],

            'summary' => [
                'total_pending_decisions' =>
                    $totalPendingDecisions,

                'commitment_approval_count' =>
                    $commitmentVersions->count(),

                'recovery_review_count' =>
                    $recoveries->count(),

                'fallback_request_approval_count' =>
                    $fallbackRequests->count(),

                'outgoing_offer_review_count' =>
                    $outgoingOffers->count(),

                'incoming_offer_decision_count' =>
                    $incomingOffers->count(),

                'readiness_approval_count' =>
                    $readinessChecklists->count(),

                'supply_risk_count' =>
                    $supplyRisks->count(),

                'primary_forecast_count' =>
                    $primaryForecasts->count(),

                'ready_for_procurement_count' =>
                    $readyForecastCount,
            ],

            'decisionGroups' =>
                $decisionGroups,

            'supplyRisks' =>
                $supplyRisks
                    ->map(
                        fn (
                            SupplyCommitment $commitment
                        ): array =>
                            $this->serializeSupplyRisk(
                                $commitment
                            )
                    )
                    ->values(),

            'primaryForecasts' =>
                $primaryForecasts,
        ];
    }

    private function pendingCommitmentVersions(
        int $organizationId,
    ) {
        return CommitmentVersion::query()
            ->where(
                'approval_status',
                CommitmentApprovalStatus
                    ::PENDING_APPROVAL
                    ->value
            )
            ->whereHas(
                'commitment',
                fn ($query) =>
                    $query
                        ->where(
                            'organization_id',
                            $organizationId
                        )
                        ->where(
                            'lifecycle_status',
                            CommitmentLifecycleStatus
                                ::ACTIVE
                                ->value
                        )
            )
            ->with([
                'unit',
                'submittedBy',
                'commitment.forecast.sppgOrganization',
                'commitment.forecast.commodity',
                'commitment.producer',
            ])
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get();
    }

    private function pendingRecoveries(
        int $organizationId,
    ) {
        return ConfidenceRecoveryRequest::query()
            ->where(
                'status',
                RecoveryRequestStatus
                    ::PENDING_APPROVAL
                    ->value
            )
            ->whereHas(
                'commitment',
                fn ($query) =>
                    $query
                        ->where(
                            'organization_id',
                            $organizationId
                        )
                        ->where(
                            'lifecycle_status',
                            CommitmentLifecycleStatus
                                ::ACTIVE
                                ->value
                        )
            )
            ->with([
                'requestedBy',
                'commitmentVersion.unit',
                'commitment.forecast.sppgOrganization',
                'commitment.forecast.commodity',
                'commitment.producer',
            ])
            ->orderBy('requested_at')
            ->orderBy('id')
            ->get();
    }

    private function pendingFallbackRequests(
        int $organizationId,
    ) {
        return FallbackRequest::query()
            ->where(
                'requester_organization_id',
                $organizationId
            )
            ->where(
                'status',
                FallbackRequestStatus
                    ::PENDING_APPROVAL
                    ->value
            )
            ->with([
                'forecast.sppgOrganization',
                'forecast.commodity',
                'unit',
                'submittedBy',
            ])
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get();
    }

    private function pendingOutgoingOffers(
        int $organizationId,
    ) {
        return FallbackOffer::query()
            ->where(
                'supplier_organization_id',
                $organizationId
            )
            ->where(
                'status',
                FallbackOfferStatus
                    ::PENDING_APPROVAL
                    ->value
            )
            ->with([
                'fallbackRequest.forecast.commodity',
                'fallbackRequest.requesterOrganization',
                'unit',
                'submittedBy',
            ])
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get();
    }

    private function availableIncomingOffers(
        int $organizationId,
    ) {
        return FallbackOffer::query()
            ->where(
                'status',
                FallbackOfferStatus
                    ::AVAILABLE
                    ->value
            )
            ->whereHas(
                'fallbackRequest',
                fn ($query) =>
                    $query->where(
                        'requester_organization_id',
                        $organizationId
                    )
            )
            ->with([
                'fallbackRequest.forecast.commodity',
                'fallbackRequest.unit',
                'supplierOrganization',
                'unit',
            ])
            ->orderBy('expires_at')
            ->orderBy('id')
            ->get();
    }

    private function pendingReadiness(
        int $organizationId,
    ) {
        return ReadinessChecklist::query()
            ->where(
                'organization_id',
                $organizationId
            )
            ->where(
                'status',
                ReadinessApprovalStatus
                    ::PENDING_APPROVAL
                    ->value
            )
            ->where(
                'is_current_version',
                true
            )
            ->with([
                'forecast.sppgOrganization',
                'forecast.commodity',
                'submittedBy',
            ])
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get();
    }

    private function supplyRisks(
        int $organizationId,
    ) {
        return SupplyCommitment::query()
            ->where(
                'organization_id',
                $organizationId
            )
            ->where(
                'lifecycle_status',
                CommitmentLifecycleStatus
                    ::ACTIVE
                    ->value
            )
            ->whereIn(
                'current_confidence',
                [
                    SupplyConfidence::YELLOW
                        ->value,

                    SupplyConfidence::RED
                        ->value,
                ]
            )
            ->with([
                'forecast.sppgOrganization',
                'forecast.commodity',
                'producer',
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();
    }

    private function primaryForecasts(
        int $organizationId,
    ) {
        $sppgIds =
            SupplyNetworkLink::query()
                ->where(
                    'kdkmp_organization_id',
                    $organizationId
                )
                ->where(
                    'network_role',
                    NetworkRole::PRIMARY
                        ->value
                )
                ->where(
                    'is_active',
                    true
                )
                ->whereHas(
                    'sppgOrganization',
                    fn ($query) =>
                        $query->where(
                            'is_active',
                            true
                        )
                )
                ->pluck(
                    'sppg_organization_id'
                );

        return DemandForecast::query()
            ->whereIn(
                'sppg_organization_id',
                $sppgIds
            )
            ->where(
                'status',
                ForecastStatus::PUBLISHED
                    ->value
            )
            ->with([
                'sppgOrganization',
                'commodity',
                'unit',
            ])
            ->orderBy('required_start_at')
            ->orderBy('id')
            ->get();
    }

    private function serializeCommitmentDecision(
        CommitmentVersion $version,
    ): array {
        $commitment =
            $version->commitment;

        return [
            'id' =>
                $version->id,

            'title' =>
                'Review Komitmen '
                .$commitment
                    ->forecast
                    ->forecast_code,

            'description' =>
                $commitment
                    ->producer
                    ->name
                .' • '
                .$commitment
                    ->forecast
                    ->commodity
                    ->name,

            'context' =>
                'Version '
                .$version->version_no
                .' • '
                .$version->min_volume
                .'–'
                .$version->max_volume
                .' '
                .$version->unit->symbol,

            'time_label' =>
                'Diajukan',

            'time_at' =>
                $version
                    ->submitted_at
                    ?->toIso8601String(),

            'href' =>
                '/kdkmp/manager/approvals/'
                .$commitment->id
                .'/versions/'
                .$version->id,
        ];
    }

    private function serializeRecoveryDecision(
        ConfidenceRecoveryRequest $recovery,
    ): array {
        $commitment =
            $recovery->commitment;

        return [
            'id' =>
                $recovery->id,

            'title' =>
                'Review Recovery Confidence',

            'description' =>
                $commitment
                    ->producer
                    ->name
                .' • '
                .$commitment
                    ->forecast
                    ->forecast_code,

            'context' =>
                $recovery
                    ->recovery_reason,

            'time_label' =>
                'Diminta',

            'time_at' =>
                $recovery
                    ->requested_at
                    ?->toIso8601String(),

            'href' =>
                '/kdkmp/manager/recoveries/'
                .$recovery->id,
        ];
    }

    private function serializeFallbackRequestDecision(
        FallbackRequest $request,
    ): array {
        return [
            'id' =>
                $request->id,

            'title' =>
                'Review Fallback Request',

            'description' =>
                $request
                    ->forecast
                    ->forecast_code
                .' • '
                .$request
                    ->forecast
                    ->commodity
                    ->name,

            'context' =>
                $request
                    ->requested_volume
                .' '
                .$request
                    ->unit
                    ->symbol,

            'time_label' =>
                'Diajukan',

            'time_at' =>
                $request
                    ->submitted_at
                    ?->toIso8601String(),

            'href' =>
                '/kdkmp/manager/fallback-requests/'
                .$request->id,
        ];
    }

    private function serializeOutgoingOfferDecision(
        FallbackOffer $offer,
    ): array {
        return [
            'id' =>
                $offer->id,

            'title' =>
                'Review Outgoing Offer',

            'description' =>
                $offer
                    ->fallbackRequest
                    ->requesterOrganization
                    ->name
                .' • '
                .$offer
                    ->fallbackRequest
                    ->forecast
                    ->commodity
                    ->name,

            'context' =>
                $offer
                    ->offered_volume
                .' '
                .$offer
                    ->unit
                    ->symbol,

            'time_label' =>
                'Diajukan',

            'time_at' =>
                $offer
                    ->submitted_at
                    ?->toIso8601String(),

            'href' =>
                '/kdkmp/fallback-offers/'
                .$offer->id,
        ];
    }

    private function serializeIncomingOfferDecision(
        FallbackOffer $offer,
    ): array {
        return [
            'id' =>
                $offer->id,

            'title' =>
                'Putuskan Incoming Offer',

            'description' =>
                $offer
                    ->supplierOrganization
                    ->name
                .' • '
                .$offer
                    ->fallbackRequest
                    ->forecast
                    ->commodity
                    ->name,

            'context' =>
                $offer
                    ->offered_volume
                .' '
                .$offer
                    ->unit
                    ->symbol,

            'time_label' =>
                'Berlaku sampai',

            'time_at' =>
                $offer
                    ->expires_at
                    ?->toIso8601String(),

            'href' =>
                '/kdkmp/manager/incoming-offers/'
                .$offer->id,
        ];
    }

    private function serializeReadinessDecision(
        ReadinessChecklist $checklist,
    ): array {
        return [
            'id' =>
                $checklist->id,

            'title' =>
                'Review '
                .$checklist
                    ->readiness_type
                    ->value
                .' Readiness',

            'description' =>
                $checklist
                    ->forecast
                    ->forecast_code
                .' • '
                .$checklist
                    ->forecast
                    ->commodity
                    ->name,

            'context' =>
                'Checklist v'
                .$checklist->version_no,

            'time_label' =>
                'Diajukan',

            'time_at' =>
                $checklist
                    ->submitted_at
                    ?->toIso8601String(),

            'href' =>
                '/kdkmp/manager/readiness/'
                .$checklist->id,
        ];
    }

    private function serializeSupplyRisk(
        SupplyCommitment $commitment,
    ): array {
        return [
            'id' =>
                $commitment->id,

            'forecast_code' =>
                $commitment
                    ->forecast
                    ->forecast_code,

            'commodity_name' =>
                $commitment
                    ->forecast
                    ->commodity
                    ->name,

            'producer_name' =>
                $commitment
                    ->producer
                    ->name,

            'current_confidence' =>
                $commitment
                    ->current_confidence
                    ?->value,

            'current_confidence_label' =>
                $commitment
                    ->current_confidence
                    ?->label(),

            'last_verified_at' =>
                $commitment
                    ->last_confidence_verified_at
                    ?->toIso8601String(),

            'href' =>
                '/kdkmp/commitments/'
                .$commitment->id,
        ];
    }

    private function serializeForecast(
        DemandForecast $forecast,
    ): array {
        return [
            'id' =>
                $forecast->id,

            'forecast_code' =>
                $forecast->forecast_code,

            'sppg' => [
                'id' =>
                    $forecast
                        ->sppgOrganization
                        ->id,

                'code' =>
                    $forecast
                        ->sppgOrganization
                        ->code,

                'name' =>
                    $forecast
                        ->sppgOrganization
                        ->name,
            ],

            'commodity' => [
                'id' =>
                    $forecast
                        ->commodity
                        ->id,

                'code' =>
                    $forecast
                        ->commodity
                        ->code,

                'name' =>
                    $forecast
                        ->commodity
                        ->name,
            ],

            'unit' => [
                'id' =>
                    $forecast
                        ->unit
                        ->id,

                'name' =>
                    $forecast
                        ->unit
                        ->name,

                'symbol' =>
                    $forecast
                        ->unit
                        ->symbol,

                'decimal_precision' =>
                    $forecast
                        ->unit
                        ->decimal_precision,
            ],

            'required_start_at' =>
                $forecast
                    ->required_start_at
                    ?->toIso8601String(),

            'required_end_at' =>
                $forecast
                    ->required_end_at
                    ?->toIso8601String(),
        ];
    }
}