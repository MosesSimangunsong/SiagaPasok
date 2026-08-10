<?php

namespace App\Policies;

use App\Models\Producer;
use App\Models\User;

class ProducerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->belongsToKdkmp()
            && $user->hasValidIdentityContext();
    }

    public function view(
        User $user,
        Producer $producer
    ): bool {
        return $this->belongsToSameKdkmp(
            $user,
            $producer
        );
    }

    public function create(User $user): bool
    {
        return $user->isKdkmpOperator()
            && $user->hasValidIdentityContext();
    }

    public function update(
        User $user,
        Producer $producer
    ): bool {
        return $user->isKdkmpOperator()
            && $producer->is_active
            && $this->belongsToSameKdkmp(
                $user,
                $producer
            );
    }

    public function setActiveState(
        User $user,
        Producer $producer
    ): bool {
        return $user->isKdkmpOperator()
            && $this->belongsToSameKdkmp(
                $user,
                $producer
            );
    }

    private function belongsToSameKdkmp(
        User $user,
        Producer $producer
    ): bool {
        return $user->belongsToKdkmp()
            && $user->hasValidIdentityContext()
            && $user->organization_id
                === $producer->organization_id;
    }
}