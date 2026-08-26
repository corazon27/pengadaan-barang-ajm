<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\HumanReviewCase;
use App\Models\User;

/**
 * Defense-in-depth policy for HumanReviewCase.
 *
 * No routes or controllers expose this model directly in Phase 3B.6.
 * This policy exists as a safety net — if future phases add routes,
 * authorization is already present.
 */
class HumanReviewCasePolicy
{
    /**
     * Determine whether the user can view any human review cases.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    /**
     * Determine whether the user can view a human review case.
     */
    public function view(User $user, HumanReviewCase $case): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    /**
     * Determine whether the user can create a human review case.
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    /**
     * Determine whether the user can update a human review case.
     */
    public function update(User $user, HumanReviewCase $case): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    /**
     * Determine whether the user can delete a human review case.
     */
    public function delete(User $user, HumanReviewCase $case): bool
    {
        return false;
    }
}
