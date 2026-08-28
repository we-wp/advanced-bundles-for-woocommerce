<?php
/**
 * Internal bridge between canonical configuration and WooCommerce products.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\WooCommerce;

use AIMPlugins\AdvancedBundles\Contracts\BundleComponent;

final readonly class ResolvedBundleComponent {
	public function __construct(
		public BundleComponent $component,
		public \WC_Product $product
	) {}
}
