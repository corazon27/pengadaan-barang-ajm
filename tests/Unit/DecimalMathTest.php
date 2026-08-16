<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\DecimalMath;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DecimalMathTest extends TestCase
{
    public function test_add(): void
    {
        $this->assertSame('3.00', DecimalMath::add('1.00', '2.00', 2));
        $this->assertSame('3.000000', DecimalMath::add('1.50', '1.50', 6));
    }

    public function test_mul(): void
    {
        $this->assertSame('3000000.00', DecimalMath::mul('1000.00', '3000.00', 2));
        $this->assertSame('6.000000', DecimalMath::mul('2', '3', 6));
    }

    public function test_div(): void
    {
        $this->assertSame('0.916666', DecimalMath::div('11', '12', 6));
        $this->assertSame('2.00', DecimalMath::div('6', '3', 2));
    }

    public function test_div_by_zero_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DecimalMath::div('1', '0', 2);
    }

    public function test_round_half_up_ties_round_away_from_zero(): void
    {
        $this->assertSame('1.01', DecimalMath::roundHalfUp('1.005', 2));
        $this->assertSame('1.00', DecimalMath::roundHalfUp('1.004', 2));
        $this->assertSame('2.5', DecimalMath::roundHalfUp('2.5', 1));
        $this->assertSame('-1.01', DecimalMath::roundHalfUp('-1.005', 2));
        $this->assertSame('3', DecimalMath::roundHalfUp('2.5', 0));
        $this->assertSame('0.01', DecimalMath::roundHalfUp('0.005', 2));
        $this->assertSame('0.00', DecimalMath::roundHalfUp('0.004', 2));
    }

    public function test_round_half_up_large_magnitude_no_float_artifact(): void
    {
        $this->assertSame('1000000000000000.00', DecimalMath::roundHalfUp('999999999999999.995', 2));
        $this->assertSame('999999999999999.99', DecimalMath::roundHalfUp('999999999999999.99', 2));
    }

    public function test_round_half_up_carry_across_whole(): void
    {
        $this->assertSame('2.00', DecimalMath::roundHalfUp('1.999', 2));
        $this->assertSame('100.00', DecimalMath::roundHalfUp('99.995', 2));
    }

    public function test_round_rejects_negative_scale(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DecimalMath::roundHalfUp('1.5', -1);
    }

    public function test_is_negative(): void
    {
        $this->assertTrue(DecimalMath::isNegative('-0.01'));
        $this->assertFalse(DecimalMath::isNegative('0.01'));
        $this->assertFalse(DecimalMath::isNegative('0.00'));
    }

    public function test_compare(): void
    {
        $this->assertSame(-1, DecimalMath::compare('0.99', '1.00', 2));
        $this->assertSame(0, DecimalMath::compare('1.00', '1.00', 2));
        $this->assertSame(1, DecimalMath::compare('1.01', '1.00', 2));
    }

    public function test_normalize_pads_and_trims(): void
    {
        $this->assertSame('1.50', DecimalMath::normalize('1.5', 2));
        $this->assertSame('1.00', DecimalMath::normalize('1', 2));
        $this->assertSame('1.500', DecimalMath::normalize('1.5000', 3));
    }

    public function test_normalize_rejects_non_numeric(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DecimalMath::normalize('abc', 2);
    }
}
