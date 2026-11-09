=== FraudNova – Fraud Detection for Orders and Forms ===
Contributors: fraudnova
Tags: woocommerce, fraud, cash on delivery, spam, phone validation
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Score every WooCommerce order's IP, phone and email, hold risky cash-on-delivery orders, and block throwaway emails on your forms.

== Description ==

Cash on delivery is popular because the customer pays nothing up front. That is also its weakness: fake orders, wrong phone numbers and prank buyers cost you shipping, packaging and courier fees, and you only find out when the parcel comes back.

FraudNova checks the customer's IP address, billing phone number and billing email the moment an order is placed, and stops the obvious bad ones before you ship. Risky orders are put on hold for you to confirm by phone; clean orders go through as usual.

What it does:

* **Orders** – scores the IP address, billing phone and billing email of every new order, on both the classic and the block checkout. The result is saved on the order and added as a private order note.
* **Orders list** – a Risk column shows each order's score at a glance.
* **Order screen** – a FraudNova box shows the score, the reasons and the details of each check, with a "Check again" button.
* **Holds** – risky cash-on-delivery orders are put on hold, flagged or cancelled, depending on your settings. The customer's "order on hold" email is not sent for orders FraudNova held.
* **Forms** – protects WordPress registration, WooCommerce My Account registration and Contact Form 7 against throwaway emails and abusive IP addresses. A buyer is never blocked during checkout.
* **Test mode** – on by default. FraudNova scores and notes everything but never holds, cancels or blocks, so you can see what it would do first.
* **Activity** – a log of recent checks, with emails, phone numbers and IP addresses masked.
* **Credits and reuse** – the same IP address, phone number or email is not checked twice within 24 hours. The automatic check of an order uses at most three credits (one per check type) and is never run twice for the same order. "Check again" on the order screen runs a fresh check and uses up to three more.

= How it decides =

Each check returns a fraud score from 0 to 100, and the order gets the highest one. At 60 or more the order is flagged for review; at 85 or more it is treated as high risk and held. Some signals count on their own, whatever the score: an invalid phone number holds the order, while a VOIP phone number, a disposable email address, a proxy, VPN or Tor connection, or recent abuse on the IP address or email flags it for review. You can change every threshold and rule, and choose whether review and high-risk orders are flagged, held or (high risk only) cancelled. Orders already paid by card are only flagged, never held. By default, if the FraudNova API cannot be reached, the order goes through normally.

= External services =

This plugin connects to the FraudNova API (https://fraudnova.com) to obtain a fraud score. When a new WooCommerce order is placed, it sends the customer's IP address, billing phone number, billing country and billing email address. When a protected registration or contact form is submitted, it sends the visitor's IP address and email address. Data is sent only after you save an API key and only for the features you have enabled; nothing is sent before that. The "Test connection" button calls the FraudNova API with only your API key, to read your remaining credits; the balance line on the Activity tab shows the result of the last test. No other external service is contacted. FraudNova terms of service: https://fraudnova.com/terms. Privacy policy: https://fraudnova.com/privacy.

= Pricing =

FraudNova uses prepaid credits: one credit per check, so the automatic check of an order uses up to three credits, and each "Check again" uses up to three more. New accounts get free trial credits after verifying their email address. More credits are sold as packs at https://fraudnova.com/dashboard/billing. There is no subscription.

== Installation ==

1. Install the plugin from Plugins → Add New Plugin, or upload the zip, and activate it.
2. Create a free account and an API key at https://fraudnova.com/dashboard/api-keys.
3. Paste the key under Settings → FraudNova and click "Test connection". It shows your credit balance.
4. Leave test mode on for a few days and look at the notes on new orders and the Activity tab.
5. When the results look right, switch test mode off.

== Frequently Asked Questions ==

= Does this slow down checkout? =

Typically by a second or two. A limit of about eight seconds applies and the order still goes through if the limit is hit. By default, if the API is unavailable the order goes through normally.

= What happens when I run out of credits? =

By default, checks are skipped and orders are not held, so nothing is blocked. You see a notice in the WordPress admin and get an email so you can top up. The email is sent at most once a day and can be turned off in the General settings.

= Why was a real customer held? =

The order's score or one of the rules (for example an invalid phone number) crossed your thresholds. The order note and the FraudNova box on the order screen show the reasons. Raise the thresholds or switch a rule off under Settings → FraudNova, keep test mode on while you tune it, and use the Activity tab to see recent decisions.

= Why did a risky order still show as Processing for a moment? =

For payment methods other than WooCommerce's built-in Cash on delivery, the hold is applied right after the payment method sets the order to Processing. By then the Processing email may have been sent and stock may have been reduced. The order then moves to On hold with a FraudNova note.

= Does it work with the block checkout and HPOS? =

Yes. Both the classic and the block checkout are supported, and the plugin is compatible with High-Performance Order Storage.

= Which payment methods are checked? =

Cash-on-delivery-like payment methods are detected automatically; if the store has none enabled, every order is checked. You can pick the exact payment methods to check, or check all orders, on the Orders tab of the settings.

= Which forms are protected? =

WordPress registration, WooCommerce My Account registration and Contact Form 7. Accounts created during checkout are never blocked, so a buyer can always finish an order.

= Is customer data stored? =

The score, the reasons and the check details are saved on the order. The Activity tab keeps a short log with emails, phone numbers and IP addresses masked. The plugin adds a suggested section to your privacy policy and removes its order data when an order's personal data is erased. See https://fraudnova.com/privacy.

= Can I define the API key in wp-config.php? =

Yes. Add `define( 'FRAUDNOVA_API_KEY', 'fn_…' );` to wp-config.php and it takes the place of the key in the settings.

== Screenshots ==

1. The orders list with the Risk column.
2. The FraudNova box on the order screen.
3. The Orders tab of the settings.
4. The Activity tab.

== Changelog ==

= 1.0.0 =
* First release.
* Scores the IP address, phone number and email of new WooCommerce orders on the classic and block checkout.
* Holds, flags or cancels risky cash-on-delivery orders by score and rules.
* Risk column on the orders list and a FraudNova box with "Check again" on the order screen.
* Protects WordPress registration, WooCommerce My Account registration and Contact Form 7.
* Test mode, masked Activity log, connection test with credit balance.
* Privacy policy text, personal data erasure and uninstall cleanup.

== Upgrade Notice ==

= 1.0.0 =
First release.
