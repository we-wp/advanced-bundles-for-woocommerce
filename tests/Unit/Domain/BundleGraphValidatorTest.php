<?php
/**
 * Bundle graph validator tests.
 *
 * @package AIMPlugins\AdvancedBundles\Tests
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Tests\Unit\Domain;

use AIMPlugins\AdvancedBundles\Domain\Validation\BundleGraphValidator;
use AIMPlugins\AdvancedBundles\Tests\Fixtures\BundleFixtures;
use DomainException;
use PHPUnit\Framework\TestCase;

final class BundleGraphValidatorTest extends TestCase {
	public function test_it_accepts_an_acyclic_graph(): void {
		$definitions = array(
			'bundle-a' => BundleFixtures::definition( 'bundle-a', 'bundle-b' ),
			'bundle-b' => BundleFixtures::definition( 'bundle-b' ),
		);

		( new BundleGraphValidator() )->assertAcyclic( 'bundle-a', $definitions );

		self::assertTrue( true );
	}

	public function test_it_rejects_a_direct_cycle(): void {
		$definitions = array(
			'bundle-a' => BundleFixtures::definition( 'bundle-a', 'bundle-a' ),
		);

		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'bundle-a > bundle-a' );

		( new BundleGraphValidator() )->assertAcyclic( 'bundle-a', $definitions );
	}

	public function test_it_rejects_an_indirect_cycle(): void {
		$definitions = array(
			'bundle-a' => BundleFixtures::definition( 'bundle-a', 'bundle-b' ),
			'bundle-b' => BundleFixtures::definition( 'bundle-b', 'bundle-c' ),
			'bundle-c' => BundleFixtures::definition( 'bundle-c', 'bundle-a' ),
		);

		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'bundle-a > bundle-b > bundle-c > bundle-a' );

		( new BundleGraphValidator() )->assertAcyclic( 'bundle-a', $definitions );
	}

	public function test_it_rejects_a_missing_reference(): void {
		$definitions = array(
			'bundle-a' => BundleFixtures::definition( 'bundle-a', 'bundle-missing' ),
		);

		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'bundle-missing' );

		( new BundleGraphValidator() )->assertAcyclic( 'bundle-a', $definitions );
	}

	public function test_it_stops_at_the_traversal_limit(): void {
		$definitions = array(
			'bundle-a' => BundleFixtures::definition( 'bundle-a', 'bundle-b' ),
			'bundle-b' => BundleFixtures::definition( 'bundle-b' ),
		);

		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'traversal limit' );

		( new BundleGraphValidator( 1 ) )->assertAcyclic( 'bundle-a', $definitions );
	}
}
