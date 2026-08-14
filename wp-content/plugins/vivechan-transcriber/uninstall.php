<?php
/**
 * Uninstall — remove plugin data.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$prefix = $wpdb->prefix;

$wpdb->query( "DROP TABLE IF EXISTS {$prefix}vivechan_transcripts" );
$wpdb->query( "DROP TABLE IF EXISTS {$prefix}vivechan_ai_integrations" );
$wpdb->query( "DROP TABLE IF EXISTS {$prefix}vivechan_system_prompts" );
$wpdb->query( "DROP TABLE IF EXISTS {$prefix}vivechan_shares" );

delete_option( 'vivechan_settings' );
delete_option( 'vivechan_index_page_checked' );
delete_option( 'vivechan_provisioned_version' );

$index = get_posts(
	array(
		'post_type'      => 'page',
		'post_status'    => array( 'draft', 'pending', 'publish' ),
		'meta_key'       => '_vivechan_index_page',
		'posts_per_page' => 1,
		'fields'         => 'ids',
	)
);
foreach ( $index as $page_id ) {
	wp_delete_post( $page_id, true );
}

$admin = get_role( 'administrator' );
if ( $admin instanceof WP_Role ) {
	$admin->remove_cap( 'vivechan_transcribe' );
	$admin->remove_cap( 'vivechan_publish' );
	$admin->remove_cap( 'vivechan_manage' );
}

foreach ( array( 'vivechak', 'vivechan_editor' ) as $vivechan_role ) {
	if ( get_role( $vivechan_role ) instanceof WP_Role ) {
		remove_role( $vivechan_role );
	}
}

$posts = get_posts(
	array(
		'post_type'      => 'vivechan',
		'post_status'    => array( 'draft', 'pending', 'publish' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $posts as $post_id ) {
	wp_delete_post( $post_id, true );
}
