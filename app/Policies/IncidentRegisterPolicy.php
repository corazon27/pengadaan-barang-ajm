<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\IncidentRegister;
use App\Models\User;

class IncidentRegisterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function view(User $user, IncidentRegister $incident): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function update(User $user, IncidentRegister $incident): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function classify(User $user, IncidentRegister $incident): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function qualifyBreach(User $user, IncidentRegister $incident): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function resolve(User $user, IncidentRegister $incident): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function close(User $user, IncidentRegister $incident): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }
}
