<?php
/**
 * WooCommerce product CRUD definition repository tests.
 *
 * @package AIMPlugins\AdvancedBundles\Tests
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Tests\Unit\WooCommerce;

use AIMPlugins\AdvancedBundles\Contracts\BundleComponent;
use AIMPlugins\AdvancedBundles\Contracts\BundleDefinition;
use AIMPlugins\AdvancedBundles\WooCommerce\Persistence\BundleDefinitionRepository;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BundleDefinitionRepositoryTest extends TestCase {
	public function test_it_stages_and_reads_current_schema_through_product_meta_api(): void {
		$product    = new \WC_Product( 500 );
		$repository = new BundleDefinitionRepository();
		$component  = new BundleComponent( 'component-main', 10, null, 2, 0 );

		$staged = $repository->stageComponents( $product, array( $component ) );

		self::assertSame( 1, $staged->version );
		self::assertEquals( $staged, $repository->find( $product ) );
		self::assertSame( BundleDefinition::CURRENT_SCHEMA_VERSION, $staged->schemaVersion );
	}

	public function test_identical_save_keeps_definition_version(): void {
		$product    = new \WC_Product( 500 );
		$repository = new BundleDefinitionRepository();
		$component  = new BundleComponent( 'component-main', 10, null, 2, 0 );

		$first  = $repository->stageComponents( $product, array( $component ) );
		$second = $repository->stageComponents( $product, array( $component ) );

		self::assertSame( 1, $first->version );
		self::assertSame( 1, $second->version );
	}

	public function test_changed_configuration_increments_definition_version(): void {
		$product    = new \WC_Product( 500 );
		$repository = new BundleDefinitionRepository();

		$repository->stageComponents( $product, array( new BundleComponent( 'component-main', 10, null, 2, 0 ) ) );
		$changed = $repository->stageComponents( $product, array( new BundleComponent( 'component-main', 10, null, 3, 0 ) ) );

		self::assertSame( 2, $changed->version );
	}

	public function test_corrupt_non_array_metadata_is_rejected(): void {
		$product = new \WC_Product( 500 );
		$product->update_meta_data( BundleDefinition::META_KEY, 'corrupt' );

		$this->expectException( InvalidArgumentException::class );

		( new BundleDefinitionRepository() )->find( $product );
	}
}
