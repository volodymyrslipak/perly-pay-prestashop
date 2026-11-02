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

namespace PerlyPay\PrestaShop\Shop;

use PerlyPay\PrestaShop\Log;
use PerlyPay\PrestaShop\Requests;
use PerlyPay\PrestaShop\Support\Money;
use PerlyPay\PrestaShop\Sync\CurrentInvoice;
use PerlyPay\PrestaShop\Vendor\Perly\Pay\PerlyPay;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * The invoice a cart is paid with (one per cart, reused while it can be
 * paid for the cart's total), and the link the buyer comes back on.
 */
final class Checkout
{
    private readonly Tables $tables;

    public function __construct(private readonly \Perlypay $module, ?Tables $tables = null)
    {
        $this->tables = $tables ?? new Tables();
    }

    /**
     * @return array<string, mixed> the invoice, with its id and url
     *
     * @throws \InvalidArgumentException with a sentence for the buyer when the cart cannot be paid this way
     */
    public function invoiceFor(\Cart $cart, PerlyPay $client): array
    {
        $currency = strtoupper((string) (new \Currency((int) $cart->id_currency))->iso_code);
        $words = Translator::forLanguage((int) $cart->id_lang);
        if (!Money::supported($currency)) {
            throw new \InvalidArgumentException($words->shop('Crypto payment takes orders in USD or EUR only.'));
        }
        $total = Money::fromFloat((float) $cart->getOrderTotal(true, \Cart::BOTH));
        if ($total === null || $total <= 0) {
            throw new \InvalidArgumentException($words->shop('This order has nothing to pay.'));
        }
        $rows = $this->tables->invoicesOf((int) $cart->id);
        $last = $rows === [] ? null : $rows[count($rows) - 1];

        return (new CurrentInvoice($client, $last === null ? null : $last['invoice_id'], (int) $cart->id))->get(
            $total,
            $currency,
            function () use ($cart, $client, $total, $currency, $last, $words): array {
                return $this->newInvoice($cart, $client, $total, $currency, ($last === null ? 0 : $last['attempt']) + 1, $words);
            }
        );
    }

    /** Where the buyer comes back to from the payment page: the confirmation controller, for this cart only. */
    public function returnUrl(\Cart $cart): string
    {
        return \Context::getContext()->link->getModuleLink($this->module->name, 'confirmation', ['id_cart' => (int) $cart->id, 'token' => self::token($cart)], true);
    }

    /** Proves a return link was made for this cart: an HMAC of its id and secure key under the shop's own secret. */
    public static function token(\Cart $cart): string
    {
        return substr(hash_hmac('sha256', 'perlypay|return|' . (int) $cart->id . '|' . (string) $cart->secure_key, _COOKIE_KEY_), 0, 32);
    }

    /** @return array<string, mixed> */
    private function newInvoice(\Cart $cart, PerlyPay $client, int $total, string $currency, int $attempt, \PerlyPay\PrestaShop\Words $words): array
    {
        $customer = new \Customer((int) $cart->id_customer);
        $shop = new \Shop((int) $cart->id_shop);
        $language = new \Language((int) $cart->id_lang);
        $shopUrl = (string) $shop->getBaseURL(true);
        $params = Requests::invoice(
            [
                'id' => (int) $cart->id,
                'total' => $total,
                'currency' => $currency,
                'email' => \Validate::isEmail($customer->email) ? (string) $customer->email : '',
                'shop_name' => (string) \Configuration::get('PS_SHOP_NAME', null, null, (int) $cart->id_shop),
                'shop_host' => (string) parse_url($shopUrl, PHP_URL_HOST),
            ],
            $this->returnUrl($cart),
            str_starts_with((string) $language->iso_code, 'uk') ? 'uk' : 'en',
            \Perlypay::VERSION,
            $words
        );
        $invoice = $client->invoices->create($params, ['idempotency_key' => Requests::invoiceKey($shopUrl, (int) $cart->id, (string) $cart->date_add, $attempt, $total, $currency)]);
        if (!is_string($invoice['id'] ?? null) || !is_string($invoice['url'] ?? null)) {
            throw new \InvalidArgumentException($words->shop('Crypto payment is not available right now. Please try again, or choose another way to pay.'));
        }
        $this->tables->addInvoice([
            'invoice_id' => $invoice['id'],
            'id_cart' => (int) $cart->id,
            'id_shop' => (int) $cart->id_shop,
            'mode' => $client->mode,
            'total_minor' => $total,
            'currency' => $currency,
            'url' => $invoice['url'],
            'attempt' => $attempt,
        ]);
        Log::info('invoice made', ['cart' => (int) $cart->id, 'invoice' => $invoice['id'], 'mode' => $client->mode]);

        return $invoice;
    }
}
