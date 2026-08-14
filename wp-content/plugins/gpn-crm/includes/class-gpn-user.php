<?php
/**
 * GPN CRM - user management.
 *
 * Mirrors the desktop application's users table (SHA-256 hashes, roles
 * Admin/BC/GC/CT/TA/Mentor, active flag). Only CRM Admins may manage users.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GPN_User {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function list() {
		$db = GPN_DB::instance();
		return $db->db()->get_results(
			'SELECT id, full_name, username, email, role, active, created_at, updated_at
			 FROM ' . $db->users() . ' ORDER BY full_name',
			ARRAY_A
		);
	}

	public function get( $id ) {
		$db = GPN_DB::instance();
		return $db->db()->get_row(
			$db->db()->prepare( 'SELECT * FROM ' . $db->users() . ' WHERE id = %d', (int) $id ),
			OBJECT
		);
	}

	/**
	 * Create/update a user. Empty password keeps the existing one on edit.
	 */
	public function save( $id, $full_name, $username, $email, $password, $role, $active ) {
		$db        = GPN_DB::instance();
		$full_name = trim( (string) $full_name );
		$username  = sanitize_user( trim( (string) $username ), true );
		$email     = sanitize_email( trim( (string) $email ) );
		$role      = in_array( $role, gpn_roles(), true ) ? $role : 'BC';
		$active    = $active ? 1 : 0;

		if ( '' === $full_name || '' === $username ) {
			return array( 'ok' => false, 'message' => 'Full name and username are required.' );
		}

		if ( ! is_email( $email ) ) {
			return array( 'ok' => false, 'message' => 'A valid email address is required.' );
		}

		// Username uniqueness.
		$existing = $db->db()->get_var( $db->db()->prepare( 'SELECT id FROM ' . $db->users() . ' WHERE username = %s AND id != %d', $username, (int) $id ) );
		if ( $existing ) {
			return array( 'ok' => false, 'message' => 'Username already taken.' );
		}

		// Email uniqueness.
		$existing_email = $db->db()->get_var( $db->db()->prepare( 'SELECT id FROM ' . $db->users() . ' WHERE email = %s AND id != %d', $email, (int) $id ) );
		if ( $existing_email ) {
			return array( 'ok' => false, 'message' => 'Email address is already in use.' );
		}

		$now = gpn_now();
		if ( $id ) {
			$data = array(
				'full_name' => $full_name,
				'username'  => $username,
				'email'     => $email ? $email : null,
				'role'      => $role,
				'active'    => $active,
				'updated_at' => $now,
			);
			if ( '' !== $password ) {
				$data['password_hash'] = gpn_hash_password( $password );
			}
			$db->db()->update( $db->users(), $data, array( 'id' => (int) $id ) );
			$message = 'User updated: ' . $full_name;
		} else {
			if ( '' === $password ) {
				return array( 'ok' => false, 'message' => 'Password is required for new users.' );
			}
			$db->db()->insert(
				$db->users(),
				array(
					'full_name'     => $full_name,
					'username'      => $username,
					'email'         => $email,
					'password_hash' => gpn_hash_password( $password ),
					'role'          => $role,
					'active'        => $active,
					'created_at'    => $now,
					'updated_at'    => $now,
				)
			);
			$message = 'User created: ' . $full_name;
		}

		GPN_Log::instance()->add( $id ? 'updated' : 'created', 'user', (int) $id, $message );
		return array( 'ok' => true, 'message' => $message, 'id' => $id ? (int) $id : (int) $db->db()->insert_id );
	}

	public function delete( $id ) {
		$db = GPN_DB::instance();
		$u  = $db->db()->get_row( $db->db()->prepare( 'SELECT * FROM ' . $db->users() . ' WHERE id = %d', (int) $id ), ARRAY_A );
		if ( ! $u ) {
			return array( 'ok' => false, 'message' => 'User not found.' );
		}
		if ( 'Admin' === $u['role'] ) {
			$admins = (int) $db->db()->get_var( $db->db()->prepare( 'SELECT COUNT(*) FROM ' . $db->users() . " WHERE role = 'Admin' AND active = 1" ) );
			if ( $admins <= 1 ) {
				return array( 'ok' => false, 'message' => 'Cannot delete the last active Administrator.' );
			}
		}
		$db->db()->delete( $db->users(), array( 'id' => (int) $id ), array( '%d' ) );
		GPN_Log::instance()->add( 'deleted', 'user', (int) $id, 'User deleted: ' . $u['full_name'] );
		return array( 'ok' => true, 'message' => 'User deleted.' );
	}

	/**
	 * Active user full names (for the group role combos, desktop users combo).
	 */
	public function active_names() {
		$db   = GPN_DB::instance();
		$rows = $db->db()->get_results( 'SELECT id, full_name FROM ' . $db->users() . ' WHERE active = 1 ORDER BY full_name', ARRAY_A );
		return array_map( function ( $r ) {
			return $r['full_name'];
		}, $rows );
	}
}
