<?php
/**
 * Build one deterministic installable Free-plugin ZIP.
 *
 * @package AIMPlugins\AdvancedBundles\BuildTools
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\BuildTools;

require_once __DIR__ . '/package-functions.php';

$root  = dirname( __DIR__ );
$build = aim_plugins_build_package( $root, $root . '/build' );

fwrite(
	STDOUT,
	sprintf(
		"Built %s (%d files)\nSHA-256 %s\n",
		basename( $build['path'] ),
		$build['files'],
		$build['sha256']
	)
);
