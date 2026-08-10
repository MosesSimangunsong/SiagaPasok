<?php

namespace App\Services\Supply;

use App\Enums\AuditSource;
use App\Models\Producer;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ProducerService
{
    public function __construct(
        private readonly AuditService $auditService
    ) {
    }

    public function create(
        User $actor,
        array $data
    ): Producer {
        $this->assertOperator($actor);

        return DB::transaction(function () use (
            $actor,
            $data
        ): Producer {
            $producer = Producer::query()->create([
                'organization_id' =>
                    $actor->organization_id,

                'producer_code' =>
                    $data['producer_code'],

                'name' =>
                    $data['name'],

                'village' =>
                    $data['village'],

                'district' =>
                    $data['district'],

                'contact_phone' =>
                    $data['contact_phone'] ?? null,

                'notes' =>
                    $data['notes'] ?? null,

                'is_active' =>
                    true,

                'created_by' =>
                    $actor->id,
            ]);

            $this->auditService->record(
                actor: $actor,
                source: AuditSource::USER,
                action: 'PRODUCER_CREATED',
                entity: $producer,
                previousValue: null,
                newValue: $this->snapshot($producer),
            );

            return $producer;
        });
    }

    public function update(
        User $actor,
        Producer $producer,
        array $data
    ): Producer {
        $this->assertOwnedActiveProducer(
            $actor,
            $producer
        );

        return DB::transaction(function () use (
            $actor,
            $producer,
            $data
        ): Producer {
            $previous = $this->snapshot(
                $producer
            );

            $producer->fill([
                'producer_code' =>
                    $data['producer_code'],

                'name' =>
                    $data['name'],

                'village' =>
                    $data['village'],

                'district' =>
                    $data['district'],

                'contact_phone' =>
                    $data['contact_phone'] ?? null,

                'notes' =>
                    $data['notes'] ?? null,
            ]);

            if (! $producer->isDirty()) {
                return $producer;
            }

            $producer->save();

            $this->auditService->record(
                actor: $actor,
                source: AuditSource::USER,
                action: 'PRODUCER_UPDATED',
                entity: $producer,
                previousValue: $previous,
                newValue: $this->snapshot($producer),
            );

            return $producer->refresh();
        });
    }

    public function setActiveState(
        User $actor,
        Producer $producer,
        bool $isActive
    ): Producer {
        $this->assertOwnedProducer(
            $actor,
            $producer
        );

        if ($producer->is_active === $isActive) {
            return $producer;
        }

        return DB::transaction(function () use (
            $actor,
            $producer,
            $isActive
        ): Producer {
            $previous = $this->snapshot(
                $producer
            );

            $producer->is_active = $isActive;
            $producer->save();

            $this->auditService->record(
                actor: $actor,
                source: AuditSource::USER,
                action: $isActive
                    ? 'PRODUCER_ACTIVATED'
                    : 'PRODUCER_DEACTIVATED',
                entity: $producer,
                previousValue: $previous,
                newValue: $this->snapshot($producer),
            );

            return $producer->refresh();
        });
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

    private function assertOwnedProducer(
        User $actor,
        Producer $producer
    ): void {
        $this->assertOperator($actor);

        if (
            $producer->organization_id
            !== $actor->organization_id
        ) {
            throw new AuthorizationException();
        }
    }

    private function assertOwnedActiveProducer(
        User $actor,
        Producer $producer
    ): void {
        $this->assertOwnedProducer(
            $actor,
            $producer
        );

        if (! $producer->is_active) {
            throw new AuthorizationException(
                'Produsen nonaktif tidak dapat diubah.'
            );
        }
    }

    private function snapshot(
        Producer $producer
    ): array {
        return [
            'organization_id' =>
                $producer->organization_id,

            'producer_code' =>
                $producer->producer_code,

            'name' =>
                $producer->name,

            'village' =>
                $producer->village,

            'district' =>
                $producer->district,

            'contact_phone' =>
                $producer->contact_phone,

            'notes' =>
                $producer->notes,

            'is_active' =>
                (bool) $producer->is_active,

            'created_by' =>
                $producer->created_by,
        ];
    }
}