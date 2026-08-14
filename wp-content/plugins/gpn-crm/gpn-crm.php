<?php
/**
 * Plugin Name:       Geeta Pariwar Nepal CRM
 * Plugin URI:        https://geetapariwarnepal.org
 * Description:       Complete Sadhak (devotee) management CRM for Geeta Pariwar Nepal – 100% migration of the desktop application into WordPress. Manages sadhaks, groups, PRN auto-search, history, sync, import/export, backup, roles and settings. Dark theme with blue header, AJAX-powered, fully responsive.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      8.0
 * Author:            Geeta Pariwar Nepal
 * Author URI:        https://geetapariwarnepal.org
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gpn-crm
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GPN_CRM_VERSION', '1.1.2' );
define( 'GPN_CRM_FILE', __FILE__ );
define( 'GPN_CRM_DIR', plugin_dir_path( __FILE__ ) );
define( 'GPN_CRM_URL', plugin_dir_url( __FILE__ ) );
define( 'GPN_CRM_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Capabilities granted to WordPress roles.
 */
define( 'GPN_CRM_CAP', 'gpn_crm_access' );
define( 'GPN_CRM_CAP_ADMIN', 'gpn_crm_admin' );

require_once GPN_CRM_DIR . 'includes/functions.php';
require_once GPN_CRM_DIR . 'includes/class-gpn-db.php';
require_once GPN_CRM_DIR . 'includes/class-gpn-log.php';
require_once GPN_CRM_DIR . 'includes/class-gpn-auth.php';
require_once GPN_CRM_DIR . 'includes/class-gpn-settings.php';
require_once GPN_CRM_DIR . 'includes/class-gpn-prn.php';
require_once GPN_CRM_DIR . 'includes/class-gpn-group.php';
require_once GPN_CRM_DIR . 'includes/class-gpn-sadhak.php';
require_once GPN_CRM_DIR . 'includes/class-gpn-user.php';
require_once GPN_CRM_DIR . 'includes/class-gpn-backup.php';
require_once GPN_CRM_DIR . 'includes/class-gpn-xlsx.php';
require_once GPN_CRM_DIR . 'includes/class-gpn-import-export.php';
require_once GPN_CRM_DIR . 'includes/class-gpn-sync.php';
require_once GPN_CRM_DIR . 'api/class-gpn-rest.php';
require_once GPN_CRM_DIR . 'includes/class-gpn-ajax.php';

register_activation_hook( __FILE__, 'gpn_crm_activate' );
register_deactivation_hook( __FILE__, 'gpn_crm_deactivate' );

/**
 * Activation: create tables, seed admin, grant caps, defaults.
 */
function gpn_crm_activate() {
	require_once GPN_CRM_DIR . 'install.php';
	gpn_crm_install();
}

/**
 * Upgrade routine: grant caps, seed admin, ensure settings on version bump.
 */
function gpn_crm_upgrade() {
	$old_version = get_option( 'gpn_crm_version', '0.0.0' );
	if ( version_compare( $old_version, GPN_CRM_VERSION, '>=' ) ) {
		return;
	}
	require_once GPN_CRM_DIR . 'install.php';
	gpn_crm_install();
	update_option( 'gpn_crm_version', GPN_CRM_VERSION );
}
add_action( 'admin_init', 'gpn_crm_upgrade' );

/**
 * Deactivation: clear rewrite rules / scheduled events. Data is kept.
 */
function gpn_crm_deactivate() {
	wp_clear_scheduled_hook( 'gpn_crm_auto_cleanup' );
	flush_rewrite_rules();
}

/**
 * Safety net: ensure tables exist (e.g. after manual drops or restore).
 * Also grants capabilities and seeds admin on upgrades.
 */
function gpn_crm_maybe_install() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$needs_upgrade = get_option( 'gpn_crm_version', '0.0.0' ) !== GPN_CRM_VERSION;
	if ( ! GPN_DB::instance()->tables_exist() || $needs_upgrade ) {
		gpn_crm_activate();
	}
}
add_action( 'admin_init', 'gpn_crm_maybe_install' );

