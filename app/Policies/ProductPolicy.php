<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class ProductPolicy
{
    /**
     * Determine whether the given product can be viewed by the given user.
     */
    public function view(User $user, $product): bool
    {
        return true; // Public read access
    }

    /**
     * Determine whether the given user can create products.
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    /**
     * Determine whether the given user can update the given product.
     */
    public function update(User $user, $product): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    /**
     * Determine whether the given user can delete the given product.
     */
    public function delete(User $user, $product): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }
}
