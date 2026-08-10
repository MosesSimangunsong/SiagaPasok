<?php

namespace App\Models;

use App\Enums\FallbackOfferStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'fallback_request_id',
    'supplier_organization_id',
    'offered_volume',
    'accepted_volume',
    'unit_id',
    'availability_note',
    'expires_at',
    'status',
    'created_by',
    'submitted_by',
    'submitted_at',
    'supplier_reviewed_by',
    'supplier_reviewed_at',
    'supplier_review_reason',
    'requester_decided_by',
    'requester_decided_at',
    'requester_decision_reason',
    'withdrawn_by',
    'withdrawn_at',
    'withdrawal_reason',
])]
class FallbackOffer extends Model
{
    protected function casts(): array
    {
        return [
            'offered_volume' =>
                'decimal:6',

            'accepted_volume' =>
                'decimal:6',

            'expires_at' =>
                'datetime',

            'status' =>
                FallbackOfferStatus::class,

            'submitted_at' =>
                'datetime',

            'supplier_reviewed_at' =>
                'datetime',

            'requester_decided_at' =>
                'datetime',

            'withdrawn_at' =>
                'datetime',
        ];
    }

    public function fallbackRequest(): BelongsTo
    {
        return $this->belongsTo(
            FallbackRequest::class,
            'fallback_request_id'
        );
    }

    public function supplierOrganization(): BelongsTo
    {
        return $this->belongsTo(
            Organization::class,
            'supplier_organization_id'
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

    public function supplierReviewedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'supplier_reviewed_by'
        );
    }

    public function requesterDecidedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'requester_decided_by'
        );
    }

    public function withdrawnBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'withdrawn_by'
        );
    }

    public function sources(): HasMany
    {
        return $this->hasMany(
            FallbackOfferSource::class,
            'fallback_offer_id'
        );
    }

    public function isDraft(): bool
    {
        return $this->status
            === FallbackOfferStatus::DRAFT;
    }

    public function isPendingApproval(): bool
    {
        return $this->status
            === FallbackOfferStatus
                ::PENDING_APPROVAL;
    }

    public function isAvailable(): bool
    {
        return $this->status
            === FallbackOfferStatus::AVAILABLE;
    }

    public function isAccepted(): bool
    {
        return $this->status
            === FallbackOfferStatus::ACCEPTED;
    }

    public function isRejected(): bool
    {
        return $this->status
            === FallbackOfferStatus::REJECTED;
    }

    public function isWithdrawn(): bool
    {
        return $this->status
            === FallbackOfferStatus::WITHDRAWN;
    }

    public function isExpired(): bool
    {
        return $this->status
            === FallbackOfferStatus::EXPIRED;
    }

    public function isTerminal(): bool
    {
        return $this->status
            ->isTerminal();
    }
}