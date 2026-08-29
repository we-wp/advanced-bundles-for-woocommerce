<?php
/**
 * Unit test bootstrap with synthetic platform seams.
 *
 * @package AIMPlugins\AdvancedBundles\Tests
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$GLOBALS['aim_plugins_test_hooks'] = array();
$GLOBALS['aim_plugins_test_products'] = array();
$GLOBALS['aim_plugins_test_notices'] = array();
$GLOBALS['aim_plugins_test_can_edit_products'] = true;
$GLOBALS['aim_plugins_test_endpoint_data'] = array();
$GLOBALS['aim_plugins_test_scripts'] = array();
$GLOBALS['aim_plugins_test_styles'] = array();
$GLOBALS['aim_plugins_test_is_product'] = true;
$GLOBALS['aim_plugins_test_queried_object_id'] = 0;
$GLOBALS['aim_plugins_test_prices_include_tax'] = false;
$GLOBALS['aim_plugins_test_tax_display_shop'] = 'excl';
$GLOBALS['aim_plugins_test_tax_rates'] = array();
$GLOBALS['aim_plugins_test_is_multisite'] = false;
$GLOBALS['aim_plugins_test_network_plugins'] = array();
$GLOBALS['aim_plugins_test_options'] = array();
$GLOBALS['aim_plugins_test_current_blog_id'] = 1;
$GLOBALS['aim_plugins_test_provisioning_calls'] = array();
$GLOBALS['aim_plugins_test_provisioning_exception'] = null;

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! class_exists( 'AIM_Test_Cart_Item_Schema' ) ) {
	class AIM_Test_Cart_Item_Schema {
		public const IDENTIFIER = 'cart-item';
	}
}

if ( ! class_exists( 'WC_Install' ) ) {
	class WC_Install {
		/** @return array<string, string> */
		public static function create_tables(): array {
			$GLOBALS['aim_plugins_test_provisioning_calls'][] = 'woocommerce:create_tables:' . get_current_blog_id();
			self::throwProvisioningException( 'woocommerce:create_tables' );

			return array();
		}

		public static function install(): void {
			$GLOBALS['aim_plugins_test_provisioning_calls'][] = 'woocommerce:install:' . get_current_blog_id();
			self::throwProvisioningException( 'woocommerce:install' );
		}

		private static function throwProvisioningException( string $stage ): void {
			$exception = $GLOBALS['aim_plugins_test_provisioning_exception'];

			if ( $exception instanceof Throwable && $exception->getMessage() === $stage ) {
				throw $exception;
			}
		}
	}
}

if ( ! class_exists( 'ActionScheduler_StoreSchema' ) ) {
	class ActionScheduler_StoreSchema {
		public function register_tables( bool $force_update = false ): void {
			$GLOBALS['aim_plugins_test_provisioning_calls'][] = sprintf(
				'action-scheduler:store:%d:%s',
				get_current_blog_id(),
				$force_update ? 'force' : 'normal'
			);
			$exception = $GLOBALS['aim_plugins_test_provisioning_exception'];

			if ( $exception instanceof Throwable && 'action-scheduler:store' === $exception->getMessage() ) {
				throw $exception;
			}
		}
	}
}

if ( ! class_exists( 'ActionScheduler_LoggerSchema' ) ) {
	class ActionScheduler_LoggerSchema {
		public function register_tables( bool $force_update = false ): void {
			$GLOBALS['aim_plugins_test_provisioning_calls'][] = sprintf(
				'action-scheduler:logger:%d:%s',
				get_current_blog_id(),
				$force_update ? 'force' : 'normal'
			);
			$exception = $GLOBALS['aim_plugins_test_provisioning_exception'];

			if ( $exception instanceof Throwable && 'action-scheduler:logger' === $exception->getMessage() ) {
				throw $exception;
			}
		}
	}
}

if ( ! class_exists( 'Automattic\\WooCommerce\\StoreApi\\Schemas\\V1\\CartItemSchema' ) ) {
	class_alias( AIM_Test_Cart_Item_Schema::class, 'Automattic\\WooCommerce\\StoreApi\\Schemas\\V1\\CartItemSchema' );
}

