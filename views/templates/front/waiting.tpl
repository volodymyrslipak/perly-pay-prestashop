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
  {l s='Waiting for your payment' d='Modules.Perlypay.Shop'}
{/block}

{block name='page_content'}
  <section class="perlypay-waiting">
    <p>{l s='Your payment has not reached the network yet. This page looks again every few seconds; your order is placed as soon as it arrives.' d='Modules.Perlypay.Shop'}</p>
    {if $perlypay_invoice_url}
      <p><a class="btn btn-primary" href="{$perlypay_invoice_url|escape:'htmlall':'UTF-8'}">{l s='Open the payment page' d='Modules.Perlypay.Shop'}</a></p>
    {/if}
    <p><a href="{$perlypay_back_url|escape:'htmlall':'UTF-8'}">{l s='Choose another way to pay' d='Modules.Perlypay.Shop'}</a></p>
  </section>
{/block}
