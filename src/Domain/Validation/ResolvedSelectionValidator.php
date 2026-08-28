<?php
/**
 * Fixed-selection validation.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Domain\Validation;

use AIMPlugins\AdvancedBundles\Contracts\BundleComponent;
use AIMPlugins\AdvancedBundles\Contracts\BundleDefinition;
use AIMPlugins\AdvancedBundles\Domain\Pricing\ResolvedComponent;
use DomainException;

final class ResolvedSelectionValidator {
	/**
	 * @param array<array-key, mixed> $resolved_components Resolved pricing inputs.
	 */
	public function assertMatches( BundleDefinition $definition, array $resolved_components ): void {
		$expected = array();

		foreach ( $definition->components() as $component ) {
			$expected[ $component->id ] = $component;
		}

		$seen = array();

		foreach ( $resolved_components as $resolved ) {
			if ( ! $resolved instanceof ResolvedComponent ) {
				throw new DomainException( 'Every resolved selection item must be a ResolvedComponent.' );
			}

			if ( isset( $seen[ $resolved->componentId ] ) ) {
				throw new DomainException( 'Resolved component IDs must be unique.' );
			}

			$component = $expected[ $resolved->componentId ] ?? null;

			if ( ! $component instanceof BundleComponent ) {
				throw new DomainException( 'Resolved selection contains an unknown component ID.' );
			}

			if ( $resolved->productId !== $component->productId || $resolved->variationId !== $component->variationId ) {
				throw new DomainException( 'Resolved product identity does not match the canonical component.' );
			}

			if ( $resolved->quantity !== $component->quantity ) {
				throw new DomainException( 'Resolved quantity does not match the fixed component quantity.' );
			}

			$seen[ $resolved->componentId ] = true;
		}

		if ( count( $seen ) !== count( $expected ) ) {
			throw new DomainException( 'Resolved selection is missing one or more required components.' );
		}
	}
}
