<?php

namespace App\Models;

use App\Enums\CommitmentLifecycleStatus;
use App\Enums\SupplyConfidence;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'forecast_id',
    'organization_id',
    'producer_id',
    'expected_harvest_id',
    'commodity_id',
    'active_version_id',
    'lifecycle_status',
    'current_confidence',
    'last_confidence_verified_at',
    'created_by',
    'cancelled_at',
    'cancellation_reason',
    'expired_at',
])]
class SupplyCommitment extends Model
{
    protected function casts(): array
    {
        return [
            'lifecycle_status' =>
                CommitmentLifecycleStatus::class,

            'current_confidence' =>
                SupplyConfidence::class,

            'last_confidence_verified_at' =>
                'datetime',

            'cancelled_at' =>
                'datetime',

            'expired_at' =>
                'datetime',
        ];
    }

    public function forecast(): BelongsTo
    {
        return $this->belongsTo(
            DemandForecast::class,
            'forecast_id'
        );
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

    public function expectedHarvest(): BelongsTo
    {
        return $this->belongsTo(
            ExpectedHarvest::class
        );
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(
            Commodity::class
        );
    }

    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(
            CommitmentVersion::class,
            'active_version_id'
        );
    }

    public function versions(): HasMany
    {
        return $this->hasMany(
            CommitmentVersion::class,
            'commitment_id'
        );
    }

    public function confidenceEvents(): HasMany
    {
        return $this->hasMany(
            CommitmentConfidenceEvent::class,
            'commitment_id'
        );
    }

    public function recoveryRequests(): HasMany
    {
        return $this->hasMany(
            ConfidenceRecoveryRequest::class,
            'commitment_id'
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function isActive(): bool
    {
        return $this->lifecycle_status
            === CommitmentLifecycleStatus::ACTIVE;
    }

    public function isCancelled(): bool
    {
        return $this->lifecycle_status
            === CommitmentLifecycleStatus::CANCELLED;
    }

    public function isExpired(): bool
    {
        return $this->lifecycle_status
            === CommitmentLifecycleStatus::EXPIRED;
    }
}