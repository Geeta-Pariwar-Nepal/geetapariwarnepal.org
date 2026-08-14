<?php

namespace Vivechan\Rest;

defined('ABSPATH') || exit;

use Vivechan\Models\ShareRepo;
use Vivechan\Security;

/**
 * Shared helpers for REST controllers.
 */
final class Helpers {

	/**
	 * Whitelist-style field projection, mirroring the original `?fields=` API.
	 */
	public static function project( $row, $fields ) {
		$row = (array) $row;
		if ( ! $fields ) {
			return $row;
		}
		$keep = array();
		foreach ( explode( ',', $fields ) as $field ) {
			$field = trim( $field );
			if ( '' !== $field && isset( $row[ $field ] ) ) {
				$keep[ $field ] = $row[ $field ];
			}
		}
		return $keep;
	}

	public static function forbidden() {
		return new \WP_Error(
			'vivechan_forbidden',
			'You do not have permission to access this resource.',
			array( 'status' => 403 )
		);
	}

	/**
	 * A transcript is reachable by its creator and by users it was shared
	 * with. Nothing is visible to everyone, and there is no admin bypass.
	 */
	public static function row_accessible( $record ) {
		if ( ! $record || ! Security::can_transcribe() ) {
			return false;
		}
		return ShareRepo::can_access( ShareRepo::TYPE_TRANSCRIPT, $record->id, $record->created_by );
	}

	/**
	 * Sharing replaced locking: if a transcript was shared with you, you are
	 * meant to work on it. What you may then *do* is governed by capability —
	 * publishing still needs vivechan_publish.
	 */
	public static function can_edit( $record ) {
		return self::row_accessible( $record );
	}

	/**
	 * Only the creator may rename, delete, or change who a transcript is
	 * shared with.
	 */
	public static function owns( $record ) {
		return $record && ShareRepo::owns( $record->created_by );
	}

	public static function param( $request, $key, $default = null ) {
		$value = $request->get_param( $key );
		return ( null === $value || '' === $value ) ? $default : $value;
	}

	public static function error( $message, $code = 400 ) {
		return new \WP_Error( 'vivechan_error', $message, array( 'status' => $code ) );
	}
}
