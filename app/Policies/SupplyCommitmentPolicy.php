<?php

namespace App\Policies;

use App\Models\SupplyCommitment;
use App\Models\User;

class SupplyCommitmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->belongsToKdkmp()
            && $user->hasValidIdentityContext();
    }

    public function view(
        User $user,
        SupplyCommitment $commitment
    ): bool {
        return $this->belongsToOwner(
            $user,
            $commitment
        );
    }

    public function create(User $user): bool
    {
        return $user->isKdkmpOperator()
            && $user->hasValidIdentityContext();
    }

    public function updateDraft(
        User $user,
        SupplyCommitment $commitment
    ): bool {
        return $user->isKdkmpOperator()
            && $this->belongsToOwner(
                $user,
                $commitment
            )
            && $commitment->isActive();
    }

    public function createRevision(
        User $user,
        SupplyCommitment $commitment
    ): bool {
        return $user->isKdkmpOperator()
            && $this->belongsToOwner(
                $user,
                $commitment
            )
            && $commitment->isActive();
    }

    public function downgradeConfidence(
        User $user,
        SupplyCommitment $commitment
    ): bool {
        return $user->isKdkmpOperator()
            && $this->belongsToOwner(
                $user,
                $commitment
            )
            && $commitment->isActive();
    }

    public function requestRecovery(
        User $user,
        SupplyCommitment $commitment
    ): bool {
        return $user->isKdkmpOperator()
            && $this->belongsToOwner(
                $user,
                $commitment
            )
            && $commitment->isActive();
    }

    private function belongsToOwner(
        User $user,
        SupplyCommitment $commitment
    ): bool {
        return $user->hasValidIdentityContext()
            && $user->belongsToKdkmp()
            && $user->organization_id
                === $commitment->organization_id;
    }
}