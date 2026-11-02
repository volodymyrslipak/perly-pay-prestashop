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
use PerlyPay\PrestaShop\Settings;
use PerlyPay\PrestaShop\Support\Money;
use PerlyPay\PrestaShop\Vendor\Perly\Pay\Exception\AuthenticationException;
use PerlyPay\PrestaShop\Vendor\Perly\Pay\Exception\ConnectionException;
use PerlyPay\PrestaShop\Vendor\Perly\Pay\Exception\PerlyPayException;
use PerlyPay\PrestaShop\Words;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Refunds from the order page. A credit slip (Partial refund, Standard
 * refund, Return products) on an order Perly Pay paid opens a refund in
 * Perly Pay for the slip's amount: all of what arrived, or its share of
 * it, in the coin that was paid. The setting "Open refunds in Perly Pay"
 * turns this off; a slip given as a voucher never opens one.
 *
 * Perly Pay holds no money: the customer gives an address by email,
 * Perly Pay checks it, and the merchant sends from their own wallet. So
 * the credit slip is made at once, as for any payment method, and the
 * order's messages say what is left to do. If Perly Pay refuses, the slip
 * stays and a message says why.
 */
final class Refunds
{
    private readonly Tables $tables;

    public function __construct(private readonly \Perlypay $module, ?Tables $tables = null)
    {
        $this->tables = $tables ?? new Tables();
    }

    /** @param array<string, mixed> $params actionOrderSlipAdd's: order, productList, qtyList */
    public function onCreditSlip(array $params): void
    {
        $order = $params['order'] ?? null;
        if (!$order instanceof \Order || $order->module !== $this->module->name) {
            return;
        }
        $words = Translator::forStaff();
        $slip = self::latestSlip((int) $order->id);
        if ($slip === null || $this->tables->slipRefunded((int) $slip->id)) {
            return;
        }
        if (!Settings::refunds()) {
            Messages::add($order, $words->admin('Not refunded in Perly Pay: "Open refunds in Perly Pay" is off. Open the refund in Perly Pay if the customer should get the coins back.'));

            return;
        }
        // The refund form's "Generate a voucher": the customer gets the voucher instead of money.
        $form = \Tools::getValue('cancel_product');
        if (is_array($form) && !empty($form['voucher'])) {
            Messages::add($order, $words->admin('Not refunded in Perly Pay: this credit slip was given as a voucher.'));

            return;
        }

        $invoiceId = $this->paidBy($order);
        $part = Money::fromFloat((float) $slip->amount + (float) $slip->shipping_cost_amount);
        $client = Settings::client();
        if ($invoiceId === null || $part === null || $part <= 0 || $client === null) {
            Messages::add($order, $words->admin('Not refunded in Perly Pay: the order has no Perly Pay payment, the credit slip no amount, or the module no secret key. Open the refund in Perly Pay.'));

            return;
        }

        try {
            $invoice = $client->invoices->retrieve($invoiceId);
            $opened = $client->refunds->create(Requests::refund($invoice, $part), ['idempotency_key' => Requests::refundKey(self::shopUrl($order), (int) $order->id, (int) $slip->id, (string) $slip->date_add)]);
        } catch (\InvalidArgumentException $error) {
            Messages::add($order, $words->admin('Not refunded in Perly Pay: that amount is too small to send in the coin that was paid.'));

            return;
        } catch (PerlyPayException $error) {
            Log::error('refund not opened', ['order' => (int) $order->id, 'code' => $error->getErrorCode(), 'request' => $error->getRequestId()]);
            Messages::add($order, self::explain($error, $words));

            return;
        }

        $id = is_string($opened['id'] ?? null) ? $opened['id'] : '';
        $this->tables->addRefund($id, (int) $order->id, (int) $slip->id);
        $currency = new \Currency((int) $order->id_currency);
        Messages::add($order, $words->admin('Refund %refund% opened in Perly Pay for %amount%. The customer gets an email to give an address; once it is checked, send the amount from your wallet and paste the transaction id in Perly Pay.', [
            '%refund%' => $id,
            '%amount%' => Money::fiat($part, (string) $currency->iso_code),
        ]));
        Log::info('refund opened', ['order' => (int) $order->id, 'refund' => $id, 'slip' => (int) $slip->id]);
    }

    /** The invoice that paid the order: its payment's transaction id, one of this module's invoices. */
    private function paidBy(\Order $order): ?string
    {
        foreach ($order->getOrderPayments() as $payment) {
            $id = (string) $payment->transaction_id;
            if (preg_match('/^inv_[0-9a-f]{32}$/', $id) === 1) {
                return $id;
            }
        }

        return null;
    }

    private static function latestSlip(int $orderId): ?\OrderSlip
    {
        $id = (int) \Db::getInstance()->getValue('SELECT MAX(`id_order_slip`) FROM `' . _DB_PREFIX_ . 'order_slip` WHERE `id_order` = ' . (int) $orderId, false);
        $slip = $id > 0 ? new \OrderSlip($id) : null;

        return $slip !== null && \Validate::isLoadedObject($slip) ? $slip : null;
    }

    private static function shopUrl(\Order $order): string
    {
        return (string) (new \Shop((int) $order->id_shop))->getBaseURL(true);
    }

    private static function explain(PerlyPayException $error, Words $words): string
    {
        if ($error->getErrorCode() === 'sanctioned_sender') {
            return $words->admin('Not refunded in Perly Pay: money on this order came from an address on a sanctions list, or one its issuer froze. It cannot be refunded; talk to a lawyer.');
        }
        if ($error instanceof AuthenticationException) {
            return $words->admin('Not refunded in Perly Pay: it refused the secret key. Check it in the module\'s settings, then open the refund in Perly Pay.');
        }
        if ($error instanceof ConnectionException) {
            return $words->admin('Not refunded in Perly Pay: it could not be reached. Open the refund in Perly Pay.');
        }

        return $words->admin('Not refunded in Perly Pay: %reason%', ['%reason%' => $error->getMessage()]);
    }
}
