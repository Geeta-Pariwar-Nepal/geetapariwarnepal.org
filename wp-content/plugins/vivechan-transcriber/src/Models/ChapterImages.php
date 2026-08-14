<?php

namespace Vivechan\Models;

defined('ABSPATH') || exit;

use Vivechan\Helpers\Chapters;

/**
 * Cover image for each Gita chapter, used on the public index.
 *
 * A chapter is not a post, so there is nothing to hang a featured image on —
 * the map lives in a single option as chapter number => attachment id.
 * Individual Vivechans use their own featured image; this is the fallback and
 * the art for the chapter heading itself.
 */
final class ChapterImages {

	const OPTION = 'vivechan_chapter_images';

	/**
	 * @return array<int,int> chapter => attachment id
	 */
	public static function all() {
		$raw = get_option( self::OPTION, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();
		foreach ( $raw as $chapter => $attachment_id ) {
			$chapter       = (int) $chapter;
			$attachment_id = (int) $attachment_id;
			if ( $chapter >= 1 && $chapter <= 18 && $attachment_id > 0 ) {
				$out[ $chapter ] = $attachment_id;
			}
		}
		return $out;
	}

	public static function get( $chapter ) {
		$all     = self::all();
		$chapter = (int) $chapter;
		return isset( $all[ $chapter ] ) ? $all[ $chapter ] : 0;
	}

	/**
	 * Set (or clear, with 0) the cover for one chapter.
	 */
	public static function set( $chapter, $attachment_id ) {
		$chapter = (int) $chapter;
		if ( $chapter < 1 || $chapter > 18 ) {
			return false;
		}

		$all           = self::all();
		$attachment_id = (int) $attachment_id;

		if ( $attachment_id > 0 && 'attachment' === get_post_type( $attachment_id ) ) {
			$all[ $chapter ] = $attachment_id;
		} else {
			unset( $all[ $chapter ] );
		}

		return update_option( self::OPTION, $all );
	}

	/**
	 * Chapter covers shaped for the app: every chapter, with its level and the
	 * image URL when one is set.
	 */
	public static function listing() {
		$all = self::all();
		$out = array();

		for ( $chapter = 1; $chapter <= 18; $chapter++ ) {
			$attachment_id = isset( $all[ $chapter ] ) ? $all[ $chapter ] : 0;

			$out[] = array(
				'chapter'       => $chapter,
				'label'         => Chapters::label( $chapter ),
				'level'         => Chapters::level_for_chapter( $chapter ),
				'attachment_id' => $attachment_id,
				'url'           => $attachment_id ? (string) wp_get_attachment_image_url( $attachment_id, 'medium' ) : '',
			);
		}

		return $out;
	}
}
