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

namespace PerlyPay\PrestaShop;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * The module's words in the shop's languages: what the merchant reads in
 * the back office and the order messages (admin), and what the buyer reads
 * (shop). PrestaShop's translator behind it (Shop\Translator), the plain
 * English in the tests. Parameters are named, "%invoice%".
 *
 * build.mjs reads every admin and shop call here for the
 * translation files, so the text is always written out in the call.
 */
interface Words
{
    /** @param array<string, string|int> $params */
    public function admin(string $text, array $params = []): string;

    /** @param array<string, string|int> $params */
    public function shop(string $text, array $params = []): string;
}
