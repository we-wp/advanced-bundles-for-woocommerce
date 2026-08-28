<?php
/**
 * Preserve merchant data by default on uninstall.
 *
 * Data removal will require an explicit, separately implemented retention setting.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
