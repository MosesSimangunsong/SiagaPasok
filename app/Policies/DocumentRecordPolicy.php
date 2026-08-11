<?php

namespace App\Policies;

use App\Models\DocumentRecord;
use App\Models\User;

class DocumentRecordPolicy
{
    public function viewAny(
        User $user
    ): bool {
        return $user->hasValidIdentityContext()
            && $user->belongsToKdkmp();
    }

    public function view(
        User $user,
        DocumentRecord $documentRecord
    ): bool {
        return $this->belongsToOwner(
            $user,
            $documentRecord
        );
    }

    public function create(
        User $user
    ): bool {
        return $user->hasValidIdentityContext()
            && $user->isKdkmpOperator();
    }

    public function update(
        User $user,
        DocumentRecord $documentRecord
    ): bool {
        return $user->isKdkmpOperator()
            && $this->belongsToOwner(
                $user,
                $documentRecord
            );
    }

    public function markValid(
        User $user,
        DocumentRecord $documentRecord
    ): bool {
        return $this->update(
            $user,
            $documentRecord
        );
    }

    public function revoke(
        User $user,
        DocumentRecord $documentRecord
    ): bool {
        return $this->update(
            $user,
            $documentRecord
        );
    }

    private function belongsToOwner(
        User $user,
        DocumentRecord $documentRecord
    ): bool {
        return $user->hasValidIdentityContext()
            && $user->belongsToKdkmp()
            && $documentRecord
                ->organization_id
                === $user->organization_id;
    }
}