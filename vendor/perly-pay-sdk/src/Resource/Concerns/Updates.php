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

namespace PerlyPay\PrestaShop\Vendor\Perly\Pay\Resource\Concerns;

if (!defined('_PS_VERSION_')) {
    exit;
}

/** @mixin \PerlyPay\PrestaShop\Vendor\Perly\Pay\Resource\AbstractResource */
trait Updates
{
    /**
     * @param array<string, mixed> $params only the fields to change
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function update(string $id, array $params, array $options = []): array
    {
        return $this->requestor->request('POST', $this->idPath($id), $params, [], $options);
    }
}
