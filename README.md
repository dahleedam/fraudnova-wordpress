# FraudNova

A WordPress plugin that scores the IP address, billing phone number and billing email of every new WooCommerce order with the [FraudNova](https://fraudnova.com) API, holds risky cash-on-delivery orders for review, and protects WordPress registration, WooCommerce My Account registration and Contact Form 7 against throwaway emails and abusive IP addresses.

- Directory page: https://wordpress.org/plugins/fraudnova/
- Documentation: https://fraudnova.com/docs#wordpress
- User-facing readme: [`readme.txt`](readme.txt)

## Requirements

WordPress 6.0+, PHP 7.4+. WooCommerce 8.0+ for order scoring (optional; the form protection works without it).

## Development

```sh
composer install      # dev dependencies (PHPUnit, Brain Monkey, WPCS, PHPCompatibilityWP)
composer test         # PHPUnit
composer lint         # PHP_CodeSniffer (WordPress Coding Standards + PHP 7.4 compatibility)
composer make-pot     # regenerate languages/fraudnova.pot (needs WP-CLI, see docs/RELEASING.md)
bin/build-zip.sh      # build dist/fraudnova-<version>.zip, the installable plugin
```

The repository root is the plugin folder. Files listed in `.distignore` are left out of the zip.

Releasing: see [`docs/RELEASING.md`](docs/RELEASING.md). Changes: [`CHANGELOG.md`](CHANGELOG.md).

## License

GPL-2.0-or-later. See [`LICENSE`](LICENSE).
