<?php

namespace App\Policies;

use App\Models\ConfidenceRecoveryRequest;
use App\Models\User;

class ConfidenceRecoveryRequestPolicy
{
    public function view(
        User $user,
        ConfidenceRecoveryRequest $request
    ): bool {
        return $this->belongsToOwner(
            $user,
            $request
        );
    }

    public function approve(
        User $user,
        ConfidenceRecoveryRequest $request
    ): bool {
        return $user->isKdkmpManager()
            && $this->belongsToOwner(
                $user,
                $request
            )
            && $request->isPendingApproval();
    }

    public function reject(
        User $user,
        ConfidenceRecoveryRequest $request
    ): bool {
        return $this->approve(
            $user,
            $request
        );
    }

    private function belongsToOwner(
        User $user,
        ConfidenceRecoveryRequest $request
    ): bool {
        return $user->hasValidIdentityContext()
            && $user->belongsToKdkmp()
            && $request->commitment
            && $request
                ->commitment
                ->organization_id
                === $user->organization_id;
    }
}