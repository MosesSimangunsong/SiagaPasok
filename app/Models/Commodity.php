<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'default_unit_id',
    'harvest_behavior',
    'notes',
    'is_active',
])]
class Commodity extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function defaultUnit(): BelongsTo
    {
        return $this->belongsTo(
            Unit::class,
            'default_unit_id'
        );
    }

    public function demandForecasts(): HasMany
    {
        return $this->hasMany(
            DemandForecast::class
        );
    }

    public function expectedHarvests(): HasMany
    {
        return $this->hasMany(
            ExpectedHarvest::class
        );
    }

    public function supplyCommitments(): HasMany
{
    return $this->hasMany(
        SupplyCommitment::class
    );
}
}