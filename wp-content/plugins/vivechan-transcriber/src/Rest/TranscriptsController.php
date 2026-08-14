<?php

namespace Vivechan\Rest;

defined('ABSPATH') || exit;

use Vivechan\Models\TranscriptRepo;
use Vivechan\Models\IntegrationRepo;
use Vivechan\Security;
use Vivechan\Services\TranscriptProcessor;
use Vivechan\Services\Publication;
use Vivechan\Services\HtmlRenderer;
use Vivechan\Helpers\Youtube;
use Vivechan\Helpers\Chapters;

/**
 * /vivechan/v1/transcripts endpoints.
 */
final class TranscriptsController {

	public static function routes() {
		return array(
			register_rest_route(
				'vivechan/v1',
				'/transcripts',
				array(
					array(
						'methods'             => \WP_REST_Server::READABLE,
						'callback'            => array( __CLASS__, 'index' ),
						'permission_callback' => array( __CLASS__, 'permission' ),
					),
					array(
						'methods'             => \WP_REST_Server::CREATABLE,
						'callback'            => array( __CLASS__, 'create' ),
						'permission_callback' => array( __CLASS__, 'permission' ),
					),
				)
			),
			register_rest_route(
				'vivechan/v1',
				'/transcripts/(?P<id>\d+)',
				array(
					array(
						'methods'             => \WP_REST_Server::READABLE,
						'callback'            => array( __CLASS__, 'show' ),
						'permission_callback' => array( __CLASS__, 'permission' ),
					),
					array(
						'methods'             => \WP_REST_Server::EDITABLE,
						'callback'            => array( __CLASS__, 'update' ),
						'permission_callback' => array( __CLASS__, 'permission' ),
					),
					array(
						'methods'             => \WP_REST_Server::DELETABLE,
						'callback'            => array( __CLASS__, 'delete' ),
						'permission_callback' => array( __CLASS__, 'permission' ),
					),
				)
			),
			register_rest_route(
				'vivechan/v1',
				'/youtube/title',
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'youtube_title' ),
					'permission_callback' => array( __CLASS__, 'permission' ),
				)
			),
			register_rest_route(
				'vivechan/v1',
				'/transcripts/(?P<id>\d+)/html',
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'html' ),
					'permission_callback' => array( __CLASS__, 'permission' ),
				)
			),
			register_rest_route(
				'vivechan/v1',
				'/transcripts/(?P<id>\d+)/publication',
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'publication' ),
					'permission_callback' => array( __CLASS__, 'permission' ),
				)
			),
			register_rest_route(
				'vivechan/v1',
				'/transcripts/(?P<id>\d+)/approve',
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'approve' ),
					'permission_callback' => array( __CLASS__, 'permission' ),
				)
			),
			register_rest_route(
				'vivechan/v1',
				'/transcripts/(?P<id>\d+)/retry',
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'retry' ),
					'permission_callback' => array( __CLASS__, 'permission' ),
				)
			),
		);
	}

	public static function permission() {
		return Security::can_transcribe();
	}

	/**
	 * GET /vivechan/v1/youtube/title?url=… — resolves a video title via oEmbed.
	 */
	public static function youtube_title( $request ) {
		$url      = trim( (string) Helpers::param( $request, 'url', '' ) );
		$video_id = Youtube::extract_video_id( $url );
		if ( ! $video_id ) {
			return Helpers::error( 'Invalid YouTube URL', 400 );
		}

		$title = Youtube::fetch_title( $video_id );
		if ( null === $title ) {
			return Helpers::error( 'Could not fetch video title', 404 );
		}

		return rest_ensure_response( array( 'title' => $title ) );
	}

	public static function index( $request ) {
		// Drive background jobs: this endpoint is polled by the app while any
		// row is PENDING, so it acts as the scheduler (no cron required).
		TranscriptProcessor::maybe_work();

		$rows   = TranscriptRepo::find_all_for_user( 0 );
		$fields = Helpers::param( $request, 'fields' );

		$out = array();
		foreach ( $rows as $row ) {
			$row = Publication::enrich( $row );
			$out[] = Helpers::project( $row, $fields );
		}
		return rest_ensure_response( $out );
	}

	public static function show( $request ) {
		$record = TranscriptRepo::find_by_id( $request['id'] );
		if ( ! Helpers::row_accessible( $record ) ) {
			return $record ? Helpers::forbidden() : Helpers::error( 'Not found', 404 );
		}
		$record = Publication::enrich( $record );
		return rest_ensure_response( Helpers::project( $record, Helpers::param( $request, 'fields' ) ) );
	}

	/**
	 * GET /transcripts/{id}/html — full styled HTML (download when ?download=1).
	 */
	public static function html( $request ) {
		$record = TranscriptRepo::find_by_id( $request['id'] );
		if ( ! Helpers::row_accessible( $record ) ) {
			return $record ? Helpers::forbidden() : Helpers::error( 'Not found', 404 );
		}
		$record = Publication::enrich( $record );

		$meta = array();
		if ( (int) $record->chapter > 0 ) {
			$meta['Chapter'] = Chapters::label( $record->chapter );
		}
		if ( ! empty( $record->level ) ) {
			$meta['Level'] = $record->level;
		}
		if ( ! empty( $record->name ) ) {
			$meta['Name'] = $record->name;
		}
		if ( ! empty( $record->created_at ) ) {
			$meta['Created'] = mysql2date( 'F j, Y', $record->created_at, false );
		}

		$html = HtmlRenderer::render( $record, $meta );

		if ( '1' === (string) Helpers::param( $request, 'download' ) ) {
			header( 'Content-Type: text/html; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="vivechan-' . (int) $record->id . '.html"' );
			echo $html;
			exit;
		}

		return new \WP_REST_Response( $html, 200, array( 'Content-Type' => 'text/html; charset=utf-8' ) );
	}

	/**
	 * POST /transcripts/{id}/publication — { status: draft|reviewed|final }.
	 */
	public static function publication( $request ) {
		$record = TranscriptRepo::find_by_id( $request['id'] );
		if ( ! Helpers::row_accessible( $record ) ) {
			return $record ? Helpers::forbidden() : Helpers::error( 'Not found', 404 );
		}
		if ( ! Helpers::can_edit( $record ) ) {
			return Helpers::forbidden();
		}
		// The whole publication lifecycle — draft, reviewed, final — is the
		// senior reviewer's. The UI hides these controls, and this makes that
		// more than a UI decision.
		if ( ! Security::can_publish() ) {
			return Helpers::error(
				'Only a Vivechan Editor or administrator can manage publication.',
				403
			);
		}

		$status = strtolower( trim( (string) Helpers::param( $request, 'status', '' ) ) );
		try {
			Publication::set_status( $record, $status );
		} catch ( \RuntimeException $e ) {
			return Helpers::error( $e->getMessage(), 409 );
		}

		return rest_ensure_response( Publication::enrich( TranscriptRepo::find_by_id( $record->id ) ) );
	}

	public static function create( $request ) {
		$url = trim( (string) Helpers::param( $request, 'url', '' ) );
		if ( '' === $url ) {
			return Helpers::error( 'YouTube URL is required' );
		}

		if ( Security::is_rate_limited( 'transcribe' ) ) {
			return Helpers::error( 'Too many requests. Please wait a few minutes.', 429 );
		}
		if ( Security::active_limit_reached() ) {
			return Helpers::error( 'Too many transcripts are being processed. Please wait for one to finish.', 429 );
		}

		try {
			$record = TranscriptProcessor::start_transcript(
				$url,
				Helpers::param( $request, 'integration_id', null ),
				Helpers::param( $request, 'system_prompt_id', null ),
				Helpers::param( $request, 'name', null ),
				Helpers::param( $request, 'model', null )
			);
		} catch ( \RuntimeException $e ) {
			return Helpers::error( $e->getMessage() );
		}

		return rest_ensure_response(
			array_merge(
				array( 'status' => 202 ),
				(array) $record
			)
		);
	}

	public static function update( $request ) {
		$record = TranscriptRepo::find_by_id( $request['id'] );
		if ( ! Helpers::row_accessible( $record ) ) {
			return $record ? Helpers::forbidden() : Helpers::error( 'Not found', 404 );
		}
		// Access was already established above; anyone a transcript is shared
		// with is expected to work on it, so there is no second edit gate.
		$name = Helpers::param( $request, 'name', null );
		if ( null !== $name ) {
			TranscriptRepo::update_name( $record->id, sanitize_text_field( $name ) );
		}

		$chapter = Helpers::param( $request, 'chapter', null );
		if ( null !== $chapter ) {
			// Classification, not publication: whoever is working on the
			// transcript knows which chapter it covers, and picks it when
			// creating it or from the list. Publishing it stays senior.
			//
			// Once live it is fixed, though — changing it would move the page
			// between category archives and index sections underneath readers
			// who already have the link.
			if ( Publication::is_published( $record ) ) {
				return Helpers::error(
					'This Vivechan is published. Unpublish it before changing its chapter.',
					409
				);
			}

			$chapter = (int) $chapter;
			if ( $chapter && ( $chapter < 1 || $chapter > 18 ) ) {
				return Helpers::error( 'chapter must be between 1 and 18' );
			}
			TranscriptRepo::set_chapter( $record->id, $chapter ?: null );

			// The public index queries the POST's chapter meta, not the
			// transcript row, and nothing else refreshes it — so a chapter set
			// here never reached the post and the published page filed itself
			// nowhere.
			$fresh = TranscriptRepo::find_by_id( $record->id );
			if ( $fresh && (int) $fresh->post_id ) {
				Publication::sync_post( (int) $fresh->post_id, $fresh );
			}
		}

		$integration_id = Helpers::param( $request, 'integration_id', null );
		if ( null !== $integration_id ) {
			if ( 'PENDING' === $record->status || TranscriptProcessor::is_locked( $record->id ) ) {
				return Helpers::error( 'Cannot change integration while transcript is processing.' );
			}
			TranscriptRepo::update_integration( $record->id, (int) $integration_id );
		}

		$raw = Helpers::param( $request, 'raw_transcript', null );
		if ( null !== $raw && 'REVIEW' === $record->status ) {
			$integration = IntegrationRepo::find_raw( $record->integration_id );
			$chunk_size  = $integration ? (int) ( $integration->chunk_size ?: 800 ) : 800;
			TranscriptRepo::update_raw( $record->id, (string) $raw, $chunk_size );
		}

		$content = Helpers::param( $request, 'content', null );
		if ( null !== $content ) {
			// Correcting the text is ordinary work: anyone the transcript
			// belongs to, or was shared with, may do it. What stays senior is
			// pushing it into WordPress — see publication() below.
			//
			// The editor sends HTML so formatting survives, so this is the
			// point where it has to be sanitised. wp_kses_post allows the
			// markup a post may contain and strips scripts and handlers.
			TranscriptRepo::update_content( $record->id, wp_kses_post( (string) $content ) );

			// Keep the published page in step. Without this an edit here was
			// invisible on the site, since the post keeps its own copy of the
			// rendered content.
			$fresh = TranscriptRepo::find_by_id( $record->id );
			if ( $fresh && (int) $fresh->post_id ) {
				Publication::sync_post( (int) $fresh->post_id, $fresh );
			}
		}

		return rest_ensure_response( array( 'ok' => true ) );
	}

	public static function approve( $request ) {
		$record = TranscriptRepo::find_by_id( $request['id'] );
		if ( ! Helpers::row_accessible( $record ) ) {
			return $record ? Helpers::forbidden() : Helpers::error( 'Not found', 404 );
		}
		if ( ! Helpers::can_edit( $record ) ) {
			return Helpers::forbidden();
		}
		// Sending a transcript to the AI is a senior decision: it spends API
		// quota and produces the text that gets published. Vivechaks review and
		// edit; a Vivechan Editor or administrator approves.
		if ( ! Security::can_publish() ) {
			return Helpers::error(
				'Only a Vivechan Editor or administrator can approve a transcript for processing.',
				403
			);
		}

		try {
			TranscriptProcessor::approve( $record->id, Helpers::param( $request, 'raw_transcript', null ) );
		} catch ( \RuntimeException $e ) {
			return Helpers::error( $e->getMessage() );
		}


		return rest_ensure_response( array( 'status' => 'PENDING' ) );
	}

	public static function retry( $request ) {
		$record = TranscriptRepo::find_by_id( $request['id'] );
		if ( ! Helpers::row_accessible( $record ) ) {
			return $record ? Helpers::forbidden() : Helpers::error( 'Not found', 404 );
		}
		if ( ! Helpers::can_edit( $record ) ) {
			return Helpers::forbidden();
		}

		try {
			TranscriptProcessor::retry( $record->id, Helpers::param( $request, 'model', null ) );
		} catch ( \RuntimeException $e ) {
			return Helpers::error( $e->getMessage(), 409 );
		}

		return rest_ensure_response( array( 'status' => 'PENDING' ) );
	}

	public static function delete( $request ) {
		$record = TranscriptRepo::find_by_id( $request['id'] );
		if ( ! Helpers::row_accessible( $record ) ) {
			return $record ? Helpers::forbidden() : Helpers::error( 'Not found', 404 );
		}
		// Being shared a transcript lets you work on it, not destroy it.
		if ( ! Helpers::owns( $record ) ) {
			return Helpers::error( 'Only the person who created this transcript can delete it.', 403 );
		}

		TranscriptRepo::delete( $record->id );
		TranscriptProcessor::unlock( $record->id );

		return rest_ensure_response( array( 'deleted' => true ) );
	}
}
