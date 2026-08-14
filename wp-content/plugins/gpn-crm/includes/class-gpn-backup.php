<?php
/**
 * GPN CRM - database backup / restore (JSON).
 *
 * Exports the full CRM (users, groups, sadhaks, history) as a single JSON
 * file, stores it under wp-content/uploads/gpn-crm/backups, and can restore
 * from JSON (keeping a safety backup of the current DB first). Auto-backup
 * runs after writes when enabled in Settings, mirroring the desktop app's
 * automatic "backup to drive" behaviour.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GPN_Backup {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function backup_dir() {
		$dir = trailingslashit( WP_CONTENT_DIR ) . 'uploads/gpn-crm/backups';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return $dir;
	}

	/**
	 * Export all tables as a PHP array.
	 */
	public function export_data() {
		$db = GPN_DB::instance();
		$q  = function ( $table ) use ( $db ) {
			return $db->db()->get_results( 'SELECT * FROM ' . $table, ARRAY_A );
		};
		return array(
			'version' => GPN_CRM_VERSION,
			'exported_at' => gpn_now(),
			'users'   => $q( $db->users() ),
			'groups'  => $q( $db->groups() ),
			'sadhaks' => $q( $db->sadhaks() ),
			'history' => $q( $db->history() ),
		);
	}

	/**
	 * Create a JSON backup file and return its basename (or '' on failure).
	 */
	public function create( $label = 'manual' ) {
		$data = $this->export_data();
		$name = 'gpn-backup-' . gmdate( 'Ymd-His' ) . '-' . substr( (string) wp_generate_password( 4, false ), 0, 4 ) . '.json';
		$file = trailingslashit( $this->backup_dir() ) . $name;
		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		if ( false === file_put_contents( $file, $json ) ) {
			return '';
		}
		GPN_Log::instance()->add( 'backup', 'system', 0, 'Backup created: ' . $name );
		$this->rotate_backups();
		return $name;
	}

	public function list_backups() {
		$dir   = $this->backup_dir();
		$files = glob( trailingslashit( $dir ) . 'gpn-backup-*.json' );
		$out   = array();
		if ( $files ) {
			usort( $files, function ( $a, $b ) {
				return filemtime( $b ) - filemtime( $a );
			} );
			foreach ( $files as $f ) {
				$out[] = array(
					'name'    => basename( $f ),
					'size'    => filesize( $f ),
					'modified' => filemtime( $f ),
				);
			}
		}
		return $out;
	}

	/**
	 * Keep only the newest N backups (settings: keep_backups).
	 */
	public function rotate_backups() {
		$keep = max( 1, (int) GPN_Settings::instance()->get( 'keep_backups', 20 ) );
		$all  = $this->list_backups();
		if ( count( $all ) <= $keep ) {
			return;
		}
		foreach ( array_slice( $all, $keep ) as $b ) {
			$path = trailingslashit( $this->backup_dir() ) . $b['name'];
			if ( is_file( $path ) ) {
				@unlink( $path );
			}
		}
	}

	public function delete( $name ) {
		$name = basename( (string) $name );
		if ( ! preg_match( '/^gpn-backup-.*\.json$/', $name ) ) {
			return array( 'ok' => false, 'message' => 'Invalid backup name.' );
		}
		$path = trailingslashit( $this->backup_dir() ) . $name;
		if ( is_file( $path ) ) {
			@unlink( $path );
			return array( 'ok' => true, 'message' => 'Backup deleted.' );
		}
		return array( 'ok' => false, 'message' => 'Backup not found.' );
	}

	/**
	 * Restore the CRM from a JSON payload array. Backs up current data first.
	 */
	public function restore_data( $data ) {
		if ( ! is_array( $data ) ) {
			return array( 'ok' => false, 'message' => 'Invalid backup data.' );
		}
		$required = array( 'users', 'groups', 'sadhaks', 'history' );
		foreach ( $required as $k ) {
			if ( ! isset( $data[ $k ] ) || ! is_array( $data[ $k ] ) ) {
				return array( 'ok' => false, 'message' => 'Backup is missing the "' . $k . '" table.' );
			}
		}

		// Safety backup of the current database before replacing.
		$this->create( 'pre-restore' );

		$db = GPN_DB::instance();
		$tables = array( $db->users(), $db->groups(), $db->sadhaks(), $db->history() );
		foreach ( $tables as $t ) {
			$db->db()->query( 'TRUNCATE TABLE ' . $t );
		}

		foreach ( array( 'users', 'groups', 'sadhaks', 'history' ) as $table_key ) {
			$table = $db->{ $table_key }();
			foreach ( $data[ $table_key ] as $row ) {
				// Insert rows with their original IDs to preserve relationships.
				// No format array: wpdb defaults to %s for every column, which is
				// safe for mixed string/number columns (MySQL casts implicitly).
				$db->db()->insert( $table, $row );
			}
		}

		GPN_Log::instance()->add( 'restore', 'system', 0, 'Database restored from backup' );
		return array( 'ok' => true, 'message' => 'Database restored successfully.' );
	}

	/**
	 * Restore from a JSON file path.
	 */
	public function restore_file( $path ) {
		if ( ! is_file( $path ) ) {
			return array( 'ok' => false, 'message' => 'Backup file not found.' );
		}
		$json = file_get_contents( $path );
		if ( false === $json ) {
			return array( 'ok' => false, 'message' => 'Could not read backup file.' );
		}
		$data = json_decode( $json, true );
		if ( null === $data ) {
			return array( 'ok' => false, 'message' => 'Backup file is not valid JSON.' );
		}
		return $this->restore_data( $data );
	}

	/**
	 * Auto-backup after writes when enabled (mirrors desktop backup_to_drive).
	 */
	public function auto_backup() {
		if ( ! GPN_Settings::instance()->get( 'auto_backup', 1 ) ) {
			return;
		}
		$this->create( 'auto' );
	}

	/**
	 * Handle the admin-post restore upload.
	 */
	public function handle_restore_upload() {
		if ( empty( $_FILES['gpn_backup_file'] ) ) {
			gpn_crm_flash_notice( 'No file selected.', 'error' );
			return;
		}
		$file = $_FILES['gpn_backup_file'];
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			gpn_crm_flash_notice( 'File upload failed.', 'error' );
			return;
		}
		$name = sanitize_file_name( (string) $file['name'] );
		if ( ! preg_match( '/\.json$/i', $name ) ) {
			gpn_crm_flash_notice( 'Backup file must be a .json file.', 'error' );
			return;
		}
		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			gpn_crm_flash_notice( 'Invalid upload.', 'error' );
			return;
		}
		$res = $this->restore_file( $file['tmp_name'] );
		if ( $res['ok'] ) {
			gpn_crm_flash_notice( $res['message'], 'success' );
		} else {
			gpn_crm_flash_notice( $res['message'], 'error' );
		}
	}

	/**
	 * Download a backup file (admin page GET, nonce checked by caller).
	 */
	public function download( $name ) {
		$name = basename( (string) $name );
		if ( ! preg_match( '/^gpn-backup-.*\.json$/', $name ) ) {
			wp_die( 'Invalid backup name.' );
		}
		$path = trailingslashit( $this->backup_dir() ) . $name;
		if ( ! is_file( $path ) ) {
			wp_die( 'Backup not found.' );
		}
		nocache_headers();
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path );
		exit;
	}
}