/**
 * Register the "Geeta CRM" menu + submenus.
 */
function gpn_crm_admin_menu() {
	$icon = 'data:image/svg+xml;base64,' . base64_encode(
		'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><text x="0" y="20" font-size="20" font-family="serif" fill="%23fff">ॐ</text></svg>'
	);

	add_menu_page(
		__( 'Geeta CRM', 'gpn-crm' ),
		__( 'Geeta CRM', 'gpn-crm' ),
		'manage_options',
		'gpn-crm-dashboard',
		'gpn_crm_render_page',
		$icon,
		26
	);

	$menus = array(
		'gpn-crm-dashboard'  => __( 'Dashboard', 'gpn-crm' ),
		'gpn-crm-sadhaks'    => __( 'Sadhaks', 'gpn-crm' ),
		'gpn-crm-add'        => __( 'Add Sadhak', 'gpn-crm' ),
		'gpn-crm-groups'     => __( 'Groups', 'gpn-crm' ),
		'gpn-crm-sync'       => __( 'Sync', 'gpn-crm' ),
		'gpn-crm-import'     => __( 'Import', 'gpn-crm' ),
		'gpn-crm-export'     => __( 'Export', 'gpn-crm' ),
		'gpn-crm-settings'   => __( 'Settings', 'gpn-crm' ),
		'gpn-crm-users'      => __( 'User Management', 'gpn-crm' ),
		'gpn-crm-backup'     => __( 'Backup', 'gpn-crm' ),
	);

	foreach ( $menus as $slug => $label ) {
		$cap = 'manage_options';
		add_submenu_page(
			'gpn-crm-dashboard',
			$label,
			$label,
			$cap,
			$slug,
			'gpn_crm_render_page'
		);
	}
}
add_action( 'admin_menu', 'gpn_crm_admin_menu' );

/**
 * Route an admin page slug to its renderer.
 */
function gpn_crm_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'gpn-crm' ) );
	}

	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'gpn-crm-dashboard';
	$page = str_replace( 'gpn-crm-', '', $page );

	// Export / backup file downloads handled separately (they must stream before any HTML).
	if ( 'export' === $page && isset( $_GET['download'], $_GET['_wpnonce'] ) ) {
		check_admin_referer( 'gpn_crm_export' );
		if ( current_user_can( 'manage_options' ) ) {
			GPN_Import_Export::instance()->download( 'csv' );
		}
		wp_die( esc_html__( 'Permission denied.', 'gpn-crm' ) );
	}
	if ( 'export' === $page && isset( $_GET['xlsx'], $_GET['_wpnonce'] ) ) {
		check_admin_referer( 'gpn_crm_export' );
		if ( current_user_can( 'manage_options' ) ) {
			GPN_Import_Export::instance()->download( 'xlsx' );
		}
		wp_die( esc_html__( 'Permission denied.', 'gpn-crm' ) );
	}
	if ( 'backup' === $page && isset( $_GET['download'], $_GET['_wpnonce'] ) ) {
		check_admin_referer( 'gpn_crm_backup_download' );
		if ( current_user_can( 'manage_options' ) ) {
			$name = isset( $_GET['download'] ) ? sanitize_file_name( wp_unslash( $_GET['download'] ) ) : '';
			GPN_Backup::instance()->download( $name );
		}
		wp_die( esc_html__( 'Permission denied.', 'gpn-crm' ) );
	}

	// If not authenticated into the CRM, show the CRM login screen (desktop-style).
	if ( ! GPN_Auth::instance()->current_user() ) {
		GPN_Auth::instance()->render_login_page();
		return;
	}

	// Expose the authenticated CRM user to every page renderer as $current_user.
	$current_user = GPN_Auth::instance()->current_user();

	$map = array(
		'dashboard' => 'dashboard.php',
		'sadhaks'   => 'sadhaks.php',
		'add'       => 'add-sadhak.php',
		'groups'    => 'groups.php',
		'sync'      => 'sync.php',
		'import'    => 'import.php',
		'export'    => 'export.php',
		'settings'  => 'settings.php',
		'users'     => 'users.php',
		'backup'    => 'backup.php',
	);

	$file = isset( $map[ $page ] ) ? $map[ $page ] : 'dashboard.php';
	require_once GPN_CRM_DIR . 'admin/' . $file;
}

