<?php
/**
 * Deterministic Free-package build and verification helpers.
 *
 * @package AIMPlugins\AdvancedBundles\BuildTools
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\BuildTools;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use ZipArchive;

const PACKAGE_SLUG      = 'aim-advanced-bundles';
const SOURCE_DATE_EPOCH = 315532800;

/**
 * @return list<string>
 */
function aim_plugins_package_inventory( string $root ): array {
	$inventory_path = $root . '/packaging/' . PACKAGE_SLUG . '.txt';
	$contents       = file_get_contents( $inventory_path );

	if ( false === $contents ) {
		throw new RuntimeException( 'Unable to read package inventory.' );
	}

	$paths  = array_values( array_filter( preg_split( '/\R/', $contents ) ?: array(), static fn ( string $path ): bool => '' !== $path ) );
	$sorted = $paths;
	sort( $sorted, SORT_STRING );

	if ( $paths !== $sorted || $paths !== array_values( array_unique( $paths ) ) ) {
		throw new RuntimeException( 'Package inventory must be sorted and unique.' );
	}

	foreach ( $paths as $path ) {
		if (
			str_starts_with( $path, '/' )
			|| str_contains( $path, '..' )
			|| str_contains( $path, "\0" )
			|| str_contains( $path, '\\' )
		) {
			throw new RuntimeException( 'Unsafe package inventory path: ' . $path );
		}

		$source_path = $root . '/' . $path;

		if ( ! is_file( $source_path ) || is_link( $source_path ) ) {
			throw new RuntimeException( 'Inventory entry is not a regular owned file: ' . $path );
		}
	}

	return $paths;
}

function aim_plugins_package_version( string $root ): string {
	$plugin_file = file_get_contents( $root . '/aim-advanced-bundles.php' );

	if (
		false === $plugin_file
		|| 1 !== preg_match( '/^\s*\*\s*Version:\s*([0-9]+\.[0-9]+\.[0-9]+)\s*$/m', $plugin_file, $matches )
	) {
		throw new RuntimeException( 'Unable to read plugin version.' );
	}

	return $matches[1];
}

/**
 * @return array{path: string, checksum_path: string, sha256: string, files: int}
 */
function aim_plugins_build_package( string $root, string $output_directory ): array {
	if ( ! extension_loaded( 'zip' ) ) {
		throw new RuntimeException( 'PHP zip extension is required.' );
	}

	if ( false === putenv( 'TZ=UTC' ) || ! date_default_timezone_set( 'UTC' ) ) {
		throw new RuntimeException( 'Unable to set UTC for package build.' );
	}

	if ( ! is_dir( $output_directory ) && ! mkdir( $output_directory, 0755, true ) && ! is_dir( $output_directory ) ) {
		throw new RuntimeException( 'Unable to create package output directory.' );
	}

	$inventory    = aim_plugins_package_inventory( $root );
	$version      = aim_plugins_package_version( $root );
	$artifact     = PACKAGE_SLUG . '-' . $version . '.zip';
	$zip_path     = $output_directory . '/' . $artifact;
	$zip          = new ZipArchive();

	if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		throw new RuntimeException( 'Unable to create package ZIP.' );
	}

	foreach ( $inventory as $relative_path ) {
		$contents = file_get_contents( $root . '/' . $relative_path );

		if ( false === $contents ) {
			throw new RuntimeException( 'Unable to read package source: ' . $relative_path );
		}

		$entry_path = PACKAGE_SLUG . '/' . $relative_path;

		if (
			! $zip->addFromString( $entry_path, $contents )
			|| ! $zip->setMtimeName( $entry_path, SOURCE_DATE_EPOCH )
			|| ! $zip->setCompressionName( $entry_path, ZipArchive::CM_DEFLATE, 9 )
			|| ! $zip->setExternalAttributesName( $entry_path, ZipArchive::OPSYS_UNIX, 0100644 << 16 )
		) {
			throw new RuntimeException( 'Unable to add deterministic ZIP entry: ' . $relative_path );
		}
	}

	if ( ! $zip->close() ) {
		throw new RuntimeException( 'Unable to finalize package ZIP.' );
	}

	$sha256 = hash_file( 'sha256', $zip_path );

	if ( false === $sha256 ) {
		throw new RuntimeException( 'Unable to hash package ZIP.' );
	}

	$checksum_path = $zip_path . '.sha256';
	$checksum      = $sha256 . '  ' . basename( $zip_path ) . "\n";

	if ( false === file_put_contents( $checksum_path, $checksum, LOCK_EX ) ) {
		throw new RuntimeException( 'Unable to write package checksum.' );
	}

	return array(
		'path'          => $zip_path,
		'checksum_path' => $checksum_path,
		'sha256'        => $sha256,
		'files'         => count( $inventory ),
	);
}

