<?php
/**
 * Cart and Checkout Blocks integration for fixed bundle groups.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\WooCommerce\Blocks;

use AIMPlugins\AdvancedBundles\PluginEnvironment;
use AIMPlugins\AdvancedBundles\WooCommerce\Metadata;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema;

final class BundleBlocksIntegration {
	public const EXTENSION_NAMESPACE = 'aim-advanced-bundles';
	public const SCRIPT_HANDLE       = 'aim-advanced-bundles-blocks';

	public function register(): void {
		add_action( 'woocommerce_blocks_loaded', array( $this, 'registerStoreApiData' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueueScript' ), 20 );
		add_filter( 'woocommerce_store_api_product_quantity_editable', array( $this, 'filterQuantityEditable' ), 20, 3 );
		add_filter( 'woocommerce_store_api_product_quantity_minimum', array( $this, 'filterQuantityLimit' ), 20, 3 );
		add_filter( 'woocommerce_store_api_product_quantity_maximum', array( $this, 'filterQuantityLimit' ), 20, 3 );
		add_filter( 'woocommerce_store_api_product_quantity_multiple_of', array( $this, 'filterQuantityLimit' ), 20, 3 );
	}

	public function registerStoreApiData(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => CartItemSchema::IDENTIFIER,
				'namespace'       => self::EXTENSION_NAMESPACE,
				'data_callback'   => array( $this, 'cartItemData' ),
				'schema_callback' => array( $this, 'cartItemSchema' ),
				'schema_type'     => 'ARRAY_A',
			)
		);
	}

	public function enqueueScript(): void {
		if (
			! function_exists( 'is_cart' )
			|| ! function_exists( 'is_checkout' )
			|| ( ! is_cart() && ! is_checkout() )
		) {
			return;
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			plugins_url( 'assets/blocks-cart.js', PluginEnvironment::file() ),
			array( 'wc-blocks-checkout' ),
			PluginEnvironment::version(),
			true
		);
	}

	/**
	 * Expose only the non-sensitive server-owned bundle role.
	 *
	 * @param array<string, mixed> $cart_item Server-side WooCommerce cart item.
	 * @return array{role: string}
	 */
	public function cartItemData( array $cart_item ): array {
		$role = $cart_item[ Metadata::ROLE ] ?? '';

		if ( ! in_array( $role, array( Metadata::ROLE_PARENT, Metadata::ROLE_COMPONENT ), true ) ) {
			$role = '';
		}

		return array( 'role' => $role );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function cartItemSchema(): array {
		return array(
			'role' => array(
				'description' => __( 'Bundle cart group role.', 'aim-advanced-bundles' ),
				'type'        => 'string',
				'enum'        => array( '', Metadata::ROLE_PARENT, Metadata::ROLE_COMPONENT ),
				'context'     => array( 'view' ),
				'readonly'    => true,
			),
		);
	}

	/**
	 * @param mixed $editable  Native Store API editable state.
	 * @param mixed $product   Native WooCommerce product; cart metadata owns grouping.
	 * @param mixed $cart_item Server-side WooCommerce cart item.
	 */
	public function filterQuantityEditable( mixed $editable, mixed $product, mixed $cart_item ): mixed {
		unset( $product );

		return null === $this->componentQuantity( $cart_item ) ? $editable : false;
	}

	/**
	 * @param mixed $limit     Native Store API quantity limit.
	 * @param mixed $product   Native WooCommerce product; cart metadata owns grouping.
	 * @param mixed $cart_item Server-side WooCommerce cart item.
	 */
	public function filterQuantityLimit( mixed $limit, mixed $product, mixed $cart_item ): mixed {
		unset( $product );
		$quantity = $this->componentQuantity( $cart_item );

		return null === $quantity ? $limit : $quantity;
	}

	private function componentQuantity( mixed $cart_item ): ?int {
		if ( ! is_array( $cart_item ) || Metadata::ROLE_COMPONENT !== ( $cart_item[ Metadata::ROLE ] ?? null ) ) {
			return null;
		}

		$quantity = $cart_item['quantity'] ?? null;

		return is_int( $quantity ) && $quantity > 0 ? $quantity : null;
	}
}
