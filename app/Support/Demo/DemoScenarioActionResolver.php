<?php

namespace App\Support\Demo;

use App\Enums\SupplyConfidence;
use App\Models\CommitmentVersion;
use App\Models\DemandForecast;
use App\Models\FallbackRequest;
use App\Models\Producer;
use App\Models\SupplyCommitment;
use App\Models\User;
use App\Models\FallbackOffer;
use App\Enums\ReadinessType;
use App\Models\ReadinessChecklist;

final class DemoScenarioActionResolver
{
    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     route: string
     * }|null
     */
    public function resolve(
        User $user
    ): ?array {
        $user->loadMissing(
            'organization'
        );

        $forecast =
            DemandForecast::query()
                ->where(
                    'forecast_code',
                    DemoIdentifiers::FORECAST_CODE
                )
                ->first();

        if (! $forecast) {
            return null;
        }

        if (
            $this->isPrimaryOperator(
                $user
            )
        ) {
            return $this->forPrimaryOperator(
                $user,
                $forecast
            );
        }

        if (
            $this->isPrimaryManager(
                $user
            )
        ) {
            return $this->forPrimaryManager(
                $user,
                $forecast
            );
        }

        if (
            $this->isNetworkOperator(
                $user
            )
        ) {
            return $this->forNetworkOperator(
                $user,
                $forecast
            );
        }

        if (
            $this->isNetworkManager(
                $user
            )
        ) {
            return $this->forNetworkManager(
                $user,
                $forecast
            );
        }

        return $this->resolveReadinessAction(
    $user,
    $forecast
);
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     route: string
     * }|null
     */
    private function forPrimaryOperator(
        User $user,
        DemandForecast $forecast
    ): ?array {
        $producer =
            Producer::query()
                ->where(
                    'organization_id',
                    $user->organization_id
                )
                ->where(
                    'producer_code',
                    DemoIdentifiers
                        ::PRIMARY_RISK_PRODUCER_CODE
                )
                ->first();

        if (! $producer) {
            return null;
        }

        $commitment =
            SupplyCommitment::query()
                ->where(
                    'forecast_id',
                    $forecast->id
                )
                ->where(
                    'organization_id',
                    $user->organization_id
                )
                ->where(
                    'producer_id',
                    $producer->id
                )
                ->first();

        if (! $commitment) {
            return null;
        }

        if (
            $commitment->current_confidence
            === SupplyConfidence::GREEN
        ) {
            return [
                'key' =>
                    'supply_risk',

                'label' =>
                    'Gangguan Pasokan',

                'route' =>
                    'demo.scenario.supply-risk',
            ];
        }

        if (
            $commitment->current_confidence
            !== SupplyConfidence::YELLOW
        ) {
            return null;
        }

        $fallbackRequest =
            $this->resolveFallbackRequest(
                $forecast,
                $user->organization_id
            );

        if (
            $fallbackRequest === null
            || $fallbackRequest->isDraft()
        ) {
            return [
                'key' =>
                    'fallback_request',

                'label' =>
                    'Siapkan Fallback 150 kg',

                'route' =>
                    'demo.scenario.fallback.request',
            ];
        }

        return $this->resolveReadinessAction(
            $user,
            $forecast
        );
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     route: string
     * }|null
     */
    private function forPrimaryManager(
        User $user,
        DemandForecast $forecast
    ): ?array {
        $fallbackRequest =
            $this->resolveFallbackRequest(
                $forecast,
                $user->organization_id
            );

        if (
            $fallbackRequest
            && $fallbackRequest
                ->isPendingApproval()
        ) {
            return [
                'key' =>
                    'fallback_broadcast',

                'label' =>
                    'Broadcast Fallback',

                'route' =>
                    'demo.scenario.fallback.broadcast',
            ];
        }

                if (
            $fallbackRequest
            && $fallbackRequest->isOpen()
        ) {
            $offer =
                $this->resolveDemoOffer(
                    $fallbackRequest
                );

            if (
                $offer
                && $offer->isAvailable()
            ) {
                return [
                    'key' =>
                        'fallback_offer_accept',

                    'label' =>
                        'Accept Fallback 150 kg',

                    'route' =>
                        'demo.scenario.fallback.offer.accept',
                ];
            }
        }

        return $this->resolveReadinessAction(
    $user,
    $forecast
);
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     route: string
     * }|null
     */
        private function forNetworkOperator(
        User $user,
        DemandForecast $forecast
    ): ?array {
        $fallbackRequest =
            $this->resolveDemoFallbackRequest(
                $forecast
            );

if (! $fallbackRequest) {
    return null;
}

if ($fallbackRequest->isFulfilled()) {
    return $this->resolveReadinessAction(
        $user,
        $forecast
    );
}

if (! $fallbackRequest->isOpen()) {
    return null;
}

        $commitment =
            $this->resolveNetworkSourceCommitment(
                $user,
                $forecast
            );

        if (! $commitment) {
            return [
                'key' =>
                    'fallback_source_prepare',

                'label' =>
                    'Siapkan Source 160 kg',

                'route' =>
                    'demo.scenario.fallback.source.prepare',
            ];
        }

        $version =
            $this->resolveInitialVersion(
                $commitment
            );

        if (
            $version
            && $version->isDraft()
        ) {
            return [
                'key' =>
                    'fallback_source_prepare',

                'label' =>
                    'Siapkan Source 160 kg',

                'route' =>
                    'demo.scenario.fallback.source.prepare',
            ];
        }

        if (
            ! $version
            || ! $version->isApproved()
            || $commitment
                ->current_confidence
                !== SupplyConfidence::GREEN
        ) {
            return null;
        }

        $offer =
            $this->resolveDemoOffer(
                $fallbackRequest
            );

        if (
            $offer === null
            || $offer->isDraft()
        ) {
            return [
                'key' =>
                    'fallback_offer_prepare',

                'label' =>
                    'Siapkan Offer 160 kg',

                'route' =>
                    'demo.scenario.fallback.offer.prepare',
            ];
        }

        return null;
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     route: string
     * }|null
     */
        private function forNetworkManager(
        User $user,
        DemandForecast $forecast
    ): ?array {
        $fallbackRequest =
            $this->resolveDemoFallbackRequest(
                $forecast
            );

if (! $fallbackRequest) {
    return null;
}

if ($fallbackRequest->isFulfilled()) {
    return $this->resolveReadinessAction(
        $user,
        $forecast
    );
}

if (! $fallbackRequest->isOpen()) {
    return null;
}

        $commitment =
            $this->resolveNetworkSourceCommitment(
                $user,
                $forecast
            );

        if (! $commitment) {
            return null;
        }

        $version =
            $this->resolveInitialVersion(
                $commitment
            );

        if (
            $version
            && $version->isPendingApproval()
        ) {
            return [
                'key' =>
                    'fallback_source_approve',

                'label' =>
                    'Approve Source 160 kg',

                'route' =>
                    'demo.scenario.fallback.source.approve',
            ];
        }

        if (
            ! $version
            || ! $version->isApproved()
            || $commitment
                ->current_confidence
                !== SupplyConfidence::GREEN
        ) {
            return null;
        }

        $offer =
            $this->resolveDemoOffer(
                $fallbackRequest
            );

        if (
            $offer
            && $offer->isPendingApproval()
        ) {
            return [
                'key' =>
                    'fallback_offer_approve',

                'label' =>
                    'Approve Offer 160 kg',

                'route' =>
                    'demo.scenario.fallback.offer.approve',
            ];
        }

        return null;
    }

    private function resolveFallbackRequest(
        DemandForecast $forecast,
        int $organizationId
    ): ?FallbackRequest {
        return FallbackRequest::query()
            ->where(
                'forecast_id',
                $forecast->id
            )
            ->where(
                'requester_organization_id',
                $organizationId
            )
            ->where(
                'broadcast_note',
                DemoIdentifiers
                    ::FALLBACK_REQUEST_NOTE
            )
            ->orderByDesc('id')
            ->first();
    }

    private function resolveDemoFallbackRequest(
        DemandForecast $forecast
    ): ?FallbackRequest {
        return FallbackRequest::query()
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
    }

    private function resolveNetworkSourceCommitment(
        User $user,
        DemandForecast $forecast
    ): ?SupplyCommitment {
        $producer =
            Producer::query()
                ->where(
                    'organization_id',
                    $user->organization_id
                )
                ->where(
                    'producer_code',
                    DemoIdentifiers
                        ::NETWORK_SOURCE_PRODUCER_CODE
                )
                ->first();

        if (! $producer) {
            return null;
        }

        return SupplyCommitment::query()
            ->where(
                'forecast_id',
                $forecast->id
            )
            ->where(
                'organization_id',
                $user->organization_id
            )
            ->where(
                'producer_id',
                $producer->id
            )
            ->first();
    }

    private function resolveInitialVersion(
        SupplyCommitment $commitment
    ): ?CommitmentVersion {
        return CommitmentVersion::query()
            ->where(
                'commitment_id',
                $commitment->id
            )
            ->where(
                'version_no',
                1
            )
            ->first();
    }


        private function resolveDemoOffer(
        FallbackRequest $fallbackRequest
    ): ?FallbackOffer {
        return FallbackOffer::query()
            ->where(
                'fallback_request_id',
                $fallbackRequest->id
            )
            ->where(
                'availability_note',
                DemoIdentifiers
                    ::FALLBACK_OFFER_NOTE
            )
            ->orderByDesc('id')
            ->first();
    }


    /**
 * @return array{
 *     key: string,
 *     label: string,
 *     route: string
 * }|null
 */
