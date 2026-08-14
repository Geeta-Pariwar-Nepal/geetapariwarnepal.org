<?php

namespace Vivechan\Services;

defined('ABSPATH') || exit;

use Vivechan\Exceptions\ApiException;

/**
 * AI provider adapters (Groq / DeepSeek via OpenAI-compatible API, Gemini) with
 * retry + backoff for 429/503 responses.
 */
final class AiService {

	const MAX_RETRIES = 5;

	/**
	 * Generate cleaned text for a chunk, retrying on rate limits.
	 */
	public static function generate_with_retry( $type, $config, $model, $system_prompt, $content ) {
		$delay = 5;

		for ( $attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++ ) {
			try {
				return self::generate_once( $type, $config, $model, $system_prompt, $content );
			} catch ( ApiException $e ) {
				if ( ! $e->is_retryable || $attempt >= self::MAX_RETRIES ) {
					throw $e;
				}
				$wait = $e->retry_after ?: $delay;
				sleep( (int) $wait );
				$delay = min( $delay * 2, 60 );
			}
		}

		throw new ApiException( 'AI request failed.' );
	}

	private static function generate_once( $type, $config, $model, $system_prompt, $content ) {
		if ( 'gemini' === $type ) {
			return self::gemini_generate( $config['api_key'], $model, $system_prompt, $content );
		}
		return self::openai_compat_generate( $config['base_url'], $config['api_key'], $model, $system_prompt, $content );
	}

	private static function openai_compat_generate( $base_url, $api_key, $model, $system_prompt, $content ) {
		$response = wp_remote_post(
			rtrim( (string) $base_url, '/' ) . '/chat/completions',
			array(
				'timeout' => 120,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'      => $model,
						'max_tokens' => 4096,
						'messages'   => array(
							array( 'role' => 'system', 'content' => $system_prompt ),
							array( 'role' => 'user', 'content' => $content ),
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new ApiException( 'Could not reach AI provider: ' . $response->get_error_message() );
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$headers = wp_remote_retrieve_headers( $response );
		$body    = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 429 === $code || 503 === $code ) {
			$retry_after = isset( $headers['retry-after'] ) ? (int) $headers['retry-after'] : null;
			throw new ApiException( 'AI provider is rate limited.', true, $retry_after );
		}

		if ( $code >= 400 ) {
			$message = 'AI provider error';
			if ( is_array( $body ) && isset( $body['error']['message'] ) ) {
				$message = self::first_line( (string) $body['error']['message'] );
			}
			throw new ApiException( $message );
		}

		if ( ! isset( $body['choices'][0]['message']['content'] ) ) {
			throw new ApiException( 'AI provider returned an empty response.' );
		}

		return (string) $body['choices'][0]['message']['content'];
	}

	private static function gemini_generate( $api_key, $model, $system_prompt, $content ) {
		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $api_key );

		$body = array(
			'contents'         => array(
				array(
					'role'  => 'user',
					'parts' => array( array( 'text' => $content ) ),
				),
			),
			'generationConfig' => array( 'maxOutputTokens' => 8192 ),
		);

		// systemInstruction must be an object with parts array, not a plain string.
		if ( '' !== trim( (string) $system_prompt ) ) {
			$body['systemInstruction'] = array(
				'parts' => array( array( 'text' => (string) $system_prompt ) ),
			);
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 180,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new ApiException( 'Could not reach Gemini: ' . $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 429 === $code || 503 === $code ) {
			$retry_after = self::gemini_retry_delay( $body );
			throw new ApiException( 'Gemini is rate limited.', true, $retry_after );
		}

		if ( $code >= 400 ) {
			$message = 'Gemini error';
			if ( is_array( $body ) && isset( $body['error']['message'] ) ) {
				$message = self::first_line( (string) $body['error']['message'] );
			}
			throw new ApiException( $message );
		}

		if ( isset( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
			return (string) $body['candidates'][0]['content']['parts'][0]['text'];
		}

		if ( isset( $body['promptFeedback']['blockReason'] ) ) {
			throw new ApiException( 'Gemini blocked the request: ' . $body['promptFeedback']['blockReason'] );
		}

		throw new ApiException( 'Gemini returned an empty response.' );
	}

	private static function gemini_retry_delay( $body ) {
		if ( is_array( $body ) && isset( $body['error']['details'] ) && is_array( $body['error']['details'] ) ) {
			foreach ( $body['error']['details'] as $detail ) {
				if ( isset( $detail['retryDelay'] ) ) {
					// retryDelay is a Duration string like "30s" or "1.5s"
					$raw = (string) $detail['retryDelay'];
					$seconds = (float) rtrim( $raw, 's' );
					return max( 1, (int) ceil( $seconds ) );
				}
			}
		}
		return null;
	}

	private static function first_line( $text ) {
		$line = trim( explode( "\n", $text )[0] );
		return '' !== $line ? $line : 'Unknown provider error';
	}
}
