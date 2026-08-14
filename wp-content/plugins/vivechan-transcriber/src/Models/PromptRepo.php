<?php

namespace Vivechan\Models;

defined('ABSPATH') || exit;

/**
 * Repository for system prompts.
 */
final class PromptRepo {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'vivechan_system_prompts';
	}

	public static function find_by_title( $title ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE title = %s LIMIT 1", $title )
		);
	}

	public static function find_by_id( $id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id )
		);
	}

	/**
	 * Title-only list, newest first: the user's own prompts, prompts shared
	 * with them, and the built-in seeded prompt (created_by 0), which belongs
	 * to nobody and must stay usable by everyone.
	 */
	public static function all_titles() {
		global $wpdb;
		$table = self::table();

		$sql  = "SELECT p.id, p.title, p.created_by, p.created_at, p.updated_at FROM {$table} p WHERE ";
		$sql .= ShareRepo::access_sql( ShareRepo::TYPE_PROMPT, 'p', null, 'created_by', true );
		$sql .= ' ORDER BY p.created_at DESC';

		return $wpdb->get_results( $sql );
	}

	/**
	 * Default prompt for a new transcript — only one the user can actually use.
	 */
	public static function first() {
		global $wpdb;
		$table = self::table();

		$sql  = "SELECT p.id FROM {$table} p WHERE ";
		$sql .= ShareRepo::access_sql( ShareRepo::TYPE_PROMPT, 'p', null, 'created_by', true );
		$sql .= ' ORDER BY p.created_at DESC LIMIT 1';

		return $wpdb->get_row( $sql );
	}

	/**
	 * The seeded prompt has no owner, so it is readable by everyone.
	 */
	public static function is_system( $record ) {
		return $record && 0 === (int) $record->created_by;
	}

	public static function can_access( $record ) {
		if ( ! $record ) {
			return false;
		}
		if ( self::is_system( $record ) ) {
			return true;
		}
		return ShareRepo::can_access( ShareRepo::TYPE_PROMPT, $record->id, $record->created_by );
	}

	public static function create( $title, $content, $user_id ) {
		global $wpdb;
		$table = self::table();
		$now   = current_time( 'mysql', true );
		$wpdb->insert(
			$table,
			array(
				'title'      => $title,
				'content'    => $content,
				'created_by' => (int) $user_id,
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%s', '%d', '%s', '%s' )
		);
		return self::find_by_id( $wpdb->insert_id );
	}

	public static function update( $id, $title, $content ) {
		global $wpdb;
		$table = self::table();
		$wpdb->update(
			$table,
			array(
				'title'      => $title,
				'content'    => $content,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
		return self::find_by_id( $id );
	}

	public static function delete( $id ) {
		global $wpdb;
		$table = self::table();

		ShareRepo::purge( ShareRepo::TYPE_PROMPT, $id );

		return $wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );
	}
}
