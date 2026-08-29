<?php
/**
 * Public Free-package inventory tests.
 *
 * @package AIMPlugins\AdvancedBundles\Tests
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Tests\Packaging;

use PHPUnit\Framework\TestCase;

use function AIMPlugins\AdvancedBundles\BuildTools\aim_plugins_package_inventory;

require_once dirname( __DIR__, 2 ) . '/tools/package-functions.php';

final class PackageInventoryTest extends TestCase {
	public function test_inventory_contains_only_installable_free_plugin_files(): void {
		$root      = dirname( __DIR__, 2 );
		$inventory = aim_plugins_package_inventory( $root );

		self::assertCount( 43, $inventory );
		self::assertContains( 'aim-advanced-bundles.php', $inventory );
		self::assertContains( 'readme.txt', $inventory );
		self::assertContains( 'LICENSE', $inventory );
		self::assertContains( 'assets/blocks-cart.js', $inventory );
		self::assertContains( 'src/WooCommerce/Storefront/BundleAddToCartForm.php', $inventory );
		self::assertNotContains( 'composer.json', $inventory );

		foreach ( $inventory as $path ) {
			self::assertFalse( str_starts_with( $path, '.github/' ) );
			self::assertFalse( str_starts_with( $path, 'docs/' ) );
			self::assertFalse( str_starts_with( $path, 'tests/' ) );
			self::assertFalse( str_starts_with( $path, 'tools/' ) );
		}
	}

	public function test_distribution_metadata_matches_supported_free_release_contract(): void {
		$root   = dirname( __DIR__, 2 );
		$header = file_get_contents( $root . '/aim-advanced-bundles.php' );
		$readme = file_get_contents( $root . '/readme.txt' );

		self::assertNotFalse( $header );
		self::assertNotFalse( $readme );
		self::assertStringContainsString( 'Plugin Name:       Advanced Bundles for WooCommerce', $header );
		self::assertStringContainsString( 'Version:           0.1.0', $header );
		self::assertStringContainsString( 'Requires at least: 6.8', $readme );
		self::assertStringContainsString( 'Tested up to: 7.1', $readme );
		self::assertStringContainsString( 'Requires PHP: 8.2', $readme );
	}
}
