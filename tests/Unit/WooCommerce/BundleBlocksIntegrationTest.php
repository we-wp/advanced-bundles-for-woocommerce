<?php
/**
 * Cart and Checkout Blocks integration tests.
 *
 * @package AIMPlugins\AdvancedBundles\Tests
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Tests\Unit\WooCommerce;

use AIMPlugins\AdvancedBundles\WooCommerce\Blocks\BundleBlocksIntegration;
use AIMPlugins\AdvancedBundles\WooCommerce\Metadata;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema;
use PHPUnit\Framework\TestCase;

final class BundleBlocksIntegrationTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['aim_plugins_test_endpoint_data'] = array();
		$GLOBALS['aim_plugins_test_scripts']       = array();

		foreach (
			array(
				'woocommerce_blocks_loaded',
				'wp_enqueue_scripts',
				'woocommerce_store_api_product_quantity_editable',
				'woocommerce_store_api_product_quantity_minimum',
				'woocommerce_store_api_product_quantity_maximum',
				'woocommerce_store_api_product_quantity_multiple_of',
			) as $hook
		) {
			unset( $GLOBALS['aim_plugins_test_hooks'][ $hook ] );
		}
	}

	public function test_store_api_extension_exposes_only_server_owned_group_role(): void {
		$integration = new BundleBlocksIntegration();
		$integration->register();

		self::assertSame( array(), $GLOBALS['aim_plugins_test_endpoint_data'] );
		do_action( 'woocommerce_blocks_loaded' );

		self::assertCount( 1, $GLOBALS['aim_plugins_test_endpoint_data'] );
		$registration = $GLOBALS['aim_plugins_test_endpoint_data'][0];
		self::assertSame( CartItemSchema::IDENTIFIER, $registration['endpoint'] );
		self::assertSame( BundleBlocksIntegration::EXTENSION_NAMESPACE, $registration['namespace'] );

		$data_callback = $registration['data_callback'];
		$schema_callback = $registration['schema_callback'];
		self::assertIsCallable( $data_callback );
		self::assertIsCallable( $schema_callback );

		$data = $data_callback(
			array(
				Metadata::ROLE         => Metadata::ROLE_COMPONENT,
				Metadata::GROUP_ID     => 'private-group-id',
				Metadata::COMPONENT_ID => 'private-component-id',
			)
		);

		self::assertSame( array( 'role' => Metadata::ROLE_COMPONENT ), $data );
		self::assertSame( 'string', $schema_callback()['role']['type'] );
	}

	public function test_component_quantity_is_fixed_to_current_server_cart_quantity(): void {
		$integration = new BundleBlocksIntegration();
		$product     = new \WC_Product( 10 );
		$component   = array(
			Metadata::ROLE => Metadata::ROLE_COMPONENT,
			'quantity'     => 6,
		);

		$integration->register();

		self::assertFalse( apply_filters( 'woocommerce_store_api_product_quantity_editable', true, $product, $component ) );
		self::assertSame( 6, apply_filters( 'woocommerce_store_api_product_quantity_minimum', 1, $product, $component ) );
		self::assertSame( 6, apply_filters( 'woocommerce_store_api_product_quantity_maximum', 99, $product, $component ) );
		self::assertSame( 6, apply_filters( 'woocommerce_store_api_product_quantity_multiple_of', 1, $product, $component ) );
	}

	public function test_client_extension_input_never_creates_server_component_privileges(): void {
		$integration = new BundleBlocksIntegration();
		$product     = new \WC_Product( 10 );
		$client_only = array(
			'quantity'   => 7,
			'extensions' => array(
				BundleBlocksIntegration::EXTENSION_NAMESPACE => array( 'role' => Metadata::ROLE_COMPONENT ),
			),
		);

		self::assertTrue( $integration->filterQuantityEditable( true, $product, $client_only ) );
		self::assertSame( 3, $integration->filterQuantityLimit( 3, $product, $client_only ) );
		self::assertSame( array( 'role' => '' ), $integration->cartItemData( $client_only ) );
	}

}
