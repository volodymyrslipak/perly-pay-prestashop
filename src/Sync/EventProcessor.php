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

use PerlyPay\PrestaShop\Support\Chains;
use PerlyPay\PrestaShop\Support\Money;
use PerlyPay\PrestaShop\Words;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * What a verified Perly Pay event does to a PrestaShop cart and its order.
 * One place for the rules, used by the webhook and by the buyer's return
 * (which asks the API, in case the webhook is late).
 *
 * PrestaShop's rule for payment modules: no order before the customer has
 * been through the payment service. So the order is made only once money
 * is on its way (the first event that says so), for the amount Perly Pay
 * billed:
 *
 *   invoice.fulfilled (or paid/overpaid once fulfilled)  → "Payment accepted", the invoice as the payment's
 *                                                          transaction id, a message with each transfer, its
 *                                                          network and a tx link (and "paid late" when the
 *                                                          invoice had expired before the money came)
 *   invoice.paid / overpaid before fulfilment             → awaiting, a message: Perly Pay is screening the sender
 *   invoice.seen / underpaid                              → awaiting, a message
 *   invoice.review                                        → held
 *   invoice.expired / canceled, nothing paid              → no order (the cart stays; the buyer can pay again),
 *                                                          or "Payment error" for an order whose money was
 *                                                          seen and then dropped
 *   invoice.expired with part paid                        → held
 *   invoice.refunded                                      → "Refunded" (in full, the invoice that paid the order),
 *                                                          else a message
 *   refund.*                                              → a message, with what to send where
 *
 * An invoice paid for another total than the order's (the buyer changed
 * the cart after the invoice was made, and the replaced invoice could not
 * be canceled) holds the order instead of accepting the payment. A second
 * invoice paid for an order already paid, or for a cart another payment
 * method ordered, leaves a message to refund it.
 *
 * Every event is applied once (its id is remembered), under the cart's
 * lock, and only to the cart the invoice was made for: the invoice must be
 * one of that cart's, in the same mode. Invoices another integration made
 * (metadata.source other than "prestashop") are not looked at. A paid
 * order is never moved back by a late "expired". Anything else is
 * acknowledged and ignored, so Perly Pay does not retry it.
 */
final class EventProcessor
{
    /** The events a shop's endpoint should send, and this class reads. */
    public const EVENT_TYPES = [
        'invoice.seen',
        'invoice.underpaid',
        'invoice.paid',
        'invoice.overpaid',
        'invoice.review',
        'invoice.expired',
        'invoice.canceled',
        'invoice.fulfilled',
        'invoice.refunded',
        'refund.requested',
        'refund.address_received',
        'refund.sent',
        'refund.confirmed',
        'refund.canceled',
    ];

    /** The metadata.source this module's invoices carry. */
    public const SOURCE = 'prestashop';

    /** States an order waiting for its money can be in. */
    private const UNPAID_STATES = ['awaiting', 'held', 'error'];

    /** @var callable(string): ?array */
    private $payments;

    /** @var callable(string): ?array */
    private $invoice;

    /**
     * @param callable(string): ?array $payments an invoice's payments (the API's list `data`), null when unknown
     * @param callable(string): ?array $invoice an invoice by id (for refund events, which name only the invoice)
     */
    public function __construct(private readonly PurchaseStore $purchases, private readonly Words $words, callable $payments, callable $invoice)
    {
        $this->payments = $payments;
        $this->invoice = $invoice;
    }

