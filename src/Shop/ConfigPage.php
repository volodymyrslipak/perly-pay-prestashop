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

use PerlyPay\PrestaShop\Log;
use PerlyPay\PrestaShop\Requests;
use PerlyPay\PrestaShop\Settings;
use PerlyPay\PrestaShop\Support\Keys;
use PerlyPay\PrestaShop\Support\Money;
use PerlyPay\PrestaShop\Vendor\Perly\Pay\Exception\AuthenticationException;
use PerlyPay\PrestaShop\Vendor\Perly\Pay\Exception\ConnectionException;
use PerlyPay\PrestaShop\Vendor\Perly\Pay\Exception\PerlyPayException;
use PerlyPay\PrestaShop\Vendor\Perly\Pay\Exception\PermissionException;
use PerlyPay\PrestaShop\Vendor\Perly\Pay\PerlyPay;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Modules → Perly Pay → Configure: the settings (PrestaShop's HelperForm)
 * and a panel with the connection, the webhook's address and two buttons,
 * "Test connection" and "Set up webhook". The page is AdminModules', so
 * PrestaShop has checked the employee's rights and the page's token before
 * any of this runs.
 */
final class ConfigPage
{
    private Translator $words;

    public function __construct(private readonly \Perlypay $module)
    {
        $this->words = new Translator();
    }

    public function render(): string
    {
        $notice = '';
        if (\Tools::isSubmit('submitPerlypaySettings')) {
            $notice = $this->save();
        } elseif (\Tools::isSubmit('submitPerlypayTest')) {
            $notice = $this->testConnection();
        } elseif (\Tools::isSubmit('submitPerlypayWebhook')) {
            $notice = $this->setUpWebhook();
        }

        return $notice . $this->status() . $this->form();
    }

    private function save(): string
    {
        $key = trim((string) \Tools::getValue(Settings::API_KEY));
        $secret = trim((string) \Tools::getValue(Settings::SECRET));
        $mode = (string) \Tools::getValue(Settings::MODE);
        if ($key !== '' && Keys::mode($key) === null) {
            return $this->error($this->words->admin('That is not a Perly Pay secret key: it starts with sk_test_ or sk_live_. Copy it again from Developers → API keys.'));
        }
        if ($secret !== '' && !Keys::isWebhookSecret($secret)) {
            return $this->error($this->words->admin('That is not a webhook signing secret: it starts with whsec_. Copy it from Developers → Webhooks, or press "Set up webhook".'));
        }
        if ($key !== '') {
            \Configuration::updateValue(Settings::API_KEY, $key);
        }
        if ($secret !== '') {
            \Configuration::updateValue(Settings::SECRET, $secret);
        }
        \Configuration::updateValue(Settings::MODE, $mode === 'overlay' ? 'overlay' : 'redirect');
        \Configuration::updateValue(Settings::REFUNDS, \Tools::getValue(Settings::REFUNDS) ? '1' : '0');
        \Configuration::updateValue(Settings::DEBUG, \Tools::getValue(Settings::DEBUG) ? '1' : '0');

        return $this->done($this->words->admin('Settings saved.'));
    }

    private function testConnection(): string
    {
        $client = Settings::client(null, 0);
        if ($client === null) {
            return $this->error($this->words->admin('Save a secret key first: sk_test_… or sk_live_….'));
        }
        try {
            $client->invoices->list(Requests::CONNECTION_CHECK, ['max_retries' => 0]);
            $found = $this->endpoint($client);
        } catch (PerlyPayException $error) {
            return $this->error($this->explain($error));
        }
        $mode = $client->mode === 'live' ? $this->words->admin('Connected in live mode.') : $this->words->admin('Connected in test mode.');
        if ($found === null) {
            $hook = $this->words->admin('This shop\'s webhook is not in Perly Pay yet: press "Set up webhook".');
        } elseif (($found['status'] ?? '') !== 'enabled') {
            $hook = $this->words->admin('This shop\'s webhook is switched off in Perly Pay. Switch it on in Developers → Webhooks.');
        } elseif (Settings::webhookSecret() === '') {
            $hook = $this->words->admin('The webhook is in Perly Pay, but its secret is not saved here. Paste it, or press "Set up webhook" for a new one.');
        } else {
            $hook = $this->words->admin('The webhook is set up.');
        }

        return $this->done($mode . ' ' . $hook);
    }

    private function setUpWebhook(): string
    {
        $client = Settings::client(null, 0);
        if ($client === null) {
            return $this->error($this->words->admin('Save a secret key first: sk_test_… or sk_live_….'));
        }
        try {
            $found = $this->endpoint($client);
            $made = $found === null
                ? $client->webhookEndpoints->create(Requests::webhookEndpoint(self::webhookUrl($this->module), (string) \Configuration::get('PS_SHOP_NAME'), $this->words))
                : $client->webhookEndpoints->rollSecret((string) $found['id']);
        } catch (PerlyPayException $error) {
            return $this->error($this->explain($error));
        }
        $secret = is_string($made['secret'] ?? null) ? $made['secret'] : '';
        if (!Keys::isWebhookSecret($secret)) {
            return $this->error($this->words->admin('Perly Pay did not send a secret back. Add the webhook in Developers → Webhooks and paste its secret here.'));
        }
        \Configuration::updateValue(Settings::SECRET, $secret);
        Log::info('webhook set up', ['endpoint' => is_string($made['id'] ?? null) ? $made['id'] : '']);

        return $this->done($found === null
            ? $this->words->admin('Webhook added in Perly Pay and its secret saved here.')
            : $this->words->admin('The webhook was already in Perly Pay: it has a new secret, saved here.'));
    }

    /** A red notice, its text escaped: some of it is Perly Pay's own words. */
    private function error(string $text): string
    {
        return $this->module->displayError(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    }

    private function done(string $text): string
    {
        return $this->module->displayConfirmation(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    }

    /** This settings page, as PrestaShop links it. */
    private function pageUrl(): string
    {
        return \Context::getContext()->link->getAdminLink('AdminModules', true, [], ['configure' => $this->module->name, 'tab_module' => $this->module->tab, 'module_name' => $this->module->name]);
    }

    /** Where Perly Pay sends this shop's events. */
    public static function webhookUrl(\Perlypay $module): string
    {
        return \Context::getContext()->link->getModuleLink($module->name, 'webhook', [], true);
    }

    /**
     * This shop's endpoint in Perly Pay, if one is there.
     *
     * @return array<string, mixed>|null
     */
    private function endpoint(PerlyPay $client): ?array
    {
        $url = self::webhookUrl($this->module);
        foreach ($client->webhookEndpoints->listAll(Requests::ENDPOINTS_PAGE) as $endpoint) {
            if (is_array($endpoint) && ($endpoint['url'] ?? '') === $url) {
                return $endpoint;
            }
        }

        return null;
    }

    private function explain(PerlyPayException $error): string
    {
        Log::error('admin call failed', ['code' => $error->getErrorCode(), 'request' => $error->getRequestId()]);
        if ($error instanceof AuthenticationException) {
            return $this->words->admin('Perly Pay refused this key. Copy it again from Developers → API keys.');
        }
        if ($error instanceof PermissionException) {
            return $this->words->admin('This key may not do that. Use a secret key with full access.');
        }
        if ($error instanceof ConnectionException) {
            return $this->words->admin('Could not reach Perly Pay. Try again in a minute.');
        }

        return $this->words->admin('Perly Pay: %reason%', ['%reason%' => $error->getMessage()]);
    }

    /** The panel above the form: connection, webhook, the two buttons and what blocks the payment option. */
    private function status(): string
    {
        $context = \Context::getContext();
        $mode = Settings::mode();
        $last = (int) Settings::get(Settings::LAST_WEBHOOK, '0');
        $currencies = [];
        foreach (\Currency::getCurrencies(false, true, true) as $currency) {
            $iso = strtoupper((string) (is_array($currency) ? $currency['iso_code'] : $currency->iso_code));
            if (!Money::supported($iso)) {
                $currencies[] = $iso;
            }
        }
        $context->smarty->assign([
            'perlypay_action' => $this->pageUrl(),
            'perlypay_mode' => $mode ?? '',
            'perlypay_key_hint' => Keys::hint(Settings::apiKey()),
            'perlypay_secret_hint' => Keys::hint(Settings::webhookSecret()),
            'perlypay_webhook_url' => self::webhookUrl($this->module),
            'perlypay_last_webhook' => $last > 0 ? \Tools::displayDate(date('Y-m-d H:i:s', $last), true) : '',
            'perlypay_other_currencies' => implode(', ', array_unique($currencies)),
            'perlypay_https' => \Configuration::get('PS_SSL_ENABLED') ? '1' : '',
            'perlypay_guide' => $this->module->getPathUri() . 'docs/readme_en.pdf',
        ]);

        return $this->module->display($this->module->getLocalPath() . 'perlypay.php', 'views/templates/admin/status.tpl');
    }

    private function form(): string
    {
        $helper = new \HelperForm();
        $helper->module = $this->module;
        $helper->name_controller = $this->module->name;
        // The form posts back to this page as PrestaShop links it (in 9, its Symfony route with its own token).
        $helper->currentIndex = $this->pageUrl();
        $helper->token = \Tools::getAdminTokenLite('AdminModules');
        $helper->default_form_language = (int) \Configuration::get('PS_LANG_DEFAULT');
        $helper->show_toolbar = false;
        $helper->submit_action = 'submitPerlypaySettings';
        $helper->tpl_vars = [
            'fields_value' => [
                Settings::API_KEY => '',
                Settings::SECRET => '',
                Settings::MODE => Settings::checkoutMode(),
                Settings::REFUNDS => Settings::refunds() ? 1 : 0,
                Settings::DEBUG => Settings::debug() ? 1 : 0,
            ],
        ];
        $switch = fn (string $name, string $label, string $desc): array => [
            'type' => 'switch',
            'name' => $name,
            'label' => $label,
            'desc' => $desc,
            'is_bool' => true,
            'values' => [
                ['id' => $name . '_on', 'value' => 1, 'label' => $this->words->admin('Yes')],
                ['id' => $name . '_off', 'value' => 0, 'label' => $this->words->admin('No')],
            ],
        ];

        return $helper->generateForm([[
            'form' => [
                'legend' => ['title' => $this->words->admin('Settings'), 'icon' => 'icon-cogs'],
                'input' => [
                    [
                        'type' => 'password',
                        'name' => Settings::API_KEY,
                        'label' => $this->words->admin('Secret key'),
                        'desc' => Settings::apiKey() === ''
                            ? $this->words->admin('Perly Pay → Developers → API keys. sk_test_… to try it, sk_live_… for real money.')
                            : $this->words->admin('Saved: %key%. Leave empty to keep it.', ['%key%' => Keys::hint(Settings::apiKey())]),
                    ],
                    [
                        'type' => 'password',
                        'name' => Settings::SECRET,
                        'label' => $this->words->admin('Webhook signing secret'),
                        'desc' => Settings::webhookSecret() === ''
                            ? $this->words->admin('Press "Set up webhook" above, or paste the whsec_… secret of this shop\'s webhook.')
                            : $this->words->admin('Saved: %key%. Leave empty to keep it.', ['%key%' => Keys::hint(Settings::webhookSecret())]),
                    ],
                    [
                        'type' => 'select',
                        'name' => Settings::MODE,
                        'label' => $this->words->admin('Payment page'),
                        'desc' => $this->words->admin('Overlay needs this shop\'s address in Perly Pay → Settings → "Sites that may embed the checkout". Phones always get the page itself.'),
                        'options' => [
                            'query' => [
                                ['id' => 'redirect', 'name' => $this->words->admin('Redirect: the payment page on pay.perly.io')],
                                ['id' => 'overlay', 'name' => $this->words->admin('Overlay: the payment page over the shop')],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    $switch(Settings::REFUNDS, $this->words->admin('Open refunds in Perly Pay'), $this->words->admin('A credit slip on an order paid with Perly Pay opens a refund there. The customer gets an email to give an address; you send the coins from your own wallet.')),
                    $switch(Settings::DEBUG, $this->words->admin('Debug log'), $this->words->admin('Ids and outcomes to Advanced Parameters → Logs. Never keys, secrets or customers\' details.')),
                ],
                'submit' => ['title' => $this->words->admin('Save')],
            ],
        ]]);
    }
}
