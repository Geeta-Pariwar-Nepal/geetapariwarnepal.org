<?php
/**
 * GPN CRM - audit log.
 *
 * Records created / updated / deleted events with user, time, IP and a
 * JSON "changes" diff - matching the desktop app's full audit trail and
 * extending it with IP + change payloads.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GPN_Log {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Add a log entry.
	 *
	 * @param string $action      created|updated|deleted|login|login_failed|sync|import|backup|settings
	 * @param string $entity      sadhak|group|user|system
	 * @param int    $entity_id
	 * @param string $description
	 * @param array  $changes     optional diff array (stored as JSON)
	 * @param int    $user_id
	 */
	public function add( $action, $entity = 'sadhak', $entity_id = 0, $description = '', $changes = array(), $user_id = 0 ) {
		global $wpdb;
		$db   = GPN_DB::instance();
		$user = GPN_Auth::instance()->current_user();
		if ( ! $user_id && $user ) {
			$user_id = (int) $user['id'];
		}
		$user_name = '';
		if ( $user_id ) {
			$u         = GPN_User::instance()->get( $user_id );
			$user_name = $u ? $u->full_name : '';
		}

		$data = array(
			'user_id'     => $user_id ? $user_id : null,
			'user_name'   => $user_name,
			'action'      => sanitize_key( (string) $action ),
			'entity'      => sanitize_key( (string) $entity ),
			'entity_id'   => $entity_id ? (int) $entity_id : null,
			'description' => sanitize_textarea_field( (string) $description ),
			'changes'     => ! empty( $changes ) ? wp_json_encode( $changes ) : null,
			'ip'          => gpn_client_ip(),
			'created_at'  => gpn_now(),
		);

		$db->db()->insert( $db->logs(), $data, array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ) );
	}

	/**
	 * Latest logs, newest first.
	 */
	public function get( $limit = 50, $offset = 0 ) {
		$db  = GPN_DB::instance();
		$sql = $db->db()->prepare(
			'SELECT * FROM ' . $db->logs() . ' ORDER BY id DESC LIMIT %d OFFSET %d',
			(int) $limit,
			(int) $offset
		);
		return $db->db()->get_results( $sql, ARRAY_A );
	}

	public function count() {
		return GPN_DB::instance()->count( GPN_DB::instance()->logs() );
	}

	public function clear() {
		GPN_DB::instance()->db()->query( 'TRUNCATE TABLE ' . GPN_DB::instance()->logs() );
	}
}
