<?php

namespace App\Services\Audit;

use App\Enums\AuditSource;
use App\Models\AuditLog;
use App\Models\User;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    public function record(
        ?User $actor,
        AuditSource $source,
        string $action,
        Model $entity,
        ?array $previousValue = null,
        ?array $newValue = null,
        ?string $reasonNote = null,
    ): AuditLog {
        $actorRole = $actor?->role;

        $roleSnapshot = $actorRole instanceof BackedEnum
            ? $actorRole->value
            : $actorRole;

        return AuditLog::create([
            'actor_user_id' => $actor?->id,
            'actor_role_snapshot' => $roleSnapshot,
            'actor_organization_id' => $actor?->organization_id,
            'source' => $source,
            'action' => $action,
            'entity_type' => $entity->getMorphClass(),
            'entity_id' => $entity->getKey(),
            'previous_value_json' => $previousValue,
            'new_value_json' => $newValue,
            'reason_note' => $reasonNote,
            'occurred_at' => now(),
        ]);
    }
}