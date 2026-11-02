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

/** `/invoices`: one bill the buyer pays at its `url`. */
final class Invoices extends AbstractResource
{
    use Creates, Lists, Retrieves;

    /**
     * Cancels an invoice while nothing has arrived for it.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function cancel(string $id, array $options = []): array
    {
        return $this->action($id, 'cancel', [], $options);
    }

    /**
     * Test mode only: the buyer pays in full (default), half (`short`) or a tenth more (`over`).
     *
     * @param array{outcome?: string, chain?: string, asset?: string} $params
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function simulate(string $id, array $params = [], array $options = []): array
    {
        return $this->action($id, 'simulate', $params, $options);
    }
}
