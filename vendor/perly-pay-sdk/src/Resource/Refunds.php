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

/** `/refunds`: you send from your own wallet; the buyer gives the address. */
final class Refunds extends AbstractResource
{
    use Creates, Lists, Retrieves;

    /**
     * What a refund of this invoice would send now.
     *
     * @param array{invoice: string} $params
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function quote(array $params, array $options = []): array
    {
        return $this->requestor->request('GET', $this->path . '/quote', null, $params, $options);
    }

    /**
     * After you sent `amount_to_send` to `refund_address`: the transaction that did it.
     *
     * @param array{txid: string} $params
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function markSent(string $id, array $params, array $options = []): array
    {
        return $this->action($id, 'mark_sent', $params, $options);
    }
}
