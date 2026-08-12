<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Rfq;
use App\Models\User;

class RfqPolicy
{
    /**
     * Determine whether the user can view any RFQs.
     */
    public function viewAny(User $user): bool
    {
        return true; // Controller scopes the query
    }

    /**
     * Determine whether the user can view the RFQ.
     */
    public function view(User $user, Rfq $rfq): bool
    {
        return $user->is($rfq->user) || $user->role === UserRole::SUPERADMIN;
    }

    /**
     * Determine whether the user can create RFQs.
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
     * Determine whether the user can respond to the RFQ (Superadmin only).
     */
    public function respond(User $user, Rfq $rfq): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    /**
     * Determine whether the user can update the RFQ status.
     */
    public function updateStatus(User $user, Rfq $rfq): bool
    {
        if ($user->role === UserRole::SUPERADMIN) {
            return true;
        }

        // Owner can attempt status updates (controller validates transitions)
        return $user->is($rfq->user);
    }
}
