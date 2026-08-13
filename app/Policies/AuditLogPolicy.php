<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    /**
     * Determine whether the user can view any audit logs.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    /**
     * Determine whether the user can view an audit log entry.
     */
    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }
}
