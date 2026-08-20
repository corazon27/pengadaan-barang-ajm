<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\DataSubjectRequest;
use App\Models\User;

class DataSubjectRequestPolicy
{
    /**
     * Admin: SUPERADMIN can manage all DSRs.
     * Subject-self: user can view their own DSRs.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function view(User $user, DataSubjectRequest $dsr): bool
    {
        if ($user->role === UserRole::SUPERADMIN) {
            return true;
        }

        return $dsr->subject_user_id === $user->id;
    }

    public function create(User $user): bool
    {
        // Both SUPERADMIN and regular users can create DSRs.
        return true;
    }

    public function update(User $user, DataSubjectRequest $dsr): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function delete(User $user, DataSubjectRequest $dsr): bool
    {
        return false; // DSRs are never deleted.
    }
}
