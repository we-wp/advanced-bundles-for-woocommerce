<?php
/**
 * WooCommerce-native editor for fixed bundle components.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\WooCommerce\Admin;

use AIMPlugins\AdvancedBundles\Contracts\BundleComponent;
use AIMPlugins\AdvancedBundles\Contracts\BundleDefinition;
use AIMPlugins\AdvancedBundles\PluginEnvironment;
use AIMPlugins\AdvancedBundles\WooCommerce\BundleComponentResolver;
use AIMPlugins\AdvancedBundles\WooCommerce\Persistence\BundleDefinitionRepository;
use AIMPlugins\AdvancedBundles\WooCommerce\ProductTypeRegistrar;
use DomainException;
use InvalidArgumentException;
use OverflowException;

final readonly class BundleProductEditor {
	private const NONCE_ACTION = 'aim_advanced_bundles_save_components';
	private const NONCE_NAME   = 'aim_advanced_bundles_components_nonce';
	private const FIELD_NAME   = 'aim_advanced_bundles_components';

	public function __construct(
		private BundleDefinitionRepository $repository = new BundleDefinitionRepository(),
		private BundleComponentResolver $resolver = new BundleComponentResolver()
	) {}

	public function register(): void {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'addTab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'renderPanel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAssets' ) );
	}

	/**
	 * @param array<string, array<string, mixed>> $tabs Product data tabs.
	 * @return array<string, array<string, mixed>>
	 */
	public function addTab( array $tabs ): array {
		$tabs['aim_bundle_components'] = array(
			'label'    => __( 'Bundle components', 'aim-advanced-bundles' ),
			'target'   => 'aim_bundle_components_product_data',
			'class'    => array( 'show_if_aim_bundle' ),
			'priority' => 25,
		);

		return $tabs;
	}

	public function renderPanel(): void {
		$product = wc_get_product( get_the_ID() );

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		try {
			$definition = $this->repository->find( $product );
		} catch ( InvalidArgumentException ) {
			$definition = null;
		}

		$components = null === $definition ? array() : $definition->components();
		?>
		<div id="aim_bundle_components_product_data" class="panel woocommerce_options_panel hidden">
			<div class="options_group aim-bundle-components-editor">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
				<p class="form-field aim-bundle-components-intro">
					<strong><?php esc_html_e( 'Fixed bundle contents', 'aim-advanced-bundles' ); ?></strong><br>
					<span class="description">
						<?php esc_html_e( 'Choose simple products or exact variations. Component prices and stock stay native to WooCommerce.', 'aim-advanced-bundles' ); ?>
					</span>
				</p>
				<table class="widefat striped aim-bundle-components-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Product or variation', 'aim-advanced-bundles' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Quantity', 'aim-advanced-bundles' ); ?></th>
							<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'aim-advanced-bundles' ); ?></span></th>
						</tr>
					</thead>
					<tbody data-aim-bundle-components>
						<?php foreach ( $components as $index => $component ) : ?>
							<?php $this->renderRow( $component, $index ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="toolbar">
					<button type="button" class="button" data-aim-bundle-add-component>
						<?php esc_html_e( 'Add component', 'aim-advanced-bundles' ); ?>
					</button>
				</p>
				<p class="description">
					<?php esc_html_e( 'At least one component is required. Bundle products cannot contain other bundles.', 'aim-advanced-bundles' ); ?>
				</p>
			</div>
		</div>
		<script type="text/html" id="tmpl-aim-bundle-component-row">
			<?php $this->renderRow( null, '{{ data.index }}' ); ?>
		</script>
		<?php
	}

	public function save( \WC_Product $product ): void {
		if ( ProductTypeRegistrar::PRODUCT_TYPE !== $product->get_type() ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE_NAME ] ) && is_string( $_POST[ self::NONCE_NAME ] )
			? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) )
			: '';

		if ( ! current_user_can( 'edit_post', $product->get_id() ) || '' === $nonce || 1 !== wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			\WC_Admin_Meta_Boxes::add_error(
				__( 'Bundle components were not saved because the security check failed.', 'aim-advanced-bundles' )
			);

			return;
		}

		try {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nested rows are unslashed next; every consumed scalar is sanitized and strictly validated in componentsFromRows().
			$raw_rows = $_POST[ self::FIELD_NAME ] ?? null;

			if ( ! is_array( $raw_rows ) ) {
				throw new InvalidArgumentException( 'Add at least one bundle component.' );
			}

			$unslashed_rows = wp_unslash( $raw_rows );
			$components     = $this->componentsFromRows( $unslashed_rows, $product->get_id() );
			$this->repository->stageComponents( $product, $components );

			$product->set_regular_price( '' );
			$product->set_sale_price( '' );
			$product->set_price( '' );
			$product->set_manage_stock( false );
			$product->set_virtual( true );
		} catch ( DomainException | InvalidArgumentException | OverflowException $exception ) {
			\WC_Admin_Meta_Boxes::add_error( wp_strip_all_tags( $exception->getMessage() ) );
		}
	}

	public function enqueueAssets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( null === $screen || 'product' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'aim-advanced-bundles-admin',
			plugins_url( 'assets/admin-components.css', PluginEnvironment::file() ),
			array(),
			PluginEnvironment::version()
		);
		wp_enqueue_script(
			'aim-advanced-bundles-admin',
			plugins_url( 'assets/admin-components.js', PluginEnvironment::file() ),
			array( 'jquery', 'wc-enhanced-select', 'wp-util' ),
			PluginEnvironment::version(),
			true
		);
	}

	/**
	 * @param array<array-key, mixed> $raw_rows Raw component rows.
	 * @return list<BundleComponent>
	 */
	private function componentsFromRows( array $raw_rows, int $bundle_product_id ): array {
		if ( count( $raw_rows ) > BundleDefinition::MAX_COMPONENTS ) {
			throw new InvalidArgumentException( 'A bundle must contain between 1 and 500 components.' );
		}

		$components = array();

		foreach ( array_values( $raw_rows ) as $position => $raw_row ) {
			if ( ! is_array( $raw_row ) ) {
				throw new InvalidArgumentException( 'Every component row must contain a product and quantity.' );
			}

			$item_id   = $this->positiveInteger( $raw_row['item_id'] ?? null, 'Component product ID', PHP_INT_MAX );
			$quantity  = $this->positiveInteger( $raw_row['quantity'] ?? null, 'Component quantity', BundleComponent::MAX_QUANTITY );
			$stored_id = sanitize_key( is_scalar( $raw_row['component_id'] ?? null ) ? (string) $raw_row['component_id'] : '' );

			if ( $item_id === $bundle_product_id ) {
				throw new DomainException( 'A bundle cannot contain itself.' );
			}

			$item = wc_get_product( $item_id );

			if ( ! $item instanceof \WC_Product ) {
				throw new DomainException( 'A selected bundle component no longer exists.' );
			}

			if ( $item->is_type( 'variation' ) ) {
				$product_id   = $item->get_parent_id();
				$variation_id = $item->get_id();
			} elseif ( $item->is_type( 'simple' ) ) {
				$product_id   = $item->get_id();
				$variation_id = null;
			} else {
				throw new DomainException( 'Free bundles accept only simple products or exact variations.' );
			}

			$component_id = '' === $stored_id ? $this->newComponentId() : $stored_id;
			$component    = new BundleComponent( $component_id, $product_id, $variation_id, $quantity, $position );

			$this->resolver->resolveComponent( $component );
			$components[] = $component;
		}

		if ( array() === $components ) {
			throw new InvalidArgumentException( 'Add at least one bundle component.' );
		}

		return $components;
	}

	private function newComponentId(): string {
		return 'component-' . str_replace( '-', '', strtolower( wp_generate_uuid4() ) );
	}

	private function positiveInteger( mixed $value, string $field, int $maximum ): int {
		if ( ! is_scalar( $value ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception message; save() strips tags before handing it to WooCommerce's notice boundary.
			throw new InvalidArgumentException( $field . ' must be a positive whole number.' );
		}

		$sanitized = sanitize_text_field( (string) $value );

		if ( 1 !== preg_match( '/\A[1-9][0-9]*\z/', $sanitized ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception message; save() strips tags before handing it to WooCommerce's notice boundary.
			throw new InvalidArgumentException( $field . ' must be a positive whole number.' );
		}

		$validated = filter_var(
			$sanitized,
			FILTER_VALIDATE_INT,
			array(
				'options' => array(
					'min_range' => 1,
					'max_range' => $maximum,
				),
			)
		);

		if ( false === $validated ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception message; save() strips tags before handing it to WooCommerce's notice boundary.
			throw new InvalidArgumentException( $field . ' is outside the supported range.' );
		}

		return $validated;
	}

	private function renderRow( ?BundleComponent $component, int|string $index ): void {
		$item_id      = null === $component ? 0 : ( $component->variationId ?? $component->productId );
		$item         = $item_id > 0 ? wc_get_product( $item_id ) : false;
		$component_id = null === $component ? '' : $component->id;
		$quantity     = null === $component ? 1 : $component->quantity;
		$select_id    = 'aim-bundle-component-item-' . $index;
		$quantity_id  = 'aim-bundle-component-quantity-' . $index;
		?>
		<tr data-aim-bundle-component-row>
			<td>
				<label class="aim-bundle-component-label" for="<?php echo esc_attr( $select_id ); ?>">
					<?php esc_html_e( 'Product or variation', 'aim-advanced-bundles' ); ?>
				</label>
				<select
					id="<?php echo esc_attr( $select_id ); ?>"
					class="wc-product-search"
					style="width: 100%;"
					name="<?php echo esc_attr( self::FIELD_NAME . '[' . $index . '][item_id]' ); ?>"
					data-placeholder="<?php esc_attr_e( 'Search for a simple product or variation…', 'aim-advanced-bundles' ); ?>"
					data-action="woocommerce_json_search_products_and_variations"
				>
					<?php if ( $item instanceof \WC_Product ) : ?>
						<option value="<?php echo esc_attr( (string) $item_id ); ?>" selected>
							<?php echo esc_html( wp_strip_all_tags( $item->get_formatted_name() ) ); ?>
						</option>
					<?php endif; ?>
				</select>
				<input
					type="hidden"
					name="<?php echo esc_attr( self::FIELD_NAME . '[' . $index . '][component_id]' ); ?>"
					value="<?php echo esc_attr( $component_id ); ?>"
				>
			</td>
			<td>
				<label class="aim-bundle-component-label" for="<?php echo esc_attr( $quantity_id ); ?>">
					<?php esc_html_e( 'Component quantity', 'aim-advanced-bundles' ); ?>
				</label>
				<input
					id="<?php echo esc_attr( $quantity_id ); ?>"
					type="number"
					class="short"
					name="<?php echo esc_attr( self::FIELD_NAME . '[' . $index . '][quantity]' ); ?>"
					value="<?php echo esc_attr( (string) $quantity ); ?>"
					min="1"
					max="<?php echo esc_attr( (string) BundleComponent::MAX_QUANTITY ); ?>"
					step="1"
					required
				>
			</td>
			<td>
				<button type="button" class="button-link-delete" data-aim-bundle-remove-component>
					<?php esc_html_e( 'Remove', 'aim-advanced-bundles' ); ?>
				</button>
			</td>
		</tr>
		<?php
	}
}