    /**
     * Applies one event.
     *
     * @param array<string, mixed> $event the Event object, verified
     *
     * @return array{result: string, reason: string, cart?: int}
     */
    public function handle(array $event): array
    {
        $type = is_string($event['type'] ?? null) ? $event['type'] : '';
        $id = is_string($event['id'] ?? null) ? $event['id'] : '';
        $object = is_array($event['data']['object'] ?? null) ? $event['data']['object'] : null;
        if (!in_array($type, self::EVENT_TYPES, true)) {
            return self::outcome('ignored', 'event_type');
        }
        if ($id === '' || $object === null) {
            return self::outcome('ignored', 'no_object');
        }

        $invoice = $object;
        if (str_starts_with($type, 'refund.')) {
            $invoiceId = is_string($object['invoice'] ?? null) ? $object['invoice'] : '';
            $invoice = $invoiceId === '' ? null : ($this->invoice)($invoiceId);
            if ($invoice === null) {
                return self::outcome('ignored', 'no_invoice');
            }
        }
        $invoiceId = is_string($invoice['id'] ?? null) ? $invoice['id'] : '';
        $cartId = self::cartIdOf($invoice);
        if ($invoiceId === '' || $cartId === null || self::SOURCE !== ($invoice['metadata']['source'] ?? null)) {
            return self::outcome('ignored', 'not_from_prestashop');
        }

        return $this->purchases->withLock(
            $cartId,
            function () use ($type, $id, $object, $invoice, $invoiceId, $cartId): array {
                $purchase = $this->purchases->find($cartId);
                if ($purchase === null) {
                    return self::outcome('ignored', 'no_cart');
                }
                if (!in_array($invoiceId, $purchase->invoiceIds(), true)) {
                    return self::outcome('ignored', 'other_invoice', $cartId);
                }
                $mode = ($invoice['livemode'] ?? false) === true ? 'live' : 'test';
                if ($purchase->modeOf($invoiceId) !== $mode) {
                    return self::outcome('ignored', 'other_mode', $cartId);
                }
                if ($purchase->hasEvent($id)) {
                    return self::outcome('duplicate', 'seen_before', $cartId);
                }

                $reason = str_starts_with($type, 'refund.')
                    ? $this->applyRefund($purchase, $type, $object)
                    : $this->applyInvoice($purchase, $type, $invoice);
                $purchase->rememberEvent($id);

                return self::outcome('applied', $reason, $cartId);
            }
        );
    }

    /**
     * What the buyer's return does with the invoice it asked the API for: the same as its latest event.
     *
     * @param array<string, mixed> $invoice
     *
     * @return array{result: string, reason: string, cart?: int}
     */
    public function sync(array $invoice): array
    {
        $status = is_string($invoice['status'] ?? null) ? $invoice['status'] : '';
        $type = match ($status) {
            'paid', 'overpaid' => empty($invoice['fulfilled_at']) ? 'invoice.' . $status : 'invoice.fulfilled',
            'seen', 'underpaid', 'review', 'expired', 'canceled' => 'invoice.' . $status,
            'refunded', 'partially_refunded' => 'invoice.refunded',
            default => '',
        };
        if ($type === '') {
            return self::outcome('ignored', 'nothing_new');
        }

        return $this->handle([
            'id' => 'sync_' . (is_string($invoice['id'] ?? null) ? $invoice['id'] : '') . '_' . substr($type, 8),
            'type' => $type,
            'data' => ['object' => $invoice],
        ]);
    }

    /** @param array<string, mixed> $invoice */
    private function applyInvoice(Purchase $purchase, string $type, array $invoice): string
    {
        $id = (string) $invoice['id'];
        if ($type === 'invoice.expired' || $type === 'invoice.canceled') {
            $purchase->markExpired($id);
        }
        if ($purchase->orderIsOtherMethod()) {
            if (in_array($type, ['invoice.fulfilled', 'invoice.paid', 'invoice.overpaid'], true)) {
                $purchase->addMessage($this->words->admin('Perly Pay invoice %invoice% for this cart was paid, but the order was placed with another payment method. Refund it in Perly Pay.', ['%invoice%' => $id]));

                return 'paid_other_method';
            }

            return 'other_method';
        }

        return $purchase->hasOrder() ? $this->applyToOrder($purchase, $type, $invoice) : $this->placeOrder($purchase, $type, $invoice);
    }

    /**
     * The first event that says money is on its way makes the order.
     *
     * @param array<string, mixed> $invoice
     */
    private function placeOrder(Purchase $purchase, string $type, array $invoice): string
    {
        $id = (string) $invoice['id'];
        switch ($type) {
            case 'invoice.fulfilled':
            case 'invoice.paid':
            case 'invoice.overpaid':
                if (empty($invoice['fulfilled_at']) && $type !== 'invoice.fulfilled') {
                    $purchase->placeOrder('awaiting', $invoice, $this->screening($id));

                    return 'placed_screening';
                }
                $mismatch = $this->mismatch($purchase, $invoice);
                if ($mismatch !== null) {
                    $purchase->placeOrder('held', $invoice, $mismatch);

                    return 'placed_other_total';
                }
                $purchase->placeOrder('paid', $invoice, $this->paidMessage($purchase, $invoice));

                return 'placed_paid';

            case 'invoice.seen':
                $purchase->placeOrder('awaiting', $invoice, $this->seen($invoice));

                return 'placed_seen';

            case 'invoice.underpaid':
                $purchase->placeOrder('awaiting', $invoice, $this->underpaid($invoice));

                return 'placed_underpaid';

            case 'invoice.review':
                $purchase->placeOrder('held', $invoice, $this->review($invoice));

                return 'placed_review';

            case 'invoice.expired':
            case 'invoice.canceled':
                if (self::cents($invoice, 'received_usd') > 0) {
                    $purchase->placeOrder('held', $invoice, $this->closedPartPaid($invoice));

                    return 'placed_closed_part_paid';
                }

                return 'closed_unpaid';
        }

        return 'no_order';
    }

