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
use PerlyPay\PrestaShop\Shop\Services;
use PerlyPay\PrestaShop\Vendor\Perly\Pay\Exception\SignatureVerificationException;
use PerlyPay\PrestaShop\Vendor\Perly\Pay\Webhook as Signature;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * POST /module/perlypay/webhook: where Perly Pay sends events.
 *
 * Public by design (Perly Pay's server calls it, not a visitor): what
 * makes a request count is its Perly-Signature, an HMAC-SHA256 of the
 * timestamp and the raw body under the endpoint's secret, checked by the
 * SDK in constant time, and refused when more than five minutes from now.
 * A request that fails the check changes nothing.
 *
 * Answers: 200 for every verified event (applied, a duplicate, or not
 * ours), 400 for a bad signature, 405 for anything but POST, 503 until a
 * key and secret are saved, 500 when applying failed or the event's
 * invoice (or refund) could not be retrieved from Perly Pay (the secret
 * alone is not proof of payment, so an event is never applied unchecked).
 * Perly Pay retries anything but 2xx for 72 hours.
 *
 * @property Perlypay $module
 */
class PerlypayWebhookModuleFrontController extends ModuleFrontController
{
    /** @var bool */
    public $ssl = true;

    /** @var bool */
    public $display_header = false;

    /** @var bool */
    public $display_footer = false;

    public function postProcess()
    {
        $this->module->loadClasses();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->respond(405, ['error' => 'method_not_allowed']);
        }
        $secret = Settings::webhookSecret();
        if ($secret === '') {
            $this->respond(503, ['error' => 'not_configured']);
        }
        $payload = file_get_contents('php://input');
        $header = $_SERVER['HTTP_PERLY_SIGNATURE'] ?? null;
        try {
            $event = Signature::verify(is_string($payload) ? $payload : '', is_string($header) ? $header : null, $secret);
        } catch (SignatureVerificationException $error) {
            Log::info('webhook refused', ['code' => $error->getErrorCode()]);
            $this->respond(400, ['error' => $error->getErrorCode()]);
        }

        Configuration::updateValue(Settings::LAST_WEBHOOK, (string) time());
        $type = is_string($event['type'] ?? null) ? $event['type'] : '';
        $id = is_string($event['id'] ?? null) ? $event['id'] : '';
        if ($type === 'webhook.test') {
            Log::info('webhook test received', ['event' => $id]);
            $this->respond(200, ['received' => true]);
        }
        $client = Settings::client();
        if ($client === null) {
            $this->respond(503, ['error' => 'no_api_key']);
        }
        try {
            $outcome = Services::processor($client, $this->module)->handle($event);
        } catch (Throwable $error) {
            Log::error('webhook failed', ['event' => $id, 'type' => $type, 'error' => get_class($error), 'message' => mb_substr($error->getMessage(), 0, 300)]);
            $this->respond(500, ['error' => 'not_applied']);
        }
        Log::info('webhook', ['event' => $id, 'type' => $type, 'result' => $outcome['result'], 'reason' => $outcome['reason'], 'cart' => $outcome['cart'] ?? null]);
        $this->respond(200, ['received' => true, 'result' => $outcome['result']]);
    }

    /** @param array<string, mixed> $body */
    private function respond(int $status, array $body): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        $this->ajaxRender((string) json_encode($body));
        exit;
    }
}
