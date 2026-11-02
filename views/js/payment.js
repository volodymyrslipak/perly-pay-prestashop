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

/*
 * The payment page over the shop: opens Perly Pay's checkout over it
 * (pay.js, loaded from Perly Pay with its integrity pinned), and again on
 * the button. Once paid, pay.js sends the buyer to the confirmation link.
 * If pay.js did not load, the button opens the payment page itself.
 */
(function () {
  var data = window.perlypayPayment;
  if (!data || !data.invoice) {
    return;
  }
  function open() {
    if (!window.PerlyPay || typeof window.PerlyPay.open !== 'function') {
      window.location.assign(data.invoiceUrl);
      return;
    }
    window.PerlyPay.open({
      invoice: data.invoice,
      baseUrl: data.baseUrl,
      paidUrl: data.paidUrl,
      mode: 'auto'
    });
  }
  var button = document.getElementById('perlypay-open');
  if (button) {
    button.addEventListener('click', function (event) {
      event.preventDefault();
      open();
    });
  }
  if (document.readyState === 'complete') {
    open();
  } else {
    window.addEventListener('load', open);
  }
})();
