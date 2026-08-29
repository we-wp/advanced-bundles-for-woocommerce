# Contributing

Thank you for helping improve Advanced Bundles for WooCommerce.

## Before opening an issue

1. Confirm the problem still happens with the latest release.
2. Test with a supported WordPress, WooCommerce, and PHP version.
3. Remove customer names, addresses, orders, license keys, credentials, and other private data.
4. Search existing issues for the same problem.

Use the bug report template and include clear reproduction steps, expected behavior, actual behavior, and the relevant software versions.

## Pull requests

- Keep each pull request focused on one change.
- Follow WordPress and WooCommerce coding and accessibility practices.
- Preserve the public contracts, product type, metadata keys, stock checks, order snapshots, and Free/Pro boundary.
- Add or update tests when behavior changes.
- Do not add telemetry, remote executable code, premium installers, customer data, or proprietary source.
- Confirm that `composer check` passes.

Run the complete public checks from the repository root:

```sh
composer install
composer check
```

For visible changes, include screenshots made with synthetic products. For cart, checkout, stock, or order changes, list the classic or block flow, HPOS state, and WooCommerce version you tested.

By contributing code, you agree that your contribution is licensed under GPL-2.0-or-later.
