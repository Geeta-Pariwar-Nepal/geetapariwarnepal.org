<?php

namespace Vivechan\Rest;

defined('ABSPATH') || exit;

use Vivechan\Models\IntegrationRepo;
use Vivechan\Security;
use Vivechan\Services\ModelCatalog;

/**
 * /vivechan/v1/ai-integrations endpoints.
 *
 * Reading (masked keys, model lists) is allowed for any transcriber so they can
 * pick an integration in the form. Creating / editing / deleting requires the
 * "manage" capability (administrator).
 */
final class IntegrationsController {

	public static function routes() {
		return array(
			register_rest_route(
				'vivechan/v1',
				'/ai-integrations',
				array(
					array(
						'methods'             => \WP_REST_Server::READABLE,
						'callback'            => array( __CLASS__, 'index' ),
						'permission_callback' => array( __CLASS__, 'read_permission' ),
					),
					array(
						'methods'             => \WP_REST_Server::CREATABLE,
						'callback'            => array( __CLASS__, 'create' ),
						'permission_callback' => array( __CLASS__, 'write_permission' ),
					),
				)
			),
			register_rest_route(
				'vivechan/v1',
				'/ai-integrations/models',
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'models' ),
					'permission_callback' => array( __CLASS__, 'read_permission' ),
				)
			),
			register_rest_route(
				'vivechan/v1',
				'/ai-integrations/(?P<id>\d+)/models',
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'models_for_integration' ),
					'permission_callback' => array( __CLASS__, 'read_permission' ),
				)
			),
			register_rest_route(
				'vivechan/v1',
				'/ai-integrations/types',
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'types' ),
					'permission_callback' => array( __CLASS__, 'read_permission' ),
				)
			),
			register_rest_route(
				'vivechan/v1',
				'/ai-integrations/(?P<id>\d+)',
				array(
					array(
						'methods'             => \WP_REST_Server::READABLE,
						'callback'            => array( __CLASS__, 'show' ),
						'permission_callback' => array( __CLASS__, 'read_permission' ),
					),
					array(
						'methods'             => \WP_REST_Server::EDITABLE,
						'callback'            => array( __CLASS__, 'update' ),
						'permission_callback' => array( __CLASS__, 'write_permission' ),
					),
					array(
						'methods'             => \WP_REST_Server::DELETABLE,
						'callback'            => array( __CLASS__, 'delete' ),
						'permission_callback' => array( __CLASS__, 'write_permission' ),
					),
				)
			),
		);
	}

	public static function read_permission() {
		return Security::can_transcribe();
	}

	public static function write_permission() {
		// Every Vivechak can manage their own AI integrations / API keys.
		return Security::can_transcribe();
	}

	public static function index() {
		return rest_ensure_response( IntegrationRepo::find_all() );
	}

	/**
	 * GET /ai-integrations/models — curated fallback lists, keyed by provider.
	 * Used before an API key is available; no provider call is made.
	 */
	public static function models() {
		return rest_ensure_response( IntegrationRepo::type_models() );
	}

	/**
	 * GET /ai-integrations/{id}/models — live list for a saved integration.
	 * Pass ?refresh=1 to bypass the cache.
	 */
	public static function models_for_integration( $request ) {
		$record = IntegrationRepo::find_raw( $request['id'] );
		if ( ! $record ) {
			return Helpers::error( 'Not found', 404 );
		}
		// Shared users pick a model too — the key stays server-side either way.
		if ( ! IntegrationRepo::can_use( $record ) ) {
			return Helpers::forbidden();
		}

		$refresh = '1' === (string) Helpers::param( $request, 'refresh', '' );

		return rest_ensure_response(
			ModelCatalog::get( $record->type, (string) $record->api_key, $refresh )
		);
	}

	public static function types() {
		return rest_ensure_response( IntegrationRepo::supported_types() );
	}

	public static function show( $request ) {
		$record = IntegrationRepo::find_by_id( $request['id'] );
		if ( ! $record ) {
			return Helpers::error( 'Not found', 404 );
		}
		return rest_ensure_response( $record );
	}

	public static function create( $request ) {
		$title   = trim( (string) Helpers::param( $request, 'title', '' ) );
		$api_key = trim( (string) Helpers::param( $request, 'api_key', '' ) );
		$type    = Helpers::param( $request, 'type', '' );
		$model   = Helpers::param( $request, 'model', null );

		if ( '' === $title || '' === $api_key || '' === $type ) {
			return Helpers::error( 'title, api_key, and type are required' );
		}
		if ( ! in_array( $type, IntegrationRepo::supported_types(), true ) ) {
			return Helpers::error( 'type must be one of: ' . implode( ', ', IntegrationRepo::supported_types() ) );
		}
		$chunk_size = (int) Helpers::param( $request, 'chunk_size', 0 );
		if ( $chunk_size <= 0 ) {
			return Helpers::error( 'chunk_size is required and must be a number' );
		}

		$record = IntegrationRepo::create( sanitize_text_field( $title ), $api_key, $type, $model, $chunk_size, get_current_user_id() );

		return rest_ensure_response( array( 'id' => (int) $record->id, 'status' => 201 ) );
	}

	public static function update( $request ) {
		$record = IntegrationRepo::find_raw( $request['id'] );
		if ( ! $record ) {
			return Helpers::error( 'Not found', 404 );
		}
		if ( ! IntegrationRepo::owns( $record ) ) {
			return Helpers::forbidden();
		}

		$fields = array();
		$title  = Helpers::param( $request, 'title', null );
		$type   = Helpers::param( $request, 'type', null );
		$model  = $request->get_param( 'model' );
		$api_key = Helpers::param( $request, 'api_key', null );
		$chunk_size = $request->get_param( 'chunk_size' );

		if ( null !== $title ) {
			$fields['title'] = sanitize_text_field( $title );
		}
		if ( null !== $type ) {
			if ( ! in_array( $type, IntegrationRepo::supported_types(), true ) ) {
				return Helpers::error( 'type must be one of: ' . implode( ', ', IntegrationRepo::supported_types() ) );
			}
			$fields['type'] = $type;
		}
		if ( null !== $model ) {
			$fields['model'] = $model;
		}
		if ( null !== $api_key && '' !== $api_key ) {
			$fields['api_key'] = $api_key;
		}
		if ( null !== $chunk_size && '' !== $chunk_size ) {
			if ( ! is_numeric( $chunk_size ) || (int) $chunk_size <= 0 ) {
				return Helpers::error( 'chunk_size must be a positive number' );
			}
			$fields['chunk_size'] = (int) $chunk_size;
		}

		$updated = IntegrationRepo::update( $record->id, $fields );
		if ( ! $updated ) {
			return Helpers::error( 'Update failed', 500 );
		}

		return rest_ensure_response( array( 'id' => (int) $updated->id ) );
	}

	public static function delete( $request ) {
		$record = IntegrationRepo::find_raw( $request['id'] );
		if ( ! $record ) {
			return Helpers::error( 'Not found', 404 );
		}
		if ( ! IntegrationRepo::owns( $record ) ) {
			return Helpers::forbidden();
		}
		IntegrationRepo::delete( $record->id );
		return rest_ensure_response( array( 'deleted' => true ) );
	}
}
