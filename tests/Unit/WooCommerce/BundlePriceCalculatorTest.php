<?php
/**
 * WooCommerce sum-price adapter tests.
 *
 * @package AIMPlugins\AdvancedBundles\Tests
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Tests\Unit\WooCommerce;

use AIMPlugins\AdvancedBundles\Contracts\BundleComponent;
use AIMPlugins\AdvancedBundles\WooCommerce\AimBundleProduct;
use AIMPlugins\AdvancedBundles\WooCommerce\Persistence\BundleDefinitionRepository;
use AIMPlugins\AdvancedBundles\WooCommerce\Pricing\BundlePriceCalculator;
use PHPUnit\Framework\TestCase;

final class BundlePriceCalculatorTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['aim_plugins_test_products'] = array();
		$GLOBALS['aim_plugins_test_prices_include_tax'] = false;
		$GLOBALS['aim_plugins_test_tax_display_shop'] = 'excl';
		$GLOBALS['aim_plugins_test_tax_rates'] = array();
	}

	public function test_it_sums_current_native_component_prices_and_fixed_quantities(): void {
		$bundle = new AimBundleProduct( 500 );
		$one    = new \WC_Product( 10 );
		$two    = new \WC_Product( 20 );
		$one->set_price( '7.25' );
		$two->set_price( '3.10' );
		$GLOBALS['aim_plugins_test_products'] = array( 10 => $one, 20 => $two, 500 => $bundle );
		( new BundleDefinitionRepository() )->stageComponents(
			$bundle,
			array(
				new BundleComponent( 'component-one', 10, null, 2, 0 ),
				new BundleComponent( 'component-two', 20, null, 1, 1 ),
			)
		);

		self::assertSame( '17.60', ( new BundlePriceCalculator() )->calculate( $bundle ) );
	}

	public function test_bundle_view_prices_ignore_stale_parent_values_after_type_conversion(): void {
		$bundle = new AimBundleProduct( 500 );
		$one    = new \WC_Product( 10 );
		$two    = new \WC_Product( 20 );
		$one->set_price( '7.25' );
		$two->set_price( '3.10' );
		$bundle->set_price( '999.00' );
		$bundle->set_regular_price( '888.00' );
		$GLOBALS['aim_plugins_test_products'] = array( 10 => $one, 20 => $two, 500 => $bundle );
		( new BundleDefinitionRepository() )->stageComponents(
			$bundle,
			array(
				new BundleComponent( 'component-one', 10, null, 2, 0 ),
				new BundleComponent( 'component-two', 20, null, 1, 1 ),
			)
		);

		self::assertSame( 'aim_bundle', $bundle->get_type() );
		self::assertSame( '17.60', $bundle->get_price() );
		self::assertSame( '17.60', $bundle->get_regular_price() );
		self::assertSame( '999.00', $bundle->get_price( 'edit' ) );
		self::assertSame( '888.00', $bundle->get_regular_price( 'edit' ) );
	}

	public function test_storefront_price_sums_mixed_tax_classes_when_catalog_prices_exclude_tax(): void {
		$bundle   = new AimBundleProduct( 500 );
		$standard = new \WC_Product( 10 );
		$reduced  = new \WC_Product( 20 );
		$standard->set_price( '10.00' );
		$standard->set_tax_class( 'standard' );
		$reduced->set_price( '10.00' );
		$reduced->set_tax_class( 'reduced' );
		$bundle->set_tax_class( 'standard' );
		$GLOBALS['aim_plugins_test_products'] = array( 10 => $standard, 20 => $reduced, 500 => $bundle );
		$GLOBALS['aim_plugins_test_prices_include_tax'] = false;
		$GLOBALS['aim_plugins_test_tax_display_shop'] = 'incl';
		$GLOBALS['aim_plugins_test_tax_rates'] = array( 'standard' => 0.20, 'reduced' => 0.10 );
		( new BundleDefinitionRepository() )->stageComponents(
			$bundle,
			array(
				new BundleComponent( 'component-standard', 10, null, 2, 0 ),
				new BundleComponent( 'component-reduced', 20, null, 1, 1 ),
			)
		);

		self::assertSame( '30.00', $bundle->get_price() );
		self::assertSame( '35.00', ( new BundlePriceCalculator() )->calculateForDisplay( $bundle ) );
		self::assertSame( '€35.00', $bundle->get_price_html() );
	}

	public function test_storefront_price_sums_mixed_tax_classes_when_catalog_prices_include_tax(): void {
		$bundle   = new AimBundleProduct( 500 );
		$standard = new \WC_Product( 10 );
		$reduced  = new \WC_Product( 20 );
		$standard->set_price( '12.00' );
		$standard->set_tax_class( 'standard' );
		$reduced->set_price( '11.00' );
		$reduced->set_tax_class( 'reduced' );
		$bundle->set_tax_class( 'standard' );
		$GLOBALS['aim_plugins_test_products'] = array( 10 => $standard, 20 => $reduced, 500 => $bundle );
		$GLOBALS['aim_plugins_test_prices_include_tax'] = true;
		$GLOBALS['aim_plugins_test_tax_display_shop'] = 'excl';
		$GLOBALS['aim_plugins_test_tax_rates'] = array( 'standard' => 0.20, 'reduced' => 0.10 );
		( new BundleDefinitionRepository() )->stageComponents(
			$bundle,
			array(
				new BundleComponent( 'component-standard', 10, null, 2, 0 ),
				new BundleComponent( 'component-reduced', 20, null, 1, 1 ),
			)
		);

		self::assertSame( '35.00', $bundle->get_price() );
		self::assertSame( '30.00', ( new BundlePriceCalculator() )->calculateForDisplay( $bundle ) );
		self::assertSame( '€30.00', $bundle->get_price_html() );
	}

	public function test_storefront_price_preserves_woocommerce_component_tax_precision_before_formatting(): void {
		$bundle   = new AimBundleProduct( 500 );
		$standard = new \WC_Product( 10 );
		$reduced  = new \WC_Product( 20 );
		$standard->set_price( '0.03' );
		$standard->set_tax_class( 'standard' );
		$reduced->set_price( '0.06' );
		$reduced->set_tax_class( 'reduced' );
		$GLOBALS['aim_plugins_test_products'] = array( 10 => $standard, 20 => $reduced, 500 => $bundle );
		$GLOBALS['aim_plugins_test_prices_include_tax'] = false;
		$GLOBALS['aim_plugins_test_tax_display_shop'] = 'incl';
		$GLOBALS['aim_plugins_test_tax_rates'] = array( 'standard' => 0.20, 'reduced' => 0.10 );
		( new BundleDefinitionRepository() )->stageComponents(
			$bundle,
			array(
				new BundleComponent( 'component-standard', 10, null, 1, 0 ),
				new BundleComponent( 'component-reduced', 20, null, 1, 1 ),
			)
		);

		self::assertSame( '0.10', ( new BundlePriceCalculator() )->calculateForDisplay( $bundle ) );
		self::assertSame( '€0.10', $bundle->get_price_html() );
	}

	public function test_cart_container_price_remains_zero(): void {
		$bundle = new AimBundleProduct( 500 );
		$bundle->markAsCartContainer();

		self::assertSame( '0', $bundle->get_price() );
		self::assertSame( '€0.00', $bundle->get_price_html() );
	}
}
