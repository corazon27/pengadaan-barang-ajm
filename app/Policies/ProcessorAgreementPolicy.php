<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ProcessorAgreement;
use App\Models\User;

class ProcessorAgreementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function view(User $user, ProcessorAgreement $agreement): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function update(User $user, ProcessorAgreement $agreement): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function changeAgreementStatus(User $user, ProcessorAgreement $agreement): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function verifyEvidence(User $user, ProcessorAgreement $agreement): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function determineRole(User $user, ProcessorAgreement $agreement): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function openRoleReview(User $user, ProcessorAgreement $agreement): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }

    public function applyRoleReview(User $user, ProcessorAgreement $agreement): bool
    {
        return $user->role === UserRole::SUPERADMIN;
    }
}
