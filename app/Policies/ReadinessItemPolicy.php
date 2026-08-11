<?php

namespace App\Policies;

use App\Models\ReadinessItem;
use App\Models\User;

class ReadinessItemPolicy
{
    public function view(
        User $user,
        ReadinessItem $item
    ): bool {
        return $this->belongsToOwner(
            $user,
            $item
        );
    }

    public function update(
        User $user,
        ReadinessItem $item
    ): bool {
        $checklist =
            $item->checklist;

        return $user->isKdkmpOperator()
            && $this->belongsToOwner(
                $user,
                $item
            )
            && $checklist
            && $checklist
                ->isCurrentVersion()
            && $checklist
                ->isDraft();
    }

    private function belongsToOwner(
        User $user,
        ReadinessItem $item
    ): bool {
        $checklist =
            $item->checklist;

        return $user->hasValidIdentityContext()
            && $user->belongsToKdkmp()
            && $checklist
            && $checklist
                ->organization_id
                === $user->organization_id;
    }
} 