<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\TaxCalculationState;
use Exception;

/**
 * Raised when an authoritative tax amount is required but the calculation
 * could not produce one (e.g. REVIEW_REQUIRED due to incomplete rule data or
 * an unsupported formula). Carries the calculation state and reason code so
 * the caller can route the transaction to human review instead of proceeding
 * with an unverifiable amount.
 */
class TaxCalculationNotAuthoritativeException extends Exception
{
    public function __construct(
        public readonly TaxCalculationState $state,
        public readonly string $reasonCode,
        public readonly string $calculationReason,
    ) {
        parent::__construct(
            "Tax calculation is not authoritative: {$state->value} ({$reasonCode}). {$calculationReason}",
        );
    }
}