/**
 * Enqueue styles/scripts on CRM admin pages.
 */
function gpn_crm_enqueue_assets( $hook ) {
	$screen = get_current_screen();
	$slug   = ( $screen && $screen->id ) ? $screen->id : '';
	if ( false === strpos( $slug, 'gpn-crm-' ) ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( ! is_string( $page ) || 0 !== strpos( $page, 'gpn-crm' ) ) {
			return;
		}
	}
	gpn_crm_do_enqueue();
}
add_action( 'admin_enqueue_scripts', 'gpn_crm_enqueue_assets' );

/**
 * Enqueue assets on frontend pages that contain the [gpn_crm] shortcode.
 */
function gpn_crm_enqueue_frontend_assets() {
	global $post;
	if ( $post && has_shortcode( $post->post_content, 'gpn_crm' ) ) {
		gpn_crm_do_enqueue();
	}
}
add_action( 'wp_enqueue_scripts', 'gpn_crm_enqueue_frontend_assets' );

/**
 * Shared enqueue logic for admin and frontend.
 */
function gpn_crm_do_enqueue() {
	wp_enqueue_style( 'gpn-crm-admin', GPN_CRM_URL . 'assets/css/admin.css', array(), GPN_CRM_VERSION );
	wp_enqueue_script( 'gpn-crm-admin', GPN_CRM_URL . 'assets/js/admin.js', array( 'jquery' ), GPN_CRM_VERSION, true );
	$user = GPN_Auth::instance()->current_user();
	wp_localize_script(
		'gpn-crm-admin',
		'gpnCrm',
		array(
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'gpn_crm_nonce' ),
			'page'           => isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '',
			'isAdmin'        => ( $user && $user['role'] === 'Admin' ) ? 1 : 0,
			'userId'         => $user ? (int) $user['id'] : 0,
			'fullName'       => $user ? $user['full_name'] : '',
			'role'           => $user ? $user['role'] : '',
			'defaultCountry' => GPN_Settings::instance()->get( 'default_country', '+977' ),
			'countryCodes'   => gpn_country_codes(),
			'countryNames'   => gpn_country_names(),
			'adminUrl'       => admin_url(),
			'pluginUrl'      => GPN_CRM_URL,
			'wpRest'         => esc_url_raw( rest_url( 'gpn-crm/v1' ) ),
			'whatsappPrefix' => GPN_Settings::instance()->get( 'whatsapp_prefix', '+977' ),
			'debug'          => defined( 'WP_DEBUG' ) && WP_DEBUG,
		)
	);
}

/**
 * Hide the CRM top-level menu for users who never authenticate into the CRM
 * (keeps the wp-admin clean for non-CRM admins while still being accessible).
 */
function gpn_crm_maybe_hide_menu() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( GPN_Auth::instance()->current_user() ) {
		return;
	}
	if ( isset( $_GET['page'] ) && 0 === strpos( sanitize_key( wp_unslash( $_GET['page'] ) ), 'gpn-crm' ) ) {
		return; // already on a CRM page.
	}
}
add_action( 'admin_head', 'gpn_crm_maybe_hide_menu' );

/**
 * Admin notices.
 */
function gpn_crm_admin_notices() {
	$screen = get_current_screen();
	$page   = ( $screen && $screen->id ) ? $screen->id : '';
	if ( false === strpos( $page, 'gpn-crm-' ) ) {
		return;
	}
	$notices = get_option( 'gpn_crm_notices', array() );
	if ( ! empty( $notices ) ) {
		foreach ( $notices as $n ) {
			$type = isset( $n['type'] ) ? $n['type'] : 'success';
			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $n['message'] ) . '</p></div>';
		}
		delete_option( 'gpn_crm_notices' );
	}
}
add_action( 'admin_notices', 'gpn_crm_admin_notices' );

/**
 * Flush the notices helper.
 */
