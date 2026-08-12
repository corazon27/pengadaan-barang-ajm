<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /**
     * Determine whether the user can view the invoice.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->is($invoice->order->user) || $user->role === UserRole::SUPERADMIN;
    }

    /**
     * Determine whether the user can update the invoice payment status.
     */
    public function updatePaymentStatus(User $user, Invoice $invoice): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }
}
