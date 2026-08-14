<?php

namespace Vivechan\Services;

defined('ABSPATH') || exit;

use Vivechan\Models\IntegrationRepo;

/**
 * Live model catalogue.
 *
 * Model lists are read from each provider's own "list models" endpoint so new
 * models show up without a plugin release. Results are cached per API key, and
 * the curated lists in IntegrationRepo::PROVIDERS are used only as a fallback
 * when the provider cannot be reached (no key, network error, revoked key).
 */
final class ModelCatalog {

	/** How long a successful lookup is reused. */
	const TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * How long a failure is remembered. Without this a dead key would trigger a
	 * fresh HTTP call on every single page load.
	 */
	const TTL_FAILURE = 10 * MINUTE_IN_SECONDS;

	/** Deliberately short: this runs inside a user-facing REST request. */
	const TIMEOUT = 8;

	/**
	 * Anything that is not a text-in / text-out model.
	 *
	 * There is no modality field to filter on: Groq's /models says nothing
	 * about it, and Gemini's image models return pictures *through*
	 * generateContent, so the method filter does not catch them either — which
	 * is how "Nano Banana Pro" became the default selection. So this matches on
	 * id, display name and description together.
	 *
	 * Note "vision" is deliberately absent: those take images and emit text,
	 * which is perfectly usable for cleaning up a transcript.
	 */
	const EXCLUDE = '~(image|imagen|nano[- ]?banana|video|veo|audio|speech|voice|whisper|\btts\b|\bstt\b|text-to-speech|\blive\b|realtime|embed|rerank|moderat|guard|safety)~i';

	/**
	 * Model list for a provider + key, fetching when the cache is cold.
	 *
	 * @return array{models: array<array{id: string, label: string}>, source: string, error: string|null}
	 */
	public static function get( $type, $api_key, $force = false ) {
		$providers = IntegrationRepo::PROVIDERS;
		$type      = (string) $type;

		if ( ! isset( $providers[ $type ] ) ) {
			return self::result( array(), 'fallback', 'Unknown provider type.' );
		}

		$api_key = trim( (string) $api_key );
		if ( '' === $api_key ) {
			return self::result( IntegrationRepo::fallback_models( $type ), 'fallback', 'No API key available.' );
		}

		$cache = self::cache_key( $type, $api_key );
		if ( ! $force ) {
			$hit = get_transient( $cache );
			if ( is_array( $hit ) && isset( $hit['models'] ) ) {
				return $hit;
			}
		}

		try {
			$models = ( 'gemini' === $type )
				? self::fetch_gemini( $api_key )
				: self::fetch_openai_compat( $providers[ $type ]['base_url'], $api_key );
		} catch ( \RuntimeException $e ) {
			$result = self::result( IntegrationRepo::fallback_models( $type ), 'fallback', $e->getMessage() );
			set_transient( $cache, $result, self::TTL_FAILURE );
			return $result;
		}

		if ( empty( $models ) ) {
			$result = self::result( IntegrationRepo::fallback_models( $type ), 'fallback', 'The provider returned no usable text models.' );
			set_transient( $cache, $result, self::TTL_FAILURE );
			return $result;
		}

		$result = self::result( $models, 'live', null );
		set_transient( $cache, $result, self::TTL );
		return $result;
	}

	/**
	 * Cache-only lookup — never makes an HTTP call.
	 *
	 * Used from cron/worker paths where a slow provider must not stall the job.
	 */
	public static function cached_or_fallback( $type, $api_key ) {
		$hit = get_transient( self::cache_key( (string) $type, trim( (string) $api_key ) ) );
		if ( is_array( $hit ) && ! empty( $hit['models'] ) ) {
			return $hit['models'];
		}
		return IntegrationRepo::fallback_models( $type );
	}

	/**
	 * Drop every cached list for a key (called when an integration is edited or
	 * deleted, so a swapped key never serves the previous key's models).
	 */
	public static function forget( $type, $api_key ) {
		delete_transient( self::cache_key( (string) $type, trim( (string) $api_key ) ) );
	}

	// ---------------------------------------------------------------------
	// Providers.
	// ---------------------------------------------------------------------

