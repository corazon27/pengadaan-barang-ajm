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

    /**
     * Review the pending-application queue and inspect individual applications.
     */
    public function reviewApplications(User $actor): bool
    {
        return $this->isSuperadmin($actor);
    }

    public function approve(User $actor, User $target): bool
    {
        // A superadmin never approves their own account through this path.
        return $this->isSuperadmin($actor) && ! $actor->is($target);
    }

    public function reject(User $actor, User $target): bool
    {
        return $this->isSuperadmin($actor) && ! $actor->is($target);
    }

    public function suspend(User $actor, User $target): bool
    {
        // Self-suspension is never permitted; the last-active-superadmin
        // invariant is enforced transactionally in the lifecycle service.
        return $this->isSuperadmin($actor) && ! $actor->is($target);
    }

    public function reactivate(User $actor, User $target): bool
    {
        return $this->isSuperadmin($actor) && ! $actor->is($target);
    }

    public function reviewB2g(User $actor, User $target): bool
    {
        return $this->isSuperadmin($actor) && ! $actor->is($target);
    }

    private function isSuperadmin(User $actor): bool
    {
        return $actor->role === UserRole::SUPERADMIN;
    }

    private function isSelfOrSuperadmin(User $actor, User $target): bool
    {
        return $actor->is($target) || $this->isSuperadmin($actor);
    }
}
