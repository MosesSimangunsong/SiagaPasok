<?php

namespace App\Services\Demo;

use App\Enums\SupplyConfidence;
use App\Models\CommitmentVersion;
use App\Models\DemandForecast;
use App\Models\ExpectedHarvest;
use App\Models\FallbackRequest;
use App\Models\Producer;
use App\Models\SupplyCommitment;
use App\Models\User;
use App\Services\Commitment\CommitmentWorkflowService;
use App\Services\Fallback\FallbackCapacityService;
use App\Services\Supply\ExpectedHarvestService;
use App\Support\Demo\DemoIdentifiers;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class DemoFallbackSourceService
{
    public function __construct(
        private readonly CommitmentWorkflowService $commitmentWorkflowService,
        private readonly ExpectedHarvestService $expectedHarvestService,
        private readonly FallbackCapacityService $fallbackCapacityService,
    ) {
    }

    public function prepareAndSubmit(
        User $actor
    ): SupplyCommitment {
        $actor->loadMissing(
            'organization'
        );

        $this->assertNetworkOperator(
            $actor
        );

        [
            $forecast,
            $fallbackRequest,
        ] = $this->resolveOpenContext();

        $producer =
            $this->resolveSourceProducer(
                $actor
            );

        $existing =
            $this->resolveSourceCommitment(
                $forecast,
                $actor,
                $producer
            );

        if ($existing) {
            $version =
                $this->resolveInitialVersion(
                    $existing
                );

            $this->assertSourceIdentity(
                $existing,
                $version,
                $forecast,
                $actor,
                $producer
            );

            if ($version->isDraft()) {
                $this->commitmentWorkflowService
                    ->submit(
                        $actor,
                        $version
                    );
            } elseif (
                ! $version->isPendingApproval()
                && ! $version->isApproved()
            ) {
                $this->fail(
                    'Source Commitment demo berada pada state yang tidak kompatibel. Gunakan Demo Reset untuk memulai ulang scenario.'
                );
            }

            return $existing
                ->refresh()
                ->load([
                    'activeVersion',
                    'versions',
                ]);
        }

        $expectedHarvest =
            $this->ensureExpectedHarvest(
                actor: $actor,
                forecast: $forecast,
                producer: $producer,
            );

        $commitment =
            $this->commitmentWorkflowService
                ->createFallbackSourceDraft(
                    $actor,
                    $fallbackRequest,
                    [
                        'producer_id' =>
                            $producer->id,

                        'expected_harvest_id' =>
                            $expectedHarvest->id,

                        'min_volume' =>
                            DemoIdentifiers
                                ::NETWORK_SOURCE_VOLUME,

                        'max_volume' =>
                            DemoIdentifiers
                                ::NETWORK_SOURCE_VOLUME,

                        'unit_id' =>
                            $forecast->unit_id,

                        'availability_start_at' =>
                            $forecast
                                ->required_start_at
                                ->toDateTimeString(),

                        'availability_end_at' =>
                            $forecast
                                ->required_end_at
                                ->toDateTimeString(),

                        'notes' =>
                            DemoIdentifiers
                                ::NETWORK_SOURCE_COMMITMENT_NOTE,

                        'operator_justification' =>
                            null,
                    ]
                );

        $version =
            $this->resolveInitialVersion(
                $commitment
            );

        $this->commitmentWorkflowService
            ->submit(
                $actor,
                $version
            );

        return $commitment
            ->refresh()
            ->load([
                'activeVersion',
                'versions',
            ]);
    }

    public function approve(
        User $actor
    ): SupplyCommitment {
        $actor->loadMissing(
            'organization'
        );

        $this->assertNetworkManager(
            $actor
        );

        [
            $forecast,
        ] = $this->resolveOpenContext();

        $producer =
            $this->resolveSourceProducer(
                $actor
            );

        $commitment =
            $this->resolveSourceCommitment(
                $forecast,
                $actor,
                $producer
            );

        if (! $commitment) {
            $this->fail(
                'Source Commitment demo 160 kg belum disiapkan oleh Operator Mitra Lestari.'
            );
        }

        $version =
            $this->resolveInitialVersion(
                $commitment
            );

        $this->assertSourceIdentity(
            $commitment,
            $version,
            $forecast,
            $actor,
            $producer
        );

        if ($version->isApproved()) {
            $this->assertApprovedSource(
                $commitment,
                $forecast,
                $actor
            );

            return $commitment
                ->refresh()
                ->load('activeVersion');
        }

        if (! $version->isPendingApproval()) {
            $this->fail(
                'Source Commitment demo belum berada pada PENDING_APPROVAL.'
            );
        }

        $this->commitmentWorkflowService
            ->approve(
                $actor,
                $version
            );

        $commitment =
            $commitment
                ->refresh()
                ->load('activeVersion');

        $this->assertApprovedSource(
            $commitment,
            $forecast,
            $actor
        );

        return $commitment;
    }

    /**
     * @return array{
     *     0: DemandForecast,
     *     1: FallbackRequest
     * }
     */
    private function resolveOpenContext(): array
    {
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
                'Forecast demo Kangkung 400 kg belum tersedia atau tidak lagi PUBLISHED.'
            );
        }

        $fallbackRequest =
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
            ! $fallbackRequest
            || ! $fallbackRequest->isOpen()
        ) {
            $this->fail(
                'Fallback Request demo harus OPEN sebelum NETWORK menyiapkan source supply.'
            );
        }

        return [
            $forecast,
            $fallbackRequest,
        ];
    }

    private function resolveSourceProducer(
        User $actor
    ): Producer {
        $producer =
            Producer::query()
                ->where(
                    'organization_id',
                    $actor->organization_id
                )
                ->where(
                    'producer_code',
                    DemoIdentifiers
                        ::NETWORK_SOURCE_PRODUCER_CODE
                )
                ->where(
                    'is_active',
                    true
                )
                ->first();

        if (! $producer) {
            $this->fail(
                'Producer demo source Mitra Lestari belum tersedia atau tidak aktif.'
            );
        }

        return $producer;
    }

    private function resolveSourceCommitment(
        DemandForecast $forecast,
        User $actor,
        Producer $producer
    ): ?SupplyCommitment {
        $commitments =
            SupplyCommitment::query()
                ->where(
                    'forecast_id',
                    $forecast->id
                )
                ->where(
                    'organization_id',
                    $actor->organization_id
                )
                ->where(
                    'producer_id',
                    $producer->id
                )
                ->orderBy('id')
                ->get();

        if ($commitments->count() > 1) {
            $this->fail(
                'Lebih dari satu source Commitment demo ditemukan untuk producer yang sama.'
            );
        }

        return $commitments->first();
    }

    private function resolveInitialVersion(
        SupplyCommitment $commitment
    ): CommitmentVersion {
        $version =
            CommitmentVersion::query()
                ->where(
                    'commitment_id',
                    $commitment->id
                )
                ->where(
                    'version_no',
                    1
                )
                ->first();

        if (! $version) {
            $this->fail(
                'Initial version source Commitment demo tidak ditemukan.'
            );
        }

        return $version;
    }

    private function ensureExpectedHarvest(
        User $actor,
        DemandForecast $forecast,
        Producer $producer
    ): ExpectedHarvest {
        $existing =
            ExpectedHarvest::query()
                ->where(
                    'organization_id',
                    $actor->organization_id
                )
                ->where(
                    'producer_id',
                    $producer->id
                )
                ->where(
                    'commodity_id',
                    $forecast->commodity_id
                )
                ->where(
                    'unit_id',
                    $forecast->unit_id
                )
                ->where(
                    'notes',
                    DemoIdentifiers
                        ::NETWORK_SOURCE_HARVEST_NOTE
                )
                ->first();

        if ($existing) {
            if (
                (string) $existing
                    ->expected_min_volume
                    !== DemoIdentifiers
                        ::NETWORK_SOURCE_VOLUME
                || (string) $existing
                    ->expected_max_volume
                    !== DemoIdentifiers
                        ::NETWORK_SOURCE_VOLUME
            ) {
                $this->fail(
                    'Expected Harvest source demo ditemukan tetapi volumenya tidak lagi 160 kg.'
                );
            }

            return $existing;
        }

        return $this->expectedHarvestService
            ->create(
                $actor,
                [
                    'producer_id' =>
                        $producer->id,

                    'commodity_id' =>
                        $forecast->commodity_id,

                    'unit_id' =>
                        $forecast->unit_id,

                    'expected_min_volume' =>
                        DemoIdentifiers
                            ::NETWORK_SOURCE_VOLUME,

                    'expected_max_volume' =>
                        DemoIdentifiers
                            ::NETWORK_SOURCE_VOLUME,

                    'harvest_start_at' =>
                        $forecast
                            ->required_start_at
                            ->toDateTimeString(),

                    'harvest_end_at' =>
                        $forecast
                            ->required_end_at
                            ->toDateTimeString(),

                    'notes' =>
                        DemoIdentifiers
                            ::NETWORK_SOURCE_HARVEST_NOTE,
                ]
            );
    }

    private function assertSourceIdentity(
        SupplyCommitment $commitment,
        CommitmentVersion $version,
        DemandForecast $forecast,
        User $actor,
        Producer $producer
    ): void {
        if (
            $commitment->forecast_id
                !== $forecast->id
            || $commitment->organization_id
                !== $actor->organization_id
            || $commitment->producer_id
                !== $producer->id
            || (string) $version->min_volume
                !== DemoIdentifiers
                    ::NETWORK_SOURCE_VOLUME
            || (string) $version->max_volume
                !== DemoIdentifiers
                    ::NETWORK_SOURCE_VOLUME
            || $version->unit_id
                !== $forecast->unit_id
            || $version->notes
                !== DemoIdentifiers
                    ::NETWORK_SOURCE_COMMITMENT_NOTE
        ) {
            $this->fail(
                'Source Commitment demo ditemukan tetapi payload-nya tidak sesuai locked scenario 160 kg.'
            );
        }
    }

    private function assertApprovedSource(
        SupplyCommitment $commitment,
        DemandForecast $forecast,
        User $actor
    ): void {
        $commitment->loadMissing(
            'activeVersion'
        );

        if (
            ! $commitment->isActive()
            || $commitment->current_confidence
                !== SupplyConfidence::GREEN
            || ! $commitment->activeVersion
            || ! $commitment
                ->activeVersion
                ->isApproved()
        ) {
            $this->fail(
                'Source Commitment demo belum menjadi ACTIVE + APPROVED + GREEN.'
            );
        }

        $availableCapacity =
            $this->fallbackCapacityService
                ->availableCapacity(
                    $commitment,
                    $forecast,
                    $actor->organization_id
                );

        if (
            $availableCapacity
            !== DemoIdentifiers
                ::NETWORK_SOURCE_VOLUME
        ) {
            $this->fail(
                'Source Commitment demo tidak menghasilkan eligible fallback capacity 160 kg.'
            );
        }
    }

    private function assertNetworkOperator(
        User $actor
    ): void {
        if (
            ! $actor->isKdkmpOperator()
            || ! $actor->hasValidIdentityContext()
            || $actor->email
                !== DemoIdentifiers
                    ::NETWORK_OPERATOR_EMAIL
            || $actor->organization?->code
                !== DemoIdentifiers
                    ::NETWORK_KDKMP_CODE
        ) {
            throw new AuthorizationException(
                'Source fallback demo hanya dapat disiapkan oleh Operator demo KDKMP Mitra Lestari.'
            );
        }
    }

    private function assertNetworkManager(
        User $actor
    ): void {
        if (
            ! $actor->isKdkmpManager()
            || ! $actor->hasValidIdentityContext()
            || $actor->email
                !== DemoIdentifiers
                    ::NETWORK_MANAGER_EMAIL
            || $actor->organization?->code
                !== DemoIdentifiers
                    ::NETWORK_KDKMP_CODE
        ) {
            throw new AuthorizationException(
                'Source fallback demo hanya dapat disetujui oleh Manager demo KDKMP Mitra Lestari.'
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