if ( ! class_exists( 'WC_Product' ) ) {
	class WC_Product {
		/** @var array<string, mixed> */
		private array $meta = array();
		private int $id;
		private string $type;
		private int $parentId = 0;
		private string $status = 'publish';
		private string $price = '10.00';
		private string $regularPrice = '10.00';
		private string $salePrice = '';
		private string $taxClass = '';
		private string $name;
		private string $sku = '';
		private bool $purchasable = true;
		private bool $inStock = true;
		private bool $manageStock = false;
		private bool $backorders = false;
		private int|float|null $stockQuantity = null;
		private int $stockOwnerId;
		private bool $virtual = false;

		public function __construct( int $id = 0, string $type = 'simple' ) {
			$this->id           = $id;
			$this->type         = $type;
			$this->name         = 'Synthetic product ' . $id;
			$this->stockOwnerId = $id;
		}

		public function get_id(): int {
			return $this->id;
		}

		public function get_type(): string {
			return $this->type;
		}

		public function is_type( string|array $type ): bool {
			return is_array( $type ) ? in_array( $this->get_type(), $type, true ) : $this->get_type() === $type;
		}

		public function get_parent_id(): int {
			return $this->parentId;
		}

		public function set_parent_id( int $parent_id ): void {
			$this->parentId = $parent_id;
		}

		public function get_status(): string {
			return $this->status;
		}

		public function set_status( string $status ): void {
			$this->status = $status;
		}

		public function is_purchasable(): bool {
			return $this->purchasable && '' !== $this->get_price();
		}

		public function set_purchasable( bool $purchasable ): void {
			$this->purchasable = $purchasable;
		}

		public function get_price( string $context = 'view' ): string {
			unset( $context );

			return $this->price;
		}

		public function set_price( string|int|float $price ): void {
			$this->price = (string) $price;
		}

		public function get_regular_price( string $context = 'view' ): string {
			unset( $context );

			return $this->regularPrice;
		}

		public function set_regular_price( string $price ): void {
			$this->regularPrice = $price;
		}

		public function set_sale_price( string $price ): void {
			$this->salePrice = $price;
		}

		public function get_tax_class(): string {
			return $this->taxClass;
		}

		public function set_tax_class( string $tax_class ): void {
			$this->taxClass = $tax_class;
		}

		public function get_price_suffix( string $price = '', int|float $quantity = 1 ): string {
			unset( $price, $quantity );

			return '';
		}

		public function get_price_html( string $price = '' ): string {
			unset( $price );

			$current_price = $this->get_price();

			if ( '' === $current_price ) {
				$price_html = (string) apply_filters( 'woocommerce_empty_price_html', '', $this );
			} else {
				$price_html = wc_price( wc_get_price_to_display( $this ) ) . $this->get_price_suffix();
			}

			return (string) apply_filters( 'woocommerce_get_price_html', $price_html, $this );
		}

		public function get_name(): string {
			return $this->name;
		}

		public function set_name( string $name ): void {
			$this->name = $name;
		}

		public function get_sku(): string {
			return $this->sku;
		}

		public function set_sku( string $sku ): void {
			$this->sku = $sku;
		}

		public function get_formatted_name(): string {
			return $this->name . ' (#' . $this->id . ')';
		}

		public function get_permalink(): string {
			return 'https://synthetic.invalid/product/' . $this->id;
		}

		/** @param array<string, string> $attributes Image attributes. */
		public function get_image( string $size = 'woocommerce_thumbnail', array $attributes = array() ): string {
			unset( $size );

			$attributes = array_merge(
				array(
					'src'    => 'https://synthetic.invalid/images/' . $this->id . '.jpg',
					'alt'    => $this->name,
					'width'  => '300',
					'height' => '300',
				),
				$attributes
			);
			$html_attributes = array();

			foreach ( $attributes as $name => $value ) {
				$html_attributes[] = sprintf(
					'%s="%s"',
					htmlspecialchars( $name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ),
					htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' )
				);
			}

			return '<img ' . implode( ' ', $html_attributes ) . ' />';
		}

		public function get_min_purchase_quantity(): int {
			return 1;
		}

		public function get_max_purchase_quantity(): int {
			return 99;
		}

		public function single_add_to_cart_text(): string {
			return 'Add to cart';
		}

		public function is_in_stock(): bool {
			return $this->inStock;
		}

		public function set_in_stock( bool $in_stock ): void {
			$this->inStock = $in_stock;
		}

		public function managing_stock(): bool {
			return $this->manageStock;
		}

		public function set_manage_stock( bool $manage_stock ): void {
			$this->manageStock = $manage_stock;
		}

		public function backorders_allowed(): bool {
			return $this->backorders;
		}

		public function set_backorders_allowed( bool $allowed ): void {
			$this->backorders = $allowed;
		}

		public function get_stock_quantity(): int|float|null {
			return $this->stockQuantity;
		}

		public function set_stock_quantity( int|float|null $quantity ): void {
			$this->stockQuantity = $quantity;
		}

		public function get_stock_managed_by_id(): int {
			return $this->stockOwnerId;
		}

		public function set_stock_managed_by_id( int $stock_owner_id ): void {
			$this->stockOwnerId = $stock_owner_id;
		}

		public function set_virtual( bool $virtual ): void {
			$this->virtual = $virtual;
		}

		public function get_meta( string $key, bool $single = true, string $context = 'view' ): mixed {
			unset( $single, $context );

			return $this->meta[ $key ] ?? '';
		}

		public function update_meta_data( string $key, mixed $value ): void {
			$this->meta[ $key ] = $value;
		}
	}
}

