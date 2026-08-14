<?php

namespace Vivechan\Services;

defined('ABSPATH') || exit;

use Vivechan\Activator;
use Vivechan\Cron\Cron;
use Vivechan\Security;
use Vivechan\Helpers\Youtube;
use Vivechan\Helpers\Chapters;
use Vivechan\Models\IntegrationRepo;
use Vivechan\Models\PromptRepo;
use Vivechan\Models\TranscriptRepo;
use Vivechan\Settings\SettingsPage;
use Vivechan\Services\Publication;

/**
 * Orchestrates transcript jobs.
 *
 * Locking model:
 *  - A transient "lock" per transcript marks the job as in-flight (used by the
 *    watchdog to detect lost jobs). It is refreshed on every cron fire.
 *  - A MySQL advisory lock per transcript prevents two cron workers from
 *    processing the same chunk concurrently.
 */
final class TranscriptProcessor {

	const LOCK_TTL = 10 * MINUTE_IN_SECONDS;

	// ---------------------------------------------------------------------
	// HTTP-facing operations (called from REST controllers).
	// ---------------------------------------------------------------------

	public static function start_transcript( $url, $integration_id, $system_prompt_id, $name, $model, $chapter = null ) {
		$video_id = Youtube::extract_video_id( $url );
		if ( ! $video_id ) {
			throw new \RuntimeException( 'Could not parse YouTube video ID from URL.' );
		}

		$integration = $integration_id ? IntegrationRepo::find_raw( $integration_id ) : IntegrationRepo::find_first_raw();
		if ( ! $integration ) {
			throw new \RuntimeException( 'No AI integration configured.' );
		}
		// find_raw() is unscoped by design (the cron uses it), so an explicitly
		// requested integration has to be checked here — otherwise any id could
		// be posted and someone else's API quota spent.
		if ( $integration_id && ! IntegrationRepo::can_use( $integration ) ) {
			throw new \RuntimeException( 'That AI integration is not available to you.' );
		}

		$prompt = $system_prompt_id ? PromptRepo::find_by_id( $system_prompt_id ) : null;
		if ( $prompt && $system_prompt_id && ! PromptRepo::can_access( $prompt ) ) {
			throw new \RuntimeException( 'That system prompt is not available to you.' );
		}
		if ( ! $prompt ) {
			$first  = PromptRepo::first();
			$prompt = $first ? PromptRepo::find_by_id( $first->id ) : null;
		}
		if ( ! $prompt ) {
			throw new \RuntimeException( 'No system prompt available. Please create one first.' );
		}

		$config        = IntegrationRepo::resolve_config( $integration );
		$resolved_model = $model ?: $config['model'];
		$filename      = $video_id . '_' . time() . '.txt';

		$record = TranscriptRepo::create_pending(
			array(
				'video_id'         => $video_id,
				'filename'         => $filename,
				'name'             => $name,
				'model'            => $resolved_model,
				'used_chunk_size'  => $config['chunk_size'],
				'integration_id'   => $integration->id,
				'system_prompt_id' => $prompt->id,
				'chapter'          => $chapter,
				'created_by'       => get_current_user_id(),
			)
		);

		self::lock( $record->id );
		Cron::schedule_fetch( $record->id );

		return $record;
	}

	public static function approve( $id, $edited_raw ) {
		$record = TranscriptRepo::find_by_id( $id );
		if ( ! $record ) {
			throw new \RuntimeException( 'Transcript not found.' );
		}
		if ( 'REVIEW' !== $record->status ) {
			throw new \RuntimeException( 'Transcript is not awaiting review.' );
		}
		if ( self::is_locked( $id ) ) {
			throw new \RuntimeException( 'Transcript is already being processed.' );
		}

		$integration = IntegrationRepo::find_raw( $record->integration_id );
		if ( ! $integration ) {
			throw new \RuntimeException( 'No AI integration configured.' );
		}
		$config = IntegrationRepo::resolve_config( $integration );

		if ( is_string( $edited_raw ) && '' !== trim( $edited_raw ) && $edited_raw !== $record->raw_transcript ) {
			TranscriptRepo::update_raw( $id, $edited_raw, $config['chunk_size'] );
		}

		TranscriptRepo::reset_for_retry( $id );
		self::lock( $id );
		Cron::schedule_chunk( $id, 0 );
	}

