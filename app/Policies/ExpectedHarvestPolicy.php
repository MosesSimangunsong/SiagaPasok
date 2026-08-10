<?php

namespace App\Policies;

use App\Models\ExpectedHarvest;
use App\Models\User;

class ExpectedHarvestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->belongsToKdkmp()
            && $user->hasValidIdentityContext();
    }

    public function view(
        User $user,
        ExpectedHarvest $expectedHarvest
    ): bool {
        return $this->belongsToSameKdkmp(
            $user,
            $expectedHarvest
        );
    }

    public function create(User $user): bool
    {
        return $user->isKdkmpOperator()
            && $user->hasValidIdentityContext();
    }

    public function update(
        User $user,
        ExpectedHarvest $expectedHarvest
    ): bool {
        return $user->isKdkmpOperator()
            && $this->belongsToSameKdkmp(
                $user,
                $expectedHarvest
            );
    }

    private function belongsToSameKdkmp(
        User $user,
        ExpectedHarvest $expectedHarvest
    ): bool {
        return $user->belongsToKdkmp()
            && $user->hasValidIdentityContext()
            && $user->organization_id
                === $expectedHarvest->organization_id;
    }
}