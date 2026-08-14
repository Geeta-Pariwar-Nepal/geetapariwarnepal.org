<?php

namespace Vivechan\Services;

defined('ABSPATH') || exit;

/**
 * Fetches raw YouTube subtitles.
 *
 * Provider chain:
 *   1. YouTube "timedtext" endpoint (no API key) for likely languages
 *   2. yt-to-text.com (existing unofficial endpoint, spoofed headers)
 *   3. YouTube Data API v3 (optional key) to discover languages, then timedtext
 */
final class SubtitleFetcher {

	const YT_TO_TEXT_URL = 'https://yt-to-text.com/api/v1/Subtitles';

	/**
	 * @param string $video_id
	 * @param string $youtube_api_key Optional YouTube Data API key.
	 * @return string Raw transcript text.
	 * @throws \RuntimeException When no subtitles can be obtained.
	 */
	public static function fetch( $video_id, $youtube_api_key = '' ) {
		$errors = array();

		$languages = array( 'en', 'hi', 'ne' );

		if ( '' !== $youtube_api_key ) {
			$discovered = self::discover_languages( $video_id, $youtube_api_key );
			foreach ( $discovered as $lang ) {
				if ( ! in_array( $lang, $languages, true ) ) {
					$languages[] = $lang;
				}
			}
		}

		foreach ( $languages as $lang ) {
			$text = self::from_timedtext( $video_id, $lang );
			if ( '' !== $text ) {
				return $text;
			}
		}

		try {
			$text = self::from_yt_to_text( $video_id );
			if ( '' !== $text ) {
				return $text;
			}
			$errors[] = 'No transcript returned by the subtitle service.';
		} catch ( \RuntimeException $e ) {
			$errors[] = $e->getMessage();
		}

		$message = 'This video has no accessible subtitles or captions. ' . implode( ' ', $errors );
		throw new \RuntimeException( trim( $message ) );
	}

	private static function from_timedtext( $video_id, $lang ) {
		$url = 'https://www.youtube.com/api/timedtext?v=' . rawurlencode( $video_id ) . '&lang=' . rawurlencode( $lang );

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 20,
				'redirection' => 2,
				'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		$body = wp_remote_retrieve_body( $response );
		if ( '' === $body || false === strpos( $body, '<text' ) ) {
			return '';
		}

		return self::parse_timedtext( $body );
	}

	private static function parse_timedtext( $body ) {
		if ( ! preg_match_all( '/<text[^>]*>(.*?)<\/text>/s', $body, $matches ) ) {
			return '';
		}

		$parts = array();
		foreach ( $matches[1] as $segment ) {
			$segment = html_entity_decode( $segment, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$segment = preg_replace( '/\s+/u', ' ', $segment );
			$segment = trim( $segment );
			if ( '' === $segment || preg_match( '/^\[.*\]$/', $segment ) ) {
				continue;
			}
			$parts[] = $segment;
		}

		return implode( ' ', $parts );
	}

	private static function from_yt_to_text( $video_id ) {
		$response = wp_remote_post(
			self::YT_TO_TEXT_URL,
			array(
				'timeout'     => 30,
				'headers'     => array(
					'Content-Type'  => 'application/json',
					'Origin'        => 'https://tubetranscript.com',
					'Referer'       => 'https://tubetranscript.com/',
					'x-app-version' => '1',
					'x-source'      => 'tubetranscript',
					'User-Agent'    => 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36',
				),
				'body'        => wp_json_encode( array( 'video_id' => $video_id ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Subtitle service unreachable: ' . $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 400 ) {
			throw new \RuntimeException( 'Subtitle service error (HTTP ' . $code . ').' );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $data['code'] ) ) {
			if ( 'NO_SUBTITLES' === $data['code'] ) {
				throw new \RuntimeException( 'This video has no subtitles or captions available.' );
			}
			throw new \RuntimeException( 'Subtitle API error: ' . $data['code'] );
		}

		if ( empty( $data['data']['transcripts'] ) || ! is_array( $data['data']['transcripts'] ) ) {
			return '';
		}

		$parts = array();
		foreach ( $data['data']['transcripts'] as $segment ) {
			if ( ! is_array( $segment ) || empty( $segment['t'] ) ) {
				continue;
			}
			$text = trim( (string) $segment['t'] );
			if ( '' === $text || preg_match( '/^\[.*\]$/', $text ) ) {
				continue;
			}
			$parts[] = $text;
		}

		return implode( ' ', $parts );
	}

	/**
	 * Use the Data API to list caption tracks and collect their languages.
	 */
	private static function discover_languages( $video_id, $api_key ) {
		$url = 'https://www.googleapis.com/youtube/v3/captions?videoId=' . rawurlencode( $video_id ) . '&part=snippet&key=' . rawurlencode( $api_key );

		$response = wp_remote_get( $url, array( 'timeout' => 20 ) );
		if ( is_wp_error( $response ) ) {
			return array();
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return array();
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['items'] ) || ! is_array( $data['items'] ) ) {
			return array();
		}

		$languages = array();
		foreach ( $data['items'] as $item ) {
			if ( isset( $item['snippet']['language'] ) ) {
				$languages[] = $item['snippet']['language'];
			}
		}
		return $languages;
	}
}
