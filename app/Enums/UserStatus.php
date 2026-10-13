<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Account access lifecycle for self-registered and administrative users.
 *
 * Operational access is gated by status alone. Email verification is a
 * prerequisite for administrator approval, never an authorization gate, so
 * legacy trusted accounts may remain ACTIVE with a NULL email_verified_at.
 */
enum UserStatus: string
{
    case PENDING = 'PENDING';
    case ACTIVE = 'ACTIVE';
    case REJECTED = 'REJECTED';
    case SUSPENDED = 'SUSPENDED';

    public function isOperational(): bool
    {
        return $this === self::ACTIVE;
    }
}
