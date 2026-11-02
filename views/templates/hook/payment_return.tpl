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
<section class="perlypay-return">
  {if $perlypay_state == 'paid'}
    <p>{l s='Your crypto payment is confirmed. Thank you.' d='Modules.Perlypay.Shop'}</p>
  {elseif $perlypay_state == 'held'}
    <p>{l s='Your payment arrived and the shop is checking it. You will hear from them by email.' d='Modules.Perlypay.Shop'}</p>
  {elseif $perlypay_state == 'awaiting'}
    <p>{l s='Your payment is on its way. Your order is confirmed as soon as the network confirms it; you will get an email.' d='Modules.Perlypay.Shop'}</p>
  {else}
    <p>{l s='Questions about this payment? Contact the shop and give your order reference.' d='Modules.Perlypay.Shop'}</p>
  {/if}
  <p>{l s='Order reference: %s' sprintf=[$perlypay_reference] d='Modules.Perlypay.Shop'}</p>
</section>
