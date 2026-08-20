<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Identity-confidence label (not a regulatory assertion).
 * Separated from VerificationStatus which captures the binary verified/unverified verdict.
 */
final class IdentityConfidence
{
    public const AUTHENTICATED_ONLY = 'AUTHENTICATED_ONLY';

    public const AUTHENTICATED_PLUS_EVIDENCE = 'AUTHENTICATED_PLUS_EVIDENCE';

    public const EVIDENCE_REQUIRED = 'EVIDENCE_REQUIRED';

    public const VERIFICATION_FAILED = 'VERIFICATION_FAILED';

    /**
     * All valid confidence labels.
     *
     * @var array<int, string>
     */
    public const ALL = [
        self::AUTHENTICATED_ONLY,
        self::AUTHENTICATED_PLUS_EVIDENCE,
        self::EVIDENCE_REQUIRED,
        self::VERIFICATION_FAILED,
    ];
}
