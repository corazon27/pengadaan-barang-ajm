<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Exact decimal arithmetic over decimal strings using BC Math (Phase 2D).
 *
 * Money must never touch IEEE-754 floats: every operation receives and returns
 * decimal strings and relies on BC Math with an explicit scale. BC Math itself
 * truncates, so roundHalfUp() implements deterministic away-from-zero-on-ties
 * rounding without any (float) cast.
 */
final class DecimalMath
{
    public static function add(string $a, string $b, int $scale): string
    {
        return bcadd($a, $b, $scale);
    }

    public static function sub(string $a, string $b, int $scale): string
    {
        return bcsub($a, $b, $scale);
    }

    public static function mul(string $a, string $b, int $scale): string
    {
        return bcmul($a, $b, $scale);
    }

    public static function div(string $dividend, string $divisor, int $scale): string
    {
        if (bccomp($divisor, '0', $scale) === 0) {
            throw new InvalidArgumentException('Division by zero is not allowed in DecimalMath.');
        }

        return bcdiv($dividend, $divisor, $scale);
    }

    /**
     * Round a decimal string half-up (away from zero on exact ties) to the
     * given scale. Implemented purely with BC Math and string handling.
     */
    public static function roundHalfUp(string $value, int $scale): string
    {
        if ($scale < 0) {
            throw new InvalidArgumentException('Rounding scale must be zero or greater.');
        }

        $normalized = self::normalize($value);
        $negative = $normalized[0] === '-';

        if ($negative) {
            $normalized = substr($normalized, 1);
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized), 2, '');

        $fraction = str_pad(substr($fraction, 0, $scale + 1), $scale + 1, '0');

        $kept = substr($fraction, 0, $scale);
        $firstDropped = $fraction[$scale] ?? '0';

        if ($firstDropped >= '5') {
            $rounded = self::incrementDecimalPart($whole, $kept, $scale);
        } elseif ($scale === 0) {
            $rounded = $whole;
        } else {
            $rounded = $whole.'.'.str_pad($kept, $scale, '0');
        }

        $sign = $negative ? '-' : '';

        return $sign.$rounded;
    }

    public static function isNegative(string $value): bool
    {
        return bccomp($value, '0', max(self::scaleOf($value), 0)) < 0;
    }

    /**
     * @return int -1, 0 or 1
     */
    public static function compare(string $a, string $b, int $scale): int
    {
        return bccomp($a, $b, $scale);
    }

    /**
     * Pad/trim a decimal string to exactly $scale fractional digits.
     */
    public static function normalize(string $value, ?int $scale = null): string
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException("'{$value}' is not a valid decimal string.");
        }

        $resolved = $scale ?? max(self::scaleOf($value), 0);

        return bcadd($value, '0', $resolved);
    }

    /**
     * @return array{string, string} [whole, fraction]
     */
    private static function split(string $value): array
    {
        return array_pad(explode('.', $value), 2, '');
    }

    private static function incrementDecimalPart(string $whole, string $kept, int $scale): string
    {
        $carry = 1;
        $digits = $kept !== '' ? $kept : '';

        for ($i = strlen($digits) - 1; $i >= 0 && $carry > 0; $i--) {
            $value = ((int) $digits[$i]) + $carry;
            $digits[$i] = (string) ($value % 10);
            $carry = intdiv($value, 10);
        }

        if ($carry > 0) {
            $whole = bcadd($whole === '' ? '0' : $whole, '1', 0);
        }

        $digits = str_pad($digits, $scale, '0');

        if ($scale === 0) {
            return $whole;
        }

        return $whole.'.'.$digits;
    }

    private static function scaleOf(string $value): int
    {
        if (! str_contains($value, '.')) {
            return 0;
        }

        return strlen(explode('.', $value)[1]);
    }
}
