<?php
/**
 * WooCommerce runtime compatibility gate tests.
 *
 * @package AIMPlugins\AdvancedBundles\Tests
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\Tests\Packaging;

use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WooCommerceRuntimeCompatibilityTest extends TestCase {
	/**
	 * @return iterable<string, array{?string, bool}>
	 */
	public static function versions(): iterable {
		yield 'missing version' => array( null, false );
		yield 'older version' => array( '9.8.9', false );
		yield 'declared minimum' => array( '9.9.0', true );
		yield 'newer version' => array( '11.0.1', true );
	}

	#[DataProvider( 'versions' )]
	public function test_boot_fails_closed_below_minimum_and_runs_on_supported_versions(
		?string $version,
		bool $expected_booted
	): void {
		$result = $this->runBootProbe( $version );

		self::assertSame( $expected_booted, $result['booted'] ?? null );
		self::assertSame( $expected_booted, $result['product_type_registered'] ?? null );
		self::assertSame( ! $expected_booted, $result['admin_notice_registered'] ?? null );

		$notice = $result['notice'] ?? null;
		self::assertIsString( $notice );

		if ( $expected_booted ) {
			self::assertSame( '', $notice );
		} else {
			self::assertStringContainsString( 'requires WooCommerce 9.9 or newer', $notice );
		}
	}

	/**
	 * @return array<string, mixed>
	 * @throws JsonException Invalid subprocess JSON.
	 */
	private function runBootProbe( ?string $version ): array {
		$root       = dirname( __DIR__, 2 );
		$version    = $version ?? '__AIM_MISSING_WOOCOMMERCE_VERSION__';
		$probe_code = <<<'PHP'
require $argv[1] . '/tests/bootstrap.php';

if ('__AIM_MISSING_WOOCOMMERCE_VERSION__' !== $argv[2]) {
    define('WC_VERSION', $argv[2]);
}

if (!function_exists('esc_html')) {
    function esc_html(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string {
        unset($domain);

        return esc_html($text);
    }
}

\AIMPlugins\AdvancedBundles\Plugin::boot();

ob_start();
do_action('admin_notices');
$notice = ob_get_clean();

echo json_encode(
    array(
        'booted' => null !== \AIMPlugins\AdvancedBundles\Plugin::extensions(),
        'product_type_registered' => !empty($GLOBALS['aim_plugins_test_hooks']['product_type_selector']),
        'admin_notice_registered' => !empty($GLOBALS['aim_plugins_test_hooks']['admin_notices']),
        'notice' => is_string($notice) ? $notice : '',
    ),
    JSON_THROW_ON_ERROR
);
PHP;
		$process    = proc_open(
			array( PHP_BINARY, '-d', 'display_errors=stderr', '-r', $probe_code, $root, $version ),
			array(
				0 => array( 'pipe', 'r' ),
				1 => array( 'pipe', 'w' ),
				2 => array( 'pipe', 'w' ),
			),
			$pipes,
			$root
		);

		if ( ! is_resource( $process ) ) {
			throw new RuntimeException( 'Unable to start WooCommerce compatibility probe.' );
		}

		fclose( $pipes[0] );
		$stdout = stream_get_contents( $pipes[1] );
		$stderr = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$exit_code = proc_close( $process );

		self::assertSame( 0, $exit_code, is_string( $stderr ) ? $stderr : '' );
		self::assertIsString( $stdout );
		$decoded = json_decode( $stdout, true, 512, JSON_THROW_ON_ERROR );

		self::assertIsArray( $decoded );

		return $decoded;
	}
}
