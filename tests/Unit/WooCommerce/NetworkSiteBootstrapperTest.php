<?php
/**
 * Network-site first-bootstrap tests.
 *
 * @package AIMPlugins\AdvancedBundles\Tests
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Tests\Unit\WooCommerce;

use AIMPlugins\AdvancedBundles\WooCommerce\Multisite\NetworkSiteBootstrapper;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NetworkSiteBootstrapperTest extends TestCase {
	private const PLUGIN_BASENAME = 'aim-advanced-bundles/aim-advanced-bundles.php';
	private const FAILURE_ACTION  = 'aim_advanced_bundles_multisite_bootstrap_failed';

	/** @var array<string, array<int, list<array{callable, int}>>> */
	private array $hooks_snapshot = array();
	private object $original_container;

	public static function setUpBeforeClass(): void {
		if ( ! defined( 'WC_VERSION' ) ) {
			define( 'WC_VERSION', '9.9.0' );
		}

		if ( ! defined( 'WC_PLUGIN_BASENAME' ) ) {
			define( 'WC_PLUGIN_BASENAME', 'woocommerce/woocommerce.php' );
		}
	}

	protected function setUp(): void {
		parent::setUp();

		$this->hooks_snapshot = $GLOBALS['aim_plugins_test_hooks'];
		$GLOBALS['aim_plugins_test_is_multisite'] = true;
		$GLOBALS['aim_plugins_test_network_plugins'] = array(
			'aim-advanced-bundles/aim-advanced-bundles.php' => 1,
			'woocommerce/woocommerce.php'                  => 1,
		);
		$GLOBALS['aim_plugins_test_options'] = array();
		$GLOBALS['aim_plugins_test_current_blog_id'] = 7;
		$GLOBALS['aim_plugins_test_provisioning_calls'] = array();
		$GLOBALS['aim_plugins_test_provisioning_exception'] = null;
		$this->original_container = new \stdClass();
		$GLOBALS['wc_container'] = $this->original_container;
	}

	protected function tearDown(): void {
		$GLOBALS['aim_plugins_test_hooks'] = $this->hooks_snapshot;

		parent::tearDown();
	}

	public function test_it_registers_before_woocommerce_init_hooks(): void {
		$bootstrapper = new NetworkSiteBootstrapper( self::PLUGIN_BASENAME );
		$bootstrapper->register();

		$callbacks = $GLOBALS['aim_plugins_test_hooks']['init'][-100] ?? array();

		self::assertNotEmpty( $callbacks );
		self::assertSame( 0, $callbacks[ array_key_last( $callbacks ) ][1] );
	}

	public function test_it_prepares_only_the_current_site_tables_before_normal_installation(): void {
		( new NetworkSiteBootstrapper( self::PLUGIN_BASENAME ) )->bootstrap();

		self::assertSame(
			array(
				'woocommerce:create_tables:7',
				'action-scheduler:store:7:force',
				'action-scheduler:logger:7:force',
			),
			$GLOBALS['aim_plugins_test_provisioning_calls']
		);
		self::assertSame( 7, get_current_blog_id() );
		self::assertSame( $this->original_container, $GLOBALS['wc_container'] );
		self::assertNotContains( 'woocommerce:install:7', $GLOBALS['aim_plugins_test_provisioning_calls'] );
	}

	public function test_it_skips_site_specific_or_incomplete_network_activation(): void {
		$GLOBALS['aim_plugins_test_network_plugins'] = array(
			'aim-advanced-bundles/aim-advanced-bundles.php' => 1,
		);

		( new NetworkSiteBootstrapper( self::PLUGIN_BASENAME ) )->bootstrap();

		self::assertSame( array(), $GLOBALS['aim_plugins_test_provisioning_calls'] );
	}

	public function test_it_leaves_existing_woocommerce_sites_unchanged(): void {
		$GLOBALS['aim_plugins_test_options']['woocommerce_version'] = '9.9.0';

		( new NetworkSiteBootstrapper( self::PLUGIN_BASENAME ) )->bootstrap();

		self::assertSame( array(), $GLOBALS['aim_plugins_test_provisioning_calls'] );
	}

	public function test_it_swallows_failures_and_observer_exceptions(): void {
		$GLOBALS['aim_plugins_test_provisioning_exception'] = new RuntimeException( 'action-scheduler:store' );
		$failure_site_ids = array();
		add_action(
			self::FAILURE_ACTION,
			static function ( int $site_id ) use ( &$failure_site_ids ): void {
				$failure_site_ids[] = $site_id;
			}
		);
		add_action(
			self::FAILURE_ACTION,
			static function (): void {
				throw new RuntimeException( 'observer failure' );
			},
			20
		);

		( new NetworkSiteBootstrapper( self::PLUGIN_BASENAME ) )->bootstrap();

		self::assertSame(
			array(
				'woocommerce:create_tables:7',
				'action-scheduler:store:7:force',
			),
			$GLOBALS['aim_plugins_test_provisioning_calls']
		);
		self::assertSame( array( 7 ), $failure_site_ids );
		self::assertSame( 7, get_current_blog_id() );
		self::assertSame( $this->original_container, $GLOBALS['wc_container'] );
		self::assertNotContains( 'woocommerce:install:7', $GLOBALS['aim_plugins_test_provisioning_calls'] );
	}
}
