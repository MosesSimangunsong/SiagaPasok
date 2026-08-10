<?php

namespace App\Models;

use App\Enums\ForecastStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sppg_organization_id',
    'commodity_id',
    'unit_id',
    'forecast_code',
    'target_volume',
    'required_start_at',
    'required_end_at',
    'freshness_interval_hours',
    'status',
    'notes',
    'published_at',
    'closed_at',
    'cancelled_at',
    'cancellation_reason',
    'version',
    'created_by',
    'updated_by',
])]
class DemandForecast extends Model
{
    protected function casts(): array
    {
        return [
            'target_volume' => 'decimal:6',
            'required_start_at' => 'datetime',
            'required_end_at' => 'datetime',
            'freshness_interval_hours' => 'integer',
            'status' => ForecastStatus::class,
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function sppgOrganization(): BelongsTo
    {
        return $this->belongsTo(
            Organization::class,
            'sppg_organization_id'
        );
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function supplyCommitments(): HasMany
{
    return $this->hasMany(
        SupplyCommitment::class,
        'forecast_id'
    );
}

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }

    public function isDraft(): bool
    {
        return $this->status === ForecastStatus::DRAFT;
    }

    public function isPublished(): bool
    {
        return $this->status === ForecastStatus::PUBLISHED;
    }

    public function isClosed(): bool
    {
        return $this->status === ForecastStatus::CLOSED;
    }

    public function isCancelled(): bool
    {
        return $this->status === ForecastStatus::CANCELLED;
    }
}