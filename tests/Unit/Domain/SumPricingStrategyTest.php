<?php
/**
 * Sum pricing tests.
 *
 * @package AIMPlugins\AdvancedBundles\Tests
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Tests\Unit\Domain;

use AIMPlugins\AdvancedBundles\Domain\Pricing\ResolvedComponent;
use AIMPlugins\AdvancedBundles\Domain\Pricing\SumPricingStrategy;
use AIMPlugins\AdvancedBundles\Tests\Fixtures\BundleFixtures;
use DomainException;
use PHPUnit\Framework\TestCase;

final class SumPricingStrategyTest extends TestCase {
	public function test_it_sums_native_component_subtotals_and_taxes(): void {
		$result = ( new SumPricingStrategy() )->calculate(
			BundleFixtures::definition(),
			BundleFixtures::resolved()
		);

		self::assertSame( 2500, $result->subtotalMinor );
		self::assertSame( 526, $result->taxMinor );
		self::assertSame( 3026, $result->totalMinor );
	}

	public function test_it_rejects_a_product_identity_mismatch(): void {
		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'product identity' );

		( new SumPricingStrategy() )->calculate(
			BundleFixtures::definition(),
			array( new ResolvedComponent( 'component-main', 999, 202, 2, 1250, 263 ) )
		);
	}

	public function test_it_rejects_a_fixed_quantity_mismatch(): void {
		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'fixed component quantity' );

		( new SumPricingStrategy() )->calculate(
			BundleFixtures::definition(),
			array( new ResolvedComponent( 'component-main', 101, 202, 3, 1250, 263 ) )
		);
	}
}