	public static function retry( $id, $model ) {
		$record = TranscriptRepo::find_by_id( $id );
		if ( ! $record ) {
			throw new \RuntimeException( 'Transcript not found.' );
		}
		if ( 'COMPLETED' === $record->status ) {
			throw new \RuntimeException( 'Transcript is already completed.' );
		}
		if ( self::is_locked( $id ) ) {
			throw new \RuntimeException( 'This transcript is already being processed.' );
		}

		$integration = IntegrationRepo::find_raw( $record->integration_id );
		if ( ! $integration ) {
			throw new \RuntimeException( 'No AI integration configured.' );
		}
		$config = IntegrationRepo::resolve_config( $integration );

		$resolved_model = $model ?: ( $record->model ?: $config['model'] );

		$saved_chunks = json_decode( (string) $record->processed_chunks, true );
		if ( ! is_array( $saved_chunks ) ) {
			$saved_chunks = array();
		}

		// If the chunk size changed, recalculate the resume point by words processed.
		$start_index = count( $saved_chunks );
		$old_size    = (int) $record->used_chunk_size;
		$new_size    = $config['chunk_size'];
		if ( $old_size && $new_size && $old_size !== $new_size && count( $saved_chunks ) > 0 ) {
			$start_index = (int) floor( ( count( $saved_chunks ) * $old_size ) / $new_size );
		}

		if ( $model ) {
			TranscriptRepo::update_model( $id, $resolved_model );
		}

		TranscriptRepo::reset_for_retry( $id );
		self::lock( $id );
		Cron::schedule_chunk( $id, $start_index );
	}

	// ---------------------------------------------------------------------
	// Cron handlers.
	// ---------------------------------------------------------------------

	public static function handle_fetch( $id ) {
		$id     = (int) $id;
		$record = TranscriptRepo::find_by_id( $id );

		if ( ! $record || 'PENDING' !== $record->status || ! empty( $record->raw_transcript ) ) {
			self::unlock( $id );
			return;
		}

		if ( 1 !== self::acquire_advisory( $id ) ) {
			return;
		}

		try {
			self::bump_time_limit();
			self::refresh_lock( $id );

			$raw       = SubtitleFetcher::fetch( $record->video_id, SettingsPage::youtube_api_key() );
			$chunk_size = $record->used_chunk_size ?: 800;
			$total      = count( Youtube::chunk_text( $raw, $chunk_size ) );

			TranscriptRepo::save_raw( $id, $raw, $total, $chunk_size );

			$title = Youtube::fetch_title( $record->video_id );
			if ( $title ) {
				TranscriptRepo::save_title( $id, $title );
			}

			TranscriptRepo::set_status( $id, 'REVIEW' );
			self::unlock( $id );
		} catch ( \Throwable $e ) {
			TranscriptRepo::mark_error( $id, $e->getMessage() );
			self::unlock( $id );
		} finally {
			self::release_advisory( $id );
		}
	}

	public static function handle_chunk( $id, $index ) {
		$id     = (int) $id;
		$index  = (int) $index;
		$record = TranscriptRepo::find_by_id( $id );

		if ( ! $record || 'PENDING' !== $record->status ) {
			self::unlock( $id );
			return;
		}

		if ( 1 !== self::acquire_advisory( $id ) ) {
			return;
		}

		try {
			self::bump_time_limit();
			self::refresh_lock( $id );

			$integration = IntegrationRepo::find_raw( $record->integration_id );
			if ( ! $integration ) {
				throw new \RuntimeException( 'AI integration is missing.' );
			}
			$prompt = $record->system_prompt_id ? PromptRepo::find_by_id( $record->system_prompt_id ) : null;
			$system = $prompt ? $prompt->content : '';

			$config  = IntegrationRepo::resolve_config( $integration );
			$model   = $record->model ?: $config['model'];
			$chunks  = Youtube::chunk_text( (string) $record->raw_transcript, $config['chunk_size'] );
			$total   = count( $chunks );
			$saved   = json_decode( (string) $record->processed_chunks, true );
			if ( ! is_array( $saved ) ) {
				$saved = array();
			}

			if ( $index >= $total ) {
				self::finish( $id, $saved, $system );
				self::unlock( $id );
				return;
			}

			$text = AiService::generate_with_retry( $config['type'], $config, $model, $system, $chunks[ $index ] );
			$saved[] = $text;
			TranscriptRepo::save_chunk( $id, $text, mb_strlen( $chunks[ $index ], 'UTF-8' ) );

			$next = $index + 1;
			if ( $next < $total ) {
				Cron::schedule_chunk( $id, $next );
			} else {
				self::finish( $id, $saved, $system );
				self::unlock( $id );
			}
		} catch ( \Throwable $e ) {
			TranscriptRepo::mark_error( $id, 'Chunk ' . ( $index + 1 ) . ': ' . $e->getMessage() );
			self::unlock( $id );
		} finally {
			self::release_advisory( $id );
		}
	}

