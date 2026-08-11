<?php

namespace App\Models;

use App\Enums\AuditSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'actor_user_id',
    'actor_role_snapshot',
    'actor_organization_id',
    'source',
    'action',
    'entity_type',
    'entity_id',
    'previous_value_json',
    'new_value_json',
    'reason_note',
    'occurred_at',
])]
class AuditLog extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'source' =>
                AuditSource::class,

            'previous_value_json' =>
                'array',

            'new_value_json' =>
                'array',

            'occurred_at' =>
                'datetime',
        ];
    }

    protected static function booted(): void
    {
        /*
         * Audit trail adalah append-only.
         *
         * Setelah row dibuat, operational code
         * tidak boleh mengubah historical truth.
         */
        static::updating(
            static function (): void {
                throw new LogicException(
                    'Audit logs are append-only and cannot be updated.'
                );
            }
        );

        static::deleting(
            static function (): void {
                throw new LogicException(
                    'Audit logs are append-only and cannot be deleted.'
                );
            }
        );
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'actor_user_id'
        );
    }

    public function actorOrganization(): BelongsTo
    {
        return $this->belongsTo(
            Organization::class,
            'actor_organization_id'
        );
    }
}