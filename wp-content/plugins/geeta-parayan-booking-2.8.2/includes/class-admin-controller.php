<?php
/**
 * Admin controller — menus, views, AJAX for approvals, unblock requests,
 * roster/audit and session link management.
 *
 * @package Geeta_Pariwar_Parayan_Booking
 */

defined( 'ABSPATH' ) || exit;

class GPPB_Admin_Controller {

	/**
	 * Singleton instance.
	 *
	 * @var GPPB_Admin_Controller|null
	 */
	private static $instance = null;

	/**
	 * Singleton.
	 *
	 * @return GPPB_Admin_Controller
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hook everything.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_action( 'wp_ajax_gppb_admin_set_approval', array( $this, 'ajax_set_approval' ) );
		add_action( 'wp_ajax_gppb_admin_set_account', array( $this, 'ajax_set_account' ) );
		add_action( 'wp_ajax_gppb_admin_mark_completed', array( $this, 'ajax_mark_completed' ) );
		add_action( 'wp_ajax_gppb_admin_save_link', array( $this, 'ajax_save_link' ) );
		add_action( 'wp_ajax_gppb_admin_delete_booking', array( $this, 'ajax_delete_booking' ) );
		add_action( 'wp_ajax_gppb_admin_set_override', array( $this, 'ajax_set_override' ) );
		add_action( 'wp_ajax_gppb_admin_grant_override', array( $this, 'ajax_grant_override' ) );
		add_action( 'wp_ajax_gppb_admin_revoke_override', array( $this, 'ajax_revoke_override' ) );
		add_action( 'wp_ajax_gppb_admin_save_prn', array( $this, 'ajax_save_prn' ) );
		add_action( 'wp_ajax_gppb_admin_toggle_prn', array( $this, 'ajax_toggle_prn' ) );
		add_action( 'wp_ajax_gppb_admin_grant_prn_override', array( $this, 'ajax_grant_prn_override' ) );
	}

	/* ------------------------------------------------------------------
	 * Menus & assets
	 * ---------------------------------------------------------------- */

	/**
	 * Register admin menus.
	 *
	 * @return void
	 */
	public function register_menus() {
		$cap = GPPB_Helpers::capability();

		add_menu_page(
			__( 'Parayan Booking', 'geeta-parayan-booking' ),
			__( 'Parayan Booking', 'geeta-parayan-booking' ),
			$cap,
			'gppb-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-book-alt',
			26
		);
		add_submenu_page( 'gppb-dashboard', __( 'Dashboard', 'geeta-parayan-booking' ), __( 'Dashboard', 'geeta-parayan-booking' ), $cap, 'gppb-dashboard-live', array( $this, 'redirect_to_booking_page' ) );
		add_submenu_page( 'gppb-dashboard', __( 'Sadhak Approvals', 'geeta-parayan-booking' ), __( 'Sadhak Approvals', 'geeta-parayan-booking' ), $cap, 'gppb-approvals', array( $this, 'render_approvals' ) );
		add_submenu_page( 'gppb-dashboard', __( 'Unblock Requests', 'geeta-parayan-booking' ), __( 'Unblock Requests', 'geeta-parayan-booking' ), $cap, 'gppb-unblocks', array( $this, 'render_unblocks' ) );
		add_submenu_page( 'gppb-dashboard', __( 'Master Roster & Audit', 'geeta-parayan-booking' ), __( 'Roster & Audit', 'geeta-parayan-booking' ), $cap, 'gppb-roster', array( $this, 'render_roster' ) );
		add_submenu_page( 'gppb-dashboard', __( 'Session Links', 'geeta-parayan-booking' ), __( 'Session Links', 'geeta-parayan-booking' ), $cap, 'gppb-links', array( $this, 'render_links' ) );
		add_submenu_page( 'gppb-dashboard', __( 'Daily Schedule', 'geeta-parayan-booking' ), __( 'Daily Schedule', 'geeta-parayan-booking' ), $cap, 'gppb-schedule', array( $this, 'render_schedule' ) );
		add_submenu_page( 'gppb-dashboard', __( 'Weekly Schedule', 'geeta-parayan-booking' ), __( 'Weekly Schedule', 'geeta-parayan-booking' ), $cap, 'gppb-weekly-schedule', array( $this, 'render_weekly_schedule' ) );
		add_submenu_page( 'gppb-dashboard', __( 'PRN Master', 'geeta-parayan-booking' ), __( 'PRN Master', 'geeta-parayan-booking' ), $cap, 'gppb-prns', array( $this, 'render_prns' ) );
		add_submenu_page( 'gppb-dashboard', __( 'Settings', 'geeta-parayan-booking' ), __( 'Settings', 'geeta-parayan-booking' ), $cap, 'gppb-settings', array( $this, 'render_settings' ) );
	}

