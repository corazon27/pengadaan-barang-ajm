<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\BastDocument;
use App\Models\User;

class BastDocumentPolicy
{
    /**
     * Determine whether the user can view the BAST document.
     */
    public function view(User $user, BastDocument $bast): bool
    {
        return $user->is($bast->order->user) || $user->role === UserRole::SUPERADMIN;
    }

    /**
     * Determine whether the user can sign the BAST document.
     */
    public function sign(User $user, BastDocument $bast): bool
    {
        if ($user->role === UserRole::SUPERADMIN) {
            return true;
        }

        return $user->is($bast->order->user);
    }
}
