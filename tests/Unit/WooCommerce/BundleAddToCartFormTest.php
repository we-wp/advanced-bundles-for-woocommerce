<?php
/**
 * Fixed-bundle storefront form tests.
 *
 * @package AIMPlugins\AdvancedBundles\Tests
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Tests\Unit\WooCommerce;

use AIMPlugins\AdvancedBundles\Contracts\BundleComponent;
use AIMPlugins\AdvancedBundles\WooCommerce\AimBundleProduct;
use AIMPlugins\AdvancedBundles\WooCommerce\Persistence\BundleDefinitionRepository;
use AIMPlugins\AdvancedBundles\WooCommerce\Storefront\BundleAddToCartForm;
use PHPUnit\Framework\TestCase;

final class BundleAddToCartFormTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['aim_plugins_test_products']          = array();
		$GLOBALS['aim_plugins_test_styles']            = array();
		$GLOBALS['aim_plugins_test_is_product']        = true;
		$GLOBALS['aim_plugins_test_queried_object_id'] = 0;
		unset( $GLOBALS['aim_plugins_test_hooks']['woocommerce_aim_bundle_add_to_cart'] );
		unset( $GLOBALS['aim_plugins_test_hooks']['wp_enqueue_scripts'] );
		unset( $GLOBALS['product'] );
	}

	protected function tearDown(): void {
		$GLOBALS['aim_plugins_test_products'] = array();
		unset( $GLOBALS['product'] );
	}

	public function test_it_renders_components_as_a_thumbnail_table_with_safe_new_tab_links(): void {
		$guide = new \WC_Product( 10 );
		$guide->set_name( 'Essential Workshop Guide' );
		$guide->set_price( '12.50' );

		$case = new \WC_Product_Variation( 12 );
		$case->set_parent_id( 11 );
		$case->set_name( 'Workshop Carry Case — Cobalt' );
		$case->set_price( '7.50' );

		$bundle     = new AimBundleProduct( 500 );
		$repository = new BundleDefinitionRepository();
		$repository->stageComponents(
			$bundle,
			array(
				new BundleComponent( 'guide', 10, null, 2, 0 ),
				new BundleComponent( 'carry-case', 11, 12, 1, 1 ),
			)
		);

		$GLOBALS['aim_plugins_test_products'] = array(
			10  => $guide,
			12  => $case,
			500 => $bundle,
		);

		$GLOBALS['product'] = $bundle;

		ob_start();
		( new BundleAddToCartForm() )->render();
		$html = ob_get_clean();

		self::assertIsString( $html );
		self::assertStringContainsString( '<table class="shop_table aim-bundle-components-table">', $html );
		self::assertStringNotContainsString( '<ul>', $html );
		self::assertSame( 2, substr_count( $html, '<th scope="row">' ) );
		self::assertSame( 2, substr_count( $html, 'class="aim-bundle-component-thumbnail"' ) );
		self::assertSame( 2, substr_count( $html, 'alt=""' ) );
		self::assertSame( 2, substr_count( $html, 'target="_blank"' ) );
		self::assertSame( 2, substr_count( $html, 'rel="noopener noreferrer"' ) );
		self::assertStringContainsString( 'https://synthetic.invalid/product/10', $html );
		self::assertStringContainsString( 'https://synthetic.invalid/product/12', $html );
		self::assertStringContainsString( 'Essential Workshop Guide', $html );
		self::assertStringContainsString( 'Workshop Carry Case — Cobalt', $html );
		self::assertStringContainsString( 'Fixed quantity: 2', $html );
		self::assertStringContainsString( 'Opens in a new tab.', $html );
	}

	public function test_it_registers_storefront_hooks_and_skips_styles_outside_product_pages(): void {
		$form = new BundleAddToCartForm();
		$form->register();

		self::assertNotEmpty( $GLOBALS['aim_plugins_test_hooks']['woocommerce_aim_bundle_add_to_cart'] ?? array() );
		self::assertNotEmpty( $GLOBALS['aim_plugins_test_hooks']['wp_enqueue_scripts'] ?? array() );

		$GLOBALS['aim_plugins_test_is_product'] = false;
		$form->enqueueStyles();

		self::assertSame( array(), $GLOBALS['aim_plugins_test_styles'] );
	}
}
