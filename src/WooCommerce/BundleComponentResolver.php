<?php
/**
 * Resolve fixed component identities through WooCommerce product APIs.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\WooCommerce;

use AIMPlugins\AdvancedBundles\Contracts\BundleComponent;
use AIMPlugins\AdvancedBundles\Contracts\BundleDefinition;
use DomainException;

final class BundleComponentResolver {
	/**
	 * @return list<ResolvedBundleComponent>
	 */
	public function resolveDefinition( BundleDefinition $definition ): array {
		$resolved = array();

		foreach ( $definition->components() as $component ) {
			$resolved[] = $this->resolveComponent( $component );
		}

		return $resolved;
	}

	public function resolveComponent( BundleComponent $component ): ResolvedBundleComponent {
		$lookup_id = $component->variationId ?? $component->productId;
		$product   = wc_get_product( $lookup_id );

		if ( ! $product instanceof \WC_Product ) {
			throw new DomainException( 'A bundle component product no longer exists.' );
		}

		if ( null === $component->variationId ) {
			if ( $product->get_id() !== $component->productId || ! $product->is_type( 'simple' ) ) {
				throw new DomainException( 'A Free bundle component must be a simple product or a fixed variation.' );
			}
		} elseif (
			! $product->is_type( 'variation' )
			|| $product->get_id() !== $component->variationId
			|| $product->get_parent_id() !== $component->productId
		) {
			throw new DomainException( 'A fixed bundle variation no longer matches its parent product.' );
		}

		if ( 'publish' !== $product->get_status() || ! $product->is_purchasable() ) {
			throw new DomainException( 'A bundle component is not currently available for purchase.' );
		}

		return new ResolvedBundleComponent( $component, $product );
	}
}
