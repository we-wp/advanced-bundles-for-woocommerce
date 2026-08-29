<?php
/**
 * Product type registrar tests.
 *
 * @package AIMPlugins\AdvancedBundles\Tests
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Tests\Unit\Domain;

use AIMPlugins\AdvancedBundles\WooCommerce\AimBundleProduct;
use AIMPlugins\AdvancedBundles\WooCommerce\ProductTypeRegistrar;
use PHPUnit\Framework\TestCase;

final class ProductTypeRegistrarTest extends TestCase {
	public function test_it_registers_the_fixed_product_type_identity(): void {
		$registrar = new ProductTypeRegistrar();

		self::assertSame(
			'Bundle',
			$registrar->addProductType( array() )['aim_bundle']
		);
		self::assertSame(
			AimBundleProduct::class,
			$registrar->mapProductClass( 'WC_Product_Simple', 'aim_bundle' )
		);
		self::assertSame( 'WC_Product_Simple', $registrar->mapProductClass( 'WC_Product_Simple', 'simple' ) );
	}

	public function test_product_class_reports_aim_bundle(): void {
		self::assertSame( 'aim_bundle', ( new AimBundleProduct() )->get_type() );
	}
}
