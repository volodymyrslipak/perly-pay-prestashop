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

namespace PerlyPay\PrestaShop\Shop;

use PerlyPay\PrestaShop\Vendor\Perly\Pay\Http\CurlTransport;
use PerlyPay\PrestaShop\Vendor\Perly\Pay\Http\Response;
use PerlyPay\PrestaShop\Vendor\Perly\Pay\Http\Transport as SdkTransport;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * The SDK's own curl transport (TLS verified, redirects never followed),
 * with this module and its version added to the User-Agent.
 */
final class Transport implements SdkTransport
{
    private readonly CurlTransport $curl;

    public function __construct()
    {
        $this->curl = new CurlTransport();
    }

    public function send(string $method, string $url, array $headers, ?string $body, float $timeoutSeconds): Response
    {
        $headers['User-Agent'] = ($headers['User-Agent'] ?? 'perly-pay-php') . ' perlypay-prestashop/' . \Perlypay::VERSION;

        return $this->curl->send($method, $url, $headers, $body, $timeoutSeconds);
    }
}
