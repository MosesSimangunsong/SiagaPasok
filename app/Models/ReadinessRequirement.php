<?php

namespace App\Models;

use App\Enums\OrganizationType;
use App\Enums\ReadinessType;
use App\Enums\RequirementScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'readiness_type',
    'requirement_code',
    'label',
    'requirement_scope',
    'applies_to_organization_type',
    'commodity_id',
    'is_required_default',
    'is_active',
    'sort_order',
    'config_json',
])]
class ReadinessRequirement extends Model
{
    protected function casts(): array
    {
        return [
            'readiness_type' =>
                ReadinessType::class,

            'requirement_scope' =>
                RequirementScope::class,

            'applies_to_organization_type' =>
                OrganizationType::class,

            'is_required_default' =>
                'boolean',

            'is_active' =>
                'boolean',

            'sort_order' =>
                'integer',

            'config_json' =>
                'array',
        ];
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(
            Commodity::class
        );
    }

    public function readinessItems(): HasMany
    {
        return $this->hasMany(
            ReadinessItem::class,
            'requirement_id'
        );
    }

    public function documentRecords(): HasMany
    {
        return $this->hasMany(
            DocumentRecord::class,
            'requirement_id'
        );
    }
}