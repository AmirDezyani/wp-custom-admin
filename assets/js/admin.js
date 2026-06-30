/**
 * WP Custom Admin — admin chrome enhancements.
 *
 * Prepends a branded header (client logo, or a monogram tile + product name) to
 * the top of the admin menu rail. Data arrives via wp_localize_script, so every
 * value is server-sanitized; the DOM is built with textContent / setAttribute to
 * stay XSS-safe. Degrades silently when the rail is absent or JS is disabled.
 */
( function () {
	'use strict';

	var data = window.wpcaAdmin || {};
	var wrap = document.getElementById( 'adminmenuwrap' );
	var menu = document.getElementById( 'adminmenu' );

	if ( ! wrap || ! menu || document.querySelector( '.wpca-brand' ) ) {
		return;
	}

	var brand = document.createElement( 'div' );
	brand.className = data.logoUrl ? 'wpca-brand has-logo' : 'wpca-brand';

	// The monogram is always rendered so the folded (icon-only) rail keeps a mark.
	var mark = document.createElement( 'span' );
	mark.className = 'wpca-brand-mark';
	mark.setAttribute( 'aria-hidden', 'true' );
	mark.textContent = data.initial || 'W';
	brand.appendChild( mark );

	if ( data.logoUrl ) {
		var logo = document.createElement( 'img' );
		logo.className = 'wpca-brand-logo';
		logo.src = data.logoUrl;
		logo.alt = data.brandName || '';
		brand.appendChild( logo );
	} else {
		var name = document.createElement( 'span' );
		name.className = 'wpca-brand-name';
		name.textContent = data.brandName || '';
		brand.appendChild( name );
	}

	wrap.insertBefore( brand, menu );
}() );
