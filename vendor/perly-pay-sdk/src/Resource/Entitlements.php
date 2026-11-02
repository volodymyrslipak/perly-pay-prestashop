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

use PerlyPay\PrestaShop\Vendor\Perly\Pay\Resource\Concerns\Lists;
use PerlyPay\PrestaShop\Vendor\Perly\Pay\Resource\Concerns\Retrieves;

/** `/entitlements`: what a customer may use now. */
final class Entitlements extends AbstractResource
{
    use Lists, Retrieves;

    /**
     * May this customer use this product now? `['has_access' => bool, …]`.
     *
     * @param array<string, mixed> $params `customer` or `customer_email`, and `product`
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function check(array $params, array $options = []): array
    {
        return $this->requestor->request('GET', $this->path . '/check', null, $params, $options);
    }

    /**
     * Spends credits from one credit pack; `action` labels it for your reports.
     *
     * @param array{credits: int, action?: string} $params
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function consume(string $id, array $params, array $options = []): array
    {
        return $this->action($id, 'consume', $params, $options);
    }
}
