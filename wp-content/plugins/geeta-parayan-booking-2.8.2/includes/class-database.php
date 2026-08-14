<?php
/**
 * Database schema & migration manager.
 *
 * Creates the four specification tables plus an internal audit-log table
 * on activation and keeps them in sync with the current schema version.
 *
 * Tables (prefixed with $wpdb->prefix):
 *   - geeta_users_meta     teacher approval + account status per WP user
 *   - geeta_adhyayas       the 18 Adhyayas (daily + weekly slot types)
 *   - geeta_bookings       user bookings keyed to adhyaya + date + status
 *   - geeta_session_links  Zoom / YouTube links per session
 *   - geeta_overrides      booking-scoped early-booking overrides
 *   - geeta_audit_log      (internal) accountability trail
 *
 * @package Geeta_Pariwar_Parayan_Booking
 */

defined( 'ABSPATH' ) || exit;

class GPPB_Database {

	/**
	 * Singleton instance.
	 *
	 * @var GPPB_Database|null
	 */
	private static $instance = null;

	/**
	 * Global $wpdb.
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Singleton.
	 *
	 * @return GPPB_Database
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
	private function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Table name with prefix.
	 *
	 * @param string $name Suffix (users_meta, adhyayas, bookings, ...).
	 * @return string
	 */
	public function table( $name ) {
		return $this->wpdb->prefix . 'geeta_' . $name;
	}

	/**
	 * List of custom tables managed by the plugin.
	 *
	 * @return array
	 */
	public function tables() {
		return array(
			'users_meta',
			'adhyayas',
			'bookings',
			'session_links',
			'overrides',
			'booking_meta',
			'audit_log',
		);
	}

	/**
	 * Run the full schema install.
	 *
	 * @return void
	 */
	public function install() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$collate = $this->wpdb->get_charset_collate();