	/**
	 * Redirect the Dashboard menu item to the public booking page.
	 *
	 * Uses the same URL as the "View Booking Page" button on the admin dashboard.
	 *
	 * @return void
	 */
	public function redirect_to_booking_page() {
		wp_safe_redirect( GPPB_Helpers::public_permalink() );
		exit;
	}

	/**
	 * Enqueue admin assets on plugin pages.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'gppb-' ) && 'toplevel_page_gppb-dashboard' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'gppb-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css', array(), '5.3.3' );
		wp_enqueue_script( 'gppb-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', array(), '5.3.3', true );
		wp_enqueue_style( 'gppb-admin', GPPB_Helpers::asset( 'admin/css/admin.css' ), array( 'gppb-bootstrap' ), GPPB_VERSION );
		wp_enqueue_script( 'gppb-admin', GPPB_Helpers::asset( 'admin/js/admin.js' ), array( 'gppb-bootstrap' ), GPPB_VERSION, true );

		wp_localize_script(
			'gppb-admin',
			'GPPB_ADMIN',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gppb_admin_nonce' ),
				'i18n'    => array(
					'confirm' => __( 'Are you sure?', 'geeta-parayan-booking' ),
					'error'   => __( 'Something went wrong.', 'geeta-parayan-booking' ),
					'saved'   => __( 'Saved.', 'geeta-parayan-booking' ),
				),
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------- */

