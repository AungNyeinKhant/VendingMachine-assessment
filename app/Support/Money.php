<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Money arithmetic on decimal strings — never floats.
 *
 * Pepsi costs 6.885 USD; a float or 2-decimal column would silently turn it
 * into 6.89. All prices travel as strings ("6.885") from DB to API response.
 */
final class Money
{
    private const SCALE = 3;

    /** Multiply a unit price by a quantity. */
    public static function mul(string $amount, int $quantity): string
    {
        return bcmul($amount, (string) $quantity, self::SCALE);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub($a, $b, self::SCALE);
    }

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, self::SCALE);
    }

    /** True when $a < $b. */
    public static function lt(string $a, string $b): bool
    {
        return bccomp($a, $b, self::SCALE) < 0;
    }

    /** True when $a > $b. */
    public static function gt(string $a, string $b): bool
    {
        return bccomp($a, $b, self::SCALE) > 0;
    }

    /** Normalize any numeric input to scale 3: "5" -> "5.000". */
    public static function normalize(string $amount): string
    {
        return bcadd($amount, '0', self::SCALE);
    }
}
