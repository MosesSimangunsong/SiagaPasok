<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'symbol',
    'decimal_precision',
    'is_active',
])]
class Unit extends Model
{
    protected function casts(): array
    {
        return [
            'decimal_precision' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function commodities(): HasMany
    {
        return $this->hasMany(
            Commodity::class,
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

    public function commitmentVersions(): HasMany
{
    return $this->hasMany(
        CommitmentVersion::class
    );
}
}