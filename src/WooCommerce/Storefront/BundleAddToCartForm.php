<?php
/**
 * Accessible fixed-bundle product form.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\WooCommerce\Storefront;

use AIMPlugins\AdvancedBundles\PluginEnvironment;
use AIMPlugins\AdvancedBundles\WooCommerce\AimBundleProduct;
use AIMPlugins\AdvancedBundles\WooCommerce\BundleComponentResolver;
use AIMPlugins\AdvancedBundles\WooCommerce\Persistence\BundleDefinitionRepository;
use DomainException;
use InvalidArgumentException;

final readonly class BundleAddToCartForm {
	public function __construct(
		private BundleDefinitionRepository $repository = new BundleDefinitionRepository(),
		private BundleComponentResolver $resolver = new BundleComponentResolver()
	) {}

	public function register(): void {
		add_action( 'woocommerce_aim_bundle_add_to_cart', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueueStyles' ) );
	}

	public function enqueueStyles(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$product = wc_get_product( get_queried_object_id() );

		if ( ! $product instanceof AimBundleProduct ) {
			return;
		}

		wp_enqueue_style(
			'aim-advanced-bundles-storefront',
			plugins_url( 'assets/storefront-bundle.css', PluginEnvironment::file() ),
			array(),
			PluginEnvironment::version()
		);
	}

	public function render(): void {
		$product = $GLOBALS['product'] ?? null;

		if ( ! $product instanceof AimBundleProduct ) {
			return;
		}

		try {
			$definition = $this->repository->find( $product );

			if ( null === $definition ) {
				throw new DomainException( 'This bundle has no component definition.' );
			}

			$components = $this->resolver->resolveDefinition( $definition );
		} catch ( DomainException | InvalidArgumentException ) {
			echo '<p class="stock out-of-stock">';
			esc_html_e( 'This bundle is not available right now.', 'aim-advanced-bundles' );
			echo '</p>';

			return;
		}

		if ( ! $product->is_purchasable() ) {
			return;
		}

		echo wp_kses_post( wc_get_stock_html( $product ) );

		if ( ! $product->is_in_stock() ) {
			return;
		}

		$heading_id = 'aim-bundle-contents-' . $product->get_id();

		do_action( 'woocommerce_before_add_to_cart_form' );
		$form_action = apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() );

		if ( ! is_string( $form_action ) ) {
			$form_action = $product->get_permalink();
		}
		?>
		<section class="aim-bundle-contents" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
			<h2 id="<?php echo esc_attr( $heading_id ); ?>"><?php esc_html_e( 'Included in this bundle', 'aim-advanced-bundles' ); ?></h2>
			<table class="shop_table aim-bundle-components-table">
				<caption class="screen-reader-text"><?php esc_html_e( 'Included products and fixed quantities.', 'aim-advanced-bundles' ); ?></caption>
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Product', 'aim-advanced-bundles' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Quantity', 'aim-advanced-bundles' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $components as $resolved ) : ?>
						<tr>
							<th scope="row">
								<a
									class="aim-bundle-component-link"
									href="<?php echo esc_url( $resolved->product->get_permalink() ); ?>"
									target="_blank"
									rel="noopener noreferrer"
								>
									<?php
									echo wp_kses_post(
										$resolved->product->get_image(
											'woocommerce_thumbnail',
											array(
												'alt'      => '',
												'class'    => 'aim-bundle-component-thumbnail',
												'loading'  => 'lazy',
												'decoding' => 'async',
											)
										)
									);
									?>
									<span class="aim-bundle-component-details">
										<span class="aim-bundle-component-name"><?php echo esc_html( $resolved->product->get_name() ); ?></span>
										<span class="aim-bundle-component-price">
											<?php esc_html_e( 'Each:', 'aim-advanced-bundles' ); ?>
											<?php echo wp_kses_post( $resolved->product->get_price_html() ); ?>
										</span>
										<span class="screen-reader-text"><?php esc_html_e( 'Opens in a new tab.', 'aim-advanced-bundles' ); ?></span>
									</span>
								</a>
							</th>
							<td class="aim-bundle-component-quantity-cell">
								<?php /* translators: %d: fixed component quantity. */ ?>
								<span class="aim-bundle-component-quantity" aria-label="<?php echo esc_attr( sprintf( __( 'Fixed quantity: %d', 'aim-advanced-bundles' ), $resolved->component->quantity ) ); ?>">
									<?php echo esc_html( '× ' . (string) $resolved->component->quantity ); ?>
								</span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>
		<form
			class="cart"
			action="<?php echo esc_url( $form_action ); ?>"
			method="post"
			enctype="multipart/form-data"
		>
			<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>
			<?php do_action( 'woocommerce_before_add_to_cart_quantity' ); ?>
			<?php
			woocommerce_quantity_input(
				array(
					'min_value'   => $product->get_min_purchase_quantity(),
					'max_value'   => $product->get_max_purchase_quantity(),
					'input_value' => $product->get_min_purchase_quantity(),
				)
			);
			do_action( 'woocommerce_after_add_to_cart_quantity' );
			?>
			<button
				type="submit"
				name="add-to-cart"
				value="<?php echo esc_attr( (string) $product->get_id() ); ?>"
				class="single_add_to_cart_button button alt"
			>
				<?php echo esc_html( $product->single_add_to_cart_text() ); ?>
			</button>
			<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
		</form>
		<?php
		do_action( 'woocommerce_after_add_to_cart_form' );
	}
}
