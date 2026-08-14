<?php
/**
 * GPN CRM - sadhak service.
 *
 * 100% port of the desktop/Flask sadhak CRUD logic (save, list, delete,
 * history, can_edit). Keeps the exact duplicate-phone checks, the role
 * holder snapshot copying from the group, and the audit history rows.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GPN_Sadhak {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Allowed sort columns (whitelisted, mirrors grid headings).
	 */
	public function sortable_columns() {
		return array( 'name', 'phone', 'email', 'prn', 'group_name', 'level', 'batch', 'bc_name', 'gc_name', 'ct_name', 'ta_name', 'created_at', 'updated_at', 'created_by_name', 'updated_by_name', 'id' );
	}

	/**
	 * Safe ORDER BY expression per sortable column. Several headings are
	 * SELECT aliases or joined columns, so a plain "s.<col>" prefix would
	 * produce an invalid SQL clause.
	 */
	public function order_expression( $orderby ) {
		$map = array(
			'id'               => 's.id',
			'name'             => 's.name',
			'phone'            => 's.phone',
			'email'            => 's.email',
			'prn'              => 's.prn',
			'group_name'       => 'g.name',
			'level'            => 'g.level',
			'batch'            => 'g.batch',
			'bc_name'          => 's.bc_name',
			'gc_name'          => 's.gc_name',
			'ct_name'          => 's.ct_name',
			'ta_name'          => 's.ta_name',
			'created_at'       => 's.created_at',
			'updated_at'       => 's.updated_at',
			'created_by_name'  => 'c.full_name',
			'updated_by_name'  => 'u.full_name',
		);
		return isset( $map[ $orderby ] ) ? $map[ $orderby ] : 's.id';
	}

	/**
	 * Sadhak list with joined group + user names (same SQL shape as desktop).
	 */
	public function list( $search = '', $group_id = 0, $page = 1, $per_page = 50, $orderby = 'id', $order = 'DESC' ) {
		$db = GPN_DB::instance();
		$s = $db->sadhaks();
		$g = $db->groups();
		$c = $db->users();
		$u = $db->users();

		$conditions = array();
		$params     = array();

		if ( '' !== $search ) {
			$conditions[] = '(s.name LIKE %s OR s.phone LIKE %s OR s.email LIKE %s OR s.prn LIKE %s)';
			$like = '%' . $search . '%';
			$params = array_merge( $params, array( $like, $like, $like, $like ) );
		}
		if ( $group_id ) {
			$conditions[] = 's.group_id = %d';
			$params[]     = (int) $group_id;
		}

		$where = $conditions ? ' WHERE ' . implode( ' AND ', $conditions ) : '';

		if ( ! in_array( $orderby, $this->sortable_columns(), true ) ) {
			$orderby = 'id';
		}
		$order = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';
		$order_expr = $this->order_expression( $orderby );

		$base_sql =
			"SELECT s.id, s.name, s.phone, s.email, s.prn,
			        COALESCE(g.name, '—') AS group_name,
			        COALESCE(g.level, 'Level 1') AS level,
			        COALESCE(g.batch, 'Regular') AS batch,
			        COALESCE(s.bc_name, '—') AS bc_name,
			        COALESCE(s.gc_name, '—') AS gc_name,
			        COALESCE(s.ct_name, '—') AS ct_name,
			        COALESCE(s.ta_name, '—') AS ta_name,
			        COALESCE(s.status, 'Ready') AS status,
			        COALESCE(s.created_at, '—') AS created_at,
			        COALESCE(s.updated_at, '—') AS updated_at,
			        COALESCE(c.full_name, '—') AS created_by_name,
			        COALESCE(u.full_name, '—') AS updated_by_name
			 FROM {$s} s
			 LEFT JOIN {$g} g ON g.id = s.group_id
			 LEFT JOIN {$c} c ON c.id = s.created_by
			 LEFT JOIN {$u} u ON u.id = s.updated_by
			 {$where}
			 ORDER BY {$order_expr} {$order}
			 LIMIT %d OFFSET %d";

		$page      = max( 1, (int) $page );
		$per_page  = max( 1, (int) $per_page );
		$offset    = ( $page - 1 ) * $per_page;
		$query     = $db->db()->prepare( $base_sql, array_merge( $params, array( $per_page, $offset ) ) );
		$rows      = $db->db()->get_results( $query, ARRAY_A );

		// Total (unlimited) count.
		$count_sql = 'SELECT COUNT(*) FROM ' . $s . ' s ' . $where;
		$total     = $params ? $db->db()->get_var( $db->db()->prepare( $count_sql, $params ) ) : $db->db()->get_var( $count_sql );

		return array(
			'records' => $rows,
			'total'   => (int) $total,
			'showing' => count( $rows ),
			'page'    => $page,
			'per_page'=> $per_page,
		);
	}

	/**
	 * Single sadhak for the edit form.
	 */
	public function get( $id ) {
		$db = GPN_DB::instance();
		return $db->db()->get_row(
			$db->db()->prepare(
				'SELECT s.id, s.name, s.phone, s.email, s.prn,
				        COALESCE(g.name, "") AS group_name, s.group_id, s.status
				 FROM ' . $db->sadhaks() . ' s
				 LEFT JOIN ' . $db->groups() . ' g ON g.id = s.group_id
				 WHERE s.id = %d',
				(int) $id
			),
			ARRAY_A
		);
	}

	/**
	 * Duplicate phone detection (mirrors desktop).
	 */
	public function find_by_phone( $phone ) {
		$db    = GPN_DB::instance();
		$plain = ltrim( (string) $phone, '+' );
		return $db->db()->get_row(
			$db->db()->prepare(
				'SELECT id, name FROM ' . $db->sadhaks() . ' WHERE phone = %s OR phone = %s LIMIT 1',
				$phone,
				$plain
			),
			ARRAY_A
		);
	}

	/**
	 * Save (create or update) a sadhak. Returns array('ok'=>bool,'message'=>, 'id'=>, 'status_code'=>).
	 */
	public function save( $user, $data ) {
		$name        = isset( $data['name'] ) ? trim( (string) $data['name'] ) : '';
		$phone       = isset( $data['phone'] ) ? trim( (string) $data['phone'] ) : '';
		$email       = isset( $data['email'] ) ? trim( (string) $data['email'] ) : '';
		$prn         = isset( $data['prn'] ) ? trim( (string) $data['prn'] ) : '';
		$group_id    = isset( $data['group_id'] ) && '' !== $data['group_id'] ? (int) $data['group_id'] : 0;
		$editing_id  = isset( $data['editing_id'] ) && '' !== $data['editing_id'] ? (int) $data['editing_id'] : 0;
		$status      = isset( $data['status'] ) && '' !== $data['status'] ? trim( (string) $data['status'] ) : 'Ready';

		if ( '' === $name || '' === $phone ) {
			return array( 'ok' => false, 'message' => 'Name and Mobile Number are required.', 'status_code' => 400 );
		}

		if ( 0 !== strpos( $phone, '+' ) ) {
			$cc    = isset( $data['country_code'] ) ? trim( (string) $data['country_code'] ) : GPN_Settings::instance()->get( 'default_country', '+977' );
			$phone = $cc . $phone;
		}

		// Duplicate phone check (same as desktop: phone or phone-without-plus).
		$existing = $this->find_by_phone( $phone );
		if ( $existing ) {
			if ( ! $editing_id || (int) $existing['id'] !== $editing_id ) {
				return array(
					'ok' => false,
					'message' => sprintf( 'Phone number already registered to \'%s\'.', $existing['name'] ),
					'status_code' => 409,
				);
			}
		}

		// Edit permission (mirrors can_edit_sadhak).
		if ( $editing_id && ! $this->can_edit( $user['id'], $editing_id ) ) {
			return array(
				'ok' => false,
				'message' => 'Access denied. You can only edit sadhaks in groups where you are assigned as BC, GC, CT, or TA.',
				'status_code' => 403,
			);
		}

		// Copy role holders + group name from the selected group (desktop logic).
		$bc_name = $gc_name = $ct_name = $ta_name = null;
		$group_name = null;
		if ( $group_id ) {
			$info = GPN_Group::instance()->get_with_names( $group_id );
			if ( $info ) {
				$bc_name = $info['bc_name'];
				$gc_name = $info['gc_name'];
				$ct_name = $info['ct_name'];
				$ta_name = $info['ta_name'];
				$group_name = $info['name'];
			}
		}

		$db  = GPN_DB::instance();
		$now = gpn_now();

		if ( $editing_id ) {
			$old = $db->db()->get_row( $db->db()->prepare( 'SELECT * FROM ' . $db->sadhaks() . ' WHERE id = %d', $editing_id ), ARRAY_A );
			$db->db()->update(
				$db->sadhaks(),
				array(
					'name'       => $name,
					'phone'      => $phone,
					'email'      => '' !== $email ? $email : null,
					'prn'        => '' !== $prn ? $prn : null,
					'group_id'   => $group_id ? $group_id : null,
					'bc_name'    => $bc_name,
					'gc_name'    => $gc_name,
					'ct_name'    => $ct_name,
					'ta_name'    => $ta_name,
					'status'     => $status,
					'updated_by' => (int) $user['id'],
					'updated_at' => $now,
				),
				array( 'id' => $editing_id ),
				array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ),
				array( '%d' )
			);
			$sadhak_id = $editing_id;
			$message   = 'Updated: ' . $name;
			$changes   = $this->diff( $old, compact( 'name', 'phone', 'email', 'prn', 'group_id', 'bc_name', 'gc_name', 'ct_name', 'ta_name', 'status' ) );
		} else {
			$db->db()->insert(
				$db->sadhaks(),
				array(
					'name'       => $name,
					'phone'      => $phone,
					'email'      => '' !== $email ? $email : null,
					'prn'        => '' !== $prn ? $prn : null,
					'group_id'   => $group_id ? $group_id : null,
					'bc_name'    => $bc_name,
					'gc_name'    => $gc_name,
					'ct_name'    => $ct_name,
					'ta_name'    => $ta_name,
					'status'     => $status,
					'created_by' => (int) $user['id'],
					'updated_by' => (int) $user['id'],
					'created_at' => $now,
					'updated_at' => $now,
				),
				array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
			);
			$sadhak_id = $db->db()->insert_id;
			$message   = 'Saved: ' . $name;
			$changes   = compact( 'name', 'phone', 'email', 'prn', 'group_id', 'bc_name', 'gc_name', 'ct_name', 'ta_name', 'status' );
		}

		// History snapshot row (desktop sadhak_history).
		$db->db()->insert(
			$db->history(),
			array(
				'sadhak_id'  => (int) $sadhak_id,
				'group_id'   => $group_id ? $group_id : null,
				'group_name' => $group_name,
				'bc_name'    => $bc_name,
				'gc_name'    => $gc_name,
				'ct_name'    => $ct_name,
				'ta_name'    => $ta_name,
				'changed_by' => (int) $user['id'],
				'changed_at' => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		GPN_Log::instance()->add( $editing_id ? 'updated' : 'created', 'sadhak', (int) $sadhak_id, $message, $changes, (int) $user['id'] );
		GPN_Backup::instance()->auto_backup();

		return array(
			'ok' => true,
			'id' => (int) $sadhak_id,
			'message' => $message,
			'status_code' => 200,
		);
	}

	/**
	 * Simple diff between old and new scalar values.
	 */
	private function diff( $old, $new ) {
		$changes = array();
		$keys = array( 'name', 'phone', 'email', 'prn', 'group_id', 'bc_name', 'gc_name', 'ct_name', 'ta_name', 'status' );
		foreach ( $keys as $k ) {
			$ov = isset( $old[ $k ] ) ? (string) $old[ $k ] : '';
			$nv = isset( $new[ $k ] ) ? (string) $new[ $k ] : '';
			if ( $ov !== $nv ) {
				$changes[ $k ] = array( 'old' => $ov, 'new' => $nv );
			}
		}
		return $changes;
	}

	/**
	 * Delete a sadhak (Admin only, mirrored at call sites too).
	 */
	public function delete( $user, $id ) {
		if ( 'Admin' !== $user['role'] ) {
			return array( 'ok' => false, 'message' => 'Only Admin can delete records.', 'status_code' => 403 );
		}
		$db   = GPN_DB::instance();
		$row  = $db->db()->get_row( $db->db()->prepare( 'SELECT id, name FROM ' . $db->sadhaks() . ' WHERE id = %d', (int) $id ), ARRAY_A );
		$db->db()->delete( $db->sadhaks(), array( 'id' => (int) $id ), array( '%d' ) );
		GPN_Log::instance()->add( 'deleted', 'sadhak', (int) $id, 'Deleted sadhak' . ( $row ? ': ' . $row['name'] : '' ), array( 'name' => $row ? $row['name'] : '' ), (int) $user['id'] );
		GPN_Backup::instance()->auto_backup();
		return array( 'ok' => true, 'message' => 'Deleted.', 'status_code' => 200 );
	}

	/**
	 * can_edit_sadhak: Admin always; otherwise must be a BC/GC/CT/TA of the
	 * sadhak's group. (100% port of group_service.can_edit_sadhak)
	 */
	public function can_edit( $user_id, $sadhak_id ) {
		$db = GPN_DB::instance();
		$role = $db->db()->get_var( $db->db()->prepare( 'SELECT role FROM ' . $db->users() . ' WHERE id = %d', (int) $user_id ) );
		if ( 'Admin' === $role ) {
			return true;
		}
		$row = $db->db()->get_var(
			$db->db()->prepare(
				'SELECT 1 FROM ' . $db->sadhaks() . ' s
				 JOIN ' . $db->groups() . ' g ON g.id = s.group_id
				 WHERE s.id = %d AND (g.bc_id = %d OR g.gc_id = %d OR g.ct_id = %d OR g.ta_id = %d)
				 LIMIT 1',
				(int) $sadhak_id,
				(int) $user_id,
				(int) $user_id,
				(int) $user_id,
				(int) $user_id
			)
		);
		return (bool) $row;
	}

	/**
	 * History rows for a sadhak (mirrors get_history).
	 */
	public function history( $sadhak_id ) {
		$db = GPN_DB::instance();
		$h = $db->history();
		$g = $db->groups();
		$u = $db->users();
		return $db->db()->get_results(
			$db->db()->prepare(
				"SELECT h.id,
				        COALESCE(h.group_name, '—') AS group_name,
				        COALESCE(g.level, 'Level 1') AS level,
				        COALESCE(g.batch, 'Regular') AS batch,
				        COALESCE(h.bc_name, '—') AS bc_name,
				        COALESCE(h.gc_name, '—') AS gc_name,
				        COALESCE(h.ct_name, '—') AS ct_name,
				        COALESCE(h.ta_name, '—') AS ta_name,
				        COALESCE(u.full_name, '—') AS changed_by_name,
				        h.changed_at
				 FROM {$h} h
				 LEFT JOIN {$g} g ON g.id = h.group_id
				 LEFT JOIN {$u} u ON u.id = h.changed_by
				 WHERE h.sadhak_id = %d
				 ORDER BY h.changed_at DESC",
				(int) $sadhak_id
			),
			ARRAY_A
		);
	}

	/**
	 * Dashboard statistics.
	 */
	public function stats() {
		$db = GPN_DB::instance();
		$s = $db->sadhaks();
		$g = $db->groups();

		$today_start = date( 'Y-m-d 00:00:00', current_time( 'timestamp' ) );

		$total   = (int) $db->db()->get_var( 'SELECT COUNT(*) FROM ' . $s );
		$ready   = (int) $db->db()->get_var( $db->db()->prepare( 'SELECT COUNT(*) FROM ' . $s . ' WHERE status = %s', 'Ready' ) );
		$today   = (int) $db->db()->get_var( $db->db()->prepare( 'SELECT COUNT(*) FROM ' . $s . ' WHERE created_at >= %s', $today_start ) );
		$groups  = (int) $db->db()->get_var( 'SELECT COUNT(*) FROM ' . $g );
		$active  = (int) $db->db()->get_var( $db->db()->prepare( 'SELECT COUNT(*) FROM ' . $g . ' WHERE status = %s', 'Active' ) );

		$levels = $db->db()->get_results(
			'SELECT COALESCE(g.level, "Level 1") AS label, COUNT(*) AS total
			 FROM ' . $s . ' s LEFT JOIN ' . $g . ' g ON g.id = s.group_id
			 GROUP BY label ORDER BY label',
			ARRAY_A
		);
		$batches = $db->db()->get_results(
			'SELECT COALESCE(g.batch, "Regular") AS label, COUNT(*) AS total
			 FROM ' . $s . ' s LEFT JOIN ' . $g . ' g ON g.id = s.group_id
			 GROUP BY label ORDER BY label',
			ARRAY_A
		);

		return array(
			'total_sadhaks' => $total,
			'ready'         => $ready,
			'today_added'   => $today,
			'groups'        => $groups,
			'active_groups' => $active,
			'levels'        => $levels,
			'batches'       => $batches,
		);
	}
}
