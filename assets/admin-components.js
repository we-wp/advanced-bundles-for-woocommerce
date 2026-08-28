/**
 * Fixed component row controls.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

( function ( $, wp ) {
	'use strict';

	const $rows = $( '[data-aim-bundle-components]' );

	if ( ! $rows.length ) {
		return;
	}

	$( '[data-aim-bundle-add-component]' ).on(
		'click',
		function () {
			const index = String( Date.now() ) + String( Math.floor( Math.random() * 1000 ) );
			$rows.append( wp.template( 'aim-bundle-component-row' )( { index } ) );
			$( document.body ).trigger( 'wc-enhanced-select-init' );
		}
	);

	$rows.on(
		'click',
		'[data-aim-bundle-remove-component]',
		function () {
			$( this ).closest( '[data-aim-bundle-component-row]' ).remove();
		}
	);
}( jQuery, window.wp ) );
