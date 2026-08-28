<?php
/**
 * Free-owned extension registry.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Extension;

use AIMPlugins\AdvancedBundles\Contracts\PricingStrategyInterface;
use AIMPlugins\AdvancedBundles\Domain\CanonicalIdentifier;
use DomainException;

final class ExtensionRegistry {
	public const REGISTER_HOOK = 'aim_advanced_bundles_register_extensions';

	/** @var array<string, PricingStrategyInterface> */
	private array $pricingStrategies = array();

	private bool $sealed = false;

	public function registerPricingStrategy( PricingStrategyInterface $strategy ): void {
		if ( $this->sealed ) {
			throw new DomainException( 'Advanced Bundles extension registration is closed.' );
		}

		$strategy_id = $strategy->id();
		CanonicalIdentifier::assert( $strategy_id, 'Pricing strategy ID' );

		if ( isset( $this->pricingStrategies[ $strategy_id ] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal domain exception; the registry never renders exception text.
			throw new DomainException( 'Pricing strategy ID is already registered: ' . $strategy_id );
		}

		$this->pricingStrategies[ $strategy_id ] = $strategy;
	}

	public function seal(): void {
		ksort( $this->pricingStrategies, SORT_STRING );
		$this->sealed = true;
	}

	public function pricingStrategy( string $strategy_id ): PricingStrategyInterface {
		$strategy = $this->pricingStrategies[ $strategy_id ] ?? null;

		if ( ! $strategy instanceof PricingStrategyInterface ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal domain exception; the registry never renders exception text.
			throw new DomainException( 'Unknown pricing strategy ID: ' . $strategy_id );
		}

		return $strategy;
	}

	/**
	 * @return array<string, PricingStrategyInterface>
	 */
	public function pricingStrategies(): array {
		return $this->pricingStrategies;
	}

	public function isSealed(): bool {
		return $this->sealed;
	}
}
