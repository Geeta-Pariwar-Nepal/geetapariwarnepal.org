<?php

namespace Vivechan\Rest;

defined('ABSPATH') || exit;

/**
 * Registers all REST routes under the vivechan/v1 namespace.
 */
final class RestApi {

	const NAMESPACE = 'vivechan/v1';

	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/health',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'health' ),
				'permission_callback' => array( __CLASS__, 'health_permission' ),
			)
		);

		TranscriptsController::routes();
		PromptsController::routes();
		IntegrationsController::routes();
		SharesController::routes();
		MediaController::routes();
	}

	/**
	 * Health is gated to logged-in transcribers so it cannot be probed publicly.
	 */
	public static function health_permission() {
		return \Vivechan\Security::can_transcribe();
	}

	public static function health() {
		return rest_ensure_response(
			array(
				'status' => 'ok',
				'nonce'  => wp_create_nonce( 'wp_rest' ),
			)
		);
	}
}
