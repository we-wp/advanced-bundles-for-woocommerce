<?php
/**
 * Validated pricing input.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Domain\Pricing;

use AIMPlugins\AdvancedBundles\Domain\CanonicalIdentifier;
use InvalidArgumentException;

final readonly class ResolvedComponent {
	public function __construct(
		public string $componentId,
		public int $productId,
		public ?int $variationId,
		public int $quantity,
		public int $unitSubtotalMinor,
		public int $unitTaxMinor
	) {
		CanonicalIdentifier::assert( $this->componentId, 'Resolved component ID' );

		if ( $this->productId < 1 || ( null !== $this->variationId && $this->variationId < 1 ) ) {
			throw new InvalidArgumentException( 'Resolved product and variation IDs must be canonical positive integers.' );
		}

		if ( $this->quantity < 1 ) {
			throw new InvalidArgumentException( 'Resolved quantity must be positive.' );
		}

		if ( $this->unitSubtotalMinor < 0 || $this->unitTaxMinor < 0 ) {
			throw new InvalidArgumentException( 'Resolved monetary amounts must not be negative.' );
		}
	}
}
