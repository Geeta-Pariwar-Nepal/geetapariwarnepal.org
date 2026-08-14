<?php

defined( 'ABSPATH' ) || exit;

define( 'ASTRA_CHILD_VERSION', '3.3.0' );
define( 'ASTRA_CHILD_DIR', trailingslashit( get_stylesheet_directory() ) );
define( 'ASTRA_CHILD_URI', trailingslashit( get_stylesheet_directory_uri() ) );

function astra_child_load_textdomain() {
	load_child_theme_textdomain( 'geetapariwar-astra-child', ASTRA_CHILD_DIR . 'languages' );
}
add_action( 'after_setup_theme', 'astra_child_load_textdomain' );

function astra_child_enqueue_styles() {
	wp_enqueue_style( 'astra-child-style', ASTRA_CHILD_URI . 'style.css', array( 'astra-theme-css' ), ASTRA_CHILD_VERSION );
}
add_action( 'wp_enqueue_scripts', 'astra_child_enqueue_styles' );

function astra_child_enqueue_fonts() {
	wp_enqueue_style( 'gp-google-fonts', 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap', array(), null );
	wp_enqueue_style( 'gp-font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', array(), '6.5.1' );
}
add_action( 'wp_enqueue_scripts', 'astra_child_enqueue_fonts' );

/**
 * Force the off-canvas/mobile menu to use the Primary Menu.
 *
 * Astra registers two separate theme locations — 'primary' (desktop)
 * and 'mobile_menu' (off-canvas). If different menus are assigned,
 * desktop and mobile show different items. This filter ensures the
 * mobile menu always renders the same WordPress menu as the primary
 * header menu, keeping navigation consistent across all devices.
 */
function gp_force_primary_menu_for_mobile( $locations ) {
	if ( is_admin() || ! is_array( $locations ) ) {
		return $locations;
	}
	if ( ! empty( $locations['primary'] ) ) {
		$locations['mobile_menu'] = $locations['primary'];
	}
	return $locations;
}
add_filter( 'theme_mod_nav_menu_locations', 'gp_force_primary_menu_for_mobile' );

/**
 * Retrieve the two Geeta book products.
 *
 * Override product IDs via filter:
 *   add_filter( 'gp_geeta_book_ids', function() { return array( 123, 456 ); } );
 */
function gp_get_geeta_books() {
	$ids = apply_filters( 'gp_geeta_book_ids', array() );

	// Return directly if IDs are configured via filter
	if ( count( $ids ) >= 2 ) {
		return array_filter( array_map( 'wc_get_product', $ids ) );
	}

	$titles = array( 'सरल गीता', 'त्रिरत्न गीता' );

	foreach ( $titles as $title ) {
		$found = null;

		// Strategy 1 — exact title lookup (reliable, handles Unicode)
		$post = get_page_by_title( $title, OBJECT, 'product' );
		if ( $post && 'publish' === get_post_status( $post ) ) {
			$found = wc_get_product( $post->ID );
		}

		// Strategy 2 — slug-based lookup
		if ( ! $found ) {
			$slug = sanitize_title( $title );
			$id   = wc_get_product_id_by_slug( $slug );
			if ( $id ) {
				$found = wc_get_product( $id );
			}
		}

		// Strategy 3 — LIKE search fallback
		if ( ! $found ) {
			$query = new WP_Query( array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				's'              => $title,
				'no_found_rows'  => true,
			) );
			foreach ( $query->posts as $p ) {
				if ( $p->post_title === $title || false !== strpos( $p->post_title, $title ) ) {
					$found = wc_get_product( $p->ID );
					break;
				}
			}
		}

		if ( $found ) {
			$ids[] = $found->get_id();
		}
	}

	return array_filter( array_map( 'wc_get_product', array_unique( $ids ) ) );
}

/**
 * WhatsApp order URL helper.
 *
 * Reads the number from the WhatsApp Notification settings
 * (WooCommerce → WhatsApp). Falls back to the default constant.
 */
function gp_whatsapp_url( $text = '' ) {
	$number = get_option( 'gp_whatsapp_number', GP_WHATSAPP_DEFAULT_NUMBER );
	$number = apply_filters( 'gp_whatsapp_number', $number );
	$number = preg_replace( '/[^0-9]/', '', $number );
	if ( $text ) {
		$text = '?text=' . rawurlencode( $text );
	}
	return 'https://wa.me/' . $number . $text;
}

/**
 * Override WooCommerce template for the Geeta books single product page.
 */
function gp_geeta_single_product_template( $template, $slug, $name ) {
	if ( 'content-single-product' === $slug && is_singular( 'product' ) ) {
		$product_title = get_the_title();
		if ( false !== strpos( $product_title, 'गीता' ) ) {
			$child = ASTRA_CHILD_DIR . 'woocommerce/content-single-product.php';
			if ( file_exists( $child ) ) {
				return $child;
			}
		}
	}
	return $template;
}
add_filter( 'wc_get_template_part', 'gp_geeta_single_product_template', 10, 3 );

/**
 * Single product — WhatsApp button after add-to-cart.
 */
function gp_single_product_whatsapp_button() {
	global $product;
	if ( ! $product ) {
		return;
	}
	$name = $product->get_name();
	$url  = gp_whatsapp_url( "म $name अर्डर गर्न चाहन्छु।" );
	echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer" class="gb-btn gb-btn-whatsapp gb-btn-block" style="margin-top:12px">💬 WhatsApp मार्फत अर्डर गर्नुहोस्</a>';
}
add_action( 'woocommerce_after_add_to_cart_button', 'gp_single_product_whatsapp_button' );

require_once ASTRA_CHILD_DIR . 'inc/whatsapp-notifications.php';
require_once ASTRA_CHILD_DIR . 'inc/cpt-depots.php';
require_once ASTRA_CHILD_DIR . 'inc/depots.php';
require_once ASTRA_CHILD_DIR . 'inc/checkout.php';
require_once ASTRA_CHILD_DIR . 'inc/admin-display.php';
require_once ASTRA_CHILD_DIR . 'inc/customer-display.php';
require_once ASTRA_CHILD_DIR . 'inc/email-routing.php';
require_once ASTRA_CHILD_DIR . 'inc/language-switcher.php';
require_once ASTRA_CHILD_DIR . 'inc/theme-logo.php';
