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

namespace PerlyPay\PrestaShop\Sync;

use PerlyPay\PrestaShop\Log;
use PerlyPay\PrestaShop\Vendor\Perly\Pay\Exception\PerlyPayException;
use PerlyPay\PrestaShop\Vendor\Perly\Pay\PerlyPay;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * The invoice a cart is paid with, each time the buyer presses Pay or
 * comes back to pay: the last one while it can still be paid for the
 * cart's total, else a new one.
 *
 * The last one can still be payable for another total: the buyer went
 * back and changed the cart. It is canceled in Perly Pay once the new one
 * is made, so the old amount cannot be paid. A cancel that fails (money
 * already seen, Perly Pay out of reach) does not stop the new invoice: it
 * is logged, and if the old invoice is paid after all the event processor
 * puts the order made from it on hold.
 *
 * The same rules as the WooCommerce plugin's CurrentInvoice, apart from
 * PrestaShop so the tests run it without a shop.
 */
final class CurrentInvoice
{
    /** Invoices the buyer can still pay: reused when they come back to pay the same cart. */
    public const PAYABLE = ['open', 'seen', 'underpaid'];

    public function __construct(private readonly PerlyPay $client, private readonly ?string $current, private readonly int $cartId)
    {
    }

    /**
     * @param int $total the cart's total now, in minor units
     * @param string $currency upper case
     * @param callable(): array<string, mixed> $create makes a new invoice and records it as the cart's current one
     *
     * @return array<string, mixed>
     */
    public function get(int $total, string $currency, callable $create): array
    {
        $replaced = null;
        if ($this->current !== null) {
            try {
                $last = $this->client->invoices->retrieve($this->current, ['max_retries' => 1]);
                if (in_array($last['status'] ?? '', self::PAYABLE, true)) {
                    if (($last['total'] ?? null) === $total && ($last['currency'] ?? '') === $currency) {
                        return $last;
                    }
                    $replaced = $this->current;
                }
            } catch (PerlyPayException $error) {
                Log::info('last invoice not reusable', ['cart' => $this->cartId, 'code' => $error->getErrorCode()]);
            }
        }

        $invoice = $create();
        if ($replaced !== null && ($invoice['id'] ?? null) !== $replaced) {
            try {
                $this->client->invoices->cancel($replaced, ['max_retries' => 1]);
                Log::info('replaced invoice canceled', ['cart' => $this->cartId, 'invoice' => $replaced]);
            } catch (PerlyPayException $error) {
                Log::error('replaced invoice not canceled', ['cart' => $this->cartId, 'invoice' => $replaced, 'code' => $error->getErrorCode()]);
            }
        }

        return $invoice;
    }
}