		/* ----------------------------------------- geeta_users_meta */
		$table = $this->table( 'users_meta' );
		$sql   = "CREATE TABLE IF NOT EXISTS {$table} (
			user_id BIGINT(20) UNSIGNED NOT NULL,
			teacher_approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
			account_status ENUM('active','blocked') NOT NULL DEFAULT 'active',
			booking_override TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
			unblock_request_reason TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (user_id),
			KEY idx_approval (teacher_approval_status),
			KEY idx_account (account_status)
		) {$collate};";
		dbDelta( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		/* ----------------------------------------- geeta_adhyayas */
		$table = $this->table( 'adhyayas' );
		$sql   = "CREATE TABLE IF NOT EXISTS {$table} (
			id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
			adhyaya_number TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
			title_nepali VARCHAR(255) NOT NULL DEFAULT '',
			slot_type ENUM('daily','weekly') NOT NULL DEFAULT 'daily',
			PRIMARY KEY  (id),
			UNIQUE KEY uq_number_type (adhyaya_number, slot_type)
		) {$collate};";
		dbDelta( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		/* ----------------------------------------- geeta_bookings */
		$table = $this->table( 'bookings' );
		$sql   = "CREATE TABLE IF NOT EXISTS {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			prn VARCHAR(50) NOT NULL DEFAULT '',
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			sadhak_prn VARCHAR(100) NOT NULL DEFAULT '',
			sadhak_name VARCHAR(255) NOT NULL DEFAULT '',
			adhyaya_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
			slot_type ENUM('daily','weekly') NOT NULL DEFAULT 'daily',
			booking_date DATE NOT NULL DEFAULT '0000-00-00',
			booking_status ENUM('confirmed','waitlist_1','waitlist_2','cancelled','completed','deleted') NOT NULL DEFAULT 'confirmed',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY uq_prn (prn),
			KEY idx_user (user_id),
			KEY idx_sadhak_prn (sadhak_prn),
			KEY idx_sadhak_date (sadhak_prn, booking_date),
			KEY idx_slot_date (slot_type, booking_date),
			KEY idx_slot_status (adhyaya_id, booking_date, booking_status),
			KEY idx_created (created_at)
		) {$collate};";
		dbDelta( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		/* ------------------------------------- geeta_session_links */
		$table = $this->table( 'session_links' );
		$sql   = "CREATE TABLE IF NOT EXISTS {$table} (
			id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
			slot_type ENUM('daily','weekly') NOT NULL DEFAULT 'daily',
			session_date DATE NOT NULL DEFAULT '0000-00-00',
			zoom_link TEXT NULL,
			youtube_link TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY uq_type_date (slot_type, session_date)
		) {$collate};";
		dbDelta( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		/* -------------------------------------- geeta_overrides
		 * Booking-scoped early-booking overrides. Each row lets ONE
		 * sadhak book ONE Adhyaya on ONE date while the 1-month
		 * restriction is active. The row is consumed ('used') when that
		 * exact booking is created, so a single approval can never open
		 * up all future Parayan bookings. */
		$table = $this->table( 'overrides' );
		$sql   = "CREATE TABLE IF NOT EXISTS {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			sadhak_prn VARCHAR(100) NOT NULL DEFAULT '',
			sadhak_name VARCHAR(255) NOT NULL DEFAULT '',
			slot_type ENUM('daily','weekly') NOT NULL DEFAULT 'daily',
			adhyaya_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
			adhyaya_number TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
			booking_date DATE NOT NULL DEFAULT '0000-00-00',
			status ENUM('active','used','revoked') NOT NULL DEFAULT 'active',
			booking_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			prn VARCHAR(50) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY idx_user_date (user_id, booking_date, adhyaya_id),
			KEY idx_sadhak_date (sadhak_prn, booking_date, adhyaya_id),
			KEY idx_status (status)
		) {$collate};";
		dbDelta( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		/* ------------------------------------- geeta_booking_meta
		 * Google-Form-style registration details collected on the public
		 * booking form (mobile, district, place, country, email, Sadhak
		 * level fields, etc.), stored keyed to each booking. Keeps the
		 * existing bookings table untouched. */
		$table = $this->table( 'booking_meta' );
		$sql   = "CREATE TABLE IF NOT EXISTS {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			sadhak_prn VARCHAR(100) NOT NULL DEFAULT '',
			meta_key VARCHAR(100) NOT NULL DEFAULT '',
			meta_value TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY uq_booking_key (booking_id, meta_key),
			KEY idx_prn (sadhak_prn)
		) {$collate};";
		dbDelta( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		/* --------------------------------------- geeta_audit_log */
		$table = $this->table( 'audit_log' );
		$sql   = "CREATE TABLE IF NOT EXISTS {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			action VARCHAR(100) NOT NULL DEFAULT '',
			object_type VARCHAR(50) NOT NULL DEFAULT '',
			object_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			description TEXT NULL,
			ip VARCHAR(45) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY idx_object (object_type, object_id),
			KEY idx_action (action),
			KEY idx_created (created_at)
		) {$collate};";
		dbDelta( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$this->seed_adhyayas();
		$this->seed_settings();
		update_option( 'gppb_db_version', GPPB_DB_VERSION );
	}

	/**
	 * Seed the 18 Adhyayas for both slot types.
	 *
	 * @return void
	 */
	private function seed_adhyayas() {
		$table  = $this->table( 'adhyayas' );
		$count  = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $count > 0 ) {
			return;
		}
		foreach ( array( 'daily', 'weekly' ) as $type ) {
			for ( $i = 1; $i <= GPPB_ADHYAYAS_TOTAL; $i++ ) {
				$this->wpdb->insert(
					$table,
					array(
						'adhyaya_number' => $i,
						'title_nepali'   => GPPB_Helpers::adhyaya_title( $i ),
						'slot_type'      => $type,
					)
				); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			}
		}
	}

	/**
	 * Seed default settings in wp_options.
	 *
	 * @return void
	 */
	private function seed_settings() {
		foreach ( GPPB_Helpers::default_settings() as $key => $value ) {
			if ( false === get_option( 'gppb_' . $key ) ) {
				update_option( 'gppb_' . $key, $value );
			}
		}
	}

	/**
	 * Upgrade routine — runs on every init, cheap when up to date.
	 *
	 * @return void
	 */
	public function maybe_upgrade() {
		if ( get_option( 'gppb_db_version' ) !== GPPB_DB_VERSION ) {
			$this->install();
			$this->upgrade_schema();
			update_option( 'gppb_db_version', GPPB_DB_VERSION );
		}
	}

	/**
	 * Incremental schema upgrades for databases created by older versions.
	 *
	 * dbDelta handles new columns/tables, but ENUM value changes are applied
	 * explicitly here (MySQL). SQLite stores these as TEXT so nothing is needed.
	 *
	 * @return void
	 */
	public function upgrade_schema() {
		global $wpdb;

		/* booking_override flag on users_meta (both MySQL and SQLite). */
		if ( ! $this->column_exists( 'users_meta', 'booking_override' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE {$this->table( 'users_meta' )} ADD COLUMN booking_override TINYINT(1) UNSIGNED NOT NULL DEFAULT 0" );
		}

		/* PRN identity columns on bookings (both MySQL and SQLite). */
		foreach ( array( 'sadhak_prn' => 'VARCHAR(100) NOT NULL DEFAULT \'\'', 'sadhak_name' => 'VARCHAR(255) NOT NULL DEFAULT \'\'' ) as $col => $def ) {
			if ( ! $this->column_exists( 'bookings', $col ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( "ALTER TABLE {$this->table( 'bookings' )} ADD COLUMN {$col} {$def}" );
			}
		}

		/* PRN identity columns on overrides (both MySQL and SQLite). */
		foreach ( array( 'sadhak_prn' => 'VARCHAR(100) NOT NULL DEFAULT \'\'', 'sadhak_name' => 'VARCHAR(255) NOT NULL DEFAULT \'\'' ) as $col => $def ) {
			if ( ! $this->column_exists( 'overrides', $col ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( "ALTER TABLE {$this->table( 'overrides' )} ADD COLUMN {$col} {$def}" );
			}
		}

		/* PRN master (existing CRM table or self-created fallback). */
		GPPB_Prn_Store::instance()->ensure_master();

		/* Extend booking_status to include 'deleted' (MySQL ENUM only). */
		if ( defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE ) {
			return;
		}
		$table = $this->table( 'bookings' );
		$col   = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE 'booking_status'" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $col && false === strpos( (string) $col->Type, 'deleted' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE {$table} MODIFY booking_status ENUM('confirmed','waitlist_1','waitlist_2','cancelled','completed','deleted') NOT NULL DEFAULT 'confirmed'" );
		}
	}

	/**
	 * Whether a column exists on one of the plugin tables.
	 *
	 * The SQLite integration's SHOW COLUMNS emulation ignores a trailing
	 * LIKE filter, so the full column list is fetched and checked in PHP —
	 * this works identically on MySQL and SQLite.
	 *
	 * @param string $table  Table suffix.
	 * @param string $column Column name.
	 * @return bool
	 */
	public function column_exists( $table, $column ) {
		global $wpdb;
		$cols = $wpdb->get_results( "SHOW COLUMNS FROM {$this->table( $table )}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( (array) $cols as $c ) {
			if ( isset( $c->Field ) && $column === $c->Field ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Drop all custom tables (used by uninstall).
	 *
	 * @return void
	 */
	public function uninstall() {
		foreach ( $this->tables() as $table ) {
			$this->wpdb->query( 'DROP TABLE IF EXISTS ' . $this->table( $table ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		delete_option( 'gppb_db_version' );
		foreach ( array_keys( GPPB_Helpers::default_settings() ) as $key ) {
			delete_option( 'gppb_' . $key );
		}
	}

	/**
	 * Insert a row, returns insert id.
	 *
	 * @param string $table Table suffix.
	 * @param array  $data  Column => value.
	 * @return int
	 */
	public function insert( $table, $data ) {
		$this->wpdb->insert( $this->table( $table ), $data );
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update rows.
	 *
	 * @param string $table Table suffix.
	 * @param array  $data  Column => value.
	 * @param array  $where Where.
	 * @return int|false
	 */
	public function update( $table, $data, $where ) {
		return $this->wpdb->update( $this->table( $table ), $data, $where );
	}

	/**
	 * Delete rows.
	 *
	 * @param string $table Table suffix.
	 * @param array  $where Where.
	 * @return int|false
	 */
	public function delete( $table, $where ) {
		return $this->wpdb->delete( $this->table( $table ), $where );
	}
}
