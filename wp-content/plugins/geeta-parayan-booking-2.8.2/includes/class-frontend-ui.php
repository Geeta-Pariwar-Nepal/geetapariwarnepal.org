<?php
/**
 * Public front-end — [geeta_parayan_dashboard] shortcode, tabs, Adhyaya
 * picker, "My Bookings" dashboard and calendar history, plus AJAX handlers.
 *
 * @package Geeta_Pariwar_Parayan_Booking
 */

defined( 'ABSPATH' ) || exit;

class GPPB_Frontend_UI {

	/**
	 * Singleton instance.
	 *
	 * @var GPPB_Frontend_UI|null
	 */
	private static $instance = null;

	/**
	 * Singleton.
	 *
	 * @return GPPB_Frontend_UI
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
		add_shortcode( 'geeta_parayan_dashboard', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_action( 'wp_ajax_gppb_get_dates', array( $this, 'ajax_get_dates' ) );
		add_action( 'wp_ajax_nopriv_gppb_get_dates', array( $this, 'ajax_get_dates' ) );
		add_action( 'wp_ajax_gppb_get_calendar', array( $this, 'ajax_get_calendar' ) );
		add_action( 'wp_ajax_nopriv_gppb_get_calendar', array( $this, 'ajax_get_calendar' ) );
		add_action( 'wp_ajax_gppb_get_availability', array( $this, 'ajax_get_availability' ) );
		add_action( 'wp_ajax_nopriv_gppb_get_availability', array( $this, 'ajax_get_availability' ) );
		add_action( 'wp_ajax_gppb_submit_booking', array( $this, 'ajax_submit_booking' ) );
		add_action( 'wp_ajax_nopriv_gppb_submit_booking', array( $this, 'ajax_submit_booking' ) );
		add_action( 'wp_ajax_gppb_verify_prn', array( $this, 'ajax_verify_prn' ) );
		add_action( 'wp_ajax_nopriv_gppb_verify_prn', array( $this, 'ajax_verify_prn' ) );
		add_action( 'wp_ajax_gppb_edit_booking', array( $this, 'ajax_edit_booking' ) );
		add_action( 'wp_ajax_gppb_cancel_booking', array( $this, 'ajax_cancel_booking' ) );
		add_action( 'wp_ajax_gppb_my_bookings', array( $this, 'ajax_my_bookings' ) );
		add_action( 'wp_ajax_gppb_unblock_request', array( $this, 'ajax_unblock_request' ) );
		add_action( 'wp_ajax_gppb_get_roster', array( $this, 'ajax_get_roster' ) );
		add_action( 'wp_ajax_nopriv_gppb_get_roster', array( $this, 'ajax_get_roster' ) );
	}

	/**
	 * Enqueue assets when the shortcode is present.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		wp_enqueue_style( 'gppb-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css', array(), '5.3.3' );
		wp_enqueue_script( 'gppb-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', array(), '5.3.3', true );
		wp_enqueue_style( 'gppb-public', GPPB_Helpers::asset( 'public/css/public.css' ), array( 'gppb-bootstrap' ), GPPB_VERSION );
		wp_enqueue_script( 'gppb-public', GPPB_Helpers::asset( 'public/js/public.js' ), array( 'gppb-bootstrap' ), GPPB_VERSION, true );

		wp_localize_script(
			'gppb-public',
			'GPPB_PUBLIC',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gppb_public_nonce' ),
				'i18n'    => array(
					'error'         => __( 'केही गडबड भयो । फेरि प्रयास गर्नुहोस् ।', 'geeta-parayan-booking' ),
					'required'      => __( 'कृपया आवश्यक विवरण भर्नुहोस् ।', 'geeta-parayan-booking' ),
					'processing'    => __( 'पेश हुँदैछ …', 'geeta-parayan-booking' ),
					'confirmCancel' => __( 'के तपाईं यो बुकिङ रद्द गर्न चाहनुहुन्छ?', 'geeta-parayan-booking' ),
					'chooseChapter' => __( 'कृपया अध्याय छान्नुहोस् ।', 'geeta-parayan-booking' ),
					'chooseDate'    => __( 'कृपया मिति छान्नुहोस् ।', 'geeta-parayan-booking' ),
					'loading'       => __( 'लोड हुँदैछ…', 'geeta-parayan-booking' ),
					'today'         => __( 'आज', 'geeta-parayan-booking' ),
					'booked'        => __( 'बुक भयो', 'geeta-parayan-booking' ),
					'open'          => __( 'खुला', 'geeta-parayan-booking' ),
					'partial'       => __( 'आंशिक', 'geeta-parayan-booking' ),
					'full'          => __( 'पूर्ण', 'geeta-parayan-booking' ),
					'closed'        => __( 'बन्द', 'geeta-parayan-booking' ),
					'bookNow'       => __( 'बुक गर्नुहोस्', 'geeta-parayan-booking' ),
					'available'     => __( 'उपलब्ध', 'geeta-parayan-booking' ),
					'waitlist1'     => __( 'प्रतीक्षा १', 'geeta-parayan-booking' ),
					'waitlist2'     => __( 'प्रतीक्षा २', 'geeta-parayan-booking' ),
					'noReciters'    => __( 'यस दिन कुनै पाठक भर्ना भएका छैनन् ।', 'geeta-parayan-booking' ),
					'todayReciters' => __( 'आजका पाठकहरू', 'geeta-parayan-booking' ),
					'summary'       => __( 'दिनको सारांश', 'geeta-parayan-booking' ),
					'upcoming'      => __( 'आगामी मितिहरू', 'geeta-parayan-booking' ),
					'legend'        => __( 'क्यालेन्डर संकेत', 'geeta-parayan-booking' ),
					'prevMonth'     => __( 'अघिल्लो महिना', 'geeta-parayan-booking' ),
					'nextMonth'     => __( 'अर्को महिना', 'geeta-parayan-booking' ),
					'selected'      => __( 'छानिएको मिति', 'geeta-parayan-booking' ),
					'reciter'       => __( 'पाठक', 'geeta-parayan-booking' ),
					'prn'           => __( 'PRN', 'geeta-parayan-booking' ),
					'bookedOn'      => __( 'बुकिङ समय', 'geeta-parayan-booking' ),
					'status'        => __( 'स्थिति', 'geeta-parayan-booking' ),
					'dailyCalendar' => __( 'दैनिक पारायण क्यालेन्डर', 'geeta-parayan-booking' ),
					'weeklyCalendar'=> __( 'साप्ताहिक पारायण क्यालेन्डर', 'geeta-parayan-booking' ),
					'dailyParayan'  => __( 'दैनिक पारायण', 'geeta-parayan-booking' ),
					'weeklyParayan' => __( 'साप्ताहिक पारायण', 'geeta-parayan-booking' ),
					'fullyBooked'   => __( 'सबै बुक भयो', 'geeta-parayan-booking' ),
					'noBookings'    => __( 'कुनै बुकिङ छैन', 'geeta-parayan-booking' ),
					'past'          => __( 'विगत', 'geeta-parayan-booking' ),
					'todayDaily'    => __( 'आजको दैनिक पारायण', 'geeta-parayan-booking' ),
					'todayChapters' => __( 'आजका अध्यायहरू', 'geeta-parayan-booking' ),
					'remainingChapters' => __( 'बाँकी अध्यायहरू', 'geeta-parayan-booking' ),
					'upcomingWeekly'=> __( 'आगामी साप्ताहिक पारायण', 'geeta-parayan-booking' ),
					'emptyChapter'  => __( 'खाली', 'geeta-parayan-booking' ),
					'bookAvailable' => __( 'उपलब्ध अध्याय बुक गर्नुहोस्', 'geeta-parayan-booking' ),
					'joinWaitlist'  => __( 'प्रतीक्षा सूचीमा जोडिनुहोस्', 'geeta-parayan-booking' ),
					'remaining'     => __( 'बाँकी', 'geeta-parayan-booking' ),
					'prnEnter'      => __( 'PRN प्रविष्ट गर्नुहोस्', 'geeta-parayan-booking' ),
					'prnVerify'     => __( 'PRN प्रमाणित गर्नुहोस्', 'geeta-parayan-booking' ),
					'prnVerified'   => __( 'PRN प्रमाणित भयो', 'geeta-parayan-booking' ),
					'prnInvalid'    => __( 'PRN मान्य छैन ।', 'geeta-parayan-booking' ),
					'prnExpired'    => __( 'यो PRN को अवधि समाप्त भइसकेको छ ।', 'geeta-parayan-booking' ),
					'prnBlocked'    => __( 'यो PRN ब्लक गरिएको छ ।', 'geeta-parayan-booking' ),
					'prnRequired'   => __( 'कृपया PRN प्रविष्ट गर्नुहोस् ।', 'geeta-parayan-booking' ),
					'verifyFailed'  => __( 'PRN प्रमाणीकरण असफल भयो ।', 'geeta-parayan-booking' ),
					'welcome'       => __( 'नमस्ते', 'geeta-parayan-booking' ),
					'prnGateHint'   => __( 'आफ्नो बुकिङ जारी राख्न PRN प्रविष्ट गर्नुहोस् ।', 'geeta-parayan-booking' ),
					'prnRegistered' => __( 'यो PRN दर्ता भइसकेको छ । कृपया फरक PRN प्रयोग गर्नुहोस् ।', 'geeta-parayan-booking' ),
					'noAccount'     => __( 'यो सुविधा PRN भएका साधकहरूका लागि मात्र हो ।', 'geeta-parayan-booking' ),
					'prnChange'     => __( 'फरक PRN', 'geeta-parayan-booking' ),
					'history'       => __( 'इतिहास / रोस्टर', 'geeta-parayan-booking' ),
					'regTitle'      => __( 'साधक विवरण पुष्टि', 'geeta-parayan-booking' ),
					'regIntro'      => __( 'कृपया तलका विवरण भर्नुहोस् वा पुष्टि गर्नुहोस् । * चिन्ह भएका फिल्डहरू अनिवार्य छन् ।', 'geeta-parayan-booking' ),
					'regMobile'     => __( 'मोवाइल नं', 'geeta-parayan-booking' ),
					'regMobileHelp' => __( 'केवल मोवाइल नं (देशको कोड नराख्नुहोस्) ।', 'geeta-parayan-booking' ),
					'regName'       => __( 'पुरा नाम थर', 'geeta-parayan-booking' ),
					'regDistrict'   => __( 'ठेगाना जिल्ला', 'geeta-parayan-booking' ),
					'regPlace'      => __( 'स्थान', 'geeta-parayan-booking' ),
					'regCountry'    => __( 'देश', 'geeta-parayan-booking' ),
					'regCountryCode'=> __( 'देशको कोड', 'geeta-parayan-booking' ),
					'regEmail'      => __( 'इमेल', 'geeta-parayan-booking' ),
					'regCompleted'  => __( 'साधक मात्र हो भने पुरा गरेको तह', 'geeta-parayan-booking' ),
					'regCurrent'    => __( 'हाल अध्ययन गरिरहेको हो भने तह', 'geeta-parayan-booking' ),
					'regTrainer'    => __( 'प्रशिक्षकको नाम', 'geeta-parayan-booking' ),
					'regAge'        => __( 'बाल साधक हो भने उमेर', 'geeta-parayan-booking' ),
					'regVolServices'=> __( 'सेवी हो भने मुख्य मुख्य सेवा (बढीमा तीनवटा)', 'geeta-parayan-booking' ),
					'regTrainerLvl' => __( 'प्रशिक्षक हो भने तह', 'geeta-parayan-booking' ),
					'regPrevPart'   => __( 'पछिल्लो पटक पारायणमा भाग लिएको', 'geeta-parayan-booking' ),
					'regPrevDate'   => __( 'पछिल्लो पटक भाग लिएको मिति', 'geeta-parayan-booking' ),
					'regContinue'   => __( 'जारी राख्नुहोस्', 'geeta-parayan-booking' ),
					'regSelect'     => __( '— छान्नुहोस् —', 'geeta-parayan-booking' ),
					'regWeekly'     => __( 'साप्ताहिक', 'geeta-parayan-booking' ),
					'regDaily'      => __( 'दैनिक', 'geeta-parayan-booking' ),
					'regNever'      => __( 'हालसम्म भाग लिएको छैन', 'geeta-parayan-booking' ),
					'regLevel1'     => __( 'तह १', 'geeta-parayan-booking' ),
					'regLevel2'     => __( 'तह २', 'geeta-parayan-booking' ),
					'regLevel3'     => __( 'तह ३', 'geeta-parayan-booking' ),
					'regLevel4'     => __( 'तह ४', 'geeta-parayan-booking' ),
				),
			)
		);
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'title' => GPPB_Helpers::get_setting( 'landing_title', __( 'श्रीमद्भगवद्गीता पारायण बुकिङ', 'geeta-parayan-booking' ) ),
			),
			$atts,
			'geeta_parayan_dashboard'
		);

		ob_start();
		if ( ! is_user_logged_in() ) {
			$this->render_prn_gate();
		} else {
			$this->render_dashboard( $atts );
		}
		return ob_get_clean();
	}

	/**
	 * PRN gate for anonymous visitors — no WP login required.
	 *
	 * @return void
	 */
	private function render_prn_gate() {
		include GPPB_PATH . 'public/views/prn-gate.php';
	}

