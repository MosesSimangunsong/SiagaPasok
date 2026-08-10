<?php

namespace App\Models;

use App\Enums\CommitmentApprovalStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'commitment_id',
    'version_no',
    'min_volume',
    'max_volume',
    'unit_id',
'availability_start_at',
'availability_end_at',
'notes',
'approval_status',
    'change_reason',
    'operator_justification',
    'created_by',
    'submitted_by',
    'submitted_at',
    'reviewed_by',
    'reviewed_at',
    'review_reason',
    'approved_at',
    'created_at',
])]
class CommitmentVersion extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'version_no' =>
                'integer',

            'min_volume' =>
                'decimal:6',

            'max_volume' =>
                'decimal:6',

            'availability_start_at' =>
                'datetime',

            'availability_end_at' =>
                'datetime',

            'approval_status' =>
                CommitmentApprovalStatus::class,

            'submitted_at' =>
                'datetime',

            'reviewed_at' =>
                'datetime',

            'approved_at' =>
                'datetime',

            'created_at' =>
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

    public function unit(): BelongsTo
    {
        return $this->belongsTo(
            Unit::class
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

    public function recoveryRequests(): HasMany
    {
        return $this->hasMany(
            ConfidenceRecoveryRequest::class,
            'commitment_version_id'
        );
    }

    public function isDraft(): bool
    {
        return $this->approval_status
            === CommitmentApprovalStatus::DRAFT;
    }

    public function isPendingApproval(): bool
    {
        return $this->approval_status
            === CommitmentApprovalStatus::PENDING_APPROVAL;
    }

    public function isApproved(): bool
    {
        return $this->approval_status
            === CommitmentApprovalStatus::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->approval_status
            === CommitmentApprovalStatus::REJECTED;
    }
}