private function resolveReadinessAction(
    User $user,
    DemandForecast $forecast
): ?array {
    $fallbackRequest =
        $this->resolveDemoFallbackRequest(
            $forecast
        );

    if (
        ! $fallbackRequest
        || ! $fallbackRequest->isFulfilled()
    ) {
        return null;
    }

    $offer =
        $this->resolveDemoOffer(
            $fallbackRequest
        );

    if (
        ! $offer
        || ! $offer->isAccepted()
    ) {
        return null;
    }

    $stages = [
        [
            'type' =>
                ReadinessType::LOGISTICS,

            'prepare_key' =>
                'readiness_logistics_prepare',

            'prepare_label' =>
                'Siapkan Logistics Readiness',

            'prepare_route' =>
                'demo.scenario.readiness.logistics.prepare',

            'approve_key' =>
                'readiness_logistics_approve',

            'approve_label' =>
                'Approve Logistics Readiness',

            'approve_route' =>
                'demo.scenario.readiness.logistics.approve',
        ],

        [
            'type' =>
                ReadinessType::DOCUMENT,

            'prepare_key' =>
                'readiness_document_prepare',

            'prepare_label' =>
                'Siapkan Document Readiness',

            'prepare_route' =>
                'demo.scenario.readiness.document.prepare',

            'approve_key' =>
                'readiness_document_approve',

            'approve_label' =>
                'Approve Document Readiness',

            'approve_route' =>
                'demo.scenario.readiness.document.approve',
        ],
    ];

    foreach ($stages as $stage) {
        $checklist =
            ReadinessChecklist::query()
                ->where(
                    'forecast_id',
                    $forecast->id
                )
                ->where(
                    'organization_id',
                    $user->organization_id
                )
                ->where(
                    'readiness_type',
                    $stage['type']->value
                )
                ->where(
                    'is_current_version',
                    true
                )
                ->first();

        if ($user->isKdkmpOperator()) {
            if (
                ! $checklist
                || $checklist->isDraft()
            ) {
                return [
                    'key' =>
                        $stage[
                            'prepare_key'
                        ],

                    'label' =>
                        $stage[
                            'prepare_label'
                        ],

                    'route' =>
                        $stage[
                            'prepare_route'
                        ],
                ];
            }

            if (
                ! $checklist->isApproved()
            ) {
                return null;
            }

            continue;
        }

        if ($user->isKdkmpManager()) {
            if (! $checklist) {
                return null;
            }

            if (
                $checklist
                    ->isPendingApproval()
            ) {
                return [
                    'key' =>
                        $stage[
                            'approve_key'
                        ],

                    'label' =>
                        $stage[
                            'approve_label'
                        ],

                    'route' =>
                        $stage[
                            'approve_route'
                        ],
                ];
            }

            if (
                ! $checklist->isApproved()
            ) {
                return null;
            }
        }
    }

    return null;
}


    private function isPrimaryOperator(
        User $user
    ): bool {
        return $user->isKdkmpOperator()
            && $user->email
                === DemoIdentifiers
                    ::PRIMARY_OPERATOR_EMAIL
            && $user->organization?->code
                === DemoIdentifiers
                    ::PRIMARY_KDKMP_CODE;
    }

    private function isPrimaryManager(
        User $user
    ): bool {
        return $user->isKdkmpManager()
            && $user->email
                === DemoIdentifiers
                    ::PRIMARY_MANAGER_EMAIL
            && $user->organization?->code
                === DemoIdentifiers
                    ::PRIMARY_KDKMP_CODE;
    }

    private function isNetworkOperator(
        User $user
    ): bool {
        return $user->isKdkmpOperator()
            && $user->email
                === DemoIdentifiers
                    ::NETWORK_OPERATOR_EMAIL
            && $user->organization?->code
                === DemoIdentifiers
                    ::NETWORK_KDKMP_CODE;
    }

    private function isNetworkManager(
        User $user
    ): bool {
        return $user->isKdkmpManager()
            && $user->email
                === DemoIdentifiers
                    ::NETWORK_MANAGER_EMAIL
            && $user->organization?->code
                === DemoIdentifiers
                    ::NETWORK_KDKMP_CODE;
    }
}