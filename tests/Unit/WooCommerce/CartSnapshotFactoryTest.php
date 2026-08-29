<?php
/**
 * Calculated cart snapshot tests.
 *
 * @package AIMPlugins\AdvancedBundles\Tests
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Tests\Unit\WooCommerce;

use AIMPlugins\AdvancedBundles\Contracts\BundleComponent;
use AIMPlugins\AdvancedBundles\Contracts\BundleDefinition;
use AIMPlugins\AdvancedBundles\WooCommerce\Metadata;
use AIMPlugins\AdvancedBundles\WooCommerce\Order\CartSnapshotFactory;
use DomainException;
use PHPUnit\Framework\TestCase;

final class CartSnapshotFactoryTest extends TestCase {
	public function test_snapshot_freezes_resolved_identity_display_and_calculated_totals(): void {
		$definition = new BundleDefinition(
			'bundle-product-500',
			3,
			BundleDefinition::CURRENT_SCHEMA_VERSION,
			array( new BundleComponent( 'component-main', 10, 12, 2, 0 ) )
		);
		$product = new \WC_Product_Variation( 12 );
		$product->set_parent_id( 10 );
		$product->set_name( 'Synthetic blue mug' );
		$product->set_sku( 'SYN-BLUE' );
		$parent = array(
			Metadata::GROUP_ID            => 'group-1',
			Metadata::DEFINITION          => $definition->toArray(),
			Metadata::SNAPSHOT_MONEY_SCALE => 2,
			'quantity'                    => 1.0,
		);
		$children = array(
			'child-1' => array(
				Metadata::GROUP_ID     => 'group-1',
				Metadata::ROLE         => Metadata::ROLE_COMPONENT,
				Metadata::COMPONENT_ID => 'component-main',
				'product_id'           => 10,
				'variation_id'         => 12,
				'quantity'             => 2,
				'data'                 => $product,
				'line_subtotal'        => '25.00',
				'line_total'           => '22.50',
				'line_tax'             => '4.73',
			),
		);

		$snapshot = ( new CartSnapshotFactory() )->create( $parent, $children );

		self::assertSame( 2500, $snapshot->subtotalMinor );
		self::assertSame( 2250, $snapshot->netMinor );
		self::assertSame( 250, $snapshot->discountMinor );
		self::assertSame( 473, $snapshot->taxMinor );
		self::assertSame( 2723, $snapshot->totalMinor );
		self::assertSame( 'SYN-BLUE', $snapshot->components()[0]->sku );
		self::assertSame( 'Synthetic blue mug', $snapshot->components()[0]->name );
		self::assertSame( 1125, $snapshot->components()[0]->unitTotalMinor );
	}

	public function test_missing_component_line_blocks_snapshot_creation(): void {
		$definition = new BundleDefinition(
			'bundle-product-500',
			1,
			BundleDefinition::CURRENT_SCHEMA_VERSION,
			array( new BundleComponent( 'component-main', 10, null, 1, 0 ) )
		);
		$parent = array(
			Metadata::GROUP_ID            => 'group-1',
			Metadata::DEFINITION          => $definition->toArray(),
			Metadata::SNAPSHOT_MONEY_SCALE => 2,
			'quantity'                    => 1,
		);

		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'does not contain every configured component' );

		( new CartSnapshotFactory() )->create( $parent, array() );
	}

	public function test_tampered_component_quantity_blocks_snapshot_creation(): void {
		[ $parent, $children ] = $this->validSnapshotSource();
		$children['child-1']['quantity'] = 3;

		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'does not match the purchased definition' );

		( new CartSnapshotFactory() )->create( $parent, $children );
	}

	public function test_tampered_component_identity_blocks_snapshot_creation(): void {
		[ $parent, $children ] = $this->validSnapshotSource();
		$children['child-1']['variation_id'] = 99;

		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'does not match the purchased definition' );

		( new CartSnapshotFactory() )->create( $parent, $children );
	}

	public function test_mismatched_cart_product_object_blocks_snapshot_creation(): void {
		[ $parent, $children ] = $this->validSnapshotSource();
		$wrong_product = new \WC_Product_Variation( 13 );
		$wrong_product->set_parent_id( 10 );
		$children['child-1']['data'] = $wrong_product;

		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'does not match the purchased definition' );

		( new CartSnapshotFactory() )->create( $parent, $children );
	}

	/**
	 * @return array{array<string, mixed>, array<string, array<string, mixed>>}
	 */
	private function validSnapshotSource(): array {
		$definition = new BundleDefinition(
			'bundle-product-500',
			1,
			BundleDefinition::CURRENT_SCHEMA_VERSION,
			array( new BundleComponent( 'component-main', 10, 12, 2, 0 ) )
		);
		$product = new \WC_Product_Variation( 12 );
		$product->set_parent_id( 10 );

		return array(
			array(
				Metadata::GROUP_ID             => 'group-1',
				Metadata::DEFINITION           => $definition->toArray(),
				Metadata::SNAPSHOT_MONEY_SCALE => 2,
				'quantity'                     => 1,
			),
			array(
				'child-1' => array(
					Metadata::GROUP_ID     => 'group-1',
					Metadata::ROLE         => Metadata::ROLE_COMPONENT,
					Metadata::COMPONENT_ID => 'component-main',
					'product_id'           => 10,
					'variation_id'         => 12,
					'quantity'             => 2,
					'data'                 => $product,
					'line_subtotal'        => '20.00',
					'line_total'           => '20.00',
					'line_tax'             => '4.20',
				),
			),
		);
	}
}