	/**
	 * Main dashboard shell.
	 *
	 * @param array $atts Shortcode attrs.
	 * @return void
	 */
	private function render_dashboard( $atts ) {
		$engine  = GPPB_Booking_Engine::instance();
		$user_id = get_current_user_id();
		$meta    = $engine->user_meta( $user_id );
		$active  = $engine->active_booking( $user_id );

		include GPPB_PATH . 'public/views/dashboard.php';
	}

	/* ------------------------------------------------------------------
	 * AJAX
	 * ---------------------------------------------------------------- */

	/**
	 * Validate AJAX request: nonce + logged in.
	 *
	 * @return void
	 */
	private function guard() {
		check_ajax_referer( 'gppb_public_nonce', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in first.', 'geeta-parayan-booking' ) ) );
		}
	}

	/**
	 * Validate a public (PRN) AJAX request: nonce only, no login required.
	 *
	 * @return void
	 */
	private function guard_public() {
		check_ajax_referer( 'gppb_public_nonce', 'nonce' );
	}

	/**
	 * Lightweight per-IP rate limit for public PRN endpoints.
	 *
	 * @param string $key   Throttle bucket name.
	 * @param int    $max   Max requests per window.
	 * @param int    $mins  Window length in minutes.
	 * @return bool True when allowed.
	 */
	private function rate_limit( $key, $max, $mins = 60 ) {
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'cli';
		$tkey = 'gppb_rl_' . $key . '_' . md5( $ip );
		$data = get_transient( $tkey );
		if ( ! is_array( $data ) ) {
			$data = array( 'count' => 0, 'ts' => time() );
		}
		if ( $data['count'] >= $max ) {
			return false;
		}
		$data['count']++;
		set_transient( $tkey, $data, $mins * MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Verify a Sadhak PRN (public, no login required).
	 *
	 * @return void
	 */
	public function ajax_verify_prn() {
		$this->guard_public();
		if ( ! $this->rate_limit( 'verify_prn', 30 ) ) {
			wp_send_json_error( array( 'code' => 'rate_limited', 'message' => __( 'Too many attempts. Please try again later.', 'geeta-parayan-booking' ) ) );
		}
		$prn = isset( $_POST['prn'] ) ? sanitize_text_field( wp_unslash( $_POST['prn'] ) ) : '';
		$check = GPPB_Prn_Store::instance()->verify( $prn );
		if ( empty( $check['ok'] ) ) {
			wp_send_json_error( array( 'code' => $check['code'], 'message' => $check['message'] ) );
		}
		$sadhak = $check['sadhak'];
		wp_send_json_success(
			array(
				'prn'       => GPPB_Prn_Store::instance()->normalize( $prn ),
				'name'      => isset( $sadhak->name ) ? $sadhak->name : '',
				'maskedPhone' => isset( $sadhak->phone ) ? GPPB_Prn_Store::instance()->masked_phone( $sadhak->phone ) : '',
				'phone'     => isset( $sadhak->phone ) ? (string) $sadhak->phone : '',
				'email'     => isset( $sadhak->email ) ? (string) $sadhak->email : '',
				'validFrom' => isset( $sadhak->valid_from ) ? (string) $sadhak->valid_from : '',
				'validUntil' => isset( $sadhak->valid_until ) ? (string) $sadhak->valid_until : '',
			)
		);
	}

	/**
	 * Available dates for a slot type.
	 *
	 * @return void
	 */
	public function ajax_get_dates() {
		$this->guard_public();
		$type  = isset( $_POST['slot_type'] ) ? sanitize_key( $_POST['slot_type'] ) : 'daily';
		$dates = GPPB_Booking_Engine::instance()->available_dates( $type );
		$out   = array();
		foreach ( $dates as $date ) {
			$out[] = array(
				'value'     => $date,
				'formatted' => GPPB_Helpers::format_date( $date ),
				'nepali'    => GPPB_Helpers::nepali_date( $date )['compact'],
			);
		}
		wp_send_json_success( array( 'dates' => $out ) );
	}

	/**
	 * 18-Adhyaya availability grid for a date + slot type.
	 *
	 * @return void
	 */
	public function ajax_get_availability() {
		$this->guard_public();
		$type = isset( $_POST['slot_type'] ) ? sanitize_key( $_POST['slot_type'] ) : 'daily';
		$date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid date.', 'geeta-parayan-booking' ) ) );
		}

		/* Weekly Parayan sessions exist only on Saturdays — reject others server-side. */
		if ( 'weekly' === $type && 6 !== (int) gmdate( 'w', strtotime( $date . ' 00:00:00' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Weekly Parayan can only be booked on Saturdays.', 'geeta-parayan-booking' ) ) );
		}

		$engine = GPPB_Booking_Engine::instance();
		$user_id = get_current_user_id();
		$is_guest = ! is_user_logged_in();
		$prn      = isset( $_POST['prn'] ) ? sanitize_text_field( wp_unslash( $_POST['prn'] ) ) : '';

		$active  = $is_guest ? null : $engine->active_booking( $user_id );
		$meta    = $is_guest ? null : $engine->user_meta( $user_id );

		$chapters = $engine->availability_for_date( $type, $date );
		$occupants = $engine->occupants_for_date( $type, $date );

		$restricted        = $is_guest ? false : $engine->one_month_restriction_active( $user_id );
		$override_for_date = false;
		$guest_dupe        = false;

		/* Guest identity (PRN) — used for duplicate flags only. */
		if ( $is_guest && '' !== $prn ) {
			$prn_norm = GPPB_Prn_Store::instance()->normalize( $prn );
			$guest_dupe = $this->has_prn_booking_on_date( $prn_norm, $date );
		}

		foreach ( $chapters as &$ch ) {
			$ch['occupants'] = array();
			if ( $is_guest ) {
				$ch['overrideActive'] = false;
			} else {
				/* Booking-scoped override for this exact Adhyaya + date. */
				$ch['overrideActive'] = $restricted ? $engine->restriction_override_active( $user_id, (int) $ch['id'], $date ) : false;
				if ( $ch['overrideActive'] ) {
					$override_for_date = true;
				}
			}
			if ( ! empty( $occupants[ (int) $ch['id'] ] ) ) {
				foreach ( $occupants[ (int) $ch['id'] ] as $occ ) {
					$statuses = GPPB_Helpers::booking_statuses();
					$name     = $occ->display_name ? $occ->display_name : ( $occ->sadhak_name ? $occ->sadhak_name : __( 'साधक', 'geeta-parayan-booking' ) );
					$masked   = $occ->user_id ? GPPB_Helpers::masked_phone_for_user( (int) $occ->user_id ) : ( $occ->sadhak_prn ? GPPB_Prn_Store::instance()->masked_phone( $this->phone_for_prn( $occ->sadhak_prn ) ) : '' );
					$ch['occupants'][] = array(
						'prn'          => $occ->prn,
						'sadhak_prn'   => isset( $occ->sadhak_prn ) ? $occ->sadhak_prn : '',
						'name'         => $name,
						'status'       => $occ->booking_status,
						'status_label' => isset( $statuses[ $occ->booking_status ] ) ? $statuses[ $occ->booking_status ]['label'] : $occ->booking_status,
						'created_at'   => $occ->created_at,
						'booked_time'  => GPPB_Helpers::format_datetime( $occ->created_at ),
						'masked_phone' => $masked,
					);
				}
			}
		}
		unset( $ch );

		wp_send_json_success(
			array(
				'chapters'         => $chapters,
				'approvalStatus'   => $is_guest ? 'n/a' : $meta->teacher_approval_status,
				'accountStatus'    => $is_guest ? 'n/a' : $meta->account_status,
				'hasActiveBooking' => (bool) $active,
				'activeBookingId'  => $active ? (int) $active->id : 0,
				'activeDate'       => $active ? $active->booking_date : '',
				'restricted'       => $restricted,
				'overrideActive'   => $override_for_date,
				'restrictionMsg'   => $engine->restriction_message(),
				'guestMode'        => $is_guest,
				'guestDupe'        => $guest_dupe,
			)
		);
	}

	/**
	 * Monthly calendar + upcoming dates with per-date occupancy.
	 *
	 * @return void
	 */
	public function ajax_get_calendar() {
		$this->guard_public();
		$type  = isset( $_POST['slot_type'] ) ? sanitize_key( $_POST['slot_type'] ) : 'daily';
		$year  = isset( $_POST['year'] ) ? absint( $_POST['year'] ) : 0;
		$month = isset( $_POST['month'] ) ? absint( $_POST['month'] ) : 0; // 1-12

		$engine = GPPB_Booking_Engine::instance();
		$today  = GPPB_Helpers::today();
		$dates  = $engine->available_dates( $type ); // bookable window

		$min_date = ! empty( $dates ) ? $dates[0] : $today;
		$max_date = ! empty( $dates ) ? $dates[ count( $dates ) - 1 ] : $today;
		$bookable = array_flip( $dates );

		if ( $year < 2000 || $year > 2100 || $month < 1 || $month > 12 ) {
			$month = (int) gmdate( 'n' );
			$year  = (int) gmdate( 'Y' );
		}

		$month_start = sprintf( '%04d-%02d-01', $year, $month );
		$days_in     = (int) gmdate( 't', strtotime( $month_start ) );
		$month_end   = sprintf( '%04d-%02d-%02d', $year, $month, $days_in );

		$occupancy = $engine->occupancy_by_date( $type, $month_start, $month_end );

		/* Daily: one admin-assigned Adhyaya per date. */
		$schedule        = array();
		$adhyaya_id_by_n = array();
		$occ_by_adhyaya  = array();
		if ( 'daily' === $type ) {
			$schedule = $engine->daily_schedule();
			foreach ( $engine->adhyaya_list( 'daily' ) as $a ) {
				$adhyaya_id_by_n[ (int) $a->adhyaya_number ] = (int) $a->id;
			}
			$occ_by_adhyaya = $engine->occupancy_by_adhyaya( 'daily', $month_start, $month_end );
		}

		$weekdays = array( __( 'आइत', 'geeta-parayan-booking' ), __( 'सोम', 'geeta-parayan-booking' ), __( 'मंगल', 'geeta-parayan-booking' ), __( 'बुध', 'geeta-parayan-booking' ), __( 'बिही', 'geeta-parayan-booking' ), __( 'शुक्र', 'geeta-parayan-booking' ), __( 'शनि', 'geeta-parayan-booking' ) );

		$days = array();
		for ( $d = 1; $d <= $days_in; $d++ ) {
			$iso      = sprintf( '%04d-%02d-%02d', $year, $month, $d );
			$ts       = strtotime( $iso );
			$wd       = (int) gmdate( 'w', $ts );
			$is_bookable = isset( $bookable[ $iso ] );

			$assigned       = 0;
			$assigned_title = '';
			$occupied       = 0;
			$total          = GPPB_ADHYAYAS_TOTAL;

			if ( 'daily' === $type ) {
				$total = 1;
				if ( isset( $schedule[ $iso ] ) ) {
					$assigned       = absint( $schedule[ $iso ] );
					$assigned_title = GPPB_Helpers::adhyaya_title( $assigned );
					$aid            = isset( $adhyaya_id_by_n[ $assigned ] ) ? $adhyaya_id_by_n[ $assigned ] : 0;
					if ( $aid && isset( $occ_by_adhyaya[ $aid ][ $iso ] ) ) {
						$occupied = (int) $occ_by_adhyaya[ $aid ][ $iso ];
					}
				}
			} else {
				$occupied = isset( $occupancy[ $iso ] ) ? $occupancy[ $iso ] : 0;
			}

			if ( $is_bookable ) {
				if ( 'daily' === $type && ! $assigned ) {
					$status = 'none';
				} else {
					$status = $occupied >= $total ? 'full' : ( $occupied > 0 ? 'partial' : 'open' );
				}
			} else {
				$status = 'closed';
			}

			$days[]   = array(
				'iso'           => $iso,
				'day'           => $d,
				'weekday'       => $wd,
				'weekdayLabel'  => $weekdays[ $wd ],
				'bookable'      => $is_bookable,
				'past'          => $iso < $today,
				'today'         => $iso === $today,
				'assigned'      => $assigned,
				'assignedTitle' => $assigned_title,
				'occupied'      => $occupied,
				'total'         => $total,
				'status'        => $status,
			);
		}

		/* First weekday (0=Sun) for grid alignment. */
		$leading = (int) gmdate( 'w', strtotime( $month_start ) );

		/* Upcoming bookable dates with occupancy. */
		$upcoming = array();
		$shown    = 0;
		foreach ( $dates as $iso ) {
			if ( $iso < $today || ! isset( $bookable[ $iso ] ) ) {
				continue;
			}
			$occupied = isset( $occupancy[ $iso ] ) ? $occupancy[ $iso ] : 0;
			$upcoming[] = array(
				'iso'      => $iso,
				'formatted' => GPPB_Helpers::format_date( $iso ),
				'nepali'   => GPPB_Helpers::nepali_date( $iso )['compact'],
				'occupied' => $occupied,
				'total'    => GPPB_ADHYAYAS_TOTAL,
				'status'   => $occupied >= GPPB_ADHYAYAS_TOTAL ? 'full' : ( $occupied > 0 ? 'partial' : 'open' ),
			);
			if ( ++$shown >= 10 ) {
				break;
			}
		}

		$nav_prev = $month > 1 ? array( 'year' => $year, 'month' => $month - 1 ) : array( 'year' => $year - 1, 'month' => 12 );
		$nav_next = $month < 12 ? array( 'year' => $year, 'month' => $month + 1 ) : array( 'year' => $year + 1, 'month' => 1 );

		wp_send_json_success(
			array(
				'year'      => $year,
				'month'     => $month,
				'monthLabel' => gmdate( 'F Y', strtotime( $month_start ) ),
				'monthNepali' => GPPB_Helpers::nepali_date( $month_start )['compact'],
				'today'     => $today,
				'minDate'   => $min_date,
				'maxDate'   => $max_date,
				'leading'   => $leading,
				'weekdays'  => $weekdays,
				'days'      => $days,
				'upcoming'  => $upcoming,
				'navPrev'   => $nav_prev,
				'navNext'   => $nav_next,
			)
		);
	}

	/**
	 * Create a booking.
	 *
	 * @return void
	 */
	public function ajax_submit_booking() {
		$this->diag_log( 'submit:REQUEST ' . wp_json_encode( array( 'prn' => isset( $_POST['prn'] ) ? sanitize_text_field( wp_unslash( $_POST['prn'] ) ) : '', 'slot' => isset( $_POST['slot_type'] ) ? sanitize_key( $_POST['slot_type'] ) : '', 'date' => isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '', 'ch' => isset( $_POST['adhyaya_number'] ) ? absint( $_POST['adhyaya_number'] ) : 0, 'keys' => array_keys( $_POST ) ) ) );
		$this->guard_public();
		$this->diag_log( 'submit:PASSED_GUARD' );
		$user_id = get_current_user_id();
		$is_guest = ! is_user_logged_in();

		$slot_type = isset( $_POST['slot_type'] ) ? sanitize_key( $_POST['slot_type'] ) : 'daily';
		$date      = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$chapter   = isset( $_POST['adhyaya_number'] ) ? absint( $_POST['adhyaya_number'] ) : 0;
		$prn       = isset( $_POST['prn'] ) ? sanitize_text_field( wp_unslash( $_POST['prn'] ) ) : '';

		if ( $is_guest ) {
			$this->diag_log( 'submit:GUEST_FLOW' );
			if ( ! $this->rate_limit( 'submit_booking', 10 ) ) {
				$this->diag_log( 'submit:RATE_LIMITED' );
				wp_send_json_error( array( 'code' => 'rate_limited', 'message' => __( 'Too many attempts. Please try again later.', 'geeta-parayan-booking' ) ) );
			}
			$this->diag_log( 'submit:BEFORE_CREATE' );
			/* Guests book strictly through a verified PRN — never anonymous. */
			$result = GPPB_Booking_Engine::instance()->create_prn_booking( $prn, $chapter, $date, $slot_type );
			$this->diag_log( 'submit:AFTER_CREATE ok=' . ( empty( $result['ok'] ) ? '0' : '1' ) . ' code=' . ( isset( $result['code'] ) ? $result['code'] : '' ) . ' bid=' . ( isset( $result['booking_id'] ) ? $result['booking_id'] : 0 ) );
			if ( empty( $result['ok'] ) ) {
				wp_send_json_error( $result );
			}
			/* Persist the Google-Form-style registration details against the booking. */
			$sadhak_prn = GPPB_Prn_Store::instance()->normalize( $prn );
			$engine     = GPPB_Booking_Engine::instance();
			$this->diag_log( 'submit:BEFORE_SAVE_REG' );
			$engine->save_booking_registration( (int) $result['booking_id'], $sadhak_prn, $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$this->diag_log( 'submit:AFTER_SAVE_REG bid=' . (int) $result['booking_id'] );
			wp_send_json_success( $result );
		}

		$result = GPPB_Booking_Engine::instance()->create_booking( $user_id, $chapter, $date, $slot_type );
		if ( empty( $result['ok'] ) ) {
			wp_send_json_error( $result );
		}
		wp_send_json_success( $result );
	}

	/**
	 * Edit / correct an active booking.
	 *
	 * @return void
	 */
	public function ajax_edit_booking() {
		$this->guard();
		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
		$date       = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$chapter    = isset( $_POST['adhyaya_number'] ) ? absint( $_POST['adhyaya_number'] ) : 0;

		$result = GPPB_Booking_Engine::instance()->edit_booking( $booking_id, get_current_user_id(), $chapter, $date );
		if ( empty( $result['ok'] ) ) {
			wp_send_json_error( $result );
		}
		wp_send_json_success( $result );
	}

	/**
	 * Cancel a booking (with penalty engine).
	 *
	 * @return void
	 */
	public function ajax_cancel_booking() {
		$this->guard();
		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
		$result     = GPPB_Booking_Engine::instance()->cancel_booking( $booking_id, get_current_user_id() );
		if ( empty( $result['ok'] ) ) {
			wp_send_json_error( $result );
		}
		wp_send_json_success( $result );
	}

	/**
	 * My Bookings — active booking + history + account state.
	 *
	 * @return void
	 */
	public function ajax_my_bookings() {
		$this->guard();
		$user_id = get_current_user_id();
		$engine  = GPPB_Booking_Engine::instance();
		$meta    = $engine->user_meta( $user_id );
		$active  = $engine->active_booking( $user_id );
		$history = $engine->user_bookings( $user_id );

		$bookings = array();
		foreach ( $history as $b ) {
			$links = $engine->session_links( $b->slot_type, $b->booking_date );
			$bookings[] = array(
				'id'            => (int) $b->id,
				'prn'           => $b->prn,
				'date'          => $b->booking_date,
				'formatted'     => GPPB_Helpers::format_date( $b->booking_date ),
				'nepali'        => GPPB_Helpers::nepali_date( $b->booking_date )['compact'],
				'adhyaya'       => $b->title_nepali ? $b->title_nepali : GPPB_Helpers::adhyaya_title( (int) $b->adhyaya_number ),
				'adhyaya_number'=> (int) $b->adhyaya_number,
				'slot_type'     => $b->slot_type,
				'type_label'    => GPPB_Helpers::slot_types()[ $b->slot_type ] ?? $b->slot_type,
				'status'        => $b->booking_status,
				'status_label'  => GPPB_Helpers::booking_statuses()[ $b->booking_status ]['label'] ?? $b->booking_status,
				'zoom_link'     => $links ? $links->zoom_link : '',
				'youtube_link'  => $links ? $links->youtube_link : '',
				'active'        => ( $active && (int) $active->id === (int) $b->id ),
			);
		}

		wp_send_json_success(
			array(
				'approvalStatus' => $meta->teacher_approval_status,
				'accountStatus'  => $meta->account_status,
				'unblockReason'  => $meta->unblock_request_reason,
				'activeBooking'  => $active ? (int) $active->id : 0,
				'bookings'       => $bookings,
			)
		);
	}

	/**
	 * Submit a written unblock request.
	 *
	 * @return void
	 */
	public function ajax_unblock_request() {
		$this->guard();
		$reason = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';
		if ( '' === $reason ) {
			wp_send_json_error( array( 'message' => __( 'Please describe your request.', 'geeta-parayan-booking' ) ) );
		}
		$ok = GPPB_Booking_Engine::instance()->submit_unblock_request( get_current_user_id(), $reason );
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => __( 'Could not submit the request.', 'geeta-parayan-booking' ) ) );
		}
		wp_send_json_success( array( 'message' => __( 'Your unblock request has been sent to the administrator.', 'geeta-parayan-booking' ) ) );
	}

	/**
	 * Public roster for a past date (calendar history).
	 *
	 * @return void
	 */
	public function ajax_get_roster() {
		$this->guard_public();
		$type = isset( $_POST['slot_type'] ) ? sanitize_key( $_POST['slot_type'] ) : 'daily';
		$date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid date.', 'geeta-parayan-booking' ) ) );
		}
		$rows = GPPB_Booking_Engine::instance()->roster_for_date( $type, $date );
		$out  = array();
		foreach ( $rows as $r ) {
			$out[] = array(
				'adhyaya'   => $r->title_nepali,
				'number'    => (int) $r->adhyaya_number,
				'name'      => $r->display_name ? $r->display_name : ( isset( $r->sadhak_name ) ? $r->sadhak_name : '' ),
				'prn'       => $r->prn,
				'sadhak_prn'=> isset( $r->sadhak_prn ) ? $r->sadhak_prn : '',
				'status'    => $r->booking_status,
			);
		}
		wp_send_json_success( array( 'roster' => $out, 'date' => $date ) );
	}

