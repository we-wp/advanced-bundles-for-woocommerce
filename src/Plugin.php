<?php
/**
 * Free plugin bootstrap coordinator.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles;

use AIMPlugins\AdvancedBundles\Domain\Pricing\SumPricingStrategy;
use AIMPlugins\AdvancedBundles\Extension\ExtensionRegistry;
use AIMPlugins\AdvancedBundles\WooCommerce\Admin\BundleProductEditor;
use AIMPlugins\AdvancedBundles\WooCommerce\Blocks\BundleBlocksIntegration;
use AIMPlugins\AdvancedBundles\WooCommerce\Cart\BundleCartController;
use AIMPlugins\AdvancedBundles\WooCommerce\Multisite\NetworkSiteBootstrapper;
use AIMPlugins\AdvancedBundles\WooCommerce\Order\OrderMetadataController;
use AIMPlugins\AdvancedBundles\WooCommerce\ProductTypeRegistrar;
use AIMPlugins\AdvancedBundles\WooCommerce\Storefront\BundleAddToCartForm;

final class Plugin {
	private static ?ExtensionRegistry $extensions = null;
	private static bool $earlyHooksRegistered     = false;
	private static bool $booted                   = false;

	/**
	 * Register hooks that WooCommerce may fire before plugins_loaded priority 20.
	 */
	public static function registerEarlyHooks(): void {
		if ( self::$earlyHooksRegistered ) {
			return;
		}

		self::$earlyHooksRegistered = true;

		( new BundleBlocksIntegration() )->register();
		( new NetworkSiteBootstrapper() )->register();
	}

	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}

		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			add_action( 'admin_notices', array( self::class, 'renderMissingWooCommerceNotice' ) );

			return;
		}

		if ( ! PluginEnvironment::supportsWooCommerceVersion( PluginEnvironment::wooCommerceVersion() ) ) {
			add_action( 'admin_notices', array( self::class, 'renderUnsupportedWooCommerceNotice' ) );

			return;
		}

		$extensions = new ExtensionRegistry();
		$extensions->registerPricingStrategy( new SumPricingStrategy() );

		/**
		 * Register Advanced Bundles extensions through the Free-owned registry.
		 *
		 * @param ExtensionRegistry $extensions Extension registry.
		 */
		do_action( 'aim_advanced_bundles_register_extensions', $extensions );
		$extensions->seal();

		self::$extensions = $extensions;
		self::$booted     = true;

		( new ProductTypeRegistrar() )->register();
		( new BundleProductEditor() )->register();
		( new BundleAddToCartForm() )->register();
		( new BundleCartController() )->register();
		( new OrderMetadataController() )->register();
	}

	public static function extensions(): ?ExtensionRegistry {
		return self::$extensions;
	}

	public static function renderMissingWooCommerceNotice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Advanced Bundles for WooCommerce requires WooCommerce to be installed and active.', 'aim-advanced-bundles' )
		);
	}

	public static function renderUnsupportedWooCommerceNotice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$message = sprintf(
			/* translators: %s: minimum supported WooCommerce version. */
			__( 'Advanced Bundles for WooCommerce requires WooCommerce %s or newer.', 'aim-advanced-bundles' ),
			PluginEnvironment::MINIMUM_WOOCOMMERCE_VERSION
		);

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html( $message )
		);
	}
}
