<?php
/**
 * GPN CRM - settings.
 *
 * Stored as a single WP option (gpn_crm_settings). Defaults mirror the
 * desktop application's behaviour.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GPN_Settings {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	const KEY = 'gpn_crm_settings';

	public function defaults() {
		return array(
			'app_name'               => __( 'Geeta Pariwar Nepal Sadhak CRM', 'gpn-crm' ),
			'default_country'        => '+977',
			'per_page'               => 50,
			'prn_remote_search'      => 1,
			'prn_remote_timeout'     => 3,
			'auto_backup'            => 1,
			'keep_backups'           => 20,
			'sync_token'             => '',
			'sync_enabled'           => 0,
			'whatsapp_enabled'       => 1,
			'whatsapp_prefix'        => '+977',
			'date_format'            => 'Y-m-d H:i',
			'log_history'            => 1,
		);
	}

	public function all() {
		$stored = get_option( self::KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return wp_parse_args( $stored, $this->defaults() );
	}

	public function get( $key, $default = null ) {
		$all = $this->all();
		return isset( $all[ $key ] ) ? $all[ $key ] : $default;
	}

	public function set( $key, $value ) {
		$all         = $this->all();
		$all[ $key ] = $value;
		update_option( self::KEY, $all );
	}

	public function update_all( $new ) {
		$all      = $this->all();
		$cleaned  = array();
		foreach ( $this->defaults() as $k => $v ) {
			$cleaned[ $k ] = isset( $new[ $k ] ) ? $new[ $k ] : $v;
		}
		update_option( self::KEY, $cleaned );
	}

	public function sync_token() {
		$token = $this->get( 'sync_token', '' );
		if ( '' === $token ) {
			$token = wp_generate_password( 32, false );
			$this->set( 'sync_token', $token );
		}
		return $token;
	}
}
