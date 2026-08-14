<?php
/**
 * GPN CRM - Dashboard (statistics).
 *
 * Expects: $current_user
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gpn_current_user = $current_user;
$gpn_stats        = GPN_Sadhak::instance()->stats();
$gpn_max_level    = 1;
foreach ( $gpn_stats['levels'] as $l ) {
	$gpn_max_level = max( $gpn_max_level, (int) $l['total'] );
}
$gpn_max_batch = 1;
foreach ( $gpn_stats['batches'] as $b ) {
	$gpn_max_batch = max( $gpn_max_batch, (int) $b['total'] );
}

require GPN_CRM_DIR . 'templates/header.php';
?>
<div class="gpn-dashboard">
	<div class="gpn-stat-grid">
		<div class="gpn-stat-card">
			<div class="gpn-stat-icon gpn-stat-blue"><span class="dashicons dashicons-groups"></span></div>
			<div class="gpn-stat-value" id="gpnStatTotal"><?php echo esc_html( number_format_i18n( $gpn_stats['total_sadhaks'] ) ); ?></div>
			<div class="gpn-stat-label">Total Sadhaks</div>
		</div>
		<div class="gpn-stat-card">
			<div class="gpn-stat-icon gpn-stat-green"><span class="dashicons dashicons-calendar"></span></div>
			<div class="gpn-stat-value" id="gpnStatToday"><?php echo esc_html( number_format_i18n( $gpn_stats['today_added'] ) ); ?></div>
			<div class="gpn-stat-label">Today's Added</div>
		</div>
		<div class="gpn-stat-card">
			<div class="gpn-stat-icon gpn-stat-cyan"><span class="dashicons dashicons-yes-alt"></span></div>
			<div class="gpn-stat-value" id="gpnStatReady"><?php echo esc_html( number_format_i18n( $gpn_stats['ready'] ) ); ?></div>
			<div class="gpn-stat-label">Ready</div>
		</div>
		<div class="gpn-stat-card">
			<div class="gpn-stat-icon gpn-stat-purple"><span class="dashicons dashicons-networking"></span></div>
			<div class="gpn-stat-value" id="gpnStatGroups"><?php echo esc_html( number_format_i18n( $gpn_stats['groups'] ) ); ?></div>
			<div class="gpn-stat-label">Groups</div>
		</div>
		<div class="gpn-stat-card">
			<div class="gpn-stat-icon gpn-stat-orange"><span class="dashicons dashicons-admin-generic"></span></div>
			<div class="gpn-stat-value" id="gpnStatActiveGroups"><?php echo esc_html( number_format_i18n( $gpn_stats['active_groups'] ) ); ?></div>
			<div class="gpn-stat-label">Active Groups</div>
		</div>
	</div>

	<div class="gpn-dash-cols">
		<section class="gpn-panel">
			<h3 class="gpn-panel-title">Level Wise Count</h3>
			<?php if ( empty( $gpn_stats['levels'] ) ) : ?>
				<p class="gpn-empty">No data yet.</p>
			<?php else : ?>
				<?php foreach ( $gpn_stats['levels'] as $l ) : ?>
					<div class="gpn-bar-row">
						<span class="gpn-bar-label"><?php echo esc_html( $l['label'] ); ?></span>
						<div class="gpn-bar-track"><div class="gpn-bar-fill" style="width:<?php echo esc_attr( round( ( (int) $l['total'] / $gpn_max_level ) * 100 ) ); ?>%"></div></div>
						<span class="gpn-bar-count"><?php echo esc_html( (int) $l['total'] ); ?></span>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</section>

		<section class="gpn-panel">
			<h3 class="gpn-panel-title">Batch Wise Count</h3>
			<?php if ( empty( $gpn_stats['batches'] ) ) : ?>
				<p class="gpn-empty">No data yet.</p>
			<?php else : ?>
				<?php foreach ( $gpn_stats['batches'] as $b ) : ?>
					<div class="gpn-bar-row">
						<span class="gpn-bar-label"><?php echo esc_html( $b['label'] ); ?></span>
						<div class="gpn-bar-track"><div class="gpn-bar-fill gpn-bar-fill-alt" style="width:<?php echo esc_attr( round( ( (int) $b['total'] / $gpn_max_batch ) * 100 ) ); ?>%"></div></div>
						<span class="gpn-bar-count"><?php echo esc_html( (int) $b['total'] ); ?></span>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</section>
	</div>

	<?php if ( 'Admin' === $gpn_current_user['role'] ) : ?>
		<section class="gpn-panel">
			<h3 class="gpn-panel-title">Quick Actions</h3>
			<div class="gpn-btn-row">
				<a class="gpn-btn gpn-btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=gpn-crm-add' ) ); ?>">Add Sadhak</a>
				<a class="gpn-btn gpn-btn-info" href="<?php echo esc_url( admin_url( 'admin.php?page=gpn-crm-sadhaks' ) ); ?>">View Sadhaks</a>
				<a class="gpn-btn gpn-btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=gpn-crm-groups' ) ); ?>">Manage Groups</a>
				<a class="gpn-btn gpn-btn-success" href="<?php echo esc_url( admin_url( 'admin.php?page=gpn-crm-backup' ) ); ?>">Backup Database</a>
			</div>
		</section>
	<?php endif; ?>
</div>
<?php
require GPN_CRM_DIR . 'templates/footer.php';
