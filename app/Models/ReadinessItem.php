<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'readiness_checklist_id',
    'requirement_id',
    'is_required',
    'is_satisfied',
    'note',
    'document_record_id',
    'value_json',
    'updated_by',
    'document_record_revision_no',
])]
class ReadinessItem extends Model
{
    protected function casts(): array
    {
        return [
            'is_required' =>
                'boolean',

            'is_satisfied' =>
                'boolean',

            'value_json' =>
                'array',
                
            'document_record_revision_no' =>
    'integer',
        ];
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(
            ReadinessChecklist::class,
            'readiness_checklist_id'
        );
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(
            ReadinessRequirement::class,
            'requirement_id'
        );
    }

    public function documentRecord(): BelongsTo
    {
        return $this->belongsTo(
            DocumentRecord::class,
            'document_record_id'
        );
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }
}