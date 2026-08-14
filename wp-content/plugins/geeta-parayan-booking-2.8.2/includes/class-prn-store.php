<?php
/**
 * PRN (Personal Registration Number) master store.
 *
 * Sadhaks are identified by a unique PRN. The PRN master is the existing
 * CRM Sadhak table ({prefix}gp_sadhak) when present — created by the GP
 * Sadhak CRM plugin. This class never duplicates that master; it extends
 * it with the eligibility columns (prn_status, valid_from, valid_until)
 * used by the booking engine, and creates the master table only when the
 * CRM table is absent so the plugin remains self-sufficient.
 *
 * @package Geeta_Pariwar_Parayan_Booking
 */

defined( 'ABSPATH' ) || exit;

class GPPB_Prn_Store {

	/**
	 * Singleton instance.
	 *
	 * @var GPPB_Prn_Store|null
	 */
	private static $instance = null;

	/**
	 * Singleton.
	 *
	 * @return GPPB_Prn_Store
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
	 * Master table name (with prefix).
	 *
	 * @return string
	 */
	public function table() {
		global $wpdb;
		return $wpdb->prefix . 'gp_sadhak';
	}

	/**
	 * Whether the CRM master table exists.
	 *
	 * @return bool
	 */
	public function table_exists() {
		global $wpdb;
		$table = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return (bool) $row;
	}

