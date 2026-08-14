<?php
/**
 * Settings page.
 *
 * @package Geeta_Pariwar_Parayan_Booking
 */

defined( 'ABSPATH' ) || exit;
$d = GPPB_Helpers::default_settings();
$get = function ( $key, $default = '' ) use ( $d ) {
	return GPPB_Helpers::get_setting( $key, $d[ $key ] ?? $default );
};
?>
<div class="wrap gpn-pb-wrap">
	<div class="d-flex align-items-center gap-3 gpn-pb-topbar mb-4">
		<div class="gpn-pb-logo">
			<svg width="46" height="46" viewBox="0 0 46 46"><circle cx="23" cy="23" r="22" fill="#7C2D12"/><circle cx="23" cy="23" r="16" fill="none" stroke="#FBBF24" stroke-width="2"/><path d="M23 14 L29 32 L23 28 L17 32 Z" fill="#FBBF24"/></svg>
		</div>
		<div>
			<h1 class="gpn-pb-title"><?php esc_html_e( 'सेटिङहरू', 'geeta-parayan-booking' ); ?></h1>
			<p class="text-muted mb-0"><?php esc_html_e( 'Booking engine configuration.', 'geeta-parayan-booking' ); ?></p>
		</div>
	</div>

	<form method="post">
		<?php wp_nonce_field( 'gppb_settings_save', 'gppb_settings_nonce' ); ?>
		<div class="gpn-pb-panel mb-4">
			<div class="card-header p-3"><?php esc_html_e( 'Business Rules', 'geeta-parayan-booking' ); ?></div>
			<div class="card-body">
				<div class="row g-3">
					<div class="col-md-3">
						<label class="form-label small"><?php esc_html_e( 'Late-cancel cutoff (hours)', 'geeta-parayan-booking' ); ?></label>
						<input type="number" min="1" name="cancellation_hours" class="form-control form-control-sm" value="<?php echo esc_attr( (int) $get( 'cancellation_hours', 24 ) ); ?>">
					</div>
					<div class="col-md-3">
						<label class="form-label small"><?php esc_html_e( 'Waitlist size', 'geeta-parayan-booking' ); ?></label>
						<input type="number" min="0" max="5" name="waiting_max" class="form-control form-control-sm" value="<?php echo esc_attr( (int) $get( 'waiting_max', 2 ) ); ?>">
					</div>
					<div class="col-md-3">
						<label class="form-label small"><?php esc_html_e( 'Daily booking horizon (days)', 'geeta-parayan-booking' ); ?></label>
						<input type="number" min="1" name="daily_days_ahead" class="form-control form-control-sm" value="<?php echo esc_attr( (int) $get( 'daily_days_ahead', 60 ) ); ?>">
					</div>
					<div class="col-md-3">
						<label class="form-label small"><?php esc_html_e( 'Weekly booking horizon (Saturdays)', 'geeta-parayan-booking' ); ?></label>
						<input type="number" min="1" name="weekly_dates_ahead" class="form-control form-control-sm" value="<?php echo esc_attr( (int) $get( 'weekly_dates_ahead', 8 ) ); ?>">
					</div>
				</div>
			</div>
		</div>

		<div class="gpn-pb-panel mb-4">
			<div class="card-header p-3"><?php esc_html_e( 'Notifications', 'geeta-parayan-booking' ); ?></div>
			<div class="card-body">
				<div class="row g-3">
					<div class="col-md-6">
						<label class="form-label small"><?php esc_html_e( 'Admin email', 'geeta-parayan-booking' ); ?></label>
						<input type="email" name="admin_email" class="form-control form-control-sm" value="<?php echo esc_attr( $get( 'admin_email', 'contact@geetapariwarnepal.org' ) ); ?>">
					</div>
					<div class="col-md-6 align-self-end">
						<div class="form-check form-switch">
							<input class="form-check-input" type="checkbox" name="notify_admin" id="notify_admin" value="1" <?php checked( (int) $get( 'notify_admin', 1 ) ); ?>>
							<label class="form-check-label small" for="notify_admin"><?php esc_html_e( 'Email admin on new bookings', 'geeta-parayan-booking' ); ?></label>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="gpn-pb-panel mb-4">
			<div class="card-header p-3"><?php esc_html_e( 'Landing Page', 'geeta-parayan-booking' ); ?></div>
			<div class="card-body">
				<div class="row g-3">
					<div class="col-md-6">
						<label class="form-label small"><?php esc_html_e( 'Title', 'geeta-parayan-booking' ); ?></label>
						<input type="text" name="landing_title" class="form-control form-control-sm" value="<?php echo esc_attr( $get( 'landing_title' ) ); ?>">
					</div>
					<div class="col-md-6">
						<label class="form-label small"><?php esc_html_e( 'Subtitle', 'geeta-parayan-booking' ); ?></label>
						<input type="text" name="landing_subtitle" class="form-control form-control-sm" value="<?php echo esc_attr( $get( 'landing_subtitle' ) ); ?>">
					</div>
					<div class="col-md-4">
						<label class="form-label small"><?php esc_html_e( 'Logo URL', 'geeta-parayan-booking' ); ?></label>
						<input type="url" name="logo_url" class="form-control form-control-sm" value="<?php echo esc_attr( $get( 'logo_url' ) ); ?>">
					</div>
					<div class="col-md-4">
						<label class="form-label small"><?php esc_html_e( 'Course / app link', 'geeta-parayan-booking' ); ?></label>
						<input type="url" name="course_link" class="form-control form-control-sm" value="<?php echo esc_attr( $get( 'course_link' ) ); ?>">
					</div>
					<div class="col-md-4">
						<label class="form-label small"><?php esc_html_e( 'Contact page URL', 'geeta-parayan-booking' ); ?></label>
						<input type="url" name="contact_page" class="form-control form-control-sm" value="<?php echo esc_attr( $get( 'contact_page' ) ); ?>">
					</div>
				</div>
			</div>
		</div>

		<button class="btn btn-warning"><?php esc_html_e( 'Save Settings', 'geeta-parayan-booking' ); ?></button>
	</form>
</div>
