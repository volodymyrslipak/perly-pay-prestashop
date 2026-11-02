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
 * What a pasted key or secret is, by its shape alone: Perly Pay's secret
 * keys are sk_test_… or sk_live_… and 32 letters or digits; a webhook
 * signing secret is whsec_… and 20 to 80.
 */
final class Keys
{
    /** "test", "live", or null when it is not a secret key. */
    public static function mode(string $key): ?string
    {
        return preg_match('/^sk_(test|live)_[0-9A-Za-z]{32}$/', trim($key), $m) === 1 ? $m[1] : null;
    }

    public static function isWebhookSecret(string $secret): bool
    {
        return preg_match('/^whsec_[0-9A-Za-z]{20,80}$/', trim($secret)) === 1;
    }

    /** "sk_test_…4f2a": enough to tell keys apart, never enough to use one. */
    public static function hint(string $key): string
    {
        $key = trim($key);
        if (strlen($key) < 12) {
            return '';
        }

        return (str_starts_with($key, 'whsec_') ? 'whsec_' : substr($key, 0, 8)) . '…' . substr($key, -4);
    }
}
