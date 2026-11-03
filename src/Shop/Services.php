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
use PerlyPay\PrestaShop\Sync\EventProcessor;
use PerlyPay\PrestaShop\Vendor\Perly\Pay\Exception\PerlyPayException;
use PerlyPay\PrestaShop\Vendor\Perly\Pay\PerlyPay;

if (!defined('_PS_VERSION_')) {
    exit;
}

/** The event processor on PrestaShop's carts and orders, asking this client for what an event does not carry. */
final class Services
{
    public static function processor(PerlyPay $client, \Perlypay $module): EventProcessor
    {
        $tables = new Tables();

        return new EventProcessor(
            new Purchases($tables, $module),
            Translator::forStaff(),
            static function (string $invoiceId) use ($client): ?array {
                try {
                    $page = $client->payments->list(Requests::paymentsOf($invoiceId), ['max_retries' => 1]);

                    return is_array($page['data'] ?? null) ? $page['data'] : null;
                } catch (PerlyPayException $error) {
                    Log::error('payments lookup failed', ['invoice' => $invoiceId, 'code' => $error->getErrorCode()]);

                    return null;
                }
            },
            static function (string $invoiceId) use ($client): ?array {
                try {
                    return $client->invoices->retrieve($invoiceId, ['max_retries' => 1]);
                } catch (PerlyPayException $error) {
                    Log::error('invoice lookup failed', ['invoice' => $invoiceId, 'code' => $error->getErrorCode()]);
                    // Not found for this key is an answer (not this shop's invoice). Anything else (no connection, a 5xx,
                    // a revoked key) is not: it is thrown so the webhook answers 500 and Perly Pay sends the event again,
                    // since the webhook secret alone is not proof of payment and the event is never applied unchecked.
                    if ($error->getHttpStatus() === 404) {
                        return null;
                    }
                    throw $error;
                }
            },
            static function (string $refundId) use ($client): ?array {
                try {
                    return $client->refunds->retrieve($refundId, ['max_retries' => 1]);
                } catch (PerlyPayException $error) {
                    Log::error('refund lookup failed', ['refund' => $refundId, 'code' => $error->getErrorCode()]);
                    // The same rule for a refund: not found is not ours, anything else is retried, since what a message
                    // tells the merchant to send, and where, is only ever what Perly Pay says.
                    if ($error->getHttpStatus() === 404) {
                        return null;
                    }
                    throw $error;
                }
            }
        );
    }
}