	/**
	 * Guard AJAX: nonce + capability.
	 *
	 * @return void
	 */
	private function guard() {
		check_ajax_referer( 'gppb_admin_nonce', 'nonce' );
		if ( ! GPPB_Helpers::current_admin_can() ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'geeta-parayan-booking' ) ) );
		}
	}

	/**
	 * All users with a geeta meta row (or all users) for the approval grid.
	 *
	 * @param string $filter pending|approved|rejected|blocked|all.
	 * @return array
	 */
	private function sadhaks( $filter = 'all' ) {
		global $wpdb;
		$users_meta = GPPB_Helpers::db()->table( 'users_meta' );
		$where      = ' WHERE 1=1';
		$sqlv       = array();
		if ( in_array( $filter, array( 'pending', 'approved', 'rejected' ), true ) ) {
			$where .= ' AND m.teacher_approval_status = %s';
			$sqlv[] = $filter;
		} elseif ( 'blocked' === $filter ) {
			$where .= ' AND m.account_status = %s';
			$sqlv[] = 'blocked';
		}
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.*, u.display_name, u.user_email, u.user_registered,
				        ( SELECT COUNT(*) FROM " . GPPB_Helpers::db()->table( 'bookings' ) . " b WHERE b.user_id = m.user_id ) AS booking_count
				 FROM {$users_meta} m
				 LEFT JOIN {$wpdb->users} u ON u.ID = m.user_id{$where}
				 ORDER BY m.user_id DESC",
				$sqlv
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/* ------------------------------------------------------------------
	 * AJAX
	 * ---------------------------------------------------------------- */

	/**
	 * Set teacher approval status.
	 *
	 * @return void
	 */
	public function ajax_set_approval() {
		$this->guard();
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$status  = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';
		if ( ! $user_id || ! in_array( $status, array( 'pending', 'approved', 'rejected' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'geeta-parayan-booking' ) ) );
		}
		$ok = GPPB_Booking_Engine::instance()->set_approval_status( $user_id, $status );
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => __( 'Could not update.', 'geeta-parayan-booking' ) ) );
		}
		wp_send_json_success( array( 'message' => __( 'Approval status updated.', 'geeta-parayan-booking' ) ) );
	}

	/**
	 * Set account status (block/unblock).
	 *
	 * @return void
	 */
	public function ajax_set_account() {
		$this->guard();
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$status  = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';
		if ( ! $user_id || ! in_array( $status, array( 'active', 'blocked' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'geeta-parayan-booking' ) ) );
		}
		$ok = GPPB_Booking_Engine::instance()->set_account_status( $user_id, $status );
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => __( 'Could not update.', 'geeta-parayan-booking' ) ) );
		}
		wp_send_json_success( array( 'message' => __( 'Account status updated.', 'geeta-parayan-booking' ) ) );
	}

	/**
	 * Mark a booking completed.
	 *
	 * @return void
	 */
	public function ajax_mark_completed() {
		$this->guard();
		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
		$ok         = GPPB_Booking_Engine::instance()->mark_completed( $booking_id );
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => __( 'Could not update.', 'geeta-parayan-booking' ) ) );
		}
		wp_send_json_success( array( 'message' => __( 'Marked completed.', 'geeta-parayan-booking' ) ) );
	}

	/**
	 * Save Zoom / YouTube link for a slot+date.
	 *
	 * @return void
	 */
	public function ajax_save_link() {
		$this->guard();
		$slot_type    = isset( $_POST['slot_type'] ) ? sanitize_key( $_POST['slot_type'] ) : 'daily';
		$date         = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$zoom_link    = isset( $_POST['zoom_link'] ) ? esc_url_raw( wp_unslash( $_POST['zoom_link'] ) ) : '';
		$youtube_link = isset( $_POST['youtube_link'] ) ? esc_url_raw( wp_unslash( $_POST['youtube_link'] ) ) : '';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid date.', 'geeta-parayan-booking' ) ) );
		}
		GPPB_Booking_Engine::instance()->save_session_links( $slot_type, $date, $zoom_link, $youtube_link );
		wp_send_json_success( array( 'message' => __( 'Session link saved.', 'geeta-parayan-booking' ) ) );
	}

	/**
	 * Delete/cancel a booking from the admin roster.
	 *
	 * @return void
	 */
	public function ajax_delete_booking() {
		$this->guard();
		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
		$result     = GPPB_Booking_Engine::instance()->delete_booking( $booking_id );
		if ( empty( $result['ok'] ) ) {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
		wp_send_json_success( array( 'message' => $result['message'] ) );
	}

	/**
	 * Grant or revoke the 1-month restriction override for a Sadhak.
	 *
	 * @return void
	 */
	public function ajax_set_override() {
		$this->guard();
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$grant   = isset( $_POST['grant'] ) ? 1 === absint( $_POST['grant'] ) : false;
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'geeta-parayan-booking' ) ) );
		}
		$ok = GPPB_Booking_Engine::instance()->set_restriction_override( $user_id, $grant );
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => __( 'Could not update.', 'geeta-parayan-booking' ) ) );
		}
		wp_send_json_success( array( 'message' => $grant ? __( 'Early booking override granted.', 'geeta-parayan-booking' ) : __( 'Override revoked.', 'geeta-parayan-booking' ) ) );
	}

	/**
	 * Grant a booking-scoped early-booking override for a Sadhak.
	 *
	 * Scoped to one Adhyaya on one date, so a single approval can never
	 * open up all future Parayan bookings.
	 *
	 * @return void
	 */
	public function ajax_grant_override() {
		$this->guard();
		$user_id        = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$slot_type      = isset( $_POST['slot_type'] ) ? sanitize_key( $_POST['slot_type'] ) : 'daily';
		$date           = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$adhyaya_number = isset( $_POST['adhyaya_number'] ) ? absint( $_POST['adhyaya_number'] ) : 0;

		if ( ! $user_id || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || $adhyaya_number < 1 || $adhyaya_number > GPPB_ADHYAYAS_TOTAL ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'geeta-parayan-booking' ) ) );
		}

		$adhyaya = GPPB_Booking_Engine::instance()->adhyaya_by_number( $adhyaya_number, $slot_type );
		if ( ! $adhyaya ) {
			wp_send_json_error( array( 'message' => __( 'The selected Adhyaya is not valid for this Parayan type.', 'geeta-parayan-booking' ) ) );
		}

		$id = GPPB_Booking_Engine::instance()->grant_booking_override( $user_id, (int) $adhyaya->id, $date );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Could not grant the override.', 'geeta-parayan-booking' ) ) );
		}
		wp_send_json_success( array( 'message' => __( 'Early-booking override granted for this Sadhak, Adhyaya and date.', 'geeta-parayan-booking' ) ) );
	}

	/**
	 * Revoke a booking-scoped early-booking override.
	 *
	 * @return void
	 */
	public function ajax_revoke_override() {
		$this->guard();
		$override_id = isset( $_POST['override_id'] ) ? absint( $_POST['override_id'] ) : 0;
		if ( ! $override_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'geeta-parayan-booking' ) ) );
		}
		$ok = GPPB_Booking_Engine::instance()->revoke_booking_override( $override_id );
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => __( 'Could not revoke the override.', 'geeta-parayan-booking' ) ) );
		}
		wp_send_json_success( array( 'message' => __( 'Override revoked.', 'geeta-parayan-booking' ) ) );
	}

	/**
	 * Create or update a PRN master record (admin).
	 *
	 * @return void
	 */
	public function ajax_save_prn() {
		$this->guard();
		$data = array(
			'id'          => isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0,
			'prn'         => isset( $_POST['prn'] ) ? sanitize_text_field( wp_unslash( $_POST['prn'] ) ) : '',
			'name'        => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'phone'       => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
			'email'       => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'prn_status'  => isset( $_POST['prn_status'] ) ? sanitize_key( $_POST['prn_status'] ) : 'allowed',
			'valid_from'  => isset( $_POST['valid_from'] ) ? sanitize_text_field( wp_unslash( $_POST['valid_from'] ) ) : '',
			'valid_until' => isset( $_POST['valid_until'] ) ? sanitize_text_field( wp_unslash( $_POST['valid_until'] ) ) : '',
		);
		$result = GPPB_Prn_Store::instance()->save( $data );
		if ( empty( $result['ok'] ) ) {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
		wp_send_json_success( array( 'message' => $result['message'], 'id' => $result['id'] ) );
	}

	/**
	 * Toggle a PRN's eligibility status (allowed/blocked).
	 *
	 * @return void
	 */
	public function ajax_toggle_prn() {
		$this->guard();
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';
		if ( ! $id || ! in_array( $status, array( 'allowed', 'blocked' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'geeta-parayan-booking' ) ) );
		}
		global $wpdb;
		$ok = $wpdb->update(
			GPPB_Prn_Store::instance()->table(),
			array( 'prn_status' => $status, 'updated_by' => get_current_user_id(), 'updated_at' => GPPB_Helpers::now() ),
			array( 'id' => $id )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		if ( false === $ok ) {
			wp_send_json_error( array( 'message' => __( 'Could not update.', 'geeta-parayan-booking' ) ) );
		}
		GPPB_Audit_Log::add( null, 'prn_status', 'prn', $id, sprintf( 'PRN status set to %s.', $status ) );
		wp_send_json_success( array( 'message' => __( 'PRN status updated.', 'geeta-parayan-booking' ) ) );
	}

	/**
	 * Grant a booking-scoped override to a Sadhak PRN.
	 *
	 * @return void
	 */
	public function ajax_grant_prn_override() {
		$this->guard();
		$sadhak_prn     = isset( $_POST['sadhak_prn'] ) ? sanitize_text_field( wp_unslash( $_POST['sadhak_prn'] ) ) : '';
		$sadhak_name    = isset( $_POST['sadhak_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sadhak_name'] ) ) : '';
		$slot_type      = isset( $_POST['slot_type'] ) ? sanitize_key( $_POST['slot_type'] ) : 'daily';
		$date           = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$adhyaya_number = isset( $_POST['adhyaya_number'] ) ? absint( $_POST['adhyaya_number'] ) : 0;

		if ( '' === $sadhak_prn || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || $adhyaya_number < 1 || $adhyaya_number > GPPB_ADHYAYAS_TOTAL ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'geeta-parayan-booking' ) ) );
		}

		$adhyaya = GPPB_Booking_Engine::instance()->adhyaya_by_number( $adhyaya_number, $slot_type );
		if ( ! $adhyaya ) {
			wp_send_json_error( array( 'message' => __( 'The selected Adhyaya is not valid for this Parayan type.', 'geeta-parayan-booking' ) ) );
		}

		$id = GPPB_Booking_Engine::instance()->grant_prn_override( $sadhak_prn, $sadhak_name, (int) $adhyaya->id, $date );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Could not grant the override.', 'geeta-parayan-booking' ) ) );
		}
		wp_send_json_success( array( 'message' => __( 'Early-booking override granted for this Sadhak, Adhyaya and date.', 'geeta-parayan-booking' ) ) );
	}

	/* ------------------------------------------------------------------
	 * Renderers
	 * ---------------------------------------------------------------- */

	/**
	 * Dashboard — stats overview.
	 *
	 * @return void
	 */
	public function render_dashboard() {
		global $wpdb;
		$engine  = GPPB_Booking_Engine::instance();
		$bookings = GPPB_Helpers::db()->table( 'bookings' );
		$users_meta = GPPB_Helpers::db()->table( 'users_meta' );

		$stats = array(
			'pending'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$users_meta} WHERE teacher_approval_status = 'pending'" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'approved'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$users_meta} WHERE teacher_approval_status = 'approved'" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'blocked'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$users_meta} WHERE account_status = 'blocked'" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'active'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings} WHERE booking_status IN ('confirmed','waitlist_1','waitlist_2') AND booking_date >= '" . GPPB_Helpers::today() . "'" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'completed'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings} WHERE booking_status = 'completed'" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$upcoming = $engine->search_bookings( array( 'status' => 'confirmed', 'date_from' => GPPB_Helpers::today(), 'per_page' => 8 ) );

		include GPPB_PATH . 'admin/views/dashboard.php';
	}

	/**
	 * Sadhak approvals page.
	 *
	 * @return void
	 */
	public function render_approvals() {
		$filter = isset( $_GET['filter'] ) ? sanitize_key( $_GET['filter'] ) : 'pending'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $filter, array( 'all', 'pending', 'approved', 'rejected' ), true ) ) {
			$filter = 'pending';
		}
		$sadhaks = $this->sadhaks( $filter );

		$engine        = GPPB_Booking_Engine::instance();
		$overrides     = $engine->overrides( array( 'status' => 'active', 'limit' => 200 ) );
		$override_map  = array();
		foreach ( $overrides as $ov ) {
			$override_map[ (int) $ov->user_id ] = ( $override_map[ (int) $ov->user_id ] ?? 0 ) + 1;
		}

		include GPPB_PATH . 'admin/views/approvals.php';
	}

	/**
	 * Unblock requests page.
	 *
	 * @return void
	 */
	public function render_unblocks() {
		$blocked = $this->sadhaks( 'blocked' );
		include GPPB_PATH . 'admin/views/unblocks.php';
	}

	/**
	 * Master roster + audit page.
	 *
	 * @return void
	 */
	public function render_roster() {
		$engine = GPPB_Booking_Engine::instance();
		$args   = array(
			'search'    => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'status'    => isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'slot_type' => isset( $_GET['slot_type'] ) ? sanitize_key( $_GET['slot_type'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'date_to'   => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'page'      => isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
		$result = $engine->search_bookings( $args );
		$audit  = GPPB_Audit_Log::search( array( 'page' => 1, 'per_page' => 25 ) );

		include GPPB_PATH . 'admin/views/roster.php';
	}

	/**
	 * Session links page.
	 *
	 * @return void
	 */
	public function render_links() {
		global $wpdb;
		$table = GPPB_Helpers::db()->table( 'session_links' );
		$links = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY session_date DESC, slot_type ASC LIMIT 200" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		include GPPB_PATH . 'admin/views/links.php';
	}

	/**
	 * Daily Schedule page — coordinator assigns date => Adhyaya.
	 *
	 * @return void
	 */
	public function render_schedule() {
		$engine = GPPB_Booking_Engine::instance();

		if ( isset( $_POST['gppb_schedule_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['gppb_schedule_nonce'] ), 'gppb_schedule_save' ) ) {
			$dates   = isset( $_POST['date'] ) && is_array( $_POST['date'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['date'] ) ) : array();
			$numbers = isset( $_POST['adhyaya'] ) && is_array( $_POST['adhyaya'] ) ? array_map( 'absint', wp_unslash( $_POST['adhyaya'] ) ) : array();
			$saved   = 0;
			foreach ( $dates as $i => $date ) {
				if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) && isset( $numbers[ $i ] ) && $engine->set_daily_schedule( $date, $numbers[ $i ] ) ) {
					$saved++;
				}
			}
			GPPB_Audit_Log::add( null, 'schedule_saved', 'settings', 0, sprintf( 'Daily schedule updated (%d rows).', $saved ) );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Daily schedule saved.', 'geeta-parayan-booking' ) . '</p></div>';
		}

		$schedule = $engine->daily_schedule();
		$dates    = $engine->available_dates( 'daily', 60 );
		$occ      = array();
		if ( ! empty( $dates ) ) {
			$occ = $engine->occupancy_by_date( 'daily', $dates[0], $dates[ count( $dates ) - 1 ] );
		}
		$today = GPPB_Helpers::today();

		include GPPB_PATH . 'admin/views/schedule.php';
	}

	/**
	 * Weekly Schedule page — view upcoming Saturday dates with session links.
	 *
	 * @return void
	 */
	public function render_weekly_schedule() {
		$engine = GPPB_Booking_Engine::instance();

		if ( isset( $_POST['gppb_weekly_link_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['gppb_weekly_link_nonce'] ), 'gppb_weekly_link_save' ) ) {
			$dates   = isset( $_POST['date'] ) && is_array( $_POST['date'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['date'] ) ) : array();
			$zoom    = isset( $_POST['zoom_link'] ) && is_array( $_POST['zoom_link'] ) ? array_map( 'esc_url_raw', wp_unslash( $_POST['zoom_link'] ) ) : array();
			$youtube = isset( $_POST['youtube_link'] ) && is_array( $_POST['youtube_link'] ) ? array_map( 'esc_url_raw', wp_unslash( $_POST['youtube_link'] ) ) : array();
			$saved   = 0;
			foreach ( $dates as $i => $date ) {
				if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) && $engine->save_session_links( 'weekly', $date, $zoom[ $i ] ?? '', $youtube[ $i ] ?? '' ) ) {
					$saved++;
				}
			}
			GPPB_Audit_Log::add( null, 'weekly_links_saved', 'settings', 0, sprintf( 'Weekly session links updated (%d rows).', $saved ) );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Weekly session links saved.', 'geeta-parayan-booking' ) . '</p></div>';
		}

		$dates = $engine->available_dates( 'weekly', 12 ); // 12 upcoming Saturdays
		$occ   = array();
		if ( ! empty( $dates ) ) {
			$occ = $engine->occupancy_by_date( 'weekly', $dates[0], $dates[ count( $dates ) - 1 ] );
		}
		$links = array();
		if ( ! empty( $dates ) ) {
			foreach ( $dates as $date ) {
				$links[ $date ] = $engine->session_links( 'weekly', $date );
			}
		}
		$today = GPPB_Helpers::today();

		include GPPB_PATH . 'admin/views/weekly-schedule.php';
	}

	/**
	 * PRN Master page — add/edit/block Sadhak PRN records.
	 *
	 * @return void
	 */
	public function render_prns() {
		$term  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$store = GPPB_Prn_Store::instance();
		$store->ensure_master();
		$rows = $store->search( $term, 200 );

		include GPPB_PATH . 'admin/views/prns.php';
	}

	/**
	 * Settings page.
	 *
	 * @return void
	 */
	public function render_settings() {
		if ( isset( $_POST['gppb_settings_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['gppb_settings_nonce'] ), 'gppb_settings_save' ) ) {
			$fields = array( 'cancellation_hours', 'waiting_max', 'daily_days_ahead', 'weekly_dates_ahead', 'admin_email', 'landing_title', 'landing_subtitle', 'course_link', 'contact_page', 'logo_url' );
			foreach ( $fields as $key ) {
				$value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
				GPPB_Helpers::set_setting( $key, $value );
			}
			GPPB_Helpers::set_setting( 'notify_admin', isset( $_POST['notify_admin'] ) ? 1 : 0 );
			GPPB_Audit_Log::add( null, 'settings_saved', 'settings', 0, 'Settings updated.' );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'geeta-parayan-booking' ) . '</p></div>';
		}
		include GPPB_PATH . 'admin/views/settings.php';
	}
}
