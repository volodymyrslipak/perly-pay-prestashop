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

/** `/checkout_sessions`: a cart of prices; send the buyer to its `url`. */
final class CheckoutSessions extends AbstractResource
{
    use Creates, Lists, Retrieves;

    /**
     * Ends an open session now.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function expire(string $id, array $options = []): array
    {
        return $this->action($id, 'expire', [], $options);
    }
}
