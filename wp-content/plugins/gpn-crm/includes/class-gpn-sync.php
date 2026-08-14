<?php
/**
 * GPN CRM - WordPress REST API sync.
 *
 * Migrates the desktop "Sync from Web" (download backup from a remote app)
 * into a WordPress REST API sync. Admins can Pull a remote site's CRM data
 * into this site, or Push this site's CRM data to a remote site, over the
 * built-in REST endpoint.
 *
 * Endpoint:  /wp-json/gpn-crm/v1/sync
 * Auth:      CRM username + password (Admin) AND the shared sync token.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GPN_Sync {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	const REST_NAMESPACE = 'gpn-crm/v1';

	/**
	 * Validate sync credentials (CRM Admin + token).
	 */
	public function authorize( $username, $password, $token ) {
		if ( ! GPN_Settings::instance()->get( 'sync_enabled', 0 ) ) {
			return false;
		}
		// Token check.
		$expected = GPN_Settings::instance()->sync_token();
		if ( $expected && $token && ! hash_equals( $expected, (string) $token ) ) {
			return false;
		}
		// CRM admin credential check.
		if ( ! $username || '' === $password ) {
			return false;
		}
		$user = GPN_Auth::instance()->authenticate( $username, $password );
		if ( ! $user || 'Admin' !== $user['role'] ) {
			return false;
		}
		return true;
	}

	/**
	 * Handle an inbound sync request from the REST endpoint.
	 */
	public function handle_request( $params ) {
		$username = isset( $params['username'] ) ? (string) $params['username'] : '';
		$password = isset( $params['password'] ) ? (string) $params['password'] : '';
		$token    = isset( $params['token'] ) ? (string) $params['token'] : '';
		$op       = isset( $params['op'] ) ? (string) $params['op'] : 'export';

		if ( ! $this->authorize( $username, $password, $token ) ) {
			return new WP_REST_Response( array( 'error' => 'Sync not authorized.' ), 403 );
		}

		if ( 'import' === $op ) {
			$data = isset( $params['data'] ) ? $params['data'] : null;
			if ( is_string( $data ) ) {
				$data = json_decode( $data, true );
			}
			$result = GPN_Backup::instance()->restore_data( $data );
			if ( ! $result['ok'] ) {
				return new WP_REST_Response( array( 'error' => $result['message'] ), 400 );
			}
			GPN_Log::instance()->add( 'sync', 'system', 0, 'Sync: data imported from remote via REST' );
			return new WP_REST_Response( array( 'success' => true, 'message' => 'Data imported.' ), 200 );
		}

		// export
		GPN_Log::instance()->add( 'sync', 'system', 0, 'Sync: data exported via REST' );
		return new WP_REST_Response( array( 'success' => true, 'data' => GPN_Backup::instance()->export_data() ), 200 );
	}

	/**
	 * Pull from a remote site. Returns array('ok'=>bool,'message'=>).
	 */
	public function pull_remote( $url, $username, $password, $token ) {
		$url = untrailingslashit( esc_url_raw( trim( $url ) ) );
		if ( ! $url ) {
			return array( 'ok' => false, 'message' => 'Remote URL is required.' );
		}
		$endpoint = $url . '/wp-json/' . self::REST_NAMESPACE . '/sync';
		$body     = wp_json_encode( array(
			'username' => $username,
			'password' => $password,
			'token'    => $token,
			'op'       => 'export',
		) );

		$response = wp_remote_post( $endpoint, array(
			'timeout'     => 60,
			'redirection' => 3,
			'headers'     => array( 'Content-Type' => 'application/json' ),
			'body'        => $body,
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'message' => 'Request failed: ' . $response->get_error_message() );
		}
		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code !== 200 || empty( $data['data'] ) ) {
			$msg  = isset( $data['error'] ) ? $data['error'] : 'Remote returned an error (HTTP ' . $code . ').';
			return array( 'ok' => false, 'message' => $msg );
		}

		$result = GPN_Backup::instance()->restore_data( $data['data'] );
		if ( ! $result['ok'] ) {
			return $result;
		}
		GPN_Log::instance()->add( 'sync', 'system', 0, 'Sync: pulled data from ' . $url );
		return array( 'ok' => true, 'message' => 'Data pulled from remote successfully.' );
	}

	/**
	 * Push to a remote site. Returns array('ok'=>bool,'message'=>).
	 */
	public function push_remote( $url, $username, $password, $token ) {
		$url = untrailingslashit( esc_url_raw( trim( $url ) ) );
		if ( ! $url ) {
			return array( 'ok' => false, 'message' => 'Remote URL is required.' );
		}
		$endpoint = $url . '/wp-json/' . self::REST_NAMESPACE . '/sync';
		$body     = wp_json_encode( array(
			'username' => $username,
			'password' => $password,
			'token'    => $token,
			'op'       => 'import',
			'data'     => GPN_Backup::instance()->export_data(),
		) );

		$response = wp_remote_post( $endpoint, array(
			'timeout'     => 60,
			'redirection' => 3,
			'headers'     => array( 'Content-Type' => 'application/json' ),
			'body'        => $body,
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'message' => 'Request failed: ' . $response->get_error_message() );
		}
		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code !== 200 ) {
			$msg = isset( $data['error'] ) ? $data['error'] : 'Remote returned an error (HTTP ' . $code . ').';
			return array( 'ok' => false, 'message' => $msg );
		}
		GPN_Log::instance()->add( 'sync', 'system', 0, 'Sync: pushed data to ' . $url );
		return array( 'ok' => true, 'message' => 'Data pushed to remote successfully.' );
	}
}