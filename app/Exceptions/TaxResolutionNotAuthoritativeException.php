<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\TaxResolutionState;
use Exception;

/**
 * Raised when an authoritative tax resolution is required but the resolver
 * could not produce an authoritative result (e.g. REVIEW_REQUIRED, RULE_CONFLICT).
 * Carries the resolution state and reason code so the caller can route the
 * transaction to human review instead of proceeding with an unverifiable rule.
 */
class TaxResolutionNotAuthoritativeException extends Exception
{
    public function __construct(
        public readonly TaxResolutionState $state,
        public readonly string $reasonCode,
        public readonly string $resolutionReason,
    ) {
        parent::__construct(
            "Tax resolution is not authoritative: {$state->value} ({$reasonCode}). {$resolutionReason}",
        );
    }
}
