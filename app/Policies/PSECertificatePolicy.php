<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\PSECertificate;
use App\Models\User;

class PSECertificatePolicy
{
    /**
     * PSE governance registries are managed by SUPERADMIN only.
     * RBAC role gate — never inferred as a legal function.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function view(User $user, PSECertificate $certificate): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function update(User $user, PSECertificate $certificate): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }
}
