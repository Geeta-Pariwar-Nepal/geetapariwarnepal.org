<?php
/**
 * Daily Schedule page — coordinator assigns one Adhyaya per daily date.
 *
 * @package Geeta_Pariwar_Parayan_Booking
 */

defined( 'ABSPATH' ) || exit;

$statuses = GPPB_Helpers::booking_statuses();
?>
<div class="wrap gpn-pb-wrap">
	<div class="d-flex align-items-center gap-3 gpn-pb-topbar mb-4">
		<div class="gpn-pb-logo">
			<svg width="46" height="46" viewBox="0 0 46 46"><circle cx="23" cy="23" r="22" fill="#7C2D12"/><circle cx="23" cy="23" r="16" fill="none" stroke="#FBBF24" stroke-width="2"/><path d="M23 14 L29 32 L23 28 L17 32 Z" fill="#FBBF24"/></svg>
		</div>
		<div>
			<h1 class="gpn-pb-title"><?php esc_html_e( 'दैनिक पारायण तालिका', 'geeta-parayan-booking' ); ?></h1>
			<p class="text-muted mb-0"><?php esc_html_e( 'Each daily date gets ONE Adhyaya (1–18). Users can only book the assigned Adhyaya.', 'geeta-parayan-booking' ); ?></p>
		</div>
	</div>

	<form method="post" class="mb-4">
		<?php wp_nonce_field( 'gppb_schedule_save', 'gppb_schedule_nonce' ); ?>
		<div class="gpn-pb-panel">
			<div class="card-header p-3"><?php esc_html_e( 'आगामी मितिहरू — अध्याय तोक्नुहोस्', 'geeta-parayan-booking' ); ?></div>
			<div class="table-responsive">
				<table class="table table-sm align-middle mb-0">
					<thead>
						<tr>
							<th><?php esc_html_e( 'मिति', 'geeta-parayan-booking' ); ?></th>
							<th><?php esc_html_e( 'English', 'geeta-parayan-booking' ); ?></th>
							<th><?php esc_html_e( 'अध्याय (1–18)', 'geeta-parayan-booking' ); ?></th>
							<th><?php esc_html_e( 'स्थिति', 'geeta-parayan-booking' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $dates ) ) : ?>
							<tr><td colspan="4" class="text-muted"><?php esc_html_e( 'कुनै मिति उपलब्ध छैन ।', 'geeta-parayan-booking' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $dates as $date ) : ?>
								<?php
								$assigned  = isset( $schedule[ $date ] ) ? (int) $schedule[ $date ] : 0;
								$occupied  = isset( $occ[ $date ] ) ? (int) $occ[ $date ] : 0;
								$row_class = $date < $today ? 'table-secondary' : ( $occupied > 0 ? 'table-warning' : '' );
								?>
								<tr class="<?php echo esc_attr( $row_class ); ?>">
									<td><?php echo esc_html( GPPB_Helpers::nepali_date( $date )['compact'] ); ?></td>
									<td class="text-muted"><?php echo esc_html( $date ); ?></td>
									<td style="min-width: 160px;">
										<select name="date[]" hidden><option value="<?php echo esc_attr( $date ); ?>"><?php echo esc_html( $date ); ?></option></select>
										<select name="adhyaya[]" class="form-select form-select-sm">
											<option value="0"><?php esc_html_e( '— छान्नुहोस् —', 'geeta-parayan-booking' ); ?></option>
											<?php for ( $i = 1; $i <= GPPB_ADHYAYAS_TOTAL; $i++ ) : ?>
												<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $assigned, $i ); ?>><?php echo esc_html( GPPB_Helpers::adhyaya_title( $i ) ); ?></option>
											<?php endfor; ?>
										</select>
									</td>
									<td>
										<?php if ( $date < $today ) : ?>
											<span class="badge text-bg-secondary"><?php esc_html_e( 'विगत', 'geeta-parayan-booking' ); ?></span>
										<?php elseif ( $occupied > 0 ) : ?>
											<span class="badge text-bg-success"><?php echo esc_html( sprintf( __( 'बुक भयो (%d)', 'geeta-parayan-booking' ), $occupied ) ); ?></span>
										<?php elseif ( $assigned ) : ?>
											<span class="badge text-bg-danger"><?php esc_html_e( 'खाली', 'geeta-parayan-booking' ); ?></span>
										<?php else : ?>
											<span class="badge text-bg-light text-muted"><?php esc_html_e( 'अध्याय तोकिएको छैन', 'geeta-parayan-booking' ); ?></span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
			<div class="card-footer p-3 d-flex justify-content-end">
				<button class="btn btn-warning"><?php esc_html_e( 'तालिका सुरक्षित गर्नुहोस्', 'geeta-parayan-booking' ); ?></button>
			</div>
		</div>
	</form>
</div>
