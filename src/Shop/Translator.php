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

use PerlyPay\PrestaShop\Words;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * The module's words through PrestaShop's translator, from the module's
 * own translations/ (XLIFF, domains Modules.Perlypay.Admin and
 * Modules.Perlypay.Shop) or what the merchant changed in International →
 * Translations. Order messages are written in the shop's default language,
 * whoever's request writes them (a webhook has no employee).
 */
final class Translator implements Words
{
    public function __construct(private readonly ?string $locale = null)
    {
    }

    /** The shop's default language: for order messages and anything else the staff read later. */
    public static function forStaff(): self
    {
        $language = new \Language((int) \Configuration::get('PS_LANG_DEFAULT'));

        return new self(\Validate::isLoadedObject($language) && is_string($language->locale) && $language->locale !== '' ? $language->locale : null);
    }

    /** A buyer's language, by its id (a cart's id_lang). */
    public static function forLanguage(int $languageId): self
    {
        $language = new \Language($languageId);

        return new self(\Validate::isLoadedObject($language) && is_string($language->locale) && $language->locale !== '' ? $language->locale : null);
    }

    public function admin(string $text, array $params = []): string
    {
        return $this->translate($text, $params, 'Modules.Perlypay.Admin');
    }

    public function shop(string $text, array $params = []): string
    {
        return $this->translate($text, $params, 'Modules.Perlypay.Shop');
    }

    /** @param array<string, string|int> $params */
    private function translate(string $text, array $params, string $domain): string
    {
        return \Context::getContext()->getTranslator()->trans($text, array_map('strval', $params), $domain, $this->locale);
    }
}