if ( ! class_exists( 'WC_Product_Simple' ) ) {
	class WC_Product_Simple extends WC_Product {}
}

if ( ! class_exists( 'WC_Product_Variation' ) ) {
	class WC_Product_Variation extends WC_Product {
		/** @var array<string, string> */
		private array $variationAttributes = array();

		public function __construct( int $id = 0 ) {
			parent::__construct( $id, 'variation' );
		}

		/** @return array<string, string> */
		public function get_variation_attributes(): array {
			return $this->variationAttributes;
		}

		/** @param array<string, string> $attributes Variation attributes. */
		public function set_variation_attributes( array $attributes ): void {
			$this->variationAttributes = $attributes;
		}
	}
}

if ( ! class_exists( 'WC_Cart' ) ) {
	class WC_Cart {
		/** @var array<string, array<string, mixed>> */
		public array $cart_contents = array();
		/** @var array<string, array<string, mixed>> */
		public array $removed_cart_contents = array();
		/** @var list<int> */
		public array $failProductIds = array();
		/** @var null|callable(int, int|float, int, array<string, string>, array<string, mixed>): void */
		public mixed $beforeAddToCart = null;
		private int $sequence = 0;

		/** @return array<string, array<string, mixed>> */
		public function get_cart(): array {
			return $this->cart_contents;
		}

		/** @param array<string, string> $variation @param array<string, mixed> $cart_item_data */
		public function add_to_cart( int $product_id, int|float $quantity = 1, int $variation_id = 0, array $variation = array(), array $cart_item_data = array() ): string|bool {
			if ( is_callable( $this->beforeAddToCart ) ) {
				( $this->beforeAddToCart )( $product_id, $quantity, $variation_id, $variation, $cart_item_data );
			}

			if ( in_array( $variation_id > 0 ? $variation_id : $product_id, $this->failProductIds, true ) ) {
				return false;
			}

			$product = wc_get_product( $variation_id > 0 ? $variation_id : $product_id );

			if ( ! $product instanceof WC_Product ) {
				return false;
			}

			$key = 'cart-key-' . ++$this->sequence;
			$this->cart_contents[ $key ] = array_merge(
				$cart_item_data,
				array(
					'key'          => $key,
					'product_id'   => $product_id,
					'variation_id' => $variation_id,
					'variation'    => $variation,
					'quantity'     => $quantity,
					'data'         => $product,
				)
			);

			return $key;
		}

		public function remove_cart_item( string $cart_item_key ): bool {
			if ( ! isset( $this->cart_contents[ $cart_item_key ] ) ) {
				return false;
			}

			$this->removed_cart_contents[ $cart_item_key ] = $this->cart_contents[ $cart_item_key ];
			unset( $this->cart_contents[ $cart_item_key ] );

			return true;
		}

		public function restore_cart_item( string $cart_item_key ): bool {
			if ( ! isset( $this->removed_cart_contents[ $cart_item_key ] ) ) {
				return false;
			}

			$this->cart_contents[ $cart_item_key ] = $this->removed_cart_contents[ $cart_item_key ];
			unset( $this->removed_cart_contents[ $cart_item_key ] );

			return true;
		}

		public function set_quantity( string $cart_item_key, int|float $quantity = 1, bool $refresh_totals = true ): bool {
			unset( $refresh_totals );

			if ( ! isset( $this->cart_contents[ $cart_item_key ] ) ) {
				return false;
			}

			$this->cart_contents[ $cart_item_key ]['quantity'] = $quantity;

			return true;
		}
	}
}

if ( ! class_exists( 'WC_Order_Item_Product' ) ) {
	class WC_Order_Item_Product {
		/** @var array<string, mixed> */
		public array $meta = array();

		public function add_meta_data( string $key, mixed $value, bool $unique = false ): void {
			unset( $unique );
			$this->meta[ $key ] = $value;
		}

		public function meta_exists( string $key ): bool {
			return array_key_exists( $key, $this->meta );
		}
	}
}

