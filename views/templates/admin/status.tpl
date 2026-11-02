{**
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
 *}
<div class="panel" id="perlypay-status">
  <div class="panel-heading"><i class="icon-link"></i> {l s='Connection' d='Modules.Perlypay.Admin'}</div>
  <p>
    {if $perlypay_mode == 'live'}
      {l s='Live mode: real money, key %s.' sprintf=[$perlypay_key_hint] d='Modules.Perlypay.Admin'}
    {elseif $perlypay_mode == 'test'}
      {l s='Test mode: test networks only, key %s.' sprintf=[$perlypay_key_hint] d='Modules.Perlypay.Admin'}
    {else}
      {l s='No secret key yet. Sign up at pay.perly.io, connect your wallet\'s public keys, and paste a secret key below.' d='Modules.Perlypay.Admin'}
    {/if}
  </p>
  <p>
    {l s='Webhook address (Perly Pay sends payments here):' d='Modules.Perlypay.Admin'}
    <code>{$perlypay_webhook_url|escape:'htmlall':'UTF-8'}</code>
  </p>
  <p>
    {if $perlypay_last_webhook}
      {l s='Last webhook received: %s.' sprintf=[$perlypay_last_webhook] d='Modules.Perlypay.Admin'}
    {else}
      {l s='No webhook received yet.' d='Modules.Perlypay.Admin'}
    {/if}
  </p>
  {if $perlypay_other_currencies}
    <p class="alert alert-warning">{l s='Perly Pay bills in USD or EUR. Buyers paying in %s do not see it.' sprintf=[$perlypay_other_currencies] d='Modules.Perlypay.Admin'}</p>
  {/if}
  {if !$perlypay_https}
    <p class="alert alert-info">{l s='This shop does not use HTTPS. Perly Pay sends webhooks only to https:// addresses.' d='Modules.Perlypay.Admin'}</p>
  {/if}
  <form method="post" action="{$perlypay_action|escape:'htmlall':'UTF-8'}" class="form-inline">
    <button type="submit" name="submitPerlypayTest" class="btn btn-default">{l s='Test connection' d='Modules.Perlypay.Admin'}</button>
    <button type="submit" name="submitPerlypayWebhook" class="btn btn-default">{l s='Set up webhook' d='Modules.Perlypay.Admin'}</button>
  </form>
  <p><a href="{$perlypay_guide|escape:'htmlall':'UTF-8'}" target="_blank" rel="noopener">{l s='Read the guide (PDF)' d='Modules.Perlypay.Admin'}</a></p>
  <p class="help-block">{l s='"Set up webhook" adds this shop\'s address in Perly Pay with the invoice and refund events and saves its secret here. If it is there already, it gets a new secret.' d='Modules.Perlypay.Admin'}</p>
</div>
