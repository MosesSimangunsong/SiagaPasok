<?php

namespace App\Policies;

use App\Models\ReadinessChecklist;
use App\Models\User;

class ReadinessChecklistPolicy
{
    public function viewAny(
        User $user
    ): bool {
        return $user->hasValidIdentityContext()
            && $user->belongsToKdkmp();
    }

    public function view(
        User $user,
        ReadinessChecklist $checklist
    ): bool {
        return $this->belongsToOwner(
            $user,
            $checklist
        );
    }

    public function create(
        User $user
    ): bool {
        return $user->hasValidIdentityContext()
            && $user->isKdkmpOperator();
    }

    public function updateItem(
        User $user,
        ReadinessChecklist $checklist
    ): bool {
        return $user->isKdkmpOperator()
            && $this->belongsToOwner(
                $user,
                $checklist
            )
            && $checklist->isCurrentVersion()
            && $checklist->isDraft();
    }

    public function submit(
        User $user,
        ReadinessChecklist $checklist
    ): bool {
        return $this->updateItem(
            $user,
            $checklist
        );
    }

    public function createRevision(
        User $user,
        ReadinessChecklist $checklist
    ): bool {
        return $user->isKdkmpOperator()
            && $this->belongsToOwner(
                $user,
                $checklist
            )
            && $checklist->isCurrentVersion()
            && ! $checklist
                ->isPendingApproval();
    }

    public function approve(
        User $user,
        ReadinessChecklist $checklist
    ): bool {
        return $user->isKdkmpManager()
            && $this->belongsToOwner(
                $user,
                $checklist
            )
            && $checklist->isCurrentVersion()
            && $checklist
                ->isPendingApproval();
    }

    public function reject(
        User $user,
        ReadinessChecklist $checklist
    ): bool {
        return $this->approve(
            $user,
            $checklist
        );
    }

    private function belongsToOwner(
        User $user,
        ReadinessChecklist $checklist
    ): bool {
        return $user->hasValidIdentityContext()
            && $user->belongsToKdkmp()
            && $checklist->organization_id
                === $user->organization_id;
    }
}