<?php
/**
 * One-time migration of the desktop app's SQLite database into the
 * Vivechan Transcriber WordPress tables.
 *
 * Usage (from the plugin directory on the server):
 *   php wp-content/plugins/vivechan-transcriber/migration/import-sqlite.php --file=/path/to/transcripts.db
 *
 * Requires the pdo_sqlite extension and an activated WordPress + plugin.
 */

if ( 'cli' !== php_sapi_name() ) {
	exit( "Run this script from the command line only.\n" );
}

require_once dirname( __DIR__, 4 ) . '/wp-load.php';

if ( ! defined( 'VIVECHAN_PATH' ) ) {
	exit( "The Vivechan Transcriber plugin is not active.\n" );
}

use Vivechan\Security;
use Vivechan\Activator;

Activator::activate();

$args = getopt( '', array( 'file:' ) );
if ( empty( $args['file'] ) ) {
	exit( "Usage: php import-sqlite.php --file=/path/to/transcripts.db\n" );
}

$db_path = $args['file'];
if ( ! is_file( $db_path ) ) {
	exit( "SQLite file not found: {$db_path}\n" );
}

if ( ! extension_loaded( 'pdo_sqlite' ) ) {
	exit( "The pdo_sqlite extension is not available.\n" );
}

global $wpdb;

$p  = $wpdb->prefix;
$src = new PDO( 'sqlite:' . $db_path );
$src->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC );

function vc_date( $raw ) {
	// SQLite stores ISO-8601 like 2025-01-01T10:00:00.000Z -> MySQL datetime.
	if ( ! $raw ) {
		return current_time( 'mysql', true );
	}
	$raw = preg_replace( '/Z$/', '', $raw );
	$raw = str_replace( 'T', ' ', $raw );
	if ( strlen( $raw ) > 19 ) {
		$raw = substr( $raw, 0, 19 );
	}
	return $raw;
}

$now = current_time( 'mysql', true );

// ---- system_prompts -------------------------------------------------------
$sp_map = array();
$sp_src = $src->query( 'SELECT id, title, content, created_at FROM system_prompts' );
if ( $sp_src ) {
	foreach ( $sp_src as $row ) {
		$wpdb->insert(
			"{$p}vivechan_system_prompts",
			array(
				'title'      => $row['title'],
				'content'    => $row['content'],
				'created_by' => 0,
				'created_at' => vc_date( $row['created_at'] ),
				'updated_at' => vc_date( $row['created_at'] ),
			),
			array( '%s', '%s', '%d', '%s', '%s' )
		);
		$sp_map[ (int) $row['id'] ] = (int) $wpdb->insert_id;
	}
}
fwrite( STDERR, 'Imported ' . count( $sp_map ) . " system prompts.\n" );

// ---- ai_integrations ------------------------------------------------------
$ai_map = array();
$ai_src = $src->query( 'SELECT id, title, api_key, type, model, chunk_size, created_at FROM ai_integrations' );
if ( $ai_src ) {
	foreach ( $ai_src as $row ) {
		$wpdb->insert(
			"{$p}vivechan_ai_integrations",
			array(
				'title'      => $row['title'],
				'api_key'    => Security::encrypt( (string) $row['api_key'] ),
				'type'       => $row['type'],
				'model'      => $row['model'] ?: null,
				'chunk_size' => (int) ( $row['chunk_size'] ?: 800 ),
				'created_by' => 0,
				'created_at' => vc_date( $row['created_at'] ),
				'updated_at' => vc_date( $row['created_at'] ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);
		$ai_map[ (int) $row['id'] ] = (int) $wpdb->insert_id;
	}
}
fwrite( STDERR, 'Imported ' . count( $ai_map ) . " integrations (keys re-encrypted).\n" );

// ---- transcripts ----------------------------------------------------------
$t_src = $src->query(
	'SELECT id, video_id, filename, name, title, model, raw_length, processed_raw_length,
	        chunks, used_chunk_size, prompt_used, status, error, content, raw_transcript,
	        processed_chunks, system_prompt_id, integration_id, created_at
	 FROM transcripts'
);
$count = 0;
if ( $t_src ) {
	foreach ( $t_src as $row ) {
		$status = $row['status'];
		// Anything mid-flight in the desktop app becomes an ERROR so users can retry.
		if ( ! in_array( $status, array( 'COMPLETED', 'ERROR' ), true ) ) {
			$status = 'ERROR';
		}

		$wpdb->insert(
			"{$p}vivechan_transcripts",
			array(
				'video_id'             => $row['video_id'],
				'filename'             => $row['filename'] ?: ( $row['video_id'] . '.txt' ),
				'name'                 => $row['name'] ?: null,
				'title'                => $row['title'] ?: null,
				'model'                => $row['model'] ?: null,
				'raw_length'           => (int) $row['raw_length'],
				'processed_raw_length' => (int) $row['processed_raw_length'],
				'chunks'               => (int) $row['chunks'],
				'used_chunk_size'      => $row['used_chunk_size'] ? (int) $row['used_chunk_size'] : null,
				'prompt_used'          => $row['prompt_used'] ?: null,
				'status'               => $status,
				'error'                => ( 'ERROR' === $status && ! empty( $row['error'] ) ) ? $row['error'] : ( 'ERROR' === $status ? 'Imported from desktop app. Please retry.' : null ),
				'content'              => $row['content'] ?: null,
				'raw_transcript'       => $row['raw_transcript'] ?: null,
				'processed_chunks'     => $row['processed_chunks'] ?: '[]',
				'system_prompt_id'     => isset( $sp_map[ (int) $row['system_prompt_id'] ] ) ? $sp_map[ (int) $row['system_prompt_id'] ] : null,
				'integration_id'       => isset( $ai_map[ (int) $row['integration_id'] ] ) ? $ai_map[ (int) $row['integration_id'] ] : null,
				'created_by'           => 0,
				'created_at'           => vc_date( $row['created_at'] ),
				'updated_at'           => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
		);
		$count++;
	}
}
fwrite( STDERR, "Imported {$count} transcripts.\n" );

fwrite( STDERR, "Done. In-flight transcripts were imported as ERROR and can be retried from the UI.\n" );
