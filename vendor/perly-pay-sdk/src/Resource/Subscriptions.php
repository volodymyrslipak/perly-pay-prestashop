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
 *
 * Perly Pay's PHP SDK (perly/perly-pay 0.2.0, MIT), its namespace prefixed by build.mjs.
 */

declare(strict_types=1);

namespace PerlyPay\PrestaShop\Vendor\Perly\Pay\Resource;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PerlyPay\PrestaShop\Vendor\Perly\Pay\Resource\Concerns\Creates;
use PerlyPay\PrestaShop\Vendor\Perly\Pay\Resource\Concerns\Lists;
use PerlyPay\PrestaShop\Vendor\Perly\Pay\Resource\Concerns\Retrieves;
use PerlyPay\PrestaShop\Vendor\Perly\Pay\Resource\Concerns\Updates;

/** `/subscriptions`: renew by invoice and reminder — never an automatic debit. */
final class Subscriptions extends AbstractResource
{
    use Creates, Lists, Retrieves, Updates;

    /**
     * Cancels now, or at the end of the period with `at_period_end`.
     *
     * @param array{at_period_end?: bool} $params
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function cancel(string $id, array $params = [], array $options = []): array
    {
        return $this->action($id, 'cancel', $params, $options);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function pause(string $id, array $options = []): array
    {
        return $this->action($id, 'pause', [], $options);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function resume(string $id, array $options = []): array
    {
        return $this->action($id, 'resume', [], $options);
    }

    /**
     * Opens one invoice for `periods` periods ahead; returns that invoice.
     *
     * @param array{periods: int} $params
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function prepay(string $id, array $params, array $options = []): array
    {
        return $this->action($id, 'prepay', $params, $options);
    }
}
