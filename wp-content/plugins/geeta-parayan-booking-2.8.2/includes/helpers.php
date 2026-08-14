<?php
/**
 * Shared helper utilities for the Geeta Pariwar Parayan Booking Engine.
 *
 * @package Geeta_Pariwar_Parayan_Booking
 */

defined( 'ABSPATH' ) || exit;

final class GPPB_Helpers {

	/**
	 * Quick access to the database singleton.
	 *
	 * @return GPPB_Database
	 */
	public static function db() {
		return GPPB_Database::instance();
	}

	/**
	 * Default plugin settings (stored in wp_options as gppb_<key>).
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'cancellation_hours' => 24,
			'waiting_max'        => 2,
			'daily_days_ahead'   => 60,
			'weekly_dates_ahead' => 8,
			'notify_admin'       => 1,
			'admin_email'        => 'contact@geetapariwarnepal.org',
			'landing_title'      => __( 'श्रीमद्भगवद्गीता पारायण बुकिङ', 'geeta-parayan-booking' ),
			'landing_subtitle'   => __( 'दैनिक र साप्ताहिक पारायणमा सहभागी हुन आफ्नो अध्याय बुक गर्नुहोस् ।', 'geeta-parayan-booking' ),
		);
	}

	/**
	 * Get a plugin setting.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get_setting( $key, $default = '' ) {
		$value = get_option( 'gppb_' . $key, null );
		if ( null === $value ) {
			$defaults = self::default_settings();
			return array_key_exists( $key, $defaults ) ? $defaults[ $key ] : $default;
		}
		return $value;
	}

	/**
	 * Store a plugin setting.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @return bool
	 */
	public static function set_setting( $key, $value ) {
		return update_option( 'gppb_' . $key, $value );
	}

	/**
	 * Capability required to manage the plugin.
	 *
	 * @return string
	 */
	public static function capability() {
		return apply_filters( 'gppb_capability', 'gppb_manage_bookings' );
	}

	/**
	 * Whether the current user can manage the plugin.
	 *
	 * @return bool
	 */
	public static function current_admin_can() {
		return current_user_can( self::capability() );
	}

	/**
	 * Booking statuses with labels + Bootstrap badge classes.
	 *
	 * @return array
	 */
	public static function booking_statuses() {
		return array(
			'confirmed' => array( 'label' => __( 'Confirmed', 'geeta-parayan-booking' ), 'badge' => 'success' ),
			'waitlist_1' => array( 'label' => __( 'Waitlist #1', 'geeta-parayan-booking' ), 'badge' => 'info' ),
			'waitlist_2' => array( 'label' => __( 'Waitlist #2', 'geeta-parayan-booking' ), 'badge' => 'secondary' ),
			'cancelled' => array( 'label' => __( 'Cancelled', 'geeta-parayan-booking' ), 'badge' => 'dark' ),
			'completed' => array( 'label' => __( 'Completed', 'geeta-parayan-booking' ), 'badge' => 'primary' ),
			'deleted' => array( 'label' => __( 'Deleted', 'geeta-parayan-booking' ), 'badge' => 'danger' ),
		);
	}

	/**
	 * Slot types.
	 *
	 * @return array
	 */
	public static function slot_types() {
		return array(
			'daily'  => __( 'दैनिक पारायण', 'geeta-parayan-booking' ),
			'weekly' => __( 'साप्ताहिक पारायण', 'geeta-parayan-booking' ),
		);
	}

	/**
	 * Teacher approval statuses with labels.
	 *
	 * @return array
	 */
	public static function approval_statuses() {
		return array(
			'pending'  => __( 'Pending', 'geeta-parayan-booking' ),
			'approved' => __( 'Approved', 'geeta-parayan-booking' ),
			'rejected' => __( 'Rejected', 'geeta-parayan-booking' ),
		);
	}

	/**
	 * Status badge HTML.
	 *
	 * @param string $status Booking status key.
	 * @return string
	 */
	public static function status_badge( $status ) {
		$statuses = self::booking_statuses();
		$label    = isset( $statuses[ $status ] ) ? $statuses[ $status ]['label'] : ucfirst( $status );
		$class    = isset( $statuses[ $status ] ) ? $statuses[ $status ]['badge'] : 'secondary';
		return sprintf( '<span class="badge text-bg-%s">%s</span>', esc_attr( $class ), esc_html( $label ) );
	}

	/**
	 * Convert an integer to Nepali (Devanagari) numerals.
	 *
	 * @param int $num Number.
	 * @return string
	 */
	public static function nepali_numeral( $num ) {
		$map = array( '०', '१', '२', '३', '४', '५', '६', '७', '८', '९' );
		$out = '';
		foreach ( str_split( (string) $num ) as $char ) {
			$out .= isset( $map[ $char ] ) ? $map[ $char ] : $char;
		}
		return $out;
	}

