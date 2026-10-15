<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Console gate for the SUPERADMIN administrative surfaces (dashboard and
 * read-only operational queues). This policy has no backing model — it exists so
 * every admin route performs an explicit, auditable authorization check instead
 * of an implicit route-middleware shortcut or a blanket superadmin bypass.
 *
 * It does NOT replace record-level authorization: detail pages still re-authorize
 * the specific record through that record's own policy (OrderPolicy, RfqPolicy,
 * InvoicePolicy, PaymentPolicy, AuditLogPolicy).
 */
class AdminPolicy
{
    /**
     * Determine whether the user may access the admin console surfaces.
     */
    public function view(User $user): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }
}
