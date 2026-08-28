<?php
/**
 * Parent and component order metadata with write-once purchase snapshot.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\WooCommerce\Order;

use AIMPlugins\AdvancedBundles\Contracts\SelectionSnapshot;
use AIMPlugins\AdvancedBundles\WooCommerce\Metadata;
use DomainException;

final readonly class OrderMetadataController {
	public function __construct( private CartSnapshotFactory $snapshots = new CartSnapshotFactory() ) {}

	public function register(): void {
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'addLineItemMetadata' ), 20, 4 );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hideInternalMetadata' ) );
	}

	/**
	 * @param array<string, mixed> $values Cart item values.
	 */
	public function addLineItemMetadata(
		\WC_Order_Item_Product $item,
		string $cart_item_key,
		array $values,
		\WC_Order $order
	): void {
		unset( $cart_item_key, $order );

		$role     = $values[ Metadata::ROLE ] ?? null;
		$group_id = $values[ Metadata::GROUP_ID ] ?? null;

		if ( ! is_string( $role ) || ! is_string( $group_id ) ) {
			return;
		}

		if ( ! in_array( $role, array( Metadata::ROLE_PARENT, Metadata::ROLE_COMPONENT ), true ) ) {
			throw new DomainException( 'Bundle order metadata contains an invalid role.' );
		}

		$item->add_meta_data( Metadata::GROUP_ID, $group_id, true );
		$item->add_meta_data( Metadata::ROLE, $role, true );
		$this->copyIntegerMeta( $item, $values, Metadata::PARENT_PRODUCT_ID );
		$this->copyStringMeta( $item, $values, Metadata::DEFINITION_ID );
		$this->copyIntegerMeta( $item, $values, Metadata::DEFINITION_VERSION );

		if ( Metadata::ROLE_COMPONENT === $role ) {
			$this->copyStringMeta( $item, $values, Metadata::COMPONENT_ID );

			return;
		}

		if ( $item->meta_exists( SelectionSnapshot::ORDER_ITEM_META_KEY ) ) {
			return;
		}

		$cart     = WC()->cart;
		$snapshot = $this->snapshots->create( $values, $cart->get_cart() );
		$item->add_meta_data( SelectionSnapshot::ORDER_ITEM_META_KEY, $snapshot->toArray(), true );
	}

	/**
	 * @param list<string> $hidden Existing hidden metadata keys.
	 * @return list<string>
	 */
	public function hideInternalMetadata( array $hidden ): array {
		$owned = array(
			Metadata::GROUP_ID,
			Metadata::ROLE,
			Metadata::PARENT_PRODUCT_ID,
			Metadata::DEFINITION_ID,
			Metadata::DEFINITION_VERSION,
			Metadata::COMPONENT_ID,
			SelectionSnapshot::ORDER_ITEM_META_KEY,
		);

		return array_values( array_unique( array_merge( $hidden, $owned ) ) );
	}

	/**
	 * @param array<string, mixed> $values Cart item values.
	 */
	private function copyStringMeta( \WC_Order_Item_Product $item, array $values, string $key ): void {
		$value = $values[ $key ] ?? null;

		if ( ! is_string( $value ) || '' === $value ) {
			throw new DomainException( 'Bundle order string metadata is incomplete.' );
		}

		$item->add_meta_data( $key, (string) $value, true );
	}

	/**
	 * @param array<string, mixed> $values Cart item values.
	 */
	private function copyIntegerMeta( \WC_Order_Item_Product $item, array $values, string $key ): void {
		$value = $values[ $key ] ?? null;

		if ( ! is_int( $value ) || $value < 1 ) {
			throw new DomainException( 'Bundle order integer metadata is incomplete.' );
		}

		$item->add_meta_data( $key, (string) $value, true );
	}
}
