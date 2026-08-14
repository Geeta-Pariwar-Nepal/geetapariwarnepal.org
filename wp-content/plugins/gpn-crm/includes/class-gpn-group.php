<?php
/**
 * GPN CRM - group service.
 *
 * 100% port of services/group_service.py. Each group stores role-holders as
 * free-text names and, when a name matches an active user, the user ID is
 * stored too (used for permission checks).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GPN_Group {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * All groups as rows of (id, name, level, batch).
	 */
	public function get_all() {
		$db = GPN_DB::instance();
		$rows = $db->db()->get_results(
			'SELECT id, name, COALESCE(level, "Level 1") AS level, COALESCE(batch, "Regular") AS batch
			 FROM ' . $db->groups() . ' ORDER BY name',
			ARRAY_A
		);
		return $rows;
	}

	/**
	 * Full group with resolved role-holder names.
	 */
	public function get_with_names( $group_id ) {
		$db    = GPN_DB::instance();
		$groups_t = $db->groups();
		$users_t  = $db->users();
		$row = $db->db()->get_row(
			$db->db()->prepare(
				"SELECT g.id, g.name,
				        COALESCE(g.level, 'Level 1') AS level,
				        COALESCE(g.batch, 'Regular') AS batch,
				        COALESCE(NULLIF(g.bc_name, ''), COALESCE(bc.full_name, '')) AS bc_name,
				        COALESCE(NULLIF(g.gc_name, ''), COALESCE(gc.full_name, '')) AS gc_name,
				        COALESCE(NULLIF(g.ct_name, ''), COALESCE(ct.full_name, '')) AS ct_name,
				        COALESCE(NULLIF(g.ta_name, ''), COALESCE(ta.full_name, '')) AS ta_name,
				        COALESCE(g.timing, '') AS timing,
				        COALESCE(g.zoom_link, '') AS zoom_link,
				        COALESCE(g.status, 'Active') AS status
				 FROM {$groups_t} g
				 LEFT JOIN {$users_t} bc ON bc.id = g.bc_id
				 LEFT JOIN {$users_t} gc ON gc.id = g.gc_id
				 LEFT JOIN {$users_t} ct ON ct.id = g.ct_id
				 LEFT JOIN {$users_t} ta ON ta.id = g.ta_id
				 WHERE g.id = %d",
				(int) $group_id
			),
			ARRAY_A
		);
		return $row;
	}

	/**
	 * Resolve role names to user IDs using active users (mirrors save_group).
	 */
	public function resolve_role_ids( $bc_name, $gc_name, $ct_name, $ta_name ) {
		$db    = GPN_DB::instance();
		$users = $db->db()->get_results( 'SELECT id, full_name FROM ' . $db->users() . ' WHERE active = 1', ARRAY_A );
		$by_name = array();
		foreach ( $users as $u ) {
			$by_name[ $u['full_name'] ] = (int) $u['id'];
		}
		return array(
			isset( $by_name[ $bc_name ] ) ? $by_name[ $bc_name ] : null,
			isset( $by_name[ $gc_name ] ) ? $by_name[ $gc_name ] : null,
			isset( $by_name[ $ct_name ] ) ? $by_name[ $ct_name ] : null,
			isset( $by_name[ $ta_name ] ) ? $by_name[ $ta_name ] : null,
		);
	}

	/**
	 * Insert or update a group.
	 */
	public function save( $group_id, $name, $bc_name, $gc_name, $ct_name, $ta_name, $timing, $zoom_link, $level, $batch, $status = 'Active' ) {
		list( $bc_id, $gc_id, $ct_id, $ta_id ) = $this->resolve_role_ids( $bc_name, $gc_name, $ct_name, $ta_name );

		$db    = GPN_DB::instance();
		$now   = gpn_now();
		$level = $level ? $level : 'Level 1';
		$batch = $batch ? $batch : 'Regular';
		$status = $status ? $status : 'Active';

		$data = array(
			'bc_id'     => $bc_id,
			'gc_id'     => $gc_id,
			'ct_id'     => $ct_id,
			'ta_id'     => $ta_id,
			'bc_name'   => '' !== $bc_name ? $bc_name : null,
			'gc_name'   => '' !== $gc_name ? $gc_name : null,
			'ct_name'   => '' !== $ct_name ? $ct_name : null,
			'ta_name'   => '' !== $ta_name ? $ta_name : null,
			'timing'    => '' !== $timing ? $timing : null,
			'zoom_link' => '' !== $zoom_link ? $zoom_link : null,
			'level'     => $level,
			'batch'     => $batch,
			'status'    => $status,
		);

		if ( $group_id ) {
			$data['name']       = $name;
			$data['updated_at'] = $now;
			$db->db()->update( $db->groups(), $data, array( 'id' => (int) $group_id ) );
			$saved_id = $group_id;
		} else {
			$data['name']       = $name;
			$data['created_at'] = $now;
			$data['updated_at'] = $now;
			$db->db()->insert( $db->groups(), $data );
			$saved_id = $db->db()->insert_id;
		}

		GPN_Backup::instance()->auto_backup();
		GPN_Log::instance()->add( $group_id ? 'updated' : 'created', 'group', $saved_id, 'Group: ' . $name, array( 'name' => $name ) );

		return array( 'ok' => true, 'id' => $saved_id, 'message' => $group_id ? 'Group updated.' : 'Group created.' );
	}

	public function delete( $group_id ) {
		$db    = GPN_DB::instance();
		$group = $this->get_with_names( (int) $group_id );
		$db->db()->delete( $db->groups(), array( 'id' => (int) $group_id ), array( '%d' ) );
		GPN_Backup::instance()->auto_backup();
		GPN_Log::instance()->add( 'deleted', 'group', (int) $group_id, 'Group deleted' . ( $group ? ': ' . $group['name'] : '' ) );
	}

	/**
	 * All groups with full info (group manager grid).
	 */
	public function list_full() {
		$db = GPN_DB::instance();
		return $db->db()->get_results(
			'SELECT g.id, g.name,
			        COALESCE(g.level, "Level 1") AS level,
			        COALESCE(g.batch, "Regular") AS batch,
			        COALESCE(g.timing, "") AS timing,
			        COALESCE(NULLIF(g.bc_name, ""), "—") AS bc_name,
			        COALESCE(NULLIF(g.gc_name, ""), "—") AS gc_name,
			        COALESCE(NULLIF(g.ct_name, ""), "—") AS ct_name,
			        COALESCE(NULLIF(g.ta_name, ""), "—") AS ta_name,
			        COALESCE(g.zoom_link, "") AS zoom_link,
			        COALESCE(g.status, "Active") AS status
			 FROM ' . $db->groups() . ' g
			 ORDER BY g.name',
			ARRAY_A
		);
	}
}
