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

use PerlyPay\PrestaShop\Settings;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * The order states this module names (Sync\Purchase): two of its own,
 * made on install, and PrestaShop's for the rest.
 *
 *   awaiting  "Awaiting crypto confirmation"  money seen, not yet confirmed and screened
 *   held      "Crypto payment held"           someone has to look in Perly Pay
 *   paid      PS_OS_PAYMENT ("Payment accepted")
 *   error     PS_OS_ERROR ("Payment error")
 *   refunded  PS_OS_REFUND ("Refunded")
 */
final class OrderStates
{
    public static function id(string $state): int
    {
        $key = [
            'awaiting' => Settings::OS_AWAITING,
            'held' => Settings::OS_HELD,
            'paid' => 'PS_OS_PAYMENT',
            'error' => 'PS_OS_ERROR',
            'refunded' => 'PS_OS_REFUND',
        ][$state] ?? null;
        if ($key === null) {
            throw new \InvalidArgumentException('No order state "' . $state . '".');
        }

        return (int) \Configuration::get($key);
    }

    /** Which of the states above an order is in, "paid" for any later state of a paid order, else "other". */
    public static function of(\Order $order): string
    {
        $current = (int) $order->current_state;
        foreach (['awaiting', 'held', 'error', 'refunded', 'paid'] as $state) {
            if ($current === self::id($state)) {
                return $state;
            }
        }

        return $order->hasBeenPaid() ? 'paid' : 'other';
    }

    /**
     * Makes this module's two states once, in every language, and remembers their ids.
     * A state that is still there from an earlier install is used again.
     */
    public static function install(\Perlypay $module): bool
    {
        $translator = $module->getTranslator();
        $states = [
            Settings::OS_AWAITING => ['#4169E1', static fn (?string $locale): string => $translator->trans('Awaiting crypto confirmation', [], 'Modules.Perlypay.Admin', $locale)],
            Settings::OS_HELD => ['#E07B00', static fn (?string $locale): string => $translator->trans('Crypto payment held', [], 'Modules.Perlypay.Admin', $locale)],
        ];
        foreach ($states as $key => [$color, $name]) {
            $existing = new \OrderState((int) \Configuration::get($key));
            if (\Validate::isLoadedObject($existing) && !$existing->deleted) {
                continue;
            }
            $state = new \OrderState();
            $state->name = [];
            foreach (\Language::getLanguages(false) as $language) {
                $state->name[(int) $language['id_lang']] = mb_substr($name(isset($language['locale']) ? (string) $language['locale'] : null), 0, 64);
            }
            $state->color = $color;
            $state->module_name = $module->name;
            $state->send_email = false;
            $state->invoice = false;
            $state->logable = false;
            $state->paid = false;
            $state->shipped = false;
            $state->delivery = false;
            $state->hidden = false;
            $state->unremovable = true;
            if (!$state->add()) {
                return false;
            }
            \Configuration::updateGlobalValue($key, (int) $state->id);
        }

        return true;
    }
}
