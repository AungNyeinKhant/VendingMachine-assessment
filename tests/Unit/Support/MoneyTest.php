<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Money;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_mul_preserves_decimal_precision(): void
    {
        $this->assertSame('20.655', Money::mul('6.885', 3));
        $this->assertSame('3.990', Money::mul('3.990', 1));
        $this->assertSame('0.500', Money::mul('0.500', 1));
    }

    public function test_sub_preserves_decimal_precision(): void
    {
        $this->assertSame('4.345', Money::sub('25.000', '20.655'));
        $this->assertSame('0.000', Money::sub('6.885', '6.885'));
        $this->assertSame('10.000', Money::sub('20.000', '10.000'));
    }

    public function test_lt_compares_correctly(): void
    {
        $this->assertTrue(Money::lt('1.000', '2.000'));
        $this->assertFalse(Money::lt('2.000', '1.000'));
        $this->assertFalse(Money::lt('2.000', '2.000'));
    }

    public function test_normalize_ensures_three_decimals(): void
    {
        $this->assertSame('5.000', Money::normalize('5'));
        $this->assertSame('5.100', Money::normalize('5.1'));
        $this->assertSame('5.120', Money::normalize('5.12'));
        $this->assertSame('5.123', Money::normalize('5.123'));
        $this->assertSame('5.123', Money::normalize('5.1234')); // truncates to 3
    }

    public function test_no_float_drift(): void
    {
        // 0.1 + 0.2 = 0.3 in decimal, but 0.30000000000000004 in float
        $this->assertSame('0.300', Money::sub('0.300', '0.000'));
        $this->assertSame('0.600', Money::mul('0.200', 3));
    }
}
