<?php

namespace App\Models;

use App\Enums\FallbackRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'forecast_id',
    'requester_organization_id',
    'requested_volume',
    'unit_id',
    'response_deadline_at',
    'status',
    'broadcast_note',
    'created_by',
    'submitted_by',
    'submitted_at',
    'reviewed_by',
    'reviewed_at',
    'review_reason',
    'opened_at',
    'fulfilled_at',
    'cancelled_at',
    'cancellation_reason',
    'expired_at',
])]
class FallbackRequest extends Model
{
    protected function casts(): array
    {
        return [
            'requested_volume' =>
                'decimal:6',

            'response_deadline_at' =>
                'datetime',

            'status' =>
                FallbackRequestStatus::class,

            'submitted_at' =>
                'datetime',

            'reviewed_at' =>
                'datetime',

            'opened_at' =>
                'datetime',

            'fulfilled_at' =>
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

    public function requesterOrganization(): BelongsTo
    {
        return $this->belongsTo(
            Organization::class,
            'requester_organization_id'
        );
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(
            Unit::class,
            'unit_id'
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'submitted_by'
        );
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by'
        );
    }

    public function offers(): HasMany
{
    return $this->hasMany(
        FallbackOffer::class,
        'fallback_request_id'
    );
}
    public function isDraft(): bool
    {
        return $this->status
            === FallbackRequestStatus::DRAFT;
    }

    public function isPendingApproval(): bool
    {
        return $this->status
            === FallbackRequestStatus
                ::PENDING_APPROVAL;
    }

    public function isOpen(): bool
    {
        return $this->status
            === FallbackRequestStatus::OPEN;
    }

    public function isRejected(): bool
    {
        return $this->status
            === FallbackRequestStatus::REJECTED;
    }

    public function isFulfilled(): bool
    {
        return $this->status
            === FallbackRequestStatus::FULFILLED;
    }

    public function isExpired(): bool
    {
        return $this->status
            === FallbackRequestStatus::EXPIRED;
    }

    public function isCancelled(): bool
    {
        return $this->status
            === FallbackRequestStatus::CANCELLED;
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }
}