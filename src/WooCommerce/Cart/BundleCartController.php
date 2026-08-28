<?php
/**
 * Fixed bundle cart grouping and validation.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\WooCommerce\Cart;

use AIMPlugins\AdvancedBundles\Contracts\BundleComponent;
use AIMPlugins\AdvancedBundles\Contracts\BundleDefinition;
use AIMPlugins\AdvancedBundles\Domain\IntegerMath;
use AIMPlugins\AdvancedBundles\WooCommerce\AimBundleProduct;
use AIMPlugins\AdvancedBundles\WooCommerce\BundleComponentResolver;
use AIMPlugins\AdvancedBundles\WooCommerce\Metadata;
use AIMPlugins\AdvancedBundles\WooCommerce\Persistence\BundleDefinitionRepository;
use AIMPlugins\AdvancedBundles\WooCommerce\ProductTypeRegistrar;
use DomainException;
use InvalidArgumentException;
use OverflowException;
use RuntimeException;
use Throwable;

final class BundleCartController {
	private const LEGACY_INTERNAL_COMPONENT_ADD = '_aim_bundle_internal_component_add';

	private bool $mutatingGroup    = false;
	private int $componentAddDepth = 0;

	public function __construct(
		private readonly BundleDefinitionRepository $repository = new BundleDefinitionRepository(),
		private readonly BundleComponentResolver $resolver = new BundleComponentResolver(),
		private readonly WooStockValidator $stockValidator = new WooStockValidator()
	) {}

	public function register(): void {
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validateAddToCart' ), 20, 6 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'addCartItemData' ), 20, 4 );
		add_action( 'woocommerce_add_to_cart', array( $this, 'expandBundle' ), 20, 6 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'zeroParentPrices' ), 20 );
		add_action( 'woocommerce_check_cart_items', array( $this, 'validateCart' ), 20 );
		add_action( 'woocommerce_store_api_validate_cart_item', array( $this, 'validateStoreApiCartItem' ), 20, 2 );
		add_filter( 'woocommerce_update_cart_validation', array( $this, 'validateQuantityUpdate' ), 20, 4 );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'synchronizeQuantity' ), 20, 4 );
		add_action( 'woocommerce_cart_item_removed', array( $this, 'removeGroup' ), 20, 2 );
		add_action( 'woocommerce_cart_item_restored', array( $this, 'restoreGroup' ), 20, 2 );
		add_filter( 'woocommerce_cart_item_remove_link', array( $this, 'filterRemoveLink' ), 20, 2 );
		add_filter( 'woocommerce_cart_item_quantity', array( $this, 'filterQuantityHtml' ), 20, 3 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'addDisplayedItemData' ), 20, 2 );
	}

	/**
	 * @param array<string, mixed> $variation      Variation attributes.
	 * @param array<string, mixed> $cart_item_data Custom cart item data.
	 */
	public function validateAddToCart(
		bool $passed,
		int $product_id,
		int|float $quantity,
		int $variation_id = 0,
		array $variation = array(),
		array $cart_item_data = array()
	): bool {
		unset( $variation_id, $variation );

		if ( ! $passed || $this->isNestedComponentAdd( $cart_item_data ) ) {
			return $passed;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product instanceof \WC_Product || ! $product->is_type( ProductTypeRegistrar::PRODUCT_TYPE ) ) {
			return $passed;
		}

		try {
			$bundle_quantity = $this->wholeQuantity( $quantity );
			$definition      = $this->definitionForProduct( $product );
			$cart            = WC()->cart;

			$this->stockValidator->assertCandidateAvailable( $definition, $bundle_quantity, $cart );
		} catch ( DomainException | InvalidArgumentException | OverflowException $exception ) {
			wc_add_notice( wp_strip_all_tags( $exception->getMessage() ), 'error' );

			return false;
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $cart_item_data Existing cart item data.
	 * @return array<string, mixed>
	 */
	public function addCartItemData(
		array $cart_item_data,
		int $product_id,
		int $variation_id,
		int|float $quantity
	): array {
		unset( $variation_id );
		unset( $cart_item_data[ self::LEGACY_INTERNAL_COMPONENT_ADD ] );

		if ( $this->isNestedComponentAdd( $cart_item_data ) ) {
			return $cart_item_data;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product instanceof \WC_Product || ! $product->is_type( ProductTypeRegistrar::PRODUCT_TYPE ) ) {
			return $cart_item_data;
		}

		$this->wholeQuantity( $quantity );
		$definition = $this->definitionForProduct( $product );

		$cart_item_data[ Metadata::GROUP_ID ]             = wp_generate_uuid4();
		$cart_item_data[ Metadata::ROLE ]                 = Metadata::ROLE_PARENT;
		$cart_item_data[ Metadata::PARENT_PRODUCT_ID ]    = $product_id;
		$cart_item_data[ Metadata::DEFINITION ]           = $definition->toArray();
		$cart_item_data[ Metadata::DEFINITION_ID ]        = $definition->id;
		$cart_item_data[ Metadata::DEFINITION_VERSION ]   = $definition->version;
		$cart_item_data[ Metadata::SNAPSHOT_MONEY_SCALE ] = wc_get_price_decimals();

		return $cart_item_data;
	}

	/**
	 * @param array<string, string> $variation      Variation attributes.
	 * @param array<string, mixed>  $cart_item_data Added cart item data.
	 */
	public function expandBundle(
		string $cart_item_key,
		int $product_id,
		int|float $quantity,
		int $variation_id,
		array $variation,
		array $cart_item_data
	): void {
		unset( $variation_id, $variation );

		if ( Metadata::ROLE_PARENT !== ( $cart_item_data[ Metadata::ROLE ] ?? null ) ) {
			return;
		}

		$cart = WC()->cart;

		$added_keys = array();

		try {
			$definition      = $this->definitionFromCartItem( $cart_item_data );
			$bundle_quantity = $this->wholeQuantity( $quantity );
			$group_id        = $this->groupId( $cart_item_data );
			$product         = wc_get_product( $product_id );

			if (
				! $product instanceof \WC_Product
				|| ! $product->is_type( ProductTypeRegistrar::PRODUCT_TYPE )
				|| ( $cart_item_data[ Metadata::PARENT_PRODUCT_ID ] ?? null ) !== $product_id
				|| ( $cart_item_data[ Metadata::DEFINITION_ID ] ?? null ) !== $definition->id
				|| ( $cart_item_data[ Metadata::DEFINITION_VERSION ] ?? null ) !== $definition->version
			) {
				throw new DomainException( 'The bundle parent cart data is invalid.' );
			}

			foreach ( $this->resolver->resolveDefinition( $definition ) as $resolved ) {
				$component      = $resolved->component;
				$child_quantity = IntegerMath::multiply( $component->quantity, $bundle_quantity );
				$variation_data = array();

				if ( null !== $component->variationId ) {
					if ( ! $resolved->product instanceof \WC_Product_Variation ) {
						throw new RuntimeException( 'A resolved variation has an invalid WooCommerce product class.' );
					}

					$variation_data = $resolved->product->get_variation_attributes();
				}

				$child_item_data = array(
					Metadata::GROUP_ID             => $group_id,
					Metadata::ROLE                 => Metadata::ROLE_COMPONENT,
					Metadata::PARENT_CART_ITEM_KEY => $cart_item_key,
					Metadata::PARENT_PRODUCT_ID    => $product_id,
					Metadata::COMPONENT_ID         => $component->id,
					Metadata::COMPONENT_QUANTITY   => $component->quantity,
					Metadata::DEFINITION_ID        => $definition->id,
					Metadata::DEFINITION_VERSION   => $definition->version,
				);

				++$this->componentAddDepth;

				try {
					$added_key = $cart->add_to_cart(
						$component->productId,
						$child_quantity,
						$component->variationId ?? 0,
						$variation_data,
						$child_item_data
					);
				} finally {
					--$this->componentAddDepth;
				}

				if ( ! is_string( $added_key ) || '' === $added_key ) {
					throw new RuntimeException( 'WooCommerce rejected a bundle component.' );
				}

				$added_keys[] = $added_key;
			}
		} catch ( Throwable ) {
			$this->rollbackAddedGroup( $cart, $cart_item_key, $added_keys );

			throw new RuntimeException(
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception message; WC_Cart catches it and WooCommerce escapes the notice when rendered.
				__( 'The complete bundle could not be added. Your cart was left unchanged.', 'aim-advanced-bundles' )
			);
		}
	}

	public function zeroParentPrices( \WC_Cart $cart ): void {
		foreach ( $cart->get_cart() as $cart_item ) {
			if ( ! is_array( $cart_item ) || Metadata::ROLE_PARENT !== ( $cart_item[ Metadata::ROLE ] ?? null ) ) {
				continue;
			}

			$product = $cart_item['data'] ?? null;

			if ( $product instanceof AimBundleProduct ) {
				$product->markAsCartContainer();
			}
		}
	}

	public function validateCart(): void {
		$cart = WC()->cart;

		try {
			$this->assertGroupIntegrity( $cart );
			$this->stockValidator->assertCartAvailable( $cart );
		} catch ( DomainException | InvalidArgumentException | OverflowException $exception ) {
			wc_add_notice( wp_strip_all_tags( $exception->getMessage() ), 'error' );
		}
	}

	/**
	 * Block direct Store API mutations when a server-owned bundle group is invalid.
	 *
	 * @param array<string, mixed> $cart_item Server-side WooCommerce cart item.
	 */
	public function validateStoreApiCartItem( \WC_Product $product, array $cart_item ): void {
		unset( $product );

		if ( ! isset( $cart_item[ Metadata::ROLE ] ) ) {
			return;
		}

		$this->assertGroupIntegrity( WC()->cart );
	}

	/**
	 * @param array<string, mixed> $values Current cart item.
	 */
	public function validateQuantityUpdate(
		bool $passed,
		string $cart_item_key,
		array $values,
		int|float $quantity
	): bool {
		unset( $cart_item_key );

		if ( ! $passed || ! isset( $values[ Metadata::ROLE ] ) ) {
			return $passed;
		}

		if ( Metadata::ROLE_COMPONENT === $values[ Metadata::ROLE ] ) {
			wc_add_notice( __( 'Change the bundle quantity instead of an included component quantity.', 'aim-advanced-bundles' ), 'error' );

			return false;
		}

		try {
			$cart = WC()->cart;

			$this->stockValidator->assertProjectedGroupQuantity(
				$this->definitionFromCartItem( $values ),
				$this->groupId( $values ),
				$this->wholeQuantity( $quantity ),
				$cart
			);
		} catch ( DomainException | InvalidArgumentException | OverflowException $exception ) {
			wc_add_notice( wp_strip_all_tags( $exception->getMessage() ), 'error' );

			return false;
		}

		return true;
	}

	public function synchronizeQuantity(
		string $cart_item_key,
		int|float $quantity,
		int|float $old_quantity,
		\WC_Cart $cart
	): void {
		unset( $old_quantity );

		if ( $this->mutatingGroup ) {
			return;
		}

		$items = $cart->get_cart();
		$item  = $items[ $cart_item_key ] ?? null;

		if ( ! is_array( $item ) || ! isset( $item[ Metadata::ROLE ] ) ) {
			return;
		}

		$item            = $this->normalizeCartItem( $item );
		$group_id        = $this->groupId( $item );
		$parent_quantity = Metadata::ROLE_PARENT === $item[ Metadata::ROLE ] ? $this->wholeQuantity( $quantity ) : null;

		if ( null === $parent_quantity ) {
			foreach ( $items as $candidate ) {
				if (
					is_array( $candidate )
					&& Metadata::ROLE_PARENT === ( $candidate[ Metadata::ROLE ] ?? null )
					&& ( $candidate[ Metadata::GROUP_ID ] ?? null ) === $group_id
				) {
					$parent_quantity = $this->wholeQuantity( $candidate['quantity'] ?? 0 );
					break;
				}
			}
		}

		if ( null === $parent_quantity ) {
			return;
		}

		$this->mutatingGroup = true;

		try {
			foreach ( $items as $key => $candidate ) {
				if (
					! is_array( $candidate )
					|| Metadata::ROLE_COMPONENT !== ( $candidate[ Metadata::ROLE ] ?? null )
					|| ( $candidate[ Metadata::GROUP_ID ] ?? null ) !== $group_id
				) {
					continue;
				}

				$component_quantity = $candidate[ Metadata::COMPONENT_QUANTITY ] ?? null;

				if ( ! is_int( $component_quantity ) || $component_quantity < 1 ) {
					continue;
				}

				$cart->set_quantity(
					(string) $key,
					IntegerMath::multiply( $component_quantity, $parent_quantity ),
					false
				);
			}
		} finally {
			$this->mutatingGroup = false;
		}
	}

	public function removeGroup( string $cart_item_key, \WC_Cart $cart ): void {
		if ( $this->mutatingGroup ) {
			return;
		}

		$removed = $cart->removed_cart_contents[ $cart_item_key ] ?? null;

		if ( ! is_array( $removed ) || ! isset( $removed[ Metadata::GROUP_ID ] ) ) {
			return;
		}

		$group_id            = $this->groupId( $this->normalizeCartItem( $removed ) );
		$this->mutatingGroup = true;

		try {
			foreach ( $cart->get_cart() as $key => $item ) {
				if ( is_array( $item ) && ( $item[ Metadata::GROUP_ID ] ?? null ) === $group_id ) {
					$cart->remove_cart_item( (string) $key );
				}
			}
		} finally {
			$this->mutatingGroup = false;
		}
	}

	public function restoreGroup( string $cart_item_key, \WC_Cart $cart ): void {
		if ( $this->mutatingGroup ) {
			return;
		}

		$restored = $cart->get_cart()[ $cart_item_key ] ?? null;

		if ( ! is_array( $restored ) || ! isset( $restored[ Metadata::GROUP_ID ] ) ) {
			return;
		}

		$group_id            = $this->groupId( $this->normalizeCartItem( $restored ) );
		$this->mutatingGroup = true;

		try {
			foreach ( array_keys( $cart->removed_cart_contents ) as $removed_key ) {
				$removed = $cart->removed_cart_contents[ $removed_key ] ?? null;

				if ( is_array( $removed ) && ( $removed[ Metadata::GROUP_ID ] ?? null ) === $group_id ) {
					$cart->restore_cart_item( (string) $removed_key );
				}
			}
		} finally {
			$this->mutatingGroup = false;
		}

		try {
			$this->assertGroupIntegrity( $cart );
			$this->stockValidator->assertCartAvailable( $cart );
		} catch ( DomainException | InvalidArgumentException | OverflowException $exception ) {
			$this->removeGroupById( $cart, $group_id );
			wc_add_notice( wp_strip_all_tags( $exception->getMessage() ), 'error' );
		}
	}

	public function filterRemoveLink( string $link, string $cart_item_key ): string {
		$cart = WC()->cart;
		$item = $cart->get_cart()[ $cart_item_key ] ?? null;

		return is_array( $item ) && Metadata::ROLE_COMPONENT === ( $item[ Metadata::ROLE ] ?? null ) ? '' : $link;
	}

	/**
	 * @param array<string, mixed> $cart_item Cart item.
	 */
	public function filterQuantityHtml( string $html, string $cart_item_key, array $cart_item ): string {
		unset( $cart_item_key );

		if ( Metadata::ROLE_COMPONENT !== ( $cart_item[ Metadata::ROLE ] ?? null ) ) {
			return $html;
		}

		$quantity = $cart_item['quantity'] ?? null;
		$product  = $cart_item['data'] ?? null;

		if ( ! is_int( $quantity ) || ! $product instanceof \WC_Product ) {
			return $html;
		}

		$formatted_quantity = wc_format_stock_quantity_for_display( $quantity, $product );

		return sprintf(
			'<span class="aim-bundle-fixed-quantity" aria-label="%1$s">%2$s</span>',
			/* translators: %s: fixed component quantity. */
			esc_attr( sprintf( __( 'Fixed quantity: %s', 'aim-advanced-bundles' ), $formatted_quantity ) ),
			esc_html( $formatted_quantity )
		);
	}

	/**
	 * @param list<array<string, string>> $item_data Display data.
	 * @param array<string, mixed>        $cart_item Cart item.
	 * @return list<array<string, string>>
	 */
	public function addDisplayedItemData( array $item_data, array $cart_item ): array {
		$role = $cart_item[ Metadata::ROLE ] ?? null;

		if ( Metadata::ROLE_PARENT === $role ) {
			try {
				$definition = $this->definitionFromCartItem( $cart_item );
			} catch ( DomainException | InvalidArgumentException ) {
				return $item_data;
			}

			$item_data[] = array(
				'key'   => __( 'Bundle contents', 'aim-advanced-bundles' ),
				'value' => sprintf(
					/* translators: %d: number of fixed components. */
					_n( '%d fixed component', '%d fixed components', count( $definition->components() ), 'aim-advanced-bundles' ),
					count( $definition->components() )
				),
			);
		} elseif ( Metadata::ROLE_COMPONENT === $role ) {
			$quantity = $cart_item['quantity'] ?? null;
			$product  = $cart_item['data'] ?? null;
			$value    = __( 'Included in the bundle above', 'aim-advanced-bundles' );

			if ( is_int( $quantity ) && $quantity > 0 && $product instanceof \WC_Product ) {
				$value = sprintf(
					/* translators: %s: fixed component quantity. */
					__( 'Included in the bundle above. Fixed quantity: %s', 'aim-advanced-bundles' ),
					wc_format_stock_quantity_for_display( $quantity, $product )
				);
			}

			$item_data[] = array(
				'key'   => __( 'Bundle component', 'aim-advanced-bundles' ),
				'value' => $value,
			);
		}

		return $item_data;
	}

	private function definitionForProduct( \WC_Product $product ): BundleDefinition {
		$definition = $this->repository->find( $product );

		if ( null === $definition ) {
			throw new DomainException( 'This bundle has no component definition.' );
		}

		$this->resolver->resolveDefinition( $definition );

		return $definition;
	}

	/**
	 * @param array<string, mixed> $cart_item Cart item.
	 */
	private function definitionFromCartItem( array $cart_item ): BundleDefinition {
		$data = $cart_item[ Metadata::DEFINITION ] ?? null;

		if ( ! is_array( $data ) ) {
			throw new DomainException( 'A bundle in the cart has no valid definition.' );
		}

		$normalized = array();

		foreach ( $data as $key => $value ) {
			if ( ! is_string( $key ) ) {
				throw new DomainException( 'A bundle in the cart has invalid definition data.' );
			}

			$normalized[ $key ] = $value;
		}

		return BundleDefinition::fromArray( $normalized );
	}

	private function wholeQuantity( mixed $quantity ): int {
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

		throw new DomainException( 'Bundle quantity must be a positive whole number.' );
	}

	/**
	 * @param array<string, mixed> $cart_item Cart item.
	 */
	private function groupId( array $cart_item ): string {
		$group_id = $cart_item[ Metadata::GROUP_ID ] ?? null;

		if ( ! is_string( $group_id ) || '' === $group_id ) {
			throw new DomainException( 'A bundle cart group has no valid identifier.' );
		}

		return $group_id;
	}

	private function assertGroupIntegrity( \WC_Cart $cart ): void {
		/** @var array<string, array{parents: list<array<string, mixed>>, components: list<array<string, mixed>>}> $groups */
		$groups = array();

		foreach ( $cart->get_cart() as $item ) {
			if ( ! is_array( $item ) || ! isset( $item[ Metadata::ROLE ] ) ) {
				continue;
			}

			$item                  = $this->normalizeCartItem( $item );
			$group_id              = $this->groupId( $item );
			$groups[ $group_id ] ??= array(
				'parents'    => array(),
				'components' => array(),
			);

			if ( Metadata::ROLE_PARENT === $item[ Metadata::ROLE ] ) {
				$groups[ $group_id ]['parents'][] = $item;
			} elseif ( Metadata::ROLE_COMPONENT === $item[ Metadata::ROLE ] ) {
				$groups[ $group_id ]['components'][] = $item;
			} else {
				throw new DomainException( 'A bundle cart group has an invalid item role.' );
			}
		}

		foreach ( $groups as $group ) {
			if ( 1 !== count( $group['parents'] ) ) {
				throw new DomainException( 'A bundle cart group is missing its single parent item.' );
			}

			$parent            = $group['parents'][0];
			$definition        = $this->definitionFromCartItem( $parent );
			$parent_quantity   = $this->wholeQuantity( $parent['quantity'] ?? null );
			$product           = $parent['data'] ?? null;
			$parent_product_id = $parent[ Metadata::PARENT_PRODUCT_ID ] ?? null;

			if (
				! $product instanceof \WC_Product
				|| ! is_int( $parent_product_id )
				|| $product->get_id() !== $parent_product_id
				|| ! $product->is_type( ProductTypeRegistrar::PRODUCT_TYPE )
				|| ( $parent[ Metadata::DEFINITION_ID ] ?? null ) !== $definition->id
				|| ( $parent[ Metadata::DEFINITION_VERSION ] ?? null ) !== $definition->version
			) {
				throw new DomainException( 'A bundle product no longer exists.' );
			}

			$current = $this->definitionForProduct( $product );

			if ( $current->toArray() !== $definition->toArray() ) {
				throw new DomainException( 'A bundle changed after it was added. Remove it and add the current version again.' );
			}

			$expected = array();

			foreach ( $definition->components() as $component ) {
				$expected[ $component->id ] = $component;
			}

			if ( count( $expected ) !== count( $group['components'] ) ) {
				throw new DomainException( 'A bundle cart group has missing or extra component items.' );
			}

			$seen = array();

			foreach ( $group['components'] as $component_item ) {
				$component_id = $component_item[ Metadata::COMPONENT_ID ] ?? null;
				$component    = is_string( $component_id ) ? ( $expected[ $component_id ] ?? null ) : null;

				if ( ! $component instanceof BundleComponent || isset( $seen[ $component->id ] ) ) {
					throw new DomainException( 'A bundle cart group has an unknown or duplicate component.' );
				}

				$quantity          = $component_item['quantity'] ?? null;
				$expected_quantity = IntegerMath::multiply( $component->quantity, $parent_quantity );

				if ( $quantity !== $expected_quantity ) {
					throw new DomainException( 'A bundle component quantity no longer matches its bundle.' );
				}

				$product_id   = $component_item['product_id'] ?? null;
				$variation_id = $component_item['variation_id'] ?? 0;
				$product      = $component_item['data'] ?? null;

				if (
					$component->productId !== $product_id
					|| ( $component->variationId ?? 0 ) !== $variation_id
					|| ( $component_item[ Metadata::PARENT_PRODUCT_ID ] ?? null ) !== $parent_product_id
					|| ( $component_item[ Metadata::DEFINITION_ID ] ?? null ) !== $definition->id
					|| ( $component_item[ Metadata::DEFINITION_VERSION ] ?? null ) !== $definition->version
					|| ( $component_item[ Metadata::COMPONENT_QUANTITY ] ?? null ) !== $component->quantity
					|| ! $product instanceof \WC_Product
					|| ( $variation_id > 0 ? $variation_id : $product_id ) !== $product->get_id()
				) {
					throw new DomainException( 'A bundle component identity no longer matches its definition.' );
				}

				$seen[ $component->id ] = true;
			}
		}
	}

	/**
	 * @param list<string> $added_keys Child keys added before failure.
	 */
	private function rollbackAddedGroup( \WC_Cart $cart, string $parent_key, array $added_keys ): void {
		$this->mutatingGroup = true;

		try {
			foreach ( array_reverse( $added_keys ) as $added_key ) {
				$cart->remove_cart_item( $added_key );
				unset( $cart->removed_cart_contents[ $added_key ] );
			}

			$cart->remove_cart_item( $parent_key );
			unset( $cart->removed_cart_contents[ $parent_key ] );
		} finally {
			$this->mutatingGroup = false;
		}
	}

	private function removeGroupById( \WC_Cart $cart, string $group_id ): void {
		$this->mutatingGroup = true;

		try {
			foreach ( $cart->get_cart() as $key => $item ) {
				if ( is_array( $item ) && ( $item[ Metadata::GROUP_ID ] ?? null ) === $group_id ) {
					$cart->remove_cart_item( (string) $key );
				}
			}
		} finally {
			$this->mutatingGroup = false;
		}
	}

	/**
	 * Only controller-created recursive component adds may skip parent orchestration.
	 *
	 * @param array<string, mixed> $cart_item_data Cart item data.
	 */
	private function isNestedComponentAdd( array $cart_item_data ): bool {
		return $this->componentAddDepth > 0
			&& Metadata::ROLE_COMPONENT === ( $cart_item_data[ Metadata::ROLE ] ?? null )
			&& is_string( $cart_item_data[ Metadata::GROUP_ID ] ?? null );
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
