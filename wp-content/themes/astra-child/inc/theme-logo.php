<?php
defined( 'ABSPATH' ) || exit;

/**
 * Sets the theme's default logo.
 *
 * Places the Geeta Pariwar Nepal logo in `assets/images/geeta-pariwar-logo.png`.
 * When no logo is uploaded via Customizer → Site Identity, this fallback is used.
 */

define( 'GP_DEFAULT_LOGO', ASTRA_CHILD_URI . 'assets/images/geeta-pariwar-logo.png' );

/**
 * Filter the custom logo markup to supply a default when none is set.
 */
add_filter( 'get_custom_logo', function ( $html ) {
	if ( $html ) {
		return $html;
	}

	$url = esc_url( GP_DEFAULT_LOGO );

	return sprintf(
		'<a href="%1$s" class="custom-logo-link" rel="home" itemprop="url">
			<img width="44" height="44" src="%2$s" class="custom-logo" alt="Geeta Pariwar Nepal" itemprop="logo" decoding="async">
		</a>',
		esc_url( home_url( '/' ) ),
		$url
	);
} );