/**
 * @param array{path: string, checksum_path: string, sha256: string, files: int} $build
 */
function aim_plugins_verify_package( string $root, array $build ): void {
	$inventory = aim_plugins_package_inventory( $root );
	$expected  = array_map( static fn ( string $path ): string => PACKAGE_SLUG . '/' . $path, $inventory );
	$zip       = new ZipArchive();

	if ( true !== $zip->open( $build['path'], ZipArchive::CHECKCONS ) ) {
		throw new RuntimeException( 'ZIP consistency check failed.' );
	}

	$actual = array();

	for ( $index = 0; $index < $zip->numFiles; ++$index ) {
		$name = $zip->getNameIndex( $index );

		if ( false === $name ) {
			throw new RuntimeException( 'Unable to read ZIP inventory.' );
		}

		$actual[] = $name;
	}

	if ( $expected !== $actual ) {
		throw new RuntimeException( 'ZIP inventory does not match the explicit package manifest.' );
	}

	foreach ( $inventory as $relative_path ) {
		$archive_path = PACKAGE_SLUG . '/' . $relative_path;
		$archived     = $zip->getFromName( $archive_path );
		$source       = file_get_contents( $root . '/' . $relative_path );
		$stat         = $zip->statName( $archive_path );

		if (
			false === $archived
			|| false === $source
			|| ! hash_equals( hash( 'sha256', $source ), hash( 'sha256', $archived ) )
		) {
			throw new RuntimeException( 'ZIP content differs from source: ' . $relative_path );
		}

		if ( ! is_array( $stat ) || SOURCE_DATE_EPOCH !== $stat['mtime'] ) {
			throw new RuntimeException( 'ZIP timestamp is not deterministic: ' . $relative_path );
		}
	}

	$zip->close();

	$sha256  = hash_file( 'sha256', $build['path'] );
	$checksum = file_get_contents( $build['checksum_path'] );

	if ( false === $sha256 || ! hash_equals( $build['sha256'], $sha256 ) ) {
		throw new RuntimeException( 'ZIP checksum changed after build.' );
	}

	if ( $sha256 . '  ' . basename( $build['path'] ) . "\n" !== $checksum ) {
		throw new RuntimeException( 'Checksum proof file is invalid.' );
	}
}

function aim_plugins_reset_build_directory( string $root, string $directory ): void {
	$build_root = $root . '/build/';

	if ( ! str_starts_with( $directory . '/', $build_root ) || $directory === $root . '/build' ) {
		throw new RuntimeException( 'Refusing to clear an unsafe build directory.' );
	}

	if ( ! is_dir( $directory ) ) {
		return;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $iterator as $item ) {
		if ( ! $item instanceof SplFileInfo ) {
			throw new RuntimeException( 'Unable to inspect build directory.' );
		}

		if ( $item->isDir() ) {
			if ( ! rmdir( $item->getPathname() ) ) {
				throw new RuntimeException( 'Unable to clear build directory.' );
			}
		} elseif ( ! unlink( $item->getPathname() ) ) {
			throw new RuntimeException( 'Unable to clear build file.' );
		}
	}

	if ( ! rmdir( $directory ) ) {
		throw new RuntimeException( 'Unable to remove build directory.' );
	}
}
