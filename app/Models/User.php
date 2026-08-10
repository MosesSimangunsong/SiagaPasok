<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'organization_id',
    'name',
    'email',
    'password',
    'role',
    'is_active',
    'last_login_at',
])]
#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(
            Organization::class
        );
    }

    public function createdForecasts(): HasMany
    {
        return $this->hasMany(
            DemandForecast::class,
            'created_by'
        );
    }

    public function updatedForecasts(): HasMany
    {
        return $this->hasMany(
            DemandForecast::class,
            'updated_by'
        );
    }

    public function createdProducers(): HasMany
    {
        return $this->hasMany(
            Producer::class,
            'created_by'
        );
    }

    public function updatedExpectedHarvests(): HasMany
    {
        return $this->hasMany(
            ExpectedHarvest::class,
            'last_updated_by'
        );
    }

    public function createdSupplyCommitments(): HasMany
{
    return $this->hasMany(
        SupplyCommitment::class,
        'created_by'
    );
}

public function createdCommitmentVersions(): HasMany
{
    return $this->hasMany(
        CommitmentVersion::class,
        'created_by'
    );
}

public function submittedCommitmentVersions(): HasMany
{
    return $this->hasMany(
        CommitmentVersion::class,
        'submitted_by'
    );
}

public function reviewedCommitmentVersions(): HasMany
{
    return $this->hasMany(
        CommitmentVersion::class,
        'reviewed_by'
    );
}

public function commitmentConfidenceEvents(): HasMany
{
    return $this->hasMany(
        CommitmentConfidenceEvent::class,
        'actor_user_id'
    );
}

public function requestedConfidenceRecoveries(): HasMany
{
    return $this->hasMany(
        ConfidenceRecoveryRequest::class,
        'requested_by'
    );
}

public function reviewedConfidenceRecoveries(): HasMany
{
    return $this->hasMany(
        ConfidenceRecoveryRequest::class,
        'reviewed_by'
    );
}

    public function auditLogs(): HasMany
    {
        return $this->hasMany(
            AuditLog::class,
            'actor_user_id'
        );
    }

    public function isSystemAdmin(): bool
    {
        return $this->role === UserRole::SYSTEM_ADMIN;
    }

    public function isSppgUser(): bool
    {
        return $this->role === UserRole::SPPG_USER;
    }

    public function isKdkmpOperator(): bool
    {
        return $this->role === UserRole::KDKMP_OPERATOR;
    }

    public function isKdkmpManager(): bool
    {
        return $this->role === UserRole::KDKMP_MANAGER;
    }

    public function belongsToKdkmp(): bool
    {
        return $this->role?->isKdkmpRole() ?? false;
    }

    public function hasValidIdentityContext(): bool
    {
        if (! $this->is_active || ! $this->role) {
            return false;
        }

        if ($this->role === UserRole::SYSTEM_ADMIN) {
            return $this->organization_id === null;
        }

        if (
            ! $this->organization_id
            || ! $this->organization
            || ! $this->organization->is_active
        ) {
            return false;
        }

        return match ($this->role) {
            UserRole::SPPG_USER =>
                $this->organization->isSppg(),

            UserRole::KDKMP_OPERATOR,
            UserRole::KDKMP_MANAGER =>
                $this->organization->isKdkmp(),

            default => false,
        };
    }
}