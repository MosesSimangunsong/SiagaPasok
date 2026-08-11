<?php

namespace App\Services\Dashboard;

use App\Enums\CommitmentApprovalStatus;
use App\Enums\CommitmentLifecycleStatus;
use App\Enums\FallbackOfferStatus;
use App\Enums\FallbackRequestStatus;
use App\Enums\ForecastStatus;
use App\Enums\NetworkRole;
use App\Enums\ReadinessApprovalStatus;
use App\Enums\ReadinessType;
use App\Enums\SupplyConfidence;
use App\Models\DemandForecast;
use App\Models\ExpectedHarvest;
use App\Models\FallbackOffer;
use App\Models\FallbackRequest;
use App\Models\ReadinessChecklist;
use App\Models\SupplyCommitment;
use App\Models\SupplyNetworkLink;
use App\Models\User;
use App\Services\Fallback\FallbackRequestService;
use App\Services\Readiness\ContributorReadinessResult;
use App\Services\Readiness\ReadyForProcurementEvaluationService;
use App\Support\FixedScaleDecimal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class OperatorDashboardProjectionService
{
    public function __construct(
        private readonly ReadyForProcurementEvaluationService
            $readyForProcurementEvaluationService,

        private readonly FallbackRequestService
            $fallbackRequestService,
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

        $primaryForecasts =
            $this->primaryForecasts(
                $organizationId
            );

        $forecastIds =
            $primaryForecasts
                ->pluck('id')
                ->map(
                    fn ($id): int =>
                        (int) $id
                )
                ->all();

        $currentChecklists =
            $this->currentChecklists(
                $organizationId,
                $forecastIds
            );

        $requesterRequests =
            $this->activeRequesterRequests(
                $organizationId,
                $forecastIds
            );

        $commitments =
            $this->activeCommitments(
                $organizationId
            );

        $networkRequests =
            $this->networkRequests(
                $organizationId
            );

        $supplierOffers =
            $this->activeSupplierOffers(
                $organizationId
            );

        $upcomingHarvests =
            $this->upcomingHarvests(
                $organizationId,
                $evaluationTime
            );

        $actionQueue = [];

        $this->appendCommitmentActions(
            $actionQueue,
            $commitments
        );

        $primaryForecastPayloads =
            $primaryForecasts
                ->map(
                    function (
                        DemandForecast $forecast
                    ) use (
                        $organizationId,
                        $evaluationTime,
                        $currentChecklists,
                        $requesterRequests,
                        &$actionQueue,
                    ): array {
                        $evaluation =
                            $this
                                ->readyForProcurementEvaluationService
                                ->evaluate(
                                    $forecast,
                                    $evaluationTime
                                );

                        $this->appendForecastActions(
                            $actionQueue,
                            $forecast,
                            $evaluation,
                            $organizationId,
                            $currentChecklists,
                            $requesterRequests
                        );

                        return [
                            'forecast' =>
                                $this->serializeForecast(
                                    $forecast
                                ),

                            'procurement_state' =>
                                $evaluation->toArray(),
                        ];
                    }
                )
                ->values();

$this->appendNetworkActions(
    $actionQueue,
    $networkRequests,
    $supplierOffers
);
        $this->appendSupplierOfferActions(
            $actionQueue,
            $supplierOffers
        );

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
                'active_forecast_count' =>
                    $primaryForecasts->count(),

                'action_count' =>
                    count($actionQueue),

                'network_request_count' =>
                    $networkRequests->count(),

                'upcoming_harvest_count' =>
                    $upcomingHarvests->count(),
            ],

            'primaryForecasts' =>
                $primaryForecastPayloads,

            'actionQueue' =>
                array_values(
                    $actionQueue
                ),

            'upcomingHarvests' =>
                $upcomingHarvests
                    ->map(
                        fn (
                            ExpectedHarvest $harvest
                        ): array =>
                            $this
                                ->serializeExpectedHarvest(
                                    $harvest
                                )
                    )
                    ->values(),

            'activeFallback' => [
                'requesterRequests' =>
                    $requesterRequests
                        ->map(
                            fn (
                                FallbackRequest $request
                            ): array =>
                                $this
                                    ->serializeRequesterRequest(
                                        $request
                                    )
                        )
                        ->values(),

                'networkRequests' =>
                    $networkRequests
                        ->map(
                            fn (
                                FallbackRequest $request
                            ): array =>
                                $this
                                    ->serializeNetworkRequest(
                                        $request
                                    )
                        )
                        ->values(),

                'supplierOffers' =>
                    $supplierOffers
                        ->map(
                            fn (
                                FallbackOffer $offer
                            ): array =>
                                $this
                                    ->serializeSupplierOffer(
                                        $offer
                                    )
                        )
                        ->values(),
            ],
        ];
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
                    NetworkRole::PRIMARY->value
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
                ForecastStatus::PUBLISHED->value
            )
            ->with([
                'sppgOrganization',
                'commodity',
                'unit',
            ])
            ->orderBy(
                'required_start_at'
            )
            ->orderBy('id')
            ->get();
    }

    private function currentChecklists(
        int $organizationId,
        array $forecastIds,
    ) {
        if ($forecastIds === []) {
            return collect();
        }

        return ReadinessChecklist::query()
            ->where(
                'organization_id',
                $organizationId
            )
            ->whereIn(
                'forecast_id',
                $forecastIds
            )
            ->where(
                'is_current_version',
                true
            )
            ->get()
            ->keyBy(
                fn (
                    ReadinessChecklist $checklist
                ): string =>
                    $this->readinessKey(
                        $checklist->forecast_id,
                        $checklist
                            ->readiness_type
                    )
            );
    }

    private function activeRequesterRequests(
        int $organizationId,
        array $forecastIds,
    ) {
        if ($forecastIds === []) {
            return collect();
        }

        return FallbackRequest::query()
            ->where(
                'requester_organization_id',
                $organizationId
            )
            ->whereIn(
                'forecast_id',
                $forecastIds
            )
            ->whereIn(
                'status',
                [
                    FallbackRequestStatus::DRAFT
                        ->value,

                    FallbackRequestStatus
                        ::PENDING_APPROVAL
                        ->value,

                    FallbackRequestStatus::OPEN
                        ->value,
                ]
            )
            ->with([
                'forecast.sppgOrganization',
                'forecast.commodity',
                'forecast.unit',
                'unit',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    private function activeCommitments(
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
            ->with([
                'forecast.sppgOrganization',
                'commodity',
                'producer',
                'activeVersion.unit',

                'versions' =>
                    fn ($query) =>
                        $query->orderByDesc(
                            'version_no'
                        ),
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();
    }

    private function networkRequests(
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
                    NetworkRole::NETWORK->value
                )
                ->where(
                    'is_active',
                    true
                )
                ->pluck(
                    'sppg_organization_id'
                );

        if ($sppgIds->isEmpty()) {
            return collect();
        }

        return FallbackRequest::query()
            ->where(
                'status',
                FallbackRequestStatus::OPEN->value
            )
            ->whereHas(
                'forecast',
                fn ($query) =>
                    $query->whereIn(
                        'sppg_organization_id',
                        $sppgIds
                    )
            )
            ->where(
                'requester_organization_id',
                '!=',
                $organizationId
            )
            ->with([
                'forecast.commodity',
                'forecast.unit',
                'requesterOrganization',
                'unit',
            ])
            ->orderBy(
                'response_deadline_at'
            )
            ->orderBy('id')
            ->get();
    }

    private function activeSupplierOffers(
        int $organizationId,
    ) {
        return FallbackOffer::query()
            ->where(
                'supplier_organization_id',
                $organizationId
            )
            ->whereIn(
                'status',
                [
                    FallbackOfferStatus::DRAFT
                        ->value,

                    FallbackOfferStatus
                        ::PENDING_APPROVAL
                        ->value,

                    FallbackOfferStatus::AVAILABLE
                        ->value,
                ]
            )
            ->with([
                'fallbackRequest.forecast.commodity',
                'fallbackRequest.requesterOrganization',
                'unit',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    private function upcomingHarvests(
        int $organizationId,
        CarbonImmutable $evaluationTime,
    ) {
        return ExpectedHarvest::query()
            ->where(
                'organization_id',
                $organizationId
            )
            ->where(
                'harvest_end_at',
                '>=',
                $evaluationTime
            )
            ->with([
                'producer',
                'commodity',
                'unit',
            ])
            ->orderBy(
                'harvest_start_at'
            )
            ->orderBy('id')
            ->limit(5)
            ->get();
    }

    private function appendCommitmentActions(
        array &$actions,
        $commitments,
    ): void {
        foreach (
            $commitments
            as $commitment
        ) {
            $latestVersion =
                $commitment
                    ->versions
                    ->first();

            if (
                $latestVersion
                && $latestVersion
                    ->approval_status
                    === CommitmentApprovalStatus::DRAFT
            ) {
                $actions[] = [
                    'kind' =>
                        'COMMITMENT_DRAFT',

                    'severity' =>
                        'ATTENTION',

                    'title' =>
                        'Selesaikan draft komitmen',

                    'description' =>
                        $commitment
                            ->forecast
                            ->forecast_code
                        .' • '
                        .$commitment
                            ->producer
                            ->name,

                    'href' =>
                        '/kdkmp/commitments/'
                        .$commitment->id,
                ];
            }

            if (
                in_array(
                    $commitment
                        ->current_confidence,
                    [
                        SupplyConfidence::YELLOW,
                        SupplyConfidence::RED,
                    ],
                    true
                )
            ) {
                $actions[] = [
                    'kind' =>
                        'SUPPLY_RISK',

                    'severity' =>
                        'CRITICAL',

                    'title' =>
                        'Tinjau komitmen berisiko',

                    'description' =>
                        $commitment
                            ->forecast
                            ->forecast_code
                        .' • '
                        .$commitment
                            ->producer
                            ->name
                        .' • '
                        .(
                            $commitment
                                ->current_confidence
                                ?->label()
                            ?? $commitment
                                ->current_confidence
                                ?->value
                            ?? 'Tidak diketahui'
                        ),

                    'href' =>
                        '/kdkmp/commitments/'
                        .$commitment->id,
                ];
            }
        }
    }

    private function appendForecastActions(
        array &$actions,
        DemandForecast $forecast,
        $evaluation,
        int $organizationId,
        $currentChecklists,
        $requesterRequests,
    ): void {
        $activeRequests =
            $requesterRequests
                ->where(
                    'forecast_id',
                    $forecast->id
                );

        if (
            $evaluation->shortfall !== null
            && ! FixedScaleDecimal::from(
                $evaluation->shortfall
            )->isZero()
        ) {
            $draftRequest =
                $activeRequests
                    ->first(
                        fn (
                            FallbackRequest $request
                        ): bool =>
                            $request->isDraft()
                    );

            if ($draftRequest) {
                $actions[] = [
                    'kind' =>
                        'FALLBACK_REQUEST_DRAFT',

                    'severity' =>
                        'CRITICAL',

                    'title' =>
                        'Selesaikan draft Fallback Request',

                    'description' =>
                        $forecast
                            ->forecast_code
                        .' masih memiliki shortfall.',

                    'href' =>
                        '/kdkmp/fallback-requests/'
                        .$draftRequest->id,
                ];
            } elseif ($activeRequests->isEmpty()) {
                $actions[] = [
                    'kind' =>
                        'FALLBACK_SHORTFALL',

                    'severity' =>
                        'CRITICAL',

                    'title' =>
                        'Siapkan Fallback Request',

                    'description' =>
                        $forecast
                            ->forecast_code
                        .' memiliki shortfall '
                        .$evaluation->shortfall
                        .' '
                        .$forecast->unit->symbol
                        .'.',

                    'href' =>
                        '/kdkmp/fallback-requests/create'
                        .'?forecast_id='
                        .$forecast->id,
                ];
            }
        }

        $readiness =
            $this->findContributorReadiness(
                $evaluation
                    ->contributorReadinessResults,
                $organizationId
            );

        if (
            ! $readiness
            || ! $readiness->isContributor
        ) {
            return;
        }

        $this->appendReadinessAction(
            $actions,
            $forecast,
            ReadinessType::LOGISTICS,
            $readiness->logisticsReady,
            $readiness
                ->logisticsReasonCodes,
            $currentChecklists
        );

        $this->appendReadinessAction(
            $actions,
            $forecast,
            ReadinessType::DOCUMENT,
            $readiness->documentReady,
            $readiness
                ->documentReasonCodes,
            $currentChecklists
        );
    }

    private function appendReadinessAction(
        array &$actions,
        DemandForecast $forecast,
        ReadinessType $type,
        bool $ready,
        array $reasonCodes,
        $currentChecklists,
    ): void {
        if ($ready) {
            return;
        }

        $checklist =
            $currentChecklists
                ->get(
                    $this->readinessKey(
                        $forecast->id,
                        $type
                    )
                );

        if (
            $checklist
            && $checklist->status
                === ReadinessApprovalStatus
                    ::PENDING_APPROVAL
        ) {
            return;
        }

        $label =
            $type === ReadinessType::LOGISTICS
                ? 'Logistics'
                : 'Document';

        $actions[] = [
            'kind' =>
                $type
                === ReadinessType::LOGISTICS
                    ? 'READINESS_LOGISTICS'
                    : 'READINESS_DOCUMENT',

            'severity' =>
                'ATTENTION',

            'title' =>
                "Tindak lanjuti {$label} Readiness",

            'description' =>
                $forecast->forecast_code,

            'reason_codes' =>
                $reasonCodes,

'href' =>
    '/kdkmp/contributor-readiness/'
    .$forecast->id,
        ];
    }

    private function appendNetworkActions(
    array &$actions,
    $networkRequests,
    $supplierOffers,
): void {
    $requestIdsWithActiveOffer =
        $supplierOffers
            ->pluck(
                'fallback_request_id'
            )
            ->map(
                fn ($id): int =>
                    (int) $id
            )
            ->all();

    foreach (
        $networkRequests
        as $request
    ) {
        /*
         * Jika supplier sudah mempunyai
         * DRAFT / PENDING_APPROVAL / AVAILABLE
         * Offer untuk Request ini, jangan
         * munculkan broadcast sebagai pekerjaan
         * kedua.
         *
         * DRAFT Offer akan memperoleh task-nya
         * sendiri. PENDING/AVAILABLE berarti
         * Operator sedang menunggu pihak lain.
         */
        if (
            in_array(
                (int) $request->id,
                $requestIdsWithActiveOffer,
                true
            )
        ) {
            continue;
        }

        $actions[] = [
            'kind' =>
                'NETWORK_FALLBACK',

            'severity' =>
                'INFO',

            'title' =>
                'Tinjau broadcast Fallback',

            'description' =>
                $request
                    ->forecast
                    ->commodity
                    ->name
                .' • '
                .$request
                    ->requesterOrganization
                    ->name,

            'href' =>
                '/kdkmp/fallback-network/'
                .$request->id,
        ];
    }
}

    private function appendSupplierOfferActions(
        array &$actions,
        $supplierOffers,
    ): void {
        foreach (
            $supplierOffers
            as $offer
        ) {
            if (! $offer->isDraft()) {
                continue;
            }

            $actions[] = [
                'kind' =>
                    'FALLBACK_OFFER_DRAFT',

                'severity' =>
                    'ATTENTION',

                'title' =>
                    'Selesaikan draft Fallback Offer',

                'description' =>
                    $offer
                        ->fallbackRequest
                        ->forecast
                        ->commodity
                        ->name
                    .' • '
                    .$offer
                        ->fallbackRequest
                        ->requesterOrganization
                        ->name,

                'href' =>
                    '/kdkmp/fallback-offers/'
                    .$offer->id,
            ];
        }
    }

    private function findContributorReadiness(
        array $results,
        int $organizationId,
    ): ?ContributorReadinessResult {
        foreach ($results as $result) {
            if (
                $result->organizationId
                === $organizationId
            ) {
                return $result;
            }
        }

        return null;
    }

    private function readinessKey(
        int $forecastId,
        ReadinessType $type,
    ): string {
        return $forecastId
            .':'
            .$type->value;
    }

    private function serializeForecast(
        DemandForecast $forecast,
    ): array {
        return [
            'id' =>
                $forecast->id,

            'forecast_code' =>
                $forecast->forecast_code,

            'version' =>
                $forecast->version,

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

    private function serializeExpectedHarvest(
        ExpectedHarvest $harvest,
    ): array {
        return [
            'id' =>
                $harvest->id,

            'producer' => [
                'id' =>
                    $harvest
                        ->producer
                        ->id,

                'producer_code' =>
                    $harvest
                        ->producer
                        ->producer_code,

                'name' =>
                    $harvest
                        ->producer
                        ->name,
            ],

            'commodity' => [
                'id' =>
                    $harvest
                        ->commodity
                        ->id,

                'code' =>
                    $harvest
                        ->commodity
                        ->code,

                'name' =>
                    $harvest
                        ->commodity
                        ->name,
            ],

            'expected_min_volume' =>
                (string)
                $harvest
                    ->expected_min_volume,

            'expected_max_volume' =>
                (string)
                $harvest
                    ->expected_max_volume,

            'unit' => [
                'symbol' =>
                    $harvest
                        ->unit
                        ->symbol,

                'decimal_precision' =>
                    $harvest
                        ->unit
                        ->decimal_precision,
            ],

            'harvest_start_at' =>
                $harvest
                    ->harvest_start_at
                    ?->toIso8601String(),

            'harvest_end_at' =>
                $harvest
                    ->harvest_end_at
                    ?->toIso8601String(),
        ];
    }

    private function serializeRequesterRequest(
        FallbackRequest $request,
    ): array {
        return [
            'id' =>
                $request->id,

            'forecast_code' =>
                $request
                    ->forecast
                    ->forecast_code,

            'commodity_name' =>
                $request
                    ->forecast
                    ->commodity
                    ->name,

            'requested_volume' =>
                (string)
                $request
                    ->requested_volume,

            'accepted_volume' =>
                $this
                    ->fallbackRequestService
                    ->calculateAcceptedVolume(
                        $request
                    ),

            'remaining_volume' =>
                $this
                    ->fallbackRequestService
                    ->calculateRemainingVolume(
                        $request
                    ),

            'unit_symbol' =>
                $request
                    ->unit
                    ->symbol,

            'status' =>
                $request
                    ->status
                    ->value,

            'status_label' =>
                $request
                    ->status
                    ->label(),

            'response_deadline_at' =>
                $request
                    ->response_deadline_at
                    ?->toIso8601String(),
        ];
    }

    private function serializeNetworkRequest(
        FallbackRequest $request,
    ): array {
        return [
            'id' =>
                $request->id,

            'requester_organization_name' =>
                $request
                    ->requesterOrganization
                    ->name,

            'commodity_name' =>
                $request
                    ->forecast
                    ->commodity
                    ->name,

            'remaining_volume' =>
                $this
                    ->fallbackRequestService
                    ->calculateRemainingVolume(
                        $request
                    ),

            'unit_symbol' =>
                $request
                    ->unit
                    ->symbol,

            'response_deadline_at' =>
                $request
                    ->response_deadline_at
                    ?->toIso8601String(),
        ];
    }

    private function serializeSupplierOffer(
        FallbackOffer $offer,
    ): array {
        return [
            'id' =>
                $offer->id,

            'request_id' =>
                $offer
                    ->fallback_request_id,

            'requester_organization_name' =>
                $offer
                    ->fallbackRequest
                    ->requesterOrganization
                    ->name,

            'commodity_name' =>
                $offer
                    ->fallbackRequest
                    ->forecast
                    ->commodity
                    ->name,

            'offered_volume' =>
                (string)
                $offer
                    ->offered_volume,

            'accepted_volume' =>
                (string)
                $offer
                    ->accepted_volume,

            'unit_symbol' =>
                $offer
                    ->unit
                    ->symbol,

            'status' =>
                $offer
                    ->status
                    ->value,

            'status_label' =>
                $offer
                    ->status
                    ->label(),

            'expires_at' =>
                $offer
                    ->expires_at
                    ?->toIso8601String(),
        ];
    }
}