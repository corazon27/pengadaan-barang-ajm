<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ConsentRecord;
use App\Models\User;

class ConsentRecordPolicy
{
    /**
     * Admin: SUPERADMIN can manage all consent records.
     * Subject-self: user can view their own consent records.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::SUPERADMIN;
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
