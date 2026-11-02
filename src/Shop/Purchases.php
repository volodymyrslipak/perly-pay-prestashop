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

use PerlyPay\PrestaShop\Sync\Purchase;
use PerlyPay\PrestaShop\Sync\PurchaseStore;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Carts this module made invoices for, and a lock per cart. The lock is
 * the database's own named lock (MySQL and MariaDB GET_LOCK): a webhook
 * and the buyer's return can arrive together, and only one of them may
 * make the order or apply an event at a time. It is released when the
 * work ends, or by the database if the request dies.
 *
 * Finding a cart also sets PrestaShop's context to it (its shop, customer,
 * currency, language and country), as validateOrder and the cart's totals
 * read them, whoever's request this is: a webhook has no visitor.
 */
final class Purchases implements PurchaseStore
{
    private const WAIT_SECONDS = 15;

    public function __construct(private readonly Tables $tables, private readonly \Perlypay $module)
    {
    }

    public function find(int $cartId): ?Purchase
    {
        $cart = new \Cart($cartId);
        if (!\Validate::isLoadedObject($cart) || $this->tables->invoicesOf($cartId) === []) {
            return null;
        }
        self::useCart($cart);

        return new CartPurchase($cart, $this->tables, $this->module);
    }

    public function withLock(int $cartId, callable $work)
    {
        $name = mb_substr('perlypay_' . _DB_NAME_ . '_' . _DB_PREFIX_ . $cartId, 0, 64);
        $got = (string) \Db::getInstance()->getValue('SELECT GET_LOCK(\'' . pSQL($name) . '\', ' . self::WAIT_SECONDS . ')', false);
        if ($got === '0') {
            throw new \RuntimeException('The cart is busy.');
        }
        try {
            return $work();
        } finally {
            if ($got === '1') {
                \Db::getInstance()->getValue('SELECT RELEASE_LOCK(\'' . pSQL($name) . '\')', false);
            }
        }
    }

    /** PrestaShop's context set to this cart. */
    public static function useCart(\Cart $cart): void
    {
        $context = \Context::getContext();
        if ((int) $cart->id_shop > 0 && (!isset($context->shop) || (int) $context->shop->id !== (int) $cart->id_shop)) {
            \Shop::setContext(\Shop::CONTEXT_SHOP, (int) $cart->id_shop);
            $context->shop = new \Shop((int) $cart->id_shop);
        }
        $context->cart = $cart;
        $context->customer = new \Customer((int) $cart->id_customer);
        $context->currency = new \Currency((int) $cart->id_currency, null, (int) $cart->id_shop);
        $context->language = new \Language((int) $cart->id_lang);
        $address = new \Address((int) $cart->id_address_invoice);
        if (\Validate::isLoadedObject($address)) {
            $context->country = new \Country((int) $address->id_country, (int) $cart->id_lang);
        }
    }
}
