<?php
/**
 * Parse every owned PHP file without executing it.
 *
 * @package AIMPlugins\AdvancedBundles\BuildTools
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\BuildTools;

use FilesystemIterator;
use PhpToken;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

$root      = dirname( __DIR__ );
$iterator  = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
);
/** @var list<string> $php_files */
$php_files = array();

foreach ( $iterator as $file ) {
	if ( ! $file instanceof SplFileInfo ) {
		throw new RuntimeException( 'Unable to inspect PHP file.' );
	}

	$path = $file->getPathname();

	if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
		continue;
	}

	if ( str_contains( $path, '/vendor/' ) || str_contains( $path, '/build/' ) ) {
		continue;
	}

	$php_files[] = $path;
}

sort( $php_files, SORT_STRING );

foreach ( $php_files as $path ) {
	$contents = file_get_contents( $path );

	if ( false === $contents ) {
		throw new RuntimeException( 'Unable to read PHP file: ' . $path );
	}

	PhpToken::tokenize( $contents, TOKEN_PARSE );
}

fwrite( STDOUT, sprintf( "Parsed %d PHP files.\n", count( $php_files ) ) );
