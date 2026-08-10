<?php

namespace App\Models;

use App\Enums\ReadinessApprovalStatus;
use App\Enums\ReadinessType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'forecast_id',
    'organization_id',
    'readiness_type',
    'forecast_version',
    'version_no',
    'supersedes_checklist_id',
    'status',
    'is_current_version',
    'prepared_by',
    'submitted_by',
    'submitted_at',
    'reviewed_by',
    'reviewed_at',
    'review_reason',
    'approved_at',
])]
class ReadinessChecklist extends Model
{
    protected function casts(): array
    {
        return [
            'readiness_type' =>
                ReadinessType::class,

            'forecast_version' =>
                'integer',

            'version_no' =>
                'integer',

            'status' =>
                ReadinessApprovalStatus::class,

            'is_current_version' =>
                'boolean',

            'submitted_at' =>
                'datetime',

            'reviewed_at' =>
                'datetime',

            'approved_at' =>
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

    public function supersedesChecklist(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'supersedes_checklist_id'
        );
    }

    public function successorVersions(): HasMany
    {
        return $this->hasMany(
            self::class,
            'supersedes_checklist_id'
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            ReadinessItem::class,
            'readiness_checklist_id'
        );
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'prepared_by'
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

    public function isDraft(): bool
    {
        return $this->status
            === ReadinessApprovalStatus::DRAFT;
    }

    public function isPendingApproval(): bool
    {
        return $this->status
            === ReadinessApprovalStatus::PENDING_APPROVAL;
    }

    public function isApproved(): bool
    {
        return $this->status
            === ReadinessApprovalStatus::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status
            === ReadinessApprovalStatus::REJECTED;
    }

    public function isCurrentVersion(): bool
    {
        return $this->is_current_version;
    }
}