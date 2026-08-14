<?php
/**
 * Login prompt — shown to anonymous visitors on the booking page.
 *
 * @package Geeta_Pariwar_Parayan_Booking
 */

defined( 'ABSPATH' ) || exit;
$redirect = add_query_arg( 'redirect_to', rawurlencode( GPPB_Helpers::public_permalink() ), wp_login_url() );
?>
<div class="gpn-pb-public">
	<div class="gpn-shell">
		<div class="gpn-form-card gpn-lookup-card mx-auto my-5 text-center">
			<div class="gpn-om mb-2">ॐ</div>
			<h1 class="gpn-section-title"><?php esc_html_e( 'गीता पारायण बुकिङ', 'geeta-parayan-booking' ); ?></h1>
			<p class="text-muted mt-2 mb-4"><?php esc_html_e( 'बुकिङ गर्न कृपया आफ्नो खातामा लगइन गर्नुहोस् ।', 'geeta-parayan-booking' ); ?></p>
			<a href="<?php echo esc_url( $redirect ); ?>" class="btn btn-lg btn-warning fw-semibold px-4">
				<?php esc_html_e( 'लगइन गर्नुहोस्', 'geeta-parayan-booking' ); ?>
			</a>
			<p class="text-muted mt-4 small">
				<?php esc_html_e( 'खाता छैन?', 'geeta-parayan-booking' ); ?>
				<a href="<?php echo esc_url( wp_registration_url() ); ?>"><?php esc_html_e( 'नयाँ खाता बनाउनुहोस्', 'geeta-parayan-booking' ); ?></a>
			</p>
		</div>
	</div>
</div>
