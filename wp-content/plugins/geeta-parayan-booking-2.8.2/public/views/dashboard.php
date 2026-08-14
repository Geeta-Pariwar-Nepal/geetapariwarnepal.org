<?php
/**
 * Main dashboard shell for [geeta_parayan_dashboard].
 *
 * @package Geeta_Pariwar_Parayan_Booking
 */

defined( 'ABSPATH' ) || exit;

$engine  = GPPB_Booking_Engine::instance();
$user_id = get_current_user_id();
$meta    = $engine->user_meta( $user_id );
$active  = $engine->active_booking( $user_id );

$init_state = array(
	'user'             => array(
		'name'  => wp_get_current_user()->display_name,
		'email' => wp_get_current_user()->user_email,
	),
	'approvalStatus'   => $meta->teacher_approval_status,
	'accountStatus'    => $meta->account_status,
	'unblockReason'    => $meta->unblock_request_reason,
	'activeBooking'    => $active ? (int) $active->id : 0,
	'settings'         => array(
		'landingTitle'   => $atts['title'],
		'courseLink'     => GPPB_Helpers::get_setting( 'course_link', '' ),
		'contactPage'    => GPPB_Helpers::get_setting( 'contact_page', '' ),
		'logoUrl'        => GPPB_Helpers::get_setting( 'logo_url', '' ),
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
			<div class="gpn-dash-user">
				<span class="gpn-dash-welcome"><?php echo esc_html( $init_state['user']['name'] ); ?></span>
				<a href="<?php echo esc_url( wp_logout_url( GPPB_Helpers::public_permalink() ) ); ?>" class="gpn-dash-logout"><?php esc_html_e( 'लगआउट', 'geeta-parayan-booking' ); ?></a>
			</div>
		</div>

		<!-- Tabs -->
		<nav class="gpn-dash-tabs" role="tablist">
			<button class="gpn-tab gpn-tab-active" data-gpn-tab="daily" role="tab"><?php esc_html_e( 'दैनिक पारायण', 'geeta-parayan-booking' ); ?></button>
			<button class="gpn-tab" data-gpn-tab="weekly" role="tab"><?php esc_html_e( 'साप्ताहिक पारायण', 'geeta-parayan-booking' ); ?></button>
			<button class="gpn-tab" data-gpn-tab="my" role="tab"><?php esc_html_e( 'मेरो बुकिङ', 'geeta-parayan-booking' ); ?></button>
			<button class="gpn-tab" data-gpn-tab="history" role="tab"><?php esc_html_e( 'इतिहास / रोस्टर', 'geeta-parayan-booking' ); ?></button>
		</nav>

		<!-- Content -->
		<div class="gpn-dash-body">

			<!-- Daily & Weekly booking tabs -->
			<div class="gpn-pane" data-gpn-pane="daily"></div>
			<div class="gpn-pane d-none" data-gpn-pane="weekly"></div>

			<!-- My Bookings -->
			<div class="gpn-pane d-none" data-gpn-pane="my">
				<div id="gppb-my-bookings"></div>
			</div>

			<!-- History / roster -->
			<div class="gpn-pane d-none" data-gpn-pane="history">
				<div id="gppb-history"></div>
			</div>

		</div>

		<!-- Global message area -->
		<div class="gpn-alert-area" id="gppb-alert" role="alert" aria-live="polite"></div>

	</div>
</div>
