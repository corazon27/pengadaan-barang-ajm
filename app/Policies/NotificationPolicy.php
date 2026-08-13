<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Notification;
use App\Models\User;

class NotificationPolicy
{
    /**
     * A notification may be viewed only by its recipient or a Superadmin.
     */
    public function view(User $user, Notification $notification): bool
    {
        return $user->role === UserRole::SUPERADMIN
            || $notification->notifiable_id === $user->id;
    }

    /**
     * A notification may only be marked as read by its recipient.
     */
    public function update(User $user, Notification $notification): bool
    {
        return $notification->notifiable_id === $user->id;
    }
}
