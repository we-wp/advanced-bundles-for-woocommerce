<?php
/**
 * Apply Free sum pricing to resolved WooCommerce products.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\WooCommerce\Pricing;

use AIMPlugins\AdvancedBundles\Domain\IntegerMath;
use AIMPlugins\AdvancedBundles\WooCommerce\BundleComponentResolver;
use AIMPlugins\AdvancedBundles\WooCommerce\Persistence\BundleDefinitionRepository;
use DomainException;

final readonly class BundlePriceCalculator {
	public function __construct(
		private BundleDefinitionRepository $repository = new BundleDefinitionRepository(),
		private BundleComponentResolver $resolver = new BundleComponentResolver()
	) {}

	public function calculate( \WC_Product $bundle ): string {
		$definition = $this->repository->find( $bundle );

		if ( null === $definition ) {
			throw new DomainException( 'This bundle has no component definition.' );
		}

		$price = 0;

		foreach ( $this->resolver->resolveDefinition( $definition ) as $resolved ) {
			$component_price = $resolved->product->get_price();

			if ( '' === $component_price || ! is_numeric( $component_price ) ) {
				throw new DomainException( 'A bundle component has no valid price.' );
			}

			$minor = (int) round( (float) wc_add_number_precision( (float) $component_price ) );
			$price = IntegerMath::add(
				$price,
				IntegerMath::multiply( $minor, $resolved->component->quantity )
			);
		}

		return wc_format_decimal( wc_remove_number_precision( $price ), wc_get_price_decimals() );
	}

	public function calculateForDisplay( \WC_Product $bundle ): string {
		$definition = $this->repository->find( $bundle );

		if ( null === $definition ) {
			throw new DomainException( 'This bundle has no component definition.' );
		}

		$price = 0.0;

		foreach ( $this->resolver->resolveDefinition( $definition ) as $resolved ) {
			$component_price = $resolved->product->get_price();

			if ( '' === $component_price || ! is_numeric( $component_price ) ) {
				throw new DomainException( 'A bundle component has no valid price.' );
			}

			$display_price = wc_get_price_to_display(
				$resolved->product,
				array(
					'price' => (float) $component_price,
					'qty'   => $resolved->component->quantity,
				)
			);

			$price += $display_price;
		}

		return wc_format_decimal( $price, wc_get_price_decimals() );
	}
}
