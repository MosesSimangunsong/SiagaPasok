<?php

namespace App\Models;

use App\Enums\AuditSource;
use App\Enums\SupplyConfidence;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'commitment_id',
    'from_confidence',
    'to_confidence',
    'source',
    'reason_code',
    'reason_note',
    'actor_user_id',
    'occurred_at',
])]
class CommitmentConfidenceEvent extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'from_confidence' =>
                SupplyConfidence::class,

            'to_confidence' =>
                SupplyConfidence::class,

            'source' =>
                AuditSource::class,

            'occurred_at' =>
                'datetime',
        ];
    }

    public function commitment(): BelongsTo
    {
        return $this->belongsTo(
            SupplyCommitment::class,
            'commitment_id'
        );
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'actor_user_id'
        );
    }
}