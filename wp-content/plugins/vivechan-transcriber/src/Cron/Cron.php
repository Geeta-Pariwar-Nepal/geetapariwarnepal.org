<?php

namespace Vivechan\Cron;

defined('ABSPATH') || exit;

use Vivechan\Services\TranscriptProcessor;

/**
 * WP-Cron event registration and scheduling.
 *
 * Jobs are deliberately tiny: one HTTP fetch, or one AI chunk, per event.
 * Each chunk event schedules the next one, so processing resumes across
 * requests even on shared hosting.
 */
final class Cron {

	const FETCH   = 'vivechan_fetch';
	const CHUNK   = 'vivechan_chunk';
	const WATCHDOG = 'vivechan_watchdog';

	const INTERVAL = 'vivechan_5min';

	public static function register() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_interval' ) );

		add_action( self::FETCH, array( TranscriptProcessor::class, 'handle_fetch' ), 10, 1 );
		add_action( self::CHUNK, array( TranscriptProcessor::class, 'handle_chunk' ), 10, 2 );
		add_action( self::WATCHDOG, array( TranscriptProcessor::class, 'handle_watchdog' ), 10, 0 );
	}

	public static function add_interval( $schedules ) {
		$schedules[ self::INTERVAL ] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => 'Every 5 minutes',
		);
		return $schedules;
	}

	/**
	 * Schedule a job for immediate execution and kick wp-cron.php right away.
	 *
	 * With DISABLE_WP_CRON defined (recommended on Hostinger) the automatic
	 * loopback spawn is disabled, so without this a new transcript would sit
	 * at "processing / fetching…" until the real cron fires (up to a minute).
	 * Firing wp-cron.php here starts the job in the background within the same
	 * request, while the real cron remains as a safety net.
	 */
	public static function schedule_fetch( $id ) {
		wp_schedule_single_event( time() + 1, self::FETCH, array( (int) $id ), true );
		self::spawn_now();
	}

	public static function schedule_chunk( $id, $index ) {
		wp_schedule_single_event( time() + 1, self::CHUNK, array( (int) $id, (int) $index ), true );
		self::spawn_now();
	}

	/**
	 * Fire wp-cron.php as a non-blocking loopback request. wp-cron.php accepts
	 * direct calls and finishes the HTTP request early, so the caller isn't
	 * held up while the job actually runs.
	 */
	public static function spawn_now() {
		$url  = site_url( 'wp-cron.php' );
		$args = array(
			'timeout'     => 0.01,
			'blocking'    => false,
			'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
			'redirection' => 0,
		);
		wp_remote_post( $url, $args );
	}

	public static function schedule_recurring() {
		if ( ! wp_next_scheduled( self::WATCHDOG ) ) {
			wp_schedule_event( time() + self::INTERVAL_FIRST, self::INTERVAL, self::WATCHDOG );
		}
	}

	public static function clear_recurring() {
		$next = wp_next_scheduled( self::WATCHDOG );
		if ( $next ) {
			wp_unschedule_event( $next, self::WATCHDOG );
		}
	}

	const INTERVAL_FIRST = 300;
}
