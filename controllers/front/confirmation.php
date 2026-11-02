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

use PerlyPay\PrestaShop\Log;
use PerlyPay\PrestaShop\Settings;
use PerlyPay\PrestaShop\Shop\Checkout;
use PerlyPay\PrestaShop\Shop\Services;
use PerlyPay\PrestaShop\Shop\Tables;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Where the buyer comes back from paying (the invoice's return_url, and
 * pay.js's paidUrl). The link carries the cart's id and a token only this
 * shop can make for it (Checkout::token). If the order is not there yet,
 * Perly Pay is asked for the invoice, in case the webhook is late, and the
 * same rules apply (Sync\EventProcessor::sync). With an order, the buyer
 * goes on to PrestaShop's order confirmation page; without one (nothing
 * has reached the network yet) this page waits and looks again.
 *
 * @property Perlypay $module
 */
class PerlypayConfirmationModuleFrontController extends ModuleFrontController
{
    /** @var bool */
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();
        $this->module->loadClasses();
        $cart = new Cart((int) Tools::getValue('id_cart'));
        $token = (string) Tools::getValue('token');
        if (!Validate::isLoadedObject($cart) || $token === '' || !hash_equals(Checkout::token($cart), $token)) {
            Tools::redirect($this->context->link->getPageLink('index', true));
        }
        $rows = (new Tables())->invoicesOf((int) $cart->id);
        $current = $rows === [] ? null : $rows[count($rows) - 1];

        $orderId = (int) Order::getIdByCartId((int) $cart->id);
        $client = Settings::client(null, 1);
        if ($orderId === 0 && $current !== null && $client !== null) {
            try {
                Services::processor($client, $this->module)->sync($client->invoices->retrieve($current['invoice_id'], ['max_retries' => 1]));
            } catch (Throwable $error) {
                Log::info('return sync skipped', ['cart' => (int) $cart->id, 'error' => get_class($error)]);
            }
            $orderId = (int) Order::getIdByCartId((int) $cart->id);
        }
        if ($orderId > 0) {
            $customer = new Customer((int) $cart->id_customer);
            Tools::redirect($this->context->link->getPageLink('order-confirmation', true, null, [
                'id_cart' => (int) $cart->id,
                'id_module' => (int) $this->module->id,
                'id_order' => $orderId,
                'key' => $customer->secure_key,
            ]));
        }

        $this->registerJavascript('perlypay-waiting', 'modules/' . $this->module->name . '/views/js/waiting.js', ['position' => 'bottom', 'priority' => 200]);
        $this->context->smarty->assign([
            'perlypay_invoice_url' => $current === null ? '' : $current['url'],
            'perlypay_back_url' => $this->context->link->getPageLink('order', true),
        ]);
        $this->setTemplate('module:perlypay/views/templates/front/waiting.tpl');
    }
}
