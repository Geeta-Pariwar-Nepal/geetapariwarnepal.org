<?php
/**
 * GPN CRM - import / export (CSV and Excel).
 *
 * Export streams the full sadhak list as CSV or XLSX with all grid columns.
 * Import accepts CSV or XLSX, maps columns by header name, resolves groups by
 * name, and upserts by phone (matching the desktop duplicate-phone rule).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GPN_Import_Export {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function export_columns() {
		return array(
			'name', 'phone', 'email', 'prn', 'group_name', 'level', 'batch',
			'bc_name', 'gc_name', 'ct_name', 'ta_name', 'status',
			'created_at', 'updated_at', 'created_by_name', 'updated_by_name',
		);
	}

	public function export_headers() {
		return array(
			'Name', 'Mobile', 'Email', 'PRN', 'Group', 'Level', 'Batch',
			'BC', 'GC', 'CT', 'TA', 'Status', 'Created At', 'Updated At',
			'Created By', 'Updated By',
		);
	}

	/**
	 * Rows for export (all sadhaks, same JOIN shape as the grid).
	 */
	public function export_rows() {
		$db = GPN_DB::instance();
		$s = $db->sadhaks();
		$g = $db->groups();
		$c = $db->users();
		$u = $db->users();
		return $db->db()->get_results(
			"SELECT s.name, s.phone, s.email, s.prn,
			        COALESCE(g.name, '') AS group_name,
			        COALESCE(g.level, 'Level 1') AS level,
			        COALESCE(g.batch, 'Regular') AS batch,
			        COALESCE(s.bc_name, '') AS bc_name,
			        COALESCE(s.gc_name, '') AS gc_name,
			        COALESCE(s.ct_name, '') AS ct_name,
			        COALESCE(s.ta_name, '') AS ta_name,
			        COALESCE(s.status, 'Ready') AS status,
			        COALESCE(s.created_at, '') AS created_at,
			        COALESCE(s.updated_at, '') AS updated_at,
			        COALESCE(c.full_name, '') AS created_by_name,
			        COALESCE(u.full_name, '') AS updated_by_name
			 FROM {$s} s
			 LEFT JOIN {$g} g ON g.id = s.group_id
			 LEFT JOIN {$c} c ON c.id = s.created_by
			 LEFT JOIN {$u} u ON u.id = s.updated_by
			 ORDER BY s.id DESC",
			ARRAY_A
		);
	}

	/**
	 * Stream a CSV/XLSX download. Nonce + capability checked by caller.
	 */
	public function download( $format = 'csv' ) {
		$rows = $this->export_rows();
		$rows = array_map( function ( $r ) {
			return array_values( $r );
		}, $rows );

		if ( 'xlsx' === $format ) {
			$data = array_merge( array( $this->export_headers() ), $rows );
			$tmp  = tempnam( sys_get_temp_dir(), 'gpnx' );
			GPN_Xlsx::write( $tmp, $data );
			nocache_headers();
			header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
			header( 'Content-Disposition: attachment; filename="sadhaks-' . gmdate( 'Ymd-His' ) . '.xlsx"' );
			header( 'Content-Length: ' . filesize( $tmp ) );
			readfile( $tmp );
			@unlink( $tmp );
			exit;
		}

		// CSV.
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="sadhaks-' . gmdate( 'Ymd-His' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM for Excel.
		fputcsv( $out, $this->export_headers() );
		foreach ( $rows as $r ) {
			fputcsv( $out, array_map( function ( $v ) {
				return is_string( $v ) ? html_entity_decode( $v, ENT_QUOTES, 'UTF-8' ) : $v;
			}, $r ) );
		}
		fclose( $out );
		exit;
	}

	/**
	 * Directory for staged import files.
	 */
	public function import_dir() {
		$dir = trailingslashit( WP_CONTENT_DIR ) . 'uploads/gpn-crm/import';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return $dir;
	}

	/**
	 * Move an uploaded file into the staging folder. Returns an array with
	 * 'name'/'ext'/'path', or array('ok'=>false,'message'=>...).
	 */
	public function stage_upload( $file ) {
		if ( empty( $file ) || ! isset( $file['tmp_name'], $file['name'] ) ) {
			return array( 'ok' => false, 'message' => 'No file selected.' );
		}
		if ( isset( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return array( 'ok' => false, 'message' => 'File upload failed (error ' . (int) $file['error'] . ').' );
		}
		if ( ! is_uploaded_file( $file['tmp_name'] ) && ! is_file( $file['tmp_name'] ) ) {
			return array( 'ok' => false, 'message' => 'Invalid upload.' );
		}
		$orig = sanitize_file_name( (string) $file['name'] );
		$ext  = strtolower( pathinfo( $orig, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'csv', 'xlsx', 'xls', 'db', 'sqlite', 'sqlite3' ), true ) ) {
			return array( 'ok' => false, 'message' => 'Unsupported file type. Upload a CSV, Excel (.xlsx) or SQLite (.db) file.' );
		}
		$name = 'gpn-import-' . gmdate( 'Ymd-His' ) . '-' . substr( (string) wp_generate_password( 6, false ), 0, 6 ) . '.' . $ext;
		$dest = trailingslashit( $this->import_dir() ) . $name;
		if ( is_uploaded_file( $file['tmp_name'] ) ) {
			$moved = move_uploaded_file( $file['tmp_name'], $dest );
		} else {
			$moved = copy( $file['tmp_name'], $dest );
		}
		if ( ! $moved ) {
			return array( 'ok' => false, 'message' => 'Could not store the uploaded file.' );
		}
		return array( 'ok' => true, 'name' => $name, 'ext' => $ext, 'path' => $dest );
	}

	/**
	 * Resolve a staged filename to its absolute path (path-traversal safe).
	 */
	public function staged_path( $name ) {
		$name = sanitize_file_name( (string) $name );
		if ( ! preg_match( '/^gpn-import-.*\.(csv|xlsx|xls|db|sqlite|sqlite3)$/i', $name ) ) {
			return '';
		}
		$path = trailingslashit( $this->import_dir() ) . $name;
		return is_file( $path ) ? $path : '';
	}

	/**
	 * Parse a staged file into a 2D array of rows. Null on failure.
	 */
	public function parse_file_rows( $path, $ext ) {
		if ( 'csv' === $ext ) {
			return $this->read_csv( $path );
		}
		if ( in_array( $ext, array( 'xlsx', 'xls' ), true ) ) {
			return GPN_Xlsx::read( $path );
		}
		return null;
	}

	/**
	 * Preview a CSV/XLSX upload: returns headers, suggested mapping and a few
	 * sample rows so the user can adjust the column mapping before importing.
	 */
	public function preview_file( $file ) {
		$staged = $this->stage_upload( $file );
		if ( ! $staged['ok'] ) {
			return $staged;
		}
		$rows = $this->parse_file_rows( $staged['path'], $staged['ext'] );
		if ( ! $rows || count( $rows ) < 1 ) {
			return array( 'ok' => false, 'message' => 'The file could not be read or has no content.', 'file' => $staged['name'] );
		}
		$headers = array_map( 'trim', (array) $rows[0] );
		return array(
			'ok'         => true,
			'file'       => $staged['name'],
			'ext'        => $staged['ext'],
			'headers'    => $headers,
			'mapping'    => $this->suggest_mapping( $headers ),
			'samples'    => array_slice( $rows, 1, 5 ),
			'total_rows' => max( 0, count( $rows ) - 1 ),
		);
	}

	/**
	 * Run an import for a staged CSV/XLSX file using an explicit mapping.
	 */
	public function run_import_file( $file, $mapping ) {
		$path = $this->staged_path( $file );
		if ( ! $path ) {
			return array( 'ok' => false, 'message' => 'Staged file not found. Please preview the file again.' );
		}
		$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		$rows = $this->parse_file_rows( $path, $ext );
		if ( ! $rows || count( $rows ) < 2 ) {
			return array( 'ok' => false, 'message' => 'The file has no data rows.' );
		}
		$mapping = is_array( $mapping ) ? $mapping : array();
		$result  = $this->import_rows( $rows, $mapping );
		$this->cleanup_staged( $file );
		return $result;
	}

	/**
	 * Remove a staged import file (called after import completes).
	 */
	public function cleanup_staged( $name ) {
		$path = $this->staged_path( $name );
		if ( $path && is_file( $path ) ) {
			@unlink( $path );
		}
	}

	/**
	 * Find the sadhak table inside a SQLite database (desktop CRM .db file).
	 */
	private function sqlite_table( $pdo ) {
		$tables = $pdo->query( "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'" )->fetchAll( PDO::FETCH_COLUMN );
		// Prefer an exact match, then a table with phone + name columns.
		$preferred = '';
		$fallback  = '';
		foreach ( $tables as $t ) {
			if ( in_array( strtolower( $t ), array( 'sadhak', 'sadhaks', 'participants' ), true ) ) {
				$preferred = $t;
				break;
			}
		}
		foreach ( $tables as $t ) {
			$cols = $this->sqlite_columns( $pdo, $t );
			if ( isset( $cols['phone'], $cols['name'] ) ) {
				$fallback = $t;
				break;
			}
		}
		return $preferred ? $preferred : $fallback;
	}

	private function sqlite_columns( $pdo, $table ) {
		$cols = array();
		foreach ( $pdo->query( 'PRAGMA table_info(' . str_replace( '"', '""', $table ) . ')' ) as $c ) {
			$cols[ strtolower( $c['name'] ) ] = $c['name'];
		}
		return $cols;
	}

	private function sqlite_row_to_mapping( array $cols ) {
		// SQLite column -> canonical target.
		$map = array();
		$rule = array(
			'name'            => 'name',
			'full_name'       => 'name',
			'phone'           => 'phone',
			'mobile'          => 'phone',
			'mobile_number'   => 'phone',
			'email'           => 'email',
			'prn'             => 'prn',
			'group_name'      => 'group_name',
			'group'           => 'group_name',
			'level'           => 'level',
			'batch'           => 'batch',
			'bc_name'         => 'bc_name',
			'gc_name'         => 'gc_name',
			'ct_name'         => 'ct_name',
			'ta_name'         => 'ta_name',
			'status'          => 'status',
		);
		foreach ( $cols as $lc => $orig ) {
			$map[ $lc ] = isset( $rule[ $lc ] ) ? $rule[ $lc ] : 'ignore';
		}
		return $map;
	}

	/**
	 * Preview a SQLite (.db) upload.
	 */
	public function preview_sqlite( $file ) {
		if ( ! extension_loaded( 'pdo_sqlite' ) ) {
			return array( 'ok' => false, 'message' => 'SQLite support (PDO pdo_sqlite) is not enabled on this server.' );
		}
		$staged = $this->stage_upload( $file );
		if ( ! $staged['ok'] ) {
			return $staged;
		}
		try {
			$pdo = new PDO( 'sqlite:' . $staged['path'] );
			$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
			$table = $this->sqlite_table( $pdo );
			if ( ! $table ) {
				$pdo = null;
				$this->cleanup_staged( $staged['name'] );
				return array( 'ok' => false, 'message' => 'No table with Name + Phone columns was found in this database.', 'file' => $staged['name'] );
			}
			$cols = $this->sqlite_columns( $pdo, $table );
			$total = (int) $pdo->query( 'SELECT COUNT(*) FROM ' . str_replace( '"', '""', $table ) )->fetchColumn();
			$samples = $pdo->query( 'SELECT * FROM ' . str_replace( '"', '""', $table ) . ' LIMIT 5' )->fetchAll( PDO::FETCH_ASSOC );
			$pdo = null;
			$lc_map = $this->sqlite_row_to_mapping( $cols );
			$pos_map = array();
			$p = 0;
			foreach ( $cols as $lc => $orig ) {
				$pos_map[ $p ] = $lc_map[ $lc ];
				$p++;
			}
			return array(
				'ok'         => true,
				'file'       => $staged['name'],
				'ext'        => $staged['ext'],
				'table'      => $table,
				'columns'    => array_values( $cols ),
				'mapping'    => $pos_map,
				'samples'    => $samples,
				'total_rows' => $total,
			);
		} catch ( Exception $e ) {
			$this->cleanup_staged( $staged['name'] );
			return array( 'ok' => false, 'message' => 'Could not open the SQLite database: ' . $e->getMessage() );
		}
	}

	/**
	 * Import all sadhaks from a staged SQLite database (upsert by phone).
	 */
	public function run_sqlite_import( $file, $mapping = null ) {
		if ( ! extension_loaded( 'pdo_sqlite' ) ) {
			return array( 'ok' => false, 'message' => 'SQLite support (PDO pdo_sqlite) is not enabled on this server.' );
		}
		$path = $this->staged_path( $file );
		if ( ! $path ) {
			return array( 'ok' => false, 'message' => 'Staged database not found. Please preview the file again.' );
		}
		try {
			$pdo = new PDO( 'sqlite:' . $path );
			$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
			$table = $this->sqlite_table( $pdo );
			if ( ! $table ) {
				return array( 'ok' => false, 'message' => 'No sadhak table was found in this database.' );
			}
			$cols      = $this->sqlite_columns( $pdo, $table );
			$lc_default = $this->sqlite_row_to_mapping( $cols );

			// Resolve group_id -> name through the groups table if present.
			$group_names = array();
			$groups_t = null;
			foreach ( $pdo->query( "SELECT name FROM sqlite_master WHERE type='table'" )->fetchAll( PDO::FETCH_COLUMN ) as $t ) {
				if ( 'groups' === strtolower( $t ) ) {
					$groups_t = $t;
					break;
				}
			}
			if ( $groups_t ) {
				$gcols = $this->sqlite_columns( $pdo, $groups_t );
				$gname = isset( $gcols['name'] ) ? $gcols['name'] : 'name';
				$gid   = isset( $gcols['id'] ) ? $gcols['id'] : 'id';
				foreach ( $pdo->query( 'SELECT ' . $gid . ', ' . $gname . ' FROM ' . str_replace( '"', '""', $groups_t ) ) as $g ) {
					$group_names[ (string) $g[ $gid ] ] = (string) $g[ $gname ];
				}
			}

			$rows  = $pdo->query( 'SELECT * FROM ' . str_replace( '"', '""', $table ) )->fetchAll( PDO::FETCH_ASSOC );
			$pdo   = null;

			// Build positional rows for import_rows(). Always resolve a
			// group_id column to group name; other columns use the user's
			// mapping (positional or lowercase-column keyed) else defaults.
			$header    = array_values( $cols );
			$gid_pos   = null;
			$mapping_idx = array();
			$pos = 0;
			foreach ( $cols as $lc => $orig ) {
				if ( 'group_id' === $lc ) {
					$gid_pos = $pos;
					$mapping_idx[ $pos ] = 'group_name';
				} else {
					$target = 'ignore';
					if ( is_array( $mapping ) && array_key_exists( $pos, $mapping ) && 'ignore' !== $mapping[ $pos ] ) {
						$target = $mapping[ $pos ];
					} elseif ( is_array( $mapping ) && array_key_exists( $lc, $mapping ) && 'ignore' !== $mapping[ $lc ] ) {
						$target = $mapping[ $lc ];
					} elseif ( isset( $lc_default[ $lc ] ) ) {
						$target = $lc_default[ $lc ];
					}
					$mapping_idx[ $pos ] = $target;
				}
				$pos++;
			}

			$data_rows = array();
			foreach ( $rows as $r ) {
				$line = array();
				foreach ( $cols as $orig ) {
					$line[] = isset( $r[ $orig ] ) ? $r[ $orig ] : '';
				}
				if ( null !== $gid_pos ) {
					$gid = isset( $r['group_id'] ) ? (string) $r['group_id'] : '';
					$line[ $gid_pos ] = isset( $group_names[ $gid ] ) ? $group_names[ $gid ] : '';
				}
				$data_rows[] = $line;
			}

			$all = array_merge( array( $header ), $data_rows );
			$result = $this->import_rows( $all, $mapping_idx );

			// Enrich log header with the source table.
			if ( $result['ok'] ) {
				$result['log'] = str_replace( 'GPN CRM Import Report' . PHP_EOL, 'GPN CRM Import Report (SQLite: ' . $table . ')' . PHP_EOL, $result['log'] );
			}
			$this->cleanup_staged( $file );
			return $result;
		} catch ( Exception $e ) {
			$this->cleanup_staged( $file );
			return array( 'ok' => false, 'message' => 'Could not import the SQLite database: ' . $e->getMessage() );
		}
	}

	/**
	 * Handle the admin-post import upload.
	 */
	public function handle_upload() {
		if ( empty( $_FILES['gpn_import_file'] ) ) {
			gpn_crm_flash_notice( 'No file selected.', 'error' );
			return;
		}
		$file = $_FILES['gpn_import_file'];
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			gpn_crm_flash_notice( 'File upload failed.', 'error' );
			return;
		}
		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			gpn_crm_flash_notice( 'Invalid upload.', 'error' );
			return;
		}
		$name = strtolower( (string) $file['name'] );
		if ( preg_match( '/\.csv$/i', $name ) ) {
			$rows = $this->read_csv( $file['tmp_name'] );
		} elseif ( preg_match( '/\.(xlsx|xls)$/i', $name ) ) {
			$rows = GPN_Xlsx::read( $file['tmp_name'] );
		} else {
			gpn_crm_flash_notice( 'Please upload a CSV or Excel (.xlsx) file.', 'error' );
			return;
		}

		if ( ! $rows || count( $rows ) < 2 ) {
			gpn_crm_flash_notice( 'The file is empty or has no data rows.', 'error' );
			return;
		}

		$result = $this->import_rows( $rows );
		if ( $result['ok'] ) {
			gpn_crm_flash_notice( 'Import complete: ' . $result['added'] . ' added, ' . $result['updated'] . ' updated, ' . $result['skipped'] . ' skipped.', 'success' );
		} else {
			gpn_crm_flash_notice( $result['message'], 'error' );
		}
	}

	/**
	 * Parse CSV (handles BOM + \r\n).
	 */
	private function read_csv( $path ) {
		$rows = array();
		$fh   = fopen( $path, 'r' );
		if ( ! $fh ) {
			return array();
		}
		while ( ( $row = fgetcsv( $fh, 0, ',', '"', '\\' ) ) !== false ) {
			// Strip UTF-8 BOM from the first cell.
			if ( ! empty( $row ) ) {
				$row[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $row[0] );
			}
			$rows[] = array_map( 'trim', $row );
		}
		fclose( $fh );
		return $rows;
	}

	/**
	 * Map headers to canonical keys. Accepts many reasonable synonyms so
	 * existing CRM databases with different column names can be imported
	 * without reformatting. Unknown columns are simply ignored.
	 */
	private function normalize_headers( array $headers ) {
		$aliases = array(
			// Name
			'name' => 'name', 'full name' => 'name', 'sadhak name' => 'name',
			'participant name' => 'name', 'student name' => 'name', 'devotee name' => 'name',
			'name of sadhak' => 'name', 'names' => 'name',
			// Phone
			'phone' => 'phone', 'mobile' => 'phone', 'mobile number' => 'phone',
			'mobile no' => 'phone', 'mobile no.' => 'phone', 'phone number' => 'phone',
			'phone no' => 'phone', 'phone no.' => 'phone', 'ph no' => 'phone',
			'contact' => 'phone', 'contact number' => 'phone', 'contact no' => 'phone',
			'contact no.' => 'phone', 'number' => 'phone', 'tel' => 'phone',
			'telephone' => 'phone', 'cell' => 'phone', 'cell number' => 'phone',
			'mobile #' => 'phone', 'phone #' => 'phone', 'ph' => 'phone',
			// Email
			'email' => 'email', 'e-mail' => 'email', 'email address' => 'email', 'mail' => 'email',
			// PRN
			'prn' => 'prn', 'registration no' => 'prn', 'registration number' => 'prn',
			'reg no' => 'prn', 'reg no.' => 'prn', 'reg number' => 'prn',
			'registration' => 'prn', 'prn no' => 'prn', 'participant registration' => 'prn',
			'id' => 'prn', 'reference no' => 'prn',
			// Group
			'group' => 'group_name', 'group name' => 'group_name', 'sadhak group' => 'group_name',
			'class' => 'group_name', 'class name' => 'group_name', 'gita class' => 'group_name',
			// Level
			'level' => 'level', 'course level' => 'level', 'gita level' => 'level',
			'level name' => 'level', 'course' => 'level',
			// Batch
			'batch' => 'batch', 'batch name' => 'batch', 'batch no' => 'batch',
			'batch number' => 'batch', 'cohort' => 'batch', 'batch group' => 'batch',
			// BC / GC / CT / TA
			'bc' => 'bc_name', 'bc name' => 'bc_name', 'batch coordinator' => 'bc_name',
			'gc' => 'gc_name', 'gc name' => 'gc_name', 'group coordinator' => 'gc_name',
			'ct' => 'ct_name', 'ct name' => 'ct_name', 'co-teacher' => 'ct_name',
			'ta' => 'ta_name', 'ta name' => 'ta_name', 'teaching assistant' => 'ta_name',
			// Status
			'status' => 'status', 'active status' => 'status', 'state' => 'status',
			'registration status' => 'status', 'sadhak status' => 'status',
			// Timestamps (imported for reference only)
			'created at' => 'created_at', 'updated at' => 'updated_at',
			'created by' => 'created_by_name', 'updated by' => 'updated_by_name',
		);
		$map = array();
		foreach ( $headers as $i => $h ) {
			$key = strtolower( trim( (string) $h ) );
			if ( isset( $aliases[ $key ] ) ) {
				$map[ $aliases[ $key ] ] = $i;
			}
		}
		return $map;
	}

	/**
	 * All valid target fields for column mapping (import UI).
	 */
	public function mapping_targets() {
		return array( 'ignore', 'name', 'phone', 'email', 'prn', 'group_name', 'level', 'batch', 'bc_name', 'gc_name', 'ct_name', 'ta_name', 'status' );
	}

	/**
	 * Suggest a mapping (colIndex => target) for a set of headers.
	 */
	public function suggest_mapping( array $headers ) {
		$suggested = $this->normalize_headers( $headers );
		// Reverse: header index -> target.
		$map = array();
		foreach ( $headers as $i => $h ) {
			$map[ $i ] = 'ignore';
		}
		foreach ( $suggested as $target => $i ) {
			$map[ $i ] = $target;
		}
		return $map;
	}

	/**
	 * Import rows with an explicit column mapping (colIndex => target).
	 * When $mapping is null the column headers are auto-detected. Upserts by
	 * phone (existing numbers updated, new numbers added, never duplicated).
	 * Returns counts plus a plain-text log for the import report.
	 */
	public function import_rows( array $rows, $mapping = null ) {
		$headers = array_map( 'trim', (array) array_shift( $rows ) );

		if ( null === $mapping ) {
			$detected = $this->normalize_headers( $headers );
			$map      = array();
			foreach ( $headers as $i => $h ) {
				$map[ $i ] = 'ignore';
			}
			foreach ( $detected as $target => $i ) {
				$map[ $i ] = $target;
			}
			$mapping = $map;
		} else {
			// Keep only valid targets.
			$valid = array_flip( $this->mapping_targets() );
			foreach ( $mapping as $i => $target ) {
				$target = strtolower( trim( (string) $target ) );
				if ( ! isset( $valid[ $target ] ) ) {
					$mapping[ $i ] = 'ignore';
				}
			}
		}

		// Reverse mapping: target => colIndex.
		$by_target = array();
		foreach ( $mapping as $i => $target ) {
			if ( 'ignore' !== $target ) {
				$by_target[ $target ] = (int) $i;
			}
		}

		if ( ! isset( $by_target['name'], $by_target['phone'] ) ) {
			return array( 'ok' => false, 'message' => 'The mapping must map a column to Name and a column to Phone/Mobile.' );
		}

		// Group name -> id lookup.
		$groups = array();
		foreach ( GPN_Group::instance()->get_all() as $g ) {
			$groups[ strtolower( trim( $g['name'] ) ) ] = (int) $g['id'];
		}

		$added    = 0;
		$updated  = 0;
		$skipped  = 0;
		$errors   = 0;
		$details  = array();
		$db       = GPN_DB::instance();
		$user     = GPN_Auth::instance()->current_user();
		$user_id  = $user ? (int) $user['id'] : 0;
		$now      = gpn_now();

		foreach ( $rows as $rindex => $row ) {
			$get = function ( $key ) use ( $by_target, $row ) {
				if ( ! isset( $by_target[ $key ] ) || ! isset( $row[ $by_target[ $key ] ] ) ) {
					return '';
				}
				return trim( (string) $row[ $by_target[ $key ] ] );
			};

			$line   = $rindex + 2; // +2: header + 1-based row.
			$name   = $get( 'name' );
			$phone  = $get( 'phone' );
			if ( '' === $name || '' === $phone ) {
				$skipped++;
				$details[] = sprintf( 'Row %d: skipped (missing Name or Phone).', $line );
				continue;
			}

			$email   = $get( 'email' );
			$prn     = $get( 'prn' );
			$level   = $get( 'level' );
			$batch   = $get( 'batch' );
			$status  = $get( 'status' );
			$bc      = $get( 'bc_name' );
			$gc      = $get( 'gc_name' );
			$ct      = $get( 'ct_name' );
			$ta      = $get( 'ta_name' );

			if ( 0 !== strpos( $phone, '+' ) && preg_match( '/^\d{9,}$/', $phone ) ) {
				$phone = GPN_Settings::instance()->get( 'default_country', '+977' ) . $phone;
			}

			$group_name = $get( 'group_name' );
			$group_id   = 0;
			if ( '' !== $group_name && isset( $groups[ strtolower( $group_name ) ] ) ) {
				$group_id = $groups[ strtolower( $group_name ) ];
			}

			// Resolve role holders from the group if not supplied.
			if ( '' === $bc && '' === $gc && '' === $ct && '' === $ta && $group_id ) {
				$info = GPN_Group::instance()->get_with_names( $group_id );
				if ( $info ) {
					$bc = $info['bc_name'];
					$gc = $info['gc_name'];
					$ct = $info['ct_name'];
					$ta = $info['ta_name'];
				}
			}

			if ( '' === $status ) {
				$status = 'Ready';
			}

			$existing = GPN_Sadhak::instance()->find_by_phone( $phone );
			$data     = array(
				'name'        => $name,
				'phone'       => $phone,
				'email'       => $email,
				'prn'         => $prn,
				'group_id'    => $group_id,
				'bc_name'     => $bc,
				'gc_name'     => $gc,
				'ct_name'     => $ct,
				'ta_name'     => $ta,
				'status'      => $status,
			);

			if ( $existing ) {
				$data['editing_id'] = (int) $existing['id'];
			}
			$saved = GPN_Sadhak::instance()->save( array( 'id' => $user_id, 'role' => 'Admin' ), $data );
			if ( $saved['ok'] ) {
				if ( $existing ) {
					$updated++;
					$details[] = sprintf( 'Row %d: updated %s (%s).', $line, $name, $phone );
				} else {
					$added++;
					$details[] = sprintf( 'Row %d: added %s (%s).', $line, $name, $phone );
				}
			} else {
				$errors++;
				$details[] = sprintf( 'Row %d: ERROR - %s', $line, isset( $saved['message'] ) ? $saved['message'] : 'save failed' );
			}
		}

		$log  = 'GPN CRM Import Report' . PHP_EOL;
		$log .= str_repeat( '=', 40 ) . PHP_EOL;
		$log .= 'Date:      ' . gpn_now() . PHP_EOL;
		$log .= 'Imported:  ' . $added . PHP_EOL;
		$log .= 'Updated:   ' . $updated . PHP_EOL;
		$log .= 'Skipped:   ' . $skipped . PHP_EOL;
		$log .= 'Errors:    ' . $errors . PHP_EOL;
		$log .= str_repeat( '=', 40 ) . PHP_EOL;
		$log .= 'Details:' . PHP_EOL;
		foreach ( $details as $d ) {
			$log .= '  ' . $d . PHP_EOL;
		}

		GPN_Log::instance()->add( 'import', 'sadhak', 0, 'Import: ' . $added . ' added, ' . $updated . ' updated, ' . $skipped . ' skipped, ' . $errors . ' errors', array( 'added' => $added, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors ), $user_id );

		return array(
			'ok'      => true,
			'added'   => $added,
			'updated' => $updated,
			'skipped' => $skipped,
			'errors'  => $errors,
			'log'     => $log,
		);
	}
}
