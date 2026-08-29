<?php
/**
 * Extension registry tests.
 *
 * @package AIMPlugins\AdvancedBundles\Tests
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Tests\Unit\Extension;

use AIMPlugins\AdvancedBundles\Domain\Pricing\SumPricingStrategy;
use AIMPlugins\AdvancedBundles\Extension\ExtensionRegistry;
use DomainException;
use PHPUnit\Framework\TestCase;

final class ExtensionRegistryTest extends TestCase {
	public function test_public_registration_hook_identity_remains_literal_and_prefixed(): void {
		self::assertSame( 'aim_advanced_bundles_register_extensions', ExtensionRegistry::REGISTER_HOOK );
	}

	public function test_free_owns_registration_and_seals_it(): void {
		$registry = new ExtensionRegistry();
		$strategy = new SumPricingStrategy();

		$registry->registerPricingStrategy( $strategy );
		$registry->seal();

		self::assertTrue( $registry->isSealed() );
		self::assertSame( $strategy, $registry->pricingStrategy( 'sum' ) );
	}

	public function test_it_rejects_duplicate_strategy_ids(): void {
		$registry = new ExtensionRegistry();
		$registry->registerPricingStrategy( new SumPricingStrategy() );

		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'already registered' );

		$registry->registerPricingStrategy( new SumPricingStrategy() );
	}

	public function test_it_rejects_registration_after_sealing(): void {
		$registry = new ExtensionRegistry();
		$registry->seal();

		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'registration is closed' );

		$registry->registerPricingStrategy( new SumPricingStrategy() );
	}
}
