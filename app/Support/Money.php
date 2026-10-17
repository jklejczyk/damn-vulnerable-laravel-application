<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Amounts are stored in the smallest currency unit (grosze) as integers, never as floats.
 */
final class Money
{
    public const PATTERN = '/^\d{1,9}([.,]\d{1,2})?$/';

    /**
     * "1234.5" or "1234,50" → 123450.
     */
    public static function toMinor(string $amount): int
    {
        $amount = trim($amount);

        if (preg_match(self::PATTERN, $amount) !== 1) {
            throw new InvalidArgumentException("Invalid amount: {$amount}");
        }

        [$units, $fraction] = array_pad(preg_split('/[.,]/', $amount), 2, '0');

        return (int) $units * 100 + (int) str_pad($fraction, 2, '0');
    }

    /**
     * 123450 → "1234.50", for form fields.
     */
    public static function toDecimal(int $minor): string
    {
        return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * 123450, "PLN" → "1 234,50 PLN", for display.
     */
    public static function format(int $minor, string $currency): string
    {
        return number_format(intdiv($minor, 100), 0, ',', ' ').','.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT).' '.$currency;
    }
}
