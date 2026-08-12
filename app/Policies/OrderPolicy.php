<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Determine whether the user can view any orders.
     */
    public function viewAny(User $user): bool
    {
        return true; // Controller scopes the query
    }

    /**
     * Determine whether the user can view the order.
     */
    public function view(User $user, Order $order): bool
    {
        return $user->is($order->user) || $user->role === UserRole::SUPERADMIN;
    }

    /**
     * Determine whether the user can create orders (convert an approved RFQ).
     */
    public function create(User $user): bool
    {
        return in_array($user->role, [
            UserRole::BUYER_B2B,
            UserRole::BUYER_B2G,
            UserRole::SUPERADMIN,
        ], true);
    }

    /**
     * Determine whether the user can update the order status.
     */
    public function updateStatus(User $user, Order $order): bool
    {
        if ($user->role === UserRole::SUPERADMIN) {
            return true;
        }

        // Owner can attempt transitions (controller validates the rules)
        return $user->is($order->user);
    }
}
