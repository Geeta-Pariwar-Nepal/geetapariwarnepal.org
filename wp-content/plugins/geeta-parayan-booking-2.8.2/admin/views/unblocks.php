<?php
/**
 * Unblock requests page.
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
			<h1 class="gpn-pb-title"><?php esc_html_e( 'अनब्लक अनुरोध', 'geeta-parayan-booking' ); ?></h1>
			<p class="text-muted mb-0"><?php esc_html_e( 'Blocked accounts requesting to re-join Parayan.', 'geeta-parayan-booking' ); ?></p>
		</div>
	</div>

	<div class="gpn-pb-panel">
		<div class="card-body p-0">
			<table class="table table-hover gpn-pb-table mb-0">
				<thead>
					<tr>
						<th><?php esc_html_e( 'साधक', 'geeta-parayan-booking' ); ?></th>
						<th><?php esc_html_e( 'Email', 'geeta-parayan-booking' ); ?></th>
						<th><?php esc_html_e( 'Blocked', 'geeta-parayan-booking' ); ?></th>
						<th><?php esc_html_e( 'Written Request', 'geeta-parayan-booking' ); ?></th>
						<th class="text-end"><?php esc_html_e( 'Action', 'geeta-parayan-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $blocked ) ) : ?>
					<tr><td colspan="5" class="text-center text-muted py-4"><?php esc_html_e( 'No blocked accounts.', 'geeta-parayan-booking' ); ?></td></tr>
				<?php else : foreach ( $blocked as $s ) : ?>
					<tr>
						<td class="fw-semibold"><?php echo esc_html( $s->display_name ? $s->display_name : '—' ); ?></td>
						<td><?php echo esc_html( $s->user_email ); ?></td>
						<td><?php echo esc_html( GPPB_Helpers::format_datetime( $s->updated_at ) ); ?></td>
						<td style="max-width:360px">
							<?php if ( ! empty( $s->unblock_request_reason ) ) : ?>
								<div class="p-2 rounded bg-light border"><?php echo esc_html( $s->unblock_request_reason ); ?></div>
							<?php else : ?>
								<span class="text-muted"><?php esc_html_e( 'No written request yet.', 'geeta-parayan-booking' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="text-end gpn-pb-actions">
							<button class="btn btn-sm btn-success gpn-unblock" data-id="<?php echo esc_attr( (int) $s->user_id ); ?>"><?php esc_html_e( 'Unblock', 'geeta-parayan-booking' ); ?></button>
							<button class="btn btn-sm btn-outline-secondary gpn-block" data-id="<?php echo esc_attr( (int) $s->user_id ); ?>"><?php esc_html_e( 'Keep Blocked', 'geeta-parayan-booking' ); ?></button>
						</td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
