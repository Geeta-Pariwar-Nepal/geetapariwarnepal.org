<?php
/**
 * Uninstall routine — removes all custom tables and options.
 *
 * Runs when the plugin is deleted from WordPress.
 *
 * @package Geeta_Pariwar_Parayan_Booking
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$tables = array(
	'geeta_users_meta',
	'geeta_adhyayas',
	'geeta_bookings',
	'geeta_session_links',
	'geeta_overrides',
	'geeta_audit_log',
);

foreach ( $tables as $table ) {
	$full = $wpdb->prefix . $table;
	$wpdb->query( "DROP TABLE IF EXISTS {$full}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

$options = array(
	'gppb_db_version',
	'gppb_keep_data_on_uninstall',
	'gppb_cancellation_hours',
	'gppb_waiting_max',
	'gppb_daily_days_ahead',
	'gppb_weekly_dates_ahead',
	'gppb_notify_admin',
	'gppb_admin_email',
	'gppb_landing_title',
	'gppb_landing_subtitle',
	'gppb_course_link',
	'gppb_contact_page',
	'gppb_logo_url',
);

foreach ( $options as $option ) {
	delete_option( $option );
}
