<?php
/**
 * Calculated selection snapshot value contract.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Contracts;

use AIMPlugins\AdvancedBundles\Domain\CanonicalIdentifier;
use AIMPlugins\AdvancedBundles\Domain\IntegerMath;
use AIMPlugins\AdvancedBundles\Domain\SelectedComponent;
use InvalidArgumentException;

final readonly class SelectionSnapshot {
	public const CURRENT_SCHEMA_VERSION = 2;
	public const ORDER_ITEM_META_KEY    = '_aim_advanced_bundles_selection_snapshot';

	/** @var list<SelectedComponent> */
	private array $components;

	/** @var array<string, bool> */
	private array $ruleResults;

	public int $subtotalMinor;
	public int $netMinor;
	public int $discountMinor;
	public int $taxMinor;
	public int $totalMinor;

	/**
	 * @param array<array-key, mixed> $components   Selected components.
	 * @param array<array-key, mixed> $rule_results Evaluated rule results.
	 */
	public function __construct(
		public int $schemaVersion,
		public string $definitionId,
		public int $definitionVersion,
		public string $currency,
		array $components,
		array $rule_results = array()
	) {
		if ( self::CURRENT_SCHEMA_VERSION !== $this->schemaVersion ) {
			throw new InvalidArgumentException( 'Unsupported selection snapshot schema version.' );
		}

		CanonicalIdentifier::assert( $this->definitionId, 'Snapshot definition ID' );

		if ( $this->definitionVersion < 1 ) {
			throw new InvalidArgumentException( 'Snapshot definition version must be positive.' );
		}

		if ( 1 !== preg_match( '/\A[A-Z]{3}\z/', $this->currency ) ) {
			throw new InvalidArgumentException( 'Snapshot currency must be a three-letter uppercase code.' );
		}

		if ( array() === $components ) {
			throw new InvalidArgumentException( 'A selection snapshot must contain at least one component.' );
		}

		$component_ids        = array();
		$subtotal             = 0;
		$net                  = 0;
		$tax                  = 0;
		$validated_components = array();

		foreach ( $components as $component ) {
			if ( ! $component instanceof SelectedComponent ) {
				throw new InvalidArgumentException( 'Every snapshot component must be a SelectedComponent.' );
			}

			if ( isset( $component_ids[ $component->componentId ] ) ) {
				throw new InvalidArgumentException( 'Snapshot component IDs must be unique.' );
			}

			$component_ids[ $component->componentId ] = true;
			$subtotal                                 = IntegerMath::add( $subtotal, $component->lineSubtotalMinor );
			$net                                      = IntegerMath::add( $net, $component->lineTotalMinor );
			$tax                                      = IntegerMath::add( $tax, $component->lineTaxMinor );
			$validated_components[]                   = $component;
		}

		$validated_rule_results = array();

		foreach ( $rule_results as $rule_id => $result ) {
			if ( ! is_string( $rule_id ) ) {
				throw new InvalidArgumentException( 'Rule result IDs must be strings.' );
			}

			CanonicalIdentifier::assert( $rule_id, 'Rule result ID' );

			if ( ! is_bool( $result ) ) {
				throw new InvalidArgumentException( 'Rule results must be boolean values.' );
			}

			$validated_rule_results[ $rule_id ] = $result;
		}

		ksort( $validated_rule_results, SORT_STRING );

		$this->components    = $validated_components;
		$this->ruleResults   = $validated_rule_results;
		$this->subtotalMinor = $subtotal;
		$this->netMinor      = $net;
		$this->discountMinor = $subtotal - $net;
		$this->taxMinor      = $tax;
		$this->totalMinor    = IntegerMath::add( $net, $tax );
	}

	/**
	 * @return list<SelectedComponent>
	 */
	public function components(): array {
		return $this->components;
	}

	/**
	 * @return array<string, bool>
	 */
	public function ruleResults(): array {
		return $this->ruleResults;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'schema_version'     => $this->schemaVersion,
			'definition_id'      => $this->definitionId,
			'definition_version' => $this->definitionVersion,
			'currency'           => $this->currency,
			'components'         => array_map(
				static fn ( SelectedComponent $component ): array => $component->toArray(),
				$this->components
			),
			'subtotal_minor'     => $this->subtotalMinor,
			'net_minor'          => $this->netMinor,
			'discount_minor'     => $this->discountMinor,
			'tax_minor'          => $this->taxMinor,
			'total_minor'        => $this->totalMinor,
			'rule_results'       => $this->ruleResults,
		);
	}

	/**
	 * @param array<string, mixed> $data Stored snapshot data.
	 */
	public static function fromArray( array $data ): self {
		$schema_version     = $data['schema_version'] ?? null;
		$definition_id      = $data['definition_id'] ?? null;
		$definition_version = $data['definition_version'] ?? null;
		$currency           = $data['currency'] ?? null;
		$component_data     = $data['components'] ?? null;
		$rule_results       = $data['rule_results'] ?? null;

		if ( ! is_int( $schema_version ) || ! is_string( $definition_id ) || ! is_int( $definition_version ) ) {
			throw new InvalidArgumentException( 'Snapshot identity fields have invalid types.' );
		}

		if ( ! is_string( $currency ) || ! is_array( $component_data ) || ! is_array( $rule_results ) ) {
			throw new InvalidArgumentException( 'Snapshot currency, components, or rules have invalid types.' );
		}

		$components = array();

		foreach ( $component_data as $component ) {
			if ( ! is_array( $component ) ) {
				throw new InvalidArgumentException( 'Stored snapshot component must be an array.' );
			}

			$component_fields = array();

			foreach ( $component as $key => $value ) {
				if ( ! is_string( $key ) ) {
					throw new InvalidArgumentException( 'Stored snapshot component keys must be strings.' );
				}

				$component_fields[ $key ] = $value;
			}

			$components[] = SelectedComponent::fromArray( $component_fields );
		}

		$snapshot = new self(
			$schema_version,
			$definition_id,
			$definition_version,
			$currency,
			$components,
			$rule_results
		);

		foreach ( array( 'subtotal_minor', 'net_minor', 'discount_minor', 'tax_minor', 'total_minor' ) as $total_key ) {
			if ( ! isset( $data[ $total_key ] ) || ! is_int( $data[ $total_key ] ) ) {
				throw new InvalidArgumentException( 'Stored snapshot totals are missing or invalid.' );
			}
		}

		if (
			$data['subtotal_minor'] !== $snapshot->subtotalMinor ||
			$data['net_minor'] !== $snapshot->netMinor ||
			$data['discount_minor'] !== $snapshot->discountMinor ||
			$data['tax_minor'] !== $snapshot->taxMinor ||
			$data['total_minor'] !== $snapshot->totalMinor
		) {
			throw new InvalidArgumentException( 'Stored snapshot totals do not match component totals.' );
		}

		return $snapshot;
	}
}