    /** @param array<string, mixed> $invoice */
    private function applyToOrder(Purchase $purchase, string $type, array $invoice): string
    {
        $id = (string) $invoice['id'];
        $paid = $purchase->isPaid();

        switch ($type) {
            case 'invoice.fulfilled':
            case 'invoice.paid':
            case 'invoice.overpaid':
                if ($paid) {
                    if ($purchase->paidBy() !== null && $purchase->paidBy() !== $id && (!empty($invoice['fulfilled_at']) || $type === 'invoice.fulfilled')) {
                        $purchase->addMessage($this->words->admin('Perly Pay invoice %invoice% was paid too, for an order already paid by %paid%. Refund one of them in Perly Pay.', ['%invoice%' => $id, '%paid%' => (string) $purchase->paidBy()]));

                        return 'paid_twice';
                    }

                    return 'already_paid';
                }
                if (empty($invoice['fulfilled_at']) && $type !== 'invoice.fulfilled') {
                    $purchase->addMessage($this->screening($id));

                    return 'screening';
                }
                $mismatch = $this->mismatch($purchase, $invoice);
                if ($mismatch !== null) {
                    $purchase->setState('held', $mismatch);

                    return 'paid_other_total';
                }
                $purchase->markPaid($invoice, $this->paidMessage($purchase, $invoice));

                return 'paid';

            case 'invoice.seen':
                if ($paid) {
                    return 'already_paid';
                }
                $purchase->addMessage($this->seen($invoice));

                return 'seen';

            case 'invoice.underpaid':
                if ($paid) {
                    return 'already_paid';
                }
                $purchase->addMessage($this->underpaid($invoice));

                return 'underpaid';

            case 'invoice.review':
                if ($paid) {
                    $purchase->addMessage($this->review($invoice));
                } else {
                    $purchase->setState('held', $this->review($invoice));
                }

                return 'review';

            case 'invoice.expired':
            case 'invoice.canceled':
                if ($paid) {
                    return 'already_paid';
                }
                if (self::cents($invoice, 'received_usd') > 0) {
                    $purchase->setState('held', $this->closedPartPaid($invoice));

                    return 'closed_part_paid';
                }
                if ($purchase->currentInvoiceId() !== $id || !in_array($purchase->state(), self::UNPAID_STATES, true)) {
                    $purchase->addMessage($this->words->admin('An earlier invoice for this cart closed unpaid: %invoice%.', ['%invoice%' => $id]));

                    return 'earlier_invoice_closed';
                }
                $purchase->setState(
                    'error',
                    $type === 'invoice.expired'
                        ? $this->words->admin('The Perly Pay invoice %invoice% expired: the payment seen for it never arrived.', ['%invoice%' => $id])
                        : $this->words->admin('The Perly Pay invoice %invoice% was canceled.', ['%invoice%' => $id])
                );

                return 'failed';

            case 'invoice.refunded':
                if (($invoice['status'] ?? '') === 'refunded') {
                    if ($purchase->paidBy() !== $id) {
                        // Another of the cart's invoices: what paid the order was not refunded.
                        $purchase->addMessage($this->words->admin('Refunded in full in Perly Pay (%invoice%). That invoice did not pay this order, so its state is unchanged.', ['%invoice%' => $id]));

                        return 'other_invoice_refunded';
                    }
                    if ($purchase->state() !== 'refunded') {
                        $purchase->setState('refunded', $this->words->admin('Refunded in full in Perly Pay (%invoice%).', ['%invoice%' => $id]));

                        return 'refunded';
                    }
                    $purchase->addMessage($this->words->admin('Refund confirmed in Perly Pay (%invoice%).', ['%invoice%' => $id]));

                    return 'refund_confirmed';
                }
                $purchase->addMessage($this->words->admin('Partly refunded in Perly Pay (%invoice%).', ['%invoice%' => $id]));

                return 'partly_refunded';
        }

        return 'none';
    }

