<?php

namespace Vivechan\Admin;

defined('ABSPATH') || exit;

use Vivechan\AppAssets;
use Vivechan\Security;

/**
 * The transcriber lives in wp-admin at admin.php?page=vivechan-transcriber.
 *
 * Transcribe / Transcripts / System Prompts / AI Integrations are routes inside
 * the React app (HashRouter), so they appear as tabs on this one screen rather
 * than as separate WordPress pages. Working data never touches posts or pages.
 */
final class AdminApp {

	const SLUG = 'vivechan-transcriber';

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function add_menu() {
		// Gated on the transcribe capability, not manage_options, so Vivechaks
		// get the menu too — they are the people who actually use this.
		add_menu_page(
			'Vivechan Transcriber',
			'Vivechan',
			Security::CAP_TRANSCRIBE,
			self::SLUG,
			array( __CLASS__, 'render' ),
			'dashicons-video-alt2',
			80
		);

		// Re-registering the parent as its own first submenu stops WordPress
		// from labelling the first child "Vivechan" a second time.
		add_submenu_page(
			self::SLUG,
			'Vivechan Transcriber',
			'Transcriber',
			Security::CAP_TRANSCRIBE,
			self::SLUG,
			array( __CLASS__, 'render' )
		);

		// No Settings screen: the transcriber does not need one. SettingsPage
		// still supplies the values (rate limits, concurrency caps) from its
		// defaults — it just has no UI. Restoring it is one add_submenu_page().
	}

	/**
	 * Styles load only on the app screen — the bundle's CSS is a whole design
	 * system and would wreck the rest of wp-admin.
	 */
	public static function enqueue( $hook ) {
		if ( 'toplevel_page_' . self::SLUG !== $hook ) {
			return;
		}

		// Gives the app window.wp.media, so picking cover art reuses the
		// WordPress media library — upload and reuse both come for free.
		wp_enqueue_media();

		$style = AppAssets::style_url();
		if ( '' !== $style ) {
			wp_enqueue_style( 'vivechan-app', $style, array(), VIVECHAN_VERSION );
		}

		wp_enqueue_style(
			'vivechan-admin',
			VIVECHAN_URL . 'assets/css/vivechan-admin.css',
			array( 'vivechan-app' ),
			VIVECHAN_VERSION
		);
	}

	public static function render() {
		if ( ! Security::can_transcribe() ) {
			wp_die( 'You do not have permission to use the Vivechan Transcriber.' );
		}

		// wp-admin screens are expected to carry an h1. The app's own top bar is
		// just the tab strip now, so this supplies the heading for screen
		// readers without adding visible chrome.
		echo '<h1 class="screen-reader-text">Vivechan Transcriber</h1>';

		// The app renders its own shell, so this wrapper only strips the admin
		// gutter. Output is built from wp_json_encode and esc_url internally.
		echo '<div class="vivechan-admin-wrap">' . AppAssets::boot_html() . '</div>'; // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped
	}
}
