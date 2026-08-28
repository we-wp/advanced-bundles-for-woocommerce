<?php
/**
 * WooCommerce product type registration.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\WooCommerce;

final class ProductTypeRegistrar {
	public const PRODUCT_TYPE = 'aim_bundle';

	public function register(): void {
		add_filter( 'product_type_selector', array( $this, 'addProductType' ) );
		add_filter( 'woocommerce_product_class', array( $this, 'mapProductClass' ), 10, 2 );
	}

	/**
	 * @param array<string, string> $types Product types.
	 * @return array<string, string>
	 */
	public function addProductType( array $types ): array {
		$types[ self::PRODUCT_TYPE ] = __( 'Bundle', 'aim-advanced-bundles' );

		return $types;
	}

	/**
	 * @param string $class_name   Product class name.
	 * @param string $product_type Product type.
	 */
	public function mapProductClass( string $class_name, string $product_type ): string {
		if ( self::PRODUCT_TYPE === $product_type ) {
			return AimBundleProduct::class;
		}

		return $class_name;
	}
}