    /** @param array<string, mixed> $refund */
    private function applyRefund(Purchase $purchase, string $type, array $refund): string
    {
        if (!$purchase->hasOrder() || $purchase->orderIsOtherMethod()) {
            return 'no_order';
        }
        $id = is_string($refund['id'] ?? null) ? $refund['id'] : '';
        $chain = is_string($refund['chain'] ?? null) ? $refund['chain'] : '';
        $asset = Chains::asset(is_string($refund['asset'] ?? null) ? $refund['asset'] : '', $chain);
        $network = Chains::name($chain);
        $decimals = is_int($refund['decimals'] ?? null) ? $refund['decimals'] : 0;
        $txid = is_string($refund['txid'] ?? null) ? $refund['txid'] : '';
        $tx = $txid === '' ? '' : (Chains::txUrl($chain, $txid) ?? $txid);

        switch ($type) {
            case 'refund.requested':
                if ($purchase->openedRefund($id)) {
                    return 'refund_requested_here';
                }
                $purchase->addMessage($this->words->admin('Refund %refund% opened in Perly Pay. The customer gets an email to give an address.', ['%refund%' => $id]));

                return 'refund_requested';

            case 'refund.address_received':
                $send = is_string($refund['amount_to_send'] ?? null) ? Money::units($refund['amount_to_send'], $decimals) : '';
                $address = is_string($refund['refund_address'] ?? null) ? $refund['refund_address'] : '';
                if ($send === '' || $address === '') {
                    $purchase->addMessage($this->words->admin('Refund %refund%: the customer gave an address. Perly Pay is checking it.', ['%refund%' => $id]));

                    return 'refund_address';
                }
                $purchase->addMessage($this->words->admin('Refund %refund% is ready. Send %amount% %asset% on %network% to %address% from your wallet, then paste the transaction id in Perly Pay.', ['%refund%' => $id, '%amount%' => $send, '%asset%' => $asset, '%network%' => $network, '%address%' => $address]));

                return 'refund_ready';

            case 'refund.sent':
                $purchase->addMessage($this->words->admin('Refund %refund% sent: %tx%. Waiting for the network.', ['%refund%' => $id, '%tx%' => $tx]));

                return 'refund_sent';

            case 'refund.confirmed':
                $amount = is_string($refund['amount_to_send'] ?? null) ? $refund['amount_to_send'] : (is_string($refund['amount'] ?? null) ? $refund['amount'] : '0');
                $purchase->addMessage($this->words->admin('Refund %refund% confirmed: %amount% %asset% on %network%. %tx%', ['%refund%' => $id, '%amount%' => Money::units($amount, $decimals), '%asset%' => $asset, '%network%' => $network, '%tx%' => $tx]));

                return 'refund_confirmed';

            case 'refund.canceled':
                $purchase->addMessage($this->words->admin('Refund %refund% was canceled.', ['%refund%' => $id]));

                return 'refund_canceled';
        }

        return 'none';
    }

    /** @param array<string, mixed> $invoice why it may not pay the order, or null when it may */
    private function mismatch(Purchase $purchase, array $invoice): ?string
    {
        $billed = is_int($invoice['total'] ?? null) ? $invoice['total'] : null;
        $billedIn = strtoupper(is_string($invoice['currency'] ?? null) ? $invoice['currency'] : '');
        $total = $purchase->totalMinor();
        if ($total === null || ($billed === $total && $billedIn === $purchase->currency())) {
            return null;
        }

        return $this->words->admin('%invoice% was paid for %billed%, but this order is %total%. Settle it in Perly Pay: refund it, or take the rest and mark this order paid.', [
            '%invoice%' => (string) $invoice['id'],
            '%billed%' => Money::fiat($billed ?? 0, $billedIn),
            '%total%' => Money::fiat($total, $purchase->currency()),
        ]);
    }

    private function screening(string $id): string
    {
        return $this->words->admin('Payment received for %invoice%. Perly Pay is checking the sender; the payment is accepted when that is done.', ['%invoice%' => $id]);
    }

    /** @param array<string, mixed> $invoice */
    private function seen(array $invoice): string
    {
        return $this->words->admin('Payment seen on %network% for %invoice%. Waiting for confirmations.', ['%network%' => $this->networkOf($invoice), '%invoice%' => (string) $invoice['id']]);
    }

    /** @param array<string, mixed> $invoice */
    private function underpaid(array $invoice): string
    {
        return $this->words->admin('Part paid: %received% of %due% received for %invoice%. The customer can still send the rest.', ['%received%' => self::usd($invoice, 'received_usd'), '%due%' => self::usd($invoice, 'total_usd'), '%invoice%' => (string) $invoice['id']]);
    }

