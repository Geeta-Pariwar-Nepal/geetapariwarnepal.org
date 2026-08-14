<?php

namespace Vivechan\Rest;

defined('ABSPATH') || exit;

use Vivechan\Models\PromptRepo;
use Vivechan\Models\ShareRepo;
use Vivechan\Security;

/**
 * /vivechan/v1/system-prompts endpoints.
 */
final class PromptsController {

	public static function routes() {
		return array(
			register_rest_route(
				'vivechan/v1',
				'/system-prompts',
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
				'/system-prompts/(?P<id>\d+)',
				array(
					array(
						'methods'             => \WP_REST_Server::READABLE,
						'callback'            => array( __CLASS__, 'show' ),
						'permission_callback' => array( __CLASS__, 'permission' ),
					),
					array(
						'methods'             => \WP_REST_Server::EDITABLE,
						'callback'            => array( __CLASS__, 'update' ),
						'permission_callback' => array( __CLASS__, 'permission' ),
					),
					array(
						'methods'             => \WP_REST_Server::DELETABLE,
						'callback'            => array( __CLASS__, 'delete' ),
						'permission_callback' => array( __CLASS__, 'permission' ),
					),
				)
			),
		);
	}

	public static function permission() {
		return Security::can_transcribe();
	}

	public static function index() {
		return rest_ensure_response( array_map( array( __CLASS__, 'shape' ), PromptRepo::all_titles() ) );
	}

	public static function show( $request ) {
		$record = PromptRepo::find_by_id( $request['id'] );
		if ( ! $record ) {
			return Helpers::error( 'Not found', 404 );
		}
		// Owner, shared, or the built-in prompt — nothing else is readable.
		if ( ! PromptRepo::can_access( $record ) ) {
			return Helpers::forbidden();
		}
		return rest_ensure_response( array(
			'id'              => (int) $record->id,
			'title'           => $record->title,
			'content'         => $record->content,
			'created_by'      => (int) $record->created_by,
			'created_by_name' => self::user_name( (int) $record->created_by ),
			'created_at'      => $record->created_at,
			'updated_at'      => $record->updated_at,
		) );
	}

	public static function create( $request ) {
		$title   = trim( (string) Helpers::param( $request, 'title', '' ) );
		$content = trim( (string) Helpers::param( $request, 'content', '' ) );
		if ( '' === $title || '' === $content ) {
			return Helpers::error( 'title and content are required' );
		}
		$record = PromptRepo::create( sanitize_text_field( $title ), $content, get_current_user_id() );
		return rest_ensure_response( array( 'id' => (int) $record->id, 'status' => 201 ) );
	}

	public static function update( $request ) {
		$record = PromptRepo::find_by_id( $request['id'] );
		if ( ! $record ) {
			return Helpers::error( 'Not found', 404 );
		}
		if ( ! self::can_edit( $record ) ) {
			return Helpers::forbidden();
		}

		$title   = Helpers::param( $request, 'title', $record->title );
		$content = Helpers::param( $request, 'content', $record->content );
		$record  = PromptRepo::update( $record->id, sanitize_text_field( $title ), $content );

		return rest_ensure_response( array(
			'id'         => (int) $record->id,
			'title'      => $record->title,
			'content'    => $record->content,
			'created_by' => (int) $record->created_by,
		) );
	}

	public static function delete( $request ) {
		$record = PromptRepo::find_by_id( $request['id'] );
		if ( ! $record ) {
			return Helpers::error( 'Not found', 404 );
		}
		if ( ! self::can_edit( $record ) ) {
			return Helpers::forbidden();
		}
		PromptRepo::delete( $record->id );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * Only the creator edits a prompt — sharing is use-only. The seeded prompt
	 * belongs to nobody, so an administrator maintains it.
	 */
	private static function can_edit( $record ) {
		if ( ! $record ) {
			return false;
		}
		if ( PromptRepo::is_system( $record ) ) {
			return Security::can_manage();
		}
		return ShareRepo::owns( $record->created_by );
	}

	private static function shape( $row ) {
		return array(
			'id'              => (int) $row->id,
			'title'           => $row->title,
			'created_by'      => (int) $row->created_by,
			'created_by_name' => self::user_name( (int) $row->created_by ),
			'is_owner'        => ShareRepo::owns( $row->created_by ),
			'is_system'       => 0 === (int) $row->created_by,
			'created_at'      => $row->created_at,
			'updated_at'      => $row->updated_at,
		);
	}

	private static function user_name( $user_id ) {
		if ( ! $user_id ) {
			return 'System';
		}
		$user = get_userdata( $user_id );
		return $user ? ( $user->display_name ?: $user->user_login ) : 'User #' . $user_id;
	}
}
