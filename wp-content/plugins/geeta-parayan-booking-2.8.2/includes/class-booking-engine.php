<?php
/**
 * Booking Engine — all business rules live here.
 *
 * Rules enforced:
 *  1. Teacher-approval gateway (no booking before teacher approval).
 *  2. Continuous open applications with duplication control — at most one
 *     active booking (confirmed / waitlist_1 / waitlist_2 with a future or
 *     today date) per user; new bookings unlock only after completion.
 *  3. Capacity engine — 1 primary + 2 waitlist per Adhyaya per session
 *     (first-come, first-served).
 *  4. Cancellation & 24-hour late-cancellation penalty with account block
 *     and written unblock requests.
 *  5. Waitlist auto-promotion on cancellation, with email notification.
 *
 * @package Geeta_Pariwar_Parayan_Booking
 */

defined( 'ABSPATH' ) || exit;

class GPPB_Booking_Engine {

	/**
	 * Singleton instance.
	 *
	 * @var GPPB_Booking_Engine|null
	 */
	private static $instance = null;

	/**
	 * Singleton.
	 *
	 * @return GPPB_Booking_Engine
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {}

	/**
	 * Auto-mark confirmed sessions completed once their date has passed,
	 * and close abandoned waitlist rows. Runs on init.
	 *
	 * @return void
	 */
	public function maybe_auto_complete() {
		global $wpdb;
		$table = GPPB_Helpers::db()->table( 'bookings' );
		$today = GPPB_Helpers::today();

		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET booking_status = 'completed', updated_at = %s WHERE booking_status = 'confirmed' AND booking_date < %s", GPPB_Helpers::now(), $today ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET booking_status = 'cancelled', updated_at = %s WHERE booking_status IN ('waitlist_1','waitlist_2') AND booking_date < %s", GPPB_Helpers::now(), $today ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		/* Expire early-booking overrides whose target date has passed. */
		$overrides = GPPB_Helpers::db()->table( 'overrides' );
		$wpdb->query( $wpdb->prepare( "UPDATE {$overrides} SET status = 'revoked', updated_at = %s WHERE status = 'active' AND booking_date < %s", GPPB_Helpers::now(), $today ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/* ------------------------------------------------------------------
	 * User meta (teacher approval / account status)
	 * ---------------------------------------------------------------- */

	/**
	 * Get (and lazily create) a user's meta row.
	 *
	 * @param int $user_id WP user id.
	 * @return object
	 */
	public function user_meta( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		$table   = GPPB_Helpers::db()->table( 'users_meta' );
		$row     = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $row ) {
			return $row;
		}
		$now = GPPB_Helpers::now();
		GPPB_Helpers::db()->insert(
			'users_meta',
			array(
				'user_id'                 => $user_id,
				'teacher_approval_status' => 'pending',
				'account_status'          => 'active',
				'booking_override'        => 0,
				'created_at'              => $now,
				'updated_at'              => $now,
			)
		);
		return (object) array(
			'user_id'                 => $user_id,
			'teacher_approval_status' => 'pending',
			'account_status'          => 'active',
			'booking_override'        => 0,
			'unblock_request_reason'  => null,
			'created_at'              => $now,
			'updated_at'              => $now,
		);
	}

	/**
	 * Whether the user is teacher-approved.
	 *
	 * @param int $user_id WP user id.
	 * @return bool
	 */
	public function is_teacher_approved( $user_id ) {
		return 'approved' === $this->user_meta( $user_id )->teacher_approval_status;
	}

	/**
	 * Whether the user's account is blocked.
	 *
	 * @param int $user_id WP user id.
	 * @return bool
	 */
	public function is_account_blocked( $user_id ) {
		return 'blocked' === $this->user_meta( $user_id )->account_status;
	}

	/**
	 * Set teacher approval status.
	 *
	 * @param int    $user_id WP user id.
	 * @param string $status  pending|approved|rejected.
	 * @return bool
	 */
	public function set_approval_status( $user_id, $status ) {
		if ( ! in_array( $status, array( 'pending', 'approved', 'rejected' ), true ) ) {
			return false;
		}
		$user_id = absint( $user_id );
		$this->user_meta( $user_id );
		$ok = GPPB_Helpers::db()->update( 'users_meta', array( 'teacher_approval_status' => $status, 'updated_at' => GPPB_Helpers::now() ), array( 'user_id' => $user_id ) );
		GPPB_Audit_Log::add( null, 'approval_' . $status, 'user', $user_id, sprintf( 'Teacher approval set to %s.', $status ) );
		return false !== $ok;
	}

	/**
	 * Set account status (unblock) and optionally clear the request reason.
	 *
	 * @param int    $user_id WP user id.
	 * @param string $status  active|blocked.
	 * @return bool
	 */
	public function set_account_status( $user_id, $status ) {
		if ( ! in_array( $status, array( 'active', 'blocked' ), true ) ) {
			return false;
		}
		$user_id = absint( $user_id );
		$this->user_meta( $user_id );
		$data = array( 'account_status' => $status, 'updated_at' => GPPB_Helpers::now() );
		if ( 'active' === $status ) {
			$data['unblock_request_reason'] = null;
		}
		$ok = GPPB_Helpers::db()->update( 'users_meta', $data, array( 'user_id' => $user_id ) );
		GPPB_Audit_Log::add( null, 'account_' . $status, 'user', $user_id, sprintf( 'Account status set to %s.', $status ) );
		return false !== $ok;
	}

	/**
	 * Store a blocked user's written unblock request.
	 *
	 * @param int    $user_id WP user id.
	 * @param string $reason  Message.
	 * @return bool
	 */
	public function submit_unblock_request( $user_id, $reason ) {
		$user_id = absint( $user_id );
		$reason  = sanitize_textarea_field( $reason );
		if ( '' === $reason ) {
			return false;
		}
		$this->user_meta( $user_id );
		$ok = GPPB_Helpers::db()->update(
			'users_meta',
			array(
				'unblock_request_reason' => $reason,
				'updated_at'             => GPPB_Helpers::now(),
			),
			array( 'user_id' => $user_id )
		);
		GPPB_Audit_Log::add( null, 'unblock_request', 'user', $user_id, 'Unblock request submitted.' );
		return false !== $ok;
	}

	/* ------------------------------------------------------------------
	 * 1-month restriction + admin override
	 * ---------------------------------------------------------------- */

	/**
	 * Date of the Sadhak's most recent completed recitation (Y-m-d).
	 *
	 * The Sadhak identity is the stable WP user id — the same key used
	 * everywhere in this booking system — so the restriction holds across
	 * Daily and Weekly Parayan (they share the bookings table) and never
	 * depends on email.
	 *
	 * @param int $user_id WP user id.
	 * @return string Y-m-d or '' when none.
	 */
	public function last_completed_date( $user_id ) {
		global $wpdb;
		$table = GPPB_Helpers::db()->table( 'bookings' );
		$date  = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(booking_date) FROM {$table} WHERE user_id = %d AND booking_status = 'completed'", absint( $user_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $date ? (string) $date : '';
	}

	/**
	 * Whether the 1-month restriction currently blocks the Sadhak.
	 *
	 * Default window is 30 days from the completed recitation date; filter
	 * with 'gppb_restriction_days'.
	 *
	 * @param int $user_id WP user id.
	 * @return bool
	 */
	public function one_month_restriction_active( $user_id ) {
		$last = $this->last_completed_date( $user_id );
		if ( '' === $last ) {
			return false;
		}
		$days      = max( 1, (int) apply_filters( 'gppb_restriction_days', 30 ) );
		$threshold = gmdate( 'Y-m-d', strtotime( $last . ' + ' . $days . ' days' ) );
		return GPPB_Helpers::today() < $threshold;
	}

	/**
	 * Whether the admin has granted this Sadhak an early-booking override.
	 *
	 * Overrides are booking-scoped: a row matches only the exact
	 * (sadhak, Adhyaya, date) it was granted for, is consumed when that
	 * booking is created and recorded in the audit log. The legacy
	 * per-user single-use flag is still honoured for backward
	 * compatibility with overrides granted before version 2.6.
	 *
	 * @param int    $user_id    WP user id.
	 * @param int    $adhyaya_id Adhyaya id (0 = user-level check only).
	 * @param string $date       Y-m-d ('' = user-level check only).
	 * @return bool
	 */
	public function restriction_override_active( $user_id, $adhyaya_id = 0, $date = '' ) {
		/* Legacy per-user single-use flag (overrides granted pre-2.6). */
		if ( 1 === (int) $this->user_meta( $user_id )->booking_override ) {
			return true;
		}
		/* Booking-scoped override for the exact Adhyaya + date. */
		return null !== $this->active_override_for( $user_id, $adhyaya_id, $date );
	}

	/**
	 * Grant or revoke the 1-month restriction override.
	 *
	 * @param int  $user_id WP user id.
	 * @param bool $grant   True grants, false revokes.
	 * @return bool
	 */
	public function set_restriction_override( $user_id, $grant ) {
		$user_id = absint( $user_id );
		if ( $user_id < 1 ) {
			return false;
		}
		$this->user_meta( $user_id );
		$value = $grant ? 1 : 0;
		$ok    = GPPB_Helpers::db()->update(
			'users_meta',
			array(
				'booking_override' => $value,
				'updated_at'       => GPPB_Helpers::now(),
			),
			array( 'user_id' => $user_id )
		);
		GPPB_Audit_Log::add(
			null,
			$grant ? 'override_granted' : 'override_revoked',
			'user',
			$user_id,
			$grant ? '1-month booking restriction override granted by admin.' : '1-month booking restriction override revoked.'
		);
		return false !== $ok;
	}

	/**
	 * Get the active booking-scoped override row for a Sadhak + Adhyaya + date.
	 *
	 * @param int    $user_id    WP user id.
	 * @param int    $adhyaya_id Adhyaya id.
	 * @param string $date       Y-m-d.
	 * @return object|null
	 */
	public function active_override_for( $user_id, $adhyaya_id, $date ) {
		global $wpdb;
		$user_id    = absint( $user_id );
		$adhyaya_id = absint( $adhyaya_id );
		if ( ! $user_id || ! $adhyaya_id || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ) {
			return null;
		}
		$table = GPPB_Helpers::db()->table( 'overrides' );

		/* Exact booking-scoped match first. */
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND adhyaya_id = %d AND booking_date = %s AND status = 'active' ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$adhyaya_id,
				$date
			)
		);
		if ( $row ) {
			return $row;
		}

		/*
		 * Daily fallback: a daily early-booking override grants ONE early
		 * booking of the currently scheduled daily Adhyaya for that date,
		 * even when the Adhyaya stored on the override differs from the
		 * schedule assignment (e.g. the schedule was set after the override
		 * was granted, or the admin picked a different chapter in the modal).
		 * This keeps the override usable for the actual session the Sadhak
		 * is being allowed to book. Weekly remains strictly chapter-scoped
		 * so one override can never unlock every weekly chapter.
		 */
		$adhyaya = $this->adhyaya( $adhyaya_id );
		if ( $adhyaya && 'daily' === $adhyaya->slot_type ) {
			$assigned = $this->daily_adhyaya_for_date( $date );
			if ( $assigned && (int) $adhyaya->adhyaya_number === $assigned ) {
				return $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$table} WHERE user_id = %d AND slot_type = 'daily' AND booking_date = %s AND status = 'active' ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$user_id,
						$date
					)
				);
			}
		}

		return null;
	}

	/**
	 * Get the active booking-scoped override row for a Sadhak PRN.
	 *
	 * @param string $sadhak_prn Sadhak PRN.
	 * @param int    $adhyaya_id Adhyaya id.
	 * @param string $date       Y-m-d.
	 * @return object|null
	 */
	public function active_override_for_prn( $sadhak_prn, $adhyaya_id, $date ) {
		global $wpdb;
		$sadhak_prn = GPPB_Prn_Store::instance()->normalize( $sadhak_prn );
		$adhyaya_id = absint( $adhyaya_id );
		if ( '' === $sadhak_prn || ! $adhyaya_id || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ) {
			return null;
		}
		$table = GPPB_Helpers::db()->table( 'overrides' );

		/* Exact booking-scoped match first. */
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE sadhak_prn = %s AND adhyaya_id = %d AND booking_date = %s AND status = 'active' ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$sadhak_prn,
				$adhyaya_id,
				$date
			)
		);
		if ( $row ) {
			return $row;
		}

		/* Daily fallback: a daily override grants ONE early booking of the
		 * currently scheduled daily Adhyaya for that date. */
		$adhyaya = $this->adhyaya( $adhyaya_id );
		if ( $adhyaya && 'daily' === $adhyaya->slot_type ) {
			$assigned = $this->daily_adhyaya_for_date( $date );
			if ( $assigned && (int) $adhyaya->adhyaya_number === $assigned ) {
				return $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$table} WHERE sadhak_prn = %s AND slot_type = 'daily' AND booking_date = %s AND status = 'active' ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$sadhak_prn,
						$date
					)
				);
			}
		}

		return null;
	}

	/**
	 * Grant a booking-scoped early-booking override to a Sadhak PRN.
	 *
	 * Records the actual Sadhak (name + PRN) — never an anonymous booking.
	 *
	 * @param string $sadhak_prn Sadhak PRN.
	 * @param string $sadhak_name Sadhak name.
	 * @param int    $adhyaya_id Adhyaya id.
	 * @param string $date       Y-m-d.
	 * @return int|false
	 */
	public function grant_prn_override( $sadhak_prn, $sadhak_name, $adhyaya_id, $date ) {
		$sadhak_prn = GPPB_Prn_Store::instance()->normalize( $sadhak_prn );
		$adhyaya_id = absint( $adhyaya_id );
		$date       = (string) $date;
		$adhyaya    = $this->adhyaya( $adhyaya_id );
		if ( '' === $sadhak_prn || ! $adhyaya || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return false;
		}

		/* Weekly overrides are only valid on Saturdays — the override
		 * bypasses eligibility restrictions, never the Saturday day rule. */
		if ( 'weekly' === $adhyaya->slot_type && 6 !== (int) gmdate( 'w', strtotime( $date . ' 00:00:00' ) ) ) {
			return false;
		}

		$existing = $this->active_override_for_prn( $sadhak_prn, $adhyaya_id, $date );
		if ( $existing ) {
			return (int) $existing->id;
		}

		$now = GPPB_Helpers::now();
		$id  = GPPB_Helpers::db()->insert(
			'overrides',
			array(
				'user_id'        => 0,
				'sadhak_prn'     => $sadhak_prn,
				'sadhak_name'    => sanitize_text_field( $sadhak_name ),
				'slot_type'      => $adhyaya->slot_type,
				'adhyaya_id'     => $adhyaya_id,
				'adhyaya_number' => (int) $adhyaya->adhyaya_number,
				'booking_date'   => $date,
				'status'         => 'active',
				'created_at'     => $now,
				'updated_at'     => $now,
			)
		);
		if ( ! $id ) {
			return false;
		}

		GPPB_Audit_Log::add(
			null,
			'override_granted',
			'override',
			$id,
			sprintf( 'Early-booking override granted to PRN %s (%s) for Adhyaya %d on %s.', $sadhak_prn, sanitize_text_field( $sadhak_name ), (int) $adhyaya->adhyaya_number, $date )
		);
		return $id;
	}

	/**
	 * Grant a booking-scoped early-booking override.
	 *
	 * The override only bypasses the 1-month restriction for the exact
	 * (sadhak, Adhyaya, date) combination, so one approval can never open
	 * up all future Parayan bookings. Returns the override row id on
	 * success (or the existing row id when one is already active).
	 *
	 * @param int    $user_id    WP user id.
	 * @param int    $adhyaya_id Adhyaya id.
	 * @param string $date       Y-m-d.
	 * @return int|false
	 */
	public function grant_booking_override( $user_id, $adhyaya_id, $date ) {
		$user_id    = absint( $user_id );
		$adhyaya_id = absint( $adhyaya_id );
		$date       = (string) $date;
		$adhyaya    = $this->adhyaya( $adhyaya_id );
		if ( ! $user_id || ! $adhyaya || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return false;
		}

		/* Weekly Parayan overrides are only valid on Saturdays — the override
		 * bypasses eligibility restrictions, never the Saturday day rule. */
		if ( 'weekly' === $adhyaya->slot_type && 6 !== (int) gmdate( 'w', strtotime( $date . ' 00:00:00' ) ) ) {
			return false;
		}

		$existing = $this->active_override_for( $user_id, $adhyaya_id, $date );
		if ( $existing ) {
			return (int) $existing->id;
		}

		$now = GPPB_Helpers::now();
		$id  = GPPB_Helpers::db()->insert(
			'overrides',
			array(
				'user_id'        => $user_id,
				'slot_type'      => $adhyaya->slot_type,
				'adhyaya_id'     => $adhyaya_id,
				'adhyaya_number' => (int) $adhyaya->adhyaya_number,
				'booking_date'   => $date,
				'status'         => 'active',
				'created_at'     => $now,
				'updated_at'     => $now,
			)
		);
		if ( ! $id ) {
			return false;
		}

		$sadhak = get_user_by( 'id', $user_id );
		$name   = $sadhak ? ( $sadhak->display_name ? $sadhak->display_name : $sadhak->user_login ) : (string) $user_id;
		GPPB_Audit_Log::add(
			null,
			'override_granted',
			'override',
			$id,
			sprintf( 'Early-booking override granted to %s (%d) for Adhyaya %d on %s.', $name, $user_id, (int) $adhyaya->adhyaya_number, $date )
		);
		return $id;
	}

	/**
	 * Revoke a booking-scoped override.
	 *
	 * @param int $override_id Override row id.
	 * @return bool
	 */
	public function revoke_booking_override( $override_id ) {
		global $wpdb;
		$override_id = absint( $override_id );
		$table       = GPPB_Helpers::db()->table( 'overrides' );
		$row         = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $override_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $row || 'active' !== $row->status ) {
			return false;
		}
		$ok = GPPB_Helpers::db()->update(
			'overrides',
			array(
				'status'     => 'revoked',
				'updated_at' => GPPB_Helpers::now(),
			),
			array( 'id' => $override_id )
		);
		if ( false === $ok ) {
			return false;
		}
		$sadhak = get_user_by( 'id', (int) $row->user_id );
		$name   = $sadhak ? ( $sadhak->display_name ? $sadhak->display_name : $sadhak->user_login ) : (string) $row->user_id;
		GPPB_Audit_Log::add(
			null,
			'override_revoked',
			'override',
			$override_id,
			sprintf( 'Early-booking override revoked for %s (%d) — Adhyaya %d on %s.', $name, (int) $row->user_id, (int) $row->adhyaya_number, $row->booking_date )
		);
		return true;
	}

	/**
	 * List booking-scoped overrides joined with the Sadhak for the admin UI.
	 *
	 * @param array $args { status, user_id, limit }.
	 * @return array
	 */
	public function overrides( $args = array() ) {
		global $wpdb;
		$defaults = array(
			'status'  => 'active',
			'user_id' => 0,
			'limit'   => 100,
		);
		$args     = wp_parse_args( $args, $defaults );

		$table = GPPB_Helpers::db()->table( 'overrides' );
		$where = ' WHERE 1=1';
		$sqlv  = array();
		if ( ! empty( $args['status'] ) ) {
			$where .= ' AND o.status = %s';
			$sqlv[] = $args['status'];
		}
		if ( ! empty( $args['user_id'] ) ) {
			$where .= ' AND o.user_id = %d';
			$sqlv[] = (int) $args['user_id'];
		}
		$limit = max( 1, (int) $args['limit'] );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT o.*, u.display_name, u.user_email
				 FROM {$table} o
				 LEFT JOIN {$wpdb->users} u ON u.ID = o.user_id{$where}
				 ORDER BY o.id DESC LIMIT %d",
				array_merge( $sqlv, array( $limit ) )
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $rows;
	}

	/**
	 * The Nepali message shown when the 1-month restriction blocks a booking.
	 *
	 * @return string
	 */
	public function restriction_message() {
		return __( 'तपाईंले हालै पारायण गर्नुभएको छ। अर्को पारायणका लागि १ महिना पूरा भएपछि मात्र बुक गर्न सक्नुहुन्छ।', 'geeta-parayan-booking' );
	}

	/* ------------------------------------------------------------------
	 * Adhyayas
	 * ---------------------------------------------------------------- */

	/**
	 * Get an Adhyaya row by id.
	 *
	 * @param int $id Adhyaya id.
	 * @return object|null
	 */
	public function adhyaya( $id ) {
		global $wpdb;
		$table = GPPB_Helpers::db()->table( 'adhyayas' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Get the Adhyaya row for a chapter number + slot type.
	 *
	 * @param int    $number    1-18.
	 * @param string $slot_type daily|weekly.
	 * @return object|null
	 */
	public function adhyaya_by_number( $number, $slot_type ) {
		global $wpdb;
		$number    = absint( $number );
		$slot_type = in_array( $slot_type, array( 'daily', 'weekly' ), true ) ? $slot_type : 'daily';
		$table     = GPPB_Helpers::db()->table( 'adhyayas' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE adhyaya_number = %d AND slot_type = %s", $number, $slot_type ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Ordered Adhyaya list for a slot type.
	 *
	 * @param string $slot_type daily|weekly.
	 * @return array
	 */
	public function adhyaya_list( $slot_type ) {
		global $wpdb;
		$slot_type = in_array( $slot_type, array( 'daily', 'weekly' ), true ) ? $slot_type : 'daily';
		$table     = GPPB_Helpers::db()->table( 'adhyayas' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE slot_type = %s ORDER BY adhyaya_number ASC", $slot_type ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/* ------------------------------------------------------------------
	 * Available session dates
	 * ---------------------------------------------------------------- */

	/**
	 * Candidate booking dates for a slot type (continuously open).
	 *
	 * @param string $slot_type daily|weekly.
	 * @param int    $count     Number of dates.
	 * @return array List of 'Y-m-d'.
	 */
	public function available_dates( $slot_type, $count = 0 ) {
		if ( 'weekly' === $slot_type ) {
			$count = $count ? $count : (int) GPPB_Helpers::get_setting( 'weekly_dates_ahead', 8 );
			$out   = array();
			$ts    = strtotime( GPPB_Helpers::today() );
			/* Find the next Saturday (including today). */
			while ( gmdate( 'w', $ts + get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS ) != 6 ) {
				$ts = strtotime( '+1 day', $ts );
			}
			for ( $i = 0; $i < $count; $i++ ) {
				$out[] = gmdate( 'Y-m-d', $ts + $i * 7 * DAY_IN_SECONDS );
			}
			return $out;
		}

		$count = $count ? $count : (int) GPPB_Helpers::get_setting( 'daily_days_ahead', 60 );
		$out   = array();
		$start = strtotime( GPPB_Helpers::today() );
		for ( $i = 0; $i < $count; $i++ ) {
			$out[] = gmdate( 'Y-m-d', $start + $i * DAY_IN_SECONDS );
		}
		return $out;
	}

	/**
	 * Get session links (Zoom / YouTube) for a slot type + date.
	 *
	 * @param string $slot_type daily|weekly.
	 * @param string $date      Y-m-d.
	 * @return object|null
	 */
	public function session_links( $slot_type, $date ) {
		global $wpdb;
		$table = GPPB_Helpers::db()->table( 'session_links' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slot_type = %s AND session_date = %s", $slot_type, $date ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Upsert session links.
	 *
	 * @param string $slot_type    daily|weekly.
	 * @param string $date         Y-m-d.
	 * @param string $zoom_link    Zoom URL.
	 * @param string $youtube_link YouTube URL.
	 * @return bool
	 */
	public function save_session_links( $slot_type, $date, $zoom_link = '', $youtube_link = '' ) {
		$table       = GPPB_Helpers::db()->table( 'session_links' );
		$slot_type   = in_array( $slot_type, array( 'daily', 'weekly' ), true ) ? $slot_type : 'daily';
		$data        = array(
			'zoom_link'    => esc_url_raw( $zoom_link ),
			'youtube_link' => esc_url_raw( $youtube_link ),
			'updated_at'   => GPPB_Helpers::now(),
		);
		$exists = $this->session_links( $slot_type, $date );
		if ( $exists ) {
			return false !== GPPB_Helpers::db()->update( 'session_links', $data, array( 'id' => (int) $exists->id ) );
		}
		$data['slot_type']    = $slot_type;
		$data['session_date'] = $date;
		$data['created_at']   = GPPB_Helpers::now();
		return 0 !== GPPB_Helpers::db()->insert( 'session_links', $data );
	}

	/* ------------------------------------------------------------------
	 * Availability / capacity engine
	 * ---------------------------------------------------------------- */

	/**
	 * Current occupancy count for an Adhyaya slot.
	 *
	 * @param int    $adhyaya_id Adhyaya id.
	 * @param string $date       Y-m-d.
	 * @return int
	 */
	public function slot_count( $adhyaya_id, $date ) {
		global $wpdb;
		$table = GPPB_Helpers::db()->table( 'bookings' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE adhyaya_id = %d AND booking_date = %s AND booking_status IN ('confirmed','waitlist_1','waitlist_2')", absint( $adhyaya_id ), $date ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Slot status for an Adhyaya on a date.
	 *
	 * @param int    $adhyaya_id Adhyaya id.
	 * @param string $date       Y-m-d.
	 * @return array{status:string,count:int,full:bool}
	 *   status: available|waitlist_1|waitlist_2|full
	 */
	public function slot_status( $adhyaya_id, $date ) {
		$count = $this->slot_count( $adhyaya_id, $date );
		if ( 0 === $count ) {
			return array( 'status' => 'available', 'count' => 0, 'full' => false );
		}
		if ( 1 === $count ) {
			return array( 'status' => 'waitlist_1', 'count' => 1, 'full' => false );
		}
		if ( 2 === $count ) {
			return array( 'status' => 'waitlist_2', 'count' => 2, 'full' => false );
		}
		return array( 'status' => 'full', 'count' => 3, 'full' => true );
	}

	/**
	 * Full 18-Adhyaya availability grid for a date + slot type.
	 *
	 * @param string $slot_type daily|weekly.
	 * @param string $date      Y-m-d.
	 * @return array
	 */
	public function availability_for_date( $slot_type, $date ) {
		$out   = array();
		$list  = $this->adhyaya_list( $slot_type );
		foreach ( $list as $adhyaya ) {
			$state = $this->slot_status( (int) $adhyaya->id, $date );
			$out[] = array(
				'id'            => (int) $adhyaya->id,
				'number'        => (int) $adhyaya->adhyaya_number,
				'title'         => $adhyaya->title_nepali,
				'status'        => $state['status'],
				'count'         => $state['count'],
				'full'          => $state['full'],
			);
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * Bookings
	 * ---------------------------------------------------------------- */

	/**
	 * The user's current active booking (if any).
	 *
	 * An active booking has status confirmed / waitlist_1 / waitlist_2 and
	 * a booking date that is today or in the future.
	 *
	 * @param int $user_id WP user id.
	 * @return object|null
	 */
	public function active_booking( $user_id ) {
		global $wpdb;
		$bookings = GPPB_Helpers::db()->table( 'bookings' );
		$adhyayas = GPPB_Helpers::db()->table( 'adhyayas' );
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT b.*, a.adhyaya_number, a.title_nepali
				 FROM {$bookings} b
				 LEFT JOIN {$adhyayas} a ON a.id = b.adhyaya_id
				 WHERE b.user_id = %d
				   AND b.booking_status IN ('confirmed','waitlist_1','waitlist_2')
				   AND b.booking_date >= %s
				 ORDER BY b.id DESC LIMIT 1",
				absint( $user_id ),
				GPPB_Helpers::today()
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Validate + create a booking. Enforces all business rules.
	 *
	 * @param int    $user_id    WP user id.
	 * @param int    $adhyaya_number Adhyaya number 1-18.
	 * @param string $date       Y-m-d.
	 * @param string $slot_type  daily|weekly.
	 * @return array{ok:bool,code:string,message:string,booking_id:int,booking:?object}
	 */
	public function create_booking( $user_id, $adhyaya_number, $date, $slot_type ) {
		$user_id    = absint( $user_id );
		$slot_type  = in_array( $slot_type, array( 'daily', 'weekly' ), true ) ? $slot_type : 'daily';
		$adhyaya    = $this->adhyaya_by_number( absint( $adhyaya_number ), $slot_type );
		$today      = GPPB_Helpers::today();

		/* --- teacher-approval gateway --- */
		if ( ! $this->is_teacher_approved( $user_id ) ) {
			return array( 'ok' => false, 'code' => 'approval_required', 'message' => __( 'Teacher approval required before booking Parayan slots.', 'geeta-parayan-booking' ), 'booking_id' => 0, 'booking' => null );
		}

		/* --- account block (late cancellation penalty) --- */
		if ( $this->is_account_blocked( $user_id ) ) {
			return array( 'ok' => false, 'code' => 'account_blocked', 'message' => __( 'Your account is locked. Please submit a written unblock request to continue.', 'geeta-parayan-booking' ), 'booking_id' => 0, 'booking' => null );
		}

		/* --- adhyaya must exist for the slot type --- */
		if ( ! $adhyaya ) {
			return array( 'ok' => false, 'code' => 'invalid_adhyaya', 'message' => __( 'The selected Adhyaya is not valid for this Parayan type.', 'geeta-parayan-booking' ), 'booking_id' => 0, 'booking' => null );
		}

		/* --- date validation --- */
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) || $date < $today ) {
			return array( 'ok' => false, 'code' => 'invalid_date', 'message' => __( 'Please choose a valid upcoming date.', 'geeta-parayan-booking' ), 'booking_id' => 0, 'booking' => null );
		}

		/* --- Weekly Parayan: must be Saturday --- */
		if ( 'weekly' === $slot_type ) {
			$weekday = (int) gmdate( 'w', strtotime( $date . ' 00:00:00' ) ); // 0=Sun, 6=Sat
			if ( 6 !== $weekday ) {
				GPPB_Audit_Log::add(
					$user_id,
					'booking_rejected',
					'booking',
					0,
					sprintf( 'Rejected weekly booking: %s is not a Saturday.', $date )
				);
				return array(
					'ok'         => false,
					'code'       => 'invalid_weekly_day',
					'message'    => __( 'Weekly Parayan can only be booked on Saturdays.', 'geeta-parayan-booking' ),
					'booking_id' => 0,
					'booking'    => null,
				);
			}
		}

		/* --- Daily: only the admin-assigned Adhyaya is bookable --- */
		if ( 'daily' === $slot_type ) {
			$assigned = $this->daily_adhyaya_for_date( $date );
			if ( ! $assigned || (int) $adhyaya->adhyaya_number !== $assigned ) {
				GPPB_Audit_Log::add(
					$user_id,
					'booking_rejected',
					'booking',
					0,
					sprintf( 'Rejected daily booking: Adhyaya %d requested for %s, but Adhyaya %d is assigned.', (int) $adhyaya->adhyaya_number, $date, $assigned )
				);
				return array(
					'ok'         => false,
					'code'       => 'adhyaya_not_scheduled',
					'message'    => __( 'This Adhyaya is not scheduled for the selected date.', 'geeta-parayan-booking' ),
					'booking_id' => 0,
					'booking'    => null,
				);
			}
		}

		/* --- duplication control: one active booking per user --- */
		$active = $this->active_booking( $user_id );
		if ( $active ) {
			return array(
				'ok'         => false,
				'code'       => 'active_booking',
				'message'    => __( 'You already have an active booking. You may edit or correct it; a new booking unlocks only after your current session is completed.', 'geeta-parayan-booking' ),
				'booking_id' => (int) $active->id,
				'booking'    => $active,
			);
		}

		/* --- 1-month restriction after a completed recitation --- */
		$override_row = null;
		$override     = false;
		if ( $this->one_month_restriction_active( $user_id ) ) {
			$override_row = $this->active_override_for( $user_id, (int) $adhyaya->id, $date );
			$override     = $this->restriction_override_active( $user_id, (int) $adhyaya->id, $date );
			GPPB_Audit_Log::add(
				$user_id,
				$override ? 'booking_allowed_override' : 'booking_blocked_restriction',
				'booking',
				0,
				$override ? sprintf( '1-month restriction active but admin override present; booking Adhyaya %d on %s allowed.', (int) $adhyaya->adhyaya_number, $date ) : sprintf( 'Booking Adhyaya %d on %s blocked by the 1-month restriction.', (int) $adhyaya->adhyaya_number, $date )
			);
			if ( ! $override ) {
				return array(
					'ok'         => false,
					'code'       => 'one_month_restriction',
					'message'    => $this->restriction_message(),
					'booking_id' => 0,
					'booking'    => null,
				);
			}
		}

		/* --- capacity engine: 1 primary + 2 waitlist, FCFS --- */
		$state = $this->slot_status( (int) $adhyaya->id, $date );
		if ( 'full' === $state['status'] ) {
			return array( 'ok' => false, 'code' => 'slot_full', 'message' => __( 'Slot & waitlist full for this Adhyaya on the selected date. Please choose another Adhyaya or date.', 'geeta-parayan-booking' ), 'booking_id' => 0, 'booking' => null );
		}

		$status = 'confirmed';
		if ( 'waitlist_1' === $state['status'] ) {
			$status = 'waitlist_1';
		} elseif ( 'waitlist_2' === $state['status'] ) {
			$status = 'waitlist_2';
		}

		$prn = $this->generate_prn();
		$now = GPPB_Helpers::now();
		$booking_id = GPPB_Helpers::db()->insert(
			'bookings',
			array(
				'prn'            => $prn,
				'user_id'        => $user_id,
				'adhyaya_id'     => (int) $adhyaya->id,
				'slot_type'      => $slot_type,
				'booking_date'   => $date,
				'booking_status' => $status,
				'created_at'     => $now,
				'updated_at'     => $now,
			)
		);

		if ( ! $booking_id ) {
			return array( 'ok' => false, 'code' => 'insert_failed', 'message' => __( 'Could not save the booking. Please try again.', 'geeta-parayan-booking' ), 'booking_id' => 0, 'booking' => null );
		}

		GPPB_Audit_Log::add(
			$user_id,
			'booking_created',
			'booking',
			$booking_id,
			sprintf( 'Booking %s created (%s) for Adhyaya %d on %s.', $prn, $status, $adhyaya->adhyaya_number, $date )
		);

		/* Consume the override that allowed this booking (scoped or legacy). */
		if ( $override_row ) {
			GPPB_Helpers::db()->update(
				'overrides',
				array(
					'status'     => 'used',
					'booking_id' => $booking_id,
					'prn'        => $prn,
					'updated_at' => $now,
				),
				array( 'id' => (int) $override_row->id )
			);
			GPPB_Audit_Log::add(
				$user_id,
				'override_consumed',
				'booking',
				$booking_id,
				sprintf( 'Booking-scoped override consumed for PRN %s (Adhyaya %d on %s).', $prn, (int) $adhyaya->adhyaya_number, $date )
			);
		} elseif ( $override ) {
			GPPB_Helpers::db()->update(
				'users_meta',
				array(
					'booking_override' => 0,
					'updated_at'       => GPPB_Helpers::now(),
				),
				array( 'user_id' => $user_id )
			);
			GPPB_Audit_Log::add( $user_id, 'override_consumed', 'user', $user_id, 'Legacy 1-month restriction override consumed for a new booking.' );
		}

		/* Notify the admin (async — never blocks the booking response). */
		if ( (int) GPPB_Helpers::get_setting( 'notify_admin', 1 ) ) {
			GPPB_Mailer::queue_async( 'admin', $booking_id );
		}

		return array(
			'ok'         => true,
			'code'       => 'created',
			'message'    => ( 'confirmed' === $status )
				? __( 'Your booking is confirmed. 🙏', 'geeta-parayan-booking' )
				: __( 'This Adhyaya is already taken — you have been added to the waiting list.', 'geeta-parayan-booking' ),
			'booking_id' => $booking_id,
			'booking'    => $this->get_booking( $booking_id ),
		);
	}

	/**
	 * Validate + create a PRN-based booking (no WP login required).
	 *
	 * Identity is the Sadhak's PRN master record — never data sent by the
	 * browser. The PRN must be registered and currently eligible for the
	 * booking date (Valid From / Valid Until). No teacher-approval gateway
	 * and no WP account exist for PRN Sadhaks; eligibility replaces the
	 * 1-month restriction for this flow.
	 *
	 * @param string $prn            Sadhak PRN.
	 * @param int    $adhyaya_number Adhyaya number 1-18.
	 * @param string $date           Y-m-d.
	 * @param string $slot_type      daily|weekly.
	 * @param bool   $allow_override Whether an admin override may bypass PRN eligibility.
	 * @return array{ok:bool,code:string,message:string,booking_id:int,booking:?object}
	 */
	public function create_prn_booking( $prn, $adhyaya_number, $date, $slot_type, $allow_override = false ) {
		global $wpdb;
		$store      = GPPB_Prn_Store::instance();
		$sadhak_prn = $store->normalize( $prn );
		$slot_type  = in_array( $slot_type, array( 'daily', 'weekly' ), true ) ? $slot_type : 'daily';
		$adhyaya    = $this->adhyaya_by_number( absint( $adhyaya_number ), $slot_type );
		$today      = GPPB_Helpers::today();

		/* --- PRN identity + eligibility --- */
		$check = $store->verify( $sadhak_prn, $date );
		$override_row = null;
		if ( ! $check['ok'] ) {
			/* An admin override may bypass blocked/expired/not-yet-valid PRNs,
			 * but never an unknown PRN (the Sadhak must exist to be recorded). */
			if ( $allow_override && 'invalid_prn' !== $check['code'] && $adhyaya ) {
				$override_row = $this->active_override_for_prn( $sadhak_prn, (int) $adhyaya->id, $date );
				if ( ! $override_row ) {
					return array( 'ok' => false, 'code' => $check['code'], 'message' => $check['message'], 'booking_id' => 0, 'booking' => null );
				}
			} else {
				return array( 'ok' => false, 'code' => $check['code'], 'message' => $check['message'], 'booking_id' => 0, 'booking' => null );
			}
		}
		$sadhak = $check['sadhak'];

		/* --- adhyaya must exist for the slot type --- */
		if ( ! $adhyaya ) {
			return array( 'ok' => false, 'code' => 'invalid_adhyaya', 'message' => __( 'The selected Adhyaya is not valid for this Parayan type.', 'geeta-parayan-booking' ), 'booking_id' => 0, 'booking' => null );
		}

		/* --- date validation --- */
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) || $date < $today ) {
			return array( 'ok' => false, 'code' => 'invalid_date', 'message' => __( 'Please choose a valid upcoming date.', 'geeta-parayan-booking' ), 'booking_id' => 0, 'booking' => null );
		}

		/* --- Weekly Parayan: must be Saturday (never overridden) --- */
		if ( 'weekly' === $slot_type ) {
			$weekday = (int) gmdate( 'w', strtotime( $date . ' 00:00:00' ) ); // 0=Sun, 6=Sat
			if ( 6 !== $weekday ) {
				return array( 'ok' => false, 'code' => 'invalid_weekly_day', 'message' => __( 'Weekly Parayan can only be booked on Saturdays.', 'geeta-parayan-booking' ), 'booking_id' => 0, 'booking' => null );
			}
		}

		/* --- Daily: only the admin-assigned Adhyaya is bookable --- */
		if ( 'daily' === $slot_type ) {
			$assigned = $this->daily_adhyaya_for_date( $date );
			if ( ! $assigned || (int) $adhyaya->adhyaya_number !== $assigned ) {
				return array( 'ok' => false, 'code' => 'adhyaya_not_scheduled', 'message' => __( 'This Adhyaya is not scheduled for the selected date.', 'geeta-parayan-booking' ), 'booking_id' => 0, 'booking' => null );
			}
		}

		/* --- duplicate: same Sadhak PRN + same date with an active booking --- */
		$table  = GPPB_Helpers::db()->table( 'bookings' );
		$dupe   = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE sadhak_prn = %s AND booking_date = %s AND booking_status IN ('confirmed','waitlist_1','waitlist_2')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$sadhak_prn,
				$date
			)
		);
		if ( $dupe > 0 ) {
			return array( 'ok' => false, 'code' => 'duplicate_prn_booking', 'message' => __( 'तपाईंले यस पारायणका लागि पहिले नै बुकिङ गरिसक्नुभएको छ।', 'geeta-parayan-booking' ), 'booking_id' => 0, 'booking' => null );
		}

		/* --- capacity engine: 1 primary + 2 waitlist, FCFS --- */
		$state = $this->slot_status( (int) $adhyaya->id, $date );
		if ( 'full' === $state['status'] ) {
			return array( 'ok' => false, 'code' => 'slot_full', 'message' => __( 'Slot & waitlist full for this Adhyaya on the selected date. Please choose another Adhyaya or date.', 'geeta-parayan-booking' ), 'booking_id' => 0, 'booking' => null );
		}

		$status = 'confirmed';
		if ( 'waitlist_1' === $state['status'] ) {
			$status = 'waitlist_1';
		} elseif ( 'waitlist_2' === $state['status'] ) {
			$status = 'waitlist_2';
		}

		$prn = $this->generate_prn();
		$now = GPPB_Helpers::now();
		$booking_id = GPPB_Helpers::db()->insert(
			'bookings',
			array(
				'prn'            => $prn,
				'user_id'        => 0,
				'sadhak_prn'     => $sadhak_prn,
				'sadhak_name'    => isset( $sadhak->name ) ? $sadhak->name : '',
				'adhyaya_id'     => (int) $adhyaya->id,
				'slot_type'      => $slot_type,
				'booking_date'   => $date,
				'booking_status' => $status,
				'created_at'     => $now,
				'updated_at'     => $now,
			)
		);

		if ( ! $booking_id ) {
			return array( 'ok' => false, 'code' => 'insert_failed', 'message' => __( 'Could not save the booking. Please try again.', 'geeta-parayan-booking' ), 'booking_id' => 0, 'booking' => null );
		}

		GPPB_Audit_Log::add(
			null,
			'booking_created',
			'booking',
			$booking_id,
			sprintf( 'Booking %s created (%s) for Sadhak PRN %s (Adhyaya %d on %s).', $prn, $status, $sadhak_prn, (int) $adhyaya->adhyaya_number, $date )
		);

		/* Consume the PRN-scoped override that allowed this booking. */
		if ( $override_row ) {
			GPPB_Helpers::db()->update(
				'overrides',
				array(
					'status'     => 'used',
					'booking_id' => $booking_id,
					'prn'        => $prn,
					'updated_at' => $now,
				),
				array( 'id' => (int) $override_row->id )
			);
			GPPB_Audit_Log::add(
				null,
				'override_consumed',
				'booking',
				$booking_id,
				sprintf( 'PRN-scoped override consumed for Sadhak %s (Adhyaya %d on %s).', $sadhak_prn, (int) $adhyaya->adhyaya_number, $date )
			);
		}

		/* Notify the admin (async — never blocks the booking response). */
		if ( (int) GPPB_Helpers::get_setting( 'notify_admin', 1 ) ) {
			GPPB_Mailer::queue_async( 'admin', $booking_id );
		}

		return array(
			'ok'         => true,
			'code'       => 'created',
			'message'    => ( 'confirmed' === $status )
				? __( 'Your booking is confirmed. 🙏', 'geeta-parayan-booking' )
				: __( 'This Adhyaya is already taken — you have been added to the waiting list.', 'geeta-parayan-booking' ),
			'booking_id' => $booking_id,
			'booking'    => $this->get_booking( $booking_id ),
		);
	}

	/**
	 * Edit / correct an active booking (change Adhyaya and/or date).
	 *
	 * The booking moves to the target slot with its status recomputed from
	 * the target occupancy (excluding itself); the freed slot is re-checked
	 * for waitlist promotion.
	 *
	 * @param int    $booking_id      Booking id.
	 * @param int    $user_id         WP user id (owner).
	 * @param int    $adhyaya_number  New Adhyaya number.
	 * @param string $date            New date Y-m-d.
	 * @return array
	 */
	public function edit_booking( $booking_id, $user_id, $adhyaya_number, $date ) {
		$booking = $this->get_booking( $booking_id );
		if ( ! $booking || (int) $booking->user_id !== absint( $user_id ) ) {
			return array( 'ok' => false, 'message' => __( 'Booking not found.', 'geeta-parayan-booking' ) );
		}
		if ( ! in_array( $booking->booking_status, array( 'confirmed', 'waitlist_1', 'waitlist_2' ), true ) ) {
			return array( 'ok' => false, 'message' => __( 'This booking can no longer be edited.', 'geeta-parayan-booking' ) );
		}

		$slot_type = $booking->slot_type;
		$adhyaya   = $this->adhyaya_by_number( absint( $adhyaya_number ), $slot_type );
		if ( ! $adhyaya ) {
			return array( 'ok' => false, 'message' => __( 'The selected Adhyaya is not valid.', 'geeta-parayan-booking' ) );
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) || $date < GPPB_Helpers::today() ) {
			return array( 'ok' => false, 'message' => __( 'Please choose a valid upcoming date.', 'geeta-parayan-booking' ) );
		}

		/* --- Weekly Parayan: must be Saturday --- */
		if ( 'weekly' === $slot_type ) {
			$weekday = (int) gmdate( 'w', strtotime( $date . ' 00:00:00' ) ); // 0=Sun, 6=Sat
			if ( 6 !== $weekday ) {
				GPPB_Audit_Log::add(
					$user_id,
					'booking_rejected',
					'booking',
					(int) $booking->id,
					sprintf( 'Rejected weekly edit: %s is not a Saturday.', $date )
				);
				return array( 'ok' => false, 'message' => __( 'Weekly Parayan can only be booked on Saturdays.', 'geeta-parayan-booking' ) );
			}
		}

		/* --- Daily: only the admin-assigned Adhyaya is bookable --- */
		if ( 'daily' === $slot_type ) {
			$assigned = $this->daily_adhyaya_for_date( $date );
			if ( ! $assigned || (int) $adhyaya->adhyaya_number !== $assigned ) {
				GPPB_Audit_Log::add(
					$user_id,
					'booking_rejected',
					'booking',
					(int) $booking->id,
					sprintf( 'Rejected daily edit: Adhyaya %d requested for %s, but Adhyaya %d is assigned.', (int) $adhyaya->adhyaya_number, $date, $assigned )
				);
				return array( 'ok' => false, 'message' => __( 'This Adhyaya is not scheduled for the selected date.', 'geeta-parayan-booking' ) );
			}
		}

		$old_adhyaya_id = (int) $booking->adhyaya_id;
		$old_date       = $booking->booking_date;

		/* Target occupancy, excluding this booking if it already sits there. */
		global $wpdb;
		$table  = GPPB_Helpers::db()->table( 'bookings' );
		$count  = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE adhyaya_id = %d AND booking_date = %s AND booking_status IN ('confirmed','waitlist_1','waitlist_2') AND id != %d",
				(int) $adhyaya->id,
				$date,
				(int) $booking->id
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $count >= 3 ) {
			return array( 'ok' => false, 'message' => __( 'The target slot is full. Please choose another Adhyaya or date.', 'geeta-parayan-booking' ) );
		}

		$new_status = 0 === $count ? 'confirmed' : ( 1 === $count ? 'waitlist_1' : 'waitlist_2' );
		$ok = GPPB_Helpers::db()->update(
			'bookings',
			array(
				'adhyaya_id'     => (int) $adhyaya->id,
				'booking_date'   => $date,
				'booking_status' => $new_status,
				'updated_at'     => GPPB_Helpers::now(),
			),
			array( 'id' => (int) $booking->id )
		);

		GPPB_Audit_Log::add( $user_id, 'booking_edited', 'booking', (int) $booking->id, sprintf( 'Edited to Adhyaya %d on %s (%s).', $adhyaya->adhyaya_number, $date, $new_status ) );

		/* Free slot promotion. */
		if ( $old_adhyaya_id && ( $old_adhyaya_id !== (int) $adhyaya->id || $old_date !== $date ) ) {
			$this->promote_waitlist( $old_adhyaya_id, $old_date );
		}

		return array( 'ok' => true, 'message' => __( 'Your booking has been corrected.', 'geeta-parayan-booking' ), 'booking' => $this->get_booking( (int) $booking->id ) );
	}

	/**
	 * Cancel a booking with the 24-hour penalty engine.
	 *
	 * @param int $booking_id Booking id.
	 * @param int $user_id    WP user id (owner).
	 * @return array
	 */
	public function cancel_booking( $booking_id, $user_id ) {
		$booking = $this->get_booking( $booking_id );
		if ( ! $booking || (int) $booking->user_id !== absint( $user_id ) ) {
			return array( 'ok' => false, 'message' => __( 'Booking not found.', 'geeta-parayan-booking' ) );
		}
		if ( ! in_array( $booking->booking_status, array( 'confirmed', 'waitlist_1', 'waitlist_2' ), true ) ) {
			return array( 'ok' => false, 'message' => __( 'This booking can no longer be cancelled online.', 'geeta-parayan-booking' ) );
		}

		$hours_left = ( strtotime( $booking->booking_date . ' 00:00:00' ) - time() ) / HOUR_IN_SECONDS;
		$cutoff     = (int) GPPB_Helpers::get_setting( 'cancellation_hours', 24 );
		$late       = $hours_left <= $cutoff;

		$ok = GPPB_Helpers::db()->update(
			'bookings',
			array( 'booking_status' => 'cancelled', 'updated_at' => GPPB_Helpers::now() ),
			array( 'id' => (int) $booking->id )
		);
		if ( false === $ok ) {
			return array( 'ok' => false, 'message' => __( 'Could not cancel the booking.', 'geeta-parayan-booking' ) );
		}

		GPPB_Audit_Log::add( $user_id, 'booking_cancelled', 'booking', (int) $booking->id, $late ? 'Late cancellation (<24h) — account blocked.' : 'Cancelled within the allowed window.' );

		if ( $late ) {
			$this->set_account_status( $user_id, 'blocked' );
			return array(
				'ok'      => true,
				'late'    => true,
				'message' => __( 'Account locked due to late cancellation (<24h). Please submit a written unblock request to continue.', 'geeta-parayan-booking' ),
			);
		}

		/* Early cancellation: promote the waiting list. */
		$promoted = $this->promote_waitlist( (int) $booking->adhyaya_id, $booking->booking_date );
		return array(
			'ok'      => true,
			'late'    => false,
			'promoted' => $promoted,
			'message' => $promoted
				? __( 'Booking cancelled. The waiting list has been promoted.', 'geeta-parayan-booking' )
				: __( 'Booking cancelled.', 'geeta-parayan-booking' ),
		);
	}

	/**
	 * Delete a booking from the admin Roster & Audit area.
	 *
	 * This is a soft delete: the booking row is kept with status 'deleted'
	 * so the audit/history trail is preserved, and the Sadhak's master
	 * record is never touched. The freed slot re-opens and the waitlist is
	 * promoted, exactly like a cancellation. No late-cancellation penalty
	 * is applied because the action is taken by an administrator.
	 *
	 * @param int $booking_id Booking id.
	 * @return array{ok:bool,message:string,promoted:int|null}
	 */
	public function delete_booking( $booking_id ) {
		$booking = $this->get_booking( $booking_id );
		if ( ! $booking ) {
			return array( 'ok' => false, 'message' => __( 'Booking not found.', 'geeta-parayan-booking' ) );
		}
		if ( ! in_array( $booking->booking_status, array( 'confirmed', 'waitlist_1', 'waitlist_2' ), true ) ) {
			return array( 'ok' => false, 'message' => __( 'Only active bookings can be deleted.', 'geeta-parayan-booking' ) );
		}

		$ok = GPPB_Helpers::db()->update(
			'bookings',
			array( 'booking_status' => 'deleted', 'updated_at' => GPPB_Helpers::now() ),
			array( 'id' => (int) $booking->id )
		);
		if ( false === $ok ) {
			return array( 'ok' => false, 'message' => __( 'Could not delete the booking.', 'geeta-parayan-booking' ) );
		}

		$sadhak = $booking->display_name ? $booking->display_name : ( $booking->user_login ? $booking->user_login : '—' );
		GPPB_Audit_Log::add(
			null,
			'booking_deleted',
			'booking',
			(int) $booking->id,
			sprintf( 'Booking deleted by admin. PRN: %s | Sadhak: %s | Adhyaya: %s | Date: %s.', $booking->prn, $sadhak, $booking->title_nepali, $booking->booking_date )
		);

		/* Re-open the slot: promote the waiting list (FCFS). */
		$promoted = $this->promote_waitlist( (int) $booking->adhyaya_id, $booking->booking_date );

		return array(
			'ok'       => true,
			'message'  => __( 'Booking deleted. The slot is now available.', 'geeta-parayan-booking' ),
			'promoted' => $promoted,
		);
	}

	/**
	 * Promote the waiting list for a freed slot (FCFS).
	 *
	 * First waiter -> confirmed (emailed), second waiter -> waitlist_1.
	 *
	 * @param int    $adhyaya_id Adhyaya id.
	 * @param string $date       Y-m-d.
	 * @return int|null Newly confirmed booking id.
	 */
	public function promote_waitlist( $adhyaya_id, $date ) {
		global $wpdb;
		$table = GPPB_Helpers::db()->table( 'bookings' );
		$list  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, user_id FROM {$table} WHERE adhyaya_id = %d AND booking_date = %s AND booking_status IN ('waitlist_1','waitlist_2') ORDER BY id ASC LIMIT 2",
				absint( $adhyaya_id ),
				$date
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $list ) ) {
			return null;
		}

		$confirmed = null;
		$now       = GPPB_Helpers::now();
		foreach ( $list as $i => $row ) {
			$status = 0 === $i ? 'confirmed' : 'waitlist_1';
			GPPB_Helpers::db()->update( 'bookings', array( 'booking_status' => $status, 'updated_at' => $now ), array( 'id' => (int) $row->id ) );
			GPPB_Audit_Log::add( (int) $row->user_id, 'booking_promoted', 'booking', (int) $row->id, sprintf( 'Auto-promoted to %s.', $status ) );
			if ( 0 === $i ) {
				$confirmed = (int) $row->id;
				GPPB_Mailer::queue_async( 'promotion', $confirmed );
			}
		}
		return $confirmed;
	}

	/**
	 * Mark a booking completed (admin or lifecycle) — unlocks new bookings.
	 *
	 * @param int $booking_id Booking id.
	 * @return bool
	 */
	public function mark_completed( $booking_id ) {
		$booking = $this->get_booking( $booking_id );
		if ( ! $booking ) {
			return false;
		}
		$ok = GPPB_Helpers::db()->update(
			'bookings',
			array( 'booking_status' => 'completed', 'updated_at' => GPPB_Helpers::now() ),
			array( 'id' => (int) $booking->id )
		);
		GPPB_Audit_Log::add( null, 'booking_completed', 'booking', (int) $booking_id, 'Session marked completed.' );
		return false !== $ok;
	}

	/**
	 * Get a booking joined with Adhyaya + user.
	 *
	 * @param int $id Booking id.
	 * @return object|null
	 */
	public function get_booking( $id ) {
		global $wpdb;
		$bookings = GPPB_Helpers::db()->table( 'bookings' );
		$adhyayas = GPPB_Helpers::db()->table( 'adhyayas' );
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT b.*, a.adhyaya_number, a.title_nepali,
				        u.display_name, u.user_email, u.user_nicename
				 FROM {$bookings} b
				 LEFT JOIN {$adhyayas} a ON a.id = b.adhyaya_id
				 LEFT JOIN {$wpdb->users} u ON u.ID = b.user_id
				 WHERE b.id = %d LIMIT 1",
				absint( $id )
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * All bookings for a user, newest first.
	 *
	 * @param int $user_id WP user id.
	 * @return array
	 */
	public function user_bookings( $user_id ) {
		global $wpdb;
		$bookings = GPPB_Helpers::db()->table( 'bookings' );
		$adhyayas = GPPB_Helpers::db()->table( 'adhyayas' );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.*, a.adhyaya_number, a.title_nepali
				 FROM {$bookings} b
				 LEFT JOIN {$adhyayas} a ON a.id = b.adhyaya_id
				 WHERE b.user_id = %d
				 ORDER BY b.booking_date DESC, b.id DESC",
				absint( $user_id )
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Admin roster search with filters + pagination.
	 *
	 * @param array $args Filters.
	 * @return array{items:array,total:int,page:int,per_page:int}
	 */
	public function search_bookings( $args = array() ) {
		global $wpdb;
		$bookings = GPPB_Helpers::db()->table( 'bookings' );
		$adhyayas = GPPB_Helpers::db()->table( 'adhyayas' );
		$defaults = array(
			'search'    => '',
			'status'    => '',
			'slot_type' => '',
			'date_from' => '',
			'date_to'   => '',
			'page'      => 1,
			'per_page'  => 25,
		);
		$args = wp_parse_args( $args, $defaults );

		$where = ' WHERE 1=1';
		$sqlv  = array();

		if ( ! empty( $args['search'] ) ) {
			$term   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where .= ' AND ( b.prn LIKE %s OR b.sadhak_prn LIKE %s OR b.sadhak_name LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s )';
			$sqlv[] = $term;
			$sqlv[] = $term;
			$sqlv[] = $term;
			$sqlv[] = $term;
			$sqlv[] = $term;
			$sqlv[] = $term;
		}
		if ( ! empty( $args['status'] ) ) {
			$where .= ' AND b.booking_status = %s';
			$sqlv[] = $args['status'];
		}
		if ( ! empty( $args['slot_type'] ) ) {
			$where .= ' AND b.slot_type = %s';
			$sqlv[] = $args['slot_type'];
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where .= ' AND b.booking_date >= %s';
			$sqlv[] = $args['date_from'];
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where .= ' AND b.booking_date <= %s';
			$sqlv[] = $args['date_to'];
		}

		$select = "FROM {$bookings} b
			LEFT JOIN {$adhyayas} a ON a.id = b.adhyaya_id
			LEFT JOIN {$wpdb->users} u ON u.ID = b.user_id";
		$offset = max( 0, ( (int) $args['page'] - 1 ) * (int) $args['per_page'] );

		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) {$select}{$where}", $sqlv ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results( $wpdb->prepare( "SELECT b.*, a.adhyaya_number, a.title_nepali, u.display_name, u.user_email, u.user_login {$select}{$where} ORDER BY b.booking_date DESC, b.id DESC LIMIT %d OFFSET %d", array_merge( $sqlv, array( (int) $args['per_page'], $offset ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array(
			'items'    => $items,
			'total'    => $total,
			'page'     => (int) $args['page'],
			'per_page' => (int) $args['per_page'],
		);
	}

	/**
	 * Public roster log for a date — who reads which Adhyaya.
	 *
	 * @param string $slot_type daily|weekly.
	 * @param string $date      Y-m-d.
	 * @return array
	 */
	public function roster_for_date( $slot_type, $date ) {
		global $wpdb;
		$bookings = GPPB_Helpers::db()->table( 'bookings' );
		$adhyayas = GPPB_Helpers::db()->table( 'adhyayas' );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.prn, b.booking_status, b.sadhak_prn, b.sadhak_name, a.adhyaya_number, a.title_nepali, u.display_name
				 FROM {$bookings} b
				 INNER JOIN {$adhyayas} a ON a.id = b.adhyaya_id
				 LEFT JOIN {$wpdb->users} u ON u.ID = b.user_id
				 WHERE b.slot_type = %s AND b.booking_date = %s
				   AND b.booking_status IN ('confirmed','completed')
				 ORDER BY a.adhyaya_number ASC",
				$slot_type,
				$date
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $rows;
	}

	/**
	 * Per-date occupancy counts within a date range (single grouped query).
	 *
	 * Returns a map of 'Y-m-d' => active booking count (confirmed + waitlist)
	 * for the given slot type. Used by the calendar/upcoming panels.
	 *
	 * @param string $slot_type daily|weekly.
	 * @param string $start     Y-m-d inclusive.
	 * @param string $end       Y-m-d inclusive.
	 * @return array<string,int>
	 */
	public function occupancy_by_date( $slot_type, $start, $end ) {
		global $wpdb;
		$table = GPPB_Helpers::db()->table( 'bookings' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT booking_date, COUNT(*) AS n
				 FROM {$table}
				 WHERE slot_type = %s
				   AND booking_date BETWEEN %s AND %s
				   AND booking_status IN ('confirmed','waitlist_1','waitlist_2')
				 GROUP BY booking_date",
				$slot_type,
				$start,
				$end
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[ $r->booking_date ] = (int) $r->n;
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * Daily schedule (admin-assigned date => adhyaya)
	 * ---------------------------------------------------------------- */

	/**
	 * The full admin-assigned Daily schedule as date (Y-m-d) => adhyaya_number.
	 *
	 * Stored as a single serialized option in wp_options (no new table): the
	 * map is small (one int per day), has a single writer (the coordinator)
	 * and is read once per calendar month, so a serialized option is simpler
	 * and fully backward compatible versus adding a database table.
	 *
	 * @return array<string,int>
	 */
	public function daily_schedule() {
		$raw = get_option( 'gppb_daily_schedule', array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		$out = array();
		foreach ( $raw as $date => $num ) {
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ) {
				$out[ (string) $date ] = absint( $num );
			}
		}
		ksort( $out );
		return $out;
	}

	/**
	 * Assigned Adhyaya number for a daily date, or 0 when unassigned.
	 *
	 * @param string $date Y-m-d.
	 * @return int
	 */
	public function daily_adhyaya_for_date( $date ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ) {
			return 0;
		}
		$schedule = $this->daily_schedule();
		return isset( $schedule[ $date ] ) ? absint( $schedule[ $date ] ) : 0;
	}

	/**
	 * Save (or clear) one date => adhyaya assignment.
	 *
	 * @param string $date   Y-m-d.
	 * @param int    $number 0 clears, 1-18 assigns.
	 * @return bool
	 */
	public function set_daily_schedule( $date, $number ) {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $date, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return false;
		}
		$schedule = $this->daily_schedule();
		$number   = absint( $number );
		if ( $number < 1 || $number > GPPB_ADHYAYAS_TOTAL ) {
			unset( $schedule[ $date ] );
		} else {
			$schedule[ $date ] = $number;
		}
		ksort( $schedule );
		return update_option( 'gppb_daily_schedule', $schedule );
	}

	/**
	 * Per-Adhyaya occupancy counts across a date range.
	 *
	 * @param string $slot_type daily|weekly.
	 * @param string $start     Y-m-d inclusive.
	 * @param string $end       Y-m-d inclusive.
	 * @return array<int,array<string,int>> adhyaya_id => (date => count).
	 */
	public function occupancy_by_adhyaya( $slot_type, $start, $end ) {
		global $wpdb;
		$table = GPPB_Helpers::db()->table( 'bookings' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT adhyaya_id, booking_date, COUNT(*) AS n
				 FROM {$table}
				 WHERE slot_type = %s
				   AND booking_date BETWEEN %s AND %s
				   AND booking_status IN ('confirmed','waitlist_1','waitlist_2')
				 GROUP BY adhyaya_id, booking_date",
				$slot_type,
				$start,
				$end
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[ (int) $r->adhyaya_id ][ $r->booking_date ] = (int) $r->n;
		}
		return $out;
	}

	/**
	 * Active occupants for a date, grouped by Adhyaya id.
	 *
	 * Includes confirmed + waitlist rows joined with the user, so the UI can
	 * show the reciter name, PRN, status and booking time per chapter.
	 *
	 * @param string $slot_type daily|weekly.
	 * @param string $date      Y-m-d.
	 * @return array<int,array> adhyaya_id => list of row objects.
	 */
	public function occupants_for_date( $slot_type, $date ) {
		global $wpdb;
		$bookings = GPPB_Helpers::db()->table( 'bookings' );
		$adhyayas = GPPB_Helpers::db()->table( 'adhyayas' );
		$rows     = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.prn, b.booking_status, b.created_at, b.sadhak_prn, b.sadhak_name,
				        a.id AS adhyaya_id, a.adhyaya_number, a.title_nepali,
				        u.display_name, u.ID AS user_id
				 FROM {$bookings} b
				 INNER JOIN {$adhyayas} a ON a.id = b.adhyaya_id
				 LEFT JOIN {$wpdb->users} u ON u.ID = b.user_id
				 WHERE b.slot_type = %s AND b.booking_date = %s
				   AND b.booking_status IN ('confirmed','waitlist_1','waitlist_2')
				 ORDER BY b.id ASC",
				$slot_type,
				$date
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[ (int) $r->adhyaya_id ][] = $r;
		}
		return $out;
	}

	/**
	 * Generate a clean, unique PRN: PRN-YYYYMMDD-XXXX.
	 *
	 * @return string
	 */
	public function generate_prn() {
		global $wpdb;
		$table = GPPB_Helpers::db()->table( 'bookings' );
		$base  = 'PRN-' . gmdate( 'Ymd' ) . '-';
		for ( $attempt = 0; $attempt < 25; $attempt++ ) {
			$prn    = $base . strtoupper( wp_generate_password( 4, false, false ) );
			$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE prn = %s", $prn ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $exists ) {
				return $prn;
			}
		}
		return $base . str_pad( (string) wp_rand( 1, 9999 ), 4, '0', STR_PAD_LEFT );
	}

	/**
	 * Allowed registration fields collected by the public Google-Form-style
	 * booking form, mapped to a sanitizer.
	 *
	 * @return array<string,callable>
	 */
	public function registration_fields() {
		return array(
			'full_name'              => 'sanitize_text_field',
			'mobile'                 => 'sanitize_text_field',
			'district'               => 'sanitize_text_field',
			'place'                  => 'sanitize_text_field',
			'country'                => 'sanitize_text_field',
			'country_code'           => 'sanitize_text_field',
			'email'                  => 'sanitize_email',
			'completed_level'        => 'sanitize_text_field',
			'current_level'          => 'sanitize_text_field',
			'trainer_name'           => 'sanitize_text_field',
			'age'                    => 'absint',
			'volunteer_services'     => 'sanitize_text_field',
			'trainer_level'          => 'sanitize_text_field',
			'previous_participation' => 'sanitize_text_field',
			'previous_date'          => 'sanitize_text_field',
		);
	}

	/**
	 * Save Google-Form-style registration details for a booking as meta rows.
	 *
	 * The existing bookings table is left untouched — details live in
	 * wp_geeta_booking_meta keyed by booking id + Sadhak PRN. Unknown keys
	 * are dropped and values sanitized.
	 *
	 * Existing rows for the booking are replaced with the submitted set in a
	 * single batched write (the SQLite integration is very slow per-query, so
	 * per-field inserts would stall the guest booking request).
	 *
	 * @param int    $booking_id Booking id.
	 * @param string $sadhak_prn Normalized Sadhak PRN.
	 * @param array  $data       Raw form values.
	 * @return void
	 */
	public function save_booking_registration( $booking_id, $sadhak_prn, $data ) {
		if ( ! $booking_id ) {
			return;
		}
		$fields = $this->registration_fields();
		$now    = GPPB_Helpers::now();
		$table  = GPPB_Helpers::db()->table( 'booking_meta' );

		$rows = array();
		foreach ( $fields as $key => $sanitizer ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			$value = is_callable( $sanitizer ) ? call_user_func( $sanitizer, $data[ $key ] ) : sanitize_text_field( $data[ $key ] );
			if ( 'previous_date' === $key && '' !== $value && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
				$value = '';
			}
			if ( '' === $value ) {
				continue;
			}
			$rows[] = array( $booking_id, $sadhak_prn, $key, $value, $now, $now );
		}

		if ( empty( $rows ) ) {
			return;
		}

		global $wpdb;
		/* Replace the previous submitted set for this booking. */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE booking_id = %d", $booking_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$placeholders = implode( ',', array_fill( 0, count( $rows ), '(%d,%s,%s,%s,%s,%s)' ) );
		$flat         = array();
		foreach ( $rows as $r ) {
			$flat = array_merge( $flat, $r );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (booking_id, sadhak_prn, meta_key, meta_value, created_at, updated_at) VALUES {$placeholders}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$flat
			)
		);
	}

	/**
	 * Read the registration meta for a booking as an associative array.
	 *
	 * @param int $booking_id Booking id.
	 * @return array<string,string>
	 */
	public function get_booking_registration( $booking_id ) {
		global $wpdb;
		$table = GPPB_Helpers::db()->table( 'booking_meta' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$table} WHERE booking_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$booking_id
			)
		);
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[ $r->meta_key ] = $r->meta_value;
		}
		return $out;
	}
}
