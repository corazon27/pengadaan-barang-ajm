<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\LegalFunctionAssignment;
use App\Models\User;

class LegalFunctionAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function view(User $user, LegalFunctionAssignment $assignment): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function update(User $user, LegalFunctionAssignment $assignment): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function activate(User $user, LegalFunctionAssignment $assignment): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function suspend(User $user, LegalFunctionAssignment $assignment): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function terminate(User $user, LegalFunctionAssignment $assignment): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function openApplicabilityReview(User $user, LegalFunctionAssignment $assignment): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function applyApplicabilityReview(User $user, LegalFunctionAssignment $assignment): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }
}
