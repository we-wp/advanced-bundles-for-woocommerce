<?php
/**
 * Static Cart/Checkout Blocks and responsive admin asset tests.
 *
 * @package AIMPlugins\AdvancedBundles\Tests
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Tests\Packaging;

use PHPUnit\Framework\TestCase;

final class BlocksAndAdminAssetsTest extends TestCase {
	public function test_blocks_script_registers_supported_component_row_filters(): void {
		$script      = $this->readOwnedFile( 'assets/blocks-cart.js' );
		$integration = $this->readOwnedFile( 'src/WooCommerce/Blocks/BundleBlocksIntegration.php' );
		$bootstrap   = $this->readOwnedFile( 'aim-advanced-bundles.php' );

		self::assertStringContainsString( '@license GPL-2.0-or-later', $script );
		self::assertStringContainsString( 'blocksCheckout.registerCheckoutFilters', $script );
		self::assertStringContainsString( 'showRemoveItemLink:', $script );
		self::assertStringContainsString( 'cartItemClass:', $script );
		self::assertStringContainsString( "=== 'component'", $script );
		self::assertStringContainsString( 'aim-bundle-component-cart-item', $script );
		self::assertStringContainsString( "array( 'wc-blocks-checkout' )", $integration );
		self::assertStringContainsString( 'PluginEnvironment::version()', $integration );
		self::assertStringContainsString( "plugins_url( 'assets/blocks-cart.js'", $integration );
		self::assertStringContainsString( "function_exists( 'is_cart' )", $integration );
		self::assertStringContainsString( "function_exists( 'is_checkout' )", $integration );
		self::assertStringContainsString( '! is_cart() && ! is_checkout()', $integration );
		self::assertStringContainsString( 'Plugin::registerEarlyHooks();', $bootstrap );
	}

	public function test_admin_component_rows_stack_without_horizontal_scrolling_at_mobile_breakpoint(): void {
		$styles = $this->readOwnedFile( 'assets/admin-components.css' );
		$editor = $this->readOwnedFile( 'src/WooCommerce/Admin/BundleProductEditor.php' );

		self::assertStringContainsString( '@media screen and (max-width: 782px)', $styles );
		self::assertStringContainsString( 'grid-template-columns: minmax(0, 1fr)', $styles );
		self::assertStringContainsString( '.aim-bundle-components-table .select2-container', $styles );
		self::assertStringContainsString( 'min-width: 0 !important', $styles );
		self::assertGreaterThanOrEqual( 3, substr_count( $styles, 'min-height: 44px' ) );
		self::assertStringContainsString( 'width: 100% !important', $styles );
		self::assertStringNotContainsString( 'overflow-x: auto', $styles );
		self::assertSame( 2, substr_count( $editor, 'class="aim-bundle-component-label"' ) );
		self::assertStringContainsString( 'data-aim-bundle-remove-component', $editor );
	}

	public function test_storefront_components_use_a_responsive_thumbnail_table_with_safe_product_links(): void {
		$styles   = $this->readOwnedFile( 'assets/storefront-bundle.css' );
		$renderer = $this->readOwnedFile( 'src/WooCommerce/Storefront/BundleAddToCartForm.php' );

		self::assertStringContainsString( '<table class="shop_table aim-bundle-components-table">', $renderer );
		self::assertStringContainsString( 'get_image(', $renderer );
		self::assertStringContainsString( 'target="_blank"', $renderer );
		self::assertStringContainsString( 'rel="noopener noreferrer"', $renderer );
		self::assertStringContainsString( "plugins_url( 'assets/storefront-bundle.css'", $renderer );
		self::assertStringContainsString( "add_action( 'wp_enqueue_scripts'", $renderer );
		self::assertStringContainsString( '@media screen and (max-width: 480px)', $styles );
		self::assertStringContainsString( 'table-layout: fixed', $styles );
		self::assertStringContainsString( 'overflow-wrap: anywhere', $styles );
		self::assertStringContainsString( 'min-height: 4.5rem', $styles );
		self::assertStringContainsString( ':focus-visible', $styles );
		self::assertStringNotContainsString( 'overflow-x: auto', $styles );
		self::assertStringNotContainsString( 'linear-gradient', $styles );
	}

	private function readOwnedFile( string $relative_path ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read-only local test fixture.
		$contents = file_get_contents( dirname( __DIR__, 2 ) . '/' . $relative_path );

		self::assertNotFalse( $contents );

		return $contents;
	}
}
