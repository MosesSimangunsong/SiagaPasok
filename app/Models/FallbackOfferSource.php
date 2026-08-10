<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'fallback_offer_id',
    'supply_commitment_id',
    'reserved_volume',
    'allocated_volume',
    'released_volume',
    'reserved_at',
    'allocated_at',
    'released_at',
])]
class FallbackOfferSource extends Model
{
    protected function casts(): array
    {
        return [
            'reserved_volume' =>
                'decimal:6',

            'allocated_volume' =>
                'decimal:6',

            'released_volume' =>
                'decimal:6',

            'reserved_at' =>
                'datetime',

            'allocated_at' =>
                'datetime',

            'released_at' =>
                'datetime',
        ];
    }

    public function fallbackOffer(): BelongsTo
    {
        return $this->belongsTo(
            FallbackOffer::class,
            'fallback_offer_id'
        );
    }

    public function supplyCommitment(): BelongsTo
    {
        return $this->belongsTo(
            SupplyCommitment::class,
            'supply_commitment_id'
        );
    }
}