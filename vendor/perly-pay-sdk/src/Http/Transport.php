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

namespace PerlyPay\PrestaShop\Vendor\Perly\Pay\Http;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * How a request leaves the machine. The SDK ships `CurlTransport`; pass
 * your own (a proxy, a test double, your framework's client) as the
 * `transport` option.
 */
interface Transport
{
    /**
     * Sends one request and returns whatever the server answered, any status.
     *
     * @param array<string, string> $headers
     *
     * @throws TransportException when no answer came back (DNS, refused, timeout)
     */
    public function send(string $method, string $url, array $headers, ?string $body, float $timeoutSeconds): Response;
}
