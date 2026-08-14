<?php
/**
 * GPN CRM - AJAX handler.
 *
 * All CRM operations run over admin-ajax.php with:
 *   - WP login + gpn_crm_access capability
 *   - a valid gpn_crm_nonce (CSRF)
 *   - an active CRM session
 *   - CRM role checks for restricted operations (Admin-only).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GPN_Ajax {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		add_action( 'wp_ajax_gpn_crm', array( $this, 'handle' ) );
	}

	public function handle() {
		// Every request needs either a logged-in WP user with the CRM capability
		// or a valid CRM session cookie.
		$has_wp_auth = is_user_logged_in() && current_user_can( GPN_CRM_CAP );
		$crm_user    = GPN_Auth::instance()->current_user();
		if ( ! $has_wp_auth && ! $crm_user ) {
			gpn_json( array( 'error' => 'Not authenticated.' ), 401 );
		}

		// CSRF: nonce check on every request.
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'gpn_crm_nonce' ) ) {
			gpn_json( array( 'error' => 'Invalid security token.' ), 403 );
		}

		$action = isset( $_REQUEST['gpn_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['gpn_action'] ) ) : '';

		if ( 'login' === $action || 'logout' === $action ) {
			$this->handle_auth( $action );
			return;
		}

		// Everything else requires an active CRM session.
		if ( ! $crm_user ) {
			gpn_json( array( 'error' => 'CRM session expired. Please log in again.', 'relogin' => 1 ), 401 );
		}

		$method = 'action_' . str_replace( '-', '_', $action );
		if ( ! method_exists( $this, $method ) ) {
			gpn_json( array( 'error' => 'Unknown action.' ), 400 );
		}
		call_user_func( array( $this, $method ), $crm_user );
	}

	private function handle_auth( $action ) {
		if ( 'login' === $action ) {
			$username = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ) ) : '';
			$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
			if ( '' === $username || '' === $password ) {
				gpn_json( array( 'error' => 'Please enter both username and password.' ), 400 );
			}
			$u = GPN_Auth::instance()->authenticate( $username, $password );
			if ( ! $u ) {
				GPN_Log::instance()->add( 'login_failed', 'system', 0, 'Failed login attempt for username: ' . $username );
				gpn_json( array( 'error' => 'Invalid username or password.' ), 401 );
			}
			GPN_Auth::instance()->login( $u );
			GPN_Log::instance()->add( 'login', 'system', $u['id'], 'Logged in: ' . $u['full_name'], array(), $u['id'] );
			gpn_json( array( 'success' => true, 'user' => $u ) );
		}
		if ( 'logout' === $action ) {
			$u = GPN_Auth::instance()->current_user();
			if ( $u ) {
				GPN_Log::instance()->add( 'logout', 'system', $u['id'], 'Logged out: ' . $u['full_name'], array(), $u['id'] );
			}
			GPN_Auth::instance()->logout();
			gpn_json( array( 'success' => true ) );
		}
	}

	private function require_admin( $user ) {
		if ( 'Admin' !== $user['role'] ) {
			gpn_json( array( 'error' => 'Only Administrator can perform this action.' ), 403 );
		}
	}

	private function action_stats( $user ) {
		gpn_json( array( 'success' => true, 'stats' => GPN_Sadhak::instance()->stats() ) );
	}

	private function action_list_sadhaks( $user ) {
		$search   = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
		$group_id = isset( $_GET['group_id'] ) ? (int) $_GET['group_id'] : 0;
		$page     = isset( $_GET['page'] ) ? (int) $_GET['page'] : 1;
		$per_page = isset( $_GET['per_page'] ) ? (int) $_GET['per_page'] : (int) GPN_Settings::instance()->get( 'per_page', 50 );
		$orderby  = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'id';
		$order    = isset( $_GET['order'] ) ? strtoupper( sanitize_key( wp_unslash( $_GET['order'] ) ) ) : 'DESC';
		$data     = GPN_Sadhak::instance()->list( $search, $group_id, $page, $per_page, $orderby, $order );
		$data['success'] = true;
		gpn_json( $data );
	}

	private function action_get_sadhak( $user ) {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$row = GPN_Sadhak::instance()->get( $id );
		if ( ! $row ) {
			gpn_json( array( 'error' => 'Not found.' ), 404 );
		}
		$row['can_edit'] = GPN_Sadhak::instance()->can_edit( $user['id'], $id );
		gpn_json( array( 'success' => true, 'sadhak' => $row ) );
	}

	private function action_save_sadhak( $user ) {
		$data = array(
			'name'        => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'phone'       => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
			'email'       => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'prn'         => isset( $_POST['prn'] ) ? sanitize_text_field( wp_unslash( $_POST['prn'] ) ) : '',
			'group_id'    => isset( $_POST['group_id'] ) ? (int) $_POST['group_id'] : 0,
			'country_code'=> isset( $_POST['country_code'] ) ? sanitize_text_field( wp_unslash( $_POST['country_code'] ) ) : '',
			'editing_id'  => isset( $_POST['editing_id'] ) ? (int) $_POST['editing_id'] : 0,
			'status'      => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'Ready',
		);
		$result = GPN_Sadhak::instance()->save( $user, $data );
		gpn_json( array(
			'success' => $result['ok'],
			'error'   => $result['ok'] ? '' : $result['message'],
			'message' => $result['ok'] ? $result['message'] : '',
			'id'      => isset( $result['id'] ) ? $result['id'] : 0,
		), $result['status_code'] );
	}

	private function action_delete_sadhak( $user ) {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$result = GPN_Sadhak::instance()->delete( $user, $id );
		gpn_json( array(
			'success' => $result['ok'],
			'error'   => $result['ok'] ? '' : $result['message'],
			'message' => $result['ok'] ? $result['message'] : '',
		), $result['status_code'] );
	}

	private function action_sadhak_history( $user ) {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		gpn_json( array( 'success' => true, 'history' => GPN_Sadhak::instance()->history( $id ) ) );
	}

	private function action_list_groups( $user ) {
		gpn_json( array( 'success' => true, 'groups' => GPN_Group::instance()->list_full() ) );
	}

	private function action_get_group( $user ) {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$row = GPN_Group::instance()->get_with_names( $id );
		if ( ! $row ) {
			gpn_json( array( 'error' => 'Not found.' ), 404 );
		}
		gpn_json( array( 'success' => true, 'group' => $row ) );
	}

	private function action_save_group( $user ) {
		$this->require_admin( $user );
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		if ( '' === $name ) {
			gpn_json( array( 'error' => 'Group name is required.' ), 400 );
		}
		$result = GPN_Group::instance()->save(
			$id,
			$name,
			isset( $_POST['bc_name'] ) ? sanitize_text_field( wp_unslash( $_POST['bc_name'] ) ) : '',
			isset( $_POST['gc_name'] ) ? sanitize_text_field( wp_unslash( $_POST['gc_name'] ) ) : '',
			isset( $_POST['ct_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ct_name'] ) ) : '',
			isset( $_POST['ta_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ta_name'] ) ) : '',
			isset( $_POST['timing'] ) ? sanitize_text_field( wp_unslash( $_POST['timing'] ) ) : '',
			isset( $_POST['zoom_link'] ) ? esc_url_raw( wp_unslash( $_POST['zoom_link'] ) ) : '',
			isset( $_POST['level'] ) ? sanitize_text_field( wp_unslash( $_POST['level'] ) ) : 'Level 1',
			isset( $_POST['batch'] ) ? sanitize_text_field( wp_unslash( $_POST['batch'] ) ) : 'Regular',
			isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'Active'
		);
		gpn_json( array( 'success' => $result['ok'], 'message' => $result['message'], 'id' => $result['id'] ) );
	}

	private function action_delete_group( $user ) {
		$this->require_admin( $user );
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		GPN_Group::instance()->delete( $id );
		gpn_json( array( 'success' => true, 'message' => 'Group deleted.' ) );
	}

	private function action_list_users( $user ) {
		$this->require_admin( $user );
		gpn_json( array( 'success' => true, 'users' => GPN_User::instance()->list() ) );
	}

	private function action_save_user( $user ) {
		$this->require_admin( $user );
		$id        = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$full_name = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
		$username  = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ) ) : '';
		$email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$password  = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$role      = isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : 'BC';
		$active    = isset( $_POST['active'] ) ? (int) $_POST['active'] : 1;
		$result    = GPN_User::instance()->save( $id, $full_name, $username, $email, $password, $role, $active );
		gpn_json( array(
			'success' => $result['ok'],
			'error'   => $result['ok'] ? '' : $result['message'],
			'message' => $result['ok'] ? $result['message'] : '',
			'id'      => isset( $result['id'] ) ? $result['id'] : 0,
		) );
	}

	private function action_delete_user( $user ) {
		$this->require_admin( $user );
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$result = GPN_User::instance()->delete( $id );
		gpn_json( array(
			'success' => $result['ok'],
			'error'   => $result['ok'] ? '' : $result['message'],
			'message' => $result['ok'] ? $result['message'] : '',
		) );
	}

	private function action_prn_search( $user ) {
		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		if ( '' === $term ) {
			gpn_json( array( 'success' => false, 'message' => 'No search term provided.' ), 400 );
		}
		$results = GPN_Prn::instance()->search_prn( $term );
		$best    = GPN_Prn::instance()->select_best( $results, $term );
		if ( ! $best ) {
			gpn_json( array( 'success' => false, 'message' => 'Not Found' ) );
		}
		gpn_json( array(
			'success' => true,
			'name'    => $best['name'],
			'prn'     => $best['prn'],
			'results' => $results,
			'best'    => $best,
		) );
	}

	private function action_prn_by_prn( $user ) {
		$prn = isset( $_GET['prn'] ) ? sanitize_text_field( wp_unslash( $_GET['prn'] ) ) : '';
		$results = GPN_Prn::instance()->search_by_prn( $prn );
		gpn_json( array( 'success' => true, 'results' => $results, 'best' => GPN_Prn::instance()->select_best( $results, $prn ) ) );
	}

	private function action_sync( $user ) {
		$this->require_admin( $user );
		$mode     = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'pull';
		$url      = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		$username = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ) ) : '';
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$token    = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';

		if ( ! $url ) {
			gpn_json( array( 'error' => 'Remote URL is required.' ), 400 );
		}
		$result = ( 'push' === $mode )
			? GPN_Sync::instance()->push_remote( $url, $username, $password, $token )
			: GPN_Sync::instance()->pull_remote( $url, $username, $password, $token );

		gpn_json( array(
			'success' => $result['ok'],
			'error'   => $result['ok'] ? '' : $result['message'],
			'message' => $result['ok'] ? $result['message'] : '',
		), $result['ok'] ? 200 : 400 );
	}

	private function action_settings_get( $user ) {
		$this->require_admin( $user );
		gpn_json( array( 'success' => true, 'settings' => GPN_Settings::instance()->all(), 'sync_token' => GPN_Settings::instance()->sync_token() ) );
	}

	private function action_settings_save( $user ) {
		$this->require_admin( $user );
		$new = array(
			'app_name'          => isset( $_POST['app_name'] ) ? sanitize_text_field( wp_unslash( $_POST['app_name'] ) ) : '',
			'default_country'   => isset( $_POST['default_country'] ) ? sanitize_text_field( wp_unslash( $_POST['default_country'] ) ) : '+977',
			'per_page'          => isset( $_POST['per_page'] ) ? (int) $_POST['per_page'] : 50,
			'prn_remote_search' => isset( $_POST['prn_remote_search'] ) ? 1 : 0,
			'prn_remote_timeout'=> isset( $_POST['prn_remote_timeout'] ) ? (int) $_POST['prn_remote_timeout'] : 3,
			'auto_backup'       => isset( $_POST['auto_backup'] ) ? 1 : 0,
			'keep_backups'      => isset( $_POST['keep_backups'] ) ? (int) $_POST['keep_backups'] : 20,
			'sync_enabled'      => isset( $_POST['sync_enabled'] ) ? 1 : 0,
			'whatsapp_enabled'  => isset( $_POST['whatsapp_enabled'] ) ? 1 : 0,
			'whatsapp_prefix'   => isset( $_POST['whatsapp_prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['whatsapp_prefix'] ) ) : '+977',
			'date_format'       => isset( $_POST['date_format'] ) ? sanitize_text_field( wp_unslash( $_POST['date_format'] ) ) : 'Y-m-d H:i',
			'log_history'       => isset( $_POST['log_history'] ) ? 1 : 0,
		);
		$old = GPN_Settings::instance()->all();
		GPN_Settings::instance()->update_all( $new );
		GPN_Log::instance()->add( 'settings', 'system', 0, 'Settings updated' );
		gpn_json( array( 'success' => true, 'message' => 'Settings saved.' ) );
	}

	private function action_backup_list( $user ) {
		$this->require_admin( $user );
		gpn_json( array( 'success' => true, 'backups' => GPN_Backup::instance()->list_backups() ) );
	}

	private function action_backup_create( $user ) {
		$this->require_admin( $user );
		$name = GPN_Backup::instance()->create( 'manual' );
		if ( ! $name ) {
			gpn_json( array( 'error' => 'Failed to create backup.' ), 500 );
		}
		gpn_json( array( 'success' => true, 'message' => 'Backup created: ' . $name, 'name' => $name ) );
	}

	private function action_backup_delete( $user ) {
		$this->require_admin( $user );
		$name = isset( $_POST['name'] ) ? sanitize_file_name( wp_unslash( $_POST['name'] ) ) : '';
		$result = GPN_Backup::instance()->delete( $name );
		gpn_json( array( 'success' => $result['ok'], 'error' => $result['ok'] ? '' : $result['message'], 'message' => $result['message'] ) );
	}

	private function action_backup_restore( $user ) {
		$this->require_admin( $user );
		$name = isset( $_POST['name'] ) ? sanitize_file_name( wp_unslash( $_POST['name'] ) ) : '';
		$path = trailingslashit( GPN_Backup::instance()->backup_dir() ) . $name;
		$result = GPN_Backup::instance()->restore_file( $path );
		gpn_json( array( 'success' => $result['ok'], 'error' => $result['ok'] ? '' : $result['message'], 'message' => $result['message'] ) );
	}

	private function action_logs( $user ) {
		$this->require_admin( $user );
		$limit  = isset( $_GET['limit'] ) ? (int) $_GET['limit'] : 50;
		$offset = isset( $_GET['offset'] ) ? (int) $_GET['offset'] : 0;
		$logs   = GPN_Log::instance()->get( $limit, $offset );
		foreach ( $logs as &$l ) {
			$l['changes'] = $l['changes'] ? json_decode( $l['changes'], true ) : null;
		}
		gpn_json( array( 'success' => true, 'logs' => $logs, 'total' => GPN_Log::instance()->count() ) );
	}

	private function action_logs_clear( $user ) {
		$this->require_admin( $user );
		GPN_Log::instance()->clear();
		gpn_json( array( 'success' => true, 'message' => 'Logs cleared.' ) );
	}

	private function action_import_preview( $user ) {
		$this->require_admin( $user );
		if ( empty( $_FILES['file'] ) ) {
			gpn_json( array( 'error' => 'No file selected.' ), 400 );
		}
		$name = strtolower( (string) $_FILES['file']['name'] );
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( in_array( $ext, array( 'db', 'sqlite', 'sqlite3' ), true ) ) {
			$res = GPN_Import_Export::instance()->preview_sqlite( $_FILES['file'] );
		} else {
			$res = GPN_Import_Export::instance()->preview_file( $_FILES['file'] );
		}
		if ( ! $res['ok'] ) {
			gpn_json( array( 'error' => $res['message'] ), 400 );
		}
		gpn_json( array( 'success' => true, 'preview' => $res ) );
	}

	private function action_import_run( $user ) {
		$this->require_admin( $user );
		$file    = isset( $_POST['file'] ) ? sanitize_file_name( wp_unslash( $_POST['file'] ) ) : '';
		$raw_map = isset( $_POST['mapping'] ) ? (string) wp_unslash( $_POST['mapping'] ) : '';
		$mapping = $raw_map ? json_decode( $raw_map, true ) : array();
		if ( ! is_array( $mapping ) ) {
			$mapping = array();
		}
		$ie   = GPN_Import_Export::instance();
		$path = $ie->staged_path( $file );
		$ext  = $path ? strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) : '';
		if ( in_array( $ext, array( 'db', 'sqlite', 'sqlite3' ), true ) ) {
			$res = $ie->run_sqlite_import( $file, $mapping );
		} else {
			$res = $ie->run_import_file( $file, $mapping );
		}
		if ( ! $res['ok'] ) {
			gpn_json( array( 'error' => $res['message'] ), 400 );
		}
		gpn_json( array( 'success' => true, 'result' => $res ) );
	}

	private function action_import_discard( $user ) {
		$this->require_admin( $user );
		$file = isset( $_POST['file'] ) ? sanitize_file_name( wp_unslash( $_POST['file'] ) ) : '';
		if ( $file ) {
			GPN_Import_Export::instance()->cleanup_staged( $file );
		}
		gpn_json( array( 'success' => true ) );
	}

	private function action_sadhak_status( $user ) {
		// Toggle a sadhak's Ready status (used by the grid if enabled).
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'Ready';
		$db = GPN_DB::instance();
		$db->db()->update( $db->sadhaks(), array( 'status' => $status, 'updated_at' => gpn_now() ), array( 'id' => $id ) );
		gpn_json( array( 'success' => true ) );
	}
}
GPN_Ajax::instance()->init();
