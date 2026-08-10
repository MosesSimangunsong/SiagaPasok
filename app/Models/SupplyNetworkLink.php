<?php

namespace App\Models;

use App\Enums\NetworkRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sppg_organization_id',
    'kdkmp_organization_id',
    'network_role',
    'is_active',
    'configured_by',
])]
class SupplyNetworkLink extends Model
{
    protected function casts(): array
    {
        return [
            'network_role' => NetworkRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function sppgOrganization(): BelongsTo
    {
        return $this->belongsTo(
            Organization::class,
            'sppg_organization_id'
        );
    }

    public function kdkmpOrganization(): BelongsTo
    {
        return $this->belongsTo(
            Organization::class,
            'kdkmp_organization_id'
        );
    }

    public function configuredBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'configured_by'
        );
    }

    public function isPrimary(): bool
    {
        return $this->network_role === NetworkRole::PRIMARY;
    }

    public function isNetwork(): bool
    {
        return $this->network_role === NetworkRole::NETWORK;
    }
}