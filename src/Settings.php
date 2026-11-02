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

use PerlyPay\PrestaShop\Shop\Transport;
use PerlyPay\PrestaShop\Support\Keys;
use PerlyPay\PrestaShop\Vendor\Perly\Pay\PerlyPay;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * The module's saved settings (PrestaShop's Configuration, per shop, every
 * key prefixed PERLYPAY_) and the SDK client made from them.
 */
final class Settings
{
    public const API_KEY = 'PERLYPAY_API_KEY';
    public const SECRET = 'PERLYPAY_WEBHOOK_SECRET';
    public const MODE = 'PERLYPAY_CHECKOUT_MODE';
    public const REFUNDS = 'PERLYPAY_REFUNDS';
    public const DEBUG = 'PERLYPAY_DEBUG';
    public const LAST_WEBHOOK = 'PERLYPAY_LAST_WEBHOOK';
    public const OS_AWAITING = 'PERLYPAY_OS_AWAITING';
    public const OS_HELD = 'PERLYPAY_OS_HELD';
    public const DEFAULT_BASE = 'https://pay.perly.io';

    /** What uninstalling removes. The two order states' ids stay, so a reinstall reuses the states old orders are in. */
    public const REMOVED_ON_UNINSTALL = [self::API_KEY, self::SECRET, self::MODE, self::REFUNDS, self::DEBUG, self::LAST_WEBHOOK];

    public static function get(string $name, string $fallback = ''): string
    {
        if (!class_exists('Configuration')) {
            return $fallback;
        }
        $value = \Configuration::get($name);

        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    public static function apiKey(): string
    {
        return trim(self::get(self::API_KEY));
    }

    public static function webhookSecret(): string
    {
        return trim(self::get(self::SECRET));
    }

    /** "test", "live", or null with no valid key. */
    public static function mode(): ?string
    {
        return Keys::mode(self::apiKey());
    }

    /** "redirect" (the payment page on pay.perly.io, the default) or "overlay" (over the shop, pay.js). */
    public static function checkoutMode(): string
    {
        return self::get(self::MODE, 'redirect') === 'overlay' ? 'overlay' : 'redirect';
    }

    /** Whether a credit slip opens a refund in Perly Pay (on unless turned off). */
    public static function refunds(): bool
    {
        return self::get(self::REFUNDS, '1') === '1';
    }

    public static function debug(): bool
    {
        return self::get(self::DEBUG, '0') === '1';
    }

    /**
     * Perly Pay's address for API calls: pay.perly.io. A developer running
     * Perly Pay locally defines PERLYPAY_API_BASE in config/defines_custom.inc.php.
     */
    public static function apiBase(): string
    {
        $base = defined('PERLYPAY_API_BASE') && is_string(constant('PERLYPAY_API_BASE')) ? rtrim((string) constant('PERLYPAY_API_BASE'), '/') : self::DEFAULT_BASE;

        return preg_match('~^https?://[^/\s?#]+$~', $base) === 1 ? $base : self::DEFAULT_BASE;
    }

    /** A client for this key (the saved one by default), or null without a valid key. */
    public static function client(?string $key = null, int $maxRetries = 2): ?PerlyPay
    {
        $key = trim($key ?? self::apiKey());
        if (Keys::mode($key) === null) {
            return null;
        }

        return new PerlyPay($key, [
            'base_url' => self::apiBase(),
            'transport' => new Transport(),
            'max_retries' => $maxRetries,
            'timeout' => 20,
        ]);
    }
}
