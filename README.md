# Perly Pay for PrestaShop

Take bitcoin, USDC and USDT in PrestaShop, paid straight to your own wallet.
Perly Pay never holds the money and takes nothing from a payment: it costs
2.5¢ a payment, never more than 0.5% of it, billed after the month, and a
month of up to 21 payments costs $0.

PrestaShop 8.0 to 9.x, PHP 8.1+ with curl. Run end to end on PrestaShop 9.2.0
(PHP 8.5) and 8.2.8 (PHP 8.1), the official Docker images, in debug mode.
Nothing to install with Composer: Perly Pay's PHP SDK is inside, under its
own namespace. The module's technical name is `perlypay` (the folder, the
zip, the class `Perlypay`). The PrestaShop Addons listing and the owner's
steps are in [marketplace/LISTING.md](marketplace/LISTING.md).

## Install

1. Modules → Module Manager → **Upload a module**, and drop `perlypay.zip`.
2. **Configure**.

## Configure

| Setting | What to put |
| --- | --- |
| Secret key | Perly Pay → Developers → API keys. `sk_test_…` for test mode, `sk_live_…` for real money. Full access, or Restricted with **Webhook endpoints** ticked: **Test connection** and **Set up webhook** read and add this shop's webhook. Save, then press **Test connection**. |
| Webhook | Press **Set up webhook**: it adds this shop's address (`https://your-shop/module/perlypay/webhook`, shown on the page) in Perly Pay with the invoice and refund events and saves its `whsec_…` secret. Or add it there yourself and paste the secret. |
| Payment page | **Redirect** (the default) sends the buyer to the payment page on pay.perly.io; **Overlay** opens it over a page of the shop (add your shop's address in Perly Pay → Settings → "Sites that may embed the checkout"). Phones always get the page itself. |
| Open refunds in Perly Pay | On by default: a credit slip on an order Perly Pay paid opens a refund there. |
| Debug log | Ids and outcomes to Advanced Parameters → Logs, never keys, secrets or customers' details. |

Perly Pay bills in USD or EUR: a cart in another currency does not see the
payment option, and the settings page names the currencies that will not.
Payment → Preferences can narrow the currencies, countries and groups as for
any payment module.

## How it works

- At checkout the buyer picks **Pay with crypto** and places the order. The
  module makes a Perly Pay invoice for the cart's total, with
  `metadata.source = "prestashop"` and the cart's id, and sends the buyer to
  pay it: on pay.perly.io, or over the shop, where pay.js (pinned by its
  Subresource Integrity hash) opens the payment page.
- No order is made yet. PrestaShop's rule for payment modules is that no
  order exists before the customer has been through the payment service; an
  order for every abandoned crypto checkout would also fill the back office.
  The order is made once money is on its way: the first webhook that says so
  (`invoice.seen`, `underpaid`, `paid`, `review`, `fulfilled`, or an expiry
  with part paid) calls `PaymentModule::validateOrder` for the cart, for the
  amount Perly Pay billed.
- Coming back to pay the same cart, the buyer gets the same invoice while it
  can be paid for the same total; for a changed cart a new one is made and the
  old one canceled.
- `invoice.fulfilled` (paid in full and the sender screened) makes the order
  **Payment accepted**: the invoice id is its payment's transaction id, the
  customer gets PrestaShop's emails, and a private message names the network,
  the coin amount and the transaction link. Money seen but not yet confirmed
  is **Awaiting crypto confirmation**, a payment held for review **Crypto
  payment held** (the module's two order states). An invoice paid for another
  total than the order's holds the order instead of accepting it.
- Part payments, overpayments, payments that arrived after the invoice
  expired ("paid late"), and a second invoice paid for the same order leave
  private messages on the order. An invoice that expires with part paid holds
  the order; one that expires unpaid leaves the cart as it was (no order); an
  order whose seen payment never arrived becomes **Payment error**.
- A signed webhook is not taken as proof of payment: it only names the
  invoice, which the module asks Perly Pay's API for with the shop's own key,
  and that answer decides the cart, mode, status, total and currency. An event
  the answer does not bear out (an `invoice.fulfilled` for an invoice still
  open) is acknowledged and ignored; when Perly Pay cannot be asked, the
  webhook answers 500 and Perly Pay sends it again.
- Each event is applied once (its id is kept), under a named database lock
  per cart, and only to a cart whose invoices include it, in the same mode,
  made by this module. A replayed webhook changes nothing.
- When the buyer comes back before the webhook, the module asks the API for
  the invoice and applies the same rules, then sends them on to PrestaShop's
  order confirmation page; with no money on the network yet, a page waits and
  looks again every ten seconds.
- A credit slip (Partial refund, Standard refund, Return products) on an
  order Perly Pay paid opens a refund in Perly Pay for the slip's amount, in
  the coin that was paid (its share of it for a part refund). Perly Pay emails
  the customer for an address; you send from your wallet and paste the
  transaction id in Perly Pay. The order's messages say each step. A slip
  given as a voucher opens no refund. A full refund made in Perly Pay marks
  the order **Refunded** here.

Tables (the module's own; PrestaShop's are never altered):
`perlypay_invoice` (every invoice made for a cart, its mode, total, payment
page and whether it expired), `perlypay_event` (every event applied),
`perlypay_refund` (refunds opened from credit slips). Settings live in
Configuration under `PERLYPAY_*`. Uninstalling removes the settings and keeps
the tables and the two order states, which orders point at.

## Test without real money

With a `sk_test_…` key, buy something with **Pay with crypto**. Pay with test
coins, or from the API (the invoice id `inv_…` is in the payment page's
address):

```bash
curl -X POST https://pay.perly.io/api/v1/invoices/inv_…/simulate \
  -H "Authorization: Bearer $PERLY_PAY_SECRET_KEY" -H "Content-Type: application/json" -d '{}'
```

Within seconds the order is **Payment accepted** and the customer has the
confirmation email.

## Go live

Turn on live mode in Perly Pay and connect your wallet's public keys, paste a
`sk_live_…` key, press **Set up webhook** again (a live key makes a live
webhook), then sell one small order for real and refund it with a credit slip.
Perly Pay sends webhooks only to `https://` addresses.

## Security

- The webhook controller acts only on a valid `Perly-Signature` (HMAC-SHA256
  of the timestamp and the raw body, at most five minutes old, compared in
  constant time by the SDK), and only on invoices this shop made for that cart.
  Even then the event only names the invoice: what the order becomes is decided
  by the invoice as Perly Pay's API returns it to the shop's key, so a leaked
  webhook secret cannot make an unpaid order paid. Refund details are also
  confirmed with Perly Pay's API, never taken from the webhook: a refund event
  only names its refund, and the order message says to send what that refund
  holds, to its address, so a leaked secret cannot point a refund at someone
  else's wallet.
- The order is made for the amount Perly Pay billed, never the cart's total,
  and only for the cart the invoice names (`validateOrder` with the cart's
  secure key).
- The return link carries the cart's id and an HMAC of it under the shop's
  own secret (`_COOKIE_KEY_`); nobody else can make one for another cart.
- The settings page is PrestaShop's AdminModules, behind its token and the
  employee's rights.
- Keys, secrets, signatures and customers' emails never go to the log.
- Everything printed is escaped; every PHP file refuses to run outside
  PrestaShop, and every folder has PrestaShop's `index.php`. Calls go out with
  curl, TLS verified and redirects off.


## PrestaShop documentation used

- Module file structure, logo, config.xml, the zip: https://devdocs.prestashop-project.org/9/modules/creation/module-file-structure/
- Payment modules (`PaymentModule`, `hookPaymentOptions`, `PaymentOption`, `validateOrder`), and `ps_wirepayment` as PrestaShop 9.2.0 ships it
- Translations in modules (XLIFF in `translations/<locale>/`, `isUsingNewTranslationSystem`): https://devdocs.prestashop-project.org/9/modules/creation/module-translation/new-system/
- The Addons technical validation checklist (licences, `index.php`, no `serialize`, tokens, the payment-module rules): https://devdocs.prestashop-project.org/1.7/modules/sell/techvalidation-checklist/
- The hooks as PrestaShop 9.2.0 and 8.2.8 fire them: `actionOrderSlipAdd` (`OrderSlipCreator`), `displayPaymentReturn`; `OrderHistory::changeIdOrderState`, `Order::addOrderPayment`, `CustomerThread`/`CustomerMessage` for the order page's messages.

## Licence

MIT (`src/perlypay/LICENSE`), which PrestaShop Addons lists among the
licences compatible with its AFL/OSL. Perly Pay's PHP SDK inside
(`vendor/perly-pay-sdk`) is MIT too.

## Changelog

- **1.0.1**: Every payment is confirmed with Perly Pay's API before an order is completed: a signed webhook alone no longer marks an order paid, so a leaked signing secret cannot ship an unpaid order. Refund details are also confirmed with Perly Pay's API, never taken from the webhook, so a leaked signing secret cannot point a refund at someone else's wallet.
- **1.0.0**: First release.
