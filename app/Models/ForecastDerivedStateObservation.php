<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'forecast_id',
    'forecast_version',
    'demand_target',
    'total_safe_supply',
    'shortfall',
    'ready_for_procurement',
    'contributor_organization_ids',
    'reason_codes',
    'evaluated_at',
    'created_at',
])]
class ForecastDerivedStateObservation extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'forecast_version' =>
                'integer',

            'demand_target' =>
                'decimal:6',

            'total_safe_supply' =>
                'decimal:6',

            'shortfall' =>
                'decimal:6',

            'ready_for_procurement' =>
                'boolean',

            'contributor_organization_ids' =>
                'array',

            'reason_codes' =>
                'array',

            'evaluated_at' =>
                'datetime',

            'created_at' =>
                'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(
            static function (): void {
                throw new LogicException(
                    'Derived state observations are append-only.'
                );
            }
        );

        static::deleting(
            static function (): void {
                throw new LogicException(
                    'Derived state observations are append-only.'
                );
            }
        );
    }

    public function forecast(): BelongsTo
    {
        return $this->belongsTo(
            DemandForecast::class,
            'forecast_id'
        );
    }
}