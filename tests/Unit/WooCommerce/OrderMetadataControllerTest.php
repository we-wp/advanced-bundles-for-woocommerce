<?php
/**
 * Checkout order metadata tests.
 *
 * @package AIMPlugins\AdvancedBundles\Tests
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Tests\Unit\WooCommerce;

use AIMPlugins\AdvancedBundles\Contracts\BundleComponent;
use AIMPlugins\AdvancedBundles\Contracts\BundleDefinition;
use AIMPlugins\AdvancedBundles\Contracts\SelectionSnapshot;
use AIMPlugins\AdvancedBundles\WooCommerce\Metadata;
use AIMPlugins\AdvancedBundles\WooCommerce\Order\OrderMetadataController;
use PHPUnit\Framework\TestCase;

final class OrderMetadataControllerTest extends TestCase {
	public function test_parent_receives_linkage_and_write_once_snapshot_while_child_receives_linkage(): void {
		$definition = new BundleDefinition(
			'bundle-product-500',
			2,
			BundleDefinition::CURRENT_SCHEMA_VERSION,
			array( new BundleComponent( 'component-main', 10, null, 2, 0 ) )
		);
		$product = new \WC_Product( 10 );
		$product->set_name( 'Synthetic component' );
		$product->set_sku( 'SYN-10' );
		$parent_values = array(
			Metadata::GROUP_ID             => 'group-1',
			Metadata::ROLE                 => Metadata::ROLE_PARENT,
			Metadata::PARENT_PRODUCT_ID    => 500,
			Metadata::DEFINITION_ID        => $definition->id,
			Metadata::DEFINITION_VERSION   => $definition->version,
			Metadata::DEFINITION           => $definition->toArray(),
			Metadata::SNAPSHOT_MONEY_SCALE => 2,
			'quantity'                    => 1,
		);
		$child_values = array(
			Metadata::GROUP_ID           => 'group-1',
			Metadata::ROLE               => Metadata::ROLE_COMPONENT,
			Metadata::PARENT_PRODUCT_ID  => 500,
			Metadata::DEFINITION_ID      => $definition->id,
			Metadata::DEFINITION_VERSION => $definition->version,
			Metadata::COMPONENT_ID       => 'component-main',
			'product_id'                 => 10,
			'variation_id'               => 0,
			'quantity'                   => 2,
			'data'                       => $product,
			'line_subtotal'              => '20.00',
			'line_total'                 => '18.00',
			'line_tax'                   => '3.78',
		);
		$cart = new \WC_Cart();
		$cart->cart_contents = array( 'parent' => $parent_values, 'child' => $child_values );
		$GLOBALS['aim_plugins_test_woocommerce']->cart = $cart;
		$controller = new OrderMetadataController();
		$parent_item = new \WC_Order_Item_Product();
		$child_item  = new \WC_Order_Item_Product();

		$controller->addLineItemMetadata( $child_item, 'child', $child_values, new \WC_Order() );
		$controller->addLineItemMetadata( $parent_item, 'parent', $parent_values, new \WC_Order() );

		self::assertSame( 'group-1', $parent_item->meta[ Metadata::GROUP_ID ] );
		self::assertSame( 'component-main', $child_item->meta[ Metadata::COMPONENT_ID ] );
		self::assertArrayNotHasKey( SelectionSnapshot::ORDER_ITEM_META_KEY, $child_item->meta );
		self::assertSame( 2178, $parent_item->meta[ SelectionSnapshot::ORDER_ITEM_META_KEY ]['total_minor'] );
		self::assertSame( 'SYN-10', $parent_item->meta[ SelectionSnapshot::ORDER_ITEM_META_KEY ]['components'][0]['sku'] );
	}

	public function test_owned_order_metadata_is_hidden_from_generic_meta_output(): void {
		$hidden = ( new OrderMetadataController() )->hideInternalMetadata( array( '_existing' ) );

		self::assertContains( '_existing', $hidden );
		self::assertContains( Metadata::GROUP_ID, $hidden );
		self::assertContains( SelectionSnapshot::ORDER_ITEM_META_KEY, $hidden );
	}

	public function test_duplicate_hook_and_later_order_edit_do_not_replace_purchase_snapshot(): void {
		[ $controller, $parent_item, $parent_values, $cart ] = $this->configuredParentSnapshot();

		$controller->addLineItemMetadata( $parent_item, 'parent', $parent_values, new \WC_Order() );
		$original = $parent_item->meta[ SelectionSnapshot::ORDER_ITEM_META_KEY ];

		$cart->cart_contents['child']['line_total'] = '1.00';
		$parent_item->add_meta_data( '_synthetic_admin_order_correction', 'recorded', true );
		$controller->addLineItemMetadata( $parent_item, 'parent', $parent_values, new \WC_Order() );

		self::assertSame( $original, $parent_item->meta[ SelectionSnapshot::ORDER_ITEM_META_KEY ] );
		self::assertSame( 'recorded', $parent_item->meta['_synthetic_admin_order_correction'] );
	}

	/**
	 * @return array{OrderMetadataController, \WC_Order_Item_Product, array<string, mixed>, \WC_Cart}
	 */
	private function configuredParentSnapshot(): array {
		$definition = new BundleDefinition(
			'bundle-product-500',
			2,
			BundleDefinition::CURRENT_SCHEMA_VERSION,
			array( new BundleComponent( 'component-main', 10, null, 2, 0 ) )
		);
		$product = new \WC_Product( 10 );
		$parent_values = array(
			Metadata::GROUP_ID             => 'group-write-once',
			Metadata::ROLE                 => Metadata::ROLE_PARENT,
			Metadata::PARENT_PRODUCT_ID    => 500,
			Metadata::DEFINITION_ID        => $definition->id,
			Metadata::DEFINITION_VERSION   => $definition->version,
			Metadata::DEFINITION           => $definition->toArray(),
			Metadata::SNAPSHOT_MONEY_SCALE => 2,
			'quantity'                     => 1,
		);
		$child_values = array(
			Metadata::GROUP_ID     => 'group-write-once',
			Metadata::ROLE         => Metadata::ROLE_COMPONENT,
			Metadata::COMPONENT_ID => 'component-main',
			'product_id'           => 10,
			'variation_id'         => 0,
			'quantity'             => 2,
			'data'                 => $product,
			'line_subtotal'        => '20.00',
			'line_total'           => '18.00',
			'line_tax'             => '3.78',
		);
		$cart = new \WC_Cart();
		$cart->cart_contents = array( 'parent' => $parent_values, 'child' => $child_values );
		$GLOBALS['aim_plugins_test_woocommerce']->cart = $cart;

		return array( new OrderMetadataController(), new \WC_Order_Item_Product(), $parent_values, $cart );
	}
}
