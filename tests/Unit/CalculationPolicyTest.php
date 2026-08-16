<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\CalculationPolicy;
use PHPUnit\Framework\TestCase;

class CalculationPolicyTest extends TestCase
{
    public function test_policy_is_inspectable(): void
    {
        $policy = new CalculationPolicy;

        $this->assertSame('ROUND_HALF_UP', $policy->roundingMode());
        $this->assertSame(2, $policy->moneyScale());
        $this->assertSame(4, $policy->rateScale());
        $this->assertSame(6, $policy->intermediateScale());
        $this->assertSame('0.01', $policy->burdenTolerance());
        $this->assertSame('1.0', $policy->calculationVersion());
    }
}
