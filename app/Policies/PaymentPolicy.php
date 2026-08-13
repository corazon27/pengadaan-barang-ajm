<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /**
     * Determine whether the user can view the payment.
     */
    public function view(User $user, Payment $payment): bool
    {
        return $user->role === UserRole::SUPERADMIN
            || $user->is($payment->invoice->order->user);
    }

    /**
     * Determine whether the user can submit a payment for the invoice.
     */
    public function create(User $user, Invoice $invoice): bool
    {
        return $user->role === UserRole::SUPERADMIN
            || $user->is($invoice->order->user);
    }

    /**
     * Determine whether the user can verify or reject the payment.
     */
    public function verify(User $user, Payment $payment): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }
}
