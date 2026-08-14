<?php
/**
 * GPN CRM - uninstall.
 *
 * Removes all plugin data:
 *   - drops the wp_gpn_* tables
 *   - deletes the plugin options (incl. sync token, settings)
 *   - revokes the WordPress capabilities added on activation
 *   - removes the backup folder under wp-content/uploads/gpn-crm
 *
 * NOTE: this permanently deletes CRM data. To keep your data, remove the
 * plugin using "Deactivate" only (data is kept), or back up first.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/class-gpn-db.php';
require_once __DIR__ . '/includes/class-gpn-settings.php';

// 1. Drop tables.
$db = GPN_DB::instance();
$db->drop_tables();

// 2. Options.
delete_option( GPN_Settings::KEY );
delete_option( 'gpn_crm_version' );
delete_option( 'gpn_crm_notices' );

// 3. Capabilities.
$admin_role = get_role( 'administrator' );
if ( $admin_role ) {
	$admin_role->remove_cap( GPN_CRM_CAP );
	$admin_role->remove_cap( GPN_CRM_CAP_ADMIN );
}

// 4. Backup folder.
$backup_dir = trailingslashit( WP_CONTENT_DIR ) . 'uploads/gpn-crm';
if ( is_dir( $backup_dir ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $backup_dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $it as $file ) {
		if ( $file->isDir() ) {
			@rmdir( $file->getPathname() );
		} else {
			@unlink( $file->getPathname() );
		}
	}
	@rmdir( $backup_dir );
}

// 5. Scheduled events.
wp_clear_scheduled_hook( 'gpn_crm_auto_cleanup' );
