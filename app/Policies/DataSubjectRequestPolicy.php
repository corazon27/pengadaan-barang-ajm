<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\DataSubjectRequest;
use App\Models\User;

class DataSubjectRequestPolicy
{
    /**
     * All authenticated users may access the DSR collection endpoint.
     * Record-level visibility is enforced by controller query scoping.
     * SUPERADMIN sees all records; regular users see only subject_user_id = current user.
     */
    public function viewAny(User $user): bool
    {
        return true;
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
