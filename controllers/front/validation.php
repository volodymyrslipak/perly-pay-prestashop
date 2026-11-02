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
use PerlyPay\PrestaShop\Shop\Translator;
use PerlyPay\PrestaShop\Vendor\Perly\Pay\Exception\PerlyPayException;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * The buyer chose Perly Pay and pressed "Place order". No order is made
 * here (PrestaShop's rule: not before the customer has been through the
 * payment service): the cart gets its Perly Pay invoice, and the buyer
 * goes to pay it, on pay.perly.io (redirect) or over the shop (the payment
 * controller, overlay). The order is made once money is on its way
 * (Sync\EventProcessor, from the webhook or the buyer's return).
 *
 * @property Perlypay $module
 */
class PerlypayValidationModuleFrontController extends ModuleFrontController
{
    /** @var bool */
    public $ssl = true;

    public function postProcess()
    {
        $cart = $this->context->cart;
        if (!Validate::isLoadedObject($cart) || !$cart->id_customer || !$cart->id_address_delivery || !$cart->id_address_invoice || !$this->module->active) {
            Tools::redirect($this->context->link->getPageLink('order', true, null, ['step' => 1]));
        }
        // Still offered for this cart: the customer may have changed the address or currency just before.
        $offered = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] === $this->module->name) {
                $offered = true;
                break;
            }
        }
        $customer = new Customer((int) $cart->id_customer);
        if (!$offered || !Validate::isLoadedObject($customer) || !$this->module->ready() || !$this->module->checkCurrency($cart)) {
            $this->back($this->trans('This payment method is not available.', [], 'Modules.Perlypay.Shop'));
        }
        $this->module->loadClasses();

        $orderId = (int) Order::getIdByCartId((int) $cart->id);
        if ($orderId > 0) {
            Tools::redirect((new Checkout($this->module))->returnUrl($cart));
        }
        if (Settings::checkoutMode() === 'overlay') {
            Tools::redirect($this->context->link->getModuleLink($this->module->name, 'payment', [], true));
        }

        $client = Settings::client();
        $words = Translator::forLanguage((int) $cart->id_lang);
        if ($client === null) {
            $this->back($words->shop('Crypto payment is not set up yet.'));
        }
        try {
            $invoice = (new Checkout($this->module))->invoiceFor($cart, $client);
        } catch (InvalidArgumentException $error) {
            $this->back($error->getMessage());
        } catch (PerlyPayException $error) {
            Log::error('invoice not made', ['cart' => (int) $cart->id, 'code' => $error->getErrorCode(), 'request' => $error->getRequestId()]);
            $this->back($words->shop('Crypto payment is not available right now. Please try again, or choose another way to pay.'));
        }
        Tools::redirect((string) $invoice['url']);
    }

    /** Back to the checkout with one sentence for the buyer. */
    private function back(string $message): never
    {
        $this->errors[] = $message;
        $this->redirectWithNotifications($this->context->link->getPageLink('order', true));
        exit;
    }
}
