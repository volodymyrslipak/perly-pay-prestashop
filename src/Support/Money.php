<?php

/**
 * Perly Pay for PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * that is bundled with this package in the file LICENSE.
 *
 * @author    Perly
 * @copyright 2026 Perly
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace PerlyPay\PrestaShop\Support;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Money without floats where it matters. PrestaShop keeps totals as
 * floats (24.5); Perly Pay takes fiat in minor units (2450) and gives
 * crypto as an integer string in the asset's smallest unit ("24500000"
 * with 6 decimals). A PrestaShop float is read once, through its
 * two-decimal text; everything after that is string or integer arithmetic.
 */
final class Money
{
    /** The currencies Perly Pay prices invoices in. */
    public const CURRENCIES = ['USD', 'EUR'];

    public static function supported(string $currency): bool
    {
        return in_array(strtoupper($currency), self::CURRENCIES, true);
    }

    /** A PrestaShop amount (24.5, a cart or credit slip total) in minor units: 2450. Null below zero. */
    public static function fromFloat(float $amount): ?int
    {
        return $amount < 0 ? null : self::toMinor(sprintf('%.2F', round($amount, 2)));
    }

    /**
     * "24.5" → 2450, "24.505" → 2451 (half up at the third decimal), "-1" → null.
     * Null for anything that is not a plain non-negative decimal.
     */
    public static function toMinor(string $amount): ?int
    {
        if (preg_match('/^(\d{1,13})(?:\.(\d*))?$/', trim($amount), $m) !== 1) {
            return null;
        }
        $fraction = str_pad($m[2] ?? '', 3, '0');
        $minor = (int) $m[1] * 100 + (int) substr($fraction, 0, 2);
        if ((int) $fraction[2] >= 5) {
            ++$minor;
        }

        return $minor;
    }

    /** 2450 → "24.50". */
    public static function fromMinor(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $minor = abs($minor);

        return $sign . intdiv($minor, 100) . '.' . str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    /** 2450, "usd" → "24.50 USD". */
    public static function fiat(int $minor, string $currency): string
    {
        return self::fromMinor($minor) . ' ' . strtoupper($currency);
    }

    /**
     * An on-chain amount for people: "24500000", 6 → "24.50"; "31250", 8 → "0.0003125".
     * Trailing zeros go, but two decimals stay.
     */
    public static function units(string $amount, int $decimals): string
    {
        if (preg_match('/^\d+$/', $amount) !== 1 || $decimals < 0) {
            return $amount;
        }
        $amount = ltrim($amount, '0');
        if ($decimals === 0) {
            return $amount === '' ? '0' : $amount;
        }
        $amount = str_pad($amount, $decimals + 1, '0', STR_PAD_LEFT);
        $whole = substr($amount, 0, -$decimals);
        $fraction = str_pad(rtrim(substr($amount, -$decimals), '0'), min(2, $decimals), '0');

        return $whole . '.' . $fraction;
    }

    /**
     * Part of an on-chain amount, by the share of the fiat total it stands for,
     * rounded down: share("24500000", 1225, 2450) → "12250000".
     * Null when the numbers are not integers or the share is not within 0…total.
     */
    public static function share(string $units, int $partMinor, int $totalMinor): ?string
    {
        if (preg_match('/^\d+$/', $units) !== 1 || $totalMinor <= 0 || $partMinor < 0 || $partMinor > $totalMinor) {
            return null;
        }

        return self::divide(self::multiply(ltrim($units, '0') ?: '0', (string) $partMinor), $totalMinor);
    }

    /** Decimal strings multiplied digit by digit: no overflow, no extension needed. */
    private static function multiply(string $a, string $b): string
    {
        $result = array_fill(0, strlen($a) + strlen($b), 0);
        for ($i = strlen($a) - 1; $i >= 0; --$i) {
            for ($j = strlen($b) - 1; $j >= 0; --$j) {
                $sum = (int) $a[$i] * (int) $b[$j] + $result[$i + $j + 1];
                $result[$i + $j + 1] = $sum % 10;
                $result[$i + $j] += intdiv($sum, 10);
            }
        }

        // Leading zeros go, but one digit stays: "000" → "0".
        return (string) preg_replace('/^0+(?=\d)/', '', implode('', $result));
    }

    /** A decimal string divided by a positive int, rounded down. */
    private static function divide(string $a, int $divisor): string
    {
        $quotient = '';
        $rest = 0;
        $length = strlen($a);
        for ($i = 0; $i < $length; ++$i) {
            $rest = $rest * 10 + (int) $a[$i];
            $quotient .= (string) intdiv($rest, $divisor);
            $rest %= $divisor;
        }
        $quotient = ltrim($quotient, '0');

        return $quotient === '' ? '0' : $quotient;
    }
}
