<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\PSERegistration;
use App\Models\User;

class PSERegistrationPolicy
{
    /**
     * PSE governance registries are managed by SUPERADMIN only.
     * RBAC role gate — never inferred as a legal function.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function view(User $user, PSERegistration $registration): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function update(User $user, PSERegistration $registration): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }
}
