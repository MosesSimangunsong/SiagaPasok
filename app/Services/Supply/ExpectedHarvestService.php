<?php

namespace App\Services\Supply;

use App\Enums\AuditSource;
use App\Models\Commodity;
use App\Models\ExpectedHarvest;
use App\Models\Producer;
use App\Models\Unit;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpectedHarvestService
{
    public function __construct(
        private readonly AuditService $auditService
    ) {
    }

    public function create(
        User $actor,
        array $data
    ): ExpectedHarvest {
        $this->assertOperator($actor);

        $this->validateReferences(
            $actor,
            $data
        );

        return DB::transaction(function () use (
            $actor,
            $data
        ): ExpectedHarvest {
            $expectedHarvest =
                ExpectedHarvest::query()->create([
                    'organization_id' =>
                        $actor->organization_id,

                    'producer_id' =>
                        $data['producer_id'],

                    'commodity_id' =>
                        $data['commodity_id'],

                    'unit_id' =>
                        $data['unit_id'],

                    'expected_min_volume' =>
                        $data['expected_min_volume'],

                    'expected_max_volume' =>
                        $data['expected_max_volume'],

                    'harvest_start_at' =>
                        $data['harvest_start_at'],

                    'harvest_end_at' =>
                        $data['harvest_end_at'],

                    'notes' =>
                        $data['notes'] ?? null,

                    'last_updated_by' =>
                        $actor->id,
                ]);

            $this->auditService->record(
                actor: $actor,
                source: AuditSource::USER,
                action: 'EXPECTED_HARVEST_CREATED',
                entity: $expectedHarvest,
                previousValue: null,
                newValue: $this->snapshot(
                    $expectedHarvest
                ),
            );

            return $expectedHarvest;
        });
    }

    public function update(
        User $actor,
        ExpectedHarvest $expectedHarvest,
        array $data
    ): ExpectedHarvest {
        $this->assertOwnedHarvest(
            $actor,
            $expectedHarvest
        );

        $this->validateReferences(
            $actor,
            $data
        );

        return DB::transaction(function () use (
            $actor,
            $expectedHarvest,
            $data
        ): ExpectedHarvest {
            $previous = $this->snapshot(
                $expectedHarvest
            );

            $expectedHarvest->fill([
                'producer_id' =>
                    $data['producer_id'],

                'commodity_id' =>
                    $data['commodity_id'],

                'unit_id' =>
                    $data['unit_id'],

                'expected_min_volume' =>
                    $data['expected_min_volume'],

                'expected_max_volume' =>
                    $data['expected_max_volume'],

                'harvest_start_at' =>
                    $data['harvest_start_at'],

                'harvest_end_at' =>
                    $data['harvest_end_at'],

                'notes' =>
                    $data['notes'] ?? null,

                'last_updated_by' =>
                    $actor->id,
            ]);

            if (! $expectedHarvest->isDirty()) {
                return $expectedHarvest;
            }

            $expectedHarvest->save();

            $this->auditService->record(
                actor: $actor,
                source: AuditSource::USER,
                action: 'EXPECTED_HARVEST_UPDATED',
                entity: $expectedHarvest,
                previousValue: $previous,
                newValue: $this->snapshot(
                    $expectedHarvest
                ),
            );

            return $expectedHarvest->refresh();
        });
    }

    private function validateReferences(
        User $actor,
        array $data
    ): void {
        $producerExists = Producer::query()
            ->whereKey($data['producer_id'])
            ->where(
                'organization_id',
                $actor->organization_id
            )
            ->where('is_active', true)
            ->exists();

        if (! $producerExists) {
            throw ValidationException::withMessages([
                'producer_id' => (
                    'Produsen tidak valid, tidak aktif, '
                    .'atau bukan milik organisasi Anda.'
                ),
            ]);
        }

        $commodityExists = Commodity::query()
            ->whereKey($data['commodity_id'])
            ->where('is_active', true)
            ->exists();

        if (! $commodityExists) {
            throw ValidationException::withMessages([
                'commodity_id' =>
                    'Komoditas aktif tidak ditemukan.',
            ]);
        }

        $unitExists = Unit::query()
            ->whereKey($data['unit_id'])
            ->where('is_active', true)
            ->exists();

        if (! $unitExists) {
            throw ValidationException::withMessages([
                'unit_id' =>
                    'Satuan aktif tidak ditemukan.',
            ]);
        }
    }

    private function assertOperator(
        User $actor
    ): void {
        if (
            ! $actor->isKdkmpOperator()
            || ! $actor->hasValidIdentityContext()
        ) {
            throw new AuthorizationException();
        }
    }

    private function assertOwnedHarvest(
        User $actor,
        ExpectedHarvest $expectedHarvest
    ): void {
        $this->assertOperator($actor);

        if (
            $expectedHarvest->organization_id
            !== $actor->organization_id
        ) {
            throw new AuthorizationException();
        }
    }

    private function snapshot(
        ExpectedHarvest $expectedHarvest
    ): array {
        return [
            'organization_id' =>
                $expectedHarvest->organization_id,

            'producer_id' =>
                $expectedHarvest->producer_id,

            'commodity_id' =>
                $expectedHarvest->commodity_id,

            'unit_id' =>
                $expectedHarvest->unit_id,

            'expected_min_volume' =>
                (string) $expectedHarvest
                    ->expected_min_volume,

            'expected_max_volume' =>
                (string) $expectedHarvest
                    ->expected_max_volume,

            'harvest_start_at' =>
                $expectedHarvest
                    ->harvest_start_at
                    ?->toIso8601String(),

            'harvest_end_at' =>
                $expectedHarvest
                    ->harvest_end_at
                    ?->toIso8601String(),

            'notes' =>
                $expectedHarvest->notes,

            'last_updated_by' =>
                $expectedHarvest->last_updated_by,
        ];
    }
}