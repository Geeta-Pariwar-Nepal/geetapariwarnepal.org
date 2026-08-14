<?php

namespace Vivechan\Models;

defined('ABSPATH') || exit;

/**
 * Per-object sharing.
 *
 * Nothing is visible to everyone: a transcript, prompt or integration is
 * reachable only by the user who created it and by users it has been
 * explicitly shared with. There is no administrator bypass — an admin sees
 * their own rows and shared rows like anybody else.
 *
 * One table covers all three object types so the rules stay in a single place.
 */
final class ShareRepo {

	const TYPE_TRANSCRIPT  = 'transcript';
	const TYPE_PROMPT      = 'prompt';
	const TYPE_INTEGRATION = 'integration';

	public static function types() {
		return array( self::TYPE_TRANSCRIPT, self::TYPE_PROMPT, self::TYPE_INTEGRATION );
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'vivechan_shares';
	}

	public static function valid_type( $type ) {
		return in_array( $type, self::types(), true );
	}

	// ---------------------------------------------------------------------
	// Access predicates.
	// ---------------------------------------------------------------------

	/**
	 * SQL condition selecting rows the user may see: their own, or shared.
	 *
	 * $alias and $owner_col are code-supplied identifiers, never request input.
	 *
	 * @param string   $type      One of the TYPE_* constants.
	 * @param string   $alias     Table alias in the calling query.
	 * @param int|null $user_id   Defaults to the current user.
	 * @param bool     $allow_system Also match rows owned by user 0, used for
	 *                               the seeded prompt which ships with the
	 *                               plugin and belongs to no one.
	 */
	public static function access_sql( $type, $alias, $user_id = null, $owner_col = 'created_by', $allow_system = false ) {
		global $wpdb;

		$user_id = ( null === $user_id ) ? get_current_user_id() : (int) $user_id;
		$shares  = self::table();

		$sql = $wpdb->prepare(
			"( {$alias}.{$owner_col} = %d
			   OR EXISTS ( SELECT 1 FROM {$shares} sh
			               WHERE sh.object_type = %s
			                 AND sh.object_id = {$alias}.id
			                 AND sh.user_id = %d )",
			$user_id,
			$type,
			$user_id
		);

		if ( $allow_system ) {
			$sql .= " OR {$alias}.{$owner_col} = 0";
		}

		return $sql . ' )';
	}

	/**
	 * Can this user reach the object at all (owner or shared)?
	 */
	public static function can_access( $type, $object_id, $owner_id, $user_id = null ) {
		$user_id = ( null === $user_id ) ? get_current_user_id() : (int) $user_id;

		if ( ! $user_id ) {
			return false;
		}
		if ( (int) $owner_id === $user_id ) {
			return true;
		}
		return self::is_shared_with( $type, $object_id, $user_id );
	}

	/**
	 * Only the creator administers an object: renaming, editing settings,
	 * deleting, and deciding who else may see it.
	 */
	public static function owns( $owner_id, $user_id = null ) {
		$user_id = ( null === $user_id ) ? get_current_user_id() : (int) $user_id;
		return $user_id && (int) $owner_id === $user_id;
	}

	public static function is_shared_with( $type, $object_id, $user_id ) {
		global $wpdb;
		$table = self::table();

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$table} WHERE object_type = %s AND object_id = %d AND user_id = %d LIMIT 1",
				$type,
				(int) $object_id,
				(int) $user_id
			)
		);
	}

	// ---------------------------------------------------------------------
	// Mutations.
	// ---------------------------------------------------------------------

	public static function share( $type, $object_id, $user_id, $owner_id = 0 ) {
		global $wpdb;

		if ( ! self::valid_type( $type ) || ! $user_id ) {
			return false;
		}
		if ( self::is_shared_with( $type, $object_id, $user_id ) ) {
			return true;
		}

		return (bool) $wpdb->insert(
			self::table(),
			array(
				'object_type' => $type,
				'object_id'   => (int) $object_id,
				'user_id'     => (int) $user_id,
				'created_by'  => (int) $owner_id,
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%d', '%d', '%s' )
		);
	}

	public static function unshare( $type, $object_id, $user_id ) {
		global $wpdb;
		return (bool) $wpdb->delete(
			self::table(),
			array(
				'object_type' => $type,
				'object_id'   => (int) $object_id,
				'user_id'     => (int) $user_id,
			),
			array( '%s', '%d', '%d' )
		);
	}

	/**
	 * Drop every share for an object, so deleting it leaves nothing behind.
	 */
	public static function purge( $type, $object_id ) {
		global $wpdb;
		return $wpdb->delete(
			self::table(),
			array( 'object_type' => $type, 'object_id' => (int) $object_id ),
			array( '%s', '%d' )
		);
	}

	// ---------------------------------------------------------------------
	// Listing.
	// ---------------------------------------------------------------------

	/**
	 * Users an object is shared with, as { user_id, user_login, display_name }.
	 */
	public static function users_for( $type, $object_id ) {
		global $wpdb;
		$table = self::table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT sh.user_id, u.user_login, u.display_name
				   FROM {$table} sh
				   LEFT JOIN {$wpdb->users} u ON u.ID = sh.user_id
				  WHERE sh.object_type = %s AND sh.object_id = %d
				  ORDER BY u.display_name ASC",
				$type,
				(int) $object_id
			)
		);

		foreach ( $rows as $row ) {
			$row->user_id = (int) $row->user_id;
			// A user deleted from WordPress leaves the share row behind.
			if ( null === $row->user_login ) {
				$row->user_login   = '';
				$row->display_name = 'Deleted user #' . $row->user_id;
			}
		}

		return $rows;
	}
}
