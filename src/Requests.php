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

use PerlyPay\PrestaShop\Support\Money;
use PerlyPay\PrestaShop\Sync\EventProcessor;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * What this module asks Perly Pay's API to make, built from plain values
 * so the tests can hold each body to the API's own schemas
 * (test/api.drift.test.ts reads what tests/Unit/RequestsTest.php records).
 */
final class Requests
{
    /** GET /invoices for "Test connection": one is enough to know the key works. */
    public const CONNECTION_CHECK = ['limit' => 1];

    /** GET /webhook_endpoints, a page at a time, to find this shop's. */
    public const ENDPOINTS_PAGE = ['limit' => 100];

    /**
     * GET /payments of one invoice, for the paid message.
     *
     * @return array<string, mixed>
     */
    public static function paymentsOf(string $invoiceId): array
    {
        return ['invoice' => $invoiceId, 'limit' => 20];
    }

    /**
     * POST /invoices for a cart's total. The order is made only once money
     * is on its way (EventProcessor), so the invoice names the cart. The
     * cart's total is in minor units; its email is '' when the customer has
     * no valid one.
     *
     * @param array{id: int, total: int, currency: string, email: string, shop_name: string, shop_host: string} $cart
     * @param string $returnUrl where the buyer comes back to: the confirmation controller
     * @param string $locale "uk" or "en", the payment page's language
     * @param string $version this module's
     *
     * @return array<string, mixed>
     */
    public static function invoice(array $cart, string $returnUrl, string $locale, string $version, Words $words): array
    {
        $params = [
            'amount' => $cart['total'],
            'currency' => strtoupper($cart['currency']),
            'description' => mb_substr($words->shop('Your order at %shop%', ['%shop%' => $cart['shop_name']]), 0, 300),
            'return_url' => $returnUrl,
            'locale' => $locale === 'uk' ? 'uk' : 'en',
            'metadata' => [
                'cart_id' => (string) $cart['id'],
                'shop' => mb_substr($cart['shop_host'], 0, 200),
                'source' => EventProcessor::SOURCE,
                'module' => $version,
            ],
        ];
        if ($cart['email'] !== '') {
            $params['customer_email'] = $cart['email'];
        }

        return $params;
    }

    /**
     * The same cart, attempt and amount make the same invoice, however often the buyer presses the button.
     * The cart's creation time is in it too: a shop installed again, or a database restored, numbers carts
     * from where it was, and another cart with an old one's id must not meet that cart's key in Perly Pay
     * (which keeps keys for a day and refuses one sent again with another body).
     */
    public static function invoiceKey(string $shopUrl, int $cartId, string $cartCreated, int $attempt, int $total, string $currency): string
    {
        return 'ps_' . substr(hash('sha256', $shopUrl . '|' . $cartId . '|' . $cartCreated . '|' . $attempt . '|' . $total . strtoupper($currency)), 0, 40);
    }

    /**
     * POST /refunds for a credit slip: the whole invoice, or part of what
     * arrived, in the paid coin, by the share of the invoice the slip stands for.
     *
     * @param array<string, mixed> $invoice the invoice as the API returns it
     * @param int $part the credit slip, in minor units
     *
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException when the part comes to nothing in the coin
     */
    public static function refund(array $invoice, int $part, string $reason = ''): array
    {
        $params = ['invoice' => is_string($invoice['id'] ?? null) ? $invoice['id'] : ''];
        $total = is_int($invoice['total'] ?? null) ? $invoice['total'] : 0;
        if ($part < $total) {
            $units = Money::share(is_string($invoice['amount_received'] ?? null) ? $invoice['amount_received'] : '', $part, $total);
            if ($units === null || $units === '0') {
                throw new \InvalidArgumentException('too_small');
            }
            $params['amount'] = $units;
        }
        if (trim($reason) !== '') {
            $params['reason'] = mb_substr(trim($reason), 0, 500);
        }

        return $params;
    }

    /** One Perly Pay refund per credit slip (its creation time in it, as for invoiceKey). */
    public static function refundKey(string $shopUrl, int $orderId, int $slipId, string $slipCreated): string
    {
        return 'ps_refund_' . substr(hash('sha256', $shopUrl . '|' . $orderId . '|' . $slipId . '|' . $slipCreated), 0, 40);
    }

    /**
     * POST /webhook_endpoints for this shop.
     *
     * @return array<string, mixed>
     */
    public static function webhookEndpoint(string $url, string $shopName, Words $words): array
    {
        return [
            'url' => $url,
            'description' => mb_substr($words->admin('PrestaShop: %shop%', ['%shop%' => $shopName]), 0, 200),
            'enabled_events' => EventProcessor::EVENT_TYPES,
        ];
    }
}
