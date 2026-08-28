<?php
/**
 * Immutable purchase-time component state.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Domain;

use InvalidArgumentException;

final readonly class SelectedComponent {
	public int $unitTotalMinor;
	public int $lineTotalMinor;

	public function __construct(
		public string $componentId,
		public int $productId,
		public ?int $variationId,
		public string $sku,
		public string $name,
		public int $quantity,
		public int $unitSubtotalMinor,
		public int $unitTaxMinor,
		public int $lineSubtotalMinor,
		public int $lineTaxMinor,
		?int $unit_total_minor = null,
		?int $line_total_minor = null
	) {
		$this->unitTotalMinor = $unit_total_minor ?? $this->unitSubtotalMinor;
		$this->lineTotalMinor = $line_total_minor ?? $this->lineSubtotalMinor;

		CanonicalIdentifier::assert( $this->componentId, 'Selected component ID' );

		if ( $this->productId < 1 || ( null !== $this->variationId && $this->variationId < 1 ) ) {
			throw new InvalidArgumentException( 'Selected product and variation IDs must be canonical positive integers.' );
		}

		if ( '' === trim( $this->name ) ) {
			throw new InvalidArgumentException( 'Selected component name must not be empty.' );
		}

		if ( $this->quantity < 1 ) {
			throw new InvalidArgumentException( 'Selected component quantity must be positive.' );
		}

		foreach ( array( $this->unitSubtotalMinor, $this->unitTaxMinor, $this->lineSubtotalMinor, $this->lineTaxMinor, $this->unitTotalMinor, $this->lineTotalMinor ) as $amount ) {
			if ( $amount < 0 ) {
				throw new InvalidArgumentException( 'Snapshot monetary amounts must not be negative.' );
			}
		}

		if ( $this->lineTotalMinor > $this->lineSubtotalMinor ) {
			throw new InvalidArgumentException( 'Line total must not exceed line subtotal.' );
		}
	}

	/**
	 * @return array<string, int|string|null>
	 */
	public function toArray(): array {
		return array(
			'component_id'        => $this->componentId,
			'product_id'          => $this->productId,
			'variation_id'        => $this->variationId,
			'sku'                 => $this->sku,
			'name'                => $this->name,
			'quantity'            => $this->quantity,
			'unit_subtotal_minor' => $this->unitSubtotalMinor,
			'unit_tax_minor'      => $this->unitTaxMinor,
			'unit_total_minor'    => $this->unitTotalMinor,
			'line_subtotal_minor' => $this->lineSubtotalMinor,
			'line_tax_minor'      => $this->lineTaxMinor,
			'line_total_minor'    => $this->lineTotalMinor,
		);
	}

	/**
	 * @param array<string, mixed> $data Stored snapshot component data.
	 */
	public static function fromArray( array $data ): self {
		$component_id  = $data['component_id'] ?? null;
		$product_id    = $data['product_id'] ?? null;
		$variation_id  = $data['variation_id'] ?? null;
		$sku           = $data['sku'] ?? null;
		$name          = $data['name'] ?? null;
		$quantity      = $data['quantity'] ?? null;
		$unit_subtotal = $data['unit_subtotal_minor'] ?? null;
		$unit_tax      = $data['unit_tax_minor'] ?? null;
		$unit_total    = $data['unit_total_minor'] ?? null;
		$line_subtotal = $data['line_subtotal_minor'] ?? null;
		$line_tax      = $data['line_tax_minor'] ?? null;
		$line_total    = $data['line_total_minor'] ?? null;

		if ( ! is_string( $component_id ) || ! is_int( $product_id ) || ( ! is_int( $variation_id ) && null !== $variation_id ) ) {
			throw new InvalidArgumentException( 'Snapshot component identity fields have invalid types.' );
		}

		if ( ! is_string( $sku ) || ! is_string( $name ) || ! is_int( $quantity ) ) {
			throw new InvalidArgumentException( 'Snapshot component display or quantity fields have invalid types.' );
		}

		if (
			! is_int( $unit_subtotal )
			|| ! is_int( $unit_tax )
			|| ! is_int( $unit_total )
			|| ! is_int( $line_subtotal )
			|| ! is_int( $line_tax )
			|| ! is_int( $line_total )
		) {
			throw new InvalidArgumentException( 'Snapshot component monetary fields have invalid types.' );
		}

		return new self(
			$component_id,
			$product_id,
			$variation_id,
			$sku,
			$name,
			$quantity,
			$unit_subtotal,
			$unit_tax,
			$line_subtotal,
			$line_tax,
			$unit_total,
			$line_total
		);
	}
}
