<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class AnalyticsPolicy
{
    /**
     * Determine whether the user can view executive analytics.
     */
    public function view(User $user): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }
}
