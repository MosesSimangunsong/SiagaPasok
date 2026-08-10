<?php

namespace App\Models;

use App\Enums\RecoveryRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'commitment_id',
    'commitment_version_id',
    'status',
    'recovery_reason',
    'requested_by',
    'requested_at',
    'reviewed_by',
    'reviewed_at',
    'review_reason',
])]
class ConfidenceRecoveryRequest extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'status' =>
                RecoveryRequestStatus::class,

            'requested_at' =>
                'datetime',

            'reviewed_at' =>
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

    public function commitmentVersion(): BelongsTo
    {
        return $this->belongsTo(
            CommitmentVersion::class,
            'commitment_version_id'
        );
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'requested_by'
        );
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by'
        );
    }

    public function isPendingApproval(): bool
    {
        return $this->status
            === RecoveryRequestStatus::PENDING_APPROVAL;
    }

    public function isApproved(): bool
    {
        return $this->status
            === RecoveryRequestStatus::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status
            === RecoveryRequestStatus::REJECTED;
    }
}