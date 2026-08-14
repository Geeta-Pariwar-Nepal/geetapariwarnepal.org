<?php
/**
 * Email dispatcher built on wp_mail().
 *
 * @package Geeta_Pariwar_Parayan_Booking
 */

defined( 'ABSPATH' ) || exit;

class GPPB_Mailer {

	/**
	 * Register the async email handler so queued booking emails are sent
	 * by wp-cron instead of inside the HTTP booking request. Called from
	 * the plugin bootstrap on every request.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'gppb_send_booking_email', array( __CLASS__, 'process_queued_email' ), 10, 2 );
	}

	/**
	 * Queue a booking email to be sent in the background. Booking emails are
	 * dispatched through wp-cron so a slow / blocking mail transport can
	 * never delay the AJAX booking response. Falls back to a synchronous
	 * send so an email is never lost.
	 *
	 * @param string $type       confirmation|promotion|admin.
	 * @param int    $booking_id Booking id.
	 * @return bool
	 */
	public static function queue_async( $type, $booking_id ) {
		$booking_id = absint( $booking_id );
		if ( ! $booking_id ) {
			return false;
		}
		$type = in_array( $type, array( 'confirmation', 'promotion', 'admin' ), true ) ? $type : 'admin';

		if ( function_exists( 'wp_schedule_single_event' ) ) {
			$ok = wp_schedule_single_event( time() + 5, 'gppb_send_booking_email', array( $type, $booking_id ) );
			if ( $ok ) {
				return true;
			}
		}

		/* Fallback: send synchronously so the email is never lost. */
		try {
			self::process_queued_email( $type, $booking_id );
			return true;
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * wp-cron handler — sends a queued booking email.
	 *
	 * @param string $type       confirmation|promotion|admin.
	 * @param int    $booking_id Booking id.
	 * @return void
	 */
	public static function process_queued_email( $type, $booking_id ) {
		try {
			$booking = GPPB_Booking_Engine::instance()->get_booking( absint( $booking_id ) );
			if ( ! $booking ) {
				return;
			}
			if ( 'confirmation' === $type ) {
				self::send_confirmation( $booking );
			} elseif ( 'promotion' === $type ) {
				self::send_promotion( $booking );
			} else {
				self::notify_admin( $booking );
			}
		} catch ( \Throwable $e ) {
			/* A mail failure must never break the booking flow. */
			return;
		}
	}

	/**
	 * Send a confirmation email to the booking owner.
	 *
	 * @param object $booking Booking row (joined).
	 * @return bool
	 */
	public static function send_confirmation( $booking ) {
		$email = self::booking_email_address( $booking );
		if ( '' === $email ) {
			return false;
		}
		$links = GPPB_Booking_Engine::instance()->session_links( $booking->slot_type, $booking->booking_date );

		$subject = sprintf( __( '[Parayan] Booking confirmed — %s', 'geeta-parayan-booking' ), $booking->prn );
		$body    = self::booking_email( $booking, $links );

		return self::mail( $email, $subject, $body, $booking );
	}

	/**
	 * Send a promotion email when a waiting user is confirmed.
	 *
	 * @param object $booking Booking row (joined).
	 * @return bool
	 */
	public static function send_promotion( $booking ) {
		$email = self::booking_email_address( $booking );
		if ( '' === $email ) {
			return false;
		}
		$links = GPPB_Booking_Engine::instance()->session_links( $booking->slot_type, $booking->booking_date );

		$subject = sprintf( __( '[Parayan] You have been confirmed — %s', 'geeta-parayan-booking' ), $booking->prn );
		$body    = self::booking_email( $booking, $links, true );

		return self::mail( $email, $subject, $body, $booking );
	}

	/**
	 * Resolve the recipient email for a booking (WP user or PRN master).
	 *
	 * @param object $booking Booking row (joined).
	 * @return string
	 */
	private static function booking_email_address( $booking ) {
		if ( ! empty( $booking->user_email ) ) {
			return (string) $booking->user_email;
		}
		if ( ! empty( $booking->sadhak_prn ) ) {
			$sadhak = GPPB_Prn_Store::instance()->sadhak_by_prn( $booking->sadhak_prn );
			if ( $sadhak && ! empty( $sadhak->email ) ) {
				return (string) $sadhak->email;
			}
		}
		return '';
	}

	/**
	 * Resolve the display name for a booking (WP user or PRN master).
	 *
	 * @param object $booking Booking row (joined).
	 * @return string
	 */
	private static function booking_display_name( $booking ) {
		if ( ! empty( $booking->display_name ) ) {
			return (string) $booking->display_name;
		}
		if ( ! empty( $booking->sadhak_name ) ) {
			return (string) $booking->sadhak_name;
		}
		if ( ! empty( $booking->sadhak_prn ) ) {
			$sadhak = GPPB_Prn_Store::instance()->sadhak_by_prn( $booking->sadhak_prn );
			if ( $sadhak && ! empty( $sadhak->name ) ) {
				return (string) $sadhak->name;
			}
		}
		return '';
	}

	/**
	 * Notify the administrator of a new booking.
	 *
	 * @param object $booking Booking row (joined).
	 * @return bool
	 */
	public static function notify_admin( $booking ) {
		$to = sanitize_email( (string) GPPB_Helpers::get_setting( 'admin_email', get_option( 'admin_email' ) ) );
		if ( '' === $to ) {
			return false;
		}
		$statuses = GPPB_Helpers::booking_statuses();
		$status   = isset( $statuses[ $booking->booking_status ] ) ? $statuses[ $booking->booking_status ]['label'] : $booking->booking_status;

		$subject = sprintf( __( '[Parayan] New booking %s (%s)', 'geeta-parayan-booking' ), $booking->prn, $status );
		$body    = sprintf(
			__( 'Name: %s<br>PRN: %s<br>Sadhak PRN: %s<br>Email: %s<br>Date: %s<br>Adhyaya: %s<br>Type: %s<br>Status: %s<br>Manage: %s', 'geeta-parayan-booking' ),
			esc_html( self::booking_display_name( $booking ) ),
			esc_html( (string) $booking->prn ),
			esc_html( isset( $booking->sadhak_prn ) ? (string) $booking->sadhak_prn : '' ),
			esc_html( self::booking_email_address( $booking ) ),
			esc_html( GPPB_Helpers::format_date( $booking->booking_date ) ),
			esc_html( (string) $booking->title_nepali ),
			esc_html( GPPB_Helpers::slot_types()[ $booking->slot_type ] ?? $booking->slot_type ),
			esc_html( $status ),
			esc_url( admin_url( 'admin.php?page=gppb-roster' ) )
		);
		return self::mail( $to, $subject, $body, $booking, true );
	}

	/**
	 * Build the HTML body of a booking email.
	 *
	 * @param object      $booking  Booking row.
	 * @param object|null $links    Session links row.
	 * @param bool        $promoted Whether this is a promotion notice.
	 * @return string
	 */
	private static function booking_email( $booking, $links, $promoted = false ) {
		$intro = $promoted
			? __( 'A slot has opened and your waiting booking has been confirmed.', 'geeta-parayan-booking' )
			: __( 'Your Parayan booking details:', 'geeta-parayan-booking' );

		$html  = '<p>' . esc_html( $intro ) . '</p>';
		$html .= '<table cellpadding="6" style="border-collapse:collapse;border:1px solid #ddd;font-size:14px">';
		$html .= '<tr><th align="left">' . esc_html__( 'PRN', 'geeta-parayan-booking' ) . '</th><td>' . esc_html( $booking->prn ) . '</td></tr>';
		$html .= '<tr><th align="left">' . esc_html__( 'Date', 'geeta-parayan-booking' ) . '</th><td>' . esc_html( GPPB_Helpers::format_date( $booking->booking_date ) ) . '</td></tr>';
		$html .= '<tr><th align="left">' . esc_html__( 'Adhyaya', 'geeta-parayan-booking' ) . '</th><td>' . esc_html( $booking->title_nepali ) . '</td></tr>';
		$html .= '<tr><th align="left">' . esc_html__( 'Type', 'geeta-parayan-booking' ) . '</th><td>' . esc_html( GPPB_Helpers::slot_types()[ $booking->slot_type ] ?? $booking->slot_type ) . '</td></tr>';

		if ( $links ) {
			if ( $links->zoom_link ) {
				$html .= '<tr><th align="left">' . esc_html__( 'Zoom', 'geeta-parayan-booking' ) . '</th><td><a href="' . esc_url( $links->zoom_link ) . '">' . esc_html( $links->zoom_link ) . '</a></td></tr>';
			}
			if ( $links->youtube_link ) {
				$html .= '<tr><th align="left">' . esc_html__( 'YouTube', 'geeta-parayan-booking' ) . '</th><td><a href="' . esc_url( $links->youtube_link ) . '">' . esc_html( $links->youtube_link ) . '</a></td></tr>';
			}
		}
		$html .= '</table>';

		$html .= '<p>' . esc_html__( 'Hare Krishna 🙏 — Geeta Pariwar Nepal', 'geeta-parayan-booking' ) . '</p>';
		return $html;
	}

	/**
	 * Send an email with shared headers + audit trail.
	 *
	 * @param string      $to      Recipient.
	 * @param string      $subject Subject.
	 * @param string      $body    HTML body.
	 * @param object|null $booking Booking (for audit).
	 * @param bool        $admin   Whether this is an admin notification.
	 * @return bool
	 */
	private static function mail( $to, $subject, $body, $booking = null, $admin = false ) {
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'Reply-To: ' . sanitize_email( (string) GPPB_Helpers::get_setting( 'admin_email', get_option( 'admin_email' ) ) ),
		);
		$sent = wp_mail( sanitize_email( $to ), sanitize_text_field( $subject ), $body, $headers );

		if ( $booking ) {
			GPPB_Audit_Log::add(
				null,
				'email_sent',
				'booking',
				(int) $booking->id,
				sprintf( 'Email "%s" to %s (%s).', $subject, $to, $sent ? 'OK' : 'FAILED' )
			);
		}
		return $sent;
	}
}
