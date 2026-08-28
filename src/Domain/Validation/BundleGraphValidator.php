<?php
/**
 * Bounded direct and indirect bundle-cycle validation.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Domain\Validation;

use AIMPlugins\AdvancedBundles\Contracts\BundleDefinition;
use DomainException;
use InvalidArgumentException;

final readonly class BundleGraphValidator {
	public function __construct( private int $maxVisitedDefinitions = 1000 ) {
		if ( $this->maxVisitedDefinitions < 1 ) {
			throw new InvalidArgumentException( 'Graph traversal limit must be positive.' );
		}
	}

	/**
	 * @param array<string, BundleDefinition> $definitions Definitions keyed by canonical ID.
	 */
	public function assertAcyclic( string $root_definition_id, array $definitions ): void {
		$states        = array();
		$path          = array();
		$visited_count = 0;

		$visit = function ( string $definition_id ) use ( &$visit, &$states, &$path, &$visited_count, $definitions ): void {
			$state = $states[ $definition_id ] ?? 0;

			if ( 1 === $state ) {
				$cycle   = $path;
				$cycle[] = $definition_id;

				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal pure-domain exception; this validator performs no output.
				throw new DomainException( 'Bundle cycle detected: ' . implode( ' > ', $cycle ) );
			}

			if ( 2 === $state ) {
				return;
			}

			$definition = $definitions[ $definition_id ] ?? null;

			if ( ! $definition instanceof BundleDefinition || $definition->id !== $definition_id ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal pure-domain exception; this validator performs no output.
				throw new DomainException( 'Referenced bundle definition is missing or keyed incorrectly: ' . $definition_id );
			}

			++$visited_count;

			if ( $visited_count > $this->maxVisitedDefinitions ) {
				throw new DomainException( 'Bundle graph traversal limit exceeded.' );
			}

			$states[ $definition_id ] = 1;
			$path[]                   = $definition_id;

			foreach ( $definition->referencedDefinitionIds() as $referenced_definition_id ) {
				$visit( $referenced_definition_id );
			}

			array_pop( $path );
			$states[ $definition_id ] = 2;
		};

		$visit( $root_definition_id );
	}
}
