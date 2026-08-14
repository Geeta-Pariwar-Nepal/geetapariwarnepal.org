<?php
/**
 * Sadhak approvals page.
 *
 * @package Geeta_Pariwar_Parayan_Booking
 */

defined( 'ABSPATH' ) || exit;

$filters = array(
	'pending'  => __( 'Pending', 'geeta-parayan-booking' ),
	'approved' => __( 'Approved', 'geeta-parayan-booking' ),
	'rejected' => __( 'Rejected', 'geeta-parayan-booking' ),
	'all'      => __( 'All', 'geeta-parayan-booking' ),
);
$approval_labels = GPPB_Helpers::approval_statuses();
?>
<div class="wrap gpn-pb-wrap">
	<div class="d-flex align-items-center gap-3 gpn-pb-topbar mb-4">
		<div class="gpn-pb-logo">
			<svg width="46" height="46" viewBox="0 0 46 46"><circle cx="23" cy="23" r="22" fill="#7C2D12"/><circle cx="23" cy="23" r="16" fill="none" stroke="#FBBF24" stroke-width="2"/><path d="M23 14 L29 32 L23 28 L17 32 Z" fill="#FBBF24"/></svg>
		</div>
		<div>
			<h1 class="gpn-pb-title"><?php esc_html_e( 'साधक अनुमोदन', 'geeta-parayan-booking' ); ?></h1>
			<p class="text-muted mb-0"><?php esc_html_e( 'Teacher approval gateway — only approved sadhaks can book.', 'geeta-parayan-booking' ); ?></p>
		</div>
	</div>

	<ul class="nav nav-pills mb-3">
		<?php foreach ( $filters as $key => $label ) : ?>
			<li class="nav-item">
				<a class="nav-link <?php echo $filter === $key ? 'active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=gppb-approvals&filter=' . $key ) ); ?>"><?php echo esc_html( $label ); ?></a>
			</li>
		<?php endforeach; ?>
	</ul>

	<div class="gpn-pb-panel">
		<div class="card-body p-0">
			<table class="table table-hover gpn-pb-table mb-0">
				<thead>
					<tr>
						<th><?php esc_html_e( 'साधक', 'geeta-parayan-booking' ); ?></th>
						<th><?php esc_html_e( 'Email', 'geeta-parayan-booking' ); ?></th>
						<th><?php esc_html_e( 'Registered', 'geeta-parayan-booking' ); ?></th>
						<th><?php esc_html_e( 'Bookings', 'geeta-parayan-booking' ); ?></th>
						<th><?php esc_html_e( 'Status', 'geeta-parayan-booking' ); ?></th>
						<th class="text-end"><?php esc_html_e( 'Action', 'geeta-parayan-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $sadhaks ) ) : ?>
					<tr><td colspan="6" class="text-center text-muted py-4"><?php esc_html_e( 'No sadhaks found.', 'geeta-parayan-booking' ); ?></td></tr>
				<?php else : foreach ( $sadhaks as $s ) : ?>
					<tr>
						<td class="fw-semibold"><?php echo esc_html( $s->display_name ? $s->display_name : '—' ); ?></td>
						<td><?php echo esc_html( $s->user_email ); ?></td>
						<td><?php echo esc_html( GPPB_Helpers::format_date( date_i18n( 'Y-m-d', strtotime( $s->user_registered ) ) ) ); ?></td>
						<td><span class="badge text-bg-light"><?php echo esc_html( (int) $s->booking_count ); ?></span></td>
						<td>
							<span class="badge text-bg-<?php echo 'approved' === $s->teacher_approval_status ? 'success' : ( 'rejected' === $s->teacher_approval_status ? 'danger' : 'warning' ); ?>">
								<?php echo esc_html( $approval_labels[ $s->teacher_approval_status ] ?? $s->teacher_approval_status ); ?>
							</span>
							<?php if ( (int) ( $s->booking_override ?? 0 ) ) : ?>
								<span class="badge text-bg-warning ms-1"><?php esc_html_e( 'Early booking allowed', 'geeta-parayan-booking' ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $override_map[ (int) $s->user_id ] ) ) : ?>
								<span class="badge text-bg-warning ms-1"><?php echo esc_html( sprintf( /* translators: %d: number of active booking-scoped overrides. */ __( 'Early booking ×%d', 'geeta-parayan-booking' ), (int) $override_map[ (int) $s->user_id ] ) ); ?></span>
							<?php endif; ?>
						</td>
						<td class="text-end gpn-pb-actions">
							<?php if ( 'approved' === $s->teacher_approval_status ) : ?>
								<button class="btn btn-sm btn-warning gpn-grant-override" data-bs-toggle="modal" data-bs-target="#gppbOverrideModal" data-id="<?php echo esc_attr( (int) $s->user_id ); ?>" data-name="<?php echo esc_attr( $s->display_name ? $s->display_name : $s->user_email ); ?>"><?php esc_html_e( 'Allow early booking', 'geeta-parayan-booking' ); ?></button>
							<?php endif; ?>
							<?php if ( 'approved' !== $s->teacher_approval_status ) : ?>
								<button class="btn btn-sm btn-success gpn-approve" data-id="<?php echo esc_attr( (int) $s->user_id ); ?>"><?php esc_html_e( 'Approve', 'geeta-parayan-booking' ); ?></button>
							<?php endif; ?>
							<?php if ( 'rejected' !== $s->teacher_approval_status ) : ?>
								<button class="btn btn-sm btn-outline-danger gpn-reject" data-id="<?php echo esc_attr( (int) $s->user_id ); ?>"><?php esc_html_e( 'Reject', 'geeta-parayan-booking' ); ?></button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<div class="gpn-pb-panel mb-4">
		<div class="card-header p-3"><?php esc_html_e( 'Active early-booking overrides', 'geeta-parayan-booking' ); ?></div>
		<div class="card-body p-0">
			<table class="table table-hover gpn-pb-table mb-0">
				<thead>
					<tr>
						<th><?php esc_html_e( 'साधक', 'geeta-parayan-booking' ); ?></th>
						<th><?php esc_html_e( 'प्रकार', 'geeta-parayan-booking' ); ?></th>
						<th><?php esc_html_e( 'अध्याय', 'geeta-parayan-booking' ); ?></th>
						<th><?php esc_html_e( 'मिति', 'geeta-parayan-booking' ); ?></th>
						<th><?php esc_html_e( 'Granted', 'geeta-parayan-booking' ); ?></th>
						<th class="text-end"><?php esc_html_e( 'Action', 'geeta-parayan-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $overrides ) ) : ?>
					<tr><td colspan="6" class="text-center text-muted py-4"><?php esc_html_e( 'No active overrides.', 'geeta-parayan-booking' ); ?></td></tr>
				<?php else : foreach ( $overrides as $ov ) : ?>
					<tr>
						<td class="fw-semibold">
							<?php echo esc_html( $ov->display_name ? $ov->display_name : ( $ov->sadhak_name ? $ov->sadhak_name : ( $ov->user_email ? $ov->user_email : '—' ) ) ); ?>
							<?php if ( ! empty( $ov->sadhak_prn ) ) : ?>
								<small class="d-block text-muted"><?php esc_html_e( 'Sadhak PRN:', 'geeta-parayan-booking' ); ?> <?php echo esc_html( $ov->sadhak_prn ); ?></small>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( ( GPPB_Helpers::slot_types() )[ $ov->slot_type ] ?? $ov->slot_type ); ?></td>
						<td><?php echo esc_html( GPPB_Helpers::adhyaya_title( (int) $ov->adhyaya_number ) ); ?></td>
						<td><?php echo esc_html( GPPB_Helpers::format_date( $ov->booking_date ) ); ?></td>
						<td><?php echo esc_html( GPPB_Helpers::format_datetime( $ov->created_at ) ); ?></td>
						<td class="text-end">
							<button class="btn btn-sm btn-outline-danger gpn-revoke-override" data-id="<?php echo esc_attr( (int) $ov->id ); ?>"><?php esc_html_e( 'Revoke', 'geeta-parayan-booking' ); ?></button>
						</td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<!-- Grant early-booking override modal -->
<div class="modal fade" id="gppbOverrideModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<form id="gppb-override-form">
				<div class="modal-header">
					<h5 class="modal-title"><?php esc_html_e( 'Allow early booking', 'geeta-parayan-booking' ); ?></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php esc_attr_e( 'Close', 'geeta-parayan-booking' ); ?>"></button>
				</div>
				<div class="modal-body">
					<input type="hidden" name="user_id" id="gppb-override-user" value="">
					<div class="mb-3">
						<label class="form-label"><?php esc_html_e( 'साधक', 'geeta-parayan-booking' ); ?></label>
						<input type="text" class="form-control" id="gppb-override-name" readonly>
					</div>
					<div class="mb-3">
						<label class="form-label"><?php esc_html_e( 'Parayan type', 'geeta-parayan-booking' ); ?></label>
						<select name="slot_type" class="form-select" id="gppb-override-type">
							<?php foreach ( GPPB_Helpers::slot_types() as $k => $v ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $v ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="mb-3">
						<label class="form-label"><?php esc_html_e( 'Date', 'geeta-parayan-booking' ); ?></label>
						<input type="date" name="date" class="form-control" id="gppb-override-date" min="<?php echo esc_attr( GPPB_Helpers::today() ); ?>" required>
					</div>
					<div class="mb-3">
						<label class="form-label"><?php esc_html_e( 'अध्याय', 'geeta-parayan-booking' ); ?></label>
						<select name="adhyaya_number" class="form-select" id="gppb-override-chapter">
							<?php for ( $i = 1; $i <= GPPB_ADHYAYAS_TOTAL; $i++ ) : ?>
								<option value="<?php echo esc_attr( $i ); ?>"><?php echo esc_html( GPPB_Helpers::adhyaya_title( $i ) ); ?></option>
							<?php endfor; ?>
						</select>
					</div>
					<p class="form-text text-muted mb-0">
						<?php esc_html_e( 'The sadhak may book ONLY this Adhyaya on this date even within the 1-month restriction. The override is consumed once that booking is created.', 'geeta-parayan-booking' ); ?>
					</p>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?php esc_html_e( 'Cancel', 'geeta-parayan-booking' ); ?></button>
					<button type="submit" class="btn btn-warning"><?php esc_html_e( 'Grant early booking', 'geeta-parayan-booking' ); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>
