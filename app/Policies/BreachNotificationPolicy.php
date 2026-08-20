<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\BreachNotification;
use App\Models\User;

class BreachNotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function view(User $user, BreachNotification $notification): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function send(User $user, BreachNotification $notification): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function acknowledge(User $user, BreachNotification $notification): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function cancel(User $user, BreachNotification $notification): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }
}
