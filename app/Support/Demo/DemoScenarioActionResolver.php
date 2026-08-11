<?php

namespace App\Support\Demo;

use App\Enums\SupplyConfidence;
use App\Models\DemandForecast;
use App\Models\FallbackRequest;
use App\Models\Producer;
use App\Models\SupplyCommitment;
use App\Models\User;

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

        if ($this->isPrimaryOperator($user)) {
            return $this->forPrimaryOperator(
                $user,
                $forecast
            );
        }

        if ($this->isPrimaryManager($user)) {
            return $this->forPrimaryManager(
                $user,
                $forecast
            );
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
                'key' => 'supply_risk',
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

        return null;
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
}