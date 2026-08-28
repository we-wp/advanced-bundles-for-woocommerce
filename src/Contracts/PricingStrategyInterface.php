<?php
/**
 * Stable pricing strategy extension contract.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Contracts;

use AIMPlugins\AdvancedBundles\Domain\Pricing\PricingResult;
use AIMPlugins\AdvancedBundles\Domain\Pricing\ResolvedComponent;

interface PricingStrategyInterface {
	public function id(): string;

	/**
	 * @param list<ResolvedComponent> $components Validated resolved components.
	 */
	public function calculate( BundleDefinition $definition, array $components ): PricingResult;
}
