<?php
/**
 * Prove package inventory, ZIP integrity, checksum, and repeatability.
 *
 * @package AIMPlugins\AdvancedBundles\BuildTools
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\BuildTools;

use RuntimeException;

require_once __DIR__ . '/package-functions.php';

$root       = dirname( __DIR__ );
$check_root = $root . '/build/package-check';

aim_plugins_reset_build_directory( $root, $check_root );

$first  = aim_plugins_build_package( $root, $check_root . '/first' );
$second = aim_plugins_build_package( $root, $check_root . '/second' );

aim_plugins_verify_package( $root, $first );
aim_plugins_verify_package( $root, $second );

if ( ! hash_equals( $first['sha256'], $second['sha256'] ) ) {
	throw new RuntimeException( 'Two clean package builds produced different bytes.' );
}

$process = proc_open(
	array( 'unzip', '-t', $first['path'] ),
	array(
		0 => array( 'pipe', 'r' ),
		1 => array( 'pipe', 'w' ),
		2 => array( 'pipe', 'w' ),
	),
	$pipes,
	$root
);

if ( ! is_resource( $process ) ) {
	throw new RuntimeException( 'Unable to run unzip integrity check.' );
}

fclose( $pipes[0] );
$stdout = stream_get_contents( $pipes[1] );
$stderr = stream_get_contents( $pipes[2] );
fclose( $pipes[1] );
fclose( $pipes[2] );
$exit_code = proc_close( $process );

if ( 0 !== $exit_code ) {
	throw new RuntimeException( 'unzip -t failed: ' . trim( (string) $stdout . "\n" . (string) $stderr ) );
}

fwrite(
	STDOUT,
	sprintf(
		"Verified deterministic installable ZIP: %d files\nSHA-256 %s\n",
		$first['files'],
		$first['sha256']
	)
);
