<?php
/**
 * Original synthetic bundle fixtures.
 *
 * @package AIMPlugins\AdvancedBundles\Tests
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Tests\Fixtures;

use AIMPlugins\AdvancedBundles\Contracts\BundleComponent;
use AIMPlugins\AdvancedBundles\Contracts\BundleDefinition;
use AIMPlugins\AdvancedBundles\Domain\Pricing\ResolvedComponent;

final class BundleFixtures {
	/**
	 * @param array<string, bool|int|string> $configuration Pricing configuration.
	 */
	public static function definition(
		string $id = 'bundle-alpha',
		?string $reference = null,
		string $pricing_strategy_id = 'sum',
		array $configuration = array()
	): BundleDefinition {
		return new BundleDefinition(
			$id,
			3,
			BundleDefinition::CURRENT_SCHEMA_VERSION,
			array( new BundleComponent( 'component-main', 101, 202, 2, 0, $reference ) ),
			$pricing_strategy_id,
			$configuration
		);
	}

	/**
	 * @return list<ResolvedComponent>
	 */
	public static function resolved(): array {
		return array( new ResolvedComponent( 'component-main', 101, 202, 2, 1250, 263 ) );
	}
}
