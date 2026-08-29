<?php
/**
 * Product editor save-boundary tests.
 *
 * @package AIMPlugins\AdvancedBundles\Tests
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Tests\Unit\WooCommerce;

use AIMPlugins\AdvancedBundles\WooCommerce\Admin\BundleProductEditor;
use AIMPlugins\AdvancedBundles\WooCommerce\AimBundleProduct;
use AIMPlugins\AdvancedBundles\WooCommerce\Persistence\BundleDefinitionRepository;
use PHPUnit\Framework\TestCase;

final class BundleProductEditorTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['aim_plugins_test_products'] = array();
		$GLOBALS['aim_plugins_test_can_edit_products'] = true;
		\WC_Admin_Meta_Boxes::$errors = array();
		$_POST = array();
	}

	protected function tearDown(): void {
		$_POST = array();
	}

	public function test_valid_nonce_and_capability_stage_sanitized_fixed_components(): void {
		$bundle = new AimBundleProduct( 500 );
		$child  = new \WC_Product( 10 );
		$GLOBALS['aim_plugins_test_products'] = array( 10 => $child, 500 => $bundle );
		$_POST = array(
			'aim_advanced_bundles_components_nonce' => 'synthetic-valid-nonce',
			'aim_advanced_bundles_components'       => array(
				array(
					'component_id' => '',
					'item_id'      => '10',
					'quantity'     => '2',
				),
			),
		);

		( new BundleProductEditor() )->save( $bundle );
		$definition = ( new BundleDefinitionRepository() )->find( $bundle );

		self::assertNotNull( $definition );
		self::assertSame( 10, $definition->components()[0]->productId );
		self::assertSame( 2, $definition->components()[0]->quantity );
		self::assertStringStartsWith( 'component-', $definition->components()[0]->id );
		self::assertSame( '', $bundle->get_price( 'edit' ) );
		self::assertSame( array(), \WC_Admin_Meta_Boxes::$errors );
	}

	public function test_invalid_nonce_leaves_definition_unchanged_and_adds_error(): void {
		$bundle = new AimBundleProduct( 500 );
		$_POST = array(
			'aim_advanced_bundles_components_nonce' => 'invalid',
			'aim_advanced_bundles_components'       => array(),
		);

		( new BundleProductEditor() )->save( $bundle );

		self::assertNull( ( new BundleDefinitionRepository() )->find( $bundle ) );
		self::assertCount( 1, \WC_Admin_Meta_Boxes::$errors );
		self::assertStringContainsString( 'security check failed', \WC_Admin_Meta_Boxes::$errors[0] );
	}

	public function test_missing_capability_leaves_definition_unchanged(): void {
		$bundle = new AimBundleProduct( 500 );
		$GLOBALS['aim_plugins_test_can_edit_products'] = false;
		$_POST = array( 'aim_advanced_bundles_components_nonce' => 'synthetic-valid-nonce' );

		( new BundleProductEditor() )->save( $bundle );

		self::assertNull( ( new BundleDefinitionRepository() )->find( $bundle ) );
		self::assertCount( 1, \WC_Admin_Meta_Boxes::$errors );
	}

	public function test_bundle_component_input_is_rejected(): void {
		$bundle = new AimBundleProduct( 500 );
		$nested = new AimBundleProduct( 600 );
		$GLOBALS['aim_plugins_test_products'] = array( 500 => $bundle, 600 => $nested );
		$_POST = array(
			'aim_advanced_bundles_components_nonce' => 'synthetic-valid-nonce',
			'aim_advanced_bundles_components'       => array(
				array( 'component_id' => '', 'item_id' => '600', 'quantity' => '1' ),
			),
		);

		( new BundleProductEditor() )->save( $bundle );

		self::assertNull( ( new BundleDefinitionRepository() )->find( $bundle ) );
		self::assertStringContainsString( 'only simple products', \WC_Admin_Meta_Boxes::$errors[0] );
	}

	public function test_negative_quantity_is_rejected_without_coercion(): void {
		$bundle = new AimBundleProduct( 500 );
		$child  = new \WC_Product( 10 );
		$GLOBALS['aim_plugins_test_products'] = array( 10 => $child, 500 => $bundle );
		$_POST = array(
			'aim_advanced_bundles_components_nonce' => 'synthetic-valid-nonce',
			'aim_advanced_bundles_components'       => array(
				array( 'component_id' => '', 'item_id' => '10', 'quantity' => '-2' ),
			),
		);

		( new BundleProductEditor() )->save( $bundle );

		self::assertNull( ( new BundleDefinitionRepository() )->find( $bundle ) );
		self::assertStringContainsString( 'positive whole number', \WC_Admin_Meta_Boxes::$errors[0] );
	}

	public function test_exact_variation_is_stored_with_canonical_parent_and_variation_ids(): void {
		$bundle    = new AimBundleProduct( 500 );
		$variation = new \WC_Product_Variation( 12 );
		$variation->set_parent_id( 10 );
		$GLOBALS['aim_plugins_test_products'] = array( 12 => $variation, 500 => $bundle );
		$_POST = array(
			'aim_advanced_bundles_components_nonce' => 'synthetic-valid-nonce',
			'aim_advanced_bundles_components'       => array(
				array( 'component_id' => '', 'item_id' => '12', 'quantity' => '3' ),
			),
		);

		( new BundleProductEditor() )->save( $bundle );
		$definition = ( new BundleDefinitionRepository() )->find( $bundle );

		self::assertNotNull( $definition );
		self::assertSame( 10, $definition->components()[0]->productId );
		self::assertSame( 12, $definition->components()[0]->variationId );
		self::assertSame( 3, $definition->components()[0]->quantity );
	}
}
