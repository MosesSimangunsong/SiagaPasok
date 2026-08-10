<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'producer_code',
    'name',
    'village',
    'district',
    'contact_phone',
    'notes',
    'is_active',
    'created_by',
])]
class Producer extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(
            Organization::class
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function expectedHarvests(): HasMany
    {
        return $this->hasMany(
            ExpectedHarvest::class
        );
    }

    public function supplyCommitments(): HasMany
{
    return $this->hasMany(
        SupplyCommitment::class
    );
}
}