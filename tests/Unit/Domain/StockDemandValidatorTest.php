<?php
/**
 * Aggregate stock demand tests.
 *
 * @package AIMPlugins\AdvancedBundles\Tests
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Tests\Unit\Domain;

use AIMPlugins\AdvancedBundles\Domain\Stock\StockDemandValidator;
use AIMPlugins\AdvancedBundles\Domain\Stock\StockState;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StockDemandValidatorTest extends TestCase {
	public function test_managed_stock_accepts_aggregate_demand_at_capacity(): void {
		$state = new StockState( 10, 'Synthetic mug', true, true, false, 5 );

		( new StockDemandValidator() )->assertAvailable( array( 10 => 5 ), array( 10 => $state ) );

		self::addToAssertionCount( 1 );
	}

	public function test_managed_stock_rejects_aggregate_shortage(): void {
		$state = new StockState( 10, 'Synthetic mug', true, true, false, 4 );

		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'Synthetic mug does not have enough stock' );

		( new StockDemandValidator() )->assertAvailable( array( 10 => 5 ), array( 10 => $state ) );
	}

	public function test_backorders_allow_demand_above_managed_quantity(): void {
		$state = new StockState( 10, 'Synthetic mug', true, true, true, 0 );

		( new StockDemandValidator() )->assertAvailable( array( 10 => 500 ), array( 10 => $state ) );

		self::addToAssertionCount( 1 );
	}

	public function test_out_of_stock_state_blocks_even_without_managed_stock(): void {
		$state = new StockState( 10, 'Synthetic mug', false, false, false, null );

		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'Synthetic mug is out of stock.' );

		( new StockDemandValidator() )->assertAvailable( array( 10 => 1 ), array( 10 => $state ) );
	}

	public function test_missing_stock_state_is_rejected(): void {
		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'no matching stock state' );

		( new StockDemandValidator() )->assertAvailable( array( 10 => 1 ), array() );
	}

	/**
	 * @return iterable<string, array{array<array-key, mixed>}>
	 */
	public static function invalidDemands(): iterable {
		yield 'zero quantity' => array( array( 10 => 0 ) );
		yield 'negative quantity' => array( array( 10 => -1 ) );
		yield 'non-numeric quantity' => array( array( 10 => '2' ) );
		yield 'invalid owner key' => array( array( 0 => 2 ) );
	}

	/** @param array<array-key, mixed> $demands Invalid demands. */
	#[DataProvider( 'invalidDemands' )]
	public function test_invalid_demand_data_is_rejected( array $demands ): void {
		$this->expectException( DomainException::class );

		( new StockDemandValidator() )->assertAvailable( $demands, array() );
	}
}
