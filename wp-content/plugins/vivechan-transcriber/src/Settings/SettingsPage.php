<?php

namespace Vivechan\Settings;

defined('ABSPATH') || exit;

use Vivechan\Security;

/**
 * Plugin options (rate limits, YouTube Data API key) and the admin settings page.
 */
final class SettingsPage {

	const OPTION = 'vivechan_settings';

	const DEFAULTS = array(
		'youtube_api_key_enc'   => '',
		'max_active_per_user'   => 5,
		'max_active_global'     => 20,
		'rate_limit_transcribe' => 15,
	);

	/**
	 * No menu registration here — Admin\AdminApp owns the Vivechan menu and
	 * mounts this render() as its "Settings" submenu, so the transcriber itself
	 * stays the top-level screen.
	 */

	public static function get( $key, $default = null ) {
		$options = wp_parse_args( (array) get_option( self::OPTION, array() ), self::DEFAULTS );
		if ( isset( $options[ $key ] ) && '' !== $options[ $key ] ) {
			return $options[ $key ];
		}
		return ( null !== $default ) ? $default : self::DEFAULTS[ $key ];
	}

	public static function youtube_api_key() {
		return Security::decrypt( (string) self::get( 'youtube_api_key_enc', '' ) );
	}

	public static function max_active_per_user() {
		return max( 1, (int) self::get( 'max_active_per_user', 5 ) );
	}

	public static function max_active_global() {
		return max( 1, (int) self::get( 'max_active_global', 20 ) );
	}

	public static function rate_limit( $action ) {
		$key = 'rate_limit_' . sanitize_key( $action );
		$default = isset( self::DEFAULTS[ $key ] ) ? self::DEFAULTS[ $key ] : 15;
		return max( 1, (int) self::get( $key, $default ) );
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Access denied.' );
		}

		if ( isset( $_POST['vivechan_save'] ) ) {
			check_admin_referer( 'vivechan_settings' );

			$new_key = isset( $_POST['youtube_api_key'] ) ? trim( wp_unslash( $_POST['youtube_api_key'] ) ) : '';

			$options = get_option( self::OPTION, array() );
			if ( '' !== $new_key ) {
				$options['youtube_api_key_enc'] = Security::encrypt( $new_key );
			}

			$options['max_active_per_user']   = isset( $_POST['max_active_per_user'] ) ? max( 1, (int) $_POST['max_active_per_user'] ) : 5;
			$options['max_active_global']     = isset( $_POST['max_active_global'] ) ? max( 1, (int) $_POST['max_active_global'] ) : 20;
			$options['rate_limit_transcribe'] = isset( $_POST['rate_limit_transcribe'] ) ? max( 1, (int) $_POST['rate_limit_transcribe'] ) : 15;

			update_option( self::OPTION, $options );

			echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
		}

		$max_per_user   = self::max_active_per_user();
		$max_global     = self::max_active_global();
		$rate_transcribe = self::rate_limit( 'transcribe' );
		$cron_ok        = self::cron_status();
		?>
		<div class="wrap">
			<h1>Vivechan Transcriber</h1>

			<h2>Background jobs</h2>
			<p>
				Transcripts are processed by <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">WP-Cron</a>.
				Recommended: disable WP-Cron on page loads and run it with a real cron job every minute
				(see the plugin README). Status:
				<strong><?php echo defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? 'Real cron enabled (DISABLE_WP_CRON is set)' : 'WP-Cron (fires on site traffic)'; ?></strong>.
				Watchdog recurrence: <strong><?php echo esc_html( $cron_ok ? 'scheduled' : 'NOT scheduled' ); ?></strong>.
			</p>

			<h2>Settings</h2>
			<form method="post">
				<?php wp_nonce_field( 'vivechan_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="youtube_api_key">YouTube Data API key (optional)</label></th>
						<td>
							<input id="youtube_api_key" name="youtube_api_key" type="password" class="regular-text" autocomplete="off" placeholder="AIza… (leave blank to keep the current key)" />
							<p class="description">Used to discover caption languages (fallback chain: YouTube timedtext &rarr; yt-to-text.com &rarr; Data API). Stored encrypted.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="max_active_per_user">Max in-flight transcripts per user</label></th>
						<td><input id="max_active_per_user" name="max_active_per_user" type="number" min="1" value="<?php echo esc_attr( $max_per_user ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="max_active_global">Max in-flight transcripts site-wide</label></th>
						<td><input id="max_active_global" name="max_active_global" type="number" min="1" value="<?php echo esc_attr( $max_global ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="rate_limit_transcribe">Max new transcript requests per 5 minutes, per user</label></th>
						<td><input id="rate_limit_transcribe" name="rate_limit_transcribe" type="number" min="1" value="<?php echo esc_attr( $rate_transcribe ); ?>" /></td>
					</tr>
				</table>
				<?php submit_button( 'Save Settings', 'primary', 'vivechan_save' ); ?>
			</form>
		</div>
		<?php
	}

	private static function cron_status() {
		return (bool) wp_next_scheduled( 'vivechan_watchdog' );
	}
}
