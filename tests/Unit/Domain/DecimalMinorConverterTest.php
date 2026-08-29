<?php
/**
 * Decimal money conversion tests.
 *
 * @package AIMPlugins\AdvancedBundles\Tests
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Tests\Unit\Domain;

use AIMPlugins\AdvancedBundles\Domain\Money\DecimalMinorConverter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DecimalMinorConverterTest extends TestCase {
	/**
	 * @return iterable<string, array{int|float|string, int, int}>
	 */
	public static function amounts(): iterable {
		yield 'integer euros' => array( 12, 2, 1200 );
		yield 'decimal string' => array( '12.34', 2, 1234 );
		yield 'half-up rounding' => array( '12.345', 2, 1235 );
		yield 'zero-decimal currency' => array( '12.6', 0, 13 );
		yield 'three-decimal currency' => array( '0.001', 3, 1 );
	}

	#[DataProvider( 'amounts' )]
	public function test_it_converts_decimal_values_to_minor_units( int|float|string $amount, int $scale, int $expected ): void {
		self::assertSame( $expected, ( new DecimalMinorConverter() )->toMinor( $amount, $scale ) );
	}

	public function test_negative_money_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		( new DecimalMinorConverter() )->toMinor( '-0.01', 2 );
	}

	public function test_unsupported_scale_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		( new DecimalMinorConverter() )->toMinor( '1.00', 9 );
	}
}
