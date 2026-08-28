<?php
/**
 * Prepare a virgin network site for its first WooCommerce bootstrap.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\WooCommerce\Multisite;

use AIMPlugins\AdvancedBundles\PluginEnvironment;
use RuntimeException;
use Throwable;

final class NetworkSiteBootstrapper {
	private const BOOTSTRAP_PRIORITY = -100;

	public function __construct( private readonly ?string $plugin_basename = null ) {}

	public function register(): void {
		add_action(
			'init',
			array( $this, 'bootstrap' ),
			self::BOOTSTRAP_PRIORITY,
			0
		);
	}

	/**
	 * Create tables before WooCommerce queries them on its normal init hooks.
	 *
	 * A newly created network site may have no WooCommerce tables until its
	 * first request. Running here keeps all container services scoped to the
	 * current site. WooCommerce performs its standard installation afterward.
	 */
	public function bootstrap(): void {
		if ( ! $this->shouldBootstrap() ) {
			return;
		}

		try {
			\WC_Install::create_tables();
			$this->registerActionSchedulerTables( 'ActionScheduler_StoreSchema' );
			$this->registerActionSchedulerTables( 'ActionScheduler_LoggerSchema' );
		} catch ( Throwable ) {
			$this->signalFailure();
		}
	}

	private function shouldBootstrap(): bool {
		if (
			! is_multisite()
			|| false !== get_option( 'woocommerce_version', false )
			|| ! PluginEnvironment::supportsWooCommerceVersion( PluginEnvironment::wooCommerceVersion() )
			|| ! class_exists( 'WC_Install' )
			|| ! class_exists( 'ActionScheduler_StoreSchema' )
			|| ! class_exists( 'ActionScheduler_LoggerSchema' )
			|| ! defined( 'WC_PLUGIN_BASENAME' )
		) {
			return false;
		}

		$woocommerce_basename = constant( 'WC_PLUGIN_BASENAME' );

		if ( ! is_string( $woocommerce_basename ) || '' === $woocommerce_basename ) {
			return false;
		}

		$network_plugins = get_site_option( 'active_sitewide_plugins', array() );

		if ( ! is_array( $network_plugins ) ) {
			return false;
		}

		$plugin_basename = $this->plugin_basename ?? plugin_basename( PluginEnvironment::file() );

		return isset( $network_plugins[ $plugin_basename ] )
			&& isset( $network_plugins[ $woocommerce_basename ] );
	}

	private function registerActionSchedulerTables( string $schema_class ): void {
		if ( ! class_exists( $schema_class ) ) {
			throw new RuntimeException( 'Action Scheduler schema class is unavailable.' );
		}

		$schema          = new $schema_class();
		$register_tables = array( $schema, 'register_tables' );

		if ( ! is_callable( $register_tables ) ) {
			throw new RuntimeException( 'Action Scheduler schema registration is unavailable.' );
		}

		$register_tables( true );
	}

	private function signalFailure(): void {
		try {
			/**
			 * Fires when automatic table preparation for a virgin network site fails.
			 *
			 * @param int $site_id Current site ID.
			 */
			do_action( 'aim_advanced_bundles_multisite_bootstrap_failed', get_current_blog_id() );
		} catch ( Throwable ) {
			// Observability callbacks must not interrupt the site's first request.
			return;
		}
	}
}
