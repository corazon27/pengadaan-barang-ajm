<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Centralized, inspectable monetary calculation policy (Phase 2D).
 *
 * Single source of truth for rounding mode, scales, tolerance and the
 * calculation algorithm version. TaxCalculationService reads these values
 * exclusively — literal "2"/"6" never appear in service logic.
 */
final readonly class CalculationPolicy
{
    public function roundingMode(): string
    {
        return 'ROUND_HALF_UP';
    }

    public function moneyScale(): int
    {
        return 2;
    }

    public function rateScale(): int
    {
        return 4;
    }

    public function intermediateScale(): int
    {
        return 6;
    }

    public function burdenTolerance(): string
    {
        return '0.01';
    }

    public function calculationVersion(): string
    {
        return '1.0';
    }
}
