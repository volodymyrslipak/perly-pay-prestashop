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

/** `/receipts`: one per paid invoice and per refund. */
final class Receipts extends AbstractResource
{
    use Lists, Retrieves;

    /**
     * The receipt as a PDF, its raw bytes.
     *
     * @param array<string, mixed> $options
     */
    public function pdf(string $id, array $options = []): string
    {
        return $this->requestor->raw($this->idPath($id, 'pdf'), $options);
    }
}
