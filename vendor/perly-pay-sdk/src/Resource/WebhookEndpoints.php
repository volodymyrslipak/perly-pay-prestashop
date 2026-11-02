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
use PerlyPay\PrestaShop\Vendor\Perly\Pay\Resource\Concerns\Deletes;
use PerlyPay\PrestaShop\Vendor\Perly\Pay\Resource\Concerns\Lists;
use PerlyPay\PrestaShop\Vendor\Perly\Pay\Resource\Concerns\Retrieves;
use PerlyPay\PrestaShop\Vendor\Perly\Pay\Resource\Concerns\Updates;

/** `/webhook_endpoints`: where events are POSTed, signed. */
final class WebhookEndpoints extends AbstractResource
{
    use Creates, Deletes, Lists, Retrieves, Updates;

    /**
     * A new signing secret; the old one stops at once.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed> the endpoint, with its new `secret`
     */
    public function rollSecret(string $id, array $options = []): array
    {
        return $this->action($id, 'roll_secret', [], $options);
    }

    /**
     * Takes this endpoint's delivery of an event once: a second claim is refused with
     * 409 `already_claimed`, an event never sent to this endpoint with 404 `not_delivered`.
     *
     * @param array{event: string} $params
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed> the event
     */
    public function claim(string $id, array $params, array $options = []): array
    {
        return $this->action($id, 'claim', $params, $options);
    }
}