if ( ! class_exists( 'WC_Order' ) ) {
	class WC_Order {}
}

if ( ! class_exists( 'WC_Admin_Meta_Boxes' ) ) {
	class WC_Admin_Meta_Boxes {
		/** @var list<string> */
		public static array $errors = array();

		public static function add_error( string $message ): void {
			self::$errors[] = $message;
		}
	}
}

if ( ! class_exists( 'AIM_Test_WooCommerce' ) ) {
	class AIM_Test_WooCommerce {
		public WC_Cart $cart;

		public function __construct() {
			$this->cart = new WC_Cart();
		}
	}
}

$GLOBALS['aim_plugins_test_woocommerce'] = new AIM_Test_WooCommerce();

if ( ! function_exists( 'WC' ) ) {
	function WC(): AIM_Test_WooCommerce {
		return $GLOBALS['aim_plugins_test_woocommerce'];
	}
}

if ( ! function_exists( 'wc_get_product' ) ) {
	function wc_get_product( int $product_id ): WC_Product|false {
		return $GLOBALS['aim_plugins_test_products'][ $product_id ] ?? false;
	}
}

if ( ! function_exists( 'wc_add_notice' ) ) {
	function wc_add_notice( string $message, string $type = 'success' ): void {
		$GLOBALS['aim_plugins_test_notices'][] = array( $type, $message );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $text ): string {
		return strip_tags( $text );
	}
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4(): string {
		static $sequence = 0;
		++$sequence;

		return sprintf( '00000000-0000-4000-8000-%012d', $sequence );
	}
}

if ( ! function_exists( 'wc_get_price_decimals' ) ) {
	function wc_get_price_decimals(): int {
		return 2;
	}
}

if ( ! function_exists( 'wc_format_stock_quantity_for_display' ) ) {
	function wc_format_stock_quantity_for_display( int|float $quantity, WC_Product $product ): string {
		unset( $product );

		return (string) $quantity;
	}
}

if ( ! function_exists( 'wc_add_number_precision' ) ) {
	function wc_add_number_precision( int|float|string $value ): int {
		return (int) round( (float) $value * 100 );
	}
}

if ( ! function_exists( 'wc_remove_number_precision' ) ) {
	function wc_remove_number_precision( int|float $value ): float {
		return (float) $value / 100;
	}
}

if ( ! function_exists( 'wc_format_decimal' ) ) {
	function wc_format_decimal( int|float|string $value, int $decimals = 2 ): string {
		return number_format( (float) $value, $decimals, '.', '' );
	}
}

if ( ! function_exists( 'wc_get_price_to_display' ) ) {
	/** @param array{qty?: int|float, price?: int|float|string} $arguments Display-price arguments. */
	function wc_get_price_to_display( WC_Product $product, array $arguments = array() ): float {
		$quantity = (float) ( $arguments['qty'] ?? 1 );
		$price    = (float) ( $arguments['price'] ?? $product->get_price() );
		$line     = $price * $quantity;
		$rate     = (float) ( $GLOBALS['aim_plugins_test_tax_rates'][ $product->get_tax_class() ] ?? 0 );

		if ( $GLOBALS['aim_plugins_test_prices_include_tax'] ) {
			return 'incl' === $GLOBALS['aim_plugins_test_tax_display_shop'] ? $line : $line / ( 1 + $rate );
		}

		return 'incl' === $GLOBALS['aim_plugins_test_tax_display_shop'] ? $line * ( 1 + $rate ) : $line;
	}
}

if ( ! function_exists( 'wc_price' ) ) {
	function wc_price( int|float|string $price ): string {
		return '€' . number_format( (float) $price, wc_get_price_decimals(), '.', '' );
	}
}

