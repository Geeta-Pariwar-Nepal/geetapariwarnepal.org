<?php
/**
 * Session links page.
 *
 * @package Geeta_Pariwar_Parayan_Booking
 */

defined( 'ABSPATH' ) || exit;
$slot_types = GPPB_Helpers::slot_types();
?>
<div class="wrap gpn-pb-wrap">
	<div class="d-flex align-items-center gap-3 gpn-pb-topbar mb-4">
		<div class="gpn-pb-logo">
			<svg width="46" height="46" viewBox="0 0 46 46"><circle cx="23" cy="23" r="22" fill="#7C2D12"/><circle cx="23" cy="23" r="16" fill="none" stroke="#FBBF24" stroke-width="2"/><path d="M23 14 L29 32 L23 28 L17 32 Z" fill="#FBBF24"/></svg>
		</div>
		<div>
			<h1 class="gpn-pb-title"><?php esc_html_e( 'सेसन लिंकहरू', 'geeta-parayan-booking' ); ?></h1>
			<p class="text-muted mb-0"><?php esc_html_e( 'Attach Zoom / YouTube links per session date.', 'geeta-parayan-booking' ); ?></p>
		</div>
	</div>

	<div class="gpn-pb-panel mb-4">
		<div class="card-header p-3"><?php esc_html_e( 'Add / Edit Session Link', 'geeta-parayan-booking' ); ?></div>
		<div class="card-body">
			<form id="gppb-link-form" class="row g-3">
				<div class="col-md-2">
					<label class="form-label small"><?php esc_html_e( 'Type', 'geeta-parayan-booking' ); ?></label>
					<select name="slot_type" class="form-select form-select-sm">
						<?php foreach ( $slot_types as $k => $v ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $v ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-3">
					<label class="form-label small"><?php esc_html_e( 'Session Date', 'geeta-parayan-booking' ); ?></label>
					<input type="date" name="date" class="form-control form-control-sm" required>
				</div>
				<div class="col-md-4">
					<label class="form-label small">Zoom Link</label>
					<input type="url" name="zoom_link" class="form-control form-control-sm" placeholder="https://zoom.us/...">
				</div>
				<div class="col-md-3">
					<label class="form-label small">YouTube Link</label>
					<input type="url" name="youtube_link" class="form-control form-control-sm" placeholder="https://youtube.com/...">
				</div>
				<div class="col-12">
					<button class="btn btn-warning btn-sm"><?php esc_html_e( 'Save Link', 'geeta-parayan-booking' ); ?></button>
				</div>
			</form>
		</div>
	</div>

	<div class="gpn-pb-panel">
		<div class="card-header p-3"><?php esc_html_e( 'Saved Links', 'geeta-parayan-booking' ); ?></div>
		<div class="card-body p-0">
			<table class="table table-hover gpn-pb-table mb-0">
				<thead><tr><th><?php esc_html_e( 'मिति', 'geeta-parayan-booking' ); ?></th><th><?php esc_html_e( 'प्रकार', 'geeta-parayan-booking' ); ?></th><th>Zoom</th><th>YouTube</th><th><?php esc_html_e( 'Updated', 'geeta-parayan-booking' ); ?></th></tr></thead>
				<tbody>
				<?php if ( empty( $links ) ) : ?>
					<tr><td colspan="5" class="text-center text-muted py-4"><?php esc_html_e( 'No session links yet.', 'geeta-parayan-booking' ); ?></td></tr>
				<?php else : foreach ( $links as $l ) : ?>
					<tr>
						<td><?php echo esc_html( GPPB_Helpers::format_date( $l->session_date ) ); ?></td>
						<td><?php echo esc_html( $slot_types[ $l->slot_type ] ?? $l->slot_type ); ?></td>
						<td><?php echo $l->zoom_link ? '<a href="' . esc_url( $l->zoom_link ) . '" target="_blank" rel="noopener">' . esc_html( $l->zoom_link ) . '</a>' : '—'; ?></td>
						<td><?php echo $l->youtube_link ? '<a href="' . esc_url( $l->youtube_link ) . '" target="_blank" rel="noopener">' . esc_html( $l->youtube_link ) . '</a>' : '—'; ?></td>
						<td><?php echo esc_html( GPPB_Helpers::format_datetime( $l->updated_at ) ); ?></td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
