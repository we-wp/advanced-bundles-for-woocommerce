<?php
/**
 * WooCommerce component resolution tests.
 *
 * @package AIMPlugins\AdvancedBundles\Tests
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Tests\Unit\WooCommerce;

use AIMPlugins\AdvancedBundles\Contracts\BundleComponent;
use AIMPlugins\AdvancedBundles\WooCommerce\AimBundleProduct;
use AIMPlugins\AdvancedBundles\WooCommerce\BundleComponentResolver;
use DomainException;
use PHPUnit\Framework\TestCase;

final class BundleComponentResolverTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['aim_plugins_test_products'] = array();
	}

	public function test_it_resolves_a_published_simple_product(): void {
		$product = new \WC_Product( 10 );
		$GLOBALS['aim_plugins_test_products'][10] = $product;

		$resolved = ( new BundleComponentResolver() )->resolveComponent(
			new BundleComponent( 'component-main', 10, null, 1, 0 )
		);

		self::assertSame( $product, $resolved->product );
	}

	public function test_it_resolves_an_exact_variation_with_matching_parent(): void {
		$variation = new \WC_Product_Variation( 12 );
		$variation->set_parent_id( 10 );
		$GLOBALS['aim_plugins_test_products'][12] = $variation;

		$resolved = ( new BundleComponentResolver() )->resolveComponent(
			new BundleComponent( 'component-main', 10, 12, 1, 0 )
		);

		self::assertSame( $variation, $resolved->product );
	}

	public function test_wrong_variation_parent_is_rejected(): void {
		$variation = new \WC_Product_Variation( 12 );
		$variation->set_parent_id( 99 );
		$GLOBALS['aim_plugins_test_products'][12] = $variation;

		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'no longer matches its parent' );

		( new BundleComponentResolver() )->resolveComponent( new BundleComponent( 'component-main', 10, 12, 1, 0 ) );
	}

	public function test_unpublished_product_is_rejected(): void {
		$product = new \WC_Product( 10 );
		$product->set_status( 'draft' );
		$GLOBALS['aim_plugins_test_products'][10] = $product;

		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'not currently available' );

		( new BundleComponentResolver() )->resolveComponent( new BundleComponent( 'component-main', 10, null, 1, 0 ) );
	}

	public function test_nested_bundle_product_is_rejected_by_free_resolver(): void {
		$bundle = new AimBundleProduct( 10 );
		$GLOBALS['aim_plugins_test_products'][10] = $bundle;

		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'simple product or a fixed variation' );

		( new BundleComponentResolver() )->resolveComponent( new BundleComponent( 'component-main', 10, null, 1, 0 ) );
	}
}
