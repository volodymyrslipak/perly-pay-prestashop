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
{extends file='page.tpl'}

{block name='page_title'}
  {l s='Pay with crypto' d='Modules.Perlypay.Shop'}
{/block}

{block name='page_content'}
  <section class="perlypay-payment">
    <p>{l s='Pay from any wallet. The money goes straight to the shop.' d='Modules.Perlypay.Shop'}</p>
    <p>
      <button type="button" class="btn btn-primary" id="perlypay-open">{l s='Pay with crypto' d='Modules.Perlypay.Shop'}</button>
      <a href="{$perlypay_invoice_url|escape:'htmlall':'UTF-8'}" id="perlypay-link">{l s='Open the payment page' d='Modules.Perlypay.Shop'}</a>
    </p>
    <p>{l s='Your order is placed as soon as your payment reaches the network.' d='Modules.Perlypay.Shop'}</p>
    <p><a href="{$perlypay_back_url|escape:'htmlall':'UTF-8'}">{l s='Choose another way to pay' d='Modules.Perlypay.Shop'}</a></p>
  </section>
  {if $perlypay_pay_js}
    <script src="{$perlypay_pay_js|escape:'htmlall':'UTF-8'}" integrity="{$perlypay_integrity|escape:'htmlall':'UTF-8'}" crossorigin="anonymous" defer></script>
  {/if}
{/block}
