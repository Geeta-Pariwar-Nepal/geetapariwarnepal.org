<?php
/**
 * GPN CRM - REST API controller.
 *
 * Registers the WordPress REST route used for CRM-to-CRM sync:
 *   POST /wp-json/gpn-crm/v1/sync
 *
 * The endpoint is public (no WP session) but requires CRM Admin credentials
 * plus the shared sync token configured on the Settings page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GPN_Rest {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_routes() {
		register_rest_route(
			GPN_Sync::REST_NAMESPACE,
			'/sync',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'sync_callback' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'username' => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					'password' => array( 'type' => 'string', 'required' => true ),
					'token'    => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					'op'       => array( 'type' => 'string', 'required' => false, 'default' => 'export', 'sanitize_callback' => 'sanitize_key' ),
					'data'     => array( 'type' => 'object', 'required' => false ),
				),
			)
		);
	}

	public function sync_callback( $request ) {
		$params = $request->get_params();
		return GPN_Sync::instance()->handle_request( $params );
	}
}
add_action( 'rest_api_init', array( GPN_Rest::instance(), 'register_routes' ) );
