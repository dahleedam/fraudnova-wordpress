# Changelog

All notable changes to this plugin are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-11-08

### Added

- Scoring of the customer IP address, billing phone number and billing email of new WooCommerce orders, on the classic and block checkout.
- Hold, flag or cancel actions for risky cash-on-delivery orders, by score thresholds (review 60, hold 85) and rules (invalid phone, VOIP phone, disposable email, proxy/VPN/Tor, recent abuse).
- Suppression of the customer on-hold email for orders FraudNova held.
- Risk column on the orders list and a FraudNova box with "Check again" on the order screen.
- Protection for WordPress registration, WooCommerce My Account registration and Contact Form 7.
- Test mode (on by default), masked Activity log, and a connection test that shows the credit balance.
- 24-hour reuse of results; the automatic check of an order uses at most three credits (one per check type) and never runs twice, while "Check again" runs a fresh check that uses up to three more; by default, orders and forms go through on every API error.
- `FRAUDNOVA_API_KEY` constant support in wp-config.php.
- Privacy policy text, order personal-data erasure and uninstall cleanup.
- HPOS compatibility.

[1.0.0]: https://github.com/dahleedam/fraudnova-wordpress/releases/tag/v1.0.0
