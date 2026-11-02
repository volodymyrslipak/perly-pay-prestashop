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

namespace PerlyPay\PrestaShop;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Advanced Parameters → Logs (PrestaShop's own log), lines starting
 * "Perly Pay:", when the setting "Debug log" is on; errors always.
 * Callers pass ids, types and outcomes only: never a key, a secret, a
 * signature, an address or a customer's email.
 */
final class Log
{
    /** @param array<string, scalar|null> $context */
    public static function info(string $message, array $context = []): void
    {
        if (Settings::debug()) {
            self::write(1, $message, $context);
        }
    }

    /** @param array<string, scalar|null> $context */
    public static function error(string $message, array $context = []): void
    {
        self::write(3, $message, $context);
    }

    /** @param array<string, scalar|null> $context */
    private static function write(int $severity, string $message, array $context): void
    {
        // Without PrestaShop (the unit tests) there is no log to write to.
        if (!class_exists('PrestaShopLogger')) {
            return;
        }
        $line = 'Perly Pay: ' . $message;
        foreach ($context as $key => $value) {
            $line .= ' ' . $key . '=' . (is_bool($value) ? ($value ? 'true' : 'false') : (string) $value);
        }
        $cart = isset($context['cart']) && is_numeric($context['cart']) ? (int) $context['cart'] : null;
        \PrestaShopLogger::addLog(mb_substr($line, 0, 1000), $severity, null, $cart === null ? null : 'Cart', $cart, true);
    }
}
