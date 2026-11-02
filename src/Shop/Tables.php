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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * This module's own tables. PrestaShop's are never altered; each row
 * points at a cart or an order by its id.
 *
 *   perlypay_invoice  every Perly Pay invoice made for a cart: its mode, total, payment page, attempt,
 *                     and whether Perly Pay said it expired
 *   perlypay_event    every event applied, by its id: a replayed webhook changes nothing
 *   perlypay_refund   every refund opened from a credit slip
 */
final class Tables
{
    /** @return list<string> the CREATE TABLE statements, run on install */
    public static function schema(): array
    {
        $engine = defined('_MYSQL_ENGINE_') ? _MYSQL_ENGINE_ : 'InnoDB';

        return [
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'perlypay_invoice` (
                `id_perlypay_invoice` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `invoice_id` VARCHAR(64) NOT NULL,
                `id_cart` INT UNSIGNED NOT NULL,
                `id_shop` INT UNSIGNED NOT NULL,
                `mode` VARCHAR(4) NOT NULL,
                `total_minor` BIGINT UNSIGNED NOT NULL,
                `currency` CHAR(3) NOT NULL,
                `url` VARCHAR(512) NOT NULL,
                `attempt` INT UNSIGNED NOT NULL,
                `expired` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `date_add` DATETIME NOT NULL,
                PRIMARY KEY (`id_perlypay_invoice`),
                UNIQUE KEY `invoice_id` (`invoice_id`),
                KEY `id_cart` (`id_cart`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'perlypay_event` (
                `event_id` VARCHAR(80) NOT NULL,
                `id_cart` INT UNSIGNED NOT NULL,
                `date_add` DATETIME NOT NULL,
                PRIMARY KEY (`event_id`),
                KEY `id_cart` (`id_cart`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'perlypay_refund` (
                `refund_id` VARCHAR(64) NOT NULL,
                `id_order` INT UNSIGNED NOT NULL,
                `id_order_slip` INT UNSIGNED NOT NULL,
                `date_add` DATETIME NOT NULL,
                PRIMARY KEY (`refund_id`),
                UNIQUE KEY `id_order_slip` (`id_order_slip`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4',
        ];
    }

    /**
     * The cart's invoices, oldest first.
     *
     * @return list<array{invoice_id: string, mode: string, total_minor: int, currency: string, url: string, attempt: int, expired: bool}>
     */
    public function invoicesOf(int $cartId): array
    {
        $rows = \Db::getInstance()->executeS('SELECT `invoice_id`, `mode`, `total_minor`, `currency`, `url`, `attempt`, `expired` FROM `' . _DB_PREFIX_ . 'perlypay_invoice` WHERE `id_cart` = ' . (int) $cartId . ' ORDER BY `id_perlypay_invoice` ASC');
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $out[] = [
                'invoice_id' => (string) $row['invoice_id'],
                'mode' => (string) $row['mode'],
                'total_minor' => (int) $row['total_minor'],
                'currency' => (string) $row['currency'],
                'url' => (string) $row['url'],
                'attempt' => (int) $row['attempt'],
                'expired' => (bool) $row['expired'],
            ];
        }

        return $out;
    }

    /** @param array{invoice_id: string, id_cart: int, id_shop: int, mode: string, total_minor: int, currency: string, url: string, attempt: int} $row */
    public function addInvoice(array $row): void
    {
        \Db::getInstance()->insert('perlypay_invoice', [
            'invoice_id' => pSQL($row['invoice_id']),
            'id_cart' => (int) $row['id_cart'],
            'id_shop' => (int) $row['id_shop'],
            'mode' => pSQL($row['mode']),
            'total_minor' => (int) $row['total_minor'],
            'currency' => pSQL($row['currency']),
            'url' => pSQL($row['url']),
            'attempt' => (int) $row['attempt'],
            'expired' => 0,
            'date_add' => date('Y-m-d H:i:s'),
        ], false, true, \Db::INSERT_IGNORE);
    }

    public function markExpired(string $invoiceId): void
    {
        \Db::getInstance()->update('perlypay_invoice', ['expired' => 1], '`invoice_id` = \'' . pSQL($invoiceId) . '\'');
    }

    public function hasEvent(string $eventId): bool
    {
        return (bool) \Db::getInstance()->getValue('SELECT 1 FROM `' . _DB_PREFIX_ . 'perlypay_event` WHERE `event_id` = \'' . pSQL($eventId) . '\'');
    }

    public function rememberEvent(string $eventId, int $cartId): void
    {
        \Db::getInstance()->insert('perlypay_event', [
            'event_id' => pSQL(mb_substr($eventId, 0, 80)),
            'id_cart' => (int) $cartId,
            'date_add' => date('Y-m-d H:i:s'),
        ], false, true, \Db::INSERT_IGNORE);
    }

    public function hasRefund(string $refundId): bool
    {
        return (bool) \Db::getInstance()->getValue('SELECT 1 FROM `' . _DB_PREFIX_ . 'perlypay_refund` WHERE `refund_id` = \'' . pSQL($refundId) . '\'');
    }

    public function slipRefunded(int $slipId): bool
    {
        return (bool) \Db::getInstance()->getValue('SELECT 1 FROM `' . _DB_PREFIX_ . 'perlypay_refund` WHERE `id_order_slip` = ' . (int) $slipId);
    }

    public function addRefund(string $refundId, int $orderId, int $slipId): void
    {
        \Db::getInstance()->insert('perlypay_refund', [
            'refund_id' => pSQL($refundId),
            'id_order' => (int) $orderId,
            'id_order_slip' => (int) $slipId,
            'date_add' => date('Y-m-d H:i:s'),
        ], false, true, \Db::INSERT_IGNORE);
    }
}
