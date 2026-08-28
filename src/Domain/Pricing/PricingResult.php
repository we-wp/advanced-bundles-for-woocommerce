<?php
/**
 * Deterministic pricing result.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Domain\Pricing;

use AIMPlugins\AdvancedBundles\Domain\IntegerMath;
use InvalidArgumentException;

final readonly class PricingResult {
	public int $totalMinor;

	public function __construct(
		public int $subtotalMinor,
		public int $taxMinor
	) {
		if ( $this->subtotalMinor < 0 || $this->taxMinor < 0 ) {
			throw new InvalidArgumentException( 'Pricing result amounts must not be negative.' );
		}

		$this->totalMinor = IntegerMath::add( $this->subtotalMinor, $this->taxMinor );
	}
}
