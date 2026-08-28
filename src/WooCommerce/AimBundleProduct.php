<?php
/**
 * WooCommerce product implementation for aim_bundle.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\WooCommerce;

use AIMPlugins\AdvancedBundles\WooCommerce\Pricing\BundlePriceCalculator;
use DomainException;
use InvalidArgumentException;

final class AimBundleProduct extends \WC_Product_Simple {
	private bool $cartContainer = false;

	public function get_type(): string {
		return ProductTypeRegistrar::PRODUCT_TYPE;
	}

	public function get_price( $context = 'view' ): string {
		if ( 'edit' === $context ) {
			return parent::get_price( $context );
		}

		if ( $this->cartContainer ) {
			return '0';
		}

		try {
			return ( new BundlePriceCalculator() )->calculate( $this );
		} catch ( DomainException | InvalidArgumentException ) {
			return '';
		}
	}

	public function get_price_html( $price = '' ): string {
		if ( $this->cartContainer ) {
			return (string) parent::get_price_html( $price );
		}

		try {
			$display_price = ( new BundlePriceCalculator() )->calculateForDisplay( $this );
			$price         = wc_price( (float) $display_price ) . $this->get_price_suffix();
		} catch ( DomainException | InvalidArgumentException ) {
			$empty_price = apply_filters( 'woocommerce_empty_price_html', '', $this );
			$price       = is_string( $empty_price ) ? $empty_price : '';
		}

		$filtered_price = apply_filters( 'woocommerce_get_price_html', $price, $this );

		return is_string( $filtered_price ) ? $filtered_price : $price;
	}

	public function get_regular_price( $context = 'view' ): string {
		if ( 'edit' === $context ) {
			return parent::get_regular_price( $context );
		}

		return $this->get_price( $context );
	}

	public function markAsCartContainer(): void {
		$this->cartContainer = true;
	}

	public function is_virtual(): bool {
		return true;
	}

	public function needs_shipping(): bool {
		return false;
	}

	public function managing_stock(): bool {
		return false;
	}
}
