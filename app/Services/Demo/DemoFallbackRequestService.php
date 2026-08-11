<?php

namespace App\Services\Demo;

use App\Models\DemandForecast;
use App\Models\FallbackRequest;
use App\Models\User;
use App\Services\Fallback\FallbackRequestService;
use App\Services\Supply\SupplyMetricsService;
use App\Support\Demo\DemoIdentifiers;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class DemoFallbackRequestService
{
    public function __construct(
        private readonly FallbackRequestService $fallbackRequestService,
        private readonly SupplyMetricsService $supplyMetricsService,
    ) {
    }

    public function prepareAndSubmit(
        User $actor
    ): FallbackRequest {
        $actor->loadMissing(
            'organization'
        );

        $this->assertPrimaryOperator(
            $actor
        );

        $forecast =
            $this->resolveForecast();

        $this->assertDisruptedState(
            $forecast
        );

        $existing =
            $this->resolveExistingRequest(
                $forecast,
                $actor->organization_id
            );

        if ($existing) {
            $this->assertRequestIdentity(
                $existing,
                $forecast
            );

            if ($existing->isDraft()) {
                return $this
                    ->fallbackRequestService
                    ->submit(
                        $actor,
                        $existing
                    );
            }

            if (
                $existing->isPendingApproval()
                || $existing->isOpen()
                || $existing->isFulfilled()
            ) {
                return $existing->refresh();
            }

            $this->fail(
                'Fallback Request demo sudah berada pada terminal state. Gunakan Demo Reset untuk memulai ulang scenario.'
            );
        }

        $deadline =
            $this->determineResponseDeadline(
                $forecast
            );

        $request =
            $this->fallbackRequestService
                ->createDraft(
                    $actor,
                    $forecast,
                    [
                        'requested_volume' =>
                            DemoIdentifiers
                                ::FALLBACK_REQUEST_VOLUME,

                        'response_deadline_at' =>
                            $deadline
                                ->toDateTimeString(),

                        'broadcast_note' =>
                            DemoIdentifiers
                                ::FALLBACK_REQUEST_NOTE,
                    ]
                );

        return $this
            ->fallbackRequestService
            ->submit(
                $actor,
                $request
            );
    }

    public function approveBroadcast(
        User $actor
    ): FallbackRequest {
        $actor->loadMissing(
            'organization'
        );

        $this->assertPrimaryManager(
            $actor
        );

        $forecast =
            $this->resolveForecast();

        $request =
            $this->resolveExistingRequest(
                $forecast,
                $actor->organization_id
            );

        if (! $request) {
            $this->fail(
                'Fallback Request demo belum disiapkan oleh Operator.'
            );
        }

        $this->assertRequestIdentity(
            $request,
            $forecast
        );

        if (
            $request->isOpen()
            || $request->isFulfilled()
        ) {
            return $request->refresh();
        }

        if (! $request->isPendingApproval()) {
            $this->fail(
                'Fallback Request demo belum berada pada PENDING_APPROVAL.'
            );
        }

        return $this
            ->fallbackRequestService
            ->approveBroadcast(
                $actor,
                $request
            );
    }

    private function resolveForecast(): DemandForecast
    {
        $forecast =
            DemandForecast::query()
                ->where(
                    'forecast_code',
                    DemoIdentifiers::FORECAST_CODE
                )
                ->first();

        if (! $forecast) {
            $this->fail(
                'Forecast demo Kangkung 400 kg belum tersedia.'
            );
        }

        if (! $forecast->isPublished()) {
            $this->fail(
                'Forecast demo tidak lagi PUBLISHED.'
            );
        }

        return $forecast;
    }

    private function resolveExistingRequest(
        DemandForecast $forecast,
        int $requesterOrganizationId
    ): ?FallbackRequest {
        $requests =
            FallbackRequest::query()
                ->where(
                    'forecast_id',
                    $forecast->id
                )
                ->where(
                    'requester_organization_id',
                    $requesterOrganizationId
                )
                ->where(
                    'broadcast_note',
                    DemoIdentifiers
                        ::FALLBACK_REQUEST_NOTE
                )
                ->orderBy('id')
                ->get();

        if ($requests->count() > 1) {
            $this->fail(
                'Lebih dari satu Fallback Request dengan marker demo yang sama ditemukan.'
            );
        }

        return $requests->first();
    }

    private function assertRequestIdentity(
        FallbackRequest $request,
        DemandForecast $forecast
    ): void {
        $expectedOperatorId =
            User::query()
                ->where(
                    'email',
                    DemoIdentifiers
                        ::PRIMARY_OPERATOR_EMAIL
                )
                ->value('id');

        if (
            (string) $request->requested_volume
                !== DemoIdentifiers
                    ::FALLBACK_REQUEST_VOLUME
            || $request->unit_id
                !== $forecast->unit_id
            || $request->created_by
                !== $expectedOperatorId
            || $request->broadcast_note
                !== DemoIdentifiers
                    ::FALLBACK_REQUEST_NOTE
        ) {
            $this->fail(
                'Fallback Request dengan marker demo ditemukan tetapi payload atau maker-nya tidak sesuai locked M13 scenario.'
            );
        }
    }

    private function determineResponseDeadline(
        DemandForecast $forecast
    ): CarbonImmutable {
        $now =
            CarbonImmutable::now();

        $requiredStart =
            CarbonImmutable::instance(
                $forecast->required_start_at
            );

        $requiredEnd =
            CarbonImmutable::instance(
                $forecast->required_end_at
            );

        /*
         * Prefer deadline tujuh hari sebelum
         * operational requirement.
         *
         * Jika scenario sudah semakin dekat,
         * gunakan boundary satu jam sebelum
         * required_end_at.
         */
        $preferred =
            $requiredStart->subDays(7);

        $minimumComfort =
            $now->addDay();

        $deadline =
            $preferred->gt(
                $minimumComfort
            )
                ? $preferred
                : $requiredEnd->subHour();

        if (! $deadline->gt($now)) {
            $this->fail(
                'Forecast demo terlalu dekat atau sudah melewati operational boundary untuk membuat Fallback Request baru.'
            );
        }

        return $deadline;
    }

    private function assertDisruptedState(
        DemandForecast $forecast
    ): void {
        $metrics =
            $this->supplyMetricsService
                ->calculate(
                    $forecast
                );

        if (
            $metrics->demandTarget
                !== '400.000000'
            || $metrics->directSafeSupply
                !== '250.000000'
            || $metrics->atRiskSupply
                !== '150.000000'
            || $metrics->fallbackSafeSupply
                !== '0.000000'
            || $metrics->totalSafeSupply
                !== '250.000000'
            || $metrics->coveragePercent
                !== '62.50'
            || $metrics->shortfall
                !== '150.000000'
            || $metrics->volumeReady
        ) {
            $this->fail(
                'Fallback demo membutuhkan canonical disrupted state Demand 400 / Safe 250 / At-Risk 150 / Shortfall 150.'
            );
        }
    }

    private function assertPrimaryOperator(
        User $actor
    ): void {
        if (
            ! $actor->isKdkmpOperator()
            || ! $actor->hasValidIdentityContext()
            || $actor->email
                !== DemoIdentifiers
                    ::PRIMARY_OPERATOR_EMAIL
            || $actor->organization?->code
                !== DemoIdentifiers
                    ::PRIMARY_KDKMP_CODE
        ) {
            throw new AuthorizationException(
                'Fallback Request demo hanya dapat disiapkan oleh Operator demo KDKMP Tani Sejahtera.'
            );
        }
    }

    private function assertPrimaryManager(
        User $actor
    ): void {
        if (
            ! $actor->isKdkmpManager()
            || ! $actor->hasValidIdentityContext()
            || $actor->email
                !== DemoIdentifiers
                    ::PRIMARY_MANAGER_EMAIL
            || $actor->organization?->code
                !== DemoIdentifiers
                    ::PRIMARY_KDKMP_CODE
        ) {
            throw new AuthorizationException(
                'Fallback Request demo hanya dapat dibroadcast oleh Manager demo KDKMP Tani Sejahtera.'
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