function gpn_crm_flash_notice( $message, $type = 'success' ) {
	$notices   = get_option( 'gpn_crm_notices', array() );
	$notices[] = array( 'message' => $message, 'type' => $type );
	update_option( 'gpn_crm_notices', $notices );
}

/**
 * Run optional daily cleanup (old backups rotation handled in Backup class).
 */
function gpn_crm_schedule_cleanup() {
	if ( ! wp_next_scheduled( 'gpn_crm_auto_cleanup' ) ) {
		wp_schedule_event( time() + 3600, 'daily', 'gpn_crm_auto_cleanup' );
	}
}
add_action( 'admin_init', 'gpn_crm_schedule_cleanup' );

function gpn_crm_auto_cleanup() {
	GPN_Backup::instance()->rotate_backups();
}
add_action( 'gpn_crm_auto_cleanup', 'gpn_crm_auto_cleanup' );

/**
 * admin-post handlers for import (file upload).
 */
add_action( 'admin_post_gpn_crm_import', 'gpn_crm_import_handler' );
function gpn_crm_import_handler() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'gpn-crm' ) );
	}
	check_admin_referer( 'gpn_crm_import' );
	GPN_Import_Export::instance()->handle_upload();
	wp_safe_redirect( admin_url( 'admin.php?page=gpn-crm-import' ) );
	exit;
}

/**
 * admin-post handlers for backup restore (file upload).
 */
add_action( 'admin_post_gpn_crm_backup_restore', 'gpn_crm_backup_restore_handler' );
function gpn_crm_backup_restore_handler() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'gpn-crm' ) );
	}
	check_admin_referer( 'gpn_crm_backup_restore' );
	GPN_Backup::instance()->handle_restore_upload();
	wp_safe_redirect( admin_url( 'admin.php?page=gpn-crm-backup' ) );
	exit;
}

/**
 * Shortcode: [gpn_crm] renders the CRM dashboard on any page.
 */
add_shortcode( 'gpn_crm', 'gpn_crm_shortcode' );
function gpn_crm_shortcode() {
	$user = GPN_Auth::instance()->current_user();
	if ( ! $user ) {
		ob_start();
		GPN_Auth::instance()->render_login_page();
		return ob_get_clean();
	}
	// Enqueue assets on frontend when shortcode is present.
	wp_enqueue_style( 'gpn-crm-admin', GPN_CRM_URL . 'assets/css/admin.css', array(), GPN_CRM_VERSION );
	wp_enqueue_script( 'gpn-crm-admin', GPN_CRM_URL . 'assets/js/admin.js', array( 'jquery' ), GPN_CRM_VERSION, true );
	wp_localize_script(
		'gpn-crm-admin',
		'gpnCrm',
		array(
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'gpn_crm_nonce' ),
			'page'           => 'dashboard',
			'isAdmin'        => ( $user && $user['role'] === 'Admin' ) ? 1 : 0,
			'userId'         => $user ? (int) $user['id'] : 0,
			'fullName'       => $user ? $user['full_name'] : '',
			'role'           => $user ? $user['role'] : '',
			'defaultCountry' => GPN_Settings::instance()->get( 'default_country', '+977' ),
			'countryCodes'   => gpn_country_codes(),
			'countryNames'   => gpn_country_names(),
			'adminUrl'       => admin_url(),
			'pluginUrl'      => GPN_CRM_URL,
			'wpRest'         => esc_url_raw( rest_url( 'gpn-crm/v1' ) ),
			'whatsappPrefix' => GPN_Settings::instance()->get( 'whatsapp_prefix', '+977' ),
			'debug'          => defined( 'WP_DEBUG' ) && WP_DEBUG,
		)
	);
	ob_start();
	require GPN_CRM_DIR . 'admin/dashboard.php';
	return ob_get_clean();
}

/**
 * Load text domain.
 */
function gpn_crm_load_textdomain() {
	load_plugin_textdomain( 'gpn-crm', false, dirname( GPN_CRM_BASENAME ) . '/languages' );
}
add_action( 'plugins_loaded', 'gpn_crm_load_textdomain' );
