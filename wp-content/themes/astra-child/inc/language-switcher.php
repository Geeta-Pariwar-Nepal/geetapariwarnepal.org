<?php
defined( 'ABSPATH' ) || exit;

/**
 * ──────────────────────────────────────────────────────────
 * LANGUAGE SWITCHER — Google Translate auto-translate
 * ──────────────────────────────────────────────────────────
 *
 * Adds a Google Translate dropdown in the site header.
 * Visitors can switch between नेपाली, English, हिन्दी
 * and the entire page is auto-translated.
 *
 * Admin setting:  Appearance → Customize → Menus → Language Switcher
 *
 * Usage in templates:
 *   $lang = gp_current_language();
 *   if ( 'en' === $lang ) { ... }
 */

function gp_site_languages() {
	return array(
		'ne' => 'नेपाली',
		'en' => 'English',
		'hi' => 'हिन्दी',
	);
}

function gp_current_language() {
	$cookie = '';
	if ( ! empty( $_COOKIE['googtrans'] ) ) {
		$parts = explode( '/', $_COOKIE['googtrans'] );
		$cookie = end( $parts );
	}
	if ( $cookie && array_key_exists( $cookie, gp_site_languages() ) ) {
		return $cookie;
	}
	return get_theme_mod( 'gp_site_language', 'ne' );
}

/* ─── Customizer ───────────────────────────────────────── */

add_action( 'customize_register', function ( $wp_customize ) {
	$wp_customize->add_section( 'gp_language_section', array(
		'title'    => 'Language Switcher',
		'panel'    => 'nav_menus',
		'priority' => 30,
	) );

	$wp_customize->add_setting( 'gp_site_language', array(
		'default'           => 'ne',
		'sanitize_callback' => function ( $val ) {
			return array_key_exists( $val, gp_site_languages() ) ? $val : 'ne';
		},
		'transport'         => 'refresh',
	) );

	$wp_customize->add_control( 'gp_site_language', array(
		'section'  => 'gp_language_section',
		'label'    => 'Default Site Language',
		'type'     => 'select',
		'choices'  => gp_site_languages(),
	) );
} );

/* ─── body class ───────────────────────────────────────── */

add_filter( 'body_class', function ( $classes ) {
	$classes[] = 'gp-lang-' . gp_current_language();
	return $classes;
} );

/* ─── Google Translate widget in header ────────────────── */

add_action( 'astra_masthead_content', function () {
	?>
	<div class="gp-lang-switcher">
		<div id="gp_google_translate" class="gp-google-translate"></div>
	</div>
	<?php
} );

/* ─── load Google Translate script ─────────────────────── */

add_action( 'wp_enqueue_scripts', function () {
	wp_add_inline_script( 'jquery', '
		function gpGoogleTranslateInit() {
			new google.translate.TranslateElement({
				pageLanguage: "ne",
				includedLanguages: "ne,en,hi",
				layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
				autoDisplay: false
			}, "gp_google_translate");
		}
	' );
	wp_enqueue_script( 'gp-google-translate', 'https://translate.google.com/translate_a/element.js?cb=gpGoogleTranslateInit', array( 'jquery' ), null, true );
}, 99 );