	/**
	 * Nepali title of an Adhyaya, e.g. "अध्याय १".
	 *
	 * @param int $num Adhyaya number 1-18.
	 * @return string
	 */
	public static function adhyaya_title( $num ) {
		$num = absint( $num );
		if ( $num < 1 || $num > GPPB_ADHYAYAS_TOTAL ) {
			$num = 0;
		}
		return sprintf( __( 'अध्याय %s', 'geeta-parayan-booking' ), self::nepali_numeral( $num ) );
	}

	/**
	 * Map of Adhyaya number => Nepali title.
	 *
	 * @return array
	 */
	public static function adhyayas() {
		$out = array();
		for ( $i = 1; $i <= GPPB_ADHYAYAS_TOTAL; $i++ ) {
			$out[ $i ] = self::adhyaya_title( $i );
		}
		return $out;
	}

	/**
	 * Current datetime string in the WP timezone (Y-m-d H:i:s).
	 *
	 * @return string
	 */
	public static function now() {
		return gmdate( 'Y-m-d H:i:s', time() + ( (float) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS ) );
	}

	/**
	 * Current date in the WP timezone (Y-m-d).
	 *
	 * @return string
	 */
	public static function today() {
		return gmdate( 'Y-m-d', time() + ( (float) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS ) );
	}

	/**
	 * Human friendly date.
	 *
	 * @param string|null $date SQL date.
	 * @return string
	 */
	public static function format_date( $date ) {
		if ( empty( $date ) || '0000-00-00' === $date ) {
			return '-';
		}
		$ts = strtotime( $date );
		return $ts ? date_i18n( get_option( 'date_format' ), $ts ) : esc_html( $date );
	}

