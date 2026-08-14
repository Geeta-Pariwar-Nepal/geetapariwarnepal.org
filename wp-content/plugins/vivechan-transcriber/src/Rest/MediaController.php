<?php

namespace Vivechan\Rest;

defined('ABSPATH') || exit;

use Vivechan\Models\ChapterImages;
use Vivechan\Models\TranscriptRepo;
use Vivechan\Security;

/**
 * Cover art for the public index.
 *
 * Two levels: a featured image on an individual Vivechan, and a cover image
 * per Gita chapter used for the chapter heading and as the fallback.
 *
 * Both are publication decisions — they only ever appear on the public site —
 * so both require vivechan_publish. Images themselves come from the WordPress
 * media library, so uploading and reuse are handled by core.
 */
final class MediaController {

	public static function routes() {
		return array(
			register_rest_route(
				'vivechan/v1',
				'/transcripts/(?P<id>\d+)/image',
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'set_transcript_image' ),
					'permission_callback' => array( __CLASS__, 'permission' ),
				)
			),
			register_rest_route(
				'vivechan/v1',
				'/chapter-images',
				array(
					array(
						'methods'             => \WP_REST_Server::READABLE,
						'callback'            => array( __CLASS__, 'chapter_images' ),
						'permission_callback' => array( __CLASS__, 'read_permission' ),
					),
					array(
						'methods'             => \WP_REST_Server::CREATABLE,
						'callback'            => array( __CLASS__, 'set_chapter_image' ),
						'permission_callback' => array( __CLASS__, 'permission' ),
					),
				)
			),
		);
	}

	public static function read_permission() {
		return Security::can_transcribe();
	}

	public static function permission() {
		return Security::can_publish();
	}

	/**
	 * POST /transcripts/{id}/image — { attachment_id }. 0 clears it.
	 */
	public static function set_transcript_image( $request ) {
		$record = TranscriptRepo::find_by_id( $request['id'] );
		if ( ! Helpers::row_accessible( $record ) ) {
			return $record ? Helpers::forbidden() : Helpers::error( 'Not found', 404 );
		}

		$post_id = (int) $record->post_id;
		if ( ! $post_id ) {
			return Helpers::error( 'This transcript has no page yet. Complete it first.', 409 );
		}

		$attachment_id = (int) Helpers::param( $request, 'attachment_id', 0 );

		if ( $attachment_id > 0 ) {
			if ( 'attachment' !== get_post_type( $attachment_id ) ) {
				return Helpers::error( 'That media item does not exist.', 404 );
			}
			set_post_thumbnail( $post_id, $attachment_id );
		} else {
			delete_post_thumbnail( $post_id );
		}

		return rest_ensure_response(
			array(
				'attachment_id' => (int) get_post_thumbnail_id( $post_id ),
				'url'           => (string) get_the_post_thumbnail_url( $post_id, 'medium' ),
			)
		);
	}

	public static function chapter_images() {
		return rest_ensure_response( ChapterImages::listing() );
	}

	/**
	 * POST /chapter-images — { chapter, attachment_id }. 0 clears it.
	 */
	public static function set_chapter_image( $request ) {
		$chapter = (int) Helpers::param( $request, 'chapter', 0 );
		if ( $chapter < 1 || $chapter > 18 ) {
			return Helpers::error( 'chapter must be between 1 and 18' );
		}

		$attachment_id = (int) Helpers::param( $request, 'attachment_id', 0 );
		if ( $attachment_id > 0 && 'attachment' !== get_post_type( $attachment_id ) ) {
			return Helpers::error( 'That media item does not exist.', 404 );
		}

		ChapterImages::set( $chapter, $attachment_id );

		return rest_ensure_response( ChapterImages::listing() );
	}
}
