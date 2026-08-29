<?php
/**
 * Bundle definition tests.
 *
 * @package AIMPlugins\AdvancedBundles\Tests
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Tests\Unit\Contracts;

use AIMPlugins\AdvancedBundles\Contracts\BundleComponent;
use AIMPlugins\AdvancedBundles\Contracts\BundleDefinition;
use AIMPlugins\AdvancedBundles\Tests\Fixtures\BundleFixtures;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BundleDefinitionTest extends TestCase {
	public function test_round_trip_is_versioned_and_canonical(): void {
		$definition = BundleFixtures::definition();
		$encoded    = $definition->toArray();

		self::assertSame( 1, $encoded['schema_version'] );
		self::assertSame( 'bundle-alpha', $encoded['definition_id'] );
		self::assertArrayNotHasKey( 'sku', $encoded['components'][0] );
		self::assertEquals( $definition, BundleDefinition::fromArray( $encoded ) );
	}

	public function test_it_rejects_duplicate_component_ids(): void {
		$this->expectException( InvalidArgumentException::class );

		new BundleDefinition(
			'bundle-alpha',
			1,
			1,
			array(
				new BundleComponent( 'same-id', 1, null, 1, 0 ),
				new BundleComponent( 'same-id', 2, null, 1, 1 ),
			)
		);
	}

	public function test_it_rejects_unknown_schema_versions(): void {
		$this->expectException( InvalidArgumentException::class );

		new BundleDefinition(
			'bundle-alpha',
			1,
			99,
			array( new BundleComponent( 'component-1', 1, null, 1, 0 ) )
		);
	}
}
