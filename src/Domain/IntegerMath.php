<?php
/**
 * Overflow-safe non-negative integer arithmetic.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Domain;

use OverflowException;

final class IntegerMath {
	public static function add( int $left, int $right ): int {
		if ( $left < 0 || $right < 0 || $left > PHP_INT_MAX - $right ) {
			throw new OverflowException( 'Non-negative integer addition overflowed.' );
		}

		return $left + $right;
	}

	public static function multiply( int $value, int $quantity ): int {
		if ( $value < 0 || $quantity < 0 || ( 0 !== $quantity && $value > intdiv( PHP_INT_MAX, $quantity ) ) ) {
			throw new OverflowException( 'Non-negative integer multiplication overflowed.' );
		}

		return $value * $quantity;
	}
}
