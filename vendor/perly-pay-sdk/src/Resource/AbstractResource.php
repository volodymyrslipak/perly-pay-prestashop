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

use PerlyPay\PrestaShop\Vendor\Perly\Pay\Http\Requestor;

/**
 * One collection of the API (`/invoices`, `/refunds`, …). The verbs it
 * has come from the traits in Concerns; its own actions are methods.
 * Every call returns the API's JSON as an associative array, and takes
 * request options last (`idempotency_key`, `max_retries`, `timeout`).
 */
abstract class AbstractResource
{
    public function __construct(
        protected readonly Requestor $requestor,
        protected readonly string $path,
    ) {
    }

    protected function idPath(string $id, string $action = ''): string
    {
        if ($id === '') {
            throw new \InvalidArgumentException('An id is required.');
        }

        return $this->path . '/' . rawurlencode($id) . ($action !== '' ? '/' . $action : '');
    }

    /**
     * POST /{collection}/{id}/{action}
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    protected function action(string $id, string $action, array $params = [], array $options = []): array
    {
        return $this->requestor->request('POST', $this->idPath($id, $action), $params, [], $options);
    }
}
