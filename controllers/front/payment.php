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
 * The payment page over the shop (the "Overlay" setting): a page of the
 * shop with the buyer's invoice, where pay.js, loaded from Perly Pay with
 * its Subresource Integrity hash pinned, opens Perly Pay's checkout over
 * it. Once paid, pay.js sends the buyer to the confirmation controller.
 * Without pay.js, the button opens the payment page itself.
 *
 * @property Perlypay $module
 */
class PerlypayPaymentModuleFrontController extends ModuleFrontController
{
    /** @var bool */
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();
        $cart = $this->context->cart;
        if (!Validate::isLoadedObject($cart) || !$cart->id_customer || !$this->module->active || !$this->module->ready()) {
            Tools::redirect($this->context->link->getPageLink('order', true));
        }
        $this->module->loadClasses();
        $checkout = new Checkout($this->module);
        if ((int) Order::getIdByCartId((int) $cart->id) > 0) {
            Tools::redirect($checkout->returnUrl($cart));
        }
        $words = Translator::forLanguage((int) $cart->id_lang);
        $client = Settings::client();
        try {
            if ($client === null) {
                throw new InvalidArgumentException($words->shop('Crypto payment is not set up yet.'));
            }
            $invoice = $checkout->invoiceFor($cart, $client);
        } catch (InvalidArgumentException $error) {
            $this->back($error->getMessage());
        } catch (PerlyPayException $error) {
            Log::error('invoice not made', ['cart' => (int) $cart->id, 'code' => $error->getErrorCode(), 'request' => $error->getRequestId()]);
            $this->back($words->shop('Crypto payment is not available right now. Please try again, or choose another way to pay.'));
        }

        $url = (string) $invoice['url'];
        $base = self::origin($url);
        $pin = require _PS_MODULE_DIR_ . $this->module->name . '/src/pay-script.php';
        Media::addJsDef(['perlypayPayment' => [
            'invoice' => (string) $invoice['id'],
            'invoiceUrl' => $url,
            'baseUrl' => $base,
            'paidUrl' => $checkout->returnUrl($cart),
        ]]);
        $this->registerJavascript('perlypay-payment', 'modules/' . $this->module->name . '/views/js/payment.js', ['position' => 'bottom', 'priority' => 200]);
        $this->context->smarty->assign([
            'perlypay_invoice_url' => $url,
            'perlypay_pay_js' => $base === null ? '' : $base . $pin['path'],
            'perlypay_integrity' => $pin['integrity'],
            'perlypay_back_url' => $this->context->link->getPageLink('order', true),
        ]);
        $this->setTemplate('module:perlypay/views/templates/front/payment.tpl');
    }

    /** Back to the checkout with one sentence for the buyer. */
    private function back(string $message): never
    {
        $this->errors[] = $message;
        $this->redirectWithNotifications($this->context->link->getPageLink('order', true));
        exit;
    }

    /** "https://pay.perly.io" from the invoice's address, or null. */
    private static function origin(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || !in_array($parts['scheme'], ['https', 'http'], true)) {
            return null;
        }

        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }
}
