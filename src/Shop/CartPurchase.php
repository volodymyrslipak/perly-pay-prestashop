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

use PerlyPay\PrestaShop\Support\Money;
use PerlyPay\PrestaShop\Sync\Purchase;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * A PrestaShop cart and its order behind the Purchase port. Orders are
 * made with PaymentModule::validateOrder, states moved with OrderHistory,
 * payments added with Order::addOrderPayment, messages through Messages:
 * PrestaShop's own classes, never its tables directly.
 */
final class CartPurchase implements Purchase
{
    /** @var list<array{invoice_id: string, mode: string, total_minor: int, currency: string, url: string, attempt: int, expired: bool}> */
    private array $invoices;

    private ?\Order $order = null;

    public function __construct(private readonly \Cart $cart, private readonly Tables $tables, private readonly \Perlypay $module)
    {
        $this->invoices = $tables->invoicesOf((int) $cart->id);
        $this->reload();
    }

    public function cartId(): int
    {
        return (int) $this->cart->id;
    }

    public function invoiceIds(): array
    {
        return array_map(static fn (array $row): string => $row['invoice_id'], $this->invoices);
    }

    public function currentInvoiceId(): ?string
    {
        return $this->invoices === [] ? null : $this->invoices[count($this->invoices) - 1]['invoice_id'];
    }

    /** The payment page of the current invoice. */
    public function currentInvoiceUrl(): ?string
    {
        return $this->invoices === [] ? null : $this->invoices[count($this->invoices) - 1]['url'];
    }

    public function modeOf(string $invoiceId): ?string
    {
        foreach ($this->invoices as $row) {
            if ($row['invoice_id'] === $invoiceId) {
                return $row['mode'];
            }
        }

        return null;
    }

    public function order(): ?\Order
    {
        return $this->order;
    }

    public function hasOrder(): bool
    {
        return $this->order !== null;
    }

    public function orderIsOtherMethod(): bool
    {
        return $this->order !== null && $this->order->module !== $this->module->name;
    }

    public function state(): string
    {
        return $this->order === null ? 'none' : OrderStates::of($this->order);
    }

    public function isPaid(): bool
    {
        return $this->order !== null && $this->order->hasBeenPaid() > 0;
    }

    public function paidBy(): ?string
    {
        if ($this->order === null) {
            return null;
        }
        foreach ($this->order->getOrderPayments() as $payment) {
            $id = (string) $payment->transaction_id;
            if (in_array($id, $this->invoiceIds(), true)) {
                return $id;
            }
        }

        return null;
    }

    public function totalMinor(): ?int
    {
        if ($this->order !== null) {
            return Money::fromFloat((float) $this->order->total_paid_tax_incl);
        }

        return Money::fromFloat((float) $this->cart->getOrderTotal(true, \Cart::BOTH));
    }

    public function currency(): string
    {
        $currency = new \Currency((int) $this->cart->id_currency);

        return strtoupper((string) $currency->iso_code);
    }

    public function hasEvent(string $eventId): bool
    {
        return $this->tables->hasEvent($eventId);
    }

    public function rememberEvent(string $eventId): void
    {
        $this->tables->rememberEvent($eventId, $this->cartId());
    }

    public function openedRefund(string $refundId): bool
    {
        return $this->tables->hasRefund($refundId);
    }

    public function expired(string $invoiceId): bool
    {
        foreach ($this->invoices as $row) {
            if ($row['invoice_id'] === $invoiceId) {
                return $row['expired'];
            }
        }

        return false;
    }

    public function markExpired(string $invoiceId): void
    {
        $this->tables->markExpired($invoiceId);
        $this->invoices = $this->tables->invoicesOf($this->cartId());
    }

    public function placeOrder(string $state, array $invoice, string $message): void
    {
        $customer = new \Customer((int) $this->cart->id_customer);
        $amount = (float) Money::fromMinor(is_int($invoice['total'] ?? null) ? $invoice['total'] : 0);
        $extra = $state === 'paid' ? ['transaction_id' => (string) $invoice['id']] : [];
        $this->module->validateOrder(
            (int) $this->cart->id,
            OrderStates::id($state),
            $amount,
            $this->module->displayName,
            null,
            $extra,
            (int) $this->cart->id_currency,
            false,
            $customer->secure_key
        );
        $this->reload();
        if ($this->order === null) {
            throw new \RuntimeException('The order was not made.');
        }
        Messages::add($this->order, $message);
    }

    public function markPaid(array $invoice, string $message): void
    {
        if ($this->order === null) {
            throw new \LogicException('No order to mark paid.');
        }
        $currency = new \Currency((int) $this->order->id_currency);
        $amount = Money::fromMinor(is_int($invoice['total'] ?? null) ? $invoice['total'] : 0);
        $this->order->addOrderPayment($amount, $this->module->displayName, (string) $invoice['id'], $currency);
        Messages::add($this->order, $message);
        // The payment above is the order's: the invoice PrestaShop makes now takes it rather than adding another.
        $this->changeState(OrderStates::id('paid'), true);
    }

    public function setState(string $state, string $message): void
    {
        if ($this->order === null) {
            throw new \LogicException('No order to change.');
        }
        Messages::add($this->order, $message);
        $this->changeState(OrderStates::id($state), false);
    }

    public function addMessage(string $message): void
    {
        if ($this->order !== null) {
            Messages::add($this->order, $message);
        }
    }

    private function changeState(int $stateId, bool $useExistingPayment): void
    {
        if ($this->order === null || (int) $this->order->current_state === $stateId) {
            return;
        }
        $history = new \OrderHistory();
        $history->id_order = (int) $this->order->id;
        $history->id_employee = 0;
        $history->changeIdOrderState($stateId, $this->order, $useExistingPayment);
        $history->addWithemail(true, []);
        $this->reload();
    }

    private function reload(): void
    {
        $id = (int) \Order::getIdByCartId((int) $this->cart->id);
        $order = $id > 0 ? new \Order($id) : null;
        $this->order = $order !== null && \Validate::isLoadedObject($order) ? $order : null;
    }
}