if ( ! function_exists( 'get_woocommerce_currency' ) ) {
	function get_woocommerce_currency(): string {
		return 'EUR';
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability, mixed ...$arguments ): bool {
		unset( $capability, $arguments );

		return $GLOBALS['aim_plugins_test_can_edit_products'];
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( string $nonce, string $action ): int|false {
		return $nonce === 'synthetic-valid-nonce' && $action === 'aim_advanced_bundles_save_components' ? 1 : false;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( mixed $value ): mixed {
		return $value;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $value ): string {
		return trim( strip_tags( $value ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( string $value ): string {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?? '';
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( mixed $value ): int {
		return abs( (int) $value );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		unset( $domain );

		return $text;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( string $url ): string {
		return htmlspecialchars( $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( string $text, string $domain = 'default' ): void {
		unset( $domain );

		echo esc_html( $text );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( string $html ): string {
		return $html;
	}
}

if ( ! function_exists( 'wc_get_stock_html' ) ) {
	function wc_get_stock_html( WC_Product $product ): string {
		return $product->is_in_stock() ? '<p class="stock in-stock">In stock</p>' : '<p class="stock out-of-stock">Out of stock</p>';
	}
}

if ( ! function_exists( 'woocommerce_quantity_input' ) ) {
	/** @param array<string, int|float|string> $arguments Quantity input arguments. */
	function woocommerce_quantity_input( array $arguments = array() ): void {
		$value = (string) ( $arguments['input_value'] ?? 1 );

		echo '<input class="qty" type="number" value="' . esc_attr( $value ) . '" />';
	}
}

if ( ! function_exists( 'is_product' ) ) {
	function is_product(): bool {
		return (bool) $GLOBALS['aim_plugins_test_is_product'];
	}
}

if ( ! function_exists( 'get_queried_object_id' ) ) {
	function get_queried_object_id(): int {
		return (int) $GLOBALS['aim_plugins_test_queried_object_id'];
	}
}

if ( ! function_exists( 'is_multisite' ) ) {
	function is_multisite(): bool {
		return (bool) $GLOBALS['aim_plugins_test_is_multisite'];
	}
}

if ( ! function_exists( 'get_site_option' ) ) {
	function get_site_option( string $option, mixed $default_value = false ): mixed {
		return 'active_sitewide_plugins' === $option
			? $GLOBALS['aim_plugins_test_network_plugins']
			: $default_value;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, mixed $default_value = false ): mixed {
		return $GLOBALS['aim_plugins_test_options'][ $option ] ?? $default_value;
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( string $file ): string {
		$file = str_replace( '\\', '/', $file );

		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}

if ( ! function_exists( 'get_current_blog_id' ) ) {
	function get_current_blog_id(): int {
		return (int) $GLOBALS['aim_plugins_test_current_blog_id'];
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['aim_plugins_test_hooks'][ $hook_name ][ $priority ][] = array( $callback, $accepted_args );

		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		return add_action( $hook_name, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( string $hook_name, mixed ...$arguments ): void {
		$priorities = $GLOBALS['aim_plugins_test_hooks'][ $hook_name ] ?? array();
		ksort( $priorities, SORT_NUMERIC );

		foreach ( $priorities as $callbacks ) {
			foreach ( $callbacks as [ $callback, $accepted_args ] ) {
				$callback( ...array_slice( $arguments, 0, $accepted_args ) );
			}
		}
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook_name, mixed $value, mixed ...$arguments ): mixed {
		$priorities = $GLOBALS['aim_plugins_test_hooks'][ $hook_name ] ?? array();
		ksort( $priorities, SORT_NUMERIC );

		foreach ( $priorities as $callbacks ) {
			foreach ( $callbacks as [ $callback, $accepted_args ] ) {
				$value = $callback( ...array_slice( array_merge( array( $value ), $arguments ), 0, $accepted_args ) );
			}
		}

		return $value;
	}
}

if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
	/** @param array<string, mixed> $arguments Store API extension arguments. */
	function woocommerce_store_api_register_endpoint_data( array $arguments ): bool {
		$GLOBALS['aim_plugins_test_endpoint_data'][] = $arguments;

		return true;
	}
}

if ( ! function_exists( 'plugins_url' ) ) {
	function plugins_url( string $path = '', string $plugin = '' ): string {
		unset( $plugin );

		return 'https://synthetic.invalid/plugins/aim-advanced-bundles/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	/** @param list<string> $dependencies Script dependencies. */
	function wp_enqueue_script(
		string $handle,
		string $source = '',
		array $dependencies = array(),
		string|bool|null $version = false,
		bool|array $arguments = false
	): void {
		$GLOBALS['aim_plugins_test_scripts'][ $handle ] = array(
			'source'       => $source,
			'dependencies' => $dependencies,
			'version'      => $version,
			'arguments'    => $arguments,
		);
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	/** @param list<string> $dependencies Style dependencies. */
	function wp_enqueue_style(
		string $handle,
		string $source = '',
		array $dependencies = array(),
		string|bool|null $version = false,
		string $media = 'all'
	): void {
		$GLOBALS['aim_plugins_test_styles'][ $handle ] = array(
			'source'       => $source,
			'dependencies' => $dependencies,
			'version'      => $version,
			'media'        => $media,
		);
	}
}
