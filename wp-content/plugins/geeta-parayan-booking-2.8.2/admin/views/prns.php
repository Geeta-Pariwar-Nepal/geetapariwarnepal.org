<?php
/**
 * PRN Master page — register / edit / block Sadhak PRN records.
 *
 * @package Geeta_Pariwar_Parayan_Booking
 */

defined( 'ABSPATH' ) || exit;

$status_labels = array(
	'allowed' => __( 'Allowed', 'geeta-parayan-booking' ),
	'blocked' => __( 'Blocked', 'geeta-parayan-booking' ),
);
?>
<div class="wrap gpn-pb-wrap">
	<div class="d-flex align-items-center gap-3 gpn-pb-topbar mb-4">
		<div class="gpn-pb-logo">
			<svg width="46" height="46" viewBox="0 0 46 46"><circle cx="23" cy="23" r="22" fill="#7C2D12"/><circle cx="23" cy="23" r="16" fill="none" stroke="#FBBF24" stroke-width="2"/><path d="M23 14 L29 32 L23 28 L17 32 Z" fill="#FBBF24"/></svg>
		</div>
		<div>
			<h1 class="gpn-pb-title"><?php esc_html_e( 'PRN Master', 'geeta-parayan-booking' ); ?></h1>
			<p class="text-muted mb-0"><?php esc_html_e( 'Sadhak PRN registration, validity window and eligibility control.', 'geeta-parayan-booking' ); ?></p>
		</div>
	</div>

	<form class="gpn-pb-panel mb-4 p-3" id="gppb-prn-form" autocomplete="off">
		<input type="hidden" name="id" value="">
		<div class="row g-2">
			<div class="col-md-2">
				<label class="form-label small mb-1"><?php esc_html_e( 'PRN', 'geeta-parayan-booking' ); ?> *</label>
				<input type="text" name="prn" class="form-control" required placeholder="PRN">
			</div>
			<div class="col-md-3">
				<label class="form-label small mb-1"><?php esc_html_e( 'Sadhak Name', 'geeta-parayan-booking' ); ?> *</label>
				<input type="text" name="name" class="form-control" required placeholder="<?php esc_attr_e( 'Full name', 'geeta-parayan-booking' ); ?>">
			</div>
			<div class="col-md-2">
				<label class="form-label small mb-1"><?php esc_html_e( 'Phone', 'geeta-parayan-booking' ); ?></label>
				<input type="text" name="phone" class="form-control" placeholder="98xxxxxxxx">
			</div>
			<div class="col-md-2">
				<label class="form-label small mb-1"><?php esc_html_e( 'Email', 'geeta-parayan-booking' ); ?></label>
				<input type="email" name="email" class="form-control" placeholder="you@example.com">
			</div>
			<div class="col-md-2">
				<label class="form-label small mb-1"><?php esc_html_e( 'Valid From', 'geeta-parayan-booking' ); ?></label>
				<input type="date" name="valid_from" class="form-control">
			</div>
			<div class="col-md-2">
				<label class="form-label small mb-1"><?php esc_html_e( 'Valid Until', 'geeta-parayan-booking' ); ?></label>
				<input type="date" name="valid_until" class="form-control">
			</div>
			<div class="col-md-2">
				<label class="form-label small mb-1"><?php esc_html_e( 'Status', 'geeta-parayan-booking' ); ?></label>
				<select name="prn_status" class="form-select">
					<option value="allowed"><?php esc_html_e( 'Allowed', 'geeta-parayan-booking' ); ?></option>
					<option value="blocked"><?php esc_html_e( 'Blocked', 'geeta-parayan-booking' ); ?></option>
				</select>
			</div>
			<div class="col-md-12 mt-2 d-flex gap-2">
				<button type="submit" class="btn btn-warning fw-semibold"><?php esc_html_e( 'सुरक्षित गर्नुहोस्', 'geeta-parayan-booking' ); ?></button>
				<button type="button" class="btn btn-light" id="gppb-prn-reset-form"><?php esc_html_e( 'नयाँ', 'geeta-parayan-booking' ); ?></button>
			</div>
		</div>
	</form>

	<form class="mb-3" method="get">
		<input type="hidden" name="page" value="gppb-prns">
		<div class="input-group" style="max-width:420px">
			<input type="search" name="s" class="form-control" value="<?php echo esc_attr( isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>" placeholder="<?php esc_attr_e( 'Search PRN / name / phone / email', 'geeta-parayan-booking' ); ?>">
			<button class="btn btn-outline-warning" type="submit"><?php esc_html_e( 'खोज्नुहोस्', 'geeta-parayan-booking' ); ?></button>
		</div>
	</form>

	<div class="gpn-pb-panel">
		<table class="table table-hover gpn-pb-table mb-0 align-middle">
			<thead>
				<tr>
					<th><?php esc_html_e( 'PRN', 'geeta-parayan-booking' ); ?></th>
					<th><?php esc_html_e( 'Sadhak', 'geeta-parayan-booking' ); ?></th>
					<th><?php esc_html_e( 'Contact', 'geeta-parayan-booking' ); ?></th>
					<th><?php esc_html_e( 'Valid From', 'geeta-parayan-booking' ); ?></th>
					<th><?php esc_html_e( 'Valid Until', 'geeta-parayan-booking' ); ?></th>
					<th><?php esc_html_e( 'Status', 'geeta-parayan-booking' ); ?></th>
					<th class="text-end"><?php esc_html_e( 'Action', 'geeta-parayan-booking' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="7" class="text-center text-muted py-4"><?php esc_html_e( 'No PRN records found. Use the form above to register the first Sadhak.', 'geeta-parayan-booking' ); ?></td></tr>
			<?php else : foreach ( $rows as $r ) : ?>
				<tr>
					<td class="fw-semibold"><?php echo esc_html( $r->prn ); ?></td>
					<td><?php echo esc_html( $r->name ); ?></td>
					<td>
						<?php echo esc_html( $r->phone ); ?>
						<?php if ( ! empty( $r->email ) ) : ?><br><small class="text-muted"><?php echo esc_html( $r->email ); ?></small><?php endif; ?>
					</td>
					<td><?php echo $r->valid_from ? esc_html( GPPB_Helpers::format_date( $r->valid_from ) ) : '—'; ?></td>
					<td><?php echo $r->valid_until ? esc_html( GPPB_Helpers::format_date( $r->valid_until ) ) : '—'; ?></td>
					<td>
						<span class="badge text-bg-<?php echo 'blocked' === $r->prn_status ? 'danger' : 'success'; ?>">
							<?php echo esc_html( $status_labels[ $r->prn_status ] ?? $r->prn_status ); ?>
						</span>
					</td>
					<td class="text-end text-nowrap">
						<button type="button" class="btn btn-sm btn-outline-primary gppb-prn-grant-override"
							data-prn="<?php echo esc_attr( $r->prn ); ?>"
							data-name="<?php echo esc_attr( $r->name ); ?>">
							<?php esc_html_e( 'Override', 'geeta-parayan-booking' ); ?>
						</button>
						<button type="button" class="btn btn-sm btn-outline-warning gppb-prn-edit"
							data-id="<?php echo esc_attr( (int) $r->id ); ?>"
							data-prn="<?php echo esc_attr( $r->prn ); ?>"
							data-name="<?php echo esc_attr( $r->name ); ?>"
							data-phone="<?php echo esc_attr( $r->phone ); ?>"
							data-email="<?php echo esc_attr( $r->email ); ?>"
							data-valid_from="<?php echo esc_attr( $r->valid_from ); ?>"
							data-valid_until="<?php echo esc_attr( $r->valid_until ); ?>"
							data-status="<?php echo esc_attr( $r->prn_status ); ?>">
							<?php esc_html_e( 'Edit', 'geeta-parayan-booking' ); ?>
						</button>
						<button type="button" class="btn btn-sm <?php echo 'blocked' === $r->prn_status ? 'btn-outline-success' : 'btn-outline-danger'; ?> gppb-prn-toggle"
							data-id="<?php echo esc_attr( (int) $r->id ); ?>"
							data-status="<?php echo 'blocked' === $r->prn_status ? 'allowed' : 'blocked'; ?>">
							<?php echo 'blocked' === $r->prn_status ? esc_html__( 'Allow', 'geeta-parayan-booking' ) : esc_html__( 'Block', 'geeta-parayan-booking' ); ?>
						</button>
					</td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>
	</div>
</div>