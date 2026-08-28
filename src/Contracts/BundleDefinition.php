<?php
/**
 * Canonical bundle definition contract.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Contracts;

use AIMPlugins\AdvancedBundles\Domain\CanonicalIdentifier;
use InvalidArgumentException;

final readonly class BundleDefinition {
	public const CURRENT_SCHEMA_VERSION = 1;
	public const META_KEY               = '_aim_advanced_bundles_definition';
	public const MAX_COMPONENTS         = 500;

	/** @var list<BundleComponent> */
	private array $components;

	/** @var array<string, bool|int|string> */
	private array $pricingConfiguration;

	/**
	 * @param array<array-key, mixed> $components            Canonical components.
	 * @param array<array-key, mixed> $pricing_configuration Flat strategy configuration.
	 */
	public function __construct(
		public string $id,
		public int $version,
		public int $schemaVersion,
		array $components,
		public string $pricingStrategyId = 'sum',
		array $pricing_configuration = array()
	) {
		CanonicalIdentifier::assert( $this->id, 'Bundle definition ID' );
		CanonicalIdentifier::assert( $this->pricingStrategyId, 'Pricing strategy ID' );

		if ( $this->version < 1 ) {
			throw new InvalidArgumentException( 'Bundle definition version must be positive.' );
		}

		if ( self::CURRENT_SCHEMA_VERSION !== $this->schemaVersion ) {
			throw new InvalidArgumentException( 'Unsupported bundle definition schema version.' );
		}

		if ( array() === $components || count( $components ) > self::MAX_COMPONENTS ) {
			throw new InvalidArgumentException( 'A bundle must contain between 1 and 500 components.' );
		}

		$component_ids        = array();
		$positions            = array();
		$validated_components = array();

		foreach ( $components as $component ) {
			if ( ! $component instanceof BundleComponent ) {
				throw new InvalidArgumentException( 'Every bundle component must implement the BundleComponent contract.' );
			}

			if ( isset( $component_ids[ $component->id ] ) ) {
				throw new InvalidArgumentException( 'Component IDs must be unique within a bundle definition.' );
			}

			if ( isset( $positions[ $component->position ] ) ) {
				throw new InvalidArgumentException( 'Component positions must be unique within a bundle definition.' );
			}

			$component_ids[ $component->id ]   = true;
			$positions[ $component->position ] = true;
			$validated_components[]            = $component;
		}

		$validated_configuration = array();

		foreach ( $pricing_configuration as $key => $value ) {
			if ( ! is_string( $key ) ) {
				throw new InvalidArgumentException( 'Pricing configuration keys must be strings.' );
			}

			CanonicalIdentifier::assert( $key, 'Pricing configuration key' );

			if ( ! is_bool( $value ) && ! is_int( $value ) && ! is_string( $value ) ) {
				throw new InvalidArgumentException( 'Pricing configuration values must be scalar and deterministic.' );
			}

			$validated_configuration[ $key ] = $value;
		}

		usort(
			$validated_components,
			static fn ( BundleComponent $left, BundleComponent $right ): int =>
				array( $left->position, $left->id ) <=> array( $right->position, $right->id )
		);
		ksort( $validated_configuration, SORT_STRING );

		$this->components           = $validated_components;
		$this->pricingConfiguration = $validated_configuration;
	}

	/**
	 * @return list<BundleComponent>
	 */
	public function components(): array {
		return $this->components;
	}

	/**
	 * @return array<string, bool|int|string>
	 */
	public function pricingConfiguration(): array {
		return $this->pricingConfiguration;
	}

	/**
	 * @return list<string>
	 */
	public function referencedDefinitionIds(): array {
		$ids = array();

		foreach ( $this->components as $component ) {
			if ( null !== $component->referencedBundleDefinitionId ) {
				$ids[ $component->referencedBundleDefinitionId ] = true;
			}
		}

		$references = array_keys( $ids );
		sort( $references, SORT_STRING );

		return $references;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'schema_version'        => $this->schemaVersion,
			'definition_id'         => $this->id,
			'definition_version'    => $this->version,
			'pricing_strategy_id'   => $this->pricingStrategyId,
			'pricing_configuration' => $this->pricingConfiguration,
			'components'            => array_map(
				static fn ( BundleComponent $component ): array => $component->toArray(),
				$this->components
			),
		);
	}

	/**
	 * @param array<string, mixed> $data Stored bundle definition data.
	 */
	public static function fromArray( array $data ): self {
		$schema_version        = $data['schema_version'] ?? null;
		$definition_id         = $data['definition_id'] ?? null;
		$definition_version    = $data['definition_version'] ?? null;
		$pricing_strategy_id   = $data['pricing_strategy_id'] ?? null;
		$pricing_configuration = $data['pricing_configuration'] ?? null;
		$component_data        = $data['components'] ?? null;

		if ( ! is_int( $schema_version ) || ! is_string( $definition_id ) || ! is_int( $definition_version ) ) {
			throw new InvalidArgumentException( 'Bundle definition identity fields have invalid types.' );
		}

		if ( ! is_string( $pricing_strategy_id ) || ! is_array( $pricing_configuration ) || ! is_array( $component_data ) ) {
			throw new InvalidArgumentException( 'Bundle definition pricing or components have invalid types.' );
		}

		$components = array();

		foreach ( $component_data as $component ) {
			if ( ! is_array( $component ) ) {
				throw new InvalidArgumentException( 'Stored bundle component must be an array.' );
			}

			$component_fields = array();

			foreach ( $component as $key => $value ) {
				if ( ! is_string( $key ) ) {
					throw new InvalidArgumentException( 'Stored bundle component keys must be strings.' );
				}

				$component_fields[ $key ] = $value;
			}

			$components[] = BundleComponent::fromArray( $component_fields );
		}

		return new self(
			$definition_id,
			$definition_version,
			$schema_version,
			$components,
			$pricing_strategy_id,
			$pricing_configuration
		);
	}
}
