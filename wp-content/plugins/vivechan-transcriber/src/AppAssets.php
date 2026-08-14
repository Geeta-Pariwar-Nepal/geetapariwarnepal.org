<?php

namespace Vivechan;

defined('ABSPATH') || exit;

/**
 * Locates and emits the compiled React bundle.
 *
 * Filenames are read out of assets/app/index.html, which Vite rewrites on every
 * build, so nothing here hardcodes a hashed path. Shared by the admin screen
 * and the (now redirecting) shortcode.
 */
final class AppAssets {

	private static function index_html() {
		$build = VIVECHAN_PATH . 'assets/app/index.html';
		return is_file( $build ) ? (string) file_get_contents( $build ) : '';
	}

	public static function style_url() {
		$html = self::index_html();
		if ( '' !== $html && preg_match( '#<link[^>]+rel=[\'"]stylesheet[\'"][^>]+href=[\'"]([^\'"]+)[\'"]#i', $html, $m ) ) {
			return self::url( $m[1] );
		}
		return '';
	}

	public static function script_url() {
		$html = self::index_html();
		if ( '' !== $html && preg_match( '#<script[^>]+src=[\'"]([^\'"]+)[\'"]#i', $html, $m ) ) {
			return self::url( $m[1] );
		}
		return '';
	}

	/**
	 * Vite emits an ES module and wp_enqueue_script would drop type="module",
	 * so the tag is written directly.
	 *
	 * The ?ver= matters more than it looks: the bundle is always named
	 * static/js/main.js with no content hash, so without this a browser keeps
	 * serving the previous release's JavaScript against the new PHP.
	 */
	public static function script_tag() {
		$src = self::script_url();
		if ( '' === $src ) {
			return '';
		}
		return '<script type="module" src="' . esc_url( add_query_arg( 'ver', VIVECHAN_VERSION, $src ) ) . '"></script>' . "\n";
	}

	public static function config() {
		return array(
			'restBase'  => rest_url( Rest\RestApi::NAMESPACE ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'loginUrl'  => wp_login_url(),
			'userId'    => get_current_user_id(),
			'canPublish' => Security::can_publish(),
			'canManage'  => Security::can_manage(),
			'adminUrl'  => admin_url(),
		);
	}

	/**
	 * Mount point + runtime config + bundle tag.
	 */
	public static function boot_html() {
		return '<div id="vivechan-app" class="vivechan-root"></div>'
			. '<script>window.VIVECHAN_APP = ' . wp_json_encode( self::config() ) . ';</script>'
			. self::script_tag();
	}

	private static function url( $ref ) {
		$ref = trim( $ref );
		if ( 0 === strpos( $ref, 'http' ) || 0 === strpos( $ref, '//' ) ) {
			return $ref;
		}
		$ref = preg_replace( '#^\.?/#', '', $ref );
		return VIVECHAN_URL . 'assets/app/' . $ref;
	}
}
