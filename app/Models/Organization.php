<?php

namespace App\Models;

use App\Enums\OrganizationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'organization_type',
    'is_active',
    'general_location',
])]
class Organization extends Model
{
    protected function casts(): array
    {
        return [
            'organization_type' => OrganizationType::class,
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function sppgNetworkLinks(): HasMany
    {
        return $this->hasMany(
            SupplyNetworkLink::class,
            'sppg_organization_id'
        );
    }

    public function kdkmpNetworkLinks(): HasMany
    {
        return $this->hasMany(
            SupplyNetworkLink::class,
            'kdkmp_organization_id'
        );
    }

    public function demandForecasts(): HasMany
    {
        return $this->hasMany(
            DemandForecast::class,
            'sppg_organization_id'
        );
    }

    public function producers(): HasMany
    {
        return $this->hasMany(
            Producer::class
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

public function readinessChecklists(): HasMany
{
    return $this->hasMany(
        ReadinessChecklist::class,
        'organization_id'
    );
}

public function documentRecords(): HasMany
{
    return $this->hasMany(
        DocumentRecord::class,
        'organization_id'
    );
}

    public function actorAuditLogs(): HasMany
    {
        return $this->hasMany(
            AuditLog::class,
            'actor_organization_id'
        );
    }

    public function isSppg(): bool
    {
        return $this->organization_type
            === OrganizationType::SPPG;
    }

    public function isKdkmp(): bool
    {
        return $this->organization_type
            === OrganizationType::KDKMP;
    }
}