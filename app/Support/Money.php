<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Money arithmetic on decimal strings — never floats.
 *
 * Pepsi costs 6.885 USD; a float or 2-decimal column would silently turn it
 * into 6.89. All prices travel as strings ("6.885") from DB to API response.
 */
final class Money
{
    private const SCALE = 3;

    /**
     * Multiply a unit price by a quantity.
     *
     * @return numeric-string
     */
    public static function mul(string $amount, int $quantity): string
    {
        return bcmul(self::numeric($amount), (string) $quantity, self::SCALE);
    }

    /** @return numeric-string */
    public static function sub(string $a, string $b): string
    {
        return bcsub(self::numeric($a), self::numeric($b), self::SCALE);
    }

    /** @return numeric-string */
    public static function add(string $a, string $b): string
    {
        return bcadd(self::numeric($a), self::numeric($b), self::SCALE);
    }

    /** True when $a < $b. */
    public static function lt(string $a, string $b): bool
    {
        return bccomp(self::numeric($a), self::numeric($b), self::SCALE) < 0;
    }

    /** True when $a > $b. */
    public static function gt(string $a, string $b): bool
    {
        return bccomp(self::numeric($a), self::numeric($b), self::SCALE) > 0;
    }

    /**
     * Normalize any numeric input to scale 3: "5" -> "5.000".
     *
     * @return numeric-string
     */
    public static function normalize(string $amount): string
    {
        return bcadd(self::numeric($amount), '0', self::SCALE);
    }

    /**
     * bcmath throws a ValueError on non-numeric input; fail with a clear message instead.
     *
     * @return numeric-string
     */
    private static function numeric(string $value): string
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Not a numeric money amount: [{$value}].");
        }

        return $value;
    }
}
