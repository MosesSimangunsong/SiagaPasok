<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'requirement_id',
    'document_name',
    'reference_number',
    'valid_from',
    'expires_at',
    'status',
    'notes',
    'created_by',
    'revision_no',
])]
class DocumentRecord extends Model
{
    protected function casts(): array
    {
        return [
            'valid_from' =>
                'datetime',

            'expires_at' =>
                'datetime',
            'revision_no' =>
    'integer',

            'status' =>
                DocumentStatus::class,
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(
            Organization::class
        );
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(
            ReadinessRequirement::class,
            'requirement_id'
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function readinessItems(): HasMany
    {
        return $this->hasMany(
            ReadinessItem::class,
            'document_record_id'
        );
    }

    public function isValid(): bool
    {
        return $this->status
            === DocumentStatus::VALID;
    }

    public function isRevoked(): bool
    {
        return $this->status
            === DocumentStatus::REVOKED;
    }

    public function isExpired(): bool
    {
        return $this->status
            === DocumentStatus::EXPIRED;
    }
}