	public static function handle_watchdog() {
		global $wpdb;

		$table = TranscriptRepo::table();
		$rows  = $wpdb->get_results( "SELECT id FROM {$table} WHERE status = 'PENDING'" );

		foreach ( $rows as $row ) {
			if ( ! self::is_locked( (int) $row->id ) ) {
				TranscriptRepo::mark_error( (int) $row->id, 'Process was lost. Please retry.' );
			}
		}
	}

	/**
	 * Self-driven worker: advances one pending transcript by one step.
	 *
	 * Called on every transcripts list request (the React app polls every ~3s
	 * while rows are PENDING). This makes processing work on shared hosts even
	 * when no real cron is configured and loopback requests to wp-cron.php are
	 * blocked — the app itself becomes the scheduler.
	 *
	 * Each call does bounded work: the subtitle fetch, or a single AI chunk.
	 * A short site-wide mutex prevents overlapping steps.
	 */
	public static function maybe_work() {
		if ( ! Security::can_transcribe() ) {
			return;
		}
		if ( get_transient( 'vivechan_worker' ) ) {
			return;
		}
		set_transient( 'vivechan_worker', 1, 25 );

		global $wpdb;
		$table = TranscriptRepo::table();
		$id    = (int) $wpdb->get_var(
			"SELECT id FROM {$table} WHERE status = 'PENDING' ORDER BY created_at ASC LIMIT 1"
		);
		if ( ! $id ) {
			return;
		}

		$record = TranscriptRepo::find_by_id( $id );
		if ( ! $record ) {
			return;
		}

		// handle_fetch / handle_chunk acquire the MySQL advisory lock internally,
		// which is the real guard against two workers processing the same chunk.
		// We intentionally do NOT skip transient-locked rows: in the chunk chain
		// the transient stays locked between steps, so skipping would stall it.

		self::bump_time_limit();

		if ( empty( $record->raw_transcript ) ) {
			self::handle_fetch( $id );
			return;
		}

		$saved = json_decode( (string) $record->processed_chunks, true );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		self::handle_chunk( $id, count( $saved ) );
	}

	// ---------------------------------------------------------------------
	// Locking helpers.
	// ---------------------------------------------------------------------

	public static function lock_key( $id ) {
		return 'vivechan_lock_' . (int) $id;
	}

	public static function lock( $id ) {
		set_transient( self::lock_key( $id ), 1, self::LOCK_TTL );
	}

	public static function refresh_lock( $id ) {
		set_transient( self::lock_key( $id ), 1, self::LOCK_TTL );
	}

	public static function is_locked( $id ) {
		return (bool) get_transient( self::lock_key( $id ) );
	}

	public static function unlock( $id ) {
		delete_transient( self::lock_key( $id ) );
	}

	private static function acquire_advisory( $id ) {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', 'vivechan_' . (int) $id )
		);
	}

	private static function release_advisory( $id ) {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', 'vivechan_' . (int) $id )
		);
	}

	private static function finish( $id, $saved_chunks, $prompt_used ) {
		TranscriptRepo::mark_completed( $id, implode( "\n\n", $saved_chunks ), $prompt_used );

		$record = TranscriptRepo::find_by_id( $id );
		if ( ! $record ) {
			return;
		}

		// Auto-detect the Gita chapter from the video title when possible.
		if ( ! $record->chapter ) {
			$detected = Chapters::detect( $record->title ?: ( $record->name ?: '' ) );
			if ( $detected ) {
				TranscriptRepo::set_chapter( $id, $detected );
				$record->chapter = $detected;
			}
		}

		// Create the editable draft blog post (Vivechaks edit in the editor).
		Publication::ensure_draft( $record );
	}

	private static function bump_time_limit() {
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 240 );
		}
	}
}
