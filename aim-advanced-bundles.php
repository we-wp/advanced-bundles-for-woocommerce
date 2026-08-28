<?php
/**
 * Plugin Name:       Advanced Bundles for WooCommerce
 * Description:       Create fixed product bundles while WooCommerce keeps pricing, stock, tax, shipping, and order lines.
 * Version:           0.1.0
 * Requires at least: 6.8
 * Requires PHP:      8.2
 * Requires Plugins:  woocommerce
 * WC requires at least: 9.9
 * Author:            UAB BusinessPress
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       aim-advanced-bundles
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

use AIMPlugins\AdvancedBundles\Plugin;
use AIMPlugins\AdvancedBundles\WooCommerce\CompatibilityDeclarations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AIM_ADVANCED_BUNDLES_VERSION', '0.1.0' );
define( 'AIM_ADVANCED_BUNDLES_FILE', __FILE__ );

require_once __DIR__ . '/autoload.php';

Plugin::registerEarlyHooks();

add_action( 'before_woocommerce_init', array( CompatibilityDeclarations::class, 'declareHpos' ) );
add_action( 'plugins_loaded', array( Plugin::class, 'boot' ), 20 );
