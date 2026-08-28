=== Advanced Bundles for WooCommerce ===
Tags: woocommerce, bundles, products, inventory
Requires at least: 6.8
Tested up to: 7.1
Stable tag: 0.1.0
Requires PHP: 8.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create fixed WooCommerce product bundles with reliable pricing, stock, tax, shipping, and order records.

== Description ==

Advanced Bundles for WooCommerce lets you combine simple products or exact variations into one fixed bundle. Each included product keeps its normal WooCommerce price, tax class, shipping data, stock handling, and order line.

Use the native WooCommerce product editor to choose bundle products and quantities. Customers see a clear table with product images, prices, and fixed quantities before adding the bundle to their cart.

Free includes:

* Fixed simple products and exact variations.
* Fixed component quantities.
* Pricing calculated from current component prices.
* Aggregate stock checks before add-to-cart and checkout.
* Grouped cart behavior for the bundle and its components.
* Classic and Cart/Checkout Blocks support.
* High-Performance Order Storage (HPOS) compatibility.
* Purchase snapshots that preserve calculated bundle details on the order.

The plugin contains no telemetry, premium installer, remote executable code, or external request.

== Installation ==

1. Install and activate WooCommerce 9.9 or newer.
2. Upload and activate Advanced Bundles for WooCommerce.
3. Create or edit a product and choose **Bundle** as the product type.
4. Open **Bundle components**, choose products or exact variations, set fixed quantities, and publish.

== Frequently Asked Questions ==

= How is the bundle price calculated? =

Free adds the current prices of all included products, multiplied by their fixed quantities. Included products keep their native WooCommerce tax classes.

= How does stock work? =

WooCommerce manages stock on each included product or variation. The plugin checks the total demand from ordinary cart items and bundle components before add-to-cart and checkout.

= What appears on the order? =

The bundle appears as a zero-value parent line. Included products remain native WooCommerce order lines for tax, stock, shipping, fulfilment, and refunds. The parent line stores a calculated purchase snapshot once.

= Does the Free package contact an external service? =

No. Free performs no external requests.

= Is Pro code shipped in the Free package? =

No. Free and Pro are separate GPL-2.0-or-later packages.

== Changelog ==

= 0.1.0 =

* Initial Free package with fixed products and variations, summed pricing, fixed quantities, aggregate stock checks, grouped classic and Blocks carts, native component order lines, write-once calculated snapshots, HPOS support, and a responsive product table.
