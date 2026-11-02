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

/** `/license_keys`: keys issued with a product; check them from your app with LicenseClient. */
final class LicenseKeys extends AbstractResource
{
    use Lists, Retrieves;

    /**
     * @param array<string, mixed> $params `key`, `product`, and `instance` or `instance_name`
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function validate(array $params, array $options = []): array
    {
        return $this->requestor->request('POST', $this->path . '/validate', $params, [], $options);
    }

    /**
     * @param array<string, mixed> $params `key`, `product`, `instance_name`
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function activate(array $params, array $options = []): array
    {
        return $this->requestor->request('POST', $this->path . '/activate', $params, [], $options);
    }

    /**
     * @param array<string, mixed> $params `key`, `product`, and `instance` or `instance_name`
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function deactivate(array $params, array $options = []): array
    {
        return $this->requestor->request('POST', $this->path . '/deactivate', $params, [], $options);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function revoke(string $id, array $options = []): array
    {
        return $this->action($id, 'revoke', [], $options);
    }
}
