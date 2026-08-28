<?php
/**
 * Pure stock state for one WooCommerce stock owner.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Domain\Stock;

use InvalidArgumentException;

final readonly class StockState {
	public function __construct(
		public int $stockOwnerId,
		public string $name,
		public bool $inStock,
		public bool $managingStock,
		public bool $backordersAllowed,
		public int|float|null $availableQuantity
	) {
		if ( $this->stockOwnerId < 1 ) {
			throw new InvalidArgumentException( 'Stock owner ID must be positive.' );
		}

		if ( '' === trim( $this->name ) ) {
			throw new InvalidArgumentException( 'Stock owner name must not be empty.' );
		}

		if ( $this->managingStock && null === $this->availableQuantity ) {
			throw new InvalidArgumentException( 'Managed stock requires an available quantity.' );
		}
	}
}
