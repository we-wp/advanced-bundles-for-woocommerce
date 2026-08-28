<?php
/**
 * Typed access to main-file runtime constants.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles;

use RuntimeException;

final class PluginEnvironment {
	public const MINIMUM_WOOCOMMERCE_VERSION = '9.9';

	public static function file(): string {
		$value = defined( 'AIM_ADVANCED_BUNDLES_FILE' ) ? constant( 'AIM_ADVANCED_BUNDLES_FILE' ) : null;

		if ( ! is_string( $value ) || '' === $value ) {
			throw new RuntimeException( 'Advanced Bundles main file is not defined.' );
		}

		return $value;
	}

	public static function version(): string {
		$value = defined( 'AIM_ADVANCED_BUNDLES_VERSION' ) ? constant( 'AIM_ADVANCED_BUNDLES_VERSION' ) : null;

		if ( ! is_string( $value ) || '' === $value ) {
			throw new RuntimeException( 'Advanced Bundles version is not defined.' );
		}

		return $value;
	}

	public static function wooCommerceVersion(): ?string {
		$value = defined( 'WC_VERSION' ) ? constant( 'WC_VERSION' ) : null;

		return is_string( $value ) ? $value : null;
	}

	public static function supportsWooCommerceVersion( ?string $version ): bool {
		return null !== $version
			&& 1 === preg_match( '/\A[0-9]+(?:\.[0-9]+)+(?:[-+][0-9A-Za-z.-]+)?\z/', $version )
			&& version_compare( $version, self::MINIMUM_WOOCOMMERCE_VERSION, '>=' );
	}

	private function __construct() {}
}
