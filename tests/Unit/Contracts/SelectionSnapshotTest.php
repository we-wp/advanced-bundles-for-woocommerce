<?php
/**
 * Selection snapshot tests.
 *
 * @package AIMPlugins\AdvancedBundles\Tests
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Tests\Unit\Contracts;

use AIMPlugins\AdvancedBundles\Contracts\SelectionSnapshot;
use AIMPlugins\AdvancedBundles\Domain\SelectedComponent;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SelectionSnapshotTest extends TestCase {
	public function test_round_trip_preserves_identity_display_money_and_rules(): void {
		$snapshot = new SelectionSnapshot(
			SelectionSnapshot::CURRENT_SCHEMA_VERSION,
			'bundle-alpha',
			4,
			'EUR',
			array( new SelectedComponent( 'component-1', 10, 12, 'SYN-12', 'Synthetic mug', 2, 1500, 315, 3000, 630 ) ),
			array( 'stock-available' => true )
		);

		self::assertSame( 3000, $snapshot->subtotalMinor );
		self::assertSame( 3000, $snapshot->netMinor );
		self::assertSame( 0, $snapshot->discountMinor );
		self::assertSame( 630, $snapshot->taxMinor );
		self::assertSame( 3630, $snapshot->totalMinor );
		self::assertEquals( $snapshot, SelectionSnapshot::fromArray( $snapshot->toArray() ) );
	}

	public function test_it_rejects_tampered_totals(): void {
		$snapshot            = new SelectionSnapshot(
			SelectionSnapshot::CURRENT_SCHEMA_VERSION,
			'bundle-alpha',
			1,
			'EUR',
			array( new SelectedComponent( 'component-1', 10, null, '', 'Synthetic item', 1, 100, 21, 100, 21 ) )
		);
		$data                = $snapshot->toArray();
		$data['total_minor'] = 999;

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'totals do not match' );
		SelectionSnapshot::fromArray( $data );
	}

	public function test_it_preserves_rounded_units_and_actual_discounted_lines(): void {
		$component = new SelectedComponent(
			'component-1',
			10,
			null,
			'SYN-10',
			'Synthetic item',
			3,
			333,
			7,
			1000,
			20,
			300,
			900
		);
		$snapshot  = new SelectionSnapshot(
			SelectionSnapshot::CURRENT_SCHEMA_VERSION,
			'bundle-alpha',
			2,
			'EUR',
			array( $component )
		);

		self::assertSame( 1000, $snapshot->subtotalMinor );
		self::assertSame( 900, $snapshot->netMinor );
		self::assertSame( 100, $snapshot->discountMinor );
		self::assertSame( 920, $snapshot->totalMinor );
		self::assertEquals( $snapshot, SelectionSnapshot::fromArray( $snapshot->toArray() ) );
	}
}
