<?php
/**
 * Bundle component tests.
 *
 * @package AIMPlugins\AdvancedBundles\Tests
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Tests\Unit\Contracts;

use AIMPlugins\AdvancedBundles\Contracts\BundleComponent;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BundleComponentTest extends TestCase {
	/**
	 * @return iterable<string, array{string, int, int|null, int, int, string|null}>
	 */
	public static function invalidInputs(): iterable {
		yield 'noncanonical component id' => array( 'Component 1', 1, null, 1, 0, null );
		yield 'nonpositive product id' => array( 'component-1', 0, null, 1, 0, null );
		yield 'nonpositive variation id' => array( 'component-1', 1, 0, 1, 0, null );
		yield 'zero quantity' => array( 'component-1', 1, null, 0, 0, null );
		yield 'excessive quantity' => array( 'component-1', 1, null, 1000001, 0, null );
		yield 'negative position' => array( 'component-1', 1, null, 1, -1, null );
		yield 'noncanonical nested id' => array( 'component-1', 1, null, 1, 0, 'Bundle B' );
	}

	#[DataProvider( 'invalidInputs' )]
	public function test_it_rejects_invalid_canonical_fields(
		string $id,
		int $product_id,
		?int $variation_id,
		int $quantity,
		int $position,
		?string $reference
	): void {
		$this->expectException( InvalidArgumentException::class );

		new BundleComponent( $id, $product_id, $variation_id, $quantity, $position, $reference );
	}

	public function test_round_trip_preserves_canonical_ids_and_quantity(): void {
		$component = new BundleComponent( 'component-1', 11, 19, 3, 2, 'nested-bundle' );

		self::assertEquals( $component, BundleComponent::fromArray( $component->toArray() ) );
	}
}
