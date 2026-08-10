<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'producer_id',
    'commodity_id',
    'unit_id',
    'expected_min_volume',
    'expected_max_volume',
    'harvest_start_at',
    'harvest_end_at',
    'notes',
    'last_updated_by',
])]
class ExpectedHarvest extends Model
{
    protected function casts(): array
    {
        return [
            'expected_min_volume' => 'decimal:6',
            'expected_max_volume' => 'decimal:6',
            'harvest_start_at' => 'datetime',
            'harvest_end_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(
            Organization::class
        );
    }

    public function producer(): BelongsTo
    {
        return $this->belongsTo(
            Producer::class
        );
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(
            Commodity::class
        );
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(
            Unit::class
        );
    }

    public function lastUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'last_updated_by'
        );
    }

    public function supplyCommitments(): HasMany
{
    return $this->hasMany(
        SupplyCommitment::class,
        'expected_harvest_id'
    );
}
}