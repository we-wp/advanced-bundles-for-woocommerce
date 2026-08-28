<?php
/**
 * Canonical identifier validation.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Domain;

use InvalidArgumentException;

final class CanonicalIdentifier {
	private const PATTERN = '/\A[a-z0-9][a-z0-9._:-]{0,127}\z/';

	public static function assert( string $value, string $field ): void {
		if ( 1 !== preg_match( self::PATTERN, $value ) ) {
			throw new InvalidArgumentException(
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal validation exception; this class performs no output.
				sprintf( '%s must be a canonical lowercase identifier.', $field )
			);
		}
	}
}
