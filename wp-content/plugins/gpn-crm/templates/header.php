<?php
/**
 * GPN CRM - app header (blue header bar + nav), mirrors the desktop layout.
 *
 * Expects: $current_user (CRM session user array).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gpn_current_user = isset( $current_user ) ? $current_user : GPN_Auth::instance()->current_user();
$gpn_page         = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'gpn-crm-dashboard';
$gpn_role         = $gpn_current_user ? $gpn_current_user['role'] : '';

$gpn_nav = array(
	'gpn-crm-dashboard' => array( 'Dashboard', 'dashicons-dashboard' ),
	'gpn-crm-sadhaks'   => array( 'Sadhaks', 'dashicons-list-view' ),
	'gpn-crm-add'       => array( 'Add Sadhak', 'dashicons-plus-alt' ),
	'gpn-crm-groups'    => array( 'Groups', 'dashicons-groups' ),
);

if ( 'Admin' === $gpn_role ) {
	$gpn_nav['gpn-crm-sync']    = array( 'Sync', 'dashicons-update' );
	$gpn_nav['gpn-crm-import']  = array( 'Import', 'dashicons-upload' );
	$gpn_nav['gpn-crm-export']  = array( 'Export', 'dashicons-download' );
	$gpn_nav['gpn-crm-settings']= array( 'Settings', 'dashicons-admin-generic' );
	$gpn_nav['gpn-crm-users']   = array( 'User Management', 'dashicons-admin-users' );
	$gpn_nav['gpn-crm-backup']  = array( 'Backup', 'dashicons-backup' );
}
?>
<div class="gpn-shell">
	<header class="gpn-header">
		<div class="gpn-header-left">
			<span class="gpn-logo">ॐ</span>
			<div class="gpn-header-title">
				<h1><?php echo esc_html( GPN_Settings::instance()->get( 'app_name', 'Geeta Pariwar Nepal Sadhak CRM' ) ); ?></h1>
				<p>Geeta Pariwar Nepal</p>
			</div>
		</div>
		<div class="gpn-header-right">
			<?php if ( $gpn_current_user ) : ?>
				<span class="gpn-badge gpn-badge-blue">Role: <?php echo esc_html( $gpn_role ); ?></span>
				<span class="gpn-welcome">Welcome, <?php echo esc_html( $gpn_current_user['full_name'] ); ?></span>
				<button type="button" class="gpn-btn gpn-btn-danger gpn-logout-btn" id="gpnLogoutBtn">Logout</button>
			<?php endif; ?>
		</div>
	</header>

	<nav class="gpn-nav">
		<?php foreach ( $gpn_nav as $slug => $item ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>"
			   class="gpn-nav-link <?php echo $gpn_page === $slug ? 'is-active' : ''; ?>">
				<span class="dashicons <?php echo esc_attr( $item[1] ); ?>"></span>
				<?php echo esc_html( $item[0] ); ?>
			</a>
		<?php endforeach; ?>
		<div class="gpn-nav-spacer"></div>
		<a href="<?php echo esc_url( admin_url() ); ?>" class="gpn-nav-link gpn-nav-exit">
			<span class="dashicons dashicons-wordpress"></span> Exit to WordPress
		</a>
	</nav>

	<main class="gpn-main">
