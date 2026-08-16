<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Verification state of a precondition, kept separate from the value it
 * qualifies (e.g. taxpayer_status = PKP vs verification_status = UNVERIFIED).
 */
enum VerificationStatus: string
{
    case VERIFIED = 'VERIFIED';
    case UNVERIFIED = 'UNVERIFIED';
    case NOT_APPLICABLE = 'NOT_APPLICABLE';
}