    /** @param array<string, mixed> $invoice */
    private function review(array $invoice): string
    {
        $reason = is_string($invoice['review_reason'] ?? null) && $invoice['review_reason'] !== '' ? $invoice['review_reason'] : 'review';

        return $this->words->admin('Held for review in Perly Pay (%invoice%): %reason%. Decide there; ship only once the payment is accepted.', ['%invoice%' => (string) $invoice['id'], '%reason%' => $reason]);
    }

    /** @param array<string, mixed> $invoice */
    private function closedPartPaid(array $invoice): string
    {
        return $this->words->admin('%invoice% closed with %received% of %due% paid. Settle it in Perly Pay: refund it, or accept it there and mark this order paid.', ['%invoice%' => (string) $invoice['id'], '%received%' => self::usd($invoice, 'received_usd'), '%due%' => self::usd($invoice, 'total_usd')]);
    }

    /** @param array<string, mixed> $invoice */
    private function paidMessage(Purchase $purchase, array $invoice): string
    {
        $id = (string) $invoice['id'];
        $lines = [];
        $list = ($this->payments)($id);
        foreach (is_array($list) ? $list : [] as $payment) {
            if (!is_array($payment) || !empty($payment['dropped_at'])) {
                continue;
            }
            $chain = is_string($payment['chain'] ?? null) ? $payment['chain'] : '';
            $txid = is_string($payment['txid'] ?? null) ? $payment['txid'] : '';
            $lines[] = $this->words->admin('Received %amount% %asset% on %network%: %tx%', [
                '%amount%' => Money::units(is_string($payment['amount'] ?? null) ? $payment['amount'] : '0', is_int($payment['decimals'] ?? null) ? $payment['decimals'] : 0),
                '%asset%' => Chains::asset(is_string($payment['asset'] ?? null) ? $payment['asset'] : '', $chain),
                '%network%' => Chains::name($chain),
                '%tx%' => Chains::txUrl($chain, $txid) ?? $txid,
            ]);
        }
        if ($lines === [] && is_array($invoice['payment'] ?? null)) {
            $rail = $invoice['payment'];
            $chain = is_string($rail['chain'] ?? null) ? $rail['chain'] : '';
            $lines[] = $this->words->admin('Received %amount% %asset% on %network%.', [
                '%amount%' => Money::units(is_string($invoice['amount_received'] ?? null) ? $invoice['amount_received'] : '0', is_int($rail['decimals'] ?? null) ? $rail['decimals'] : 0),
                '%asset%' => Chains::asset(is_string($rail['asset'] ?? null) ? $rail['asset'] : '', $chain),
                '%network%' => Chains::name($chain),
            ]);
        }

        $message = $this->words->admin('Paid with Perly Pay (%invoice%).', ['%invoice%' => $id]);
        if (($invoice['livemode'] ?? false) !== true) {
            $message = $this->words->admin('Test mode: no real money moved.') . ' ' . $message;
        }
        if ($purchase->expired($id)) {
            $lines[] = $this->words->admin('Paid late: the money arrived after the invoice had expired.');
        }
        if (($invoice['status'] ?? '') === 'overpaid') {
            $lines[] = $this->words->admin('Overpaid: %received% received for %due%. Refund the difference in Perly Pay if you need to.', ['%received%' => self::usd($invoice, 'received_usd'), '%due%' => self::usd($invoice, 'total_usd')]);
        }

        return implode("\n", array_merge([$message], $lines));
    }

    /** @param array<string, mixed> $invoice */
    private static function cartIdOf(array $invoice): ?int
    {
        $raw = $invoice['metadata']['cart_id'] ?? null;
        if (!is_string($raw) || preg_match('/^[1-9]\d{0,9}$/', $raw) !== 1) {
            return null;
        }

        return (int) $raw;
    }

    /** @param array<string, mixed> $invoice */
    private function networkOf(array $invoice): string
    {
        $chain = is_array($invoice['payment'] ?? null) && is_string($invoice['payment']['chain'] ?? null) ? $invoice['payment']['chain'] : '';

        return $chain === '' ? $this->words->admin('the network') : Chains::name($chain);
    }

    /** @param array<string, mixed> $invoice */
    private static function cents(array $invoice, string $field): int
    {
        return is_int($invoice[$field] ?? null) ? $invoice[$field] : 0;
    }

    /** @param array<string, mixed> $invoice */
    private static function usd(array $invoice, string $field): string
    {
        return '$' . Money::fromMinor(self::cents($invoice, $field));
    }

    /** @return array{result: string, reason: string, cart?: int} */
    private static function outcome(string $result, string $reason, ?int $cart = null): array
    {
        return $cart === null ? ['result' => $result, 'reason' => $reason] : ['result' => $result, 'reason' => $reason, 'cart' => $cart];
    }
}
