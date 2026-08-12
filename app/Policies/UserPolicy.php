<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function view(User $actor, User $target): bool
    {
        return $this->isSelfOrSuperadmin($actor, $target);
    }

    public function update(User $actor, User $target): bool
    {
        return $this->isSelfOrSuperadmin($actor, $target);
    }

    private function isSelfOrSuperadmin(User $actor, User $target): bool
    {
        return $actor->is($target) || $actor->role === UserRole::SUPERADMIN;
    }
}
