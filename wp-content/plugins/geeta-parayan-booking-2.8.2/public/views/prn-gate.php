<?php
/**
 * PRN gate for anonymous visitors — no WP login/account/registration.
 *
 * @package Geeta_Pariwar_Parayan_Booking
 */

defined( 'ABSPATH' ) || exit;

$init_state = array(
	'guest'    => true,
	'verified' => false,
	'settings' => array(
		'landingTitle' => GPPB_Helpers::get_setting( 'landing_title', __( 'श्रीमद्भगवद्गीता पारायण बुकिङ', 'geeta-parayan-booking' ) ),
		'courseLink'   => GPPB_Helpers::get_setting( 'course_link', '' ),
		'contactPage'  => GPPB_Helpers::get_setting( 'contact_page', '' ),
		'logoUrl'      => GPPB_Helpers::get_setting( 'logo_url', '' ),
	),
);
?>
<div class="gpn-pb-public" id="gppb-root" data-state="<?php echo esc_attr( wp_json_encode( $init_state ) ); ?>">

	<div class="gpn-shell">

		<!-- Top bar -->
		<div class="gpn-dash-head">
			<div class="gpn-dash-brand">
				<?php if ( ! empty( $init_state['settings']['logoUrl'] ) ) : ?>
					<img src="<?php echo esc_url( $init_state['settings']['logoUrl'] ); ?>" alt="" class="gpn-dash-logo">
				<?php else : ?>
					<span class="gpn-brand-om">ॐ</span>
				<?php endif; ?>
				<div class="gpn-dash-brand-text">
					<span class="gpn-dash-title"><?php echo esc_html( $init_state['settings']['landingTitle'] ); ?></span>
					<span class="gpn-dash-sub">श्रीमद्भगवद्गीता पारायण</span>
				</div>
			</div>
		</div>

		<!-- PRN gate -->
		<div class="gpn-prn-gate" id="gppb-prn-gate">
			<div class="gpn-prn-card">
				<div class="gpn-prn-icon">🔐</div>
				<h3 class="gpn-prn-title"><?php esc_html_e( 'PRN मार्फत बुकिङ', 'geeta-parayan-booking' ); ?></h3>
				<p class="gpn-prn-hint"><?php esc_html_e( 'आफ्नो बुकिङ जारी राख्न PRN प्रविष्ट गर्नुहोस् ।', 'geeta-parayan-booking' ); ?></p>
				<div class="gpn-prn-field">
					<input type="text" class="form-control form-control-lg" id="gppb-prn-input" autocomplete="off" inputmode="text" placeholder="PRN">
				</div>
				<button type="button" class="btn btn-warning btn-lg w-100 fw-semibold" id="gppb-prn-verify">
					<?php esc_html_e( 'PRN प्रमाणित गर्नुहोस्', 'geeta-parayan-booking' ); ?>
				</button>
				<div class="gpn-prn-error" id="gppb-prn-error" role="alert"></div>
			</div>
		</div>

		<!-- Global message area -->
		<div class="gpn-alert-area" id="gppb-alert" role="alert" aria-live="polite"></div>

	</div>
</div>
