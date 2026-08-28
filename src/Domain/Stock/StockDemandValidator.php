<?php
/**
 * Pure aggregate stock-demand validation.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Domain\Stock;

use DomainException;

final class StockDemandValidator {
	/**
	 * @param array<array-key, mixed> $demands Aggregate quantities by stock-owner ID.
	 * @param array<array-key, mixed> $states  Stock state by stock-owner ID.
	 */
	public function assertAvailable( array $demands, array $states ): void {
		foreach ( $demands as $stock_owner_id => $quantity ) {
			if (
				! is_int( $stock_owner_id )
				|| $stock_owner_id < 1
				|| ( ! is_int( $quantity ) && ! is_float( $quantity ) )
				|| ! is_finite( (float) $quantity )
				|| $quantity <= 0
			) {
				throw new DomainException( 'Aggregate stock demand contains an invalid identity or quantity.' );
			}

			$state = $states[ $stock_owner_id ] ?? null;

			if ( ! $state instanceof StockState || $state->stockOwnerId !== $stock_owner_id ) {
				throw new DomainException( 'Aggregate stock demand has no matching stock state.' );
			}

			if ( ! $state->inStock ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception; Woo adapters strip tags before passing the message to a notice API.
				throw new DomainException( sprintf( '%s is out of stock.', $state->name ) );
			}

			if (
				$state->managingStock
				&& ! $state->backordersAllowed
				&& null !== $state->availableQuantity
				&& $quantity > $state->availableQuantity
			) {
				throw new DomainException(
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception; Woo adapters strip tags before passing the message to a notice API.
					sprintf( '%s does not have enough stock for all items in this cart.', $state->name )
				);
			}
		}
	}
}