	/**
	 * Human friendly datetime.
	 *
	 * @param string|null $datetime SQL datetime.
	 * @return string
	 */
	public static function format_datetime( $datetime ) {
		if ( empty( $datetime ) || '0000-00-00 00:00:00' === $datetime ) {
			return '-';
		}
		$ts = strtotime( $datetime );
		return $ts ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts ) : esc_html( $datetime );
	}

	/**
	 * Bikram Sambat calendar data (BS year => 12 month day-counts).
	 * Anchor: BS 2000-01-01 = AD 1943-04-14.
	 *
	 * @return array
	 */
	public static function bs_data() {
		static $data = null;
		if ( null === $data ) {
			$data = array(
				2000 => array( 30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31 ),
				2001 => array( 31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2002 => array( 31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30 ),
				2003 => array( 31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31 ),
				2004 => array( 30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31 ),
				2005 => array( 31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2006 => array( 31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30 ),
				2007 => array( 31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31 ),
				2008 => array( 31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31 ),
				2009 => array( 31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2010 => array( 31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30 ),
				2011 => array( 31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31 ),
				2012 => array( 31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30 ),
				2013 => array( 31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2014 => array( 31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30 ),
				2015 => array( 31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31 ),
				2016 => array( 31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30 ),
				2017 => array( 31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2018 => array( 31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30 ),
				2019 => array( 31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31 ),
				2020 => array( 31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2021 => array( 31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2022 => array( 31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30 ),
				2023 => array( 31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31 ),
				2024 => array( 31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2025 => array( 31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2026 => array( 31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31 ),
				2027 => array( 30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31 ),
				2028 => array( 31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2029 => array( 31, 31, 32, 31, 32, 30, 30, 29, 30, 29, 30, 30 ),
				2030 => array( 31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31 ),
				2031 => array( 30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31 ),
				2032 => array( 31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2033 => array( 31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30 ),
				2034 => array( 31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31 ),
				2035 => array( 30, 32, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31 ),
				2036 => array( 31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2037 => array( 31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30 ),
				2038 => array( 31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31 ),
				2039 => array( 31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30 ),
				2040 => array( 31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2041 => array( 31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30 ),
				2042 => array( 31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31 ),
				2043 => array( 31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30 ),
				2044 => array( 31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2045 => array( 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 30, 30 ),
				2046 => array( 31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2047 => array( 31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31 ),
				2048 => array( 31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2049 => array( 31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2050 => array( 31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30 ),
				2051 => array( 31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2052 => array( 31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2053 => array( 31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30 ),
				2054 => array( 31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31 ),
				2055 => array( 31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2056 => array( 31, 31, 32, 31, 32, 30, 30, 29, 30, 29, 30, 30 ),
				2057 => array( 31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31 ),
				2058 => array( 30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31 ),
				2059 => array( 31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2060 => array( 31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30 ),
				2061 => array( 31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31 ),
				2062 => array( 30, 32, 31, 32, 31, 31, 29, 30, 29, 30, 29, 31 ),
				2063 => array( 31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2064 => array( 31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30 ),
				2065 => array( 31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31 ),
				2066 => array( 31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31 ),
				2067 => array( 31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2068 => array( 31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30 ),
				2069 => array( 31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31 ),
				2070 => array( 31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30 ),
				2071 => array( 31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2072 => array( 31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30 ),
				2073 => array( 31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31 ),
				2074 => array( 31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2075 => array( 31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2076 => array( 31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30 ),
				2077 => array( 31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31 ),
				2078 => array( 31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2079 => array( 31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30 ),
				2080 => array( 31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30 ),
				2081 => array( 31, 31, 32, 32, 31, 30, 30, 30, 29, 30, 30, 30 ),
				2082 => array( 30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30 ),
				2083 => array( 31, 31, 32, 31, 31, 30, 30, 30, 29, 30, 30, 30 ),
				2084 => array( 31, 31, 32, 31, 31, 30, 30, 30, 29, 30, 30, 30 ),
				2085 => array( 31, 32, 31, 32, 30, 31, 30, 30, 29, 30, 30, 30 ),
				2086 => array( 30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30 ),
				2087 => array( 31, 31, 32, 31, 31, 31, 30, 30, 29, 30, 30, 30 ),
				2088 => array( 30, 31, 32, 32, 30, 31, 30, 30, 29, 30, 30, 30 ),
				2089 => array( 30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30 ),
				2090 => array( 30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30 ),
				2091 => array( 31, 31, 32, 31, 31, 31, 30, 30, 29, 30, 30, 30 ),
				2092 => array( 30, 31, 32, 32, 31, 30, 30, 30, 29, 30, 30, 30 ),
				2093 => array( 30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30 ),
				2094 => array( 31, 31, 32, 31, 31, 30, 30, 30, 29, 30, 30, 30 ),
				2095 => array( 31, 31, 32, 31, 31, 31, 30, 29, 30, 30, 30, 30 ),
			);
		}
		return $data;
	}

	/**
	 * Serial day number (1970-01-01 = 0) from a Gregorian date.
	 *
	 * @param int $y Year.
	 * @param int $m Month.
	 * @param int $d Day.
	 * @return int
	 */
	public static function ad_serial( $y, $m, $d ) {
		if ( $m <= 2 ) {
			--$y;
		}
		$era = intdiv( $y, 400 );
		$yoe = $y - ( 400 * $era );
		$mp  = $m + ( $m > 2 ? -3 : 9 );
		$doy = intdiv( ( 153 * $mp ) + 2, 5 ) + $d - 1;
		$doe = ( 365 * $yoe ) + intdiv( $yoe, 4 ) - intdiv( $yoe, 100 ) + $doy;
		return ( 146097 * $era ) + $doe - 719468;
	}

	/**
	 * Convert a Gregorian date (Y-m-d) to a Bikram Sambat date.
	 *
	 * @param string $date Y-m-d.
	 * @return array|false {year,month,day}.
	 */
	public static function ad_to_bs( $date ) {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) ) {
			return false;
		}
		$data         = self::bs_data();
		$anchor_serial = self::ad_serial( 1943, 4, 14 );
		$serial       = self::ad_serial( (int) $m[1], (int) $m[2], (int) $m[3] );
		$diff         = $serial - $anchor_serial;
		if ( $diff < 0 ) {
			return false;
		}
		$year = 2000;
		while ( isset( $data[ $year ] ) ) {
			$len = array_sum( $data[ $year ] );
			if ( $diff < $len ) {
				break;
			}
			$diff -= $len;
			++$year;
		}
		if ( ! isset( $data[ $year ] ) ) {
			return false;
		}
		foreach ( $data[ $year ] as $mi => $mlen ) {
			if ( $diff < $mlen ) {
				return array( 'year' => $year, 'month' => $mi + 1, 'day' => $diff + 1 );
			}
			$diff -= $mlen;
		}
		return false;
	}

	/**
	 * Nepali (Bikram Sambat) representation of a Gregorian date.
	 *
	 * @param string|null $date Y-m-d.
	 * @return array
	 */
	public static function nepali_date( $date ) {
		$empty = array( 'ok' => false, 'label' => '-', 'compact' => '-', 'year' => 0, 'month' => 0, 'day' => 0 );
		if ( empty( $date ) || '0000-00-00' === $date ) {
			return $empty;
		}
		$bs = self::ad_to_bs( $date );
		if ( ! $bs ) {
			return $empty;
		}
		$months = array(
			1  => __( 'बैशाख', 'geeta-parayan-booking' ),
			2  => __( 'जेठ', 'geeta-parayan-booking' ),
			3  => __( 'असार', 'geeta-parayan-booking' ),
			4  => __( 'साउन', 'geeta-parayan-booking' ),
			5  => __( 'भदौ', 'geeta-parayan-booking' ),
			6  => __( 'असोज', 'geeta-parayan-booking' ),
			7  => __( 'कात्तिक', 'geeta-parayan-booking' ),
			8  => __( 'मंसिर', 'geeta-parayan-booking' ),
			9  => __( 'पुस', 'geeta-parayan-booking' ),
			10 => __( 'माघ', 'geeta-parayan-booking' ),
			11 => __( 'फागुन', 'geeta-parayan-booking' ),
			12 => __( 'चैत', 'geeta-parayan-booking' ),
		);
		$compact = self::nepali_numeral( $bs['year'] ) . '-' . self::nepali_numeral( $bs['month'] ) . '-' . self::nepali_numeral( $bs['day'] );
		$month   = isset( $months[ $bs['month'] ] ) ? $months[ $bs['month'] ] : '';
		return array(
			'ok'      => true,
			'label'   => sprintf( __( 'सम्वत् %s ( %s गते )', 'geeta-parayan-booking' ), $compact, $month ),
			'compact' => $compact,
			'year'    => $bs['year'],
			'month'   => $bs['month'],
			'day'     => $bs['day'],
		);
	}

	/**
	 * Partially masked mobile number.
	 *
	 * @param string $mobile Mobile number.
	 * @return string
	 */
	public static function masked_mobile( $mobile ) {
		$len = strlen( $mobile );
		if ( $len <= 4 ) {
			return $mobile;
		}
		return substr( $mobile, 0, 3 ) . str_repeat( '*', max( 0, $len - 6 ) ) . substr( $mobile, -3 );
	}

	/**
	 * Best-effort masked phone for a WP user.
	 *
	 * Checks common user-meta phone keys first, then the optional Geeta CRM
	 * sadhak table matched by email/name. Returns an empty string when no
	 * phone data exists so the UI simply omits the field.
	 *
	 * @param int $user_id WP user id.
	 * @return string Masked phone or ''.
	 */
	public static function masked_phone_for_user( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id < 1 ) {
			return '';
		}

		/* Common WP user-meta phone keys. */
		foreach ( array( 'phone', 'mobile', 'mobile_number', 'cellphone', 'contact_phone', 'contact_number', 'sadhak_phone' ) as $key ) {
			$value = get_user_meta( $user_id, $key, true );
			if ( is_string( $value ) && preg_match( '/\d{6,}/', $value ) ) {
				return self::masked_mobile( $value );
			}
		}

		/* Optional Geeta CRM sadhak table (may not exist). */
		global $wpdb;
		$table = $wpdb->prefix . 'gp_sadhak';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( ! $exists ) {
			return '';
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return '';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$phone = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT phone FROM {$table}
				 WHERE ( email = %s OR name = %s )
				   AND phone IS NOT NULL AND phone != ''
				 ORDER BY id DESC LIMIT 1",
				$user->user_email,
				$user->display_name
			)
		);
		if ( is_string( $phone ) && preg_match( '/\d{6,}/', $phone ) ) {
			return self::masked_mobile( $phone );
		}
		return '';
	}

	/**
	 * URL helper for plugin assets.
	 *
	 * @param string $path Relative path from plugin root.
	 * @return string
	 */
	public static function asset( $path ) {
		return GPPB_URL . ltrim( $path, '/' );
	}

	/**
	 * Current public permalink (fallback home).
	 *
	 * @return string
	 */
	public static function public_permalink() {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && function_exists( 'get_permalink' ) ) {
			return get_permalink( $post );
		}
		return home_url( '/' );
	}

	/**
	 * Get the visitor IP safely.
	 *
	 * @return string
	 */
	public static function client_ip() {
		$ip = '';
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$ip = sanitize_text_field( wp_unslash( explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] )[0] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}
		return $ip;
	}

	/**
	 * Write to the WP debug log (safe wrapper).
	 *
	 * @param mixed $data Data to log.
	 * @return void
	 */
	public static function log( $data ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[GPPB] ' . ( is_scalar( $data ) ? $data : wp_json_encode( $data ) ) );
		}
	}

	/**
	 * Print an admin notice safely.
	 *
	 * @param string $message Message.
	 * @param string $type    success|error|warning|info.
	 * @return void
	 */
	public static function notice( $message, $type = 'success' ) {
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}
}
