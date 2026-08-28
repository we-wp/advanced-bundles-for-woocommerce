<?php
/**
 * Bundle definition persistence through WooCommerce product CRUD.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\WooCommerce\Persistence;

use AIMPlugins\AdvancedBundles\Contracts\BundleComponent;
use AIMPlugins\AdvancedBundles\Contracts\BundleDefinition;
use AIMPlugins\AdvancedBundles\Domain\IntegerMath;
use InvalidArgumentException;

final class BundleDefinitionRepository {
	public function find( \WC_Product $product ): ?BundleDefinition {
		$stored = $product->get_meta( BundleDefinition::META_KEY, true, 'edit' );

		if ( '' === $stored || null === $stored ) {
			return null;
		}

		if ( ! is_array( $stored ) ) {
			throw new InvalidArgumentException( 'Stored bundle definition must be an array.' );
		}

		$normalized = array();

		foreach ( $stored as $key => $value ) {
			if ( ! is_string( $key ) ) {
				throw new InvalidArgumentException( 'Stored bundle definition keys must be strings.' );
			}

			$normalized[ $key ] = $value;
		}

		return BundleDefinition::fromArray( $normalized );
	}

	/**
	 * Stage current-schema data on a product. The caller owns product save timing.
	 */
	public function stage( \WC_Product $product, BundleDefinition $definition ): void {
		$product->update_meta_data( BundleDefinition::META_KEY, $definition->toArray() );
	}

	/**
	 * Create a new definition version only when canonical component data changed.
	 *
	 * @param list<BundleComponent> $components Validated components.
	 */
	public function stageComponents( \WC_Product $product, array $components ): BundleDefinition {
		$existing  = $this->find( $product );
		$version   = null === $existing ? 1 : IntegerMath::add( $existing->version, 1 );
		$candidate = new BundleDefinition(
			'bundle-product-' . $product->get_id(),
			$version,
			BundleDefinition::CURRENT_SCHEMA_VERSION,
			$components
		);

		if ( null !== $existing && $this->sameConfiguration( $existing, $candidate ) ) {
			return $existing;
		}

		$this->stage( $product, $candidate );

		return $candidate;
	}

	private function sameConfiguration( BundleDefinition $left, BundleDefinition $right ): bool {
		$left_data  = $left->toArray();
		$right_data = $right->toArray();

		unset( $left_data['definition_version'], $right_data['definition_version'] );

		return $left_data === $right_data;
	}
}
