<?php
/**
 * Deterministic conversion from WooCommerce decimal values to minor units.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Domain\Money;

use AIMPlugins\AdvancedBundles\Domain\IntegerMath;
use InvalidArgumentException;

final class DecimalMinorConverter {
	public function toMinor( int|float|string $amount, int $scale ): int {
		if ( $scale < 0 || $scale > 8 || ! is_numeric( $amount ) ) {
			throw new InvalidArgumentException( 'Money amount or decimal scale is invalid.' );
		}

		$numeric = (float) $amount;

		if ( ! is_finite( $numeric ) || $numeric < 0 ) {
			throw new InvalidArgumentException( 'Money amount must be finite and non-negative.' );
		}

		$normalized           = number_format( $numeric, $scale, '.', '' );
		[ $whole, $fraction ] = array_pad( explode( '.', $normalized, 2 ), 2, '' );
		$factor               = 1;

		for ( $index = 0; $index < $scale; ++$index ) {
			$factor = IntegerMath::multiply( $factor, 10 );
		}

		return IntegerMath::add(
			IntegerMath::multiply( (int) $whole, $factor ),
			'' === $fraction ? 0 : (int) $fraction
		);
	}
}
