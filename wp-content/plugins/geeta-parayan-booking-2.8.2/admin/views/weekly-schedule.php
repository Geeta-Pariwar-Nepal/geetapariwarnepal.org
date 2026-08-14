<?php
/**
 * Weekly Schedule page — view upcoming Saturday dates with session links.
 *
 * @package Geeta_Pariwar_Parayan_Booking
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap gpn-pb-wrap">
	<div class="d-flex align-items-center gap-3 gpn-pb-topbar mb-4">
		<div class="gpn-pb-logo">
			<svg width="46" height="46" viewBox="0 0 46 46"><circle cx="23" cy="23" r="22" fill="#7C2D12"/><circle cx="23" cy="23" r="16" fill="none" stroke="#FBBF24" stroke-width="2"/><path d="M23 14 L29 32 L23 28 L17 32 Z" fill="#FBBF24"/></svg>
		</div>
		<div>
			<h1 class="gpn-pb-title"><?php esc_html_e( 'साप्ताहिक पारायण तालिका', 'geeta-parayan-booking' ); ?></h1>
			<p class="text-muted mb-0"><?php esc_html_e( 'Weekly Parayan occurs only on Saturdays. Manage Zoom/YouTube links for each session.', 'geeta-parayan-booking' ); ?></p>
		</div>
	</div>

	<form method="post" class="mb-4">
		<?php wp_nonce_field( 'gppb_weekly_link_save', 'gppb_weekly_link_nonce' ); ?>
		<div class="gpn-pb-panel">
			<div class="card-header p-3"><?php esc_html_e( 'आगामी साप्ताहिक मितिहरू — लिङ्क तोक्नुहोस्', 'geeta-parayan-booking' ); ?></div>
			<div class="table-responsive">
				<table class="table table-sm align-middle mb-0">
					<thead>
						<tr>
							<th><?php esc_html_e( 'मिति', 'geeta-parayan-booking' ); ?></th>
							<th><?php esc_html_e( 'English', 'geeta-parayan-booking' ); ?></th>
							<th><?php esc_html_e( 'वार', 'geeta-parayan-booking' ); ?></th>
							<th><?php esc_html_e( 'स्थिति', 'geeta-parayan-booking' ); ?></th>
							<th><?php esc_html_e( 'Zoom Link', 'geeta-parayan-booking' ); ?></th>
							<th><?php esc_html_e( 'YouTube Link', 'geeta-parayan-booking' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $dates ) ) : ?>
							<tr><td colspan="6" class="text-muted"><?php esc_html_e( 'कुनै मिति उपलब्ध छैन ।', 'geeta-parayan-booking' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $dates as $date ) : ?>
								<?php
								$occupied  = isset( $occ[ $date ] ) ? (int) $occ[ $date ] : 0;
								$link      = isset( $links[ $date ] ) ? $links[ $date ] : null;
								$zoom_url  = $link ? $link->zoom_link : '';
								$yt_url    = $link ? $link->youtube_link : '';
								$row_class = $date < $today ? 'table-secondary' : ( $occupied > 0 ? 'table-warning' : '' );
								?>
								<tr class="<?php echo esc_attr( $row_class ); ?>">
									<td><?php echo esc_html( GPPB_Helpers::nepali_date( $date )['compact'] ); ?></td>
									<td class="text-muted"><?php echo esc_html( $date ); ?></td>
									<td><span class="badge text-bg-success"><?php esc_html_e( 'शनिबार', 'geeta-parayan-booking' ); ?></span></td>
									<td>
										<?php if ( $date < $today ) : ?>
											<span class="badge text-bg-secondary"><?php esc_html_e( 'विगत', 'geeta-parayan-booking' ); ?></span>
										<?php elseif ( $occupied >= GPPB_ADHYAYAS_TOTAL ) : ?>
											<span class="badge text-bg-danger"><?php esc_html_e( 'पूर्ण', 'geeta-parayan-booking' ); ?></span>
										<?php elseif ( $occupied > 0 ) : ?>
											<span class="badge text-bg-warning"><?php echo esc_html( sprintf( __( 'बुक भयो (%d)', 'geeta-parayan-booking' ), $occupied ) ); ?></span>
										<?php else : ?>
											<span class="badge text-bg-success"><?php esc_html_e( 'खुला', 'geeta-parayan-booking' ); ?></span>
										<?php endif; ?>
									</td>
									<td style="min-width: 200px;">
										<input type="hidden" name="date[]" value="<?php echo esc_attr( $date ); ?>">
										<input type="url" name="zoom_link[]" class="form-control form-control-sm" value="<?php echo esc_attr( $zoom_url ); ?>" placeholder="https://zoom.us/...">
									</td>
									<td style="min-width: 200px;">
										<input type="url" name="youtube_link[]" class="form-control form-control-sm" value="<?php echo esc_attr( $yt_url ); ?>" placeholder="https://youtube.com/...">
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
			<div class="card-footer p-3 d-flex justify-content-end">
				<button class="btn btn-warning"><?php esc_html_e( 'लिङ्कहरू सुरक्षित गर्नुहोस्', 'geeta-parayan-booking' ); ?></button>
			</div>
		</div>
	</form>
</div>