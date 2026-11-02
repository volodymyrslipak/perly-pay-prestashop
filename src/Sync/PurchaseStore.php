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
 * Finds the cart an invoice was made for, and runs work on it one request
 * at a time (a delivery and the buyer's return can arrive together).
 */
interface PurchaseStore
{
    /** The cart with this id and its order, if the cart exists and this module made invoices for it. */
    public function find(int $cartId): ?Purchase;

    /**
     * Runs $work while holding this cart's lock; returns what $work returned.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function withLock(int $cartId, callable $work);
}
