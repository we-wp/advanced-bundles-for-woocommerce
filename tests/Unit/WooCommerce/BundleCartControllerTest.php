<?php
/**
 * Fixed bundle cart orchestration tests.
 *
 * @package AIMPlugins\AdvancedBundles\Tests
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Tests\Unit\WooCommerce;

use AIMPlugins\AdvancedBundles\Contracts\BundleComponent;
use AIMPlugins\AdvancedBundles\Contracts\BundleDefinition;
use AIMPlugins\AdvancedBundles\WooCommerce\AimBundleProduct;
use AIMPlugins\AdvancedBundles\WooCommerce\Cart\BundleCartController;
use AIMPlugins\AdvancedBundles\WooCommerce\Metadata;
use AIMPlugins\AdvancedBundles\WooCommerce\Persistence\BundleDefinitionRepository;
use DomainException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BundleCartControllerTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['aim_plugins_test_products'] = array();
		$GLOBALS['aim_plugins_test_notices']  = array();
		$GLOBALS['aim_plugins_test_woocommerce']->cart = new \WC_Cart();
	}

	public function test_successful_expansion_creates_zero_value_parent_and_native_component_lines(): void {
		[ $controller, $cart, $parent_data ] = $this->configuredCart();

		$controller->expandBundle( 'parent', 500, 1, 0, array(), $parent_data );
		$controller->zeroParentPrices( $cart );

		self::assertCount( 3, $cart->get_cart() );
		self::assertSame( '0', $cart->get_cart()['parent']['data']->get_price() );
		self::assertSame( '0', $cart->get_cart()['parent']['data']->get_regular_price() );
		self::assertSame( '10.00', $cart->get_cart()['parent']['data']->get_price( 'edit' ) );
		self::assertCount( 2, array_filter( $cart->get_cart(), static fn ( array $item ): bool => Metadata::ROLE_COMPONENT === ( $item[ Metadata::ROLE ] ?? null ) ) );
	}

	public function test_partial_component_failure_rolls_back_group_and_wc_boundary_returns_false(): void {
		[ $controller, $cart, $parent_data ] = $this->configuredCart();
		$native = new \WC_Product( 900 );
		$GLOBALS['aim_plugins_test_products'][900] = $native;
		$cart->cart_contents['native'] = array(
			'product_id'   => 900,
			'variation_id' => 0,
			'quantity'     => 1,
			'data'         => $native,
		);
		$cart->failProductIds = array( 102 );

		$result = $this->simulateWooAddBoundary( $controller, $parent_data );

		self::assertFalse( $result );
		self::assertSame( array( 'native' ), array_keys( $cart->get_cart() ) );
		self::assertSame( array(), $cart->removed_cart_contents );
		self::assertSame( 'error', $GLOBALS['aim_plugins_test_notices'][0][0] );
		self::assertStringContainsString( 'cart was left unchanged', $GLOBALS['aim_plugins_test_notices'][0][1] );
	}

	public function test_candidate_stock_includes_existing_native_cart_demand(): void {
		[ $controller, $cart ] = $this->configuredCart();
		$child = $GLOBALS['aim_plugins_test_products'][101];
		$child->set_manage_stock( true );
		$child->set_stock_quantity( 5 );
		$cart->cart_contents['native-child'] = array(
			'product_id'   => 101,
			'variation_id' => 0,
			'quantity'     => 4,
			'data'         => $child,
		);

		$passed = $controller->validateAddToCart( true, 500, 1 );

		self::assertFalse( $passed );
		self::assertStringContainsString( 'not have enough stock', $GLOBALS['aim_plugins_test_notices'][0][1] );
	}

	public function test_injected_legacy_internal_marker_never_bypasses_parent_validation(): void {
		[ $controller ] = $this->configuredCart();
		$child = $GLOBALS['aim_plugins_test_products'][101];
		$child->set_in_stock( false );
		$injected = array(
			'_aim_bundle_internal_component_add' => true,
			Metadata::GROUP_ID                    => 'attacker-supplied-group',
			Metadata::ROLE                        => Metadata::ROLE_COMPONENT,
		);

		self::assertFalse( $controller->validateAddToCart( true, 500, 1, 0, array(), $injected ) );

		$parent_data = $controller->addCartItemData( $injected, 500, 0, 1 );
		self::assertArrayNotHasKey( '_aim_bundle_internal_component_add', $parent_data );
		self::assertSame( Metadata::ROLE_PARENT, $parent_data[ Metadata::ROLE ] );
		self::assertNotSame( 'attacker-supplied-group', $parent_data[ Metadata::GROUP_ID ] );
	}

	public function test_failed_child_add_resets_private_nesting_before_next_unrelated_add(): void {
		[ $controller, $cart, $parent_data ] = $this->configuredCart();
		$invalid_bundle = new AimBundleProduct( 700 );
		$GLOBALS['aim_plugins_test_products'][700] = $invalid_bundle;
		$nested_results = array();
		$cart->failProductIds = array( 102 );
		$cart->beforeAddToCart = static function (
			int $product_id,
			int|float $quantity,
			int $variation_id,
			array $variation,
			array $cart_item_data
		) use ( $controller, &$nested_results ): void {
			unset( $product_id, $quantity, $variation_id, $variation );
			$nested_results[] = $controller->validateAddToCart( true, 700, 1, 0, array(), $cart_item_data );
		};

		self::assertFalse( $this->simulateWooAddBoundary( $controller, $parent_data ) );
		self::assertNotEmpty( $nested_results );
		self::assertNotContains( false, $nested_results );

		$cart->beforeAddToCart = null;
		$GLOBALS['aim_plugins_test_notices'] = array();
		self::assertFalse( $controller->validateAddToCart( true, 700, 1 ) );
		self::assertStringContainsString( 'no component definition', $GLOBALS['aim_plugins_test_notices'][0][1] );
	}

	public function test_group_remove_and_restore_keep_parent_and_components_together(): void {
		[ $controller, $cart, $parent_data ] = $this->configuredCart();
		$controller->expandBundle( 'parent', 500, 1, 0, array(), $parent_data );

		self::assertTrue( $cart->remove_cart_item( 'parent' ) );
		$controller->removeGroup( 'parent', $cart );
		self::assertCount( 0, $cart->get_cart() );
		self::assertCount( 3, $cart->removed_cart_contents );

		self::assertTrue( $cart->restore_cart_item( 'parent' ) );
		$controller->restoreGroup( 'parent', $cart );
		self::assertCount( 3, $cart->get_cart() );
		self::assertCount( 0, $cart->removed_cart_contents );
	}

	public function test_direct_component_removal_removes_the_complete_group(): void {
		[ $controller, $cart, $parent_data ] = $this->configuredCart();
		$controller->expandBundle( 'parent', 500, 1, 0, array(), $parent_data );
		$component_key = '';

		foreach ( $cart->get_cart() as $key => $item ) {
			if ( Metadata::ROLE_COMPONENT === ( $item[ Metadata::ROLE ] ?? null ) ) {
				$component_key = (string) $key;
				break;
			}
		}

		self::assertNotSame( '', $component_key );
		self::assertTrue( $cart->remove_cart_item( $component_key ) );
		$controller->removeGroup( $component_key, $cart );
		self::assertSame( array(), $cart->get_cart() );
	}

	public function test_parent_quantity_update_recomputes_every_fixed_child_quantity(): void {
		[ $controller, $cart, $parent_data ] = $this->configuredCart();
		$controller->expandBundle( 'parent', 500, 1, 0, array(), $parent_data );
		$cart->cart_contents['parent']['quantity'] = 3;

		$controller->synchronizeQuantity( 'parent', 3, 1, $cart );

		$quantities = array();

		foreach ( $cart->get_cart() as $item ) {
			if ( Metadata::ROLE_COMPONENT === ( $item[ Metadata::ROLE ] ?? null ) ) {
				$quantities[ $item[ Metadata::COMPONENT_ID ] ] = $item['quantity'];
			}
		}

		self::assertSame( array( 'component-one' => 6, 'component-two' => 3 ), $quantities );
	}

	public function test_fractional_bundle_quantity_is_rejected(): void {
		$this->configuredCart();
		$controller = new BundleCartController();

		self::assertFalse( $controller->validateAddToCart( true, 500, 1.5 ) );
		self::assertStringContainsString( 'positive whole number', $GLOBALS['aim_plugins_test_notices'][0][1] );
	}

	public function test_checkout_revalidation_rejects_tampered_component_quantity(): void {
		[ $controller, $cart, $parent_data ] = $this->configuredCart();
		$controller->expandBundle( 'parent', 500, 1, 0, array(), $parent_data );

		foreach ( $cart->get_cart() as $key => $item ) {
			if ( Metadata::ROLE_COMPONENT === ( $item[ Metadata::ROLE ] ?? null ) ) {
				$cart->cart_contents[ $key ]['quantity'] = 99;
				break;
			}
		}

		$controller->validateCart();

		self::assertStringContainsString( 'quantity no longer matches', $GLOBALS['aim_plugins_test_notices'][0][1] );
	}

	public function test_checkout_revalidation_rejects_changed_definition_content_with_same_identity(): void {
		[ $controller, $cart, $parent_data ] = $this->configuredCart();
		$controller->expandBundle( 'parent', 500, 1, 0, array(), $parent_data );
		$bundle     = $GLOBALS['aim_plugins_test_products'][500];
		$repository = new BundleDefinitionRepository();
		$frozen     = BundleDefinition::fromArray( $parent_data[ Metadata::DEFINITION ] );

		$repository->stage(
			$bundle,
			new BundleDefinition(
				$frozen->id,
				$frozen->version,
				$frozen->schemaVersion,
				array(
					new BundleComponent( 'component-one', 101, null, 3, 0 ),
					new BundleComponent( 'component-two', 102, null, 1, 1 ),
				)
			)
		);

		$controller->validateCart();

		self::assertStringContainsString( 'changed after it was added', $GLOBALS['aim_plugins_test_notices'][0][1] );
	}

	public function test_store_api_validation_rejects_direct_component_quantity_tampering(): void {
		[ $controller, $cart, $parent_data ] = $this->configuredCart();
		$controller->expandBundle( 'parent', 500, 1, 0, array(), $parent_data );
		$component = array();

		foreach ( $cart->get_cart() as $key => $item ) {
			if ( Metadata::ROLE_COMPONENT === ( $item[ Metadata::ROLE ] ?? null ) ) {
				$cart->cart_contents[ $key ]['quantity'] = 99;
				$component = $cart->cart_contents[ $key ];
				break;
			}
		}

		self::assertNotSame( array(), $component );
		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'quantity no longer matches' );
		$controller->validateStoreApiCartItem( $component['data'], $component );
	}

	public function test_component_item_data_keeps_a_readable_fixed_quantity_label(): void {
		[ $controller, $cart, $parent_data ] = $this->configuredCart();
		$controller->expandBundle( 'parent', 500, 1, 0, array(), $parent_data );
		$component = array();

		foreach ( $cart->get_cart() as $item ) {
			if ( Metadata::ROLE_COMPONENT === ( $item[ Metadata::ROLE ] ?? null ) ) {
				$component = $item;
				break;
			}
		}

		$item_data = $controller->addDisplayedItemData( array(), $component );

		self::assertSame( 'Bundle component', $item_data[0]['key'] );
		self::assertStringContainsString( 'Fixed quantity: 2', $item_data[0]['value'] );
	}

	/**
	 * @return array{BundleCartController, \WC_Cart, array<string, mixed>}
	 */
	private function configuredCart(): array {
		$bundle = new AimBundleProduct( 500 );
		$one    = new \WC_Product( 101 );
		$two    = new \WC_Product( 102 );
		$GLOBALS['aim_plugins_test_products'] = array( 101 => $one, 102 => $two, 500 => $bundle );

		( new BundleDefinitionRepository() )->stageComponents(
			$bundle,
			array(
				new BundleComponent( 'component-one', 101, null, 2, 0 ),
				new BundleComponent( 'component-two', 102, null, 1, 1 ),
			)
		);

		$controller = new BundleCartController();
		$cart       = $GLOBALS['aim_plugins_test_woocommerce']->cart;
		$parent_data = $controller->addCartItemData( array(), 500, 0, 1 );
		$cart->cart_contents['parent'] = array_merge(
			$parent_data,
			array(
				'key'          => 'parent',
				'product_id'   => 500,
				'variation_id' => 0,
				'quantity'     => 1,
				'data'         => $bundle,
			)
		);

		return array( $controller, $cart, $parent_data );
	}

	private function simulateWooAddBoundary( BundleCartController $controller, array $parent_data ): string|false {
		try {
			$controller->expandBundle( 'parent', 500, 1, 0, array(), $parent_data );

			return 'parent';
		} catch ( RuntimeException $exception ) {
			wc_add_notice( $exception->getMessage(), 'error' );

			return false;
		}
	}
}
