<?php

namespace App\Policies;

use App\Models\CommitmentVersion;
use App\Models\User;

class CommitmentVersionPolicy
{
    public function view(
        User $user,
        CommitmentVersion $version
    ): bool {
        return $this->belongsToOwner(
            $user,
            $version
        );
    }

    public function updateDraft(
        User $user,
        CommitmentVersion $version
    ): bool {
        return $user->isKdkmpOperator()
            && $this->belongsToOwner(
                $user,
                $version
            )
            && $version->isDraft()
            && $version
                ->commitment
                ->isActive();
    }

    public function submit(
        User $user,
        CommitmentVersion $version
    ): bool {
        return $user->isKdkmpOperator()
            && $this->belongsToOwner(
                $user,
                $version
            )
            && $version->isDraft()
            && $version
                ->commitment
                ->isActive();
    }

    public function approve(
        User $user,
        CommitmentVersion $version
    ): bool {
        return $user->isKdkmpManager()
            && $this->belongsToOwner(
                $user,
                $version
            )
            && $version->isPendingApproval()
            && $version
                ->commitment
                ->isActive();
    }

    public function reject(
        User $user,
        CommitmentVersion $version
    ): bool {
        return $this->approve(
            $user,
            $version
        );
    }

    private function belongsToOwner(
        User $user,
        CommitmentVersion $version
    ): bool {
        return $user->hasValidIdentityContext()
            && $user->belongsToKdkmp()
            && $version->commitment
            && $version
                ->commitment
                ->organization_id
                === $user->organization_id;
    }
}