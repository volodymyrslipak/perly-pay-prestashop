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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * One PrestaShop cart paid with Perly Pay, and the order made from it once
 * money is on its way, as the event processor sees them. Shop\CartPurchase
 * is the real one; the tests use a fake. Keeping the processor on this
 * small surface is what lets its rules be tested without PrestaShop.
 *
 * States, as this module names PrestaShop's:
 *   none      no order yet
 *   awaiting  "Awaiting crypto confirmation" (this module's): money seen, not yet confirmed and screened
 *   held      "Crypto payment held" (this module's): someone has to look
 *   paid      PrestaShop's "Payment accepted", or any later state of a paid order
 *   error     PrestaShop's "Payment error"
 *   refunded  PrestaShop's "Refunded"
 *   other     anything else (canceled, or a state another module set)
 */
interface Purchase
{
    public function cartId(): int;

    /** Every invoice made for this cart, oldest first. */
    public function invoiceIds(): array;

    /** The invoice the buyer was last sent to. */
    public function currentInvoiceId(): ?string;

    /** "test" or "live": the key's mode when this invoice was made. */
    public function modeOf(string $invoiceId): ?string;

    public function hasOrder(): bool;

    /** The cart's order was paid with another payment method. */
    public function orderIsOtherMethod(): bool;

    public function state(): string;

    /** Paid by PrestaShop's own reckoning (an order state marked paid, now or before). */
    public function isPaid(): bool;

    /** The invoice that paid the order (its payment's transaction id), null until one did. */
    public function paidBy(): ?string;

    /** The order's total, or the cart's while there is no order, in minor units (null when it cannot be read). */
    public function totalMinor(): ?int;

    /** The cart's currency, upper case. */
    public function currency(): string;

    public function hasEvent(string $eventId): bool;

    public function rememberEvent(string $eventId): void;

    /** Whether this refund was opened from a credit slip (that already left its message). */
    public function openedRefund(string $refundId): bool;

    /** Whether Perly Pay said this invoice expired before money arrived for it. */
    public function expired(string $invoiceId): bool;

    public function markExpired(string $invoiceId): void;

    /**
     * Makes the order from the cart (PaymentModule::validateOrder), for the
     * invoice's amount, in the state given (awaiting, held or paid; paid
     * records the invoice as its payment's transaction id), with a message.
     *
     * @param array<string, mixed> $invoice
     */
    public function placeOrder(string $state, array $invoice, string $message): void;

    /**
     * The invoice as the order's payment and its transaction id, then
     * "Payment accepted": the customer's email and the invoice go out.
     *
     * @param array<string, mixed> $invoice
     */
    public function markPaid(array $invoice, string $message): void;

    /** A new state (held, error, refunded) with a message saying why. */
    public function setState(string $state, string $message): void;

    /** A private message on the order, which only the shop's staff see. */
    public function addMessage(string $message): void;
}