	/**
	 * OpenAI-compatible listing (Groq, DeepSeek): GET {base}/models.
	 */
	private static function fetch_openai_compat( $base_url, $api_key ) {
		$response = wp_remote_get(
			rtrim( (string) $base_url, '/' ) . '/models',
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Accept'        => 'application/json',
				),
			)
		);

		$body = self::body_or_throw( $response );

		if ( empty( $body['data'] ) || ! is_array( $body['data'] ) ) {
			return array();
		}

		$models = array();
		foreach ( $body['data'] as $item ) {
			if ( empty( $item['id'] ) ) {
				continue;
			}
			$id = (string) $item['id'];
			if ( self::excluded( $id, isset( $item['owned_by'] ) ? (string) $item['owned_by'] : '' ) ) {
				continue;
			}
			// Groq flags retired models; skip anything explicitly inactive.
			if ( isset( $item['active'] ) && false === $item['active'] ) {
				continue;
			}
			$models[] = array(
				'id'      => $id,
				'label'   => $id,
				'created' => isset( $item['created'] ) ? (int) $item['created'] : 0,
			);
		}

		return self::sorted( $models );
	}

	/**
	 * Gemini listing: GET v1beta/models. Only models advertising
	 * "generateContent" can be used for transcript clean-up.
	 */
	private static function fetch_gemini( $api_key ) {
		$models = array();
		$token  = '';

		// The endpoint paginates; a couple of pages covers the whole catalogue.
		for ( $page = 0; $page < 3; $page++ ) {
			$url = 'https://generativelanguage.googleapis.com/v1beta/models?pageSize=200&key=' . rawurlencode( $api_key );
			if ( '' !== $token ) {
				$url .= '&pageToken=' . rawurlencode( $token );
			}

			$response = wp_remote_get(
				$url,
				array(
					'timeout' => self::TIMEOUT,
					'headers' => array( 'Accept' => 'application/json' ),
				)
			);

			$body = self::body_or_throw( $response );

			if ( empty( $body['models'] ) || ! is_array( $body['models'] ) ) {
				break;
			}

			foreach ( $body['models'] as $item ) {
				if ( empty( $item['name'] ) ) {
					continue;
				}
				$methods = isset( $item['supportedGenerationMethods'] ) ? (array) $item['supportedGenerationMethods'] : array();
				if ( ! in_array( 'generateContent', $methods, true ) ) {
					continue;
				}
				$id    = preg_replace( '#^models/#', '', (string) $item['name'] );
				$label = ! empty( $item['displayName'] ) ? (string) $item['displayName'] : $id;
				$desc  = ! empty( $item['description'] ) ? (string) $item['description'] : '';

				// Gemini's description states the purpose outright ("...image
				// generation", "...native audio"), so it catches models whose
				// id gives nothing away.
				if ( self::excluded( $id, $label, $desc ) ) {
					continue;
				}
				$models[] = array(
					'id'      => $id,
					'label'   => $label,
					'created' => 0,
				);
			}

			$token = ! empty( $body['nextPageToken'] ) ? (string) $body['nextPageToken'] : '';
			if ( '' === $token ) {
				break;
			}
		}

		return self::sorted( $models );
	}

	// ---------------------------------------------------------------------
	// Helpers.
	// ---------------------------------------------------------------------

	private static function body_or_throw( $response ) {
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Could not reach the provider: ' . $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $code || 403 === $code ) {
			throw new \RuntimeException( 'The provider rejected this API key.' );
		}

		if ( $code >= 400 ) {
			$message = isset( $body['error']['message'] ) ? (string) $body['error']['message'] : 'HTTP ' . $code;
			throw new \RuntimeException( 'Could not list models: ' . trim( explode( "\n", $message )[0] ) );
		}

		return is_array( $body ) ? $body : array();
	}

	/**
	 * @param string ...$fields id, display name, description — whatever the
	 *                          provider gives. Matching across all of them
	 *                          catches models whose id alone looks innocuous.
	 */
	private static function excluded( ...$fields ) {
		return (bool) preg_match( self::EXCLUDE, implode( ' ', array_filter( $fields ) ) );
	}

	private static function is_preview( $id ) {
		return (bool) preg_match( '/(preview|experimental|-exp[-0-9]*$|-exp-)/i', $id );
	}

	/**
	 * Newest first when the provider timestamps its models (Groq does),
	 * otherwise reverse natural order so higher version numbers float up
	 * (gemini-2.5-pro above gemini-2.0-flash).
	 */
	private static function sorted( $models ) {
		$has_timestamps = false;
		foreach ( $models as $model ) {
			if ( ! empty( $model['created'] ) ) {
				$has_timestamps = true;
				break;
			}
		}

		usort(
			$models,
			static function ( $a, $b ) use ( $has_timestamps ) {
				// Stable releases first: the top entry becomes the default
				// selection, and that should not be a preview build.
				$a_preview = self::is_preview( $a['id'] );
				$b_preview = self::is_preview( $b['id'] );
				if ( $a_preview !== $b_preview ) {
					return $a_preview ? 1 : -1;
				}
				if ( $has_timestamps && $a['created'] !== $b['created'] ) {
					return $b['created'] <=> $a['created'];
				}
				return strnatcasecmp( $b['id'], $a['id'] );
			}
		);

		// 'created' was only needed for sorting.
		return array_map(
			static function ( $model ) {
				return array(
					'id'    => $model['id'],
					'label' => $model['label'],
				);
			},
			$models
		);
	}

	private static function result( $models, $source, $error ) {
		return array(
			'models' => array_values( (array) $models ),
			'source' => $source,
			'error'  => $error,
		);
	}

	/**
	 * Keyed by a hash of the API key so two users with different keys (and so
	 * different model access) never share a cache entry. The key itself is
	 * never stored in the transient name.
	 */
	private static function cache_key( $type, $api_key ) {
		return 'vivechan_models_' . sanitize_key( $type ) . '_' . substr( hash( 'sha256', $api_key ), 0, 16 );
	}
}
