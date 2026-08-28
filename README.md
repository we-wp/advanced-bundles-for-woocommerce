# Advanced Bundles for WooCommerce

Create fixed WooCommerce product bundles from simple products or exact variations. Each included item keeps its own price, stock, tax, shipping, and refund data.

- [Product page](https://we-wp.com/plugins/aim-advanced-bundles)
- [Live demo](https://demo.we-wp.com/plugins/advanced-bundles/)
- [Documentation](https://we-wp.com/docs)
- [Download the verified ZIP](https://we-wp.com/downloads/aim-advanced-bundles/latest)
- [GitHub releases](https://github.com/we-wp/advanced-bundles-for-woocommerce/releases)

## Free features

- Fixed simple products and exact variations.
- Fixed component quantities.
- Bundle pricing from current component prices.
- Combined stock checks before add-to-cart and checkout.
- Grouped classic and Cart/Checkout Blocks carts.
- Native WooCommerce order lines for tax, stock, shipping, fulfilment, and refunds.
- High-Performance Order Storage compatibility.
- Purchase snapshots that preserve calculated bundle details on the order.

Free sends no telemetry and makes no external requests.

## Requirements

- WordPress 6.8 or newer.
- WooCommerce 9.9 or newer.
- PHP 8.2 or newer.

## Install

1. Open the [latest GitHub release](https://github.com/we-wp/advanced-bundles-for-woocommerce/releases/latest).
2. Download `aim-advanced-bundles-0.1.0.zip` from **Assets**. Do not use GitHub's automatically generated source archive as the WordPress installer.
3. In WordPress, go to **Plugins > Add New Plugin > Upload Plugin**.
4. Upload the ZIP, activate it, then create or edit a product and choose **Bundle** as the product type.

The same installable ZIP is available from the [official download page](https://we-wp.com/downloads/aim-advanced-bundles/latest). Verify downloads with the SHA-256 checksum published in the release.

## Source and releases

This public repository contains the Free plugin source only. Advanced Bundles Pro is a separate planned add-on and is not included here.

Every version is tagged. Each GitHub release includes the installable plugin ZIP and checksum. The website remains the primary verified download service; GitHub Releases is the public source and release mirror.

## Development

Validate the package metadata and PHP syntax:

```sh
composer validate --strict --no-check-publish
find . -type f -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
```

See [CONTRIBUTING.md](CONTRIBUTING.md) before opening a pull request.

## Support and security

- Use [GitHub Issues](https://github.com/we-wp/advanced-bundles-for-woocommerce/issues) for reproducible Free-version bugs. Remove private store and customer data first.
- Read [SUPPORT.md](SUPPORT.md) for usage and compatibility questions.
- Report vulnerabilities privately. Follow [SECURITY.md](SECURITY.md); do not open a public security issue.

## License

Copyright 2026 UAB BusinessPress. Licensed under GPL-2.0-or-later. See [LICENSE.txt](LICENSE.txt).
