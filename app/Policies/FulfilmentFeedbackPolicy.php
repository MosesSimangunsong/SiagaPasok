<?php

namespace App\Policies;

use App\Models\FulfilmentFeedback;
use App\Models\User;

class FulfilmentFeedbackPolicy
{
    public function viewAny(
        User $user,
    ): bool {
        if (
            ! $user
                ->hasValidIdentityContext()
        ) {
            return false;
        }

        return
            $user->isSppgUser()
            || $user->isKdkmpOperator()
            || $user->isKdkmpManager();
    }

    public function view(
        User $user,
        FulfilmentFeedback $feedback,
    ): bool {
        if (
            ! $user
                ->hasValidIdentityContext()
        ) {
            return false;
        }

        if ($user->isSppgUser()) {
            return
                $feedback
                    ->forecast
                    ->sppg_organization_id
                === $user->organization_id;
        }

        if (
            $user->isKdkmpOperator()
            || $user->isKdkmpManager()
        ) {
            return
                $feedback
                    ->contributor_organization_id
                === $user->organization_id;
        }

        return false;
    }

    public function create(
        User $user,
    ): bool {
        return
            $user
                ->hasValidIdentityContext()
            && $user->isSppgUser();
    }

    public function update(
        User $user,
        FulfilmentFeedback $feedback,
    ): bool {
        return false;
    }

    public function delete(
        User $user,
        FulfilmentFeedback $feedback,
    ): bool {
        return false;
    }
}