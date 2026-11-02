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

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Perly Pay for PrestaShop: bitcoin, USDC and USDT, paid straight to the
 * merchant's own wallet. Perly Pay never holds the money; it charges 2.5¢
 * a payment, never more than 0.5% of it, billed after the month.
 *
 *   perlypay.php          this file: install, the settings page and the hooks
 *   controllers/front/    validation (the buyer pressed Pay), payment (the payment page over the shop),
 *                         confirmation (back from paying), webhook (Perly Pay's signed events)
 *   src/                  the rules (Sync/), PrestaShop behind them (Shop/), the autoloader
 *   vendor/perly-pay-sdk  Perly Pay's PHP SDK, its namespace prefixed at build time
 *   translations/         the module's words in Ukrainian (English is the source)
 *
 * This file is read by PrestaShop's module list on any PHP, so it keeps to
 * what old PHP can parse; the rest needs PHP 8.1, which install() checks.
 */
class Perlypay extends PaymentModule
{
    public const VERSION = '1.0.0';

    public function __construct()
    {
        $this->name = 'perlypay';
        $this->tab = 'payments_gateways';
        $this->version = self::VERSION;
        $this->author = 'Perly';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => '9.99.99'];
        $this->controllers = ['validation', 'payment', 'confirmation', 'webhook'];
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->bootstrap = true;
        $this->module_key = '';

        parent::__construct();

        $this->displayName = $this->trans('Perly Pay', [], 'Modules.Perlypay.Admin');
        $this->description = $this->trans('Take bitcoin, USDC and USDT, paid straight to your own wallet. Perly Pay never holds the money.', [], 'Modules.Perlypay.Admin');
        $this->confirmUninstall = $this->trans('Remove Perly Pay? Its settings go; orders keep their payments and messages.', [], 'Modules.Perlypay.Admin');
    }

    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    public function install()
    {
        if (PHP_VERSION_ID < 80100 || !extension_loaded('curl')) {
            $this->_errors[] = $this->trans('Perly Pay needs PHP 8.1 or later with the curl extension.', [], 'Modules.Perlypay.Admin');

            return false;
        }
        $this->loadClasses();
        foreach (PerlyPay\PrestaShop\Shop\Tables::schema() as $sql) {
            if (!Db::getInstance()->execute($sql)) {
                return false;
            }
        }

        return parent::install()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('displayPaymentReturn')
            && $this->registerHook('actionOrderSlipAdd')
            && PerlyPay\PrestaShop\Shop\OrderStates::install($this)
            && Configuration::updateValue(PerlyPay\PrestaShop\Settings::MODE, 'redirect')
            && Configuration::updateValue(PerlyPay\PrestaShop\Settings::REFUNDS, '1');
    }

    /** The settings go; the tables and the two order states stay, as orders point at them. */
    public function uninstall()
    {
        $this->loadClasses();
        foreach (PerlyPay\PrestaShop\Settings::REMOVED_ON_UNINSTALL as $key) {
            Configuration::deleteByName($key);
        }

        return parent::uninstall();
    }

    public function getContent()
    {
        $this->loadClasses();

        return (new PerlyPay\PrestaShop\Shop\ConfigPage($this))->render();
    }

    /**
     * Perly Pay among the payment options, for a cart in USD or EUR, once a secret key is saved.
     *
     * @param array<string, mixed> $params
     *
     * @return list<PaymentOption>
     */
    public function hookPaymentOptions($params)
    {
        $cart = isset($params['cart']) ? $params['cart'] : null;
        if (!$this->active || !$cart instanceof Cart || !$this->ready() || !$this->checkCurrency($cart)) {
            return [];
        }
        $currency = new Currency((int) $cart->id_currency);
        if (!PerlyPay\PrestaShop\Support\Money::supported((string) $currency->iso_code)) {
            return [];
        }
        $option = new PaymentOption();
        $option->setModuleName($this->name)
            ->setCallToActionText($this->trans('Pay with crypto: bitcoin, USDC or USDT', [], 'Modules.Perlypay.Shop'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', [], true))
            ->setAdditionalInformation($this->fetch('module:perlypay/views/templates/hook/payment_option.tpl'));

        return [$option];
    }

    /**
     * The order confirmation page of an order Perly Pay paid: what happens next, by the order's state.
     *
     * @param array<string, mixed> $params
     */
    public function hookDisplayPaymentReturn($params)
    {
        $order = isset($params['order']) ? $params['order'] : null;
        if (!$this->active || !$order instanceof Order || $order->module !== $this->name) {
            return '';
        }
        $this->loadClasses();
        $this->context->smarty->assign([
            'perlypay_state' => PerlyPay\PrestaShop\Shop\OrderStates::of($order),
            'perlypay_reference' => (string) $order->reference,
        ]);

        return $this->fetch('module:perlypay/views/templates/hook/payment_return.tpl');
    }

    /**
     * A credit slip was made on an order: a refund in Perly Pay for an order it paid (Shop\Refunds).
     *
     * @param array<string, mixed> $params
     */
    public function hookActionOrderSlipAdd($params)
    {
        if (!$this->active || !$this->ready()) {
            return;
        }
        try {
            (new PerlyPay\PrestaShop\Shop\Refunds($this))->onCreditSlip($params);
        } catch (Throwable $error) {
            // A refund in Perly Pay never stops the credit slip itself.
            PerlyPay\PrestaShop\Log::error('refund failed', ['error' => get_class($error), 'message' => mb_substr($error->getMessage(), 0, 300)]);
        }
    }

    /** Whether payments can be taken: PHP 8.1 and a valid secret key. */
    public function ready()
    {
        if (PHP_VERSION_ID < 80100) {
            return false;
        }
        $this->loadClasses();

        return PerlyPay\PrestaShop\Settings::mode() !== null;
    }

    /** Whether the cart's currency is one this module is allowed for (Payment → Preferences). */
    public function checkCurrency($cart)
    {
        $currency = new Currency((int) $cart->id_currency);
        $allowed = $this->getCurrency((int) $cart->id_currency);
        if (is_array($allowed)) {
            foreach ($allowed as $row) {
                if ((int) $currency->id === (int) $row['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    public function loadClasses()
    {
        require_once __DIR__ . '/src/autoload.php';
    }
}
