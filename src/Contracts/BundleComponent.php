<?php
/**
 * Canonical component contract.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Contracts;

use AIMPlugins\AdvancedBundles\Domain\CanonicalIdentifier;
use InvalidArgumentException;

final readonly class BundleComponent {
	public const MAX_QUANTITY = 1000000;

	public function __construct(
		public string $id,
		public int $productId,
		public ?int $variationId,
		public int $quantity,
		public int $position,
		public ?string $referencedBundleDefinitionId = null
	) {
		CanonicalIdentifier::assert( $this->id, 'Component ID' );

		if ( $this->productId < 1 ) {
			throw new InvalidArgumentException( 'Product ID must be a positive integer.' );
		}

		if ( null !== $this->variationId && $this->variationId < 1 ) {
			throw new InvalidArgumentException( 'Variation ID must be null or a positive integer.' );
		}

		if ( $this->quantity < 1 || $this->quantity > self::MAX_QUANTITY ) {
			throw new InvalidArgumentException( 'Quantity must be between 1 and 1000000.' );
		}

		if ( $this->position < 0 ) {
			throw new InvalidArgumentException( 'Position must be zero or greater.' );
		}

		if ( null !== $this->referencedBundleDefinitionId ) {
			CanonicalIdentifier::assert( $this->referencedBundleDefinitionId, 'Referenced bundle definition ID' );
		}
	}

	/**
	 * @return array<string, int|string|null>
	 */
	public function toArray(): array {
		return array(
			'id'                              => $this->id,
			'product_id'                      => $this->productId,
			'variation_id'                    => $this->variationId,
			'quantity'                        => $this->quantity,
			'position'                        => $this->position,
			'referenced_bundle_definition_id' => $this->referencedBundleDefinitionId,
		);
	}

	/**
	 * @param array<string, mixed> $data Stored component data.
	 */
	public static function fromArray( array $data ): self {
		$id           = $data['id'] ?? null;
		$product_id   = $data['product_id'] ?? null;
		$variation_id = $data['variation_id'] ?? null;
		$quantity     = $data['quantity'] ?? null;
		$position     = $data['position'] ?? null;
		$reference    = $data['referenced_bundle_definition_id'] ?? null;

		if ( ! is_string( $id ) || ! is_int( $product_id ) || ( ! is_int( $variation_id ) && null !== $variation_id ) ) {
			throw new InvalidArgumentException( 'Component identity fields have invalid types.' );
		}

		if ( ! is_int( $quantity ) || ! is_int( $position ) || ( ! is_string( $reference ) && null !== $reference ) ) {
			throw new InvalidArgumentException( 'Component quantity, position, or reference has an invalid type.' );
		}

		return new self( $id, $product_id, $variation_id, $quantity, $position, $reference );
	}
}
