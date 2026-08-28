/**
 * Fixed bundle Cart and Checkout Blocks filters.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

( function ( blocksCheckout ) {
	'use strict';

	if ( ! blocksCheckout || typeof blocksCheckout.registerCheckoutFilters !== 'function' ) {
		return;
	}

	var namespace      = 'aim-advanced-bundles';
	var componentClass = 'aim-bundle-component-cart-item';

	function roleFromExtensions( extensions, args ) {
		var cartItemExtensions = args && args.cartItem && args.cartItem.extensions;
		var itemData           = cartItemExtensions && cartItemExtensions[ namespace ];
		var filterData         = extensions && extensions[ namespace ];

		if ( itemData && typeof itemData.role === 'string' ) {
			return itemData.role;
		}

		return filterData && typeof filterData.role === 'string' ? filterData.role : '';
	}

	function isComponent( extensions, args ) {
		return roleFromExtensions( extensions, args ) === 'component';
	}

	blocksCheckout.registerCheckoutFilters(
		namespace,
		{
			showRemoveItemLink: function ( defaultValue, extensions, args ) {
				return isComponent( extensions, args ) ? false : defaultValue;
			},
			cartItemClass: function ( defaultValue, extensions, args ) {
				if ( ! isComponent( extensions, args ) ) {
					return defaultValue;
				}

				return ( typeof defaultValue === 'string' && defaultValue.length ? defaultValue + ' ' : '' ) + componentClass;
			}
		}
	);
}( window.wc && window.wc.blocksCheckout ) );
