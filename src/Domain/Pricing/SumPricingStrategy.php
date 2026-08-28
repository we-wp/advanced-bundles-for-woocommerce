<?php
/**
 * Free sum pricing strategy.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Domain\Pricing;

use AIMPlugins\AdvancedBundles\Contracts\BundleDefinition;
use AIMPlugins\AdvancedBundles\Contracts\PricingStrategyInterface;
use AIMPlugins\AdvancedBundles\Domain\IntegerMath;
use AIMPlugins\AdvancedBundles\Domain\Validation\ResolvedSelectionValidator;

final readonly class SumPricingStrategy implements PricingStrategyInterface {
	public function __construct( private ResolvedSelectionValidator $validator = new ResolvedSelectionValidator() ) {}

	public function id(): string {
		return 'sum';
	}

	/**
	 * @param list<ResolvedComponent> $components Validated resolved components.
	 */
	public function calculate( BundleDefinition $definition, array $components ): PricingResult {
		$this->validator->assertMatches( $definition, $components );

		$subtotal = 0;
		$tax      = 0;

		foreach ( $components as $component ) {
			$subtotal = IntegerMath::add(
				$subtotal,
				IntegerMath::multiply( $component->unitSubtotalMinor, $component->quantity )
			);
			$tax      = IntegerMath::add(
				$tax,
				IntegerMath::multiply( $component->unitTaxMinor, $component->quantity )
			);
		}

		return new PricingResult( $subtotal, $tax );
	}
}
