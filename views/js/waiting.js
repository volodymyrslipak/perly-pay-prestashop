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
 * The page that waits for a payment to reach the network: it looks again
 * every ten seconds for half an hour, then leaves the buttons to the buyer.
 */
(function () {
  var key = 'perlypayWaitingSince' + window.location.search;
  var since = Date.now();
  try {
    since = Number(window.sessionStorage.getItem(key)) || since;
    window.sessionStorage.setItem(key, String(since));
  } catch {
    // Private windows may refuse storage; the page then simply keeps looking.
  }
  if (Date.now() - since < 30 * 60 * 1000) {
    window.setTimeout(function () {
      window.location.reload();
    }, 10000);
  }
})();
