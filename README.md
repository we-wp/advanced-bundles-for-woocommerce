# Advanced Bundles for WooCommerce

Sell fixed product bundles without replacing WooCommerce's normal product, stock, tax, shipping, or refund handling.

[**Download Free 0.1.0 ZIP**](https://github.com/we-wp/advanced-bundles-for-woocommerce/releases/download/v0.1.0/aim-advanced-bundles-0.1.0.zip) · [Try the live demo](https://demo.we-wp.com/plugins/advanced-bundles/) · [View the product page](https://we-wp.com/plugins/aim-advanced-bundles)

**Free download. No account, email address, license key, or telemetry.**

[![Advanced Bundles running in a synthetic WooCommerce demo store](https://raw.githubusercontent.com/we-wp/plugin-demo-platform/e6b21fe51f4517c87c8a80acc00d3c675abc354e/screenshots/interactive-advanced-bundles-desktop-1440.jpg)](https://demo.we-wp.com/plugins/advanced-bundles/)

*Synthetic demo store. Open the [interactive demo](https://demo.we-wp.com/plugins/advanced-bundles/) to test the product page, bundle editor, cart, and checkout.*

## What it does

Choose simple products or exact variations, set fixed quantities, and publish one bundle product. Customers see included products in a clear table with thumbnails, prices, quantities, and links to each product.

At checkout, every included product stays a native WooCommerce line. Existing product settings continue to control price, tax, shipping, stock, fulfilment, and refunds. Saved bundle details remain on the order even if the bundle changes later.

## Free features

- Fixed simple products and exact variations.
- Fixed component quantities.
- Bundle pricing from current component prices.
- Combined stock checks before add-to-cart and checkout.
- Grouped classic and Cart/Checkout Blocks carts.
- Native WooCommerce order lines for tax, stock, shipping, fulfilment, and refunds.
- High-Performance Order Storage compatibility.
- Purchase snapshots that preserve calculated bundle details on the order.

Free has no account requirement, email gate, telemetry, or external requests.

## Requirements

- WordPress 6.8 or newer.
- WooCommerce 9.9 or newer.
- PHP 8.2 or newer.

## Install

1. [Download the Free 0.1.0 installer ZIP](https://github.com/we-wp/advanced-bundles-for-woocommerce/releases/download/v0.1.0/aim-advanced-bundles-0.1.0.zip).
2. Do not use GitHub's automatically generated source archive as the WordPress installer.
3. In WordPress, go to **Plugins > Add New Plugin > Upload Plugin**.
4. Upload the ZIP, activate it, then create or edit a product and choose **Bundle** as the product type.

The same installable ZIP is available from the [we-wp download mirror](https://we-wp.com/downloads/aim-advanced-bundles/latest). Verify either download with the SHA-256 checksum published in the [v0.1.0 release](https://github.com/we-wp/advanced-bundles-for-woocommerce/releases/tag/v0.1.0).

## Source and releases

This public repository contains the Free plugin source only. Advanced Bundles Pro is a separate planned add-on and is not included here.

Every version is tagged. Each GitHub release includes the installable plugin ZIP and checksum. GitHub Releases is the primary public installer source; the website provides a verified mirror.

If Advanced Bundles solves a real store need, star this repository to follow updates and help other WooCommerce users find it.

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

Copyright 2026 UAB BusinessPress. Licensed under GPL-2.0-or-later. See [LICENSE](LICENSE).
