<?php
/**
 * Admin dashboard — stats overview.
 *
 * @package Geeta_Pariwar_Parayan_Booking
 */

defined( 'ABSPATH' ) || exit;

$stat_links = array(
	'pending'   => array( 'label' => __( 'Approval Pending', 'geeta-parayan-booking' ), 'url' => admin_url( 'admin.php?page=gppb-approvals&filter=pending' ), 'cls' => 'gpn-bg-saffron', 'icon' => 'clock' ),
	'approved'  => array( 'label' => __( 'Approved Sadhaks', 'geeta-parayan-booking' ), 'url' => admin_url( 'admin.php?page=gppb-approvals&filter=approved' ), 'cls' => 'gpn-bg-maroon', 'icon' => 'yes-alt' ),
	'blocked'   => array( 'label' => __( 'Blocked Accounts', 'geeta-parayan-booking' ), 'url' => admin_url( 'admin.php?page=gppb-unblocks' ), 'cls' => 'gpn-bg-gold', 'icon' => 'dismiss' ),
	'active'    => array( 'label' => __( 'Active Bookings', 'geeta-parayan-booking' ), 'url' => admin_url( 'admin.php?page=gppb-roster' ), 'cls' => 'gpn-bg-saffron-dark', 'icon' => 'calendar-alt' ),
	'completed' => array( 'label' => __( 'Completed Sessions', 'geeta-parayan-booking' ), 'url' => admin_url( 'admin.php?page=gppb-roster&status=completed' ), 'cls' => 'gpn-bg-maroon-dark', 'icon' => 'awards' ),
);
?>
<div class="wrap gpn-pb-wrap">
	<div class="d-flex align-items-center gap-3 gpn-pb-topbar mb-4">
		<div class="gpn-pb-logo">
			<svg width="46" height="46" viewBox="0 0 46 46"><circle cx="23" cy="23" r="22" fill="#7C2D12"/><circle cx="23" cy="23" r="16" fill="none" stroke="#FBBF24" stroke-width="2"/><path d="M23 14 L29 32 L23 28 L17 32 Z" fill="#FBBF24"/></svg>
		</div>
		<div>
			<h1 class="gpn-pb-title"><?php esc_html_e( 'गीता पारायण बुकिङ इन्जिन', 'geeta-parayan-booking' ); ?></h1>
			<p class="text-muted mb-0"><?php esc_html_e( 'Dashboard — Geeta Pariwar Nepal', 'geeta-parayan-booking' ); ?></p>
		</div>
	</div>

	<div class="row g-3 mb-4">
		<?php foreach ( $stat_links as $key => $stat ) : ?>
			<div class="col-6 col-md-4 col-xl">
				<a class="card gpn-pb-stat-card text-decoration-none text-body h-100" href="<?php echo esc_url( $stat['url'] ); ?>">
					<div class="card-body d-flex align-items-center gap-3">
						<div class="gpn-stat-icon <?php echo esc_attr( $stat['cls'] ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( $stat['icon'] ); ?>"></span></div>
						<div>
							<div class="gpn-stat-value"><?php echo esc_html( (int) $stats[ $key ] ); ?></div>
							<div class="gpn-stat-label"><?php echo esc_html( $stat['label'] ); ?></div>
						</div>
					</div>
				</a>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="row g-3">
		<div class="col-lg-7">
			<div class="gpn-pb-panel">
				<div class="card-header p-3"><?php esc_html_e( 'Upcoming Confirmed Sessions', 'geeta-parayan-booking' ); ?></div>
				<div class="card-body p-0">
					<table class="table table-hover gpn-pb-table mb-0">
						<thead><tr><th><?php esc_html_e( 'PRN', 'geeta-parayan-booking' ); ?></th><th><?php esc_html_e( 'साधक', 'geeta-parayan-booking' ); ?></th><th><?php esc_html_e( 'अध्याय', 'geeta-parayan-booking' ); ?></th><th><?php esc_html_e( 'मिति', 'geeta-parayan-booking' ); ?></th></tr></thead>
						<tbody>
						<?php if ( empty( $upcoming['items'] ) ) : ?>
							<tr><td colspan="4" class="text-center text-muted py-4"><?php esc_html_e( 'No upcoming confirmed sessions.', 'geeta-parayan-booking' ); ?></td></tr>
						<?php else : foreach ( $upcoming['items'] as $b ) : ?>
							<tr>
								<td><code><?php echo esc_html( $b->prn ); ?></code></td>
								<td><?php echo esc_html( $b->display_name ? $b->display_name : $b->user_login ); ?></td>
								<td><?php echo esc_html( $b->title_nepali ); ?></td>
								<td><?php echo esc_html( GPPB_Helpers::format_date( $b->booking_date ) ); ?></td>
							</tr>
						<?php endforeach; endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<div class="col-lg-5">
			<div class="gpn-pb-panel">
				<div class="card-header p-3"><?php esc_html_e( 'Quick Actions', 'geeta-parayan-booking' ); ?></div>
				<div class="card-body">
					<a class="btn btn-warning w-100 mb-2" href="<?php echo esc_url( admin_url( 'admin.php?page=gppb-approvals&filter=pending' ) ); ?>"><?php esc_html_e( 'Approve Sadhaks', 'geeta-parayan-booking' ); ?></a>
					<a class="btn btn-outline-danger w-100 mb-2" href="<?php echo esc_url( admin_url( 'admin.php?page=gppb-unblocks' ) ); ?>"><?php esc_html_e( 'Unblock Requests', 'geeta-parayan-booking' ); ?></a>
					<a class="btn btn-outline-dark w-100 mb-2" href="<?php echo esc_url( admin_url( 'admin.php?page=gppb-links' ) ); ?>"><?php esc_html_e( 'Set Session Links', 'geeta-parayan-booking' ); ?></a>
					<a class="btn btn-outline-secondary w-100" href="<?php echo esc_url( GPPB_Helpers::public_permalink() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View Booking Page', 'geeta-parayan-booking' ); ?></a>
				</div>
			</div>
		</div>
	</div>
</div>
