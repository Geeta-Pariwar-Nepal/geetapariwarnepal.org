<?php

namespace Vivechan\Models;

defined('ABSPATH') || exit;

use Vivechan\Security;
use Vivechan\Services\ModelCatalog;

/**
 * Repository for AI integrations. API keys are stored encrypted.
 */
final class IntegrationRepo {

	/**
	 * Provider definitions. The `models` lists are a FALLBACK only — the live
	 * catalogue is fetched from each provider by ModelCatalog. Keep these short
	 * and conservative: they are what users see when a provider is unreachable,
	 * and the first entry is the default model for an integration that has none.
	 */
	const PROVIDERS = array(
		'groq'     => array(
			'base_url' => 'https://api.groq.com/openai/v1',
			'models'   => array(
				array( 'id' => 'llama-3.3-70b-versatile', 'label' => 'Llama 3.3 70B' ),
				array( 'id' => 'llama-3.1-8b-instant',    'label' => 'Llama 3.1 8B (fast)' ),
			),
		),
		'deepseek' => array(
			'base_url' => 'https://api.deepseek.com',
			'models'   => array(
				array( 'id' => 'deepseek-reasoner', 'label' => 'DeepSeek R1 (Reasoner)' ),
				array( 'id' => 'deepseek-chat',     'label' => 'DeepSeek V3 (Chat)' ),
			),
		),
		'gemini'   => array(
			'base_url' => null,
			'models'   => array(
				array( 'id' => 'gemini-2.5-pro',   'label' => 'Gemini 2.5 Pro' ),
				array( 'id' => 'gemini-2.5-flash', 'label' => 'Gemini 2.5 Flash' ),
				array( 'id' => 'gemini-2.0-flash', 'label' => 'Gemini 2.0 Flash' ),
			),
		),
	);

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'vivechan_ai_integrations';
	}

	public static function supported_types() {
		return array_keys( self::PROVIDERS );
	}

	/**
	 * Curated fallback models for a provider type.
	 */
	public static function fallback_models( $type ) {
		return isset( self::PROVIDERS[ $type ] ) ? self::PROVIDERS[ $type ]['models'] : array();
	}

	/**
	 * Fallback models grouped by provider type, for the frontend dropdowns
	 * before an API key has been entered. Live lists come from ModelCatalog.
	 */
	public static function type_models() {
		$out = array();
		foreach ( self::PROVIDERS as $type => $provider ) {
			$out[ $type ] = $provider['models'];
		}
		return $out;
	}

	public static function create( $title, $api_key, $type, $model, $chunk_size, $user_id ) {
		global $wpdb;
		$table = self::table();
		$now   = current_time( 'mysql', true );
		$wpdb->insert(
			$table,
			array(
				'title'      => $title,
				'api_key'    => Security::encrypt( $api_key ),
				'type'       => $type,
				'model'      => $model ?: null,
				'chunk_size' => (int) $chunk_size,
				'created_by' => (int) $user_id,
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);
		return self::find_by_id( $wpdb->insert_id );
	}

	public static function update( $id, $fields ) {
		global $wpdb;
		$table  = self::table();
		$row    = self::find_raw( $id );
		if ( ! $row ) {
			return null;
		}

		$data = array( 'updated_at' => current_time( 'mysql', true ) );
		$fmt  = array( '%s' );

		if ( isset( $fields['title'] ) ) {
			$data['title'] = $fields['title'];
			$fmt[]         = '%s';
		}
		if ( isset( $fields['type'] ) ) {
			$data['type'] = $fields['type'];
			$fmt[]        = '%s';
		}
		if ( array_key_exists( 'model', $fields ) ) {
			$data['model'] = '' === $fields['model'] ? null : $fields['model'];
			$fmt[]         = '%s';
		}
		if ( isset( $fields['chunk_size'] ) ) {
			$data['chunk_size'] = (int) $fields['chunk_size'];
			$fmt[]              = '%d';
		}
		if ( isset( $fields['api_key'] ) && '' !== $fields['api_key'] ) {
			$data['api_key'] = Security::encrypt( $fields['api_key'] );
			$fmt[]           = '%s';
		}

		// Drop the cached model list whenever the key or provider changes, so a
		// swapped key never serves the previous key's models.
		if ( isset( $data['api_key'] ) || isset( $data['type'] ) ) {
			ModelCatalog::forget( $row->type, (string) $row->api_key );
		}

		$wpdb->update( $table, $data, array( 'id' => (int) $id ), $fmt, array( '%d' ) );
		return self::find_by_id( $id );
	}

	/**
	 * Integrations the user may use: their own, plus any shared with them.
	 *
	 * Shared users can select the integration for a transcript but never see
	 * the key — it is masked here and only ever decrypted server-side.
	 */
	public static function find_all() {
		global $wpdb;
		$table = self::table();

		$sql  = "SELECT i.* FROM {$table} i WHERE ";
		$sql .= ShareRepo::access_sql( ShareRepo::TYPE_INTEGRATION, 'i' );
		$sql .= ' ORDER BY i.created_at DESC';

		$rows = $wpdb->get_results( $sql );
		foreach ( $rows as $row ) {
			// Decrypt first: the mask must show the tail of the real key, and the
			// model lookup needs the key to talk to the provider.
			$plain        = Security::decrypt( (string) $row->api_key );
			self::annotate( $row, $plain );
			$row->api_key = Security::mask_key( $plain );
			// Lets the UI show Edit/Delete only to the owner.
			$row->is_owner = ShareRepo::owns( $row->created_by );
		}
		return $rows;
	}

	public static function find_by_id( $id ) {
		$row = self::find_raw( $id );
		if ( ! $row ) {
			return null;
		}
		// Owner or shared only — there is no administrator bypass.
		if ( ! self::can_use( $row ) ) {
			return null;
		}
		// find_raw() already decrypted the key.
		self::annotate( $row, (string) $row->api_key );
		$row->api_key  = Security::mask_key( (string) $row->api_key );
		$row->is_owner = ShareRepo::owns( $row->created_by );
		return $row;
	}

	/**
	 * Raw row with the decrypted key. Internal use only — never expose via REST.
	 */
	public static function find_raw( $id ) {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id )
		);
		if ( $row ) {
			$row->api_key = Security::decrypt( (string) $row->api_key );
		}
		return $row;
	}

	public static function find_first_raw() {
		global $wpdb;
		$table = self::table();

		$sql  = "SELECT i.* FROM {$table} i WHERE ";
		$sql .= ShareRepo::access_sql( ShareRepo::TYPE_INTEGRATION, 'i' );
		$sql .= ' ORDER BY i.created_at DESC LIMIT 1';

		$row = $wpdb->get_row( $sql );
		if ( $row ) {
			$row->api_key = Security::decrypt( (string) $row->api_key );
		}
		return $row;
	}

	public static function delete( $id ) {
		global $wpdb;
		$table = self::table();

		$row = self::find_raw( $id );
		if ( $row ) {
			ModelCatalog::forget( $row->type, (string) $row->api_key );
		}

		ShareRepo::purge( ShareRepo::TYPE_INTEGRATION, $id );

		return $wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Only the creator administers an integration — editing it, deleting it,
	 * and choosing who else may use it. Sharing never confers this.
	 */
	public static function owns( $row ) {
		return $row && ShareRepo::owns( $row->created_by );
	}

	/**
	 * Owner or shared: may select this integration for a transcript. The API
	 * key is never exposed to a shared user.
	 */
	public static function can_use( $row ) {
		return $row && ShareRepo::can_access( ShareRepo::TYPE_INTEGRATION, $row->id, $row->created_by );
	}

	/**
	 * Resolve a row into a usable provider config array.
	 */
	public static function resolve_config( $row ) {
		$type       = isset( $row->type ) && isset( self::PROVIDERS[ $row->type ] ) ? $row->type : 'groq';
		$provider   = self::PROVIDERS[ $type ];
		$model      = ! empty( $row->model ) ? $row->model : $provider['models'][0]['id'];
		$chunk_size = ! empty( $row->chunk_size ) ? (int) $row->chunk_size : 800;

		return array(
			'type'       => $type,
			'base_url'   => $provider['base_url'],
			'model'      => $model,
			'api_key'    => isset( $row->api_key ) ? (string) $row->api_key : '',
			'chunk_size' => $chunk_size,
		);
	}

	/**
	 * Attach the model list the frontend dropdowns use. Live when the provider
	 * answers (cached for hours), curated fallback otherwise.
	 *
	 * @param string $api_key Decrypted key — required for the live lookup.
	 */
	private static function annotate( $row, $api_key = '' ) {
		$catalog = ModelCatalog::get( isset( $row->type ) ? $row->type : '', $api_key );

		$row->available_models = $catalog['models'];
		$row->models_source    = $catalog['source'];
		$row->models_error     = $catalog['error'];
	}
}
