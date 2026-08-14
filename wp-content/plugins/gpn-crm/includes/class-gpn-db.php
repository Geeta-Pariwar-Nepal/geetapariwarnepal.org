<?php
/**
 * GPN CRM - database layer.
 *
 * Creates and manages the plugin tables (wp_gpn_*), provides table name
 * helpers and low-level safe query wrappers. All queries use $wpdb->prepare.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GPN_DB {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** @var wpdb */
	private $db;

	private function __construct() {
		global $wpdb;
		$this->db = $wpdb;
	}

	public function users() {
		return $this->db->prefix . 'gpn_users';
	}

	public function groups() {
		return $this->db->prefix . 'gpn_groups';
	}

	public function sadhaks() {
		return $this->db->prefix . 'gpn_sadhaks';
	}

	public function history() {
		return $this->db->prefix . 'gpn_history';
	}

	public function logs() {
		return $this->db->prefix . 'gpn_logs';
	}

	public function db() {
		return $this->db;
	}

	/**
	 * Create all tables. Idempotent (uses IF NOT EXISTS).
	 */
	public function create_tables() {
		$db         = $this->db;
		$collation  = $db->get_charset_collate();
		$users_t    = $this->users();
		$groups_t   = $this->groups();
		$sadhak_t   = $this->sadhaks();
		$history_t  = $this->history();
		$logs_t     = $this->logs();

		$sql = array();

		$sql[] = "CREATE TABLE IF NOT EXISTS {$users_t} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			full_name VARCHAR(190) NOT NULL,
			username VARCHAR(190) NOT NULL,
			email VARCHAR(190) DEFAULT NULL,
			password_hash VARCHAR(255) NOT NULL,
			role VARCHAR(20) NOT NULL DEFAULT 'BC',
			active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY username (username),
			KEY email (email)
		) $collation;";

		$sql[] = "CREATE TABLE IF NOT EXISTS {$groups_t} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			bc_id BIGINT UNSIGNED DEFAULT NULL,
			gc_id BIGINT UNSIGNED DEFAULT NULL,
			ct_id BIGINT UNSIGNED DEFAULT NULL,
			ta_id BIGINT UNSIGNED DEFAULT NULL,
			bc_name VARCHAR(190) DEFAULT NULL,
			gc_name VARCHAR(190) DEFAULT NULL,
			ct_name VARCHAR(190) DEFAULT NULL,
			ta_name VARCHAR(190) DEFAULT NULL,
			timing VARCHAR(190) DEFAULT NULL,
			zoom_link TEXT,
			level VARCHAR(30) DEFAULT 'Level 1',
			batch VARCHAR(30) DEFAULT 'Regular',
			status VARCHAR(20) DEFAULT 'Active',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY name (name),
			KEY level (level),
			KEY status (status)
		) $collation;";

		$sql[] = "CREATE TABLE IF NOT EXISTS {$sadhak_t} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			phone VARCHAR(40) NOT NULL,
			email VARCHAR(190) DEFAULT NULL,
			prn VARCHAR(60) DEFAULT NULL,
			group_id BIGINT UNSIGNED DEFAULT NULL,
			bc_name VARCHAR(190) DEFAULT NULL,
			gc_name VARCHAR(190) DEFAULT NULL,
			ct_name VARCHAR(190) DEFAULT NULL,
			ta_name VARCHAR(190) DEFAULT NULL,
			status VARCHAR(20) DEFAULT 'Ready',
			created_by BIGINT UNSIGNED DEFAULT NULL,
			updated_by BIGINT UNSIGNED DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY phone (phone),
			KEY group_id (group_id),
			KEY prn (prn),
			KEY name (name)
		) $collation;";

		$sql[] = "CREATE TABLE IF NOT EXISTS {$history_t} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			sadhak_id BIGINT UNSIGNED NOT NULL,
			group_id BIGINT UNSIGNED DEFAULT NULL,
			group_name VARCHAR(190) DEFAULT NULL,
			bc_name VARCHAR(190) DEFAULT NULL,
			gc_name VARCHAR(190) DEFAULT NULL,
			ct_name VARCHAR(190) DEFAULT NULL,
			ta_name VARCHAR(190) DEFAULT NULL,
			changed_by BIGINT UNSIGNED DEFAULT NULL,
			changed_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY sadhak_id (sadhak_id)
		) $collation;";

		$sql[] = "CREATE TABLE IF NOT EXISTS {$logs_t} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED DEFAULT NULL,
			user_name VARCHAR(190) DEFAULT NULL,
			action VARCHAR(40) NOT NULL,
			entity VARCHAR(40) DEFAULT 'sadhak',
			entity_id BIGINT UNSIGNED DEFAULT NULL,
			description TEXT,
			changes LONGTEXT,
			ip VARCHAR(45) DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY action (action),
			KEY created_at (created_at),
			KEY entity (entity)
		) $collation;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * Whether all plugin tables exist.
	 */
	public function tables_exist() {
		$names = array(
			$this->users(),
			$this->groups(),
			$this->sadhaks(),
			$this->history(),
			$this->logs(),
		);
		$db    = $this->db;
		foreach ( $names as $n ) {
			$table = $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $n ) );
			if ( $table !== $n ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Drop all plugin tables (used by uninstall).
	 */
	public function drop_tables() {
		$db = $this->db;
		// Names come from our own prefix constants (safe identifiers).
		foreach ( array( $this->logs(), $this->history(), $this->sadhaks(), $this->groups(), $this->users() ) as $n ) {
			$db->query( 'DROP TABLE IF EXISTS ' . $n );
		}
	}

	/**
	 * Count rows for a table (used by dashboard / db-info).
	 */
	public function count( $table ) {
		return (int) $this->db->get_var( 'SELECT COUNT(*) FROM ' . $table );
	}
}
