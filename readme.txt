=== LAFU Gift Cards ===
Contributors: elsama101
Tags: gift card, gift cards, gift certificate, gift voucher, woocommerce gift cards
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.13.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sell gift cards online and in your store. Customers pay at checkout, the leftover balance stays on the card, and every cent is tracked.

== Description ==

Gift cards bring you money up front, new customers who discover your shop, and recipients who usually spend more than the card is worth. LAFU Gift Cards makes selling them simple, online and in your shop.

Turn any WooCommerce product into a gift card in a minute. Customers pick an amount, see the card in your shop's colours and send it by email now or on a day they choose. The recipient pays with it at checkout, and whatever they do not spend stays on the card.

**Completely free.** No account, no fee per card sold, no upsell screens.

= Why shops choose LAFU Gift Cards =

* **Sell online and at the counter.** A card sold in your shop is saved as a normal paid order, so it is in your sales and bookkeeping.
* **Full transaction history (ledger) for every card.** Every sale, payment, refund and manual change, with the date and who made it.
* **Correct VAT and bookkeeping.** The card pays like a payment method, so product prices and VAT stay right.
* **A card can never be spent twice**, even when two people try at the same moment.
* **Bring your paper gift cards** from a CSV file, with their own codes and balances.

= Gift card products =

* Fixed amounts, or let customers enter their own amount within your limits.
* Three ready designs: Colour (your brand colour), Gift ribbon and Christmas.
* Live preview of the card while the customer fills in the form.
* Your own heading and text on the card, per product.

= Delivery =

* Email the gift card right away or on a chosen date (scheduled delivery).
* Send to someone else with a personal message, or anonymously.
* Several recipients in one go: each gets their own card.
* Physical gift cards: print and ship them instead of emailing.

= Redeem at checkout =

* Customers enter the code in the cart or checkout, in the block checkout or the classic one.
* Partial redemption: the unused balance stays on the card.
* Several gift cards on one order, or one card for the whole order.
* Cancelled or refunded orders put the money back on the card automatically.

= In your shop =

* Sell a gift card at the counter for cash or card, or give one away for free.
* Every card has a QR code for quick lookup and can be printed.

= Your customers =

* My Account shows their gift cards and balances, and they can save a card they received.
* Balance check anywhere with the [lafu_gift_card_balance] shortcode.

= Manage your cards =

* Search every card and see its balance and full transaction history.
* Change a balance with a reason, edit the recipient or expiry, resend the email or cancel a card.
* Optional expiry date. Export all cards to CSV.

= Built right =

* Works with the block checkout, High-Performance Order Storage (HPOS) and the WordPress privacy tools.
* Codes that cannot be guessed, with limits on how often codes can be tried.
* Accessible (WCAG 2.1 AA) and translated into 20 languages.

= For developers =

* Hooks: lafu_gift_cards_issued, lafu_gift_cards_balance_changed, lafu_gift_cards_designs, lafu_gift_cards_default_expiry, lafu_gift_cards_redemption_texts, lafu_gift_cards_redeem_online.
* Orders paid with gift cards carry the meta _lafu_gc_redemptions (card, masked code, amount), so accounting tools can book them as a payment.
* Email templates can be overridden in your theme like any WooCommerce email.

= LAFU Gift Cards Pro =