	/**
	 * Ensure the PRN master table exists and carries the eligibility columns.
	 *
	 * Safe on MySQL and SQLite. Never drops or rewrites existing rows.
	 *
	 * @return void
	 */
	public function ensure_master() {
		global $wpdb;

		if ( ! $this->table_exists() ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$collate = $wpdb->get_charset_collate();
			$table   = $this->table();
			$sql     = "CREATE TABLE IF NOT EXISTS {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(255) NOT NULL,
				phone varchar(50) NOT NULL DEFAULT '',
				email varchar(255) DEFAULT NULL,
				prn varchar(100) DEFAULT NULL,
				group_id bigint(20) unsigned DEFAULT NULL,
				bc_name varchar(255) DEFAULT NULL,
				gc_name varchar(255) DEFAULT NULL,
				ct_name varchar(255) DEFAULT NULL,
				ta_name varchar(255) DEFAULT NULL,
				created_by bigint(20) unsigned DEFAULT NULL,
				updated_by bigint(20) unsigned DEFAULT NULL,
				created_at datetime DEFAULT NULL,
				updated_at datetime DEFAULT NULL,
				prn_status varchar(20) NOT NULL DEFAULT 'allowed',
				valid_from date DEFAULT NULL,
				valid_until date DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY phone (phone),
				KEY idx_prn (prn),
				KEY group_id (group_id)
			) {$collate};";
			dbDelta( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		/* Extend an existing CRM master with the eligibility columns. */
		$this->add_column_if_missing( 'prn_status', "varchar(20) NOT NULL DEFAULT 'allowed'" );
		$this->add_column_if_missing( 'valid_from', 'date DEFAULT NULL' );
		$this->add_column_if_missing( 'valid_until', 'date DEFAULT NULL' );
	}

	/**
	 * Add one column to the master table when missing.
	 *
	 * @param string $column Column name.
	 * @param string $def    Column definition (without the name).
	 * @return void
	 */
	private function add_column_if_missing( $column, $def ) {
		global $wpdb;
		$table = $this->table();
		$cols  = $wpdb->get_results( "SHOW COLUMNS FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
		foreach ( (array) $cols as $c ) {
			if ( isset( $c->Field ) && $column === $c->Field ) {
				return;
			}
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN {$column} {$def}" );
	}

	/**
	 * Normalize a PRN for comparison (trim, uppercase, allow any casing).
	 *
	 * @param string $prn Raw PRN.
	 * @return string
	 */
	public function normalize( $prn ) {
		return strtoupper( trim( (string) $prn ) );
	}

	/**
	 * Look up a Sadhak by PRN (case-insensitive).
	 *
	 * @param string $prn PRN.
	 * @return object|null
	 */
	public function sadhak_by_prn( $prn ) {
		global $wpdb;
		$prn = $this->normalize( $prn );
		if ( '' === $prn ) {
			return null;
		}
		$table = $this->table();
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE UPPER(prn) = %s ORDER BY id ASC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$prn
			)
		);
	}

	/**
	 * Verify a PRN and return its current eligibility for a date.
	 *
	 * @param string $prn  PRN.
	 * @param string $date Y-m-d (defaults to today).
	 * @return array{ok:bool,code:string,message:string,sadhak:?object}
	 */
	public function verify( $prn, $date = '' ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$date = GPPB_Helpers::today();
		}
		$prn = $this->normalize( $prn );
		if ( '' === $prn ) {
			return array( 'ok' => false, 'code' => 'invalid_prn', 'message' => __( 'PRN मान्य छैन। कृपया आफ्नो सही PRN प्रविष्ट गर्नुहोस्।', 'geeta-parayan-booking' ), 'sadhak' => null );
		}

		$sadhak = $this->sadhak_by_prn( $prn );
		if ( ! $sadhak || null === $sadhak->prn || '' === $sadhak->prn ) {
			return array( 'ok' => false, 'code' => 'invalid_prn', 'message' => __( 'PRN मान्य छैन। कृपया आफ्नो सही PRN प्रविष्ट गर्नुहोस्।', 'geeta-parayan-booking' ), 'sadhak' => null );
		}

		$status = isset( $sadhak->prn_status ) ? $sadhak->prn_status : 'allowed';
		if ( 'blocked' === $status ) {
			return array( 'ok' => false, 'code' => 'prn_blocked', 'message' => __( 'यो PRN ब्लक गरिएको छ। कृपया सम्पर्क गर्नुहोस्।', 'geeta-parayan-booking' ), 'sadhak' => $sadhak );
		}

		/* Validity window. */
		$valid_from  = isset( $sadhak->valid_from ) ? (string) $sadhak->valid_from : '';
		$valid_until = isset( $sadhak->valid_until ) ? (string) $sadhak->valid_until : '';
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $valid_from ) && $date < $valid_from ) {
			return array( 'ok' => false, 'code' => 'prn_not_yet_valid', 'message' => __( 'यो PRN अझै सक्रिय भएको छैन।', 'geeta-parayan-booking' ), 'sadhak' => $sadhak );
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $valid_until ) && $date > $valid_until ) {
			return array( 'ok' => false, 'code' => 'prn_expired', 'message' => __( 'यो PRN को अवधि समाप्त भइसकेको छ।', 'geeta-parayan-booking' ), 'sadhak' => $sadhak );
		}

		return array( 'ok' => true, 'code' => 'valid', 'message' => '', 'sadhak' => $sadhak );
	}

	/**
	 * Create or update a PRN master record (admin).
	 *
	 * @param array $data { id, prn, name, phone, email, prn_status, valid_from, valid_until }.
	 * @return array{ok:bool,message:string,id:int}
	 */
	public function save( $data ) {
		global $wpdb;

		$id          = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		$prn         = $this->normalize( isset( $data['prn'] ) ? $data['prn'] : '' );
		$name        = sanitize_text_field( isset( $data['name'] ) ? $data['name'] : '' );
		$phone       = sanitize_text_field( isset( $data['phone'] ) ? $data['phone'] : '' );
		$email       = sanitize_email( isset( $data['email'] ) ? $data['email'] : '' );
		$status      = 'blocked' === ( isset( $data['prn_status'] ) ? $data['prn_status'] : '' ) ? 'blocked' : 'allowed';
		$valid_from  = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( isset( $data['valid_from'] ) ? $data['valid_from'] : '' ) ) ? $data['valid_from'] : null;
		$valid_until = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( isset( $data['valid_until'] ) ? $data['valid_until'] : '' ) ) ? $data['valid_until'] : null;

		if ( '' === $prn || '' === $name ) {
			return array( 'ok' => false, 'message' => __( 'PRN and Sadhak name are required.', 'geeta-parayan-booking' ), 'id' => 0 );
		}

		$this->ensure_master();
		$table = $this->table();

		/* Unique PRN check. */
		$dup = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE UPPER(prn) = %s AND id != %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$prn,
				$id
			)
		);
		if ( $dup ) {
			return array( 'ok' => false, 'message' => __( 'This PRN is already assigned to another Sadhak.', 'geeta-parayan-booking' ), 'id' => 0 );
		}

		$row = array(
			'name'        => $name,
			'phone'       => $phone,
			'email'       => '' !== $email ? $email : null,
			'prn'         => $prn,
			'prn_status'  => $status,
			'valid_from'  => $valid_from,
			'valid_until' => $valid_until,
			'updated_by'  => get_current_user_id(),
			'updated_at'  => current_time( 'mysql' ),
		);

		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			GPPB_Audit_Log::add( null, 'prn_updated', 'prn', $id, sprintf( 'PRN %s updated (%s).', $prn, $name ) );
			return array( 'ok' => true, 'message' => __( 'PRN record updated.', 'geeta-parayan-booking' ), 'id' => $id );
		}

		$row['created_by'] = get_current_user_id();
		$row['created_at'] = current_time( 'mysql' );
		$wpdb->insert( $table, $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$new_id = (int) $wpdb->insert_id;
		GPPB_Audit_Log::add( null, 'prn_created', 'prn', $new_id, sprintf( 'PRN %s created for %s.', $prn, $name ) );
		return array( 'ok' => true, 'message' => __( 'PRN record created.', 'geeta-parayan-booking' ), 'id' => $new_id );
	}

	/**
	 * Search the PRN master.
	 *
	 * @param string $term PRN/name/phone/email.
	 * @param int    $limit Max rows.
	 * @return array
	 */
	public function search( $term, $limit = 50 ) {
		global $wpdb;
		if ( ! $this->table_exists() ) {
			return array();
		}
		$table = $this->table();
		$term  = trim( $term );
		$limit = max( 1, min( 200, absint( $limit ) ) );

		if ( '' === $term ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$like = '%' . $wpdb->esc_like( $term ) . '%';
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE name LIKE %s OR phone LIKE %s OR email LIKE %s OR prn LIKE %s ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$like,
					$like,
					$like,
					$like,
					$limit
				)
			);
		}
		return (array) $rows;
	}

	/**
	 * Mask a phone number (keeps first + last segments).
	 *
	 * @param string $phone Phone.
	 * @return string
	 */
	public function masked_phone( $phone ) {
		return GPPB_Helpers::masked_mobile( (string) $phone );
	}
}
