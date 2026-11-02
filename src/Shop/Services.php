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

                    return null;
                }
            }
        );
    }
}
