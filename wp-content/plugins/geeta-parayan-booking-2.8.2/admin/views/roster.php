<?php
/**
 * Master roster + audit log page.
 *
 * @package Geeta_Pariwar_Parayan_Booking
 */

defined( 'ABSPATH' ) || exit;

$statuses = GPPB_Helpers::booking_statuses();
$slot_types = GPPB_Helpers::slot_types();
$base_url = admin_url( 'admin.php?page=gppb-roster' );
$total_pages = max( 1, (int) ceil( $result['total'] / max( 1, $result['per_page'] ) ) );
?>
<div class="wrap gpn-pb-wrap">
	<div class="d-flex align-items-center gap-3 gpn-pb-topbar mb-4">
		<div class="gpn-pb-logo">
			<svg width="46" height="46" viewBox="0 0 46 46"><circle cx="23" cy="23" r="22" fill="#7C2D12"/><circle cx="23" cy="23" r="16" fill="none" stroke="#FBBF24" stroke-width="2"/><path d="M23 14 L29 32 L23 28 L17 32 Z" fill="#FBBF24"/></svg>
		</div>
		<div>
			<h1 class="gpn-pb-title"><?php esc_html_e( 'मास्टर रोस्टर र अडिट', 'geeta-parayan-booking' ); ?></h1>
			<p class="text-muted mb-0"><?php echo esc_html( $result['total'] ); ?> <?php esc_html_e( 'records', 'geeta-parayan-booking' ); ?></p>
		</div>
	</div>

	<form method="get" class="gpn-pb-filter p-3 mb-3 rounded">
		<input type="hidden" name="page" value="gppb-roster">
		<div class="row g-2 align-items-end">
			<div class="col-md-4">
				<label class="small text-muted"><?php esc_html_e( 'Search (PRN/name/email)', 'geeta-parayan-booking' ); ?></label>
				<input type="text" name="s" value="<?php echo esc_attr( $args['search'] ); ?>" class="form-control form-control-sm">
			</div>
			<div class="col-md-2">
				<label class="small text-muted"><?php esc_html_e( 'Status', 'geeta-parayan-booking' ); ?></label>
				<select name="status" class="form-select form-select-sm">
					<option value=""><?php esc_html_e( 'All', 'geeta-parayan-booking' ); ?></option>
					<?php foreach ( $statuses as $k => $v ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $args['status'], $k ); ?>><?php echo esc_html( $v['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-2">
				<label class="small text-muted"><?php esc_html_e( 'Type', 'geeta-parayan-booking' ); ?></label>
				<select name="slot_type" class="form-select form-select-sm">
					<option value=""><?php esc_html_e( 'All', 'geeta-parayan-booking' ); ?></option>
					<?php foreach ( $slot_types as $k => $v ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $args['slot_type'], $k ); ?>><?php echo esc_html( $v ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-2">
				<label class="small text-muted"><?php esc_html_e( 'From', 'geeta-parayan-booking' ); ?></label>
				<input type="date" name="date_from" value="<?php echo esc_attr( $args['date_from'] ); ?>" class="form-control form-control-sm">
			</div>
			<div class="col-md-2">
				<label class="small text-muted"><?php esc_html_e( 'To', 'geeta-parayan-booking' ); ?></label>
				<input type="date" name="date_to" value="<?php echo esc_attr( $args['date_to'] ); ?>" class="form-control form-control-sm">
			</div>
			<div class="col-auto">
				<button class="btn btn-sm btn-warning"><?php esc_html_e( 'Filter', 'geeta-parayan-booking' ); ?></button>
				<a class="btn btn-sm btn-outline-secondary" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Reset', 'geeta-parayan-booking' ); ?></a>
			</div>
		</div>
	</form>

	<div class="gpn-pb-panel mb-4">
		<div class="card-body p-0">
			<table class="table table-hover gpn-pb-table mb-0">
				<thead>
					<tr>
						<th><?php esc_html_e( 'PRN', 'geeta-parayan-booking' ); ?></th>
						<th><?php esc_html_e( 'साधक', 'geeta-parayan-booking' ); ?></th>
						<th><?php esc_html_e( 'अध्याय', 'geeta-parayan-booking' ); ?></th>
						<th><?php esc_html_e( 'मिति', 'geeta-parayan-booking' ); ?></th>
						<th><?php esc_html_e( 'प्रकार', 'geeta-parayan-booking' ); ?></th>
						<th><?php esc_html_e( 'Status', 'geeta-parayan-booking' ); ?></th>
						<th><?php esc_html_e( 'Created', 'geeta-parayan-booking' ); ?></th>
						<th class="text-end"><?php esc_html_e( 'Action', 'geeta-parayan-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $result['items'] ) ) : ?>
					<tr><td colspan="8" class="text-center text-muted py-4"><?php esc_html_e( 'No bookings found.', 'geeta-parayan-booking' ); ?></td></tr>
				<?php else : foreach ( $result['items'] as $b ) : ?>
					<tr>
						<td><code><?php echo esc_html( $b->prn ); ?></code></td>
						<td>
							<?php echo esc_html( $b->display_name ? $b->display_name : ( $b->sadhak_name ? $b->sadhak_name : $b->user_login ) ); ?>
							<?php if ( ! empty( $b->sadhak_prn ) ) : ?>
								<small class="d-block text-muted"><?php esc_html_e( 'Sadhak PRN:', 'geeta-parayan-booking' ); ?> <?php echo esc_html( $b->sadhak_prn ); ?></small>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $b->title_nepali ); ?></td>
						<td><?php echo esc_html( GPPB_Helpers::format_date( $b->booking_date ) ); ?></td>
						<td><?php echo esc_html( $slot_types[ $b->slot_type ] ?? $b->slot_type ); ?></td>
						<td><?php echo wp_kses_post( GPPB_Helpers::status_badge( $b->booking_status ) ); ?></td>
						<td><?php echo esc_html( GPPB_Helpers::format_datetime( $b->created_at ) ); ?></td>
						<td class="text-end gpn-pb-actions">
							<?php if ( in_array( $b->booking_status, array( 'confirmed', 'waitlist_1', 'waitlist_2' ), true ) ) : ?>
								<button class="btn btn-sm btn-outline-danger gpn-delete-bk" data-id="<?php echo esc_attr( (int) $b->id ); ?>"><?php esc_html_e( 'Delete', 'geeta-parayan-booking' ); ?></button>
							<?php endif; ?>
							<?php if ( 'confirmed' === $b->booking_status && $b->booking_date < GPPB_Helpers::today() ) : ?>
								<button class="btn btn-sm btn-outline-primary gpn-complete" data-id="<?php echo esc_attr( (int) $b->id ); ?>"><?php esc_html_e( 'Mark Completed', 'geeta-parayan-booking' ); ?></button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php if ( $total_pages > 1 ) : ?>
			<div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
				<span class="small text-muted"><?php esc_html_e( 'Page', 'geeta-parayan-booking' ); ?> <?php echo (int) $result['page']; ?> / <?php echo (int) $total_pages; ?></span>
				<div class="gpn-pb-pagination">
					<nav>
						<ul class="pagination pagination-sm mb-0">
							<?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
								<li class="page-item <?php echo $i === (int) $result['page'] ? 'active' : ''; ?>">
									<a class="page-link" href="<?php echo esc_url( add_query_arg( array_merge( $args, array( 'paged' => $i ) ), $base_url ) ); ?>"><?php echo esc_html( $i ); ?></a>
								</li>
							<?php endfor; ?>
						</ul>
					</nav>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<div class="gpn-pb-panel">
		<div class="card-header p-3"><?php esc_html_e( 'Recent Audit Trail', 'geeta-parayan-booking' ); ?></div>
		<div class="card-body p-0">
			<ul class="list-group list-group-flush gpn-pb-audit-list">
				<?php if ( empty( $audit['items'] ) ) : ?>
					<li class="list-group-item text-muted text-center py-3"><?php esc_html_e( 'No audit entries yet.', 'geeta-parayan-booking' ); ?></li>
				<?php else : foreach ( $audit['items'] as $a ) : ?>
					<li class="list-group-item small">
						<span class="fw-semibold text-muted"><?php echo esc_html( GPPB_Helpers::format_datetime( $a->created_at ) ); ?></span>
						<span class="badge text-bg-light ms-2"><?php echo esc_html( $a->action ); ?></span>
						<span class="ms-2"><?php echo esc_html( $a->description ); ?></span>
						<?php if ( $a->ip ) : ?><span class="text-muted float-end">IP: <?php echo esc_html( $a->ip ); ?></span><?php endif; ?>
					</li>
				<?php endforeach; endif; ?>
			</ul>
		</div>
	</div>
</div>
