<?php

namespace Vivechan\Helpers;

defined('ABSPATH') || exit;

/**
 * YouTube helpers: URL parsing, text chunking, title lookup via oEmbed.
 */
final class Youtube {

	/**
	 * Extract a video ID from common YouTube URL forms, or a bare ID.
	 */
	public static function extract_video_id( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return null;
		}

		if ( preg_match( '~(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([^&\n?#]+)~', $url, $m ) ) {
			return $m[1];
		}
		if ( preg_match( '/^([a-zA-Z0-9_-]{11})$/', $url, $m ) ) {
			return $m[1];
		}
		return null;
	}

	/**
	 * Split text into word-count chunks.
	 */
	public static function chunk_text( $text, $words_per_chunk = 800 ) {
		$words_per_chunk = max( 1, (int) $words_per_chunk );
		$words           = preg_split( '/\s+/', trim( (string) $text ) );
		$words           = array_values( array_filter( $words, 'strlen' ) );

		if ( 0 === count( $words ) ) {
			return array();
		}

		$chunks = array();
		foreach ( array_chunk( $words, $words_per_chunk ) as $group ) {
			$chunks[] = implode( ' ', $group );
		}
		return $chunks;
	}

	/**
	 * Fetch a video title via YouTube's oEmbed endpoint (no scraping).
	 */
	public static function fetch_title( $video_id ) {
		$url = 'https://www.youtube.com/oembed?url=' . rawurlencode( 'https://www.youtube.com/watch?v=' . $video_id ) . '&format=json';

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 15,
				'redirection' => 2,
				'user-agent'  => 'Mozilla/5.0 (compatible; Vivechan/1.0)',
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( is_array( $data ) && ! empty( $data['title'] ) ) {
			return sanitize_text_field( $data['title'] );
		}
		return null;
	}
}
