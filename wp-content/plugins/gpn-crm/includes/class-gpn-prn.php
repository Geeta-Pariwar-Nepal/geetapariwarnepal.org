<?php
/**
 * GPN CRM - PRN lookup service.
 *
 * 100% port of services/prn_service.py:
 *  - cleans country codes exactly like the desktop app
 *  - searches the local DB first (phone / email), then falls back to the
 *    LearnGeeta remote search when nothing is found locally
 *  - PRN-by-PRN search is local only (the remote endpoint only supports
 *    mobile/email lookup), identical to the Python behaviour.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GPN_Prn {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	const REMOTE_URL = 'https://online.learngeeta.com/participant/searchparticipant.php';
	const UA         = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0';

	/**
	 * Strip a country code and non-digits, returning the local number.
	 *
	 * Only removes a country code when the raw value begins with an explicit
	 * international prefix ("+" or "00"). This mirrors the desktop fix that
	 * prevents local Nepali numbers (e.g. 9846...) being stripped as "+98".
	 */
	public function clean_phone( $raw ) {
		$value = trim( (string) $raw );
		if ( '' === $value ) {
			return '';
		}
		if ( 0 !== strpos( $value, '+' ) && 0 !== strpos( $value, '00' ) ) {
			return gpn_digits( $value );
		}
		if ( 0 === strpos( $value, '00' ) ) {
			$value = '+' . substr( $value, 2 );
		}
		$digits = gpn_digits( $value );
		$codes  = gpn_country_codes();
		// Sort longest first so longer prefixes match first (as in Python).
		usort( $codes, function ( $a, $b ) {
			return strlen( $b ) - strlen( $a );
		} );
		foreach ( $codes as $cc ) {
			$code_digits = str_replace( '+', '', $cc );
			if ( strlen( $digits ) > strlen( $code_digits ) && 0 === strpos( $digits, $code_digits ) ) {
				return substr( $digits, strlen( $code_digits ) );
			}
		}
		return $digits;
	}

	/**
	 * Local DB search by mobile or email. Returns list of result arrays:
	 * array('prn'=>, 'name'=>, 'phone'=>, 'email'=>).
	 */
	public function search_local( $mobile_or_email ) {
		$db    = GPN_DB::instance();
		$term  = trim( (string) $mobile_or_email );
		$digits = gpn_digits( $term );
		$sadhaks = $db->db()->get_results( $db->db()->prepare(
			'SELECT prn, name, phone, email FROM ' . $db->sadhaks() . '
			 WHERE (phone LIKE %s OR phone LIKE %s OR email = %s)',
			'%' . $digits,
			'%' . $digits . '%',
			$term
		), ARRAY_A );

		$exact   = array();
		$partial = array();
		foreach ( $sadhaks as $row ) {
			if ( empty( $row['prn'] ) ) {
				continue;
			}
			$phone_digits = gpn_digits( $row['phone'] );
			if ( $digits && substr( $phone_digits, -strlen( $digits ) ) === $digits ) {
				$exact[] = array(
					'prn'   => $row['prn'],
					'name'  => $row['name'],
					'phone' => $row['phone'],
					'email' => $row['email'],
				);
			} else {
				$partial[] = array(
					'prn'   => $row['prn'],
					'name'  => $row['name'],
					'phone' => $row['phone'],
					'email' => $row['email'],
				);
			}
		}
		return $exact ? $exact : $partial;
	}

	/**
	 * Remote LearnGeeta search. Returns list of array('prn'=>, 'name'=>).
	 */
	public function search_remote( $term ) {
		if ( ! GPN_Settings::instance()->get( 'prn_remote_search', 1 ) ) {
			return array();
		}
		if ( ! function_exists( 'curl_init' ) ) {
			return array();
		}
		$timeout = (int) GPN_Settings::instance()->get( 'prn_remote_timeout', 3 );

		// Establish session (GET first to pick up cookies) then POST, mirroring
		// the Python session flow.
		$jar = tempnam( sys_get_temp_dir(), 'gpn' );
		if ( ! $jar ) {
			$jar = '';
		}

		$headers = array(
			'User-Agent: ' . self::UA,
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Content-Type: application/x-www-form-urlencoded',
		);

		$ch = curl_init();
		curl_setopt_array( $ch, array(
			CURLOPT_URL            => self::REMOTE_URL,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => 3,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => 0,
			CURLOPT_CONNECTTIMEOUT => $timeout,
			CURLOPT_TIMEOUT        => $timeout,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_COOKIEJAR      => $jar,
			CURLOPT_COOKIEFILE     => $jar,
		) );
		$html = curl_exec( $ch );
		curl_close( $ch );

		// POST the search (same payload as the desktop: email + Submit).
		$ch = curl_init();
		curl_setopt_array( $ch, array(
			CURLOPT_URL            => self::REMOTE_URL,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => 3,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => 0,
			CURLOPT_CONNECTTIMEOUT => $timeout,
			CURLOPT_TIMEOUT        => $timeout,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_COOKIEJAR      => $jar,
			CURLOPT_COOKIEFILE     => $jar,
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => http_build_query( array( 'email' => $term, 'Submit' => 'Search' ) ),
		) );
		$html = curl_exec( $ch );
		curl_close( $ch );

		if ( $jar && is_file( $jar ) ) {
			@unlink( $jar );
		}

		if ( ! $html || ! is_string( $html ) ) {
			return array();
		}
		return $this->parse_remote( $html );
	}

	/**
	 * Parse the LearnGeeta result table (same rules as _LearnGeetaParser):
	 * .table-striped tbody tr -> cells (td or th) -> [1]=prn, [2]=name.
	 */
	public function parse_remote( $html ) {
		$results = array();
		$doc     = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
		libxml_clear_errors();

		$xpath = new DOMXPath( $doc );
		$tables = $xpath->query( '//table[contains(concat(" ", normalize-space(@class), " "), " table-striped ")]//tbody/tr' );

		foreach ( $tables as $tr ) {
			$cells = array();
			foreach ( $tr->childNodes as $node ) {
				if ( $node->nodeType === XML_ELEMENT_NODE && in_array( strtolower( $node->nodeName ), array( 'td', 'th' ), true ) ) {
					$cells[] = trim( $node->textContent );
				}
			}
			if ( count( $cells ) >= 3 ) {
				$results[] = array(
					'prn'   => $cells[1],
					'name'  => $cells[2],
				);
			}
		}
		return $results;
	}

	/**
	 * Full search: local first, remote fallback (mirrors search_prn()).
	 */
	public function search_prn( $mobile_or_email ) {
		$search_terms = array( trim( (string) $mobile_or_email ) );
		$cleaned      = $this->clean_phone( $mobile_or_email );
		if ( $cleaned && ! in_array( $cleaned, $search_terms, true ) ) {
			$search_terms[] = $cleaned;
		}

		foreach ( $search_terms as $term ) {
			$results = $this->search_local( $term );
			if ( $results ) {
				return $results;
			}
		}

		foreach ( $search_terms as $term ) {
			$results = $this->search_remote( $term );
			if ( $results ) {
				return $results;
			}
		}
		return array();
	}

	/**
	 * PRN -> name (local only, mirrors search_by_prn()).
	 */
	public function search_by_prn( $prn ) {
		$db      = GPN_DB::instance();
		$rows    = $db->db()->get_results( $db->db()->prepare(
			'SELECT prn, name FROM ' . $db->sadhaks() . ' WHERE prn LIKE %s',
			'%' . $prn . '%'
		), ARRAY_A );
		$results = array();
		foreach ( $rows as $row ) {
			if ( ! empty( $row['prn'] ) ) {
				$results[] = array( 'prn' => $row['prn'], 'name' => $row['name'] );
			}
		}
		return $results;
	}

	/**
	 * Pick the best match (mirrors _select_best_prn_result).
	 */
	public function select_best( $results, $term ) {
		if ( empty( $results ) ) {
			return null;
		}
		if ( count( $results ) === 1 ) {
			return $results[0];
		}
		$digits = gpn_digits( $term );
		if ( $digits ) {
			foreach ( $results as $r ) {
				if ( ! empty( $r['phone'] ) && substr( gpn_digits( $r['phone'] ), -strlen( $digits ) ) === $digits ) {
					return $r;
				}
			}
		}
		return $results[0];
	}

	/**
	 * Strip country code for the form's display phone (desktop load_sadhak logic).
	 */
	public function display_phone( $phone, $default_code = '+977' ) {
		$phone = (string) $phone;
		$matched = $default_code;
		foreach ( gpn_country_codes() as $code ) {
			if ( 0 === strpos( $phone, $code ) ) {
				return array( substr( $phone, strlen( $code ) ), $code );
			}
		}
		return array( $phone, $matched );
	}
}
