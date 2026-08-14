<?php

namespace Vivechan\Rest;

defined('ABSPATH') || exit;

use Vivechan\Models\IntegrationRepo;
use Vivechan\Models\PromptRepo;
use Vivechan\Models\ShareRepo;
use Vivechan\Models\TranscriptRepo;
use Vivechan\Security;

/**
 * /vivechan/v1/shares/{type}/{id}
 *
 * Only the object's creator decides who else may see it. Being shared
 * something never confers the right to re-share it.
 */
final class SharesController {

	public static function routes() {
		$types = implode( '|', ShareRepo::types() );

		return array(
			register_rest_route(
				'vivechan/v1',
				'/shares/(?P<type>' . $types . ')/(?P<id>\d+)',
				array(
					array(
						'methods'             => \WP_REST_Server::READABLE,
						'callback'            => array( __CLASS__, 'index' ),
						'permission_callback' => array( __CLASS__, 'permission' ),
					),
					array(
						'methods'             => \WP_REST_Server::CREATABLE,
						'callback'            => array( __CLASS__, 'create' ),
						'permission_callback' => array( __CLASS__, 'permission' ),
					),
				)
			),
			register_rest_route(
				'vivechan/v1',
				'/shares/(?P<type>' . $types . ')/(?P<id>\d+)/(?P<user_id>\d+)',
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete' ),
					'permission_callback' => array( __CLASS__, 'permission' ),
				)
			),
			// Typeahead for the share box: only users who could actually use
			// what is being shared with them.
			register_rest_route(
				'vivechan/v1',
				'/shareable-users',
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'users' ),
					'permission_callback' => array( __CLASS__, 'permission' ),
				)
			),
		);
	}

	public static function permission() {
		return Security::can_transcribe();
	}

	/**
	 * Owner id for an object, or null when it does not exist.
	 */
	private static function owner_of( $type, $id ) {
		switch ( $type ) {
			case ShareRepo::TYPE_TRANSCRIPT:
				$row = TranscriptRepo::find_by_id( $id );
				break;
			case ShareRepo::TYPE_PROMPT:
				$row = PromptRepo::find_by_id( $id );
				break;
			case ShareRepo::TYPE_INTEGRATION:
				$row = IntegrationRepo::find_raw( $id );
				break;
			default:
				return null;
		}
		return $row ? (int) $row->created_by : null;
	}

	/**
	 * @return true|\WP_Error
	 */
	private static function guard_owner( $type, $id ) {
		$owner = self::owner_of( $type, $id );
		if ( null === $owner ) {
			return Helpers::error( 'Not found', 404 );
		}
		if ( ! ShareRepo::owns( $owner ) ) {
			return Helpers::error( 'Only the person who created this can change who it is shared with.', 403 );
		}
		return true;
	}

	public static function index( $request ) {
		$guard = self::guard_owner( $request['type'], $request['id'] );
		if ( true !== $guard ) {
			return $guard;
		}
		return rest_ensure_response( ShareRepo::users_for( $request['type'], (int) $request['id'] ) );
	}

	public static function create( $request ) {
		$type  = $request['type'];
		$id    = (int) $request['id'];
		$guard = self::guard_owner( $type, $id );
		if ( true !== $guard ) {
			return $guard;
		}

		$username = trim( (string) Helpers::param( $request, 'username', '' ) );
		if ( '' === $username ) {
			return Helpers::error( 'Enter the username of the person to share with.' );
		}

		$user = get_user_by( 'login', $username );
		if ( ! $user ) {
			$user = get_user_by( 'email', $username );
		}
		if ( ! $user ) {
			$user = get_user_by( 'slug', $username );
		}
		if ( ! $user ) {
			return Helpers::error( sprintf( 'No user found with the username "%s".', $username ), 404 );
		}

		if ( (int) $user->ID === get_current_user_id() ) {
			return Helpers::error( 'You already have access to this — it is yours.' );
		}

		// Sharing with someone who cannot open the tool would look like it
		// worked while doing nothing.
		if ( ! user_can( $user, Security::CAP_TRANSCRIBE ) ) {
			return Helpers::error(
				sprintf( '%s does not have access to the Vivechan Transcriber. Give them the Vivechak or Vivechan Editor role first.', $user->display_name ),
				400
			);
		}

		ShareRepo::share( $type, $id, (int) $user->ID, get_current_user_id() );

		return rest_ensure_response( ShareRepo::users_for( $type, $id ) );
	}

	public static function delete( $request ) {
		$type  = $request['type'];
		$id    = (int) $request['id'];
		$guard = self::guard_owner( $type, $id );
		if ( true !== $guard ) {
			return $guard;
		}

		ShareRepo::unshare( $type, $id, (int) $request['user_id'] );

		return rest_ensure_response( ShareRepo::users_for( $type, $id ) );
	}

	/**
	 * Users who hold the transcribe capability, for the share box.
	 */
	public static function users() {
		$out = array();

		foreach ( get_users( array( 'number' => 200, 'orderby' => 'display_name' ) ) as $user ) {
			if ( (int) $user->ID === get_current_user_id() ) {
				continue;
			}
			if ( ! user_can( $user, Security::CAP_TRANSCRIBE ) ) {
				continue;
			}
			$out[] = array(
				'user_id'      => (int) $user->ID,
				'user_login'   => $user->user_login,
				'display_name' => $user->display_name,
			);
		}

		return rest_ensure_response( $out );
	}
}
