<?php

namespace Vivechan\Models;

defined('ABSPATH') || exit;

use Vivechan\Helpers\Youtube;
use Vivechan\Models\ShareRepo;

/**
 * Repository for transcripts (the core business entity).
 */
final class TranscriptRepo {

	const STATUS = array( 'PENDING', 'REVIEW', 'COMPLETED', 'ERROR' );

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'vivechan_transcripts';
	}

	/**
	 * Transcripts the user may see: their own, plus any shared with them.
	 *
	 * @param int $user_id 0 for the current user.
	 */
	public static function find_all_for_user( $user_id = 0 ) {
		global $wpdb;
		$table = self::table();
		$sp    = PromptRepo::table();
		$ai    = IntegrationRepo::table();

		// $wpdb->users, not {$wpdb->prefix}users: on multisite the users table
		// is global (wp_users), so the prefixed name does not exist.
		$users = $wpdb->users;

		$sql = "SELECT t.id, t.video_id, t.name, t.title, t.filename, t.raw_length,
					t.chunks AS chunks_total,
					COALESCE(JSON_LENGTH(NULLIF(t.processed_chunks, '')), 0) AS chunks_done,
					t.processed_raw_length, t.status, t.error, t.chapter, t.post_id,
					t.integration_id, t.system_prompt_id, t.created_at, t.created_by,
					sp.title AS system_prompt_title,
					ai.title AS integration_title, ai.type AS integration_type,
					(t.content IS NOT NULL) AS has_content,
					(t.raw_transcript IS NOT NULL) AS has_raw,
					t.model AS model,
					cu.display_name AS created_by_name
				FROM {$table} t
				LEFT JOIN {$sp} sp ON sp.id = t.system_prompt_id
				LEFT JOIN {$ai} ai ON ai.id = t.integration_id
				LEFT JOIN {$users} cu ON cu.ID = t.created_by
				WHERE ";

		$sql .= ShareRepo::access_sql( ShareRepo::TYPE_TRANSCRIPT, 't', $user_id ?: null );
		$sql .= ' ORDER BY t.created_at DESC';

		$rows = $wpdb->get_results( $sql );
		foreach ( $rows as $row ) {
			$row->has_content = (bool) $row->has_content;
			$row->has_raw     = (bool) $row->has_raw;

			// wpdb returns every column as a string, so "1" === 1 is false in
			// the browser. Send real types, and an explicit ownership flag
			// rather than making the UI compare ids.
			$row->id         = (int) $row->id;
			$row->created_by = (int) $row->created_by;
			$row->is_owner   = ShareRepo::owns( $row->created_by );
		}
		return $rows;
	}

	public static function find_by_id( $id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id )
		);
	}

	public static function get_field( $id, $field ) {
		global $wpdb;
		$table = self::table();
		$allowed = array( 'id', 'video_id', 'raw_transcript', 'processed_chunks', 'status', 'processed_raw_length', 'used_chunk_size', 'model', 'chunks', 'raw_length' );
		if ( ! in_array( $field, $allowed, true ) ) {
			return null;
		}
		return $wpdb->get_var(
			$wpdb->prepare( "SELECT {$field} FROM {$table} WHERE id = %d", (int) $id )
		);
	}

	public static function create_pending( $data ) {
		global $wpdb;
		$table = self::table();
		$now   = current_time( 'mysql', true );
		$wpdb->insert(
			$table,
			array(
				'video_id'         => $data['video_id'],
				'filename'         => $data['filename'],
				'name'             => isset( $data['name'] ) ? $data['name'] : null,
				'model'            => isset( $data['model'] ) ? $data['model'] : null,
				'used_chunk_size'  => isset( $data['used_chunk_size'] ) ? (int) $data['used_chunk_size'] : 800,
				'status'           => 'PENDING',
				'processed_chunks' => '[]',
				'system_prompt_id' => isset( $data['system_prompt_id'] ) ? (int) $data['system_prompt_id'] : null,
				'integration_id'   => isset( $data['integration_id'] ) ? (int) $data['integration_id'] : null,
				'chapter'          => empty( $data['chapter'] ) ? null : (int) $data['chapter'],
				'created_by'       => (int) $data['created_by'],
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			// One format per field, in order. A null chapter still writes NULL:
			// wpdb special-cases null values before it applies formats.
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
		);
		return self::find_by_id( $wpdb->insert_id );
	}

	public static function set_status( $id, $status ) {
		global $wpdb;
		if ( ! in_array( $status, self::STATUS, true ) ) {
			return;
		}
		$table = self::table();
		$wpdb->update(
			$table,
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function reset_for_retry( $id ) {
		global $wpdb;
		$table = self::table();
		$wpdb->update(
			$table,
			array(
				'status'     => 'PENDING',
				'error'      => null,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id ),
			array( '%s', null, '%s' ),
			array( '%d' )
		);
	}

	public static function save_title( $id, $title ) {
		global $wpdb;
		$table = self::table();
		$wpdb->update(
			$table,
			array(
				'title'      => $title,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function save_raw( $id, $raw, $total_chunks, $chunk_size ) {
		global $wpdb;
		$table = self::table();
		$wpdb->update(
			$table,
			array(
				'raw_transcript'        => $raw,
				'chunks'                => (int) $total_chunks,
				'raw_length'            => mb_strlen( $raw, 'UTF-8' ),
				'processed_raw_length'  => 0,
				'used_chunk_size'       => (int) $chunk_size,
				'processed_chunks'      => '[]',
				'updated_at'            => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%d', '%d', '%d', '%d', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Update the raw transcript after human editing and recompute the chunk count.
	 */
	public static function update_raw( $id, $raw, $chunk_size ) {
		global $wpdb;
		$table = self::table();
		$chunks = count( Youtube::chunk_text( $raw, $chunk_size ) );
		$wpdb->update(
			$table,
			array(
				'raw_transcript' => $raw,
				'raw_length'     => mb_strlen( $raw, 'UTF-8' ),
				'chunks'         => $chunks,
				'processed_chunks' => '[]',
				'processed_raw_length' => 0,
				'used_chunk_size' => (int) $chunk_size,
				'updated_at'     => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%d', '%d', '%s', '%d', '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Append one processed chunk; returns the new chunk count.
	 */
	public static function save_chunk( $id, $chunk_text, $raw_chunk_length ) {
		global $wpdb;
		$table = self::table();
		$saved = self::get_field( $id, 'processed_chunks' );
		$arr   = json_decode( (string) $saved, true );
		if ( ! is_array( $arr ) ) {
			$arr = array();
		}
		$arr[] = $chunk_text;

		$wpdb->update(
			$table,
			array(
				'processed_chunks'       => wp_json_encode( $arr ),
				'processed_raw_length'   => ( (int) self::get_field( $id, 'processed_raw_length' ) ) + (int) $raw_chunk_length,
				'updated_at'             => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
		return count( $arr );
	}

	public static function mark_completed( $id, $content, $prompt_used = null ) {
		global $wpdb;
		$table = self::table();
		$wpdb->update(
			$table,
			array(
				'status'           => 'COMPLETED',
				'content'          => $content,
				'prompt_used'      => $prompt_used,
				'processed_chunks' => '[]',
				'error'            => null,
				'updated_at'       => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%s', '%s', null, '%s' ),
			array( '%d' )
		);
	}

	public static function mark_error( $id, $message ) {
		global $wpdb;
		$table = self::table();
		$wpdb->update(
			$table,
			array(
				'status'     => 'ERROR',
				'error'      => $message,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function update_name( $id, $name ) {
		global $wpdb;
		$table = self::table();
		$wpdb->update(
			$table,
			array(
				'name'       => $name ?: null,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function update_model( $id, $model ) {
		global $wpdb;
		$table = self::table();
		$wpdb->update(
			$table,
			array(
				'model'      => $model ?: null,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function update_integration( $id, $integration_id ) {
		global $wpdb;
		$table = self::table();
		$wpdb->update(
			$table,
			array(
				'integration_id' => (int) $integration_id,
				'updated_at'     => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	public static function set_chapter( $id, $chapter ) {
		global $wpdb;
		$table = self::table();
		$wpdb->update(
			$table,
			array(
				'chapter'    => $chapter ? (int) $chapter : null,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id ),
			// Must not lead with null. wpdb falls back to the *first* format
			// when one is missing, so a null in slot 0 leaves the placeholder
			// empty and the generated SQL is malformed — the update failed
			// silently and the chapter was never stored. A null $chapter still
			// writes NULL: wpdb special-cases null values before formatting.
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	public static function set_post_id( $id, $post_id ) {
		global $wpdb;
		$table = self::table();
		$wpdb->update(
			$table,
			array(
				'post_id'    => (int) $post_id,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Update the processed (AI-generated) content after human editing.
	 */
	public static function update_content( $id, $content ) {
		global $wpdb;
		$table = self::table();
		$wpdb->update(
			$table,
			array(
				'content'    => $content,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function delete( $id ) {
		global $wpdb;
		$table = self::table();

		ShareRepo::purge( ShareRepo::TYPE_TRANSCRIPT, $id );

		return $wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );
	}
}
