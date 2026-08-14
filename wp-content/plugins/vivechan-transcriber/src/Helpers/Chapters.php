<?php

namespace Vivechan\Helpers;

defined('ABSPATH') || exit;

/**
 * Gita chapter → Learn Geeta level mapping and chapter detection.
 *
 * L1: 12, 15
 * L2: 9, 14, 16, 17
 * L3: 1, 3, 4, 5, 6, 7
 * L4: 2, 8, 10, 11, 13, 18
 */
final class Chapters {

	const LEVELS = array(
		'L1' => array( 12, 15 ),
		'L2' => array( 9, 14, 16, 17 ),
		'L3' => array( 1, 3, 4, 5, 6, 7 ),
		'L4' => array( 2, 8, 10, 11, 13, 18 ),
	);

	const NEPALI_DIGITS = array( '०', '१', '२', '३', '४', '५', '६', '७', '८', '९' );

	/**
	 * Ordered chapter list for a level.
	 */
	public static function chapters_for_level( $level ) {
		if ( isset( self::LEVELS[ $level ] ) ) {
			return self::LEVELS[ $level ];
		}
		return array();
	}

	public static function level_for_chapter( $chapter ) {
		$chapter = (int) $chapter;
		foreach ( self::LEVELS as $level => $chapters ) {
			if ( in_array( $chapter, $chapters, true ) ) {
				return $level;
			}
		}
		return null;
	}

	/**
	 * "Chapter 12 (अध्याय १२)"
	 */
	public static function label( $chapter ) {
		$chapter = (int) $chapter;
		if ( $chapter < 1 || $chapter > 18 ) {
			return '';
		}
		return 'Chapter ' . $chapter . ' (अध्याय ' . self::nepali( $chapter ) . ')';
	}

	public static function nepali( $n ) {
		$n = (string) $n;
		$out = '';
		foreach ( str_split( $n ) as $ch ) {
			$out .= isset( self::NEPALI_DIGITS[ (int) $ch ] ) ? self::NEPALI_DIGITS[ (int) $ch ] : $ch;
		}
		return $out;
	}

	/**
	 * Detect a chapter (1–18) from a title/name. Returns null when ambiguous.
	 * Recognises English ("chapter 12", "CH. 12", "12th chapter") and Nepali
	 * ("अध्याय १२", "adhyay 12") forms, including Nepali numerals.
	 */
	public static function detect( $title ) {
		$t = (string) $title;
		if ( '' === $t ) {
			return null;
		}

		// Normalise Nepali numerals to ASCII so one regex handles both.
		$t_ascii = strtr( $t, array_combine( self::NEPALI_DIGITS, range( 0, 9 ) ) );
		$t_ascii = mb_strtolower( $t_ascii, 'UTF-8' );

		$patterns = array(
			'/(?:chapter|adhyay|adhyaya|ch\.?|अध्याय)[\s#:.-]*(\d{1,2})/u',
			'/(\d{1,2})\s*(?:th|st|nd|rd)?\s*(?:chapter|अध्याय)/u',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $t_ascii, $m ) ) {
				$n = (int) $m[1];
				if ( $n >= 1 && $n <= 18 ) {
					return $n;
				}
			}
		}
		return null;
	}
}