Everything above is free and stays free. For shops that want more, the separate [LAFU Gift Cards Pro](https://www.lanetti.fi/products/gift-cards) add-on adds:

* **Designer**: your own words and colours on every design, the style of the gift card page, and designs made from your own photo or printed card, with the amount placed anywhere
* **Ten illustrated occasion designs** and buyer photo cards
* **Till for the counter** with QR scanning
* **Printed card batches** and **bulk orders** for companies
* **Store credit** from refunds, **top-ups** and **bonus cards**
* **Group gifts**: several people chip in for one card
* **Text message and WhatsApp delivery**
* **Limits** by category or product
* **Expiry reminders** and **reports**
* **REST API and webhooks**

== Installation ==

1. Install and activate WooCommerce and LAFU Gift Cards.
2. Click Create gift card product in the notice, or tick Gift card on any simple product.
3. Set the amounts and the design on the Gift card tab and publish the product.
4. Optional: set a code prefix and validity under WooCommerce > Settings > Products > Gift cards.

== Frequently Asked Questions ==

= Is VAT charged on gift cards? =

The card is sold without VAT and VAT is charged on the products when the card is used, which is the usual rule for multi-purpose vouchers in the EU. Check with your accountant how your cards should be booked.

= What happens when an order paid with a gift card is cancelled? =

The amount taken from the card goes back to it, and the card history shows both rows.

= What happens when a gift card purchase is refunded? =

The card is cancelled. If part of it was already spent, the order gets a note so you can decide what to do.

= Can I import paper gift cards? =

Yes. WooCommerce > Gift cards > Import takes a CSV file with the code and balance of each card. Customers keep using their old codes.

= Is there a fee per gift card sold? =

No. The plugin is free, and payments go through your own WooCommerce payment methods.

= Can I sell gift cards in my physical shop? =

Yes. Sell them from WooCommerce > Gift cards. Each sale is saved as a normal paid order.

= My customers use the card at an appointment, not in a web shop. Does it fit? =

Yes. Under WooCommerce > Settings > Products > Gift cards, set Customers use the card to At an appointment or booking and add your booking page as the Button link. The gift card page and the email then show a Book your appointment button and steps for the appointment. The button text and the How to use it steps can be reworded too, and Send me a test gift card email shows you the result.

= Can a gift card be used more than once? =

Yes. Customers can pay part of an order with it, and the rest stays on the card for next time.

= Can a gift card be sent on a later date? =

Yes. The buyer picks the delivery date, and the email goes out at 8:00 that morning, in your shop's time zone.

= Do gift cards expire? =

Only if you want them to. Set an expiry in months, or leave it off.

= Does it work with the WooCommerce block checkout? =

Yes, and with the classic cart and checkout too.

== Screenshots ==

1. Buyers choose the amount and recipient and see the card change as they go.
2. Paying with a gift card in the checkout block. The card pays part of the order; prices and VAT stay as they are.
3. WooCommerce > Gift cards: open balance, cards sold and used this month, and every card.
4. One card: QR code, balance left, details and the full history of where the money went.
5. Selling a card at the till, with a live preview. It is saved as a normal paid order.
6. The gift card email, in the shop's own WooCommerce email style.
7. The gift card page the email opens: the card with its QR code, a PDF to print and how to use it.

== Credits ==

The gift card page bundles the Fraunces and Manrope fonts (SIL Open Font License 1.1, licence files in assets/fonts). No fonts are loaded from other servers.

== Changelog ==

= 1.13.2 =
* Fixed cart coupons (an amount off the whole cart) no longer lower the price of a gift card on WooCommerce 8.5 to 8.8 either. The whole coupon goes to the other products, as on newer WooCommerce. On those versions a product or percentage coupon is also refused again on a cart with only gift cards ("not applicable to selected products"), as on newer WooCommerce. Also on those versions, a coupon's product rules (allowed or excluded products and categories, Exclude sale items) look only at the other products, not at gift card lines.
* Admin buttons: the hover and focus colour is now a darker pink (dark red for Cancel card), so the white text is easy to read (contrast at least 4.5:1). The step numbers and the main Sell a card tile use the darker colours too.

= 1.13.1 =
* The gift card email now looks like the gift card page it opens: the same card picture (colours, gradient, photo and illustration where the mail app shows background images), greeting, message, buttons, How to use it steps and balance, in the page style chosen on the Designs page. The email and the page are built from one shared view, so they stay the same.
* A gift card still never pays for a new gift card or a top-up in the cart: in a cart with other products it pays only those, shipping and fees, and the Have a gift card? box stays. In a cart with only gift cards there is nothing it may pay, so the box is hidden and codes are refused, with a message in the cart and checkout blocks too.
* New setting Allow paying for new gift cards with existing ones (WooCommerce > Settings > Products > Gift cards, off by default, with a help tip). Off is safer: if on, an old gift card can buy a new card or top one up, which resets its expiry date.
* Checkout safety: if the cart changes after the gift cards were applied (for example another plugin changes it before the order is made), the cards are taken off and checkout stops with a message, so a card is never left uncharged. Each stop is written to the WooCommerce log (source lafu-gift-cards).
* Subscriptions and other plugins that calculate copies of the cart (for example renewal carts) no longer get the gift card deducted: cards apply only to the shopper's own cart.
* Shops that take gift cards in person or at an appointment (How the card is used) no longer show the gift card box in the web shop, and codes are refused at checkout.

= 1.13.0 =
* How the card is used: a new setting under WooCommerce > Settings > Products > Gift cards for shops where cards are used online, in person at the counter, or at an appointment or booking. Each choice brings its own button and How to use it steps on the gift card page and in the gift card email.
* The main button (text and link, for example your booking page) can be changed or hidden, and the How to use it heading and steps can be written in your own words, with {shop} and {balance}. The texts can be translated with WPML or Polylang.
* Send me a test gift card email: a button in the settings sends the real gift card email with a sample card to you.

= 1.12.3 =
* Money safety: coupons no longer lower the price of a gift card in the cart, also cart-wide coupons such as 10 off the cart. If a discount or a pricing plugin still lowers a gift card line, the card is created with the amount actually paid, never more.
* Money safety: a double click at checkout, a payment notice arriving at the same time as the thank you page, or two status changes at once can no longer charge or return a gift card twice. Each order's gift card changes now run one at a time.
* Unfinished payments: gift card money held by every unpaid order is released after the hold time, not only for the newest orders.
* Paying a failed order again: the gift cards are charged again before the payment. If a card was spent in the meantime, the payment stops with a clear message instead of the shop getting less money.
* Too many wrong codes: the limit now counts each shopper on their own, so one person on a shared connection cannot lock out others. Shops behind a reverse proxy on their own network count the real visitor address (other proxies, such as Cloudflare, can be added with the lafu_gift_cards_trusted_proxies filter).
* Chargebacks and failed payments: when a paid order fails afterwards or is disputed (moves from paid to On hold), gift cards it bought that nobody has used yet are cancelled, and used ones are flagged for the shop to check. If the order is paid again, new cards are created. Deleting a paid order flags its cards and cancels nothing.
* Email preview: the gift card email can be previewed under WooCommerce > Settings > Emails without an error.

= 1.12.2 =
* Plugin header: author is LAFU, like our other plugins on WordPress.org.

= 1.12.1 =
* Product page: the gift option now says Email it to someone else, so buyers know the card goes by email.

= 1.12.0 =
* The cart and order show Delivery: Printed card for printed cards, and no longer show the colour as a code.
* Hooks for add-ons: lafu_gift_cards_buyer_form_delivery (start of the Who is it for part) and lafu_gift_cards_physical_line (printed or emailed per order line).

= 1.11.0 =
* The Designer page moves to LAFU Gift Cards Pro. The free plugin keeps the three ready designs, picked with their colour on each gift card product. Texts and colours you saved in the Designer keep showing on your cards.
* The first-run tour starts with your first gift card product.

= 1.10.2 =
* A "Pro features" link on the Plugins screen and a small card at the bottom of the Gift cards page that says what the Pro add-on adds. The card can be hidden, and neither shows once Pro is active.

= 1.10.1 =
* Photo cards stay visible with image lazy-load plugins (EWWW, Jetpack, WP Rocket, LiteSpeed, Smush, a3 Lazy Load): the card opts out of lazy loading.

= 1.10.0 =
* Card designs can place the amount anywhere and choose its size, font and colour (used by Pro photo designs).
* Card designs with the amount at the top or bottom place it right under the heading or right above the names and code, so it never covers them.

= 1.9.0 =
* New: a short tour of the Gift cards pages on the first visit (Designer, Counter, Cards, Settings). Skip it any time; Take the tour plays it again.
* Card designs can hide the heading and shop name and put the amount in another spot (used by Pro photo designs), on the web card, in emails and in product images.

= 1.8.1 =
* My Account: the Gift cards entry gets a gift icon in themes that give every entry an icon (Storefront, OceanWP, Blocksy, WoodMart).
* The gift card form keeps its layout in every theme: no grey boxes or clipped headings, and themes that put the add to cart form in a row no longer squeeze it.
* The card list in My Account stacks on phones in every theme.

= 1.8.0 =
* Designer: one colour picker for every design. Our ready designs take your colour too, the art stays, and the card and its tile change as you pick. Your brand colours are one-click picks.
* Designer: the preview stays in view while you scroll the designs and settings.

= 1.7.0 =
* The gift card email no longer shows the code by default: it shows the card and an Open your gift card button, and the code is on the private gift card page. Turn on Code in the email under WooCommerce > Settings > Gift cards to show it.
* New Print or save as PDF button in the gift card email: it opens the gift card page ready to print.

= 1.6.0 =
* New Designer: one page for the card design, the texts on the card, your brand colours and the gift card page. Pick a design on the left, see it large with your texts as you type, and make a new product from it.
* New Letter style for the gift card page: the card comes out of an envelope and the message reads like a letter.
* Point at a gift card page style to see a small live preview of it, click Preview for the full page.

= 1.5.1 =
* Fix: the Edit email text links on the Designs page and the Pro reminder settings now open the right email settings.

= 1.5.0 =
* New gift card page: the link in the gift card email opens a page made for the recipient, with the card, the message, how to use it, a Shop button and a Save as PDF or print button. Choose the Celebration or the Card style on the Designs page and preview both.
* Entrance animation that fits the design: confetti, snow, stars or hearts fly in once when the page opens. It can be switched off, and visitors who turn off motion never see it.
* The gift card email has an Open your gift card button.
* A gift card goes into the cart only through its product form. Express pay buttons, block add to cart buttons and other plugins that add products directly are refused with a clear message, so no order line can exist without a card behind it.
* Apple Pay, Google Pay and PayPal product page buttons (WooCommerce Stripe Gateway, WooPayments, Payment Plugins for Stripe and PayPal) are hidden on gift card pages. They stay available at checkout.
* Tested with WordPress 6.5 to 7.1, WooCommerce 9.4 to 11.2, Elementor with Pro Elements, Polylang, Yoast SEO, Klarna, WoodMart, Storefront, Hello Elementor and Twenty Twenty-Five.

= 1.4.2 =
* Use this card as the product image now makes a sharp square image of the whole card (design, art and texts), so shop grids that crop to squares still show all of it. The image stays when you then click Update, and the Product image box shows it right away.
* Card history amounts and other plus amounts in tables line up again (a Counter style had turned them into blocks).
* The live card preview shows amounts exactly like the shop: same currency position, decimals and separators.
* Card text fields on the Designs page use the full width, so the example text fits.
* Wide report tables scroll inside their box on phones.

= 1.4.1 =
* Ships in 20 languages, and Finnish and Swedish load for every shop locale variant (fi, fi_FI, sv, sv_SE).
* WPML can translate the card texts and the product heading, name and line. Polylang can translate the shop card texts. Gift card emails use the language the buyer checked out in.
* A gift card charged at checkout is released when the payment fails, the order is cancelled, or a pending payment is left unfinished. Paying that order later charges the card again, and if a card can no longer pay, the order gets a note.
* Refunding the products on an order returns the gift cards' share of those lines. Refunding part of a sold gift card lowers that card, and refunded cards are not issued later.
* A gift card cannot pay for another gift card.
* The Gift card tab can save the card preview as the product image.
* Designs has a link to the gift card email text.

= 1.4.0 =
* A simpler menu: Cards, Counter, Designs and Settings. Import is a button on the card list.
* Each gift card product now shows one design that you choose, like any other product with its own picture. For Christmas or a birthday, make another gift card product with that design: one click from the Designs page.
* New Designs page, one place for the look of your cards: the heading, the name and a line of text on the card (live preview as you type), your brand colours and the recipient and expiry texts, in your own words or language.
* Each gift card product can change the heading, name and text on its own cards, with a live card preview on the product's Gift card tab.
* New Colour card in your own brand colour.
* Counter: sell a card in a pop-up window, POS style. Staff can pick any design there.
* Christmas is now an illustrated card with a decorated tree, and the new Gift ribbon card has a gold ribbon and bow.
* The design picker at the counter shows small cards instead of colour chips.
* Classic, Warm and Minimal are no longer offered for new products. Cards already sold with them keep their look.
* New hook data for add-ons: designs can carry an illustration or a picture with position, size and shade.

= 1.3.0 =
* Printed cards can be made in advance and activated when they are sold ("Not activated" status).
* Checkout shows the name of each card, for example "Gift card ••••7KQ2".
* New hooks for add-ons: card data, card limits, delivery, card look, admin sections and the account page.

= 1.2.0 =
* Return to card: put back what a gift card paid on an order, for example when the order is refunded in part.
* Privacy: gift cards are included in WordPress personal data export and erase, and a suggested privacy policy text is added.
* Accessibility: colours meet WCAG 2.1 AA contrast; the Warm design is a little deeper.
* No inline JavaScript or encoded data in the plugin markup.

= 1.1.0 =
* QR code on every card (print page, email and admin), made inside the plugin with no outside service.
* Live card preview on the product page and in the admin sale form.
* New admin look: overview numbers (open balance, active cards, sold and used this month), card page with balance meter and history, step by step sale form, simpler import.
* Christmas design reads better (gold label).

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.13.2 =
Fixes fixed cart coupons lowering gift card prices on WooCommerce 8.5 to 8.8, and makes the admin button hover colour easier to read.

= 1.13.1 =
Shops that set How the card is used to In person or Appointment no longer take gift card codes at the web shop checkout. The gift card email now matches the gift card page.
