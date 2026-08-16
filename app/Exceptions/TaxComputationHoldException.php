<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Signals that an invoice's tax computation must be placed on HOLD
 * (Phase 2E): at least one line item could not be resolved or calculated
 * authoritatively. Carries the machine-readable reason code for audit.
 */
final class TaxComputationHoldException extends RuntimeException
{
    public function __construct(
        public readonly string $reasonCode,
        string $message = '',
    ) {
        parent::__construct(
            $message !== '' ? $message : "Tax computation is on hold (reason: {$reasonCode})."
        );
    }
}
