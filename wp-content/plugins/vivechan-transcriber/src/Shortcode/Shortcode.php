<?php

namespace Vivechan\Shortcode;

defined('ABSPATH') || exit;

use Vivechan\Admin\AdminApp;
use Vivechan\Security;

/**
 * [vivechan_transcriber] — retained for pages that still contain the shortcode.
 *
 * The transcriber moved into wp-admin (Vivechan → Transcriber): it is a
 * back-office tool, and its working data has no business living on a public
 * page. Rather than rendering nothing on sites where the shortcode is still
 * embedded, this points people at the admin screen.
 */
final class Shortcode {

	public static function register() {
		add_shortcode( 'vivechan_transcriber', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts = array() ) {
		wp_enqueue_style( 'vivechan-gate', VIVECHAN_URL . 'assets/css/vivechan.css', array(), VIVECHAN_VERSION );

		if ( ! is_user_logged_in() ) {
			$redirect = ( is_ssl() ? 'https://' : 'http://' )
				. ( isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '' )
				. ( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/' );

			return self::gate(
				sprintf(
					'<p>This tool is for Vivechak users only.</p><p><a class="vivechan-login-btn" href="%s">Log in to continue</a></p>',
					esc_url( wp_login_url( $redirect ) )
				)
			);
		}

		if ( ! Security::can_transcribe() ) {
			return self::gate( '<p>Your account does not have access to the Vivechan Transcriber.</p>' );
		}

		return self::gate(
			sprintf(
				'<p>The Vivechan Transcriber now runs inside the WordPress admin.</p><p><a class="vivechan-login-btn" href="%s">Open the Transcriber &rarr;</a></p>',
				esc_url( admin_url( 'admin.php?page=' . AdminApp::SLUG ) )
			)
		);
	}

	private static function gate( $inner ) {
		return '<div class="vivechan-gate"><div class="vivechan-gate-box">' . $inner . '</div></div>';
	}
}
