<?php

namespace Vivechan;

defined('ABSPATH') || exit;

/**
 * Security helpers: capabilities, API-key encryption, rate limiting.
 */
final class Security {

	const CAP_TRANSCRIBE = 'vivechan_transcribe';
	const CAP_PUBLISH    = 'vivechan_publish';
	const CAP_MANAGE     = 'vivechan_manage';

	public static function can_transcribe() {
		return is_user_logged_in() && current_user_can( self::CAP_TRANSCRIBE );
	}

	/**
	 * Senior review: may edit the finished text and publish it publicly,
	 * without the site-wide powers of an administrator.
	 *
	 * Administrators pass implicitly — they hold CAP_MANAGE, and a site owner
	 * should never be locked out of publishing by a missing capability.
	 */
	public static function can_publish() {
		return is_user_logged_in()
			&& ( current_user_can( self::CAP_PUBLISH ) || current_user_can( self::CAP_MANAGE ) );
	}

	public static function can_manage() {
		return is_user_logged_in() && current_user_can( self::CAP_MANAGE );
	}

	/**
	 * Mask a key for display: "****abcd".
	 */
	public static function mask_key( $key ) {
		if ( ! is_string( $key ) || strlen( $key ) < 8 ) {
			return '****';
		}
		return '****' . substr( $key, -4 );
	}

	/**
	 * Encrypt a value with AES-256-GCM using a key derived from the WP auth salt.
	 * Returns base64(iv + tag + ciphertext).
	 */
	public static function encrypt( $plain ) {
		if ( ! is_string( $plain ) || '' === $plain ) {
			return '';
		}
		$key  = self::key();
		$iv   = random_bytes( 12 );
		$tag  = '';
		$cipher = openssl_encrypt( $plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16 );
		if ( false === $cipher ) {
			return '';
		}
		return base64_encode( $iv . $tag . $cipher );
	}

	public static function decrypt( $encoded ) {
		if ( ! is_string( $encoded ) || '' === $encoded ) {
			return '';
		}
		$raw = base64_decode( $encoded, true );
		if ( false === $raw || strlen( $raw ) < 28 ) {
			return '';
		}
		$iv     = substr( $raw, 0, 12 );
		$tag    = substr( $raw, 12, 16 );
		$cipher = substr( $raw, 28 );
		$plain  = openssl_decrypt( $cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag );
		return false === $plain ? '' : $plain;
	}

	/**
	 * Simple per-user rate limiter. Returns true when the caller is rate-limited.
	 */
	public static function is_rate_limited( $action ) {
		if ( ! is_user_logged_in() ) {
			return true;
		}
		$transient = 'vivechan_rl_' . sanitize_key( $action ) . '_' . get_current_user_id();
		$count     = (int) get_transient( $transient );

		if ( $count >= Settings\SettingsPage::rate_limit( $action ) ) {
			return true;
		}

		set_transient( $transient, $count + 1, 5 * MINUTE_IN_SECONDS );
		return false;
	}

	/**
	 * Abort guard: a user can only have a small number of in-flight transcripts,
	 * and the site only processes a limited number concurrently.
	 */
	public static function active_limit_reached() {
		global $wpdb;

		if ( ! is_user_logged_in() ) {
			return true;
		}

		$table = Models\TranscriptRepo::table();
		$user  = get_current_user_id();

		$user_active = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = %s AND created_by = %d",
				'PENDING',
				$user
			)
		);
		if ( $user_active >= Settings\SettingsPage::max_active_per_user() ) {
			return true;
		}

		$global_active = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'PENDING' )
		);
		return $global_active >= Settings\SettingsPage::max_active_global();
	}

	private static function key() {
		return hash( 'sha256', (string) wp_salt( 'auth' ), true );
	}
}
