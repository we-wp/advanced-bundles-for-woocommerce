<?php
/**
 * Build calculated purchase snapshots from WooCommerce cart lines.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\WooCommerce\Order;

use AIMPlugins\AdvancedBundles\Contracts\BundleDefinition;
use AIMPlugins\AdvancedBundles\Contracts\SelectionSnapshot;
use AIMPlugins\AdvancedBundles\Domain\IntegerMath;
use AIMPlugins\AdvancedBundles\Domain\Money\DecimalMinorConverter;
use AIMPlugins\AdvancedBundles\Domain\SelectedComponent;
use AIMPlugins\AdvancedBundles\WooCommerce\Metadata;
use DomainException;

final readonly class CartSnapshotFactory {
	public function __construct( private DecimalMinorConverter $money = new DecimalMinorConverter() ) {}

	/**
	 * @param array<string, mixed>   $parent_item Parent cart item.
	 * @param array<array-key, mixed> $cart_items All calculated cart items.
	 */
	public function create( array $parent_item, array $cart_items ): SelectionSnapshot {
		$definition_data = $parent_item[ Metadata::DEFINITION ] ?? null;
		$group_id        = $parent_item[ Metadata::GROUP_ID ] ?? null;
		$scale           = $parent_item[ Metadata::SNAPSHOT_MONEY_SCALE ] ?? null;

		if ( ! is_array( $definition_data ) || ! is_string( $group_id ) || '' === $group_id || ! is_int( $scale ) ) {
			throw new DomainException( 'Bundle snapshot source data is incomplete.' );
		}

		$definition      = $this->definition( $definition_data );
		$components      = array();
		$expected        = array();
		$seen            = array();
		$parent_quantity = $this->wholeQuantity( $parent_item['quantity'] ?? null, 'parent' );

		foreach ( $definition->components() as $component ) {
			$expected[ $component->id ] = $component;
		}

		foreach ( $cart_items as $cart_item ) {
			if ( ! is_array( $cart_item ) ) {
				throw new DomainException( 'Bundle snapshot cart data is invalid.' );
			}

			$cart_item = $this->normalizeCartItem( $cart_item );
			if (
				Metadata::ROLE_COMPONENT !== ( $cart_item[ Metadata::ROLE ] ?? null )
				|| ( $cart_item[ Metadata::GROUP_ID ] ?? null ) !== $group_id
			) {
				continue;
			}

			$product      = $cart_item['data'] ?? null;
			$component_id = $cart_item[ Metadata::COMPONENT_ID ] ?? null;
			$quantity     = $this->wholeQuantity( $cart_item['quantity'] ?? null, 'component' );

			if ( ! $product instanceof \WC_Product || ! is_string( $component_id ) ) {
				throw new DomainException( 'Bundle snapshot component data is incomplete.' );
			}

			$configured = $expected[ $component_id ] ?? null;

			if ( null === $configured || isset( $seen[ $component_id ] ) ) {
				throw new DomainException( 'Bundle snapshot contains an unknown or duplicate component.' );
			}

			$line_subtotal = $this->minorValue( $cart_item, 'line_subtotal', $scale );
			$line_total    = $this->minorValue( $cart_item, 'line_total', $scale );
			$line_tax      = $this->minorValue( $cart_item, 'line_tax', $scale );
			$product_id    = $cart_item['product_id'] ?? null;
			$variation_id  = $cart_item['variation_id'] ?? 0;

			if ( ! is_int( $product_id ) || ! is_int( $variation_id ) ) {
				throw new DomainException( 'Bundle snapshot product identity is invalid.' );
			}

			if (
				$configured->productId !== $product_id
				|| ( $configured->variationId ?? 0 ) !== $variation_id
				|| ( $variation_id > 0 ? $variation_id : $product_id ) !== $product->get_id()
				|| ( $variation_id > 0 && ( ! $product->is_type( 'variation' ) || $product->get_parent_id() !== $product_id ) )
				|| ( 0 === $variation_id && ! $product->is_type( 'simple' ) )
				|| IntegerMath::multiply( $configured->quantity, $parent_quantity ) !== $quantity
			) {
				throw new DomainException( 'Bundle snapshot component does not match the purchased definition.' );
			}

			$components[]          = new SelectedComponent(
				$component_id,
				$product_id,
				$variation_id > 0 ? $variation_id : null,
				$product->get_sku(),
				$product->get_name(),
				$quantity,
				$this->unitAmount( $line_subtotal, $quantity ),
				$this->unitAmount( $line_tax, $quantity ),
				$line_subtotal,
				$line_tax,
				$this->unitAmount( $line_total, $quantity ),
				$line_total
			);
			$seen[ $component_id ] = true;
		}

		if ( count( $components ) !== count( $definition->components() ) ) {
			throw new DomainException( 'Bundle snapshot does not contain every configured component.' );
		}

		return new SelectionSnapshot(
			SelectionSnapshot::CURRENT_SCHEMA_VERSION,
			$definition->id,
			$definition->version,
			strtoupper( get_woocommerce_currency() ),
			$components,
			array( 'fixed-selection-valid' => true )
		);
	}

	/**
	 * @param array<array-key, mixed> $data Stored definition data.
	 */
	private function definition( array $data ): BundleDefinition {
		$normalized = array();

		foreach ( $data as $key => $value ) {
			if ( ! is_string( $key ) ) {
				throw new DomainException( 'Bundle snapshot definition has invalid keys.' );
			}

			$normalized[ $key ] = $value;
		}

		return BundleDefinition::fromArray( $normalized );
	}

	/**
	 * @param array<string, mixed> $cart_item Cart item.
	 */
	private function minorValue( array $cart_item, string $key, int $scale ): int {
		$value = $cart_item[ $key ] ?? null;

		if ( ! is_int( $value ) && ! is_float( $value ) && ! is_string( $value ) ) {
			throw new DomainException( 'Bundle snapshot money data is incomplete.' );
		}

		return $this->money->toMinor( $value, $scale );
	}

	private function unitAmount( int $line_amount, int $quantity ): int {
		return intdiv( IntegerMath::add( $line_amount, intdiv( $quantity, 2 ) ), $quantity );
	}

	private function wholeQuantity( mixed $quantity, string $subject ): int {
		if ( is_int( $quantity ) && $quantity > 0 ) {
			return $quantity;
		}

		if (
			is_float( $quantity )
			&& is_finite( $quantity )
			&& $quantity > 0
			&& $quantity < (float) PHP_INT_MAX
			&& floor( $quantity ) === $quantity
		) {
			return (int) $quantity;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception message; callers sanitize it before any notice boundary.
		throw new DomainException( sprintf( 'Bundle snapshot %s quantity is invalid.', $subject ) );
	}

	/**
	 * @param array<array-key, mixed> $cart_item Cart item.
	 * @return array<string, mixed>
	 */
	private function normalizeCartItem( array $cart_item ): array {
		$normalized = array();

		foreach ( $cart_item as $key => $value ) {
			if ( is_string( $key ) ) {
				$normalized[ $key ] = $value;
			}
		}

		return $normalized;
	}
}
