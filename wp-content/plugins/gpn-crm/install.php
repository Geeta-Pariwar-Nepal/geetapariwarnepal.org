<?php
/**
 * GPN CRM - installation routine.
 *
 * Called by the activation hook. Creates tables, seeds the default
 * Administrator account (admin / admin123 - identical to the desktop app),
 * grants WordPress capabilities and stores default settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function gpn_crm_install() {
	$db = GPN_DB::instance();

	// 1. Tables.
	$db->create_tables();

	// 2. Add email column to users table if missing (upgrade path).
	$users_table = $db->users();
	$col = $db->db()->get_row( $db->db()->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $users_table, 'email' ) );
	if ( ! $col ) {
		$db->db()->query( "ALTER TABLE {$users_table} ADD COLUMN email VARCHAR(190) DEFAULT NULL AFTER username" );
	}

	// 3. Default admin account (mirrors services/auth_service.seed_admin).
	$user_count = (int) $db->db()->get_var( 'SELECT COUNT(*) FROM ' . $db->users() );
	if ( 0 === $user_count ) {
		$now = gpn_now();
		$db->db()->insert(
			$db->users(),
			array(
				'full_name'     => 'Administrator',
				'username'      => 'admin',
				'email'         => 'admin@geetapariwarnepal.org',
				'password_hash' => gpn_hash_password( 'admin123' ),
				'role'          => 'Admin',
				'active'        => 1,
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	// 4. WordPress capabilities (always grant to existing admins).
	$admin_role = get_role( 'administrator' );
	if ( $admin_role ) {
		$admin_role->add_cap( GPN_CRM_CAP );
		$admin_role->add_cap( GPN_CRM_CAP_ADMIN );
	}

	// 5. Default settings (only if not already stored).
	$existing = get_option( GPN_Settings::KEY, null );
	if ( null === $existing || ! is_array( $existing ) ) {
		$settings = GPN_Settings::instance()->defaults();
		$settings['sync_token'] = wp_generate_password( 32, false );
		update_option( GPN_Settings::KEY, $settings );
	} else {
		// Ensure a sync token exists.
		GPN_Settings::instance()->sync_token();
	}

	update_option( 'gpn_crm_version', GPN_CRM_VERSION );
	flush_rewrite_rules();
}
