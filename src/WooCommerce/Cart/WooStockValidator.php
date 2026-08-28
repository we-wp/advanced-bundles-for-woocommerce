<?php
/**
 * WooCommerce adapter for aggregate cart stock validation.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\WooCommerce\Cart;

use AIMPlugins\AdvancedBundles\Contracts\BundleDefinition;
use AIMPlugins\AdvancedBundles\Domain\IntegerMath;
use AIMPlugins\AdvancedBundles\Domain\Stock\StockDemandValidator;
use AIMPlugins\AdvancedBundles\Domain\Stock\StockState;
use AIMPlugins\AdvancedBundles\WooCommerce\BundleComponentResolver;
use AIMPlugins\AdvancedBundles\WooCommerce\Metadata;
use DomainException;

final readonly class WooStockValidator {
	public function __construct(
		private BundleComponentResolver $resolver = new BundleComponentResolver(),
		private StockDemandValidator $validator = new StockDemandValidator()
	) {}

	public function assertCandidateAvailable( BundleDefinition $definition, int $bundle_quantity, \WC_Cart $cart ): void {
		if ( $bundle_quantity < 1 ) {
			throw new DomainException( 'Bundle quantity must be a positive whole number.' );
		}

		[ $demands, $products ] = $this->cartDemands( $cart );

		foreach ( $this->resolver->resolveDefinition( $definition ) as $resolved ) {
			$quantity = IntegerMath::multiply( $resolved->component->quantity, $bundle_quantity );
			$this->addDemand( $demands, $products, $resolved->product, $quantity );
		}

		$this->validator->assertAvailable( $demands, $this->stockStates( $demands, $products ) );
	}

	public function assertCartAvailable( \WC_Cart $cart ): void {
		[ $demands, $products ] = $this->cartDemands( $cart );
		$this->validator->assertAvailable( $demands, $this->stockStates( $demands, $products ) );
	}

	public function assertProjectedGroupQuantity(
		BundleDefinition $definition,
		string $group_id,
		int $bundle_quantity,
		\WC_Cart $cart
	): void {
		if ( $bundle_quantity < 1 ) {
			throw new DomainException( 'Bundle quantity must be a positive whole number.' );
		}

		[ $demands, $products ] = $this->cartDemands( $cart, $group_id );

		foreach ( $this->resolver->resolveDefinition( $definition ) as $resolved ) {
			$quantity = IntegerMath::multiply( $resolved->component->quantity, $bundle_quantity );
			$this->addDemand( $demands, $products, $resolved->product, $quantity );
		}

		$this->validator->assertAvailable( $demands, $this->stockStates( $demands, $products ) );
	}

	/**
	 * @return array{array<int, int|float>, array<int, \WC_Product>}
	 */
	private function cartDemands( \WC_Cart $cart, ?string $excluded_group_id = null ): array {
		$demands  = array();
		$products = array();

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( ! is_array( $cart_item ) ) {
				continue;
			}

			$role     = $cart_item[ Metadata::ROLE ] ?? null;
			$group_id = $cart_item[ Metadata::GROUP_ID ] ?? null;

			if ( Metadata::ROLE_PARENT === $role || ( null !== $excluded_group_id && $excluded_group_id === $group_id ) ) {
				continue;
			}

			$product  = $cart_item['data'] ?? null;
			$quantity = $cart_item['quantity'] ?? null;

			if (
				! $product instanceof \WC_Product
				|| ( ! is_int( $quantity ) && ! is_float( $quantity ) )
				|| ! is_finite( (float) $quantity )
				|| $quantity <= 0
			) {
				continue;
			}

			$this->addDemand( $demands, $products, $product, $quantity );
		}

		return array( $demands, $products );
	}

	/**
	 * @param array<int, int|float>   $demands  Aggregate demands.
	 * @param array<int, \WC_Product> $products Product representatives by stock-owner ID.
	 */
	private function addDemand( array &$demands, array &$products, \WC_Product $product, int|float $quantity ): void {
		$stock_owner_id = $product->get_stock_managed_by_id();

		if ( $stock_owner_id < 1 ) {
			throw new DomainException( 'A component has no valid WooCommerce stock owner.' );
		}

		$demands[ $stock_owner_id ]  = ( $demands[ $stock_owner_id ] ?? 0 ) + $quantity;
		$products[ $stock_owner_id ] = $product;
	}

	/**
	 * @param array<int, int|float>   $demands  Aggregate demands.
	 * @param array<int, \WC_Product> $products Product representatives by stock-owner ID.
	 * @return array<int, StockState>
	 */
	private function stockStates( array $demands, array $products ): array {
		$states = array();

		foreach ( $demands as $stock_owner_id => $quantity ) {
			unset( $quantity );

			$product = $products[ $stock_owner_id ] ?? null;
			$owner   = wc_get_product( $stock_owner_id );

			if ( $owner instanceof \WC_Product ) {
				$product = $owner;
			}

			if ( ! $product instanceof \WC_Product ) {
				throw new DomainException( 'A cart item stock owner no longer exists.' );
			}

			$states[ $stock_owner_id ] = new StockState(
				$stock_owner_id,
				$product->get_name(),
				$product->is_in_stock(),
				$product->managing_stock(),
				$product->backorders_allowed(),
				$product->get_stock_quantity()
			);
		}

		return $states;
	}
}
