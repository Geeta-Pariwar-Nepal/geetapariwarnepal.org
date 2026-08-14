<?php
/**
 * Audit log — accountability trail for every meaningful action.
 *
 * @package Geeta_Pariwar_Parayan_Booking
 */

defined( 'ABSPATH' ) || exit;

class GPPB_Audit_Log {

	/**
	 * Singleton instance.
	 *
	 * @var GPPB_Audit_Log|null
	 */
	private static $instance = null;

	/**
	 * Singleton accessor.
	 *
	 * @return GPPB_Audit_Log
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {}

	/**
	 * Add an audit entry.
	 *
	 * @param int|null $user_id     WP user id (null = current user).
	 * @param string   $action      Action slug.
	 * @param string   $object_type Object type.
	 * @param int      $object_id   Object id.
	 * @param string   $description Human description.
	 * @return int
	 */
	public static function add( $user_id, $action, $object_type, $object_id, $description ) {
		return GPPB_Helpers::db()->insert(
			'audit_log',
			array(
				'user_id'     => null === $user_id && is_user_logged_in() ? get_current_user_id() : (int) $user_id,
				'action'      => sanitize_text_field( $action ),
				'object_type' => sanitize_text_field( $object_type ),
				'object_id'   => (int) $object_id,
				'description' => sanitize_textarea_field( $description ),
				'ip'          => GPPB_Helpers::client_ip(),
				'created_at'  => GPPB_Helpers::now(),
			)
		);
	}

	/**
	 * Search log entries.
	 *
	 * @param array $args { search, action, page, per_page }.
	 * @return array{items:array,total:int}
	 */
	public static function search( $args = array() ) {
		global $wpdb;
		$table    = GPPB_Helpers::db()->table( 'audit_log' );
		$defaults = array( 'search' => '', 'action' => '', 'page' => 1, 'per_page' => 30 );
		$args     = wp_parse_args( $args, $defaults );

		$where = ' WHERE 1=1';
		$sqlv  = array();

		if ( ! empty( $args['search'] ) ) {
			$where .= ' AND ( description LIKE %s OR action LIKE %s )';
			$sqlv[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$sqlv[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}
		if ( ! empty( $args['action'] ) ) {
			$where .= ' AND action = %s';
			$sqlv[] = $args['action'];
		}

		$offset = max( 0, ( (int) $args['page'] - 1 ) * (int) $args['per_page'] );

		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table}{$where}", $sqlv ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table}{$where} ORDER BY id DESC LIMIT %d OFFSET %d", array_merge( $sqlv, array( (int) $args['per_page'], $offset ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array( 'items' => $items, 'total' => $total );
	}
}