	/**
	 * Whether a Sadhak PRN already has an active booking on a date.
	 *
	 * @param string $sadhak_prn Normalized PRN.
	 * @param string $date       Y-m-d.
	 * @return bool
	 */
	private function has_prn_booking_on_date( $sadhak_prn, $date ) {
		global $wpdb;
		$table = GPPB_Helpers::db()->table( 'bookings' );
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE sadhak_prn = %s AND booking_date = %s AND booking_status IN ('confirmed','waitlist_1','waitlist_2')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$sadhak_prn,
				$date
			)
		);
		return $count > 0;
	}

	/**
	 * Phone number for a Sadhak PRN ('' when unknown).
	 *
	 * @param string $sadhak_prn Normalized PRN.
	 * @return string
	 */
	private function phone_for_prn( $sadhak_prn ) {
		$sadhak = GPPB_Prn_Store::instance()->sadhak_by_prn( $sadhak_prn );
		return $sadhak && isset( $sadhak->phone ) ? (string) $sadhak->phone : '';
	}

	/**
	 * Diagnostic log for the guest submit path (temporary instrumentation).
	 *
	 * Appends a timestamped line to wp-content/gppb-submit-diag.log so the
	 * live submission can be traced step by step without any UI change.
	 *
	 * @param string $msg Message.
	 * @return void
	 */
	private function diag_log( $msg ) {
		$file = WP_CONTENT_DIR . '/gppb-submit-diag.log';
		@file_put_contents( $file, gmdate( 'Y-m-d H:i:s' ) . ' ' . $msg . "\n", FILE_APPEND | LOCK_EX );
	}
}
