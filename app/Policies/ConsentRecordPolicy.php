<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ConsentRecord;
use App\Models\User;

class ConsentRecordPolicy
{
    /**
     * All authenticated users may access the consent collection endpoint.
     * Record-level visibility is enforced by controller query scoping.
     * SUPERADMIN sees all records; regular users see only subject_user_id = current user.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ConsentRecord $record): bool
    {
        if ($user->role === UserRole::SUPERADMIN) {
            return true;
        }

        return $record->subject_user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function update(User $user, ConsentRecord $record): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function withdraw(User $user, ConsentRecord $record): bool
    {
        if ($user->role === UserRole::SUPERADMIN) {
            return true;
        }

        return $record->subject_user_id === $user->id;
    }
}
