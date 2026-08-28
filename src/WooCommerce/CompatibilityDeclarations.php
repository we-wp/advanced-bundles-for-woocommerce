<?php
/**
 * WooCommerce feature compatibility declarations.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\WooCommerce;

use AIMPlugins\AdvancedBundles\PluginEnvironment;
use Automattic\WooCommerce\Utilities\FeaturesUtil;

final class CompatibilityDeclarations {
	public static function declareHpos(): void {
		if ( ! class_exists( FeaturesUtil::class ) ) {
			return;
		}

		FeaturesUtil::declare_compatibility( 'custom_order_tables', PluginEnvironment::file(), true );
	}

	private function __construct() {}
}
