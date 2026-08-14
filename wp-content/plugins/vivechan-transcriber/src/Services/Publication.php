<?php

namespace Vivechan\Services;

defined('ABSPATH') || exit;

use Vivechan\Helpers\Chapters;
use Vivechan\Models\TranscriptRepo;

/**
 * Blog post lifecycle for completed transcripts.
 *
 * When a transcript reaches COMPLETED an auto-draft WordPress post of the
 * "vivechan" post type is created so Vivechaks can edit it in the block
 * editor. Publication states (draft → reviewed → final) are tracked in post
 * meta; "final" flips the post to published (publicly visible). All Vivechaks
 * can view and edit these posts.
 */
final class Publication {

	/**
	 * Publishing produces an ordinary WordPress post, so it lands in the blog
	 * loop, the feed and its chapter's category archive, and the active theme
	 * renders it.
	 */
	const POST_TYPE = 'post';

	/**
	 * The custom type earlier releases published into. Still registered so
	 * anything not yet converted stays reachable, and still searched when
	 * looking up a transcript's post.
	 */
	const LEGACY_POST_TYPE = 'vivechan';

	const REWRITE_SLUG = 'vivechan-page';

	const META_TRANSCRIPT  = '_vivechan_transcript_id';
	const META_CHAPTER     = '_vivechan_chapter';
	const META_LEVEL       = '_vivechan_level';
	const META_PUB_STATUS  = '_vivechan_pub_status';

	const INDEX_PAGE_TITLE = 'Vivechan Index';
	const INDEX_PAGE_SLUG  = 'vivechan-index';
	const META_INDEX_PAGE  = '_vivechan_index_page';

	const STATUS = array( 'draft', 'reviewed', 'final' );

	/**
	 * Block editor stores HTML; render the transcript as styled HTML so the
	 * post and its public page match the downloadable document.
	 */
	public static function content_for_post( $record ) {
		$meta = array();
		if ( (int) $record->chapter > 0 ) {
			$meta['Chapter'] = Chapters::label( $record->chapter );
			$level           = Chapters::level_for_chapter( $record->chapter );
			if ( $level ) {
				$meta['Level'] = $level;
			}
		}
		if ( ! empty( $record->name ) ) {
			$meta['Name'] = $record->name;
		}
		if ( ! empty( $record->created_at ) ) {
			$meta['Created'] = mysql2date( 'F j, Y', $record->created_at, false );
		}
		return HtmlRenderer::inner( $record, $meta );
	}

	public static function register_post_type() {
		register_post_type(
			self::LEGACY_POST_TYPE,
			array(
				'labels'        => array(
					'name'          => 'Vivechans',
					'singular_name' => 'Vivechan',
					'add_new_item'  => 'Add New Vivechan',
				),
				'public'        => true,
				'show_in_menu'  => false,
				'show_in_rest'  => true,
				// 'thumbnail' gives each Vivechan a featured image, which the
				// public index uses as its card art.
				'supports'      => array( 'title', 'editor', 'revisions', 'author', 'thumbnail' ),
				'rewrite'       => array( 'slug' => self::REWRITE_SLUG ),
				'capability_type' => 'vivechan_post',
				'map_meta_cap'  => true,
				'has_archive'   => false,
				'menu_icon'     => 'dashicons-format-status',
			)
		);
	}

	/**
	 * Create (or refresh) the draft blog post for a completed transcript.
	 * Returns the post ID, or 0 on failure.
	 */
	public static function ensure_draft( $record ) {
		if ( ! $record || ! isset( $record->id ) ) {
			return 0;
		}

		$existing = $record->post_id ? (int) $record->post_id : self::find_post_for_transcript( $record->id );
		if ( $existing && get_post( $existing ) ) {
			self::sync_post( $existing, $record );
			return $existing;
		}

		$title   = $record->name ?: ( $record->title ?: ( 'Transcript ' . $record->video_id ) );
		$content = self::content_for_post( $record );
		if ( '' === trim( strip_tags( $content ) ) ) {
			return 0;
		}

		$chapter = (int) ( $record->chapter ?: 0 );
		$level   = Chapters::level_for_chapter( $chapter );

		$post_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'draft',
				'post_title'   => $title,
				'post_content' => $content,
				'post_author'  => (int) $record->created_by,
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		update_post_meta( $post_id, self::META_TRANSCRIPT, (int) $record->id );
		update_post_meta( $post_id, self::META_CHAPTER, $chapter );
		update_post_meta( $post_id, self::META_LEVEL, $level ?: '' );
		update_post_meta( $post_id, self::META_PUB_STATUS, 'draft' );

		TranscriptRepo::set_post_id( $record->id, (int) $post_id );

		return (int) $post_id;
	}

	public static function sync_post( $post_id, $record ) {
		if ( ! $post_id || ! $record || ! isset( $record->id ) ) {
			return;
		}
		$meta  = get_post_meta( $post_id, self::META_PUB_STATUS, true );
		$state = in_array( $meta, self::STATUS, true ) ? $meta : 'draft';

		$wp_status = ( 'final' === $state ) ? 'publish' : 'draft';

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_status'  => $wp_status,
				'post_title'   => $record->name ?: ( $record->title ?: get_the_title( $post_id ) ),
				'post_content' => self::content_for_post( $record ),
			)
		);

		$chapter = (int) $record->chapter;
		if ( $chapter > 0 ) {
			update_post_meta( $post_id, self::META_CHAPTER, $chapter );
			$level = Chapters::level_for_chapter( $chapter );
			update_post_meta( $post_id, self::META_LEVEL, $level ?: '' );
			self::apply_chapter_category( $post_id, $chapter );
		}
	}

	/**
	 * Set the publication state for a transcript's post.
	 * $status ∈ draft | reviewed | final. final → published publicly.
	 */
	public static function set_status( $record, $status ) {
		if ( ! $record || ! isset( $record->id ) ) {
			throw new \RuntimeException( 'Transcript not found.' );
		}
		if ( ! in_array( $status, self::STATUS, true ) ) {
			throw new \RuntimeException( 'Invalid publication status.' );
		}

		// Publishing publicly is the senior reviewer's call (Vivechan Editor or
		// administrator). Vivechaks can still move a transcript to "reviewed".
		if ( 'final' === $status && ! \Vivechan\Security::can_publish() ) {
			throw new \RuntimeException( 'You do not have permission to publish a Vivechan. Ask a Vivechan Editor or an administrator.' );
		}

		$post_id = (int) ( $record->post_id ?: self::ensure_draft( $record ) );
		if ( ! $post_id ) {
			throw new \RuntimeException( 'A draft post could not be created yet. Make sure the transcript is completed.' );
		}

		if ( 'final' === $status ) {
			// Publishing is reachable only from "reviewed". The review step is
			// the point of the sequence, and hiding the button is not a rule —
			// this stops the state being skipped by calling the endpoint.
			$current = get_post_meta( $post_id, self::META_PUB_STATUS, true );
			if ( 'reviewed' !== $current ) {
				throw new \RuntimeException( 'Mark this reviewed before publishing it.' );
			}

			// The public index groups Vivechans by chapter, so publishing
			// without one produces a page that is listed nowhere.
			$chapter = (int) $record->chapter;
			if ( $chapter < 1 || $chapter > 18 ) {
				throw new \RuntimeException( 'Set a chapter before publishing — the index lists Vivechans by chapter.' );
			}

			// Make sure the post carries the chapter the transcript has, so it
			// appears under the right heading the moment it goes live — and in
			// the chapter's category archive.
			update_post_meta( $post_id, self::META_CHAPTER, $chapter );
			update_post_meta( $post_id, self::META_LEVEL, Chapters::level_for_chapter( $chapter ) ?: '' );
			self::apply_chapter_category( $post_id, $chapter );
		}

		update_post_meta( $post_id, self::META_PUB_STATUS, $status );

		$wp_status = ( 'final' === $status ) ? 'publish' : 'draft';
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $wp_status,
			)
		);

		return $post_id;
	}

	/**
	 * Attach post metadata (chapter, level, links, pub status) to a row.
	 */
	public static function enrich( $record ) {
		$record = (array) $record;

		$record['chapter']       = isset( $record['chapter'] ) ? (int) $record['chapter'] : 0;
		$record['post_id']       = isset( $record['post_id'] ) ? (int) $record['post_id'] : 0;
		$record['level']         = null;
		$record['pub_status']    = null;
		$record['post_title']    = null;
		$record['post_url']      = null;
		$record['post_edit_url'] = null;
		$record['image_id']      = 0;
		$record['image_url']     = null;

		if ( $record['chapter'] > 0 ) {
			$record['level'] = Chapters::level_for_chapter( $record['chapter'] );
		}

		if ( ! $record['post_id'] && isset( $record['id'] ) ) {
			$record['post_id'] = (int) self::find_post_for_transcript( $record['id'] );
		}

		if ( $record['post_id'] ) {
			$post = get_post( $record['post_id'] );
			if ( $post ) {
				$meta = get_post_meta( $record['post_id'], self::META_PUB_STATUS, true );
				$record['pub_status'] = in_array( $meta, self::STATUS, true ) ? $meta : null;
				$record['post_title'] = $post->post_title;
				$record['post_url']   = get_permalink( $post );
				$record['post_edit_url'] = get_edit_post_link( $post, 'raw' );

				$record['image_id']  = (int) get_post_thumbnail_id( $post );
				$record['image_url'] = $record['image_id']
					? (string) wp_get_attachment_image_url( $record['image_id'], 'medium' )
					: null;
				if ( ! $record['chapter'] ) {
					$record['chapter'] = (int) get_post_meta( $record['post_id'], self::META_CHAPTER, true );
					$record['level']   = Chapters::level_for_chapter( $record['chapter'] );
				}
			}
		}

		return (object) $record;
	}

	/**
	 * Create the L1–L4 public index page once. It holds the [vivechan_index]
	 * shortcode so chapter lists appear without manual setup.
	 */
	public static function ensure_index_page() {
		$existing = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'meta_key'       => self::META_INDEX_PAGE,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		if ( ! empty( $existing ) ) {
			return (int) $existing[0];
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => self::INDEX_PAGE_TITLE,
				'post_name'    => self::INDEX_PAGE_SLUG,
				'post_content' => '[vivechan_index]',
			),
			true
		);

		if ( is_wp_error( $page_id ) || ! $page_id ) {
			return 0;
		}

		update_post_meta( $page_id, self::META_INDEX_PAGE, 1 );
		return (int) $page_id;
	}

	/**
	 * Use the plugin's styled single template for published vivechan posts so
	 * the public page matches the downloadable HTML document.
	 */
	public static function single_template( $template ) {
		// Only the legacy type uses the plugin's own template. Ordinary posts
		// are rendered by the active theme, which is the point of publishing
		// into them.
		if ( is_singular( self::LEGACY_POST_TYPE ) ) {
			$custom = VIVECHAN_PATH . 'templates/single-vivechan.php';
			if ( is_file( $custom ) ) {
				return $custom;
			}
		}
		return $template;
	}

	/**
	 * Is this transcript's page live on the site?
	 */
	public static function is_published( $record ) {
		if ( ! $record || empty( $record->post_id ) ) {
			return false;
		}
		return 'final' === get_post_meta( (int) $record->post_id, self::META_PUB_STATUS, true );
	}

	/**
	 * Top-level category for a Learn Geeta level (L1–L4).
	 *
	 * @return int term id, or 0 if it could not be resolved.
	 */
	public static function level_category( $level ) {
		$level = strtoupper( (string) $level );
		if ( ! in_array( $level, array( 'L1', 'L2', 'L3', 'L4' ), true ) ) {
			return 0;
		}

		$slug = 'level-' . strtolower( $level );
		$term = get_term_by( 'slug', $slug, 'category' );
		if ( $term ) {
			return (int) $term->term_id;
		}

		$created = wp_insert_term( $level, 'category', array( 'slug' => $slug ) );
		if ( is_wp_error( $created ) ) {
			$term = get_term_by( 'slug', $slug, 'category' );
			return $term ? (int) $term->term_id : 0;
		}

		return (int) $created['term_id'];
	}

	/**
	 * Category for a chapter, as a child of its level.
	 *
	 * Posts are filed against the chapter only. WordPress category archives
	 * include child categories, so the level archive lists everything beneath
	 * it without the post being in two categories.
	 *
	 * Keyed on the slug rather than the name, so renaming a category in
	 * wp-admin does not cause a duplicate on the next publish.
	 *
	 * @return int term id, or 0 if it could not be resolved.
	 */
	public static function chapter_category( $chapter ) {
		$chapter = (int) $chapter;
		if ( $chapter < 1 || $chapter > 18 ) {
			return 0;
		}

		$parent = self::level_category( Chapters::level_for_chapter( $chapter ) );
		$slug   = 'chapter-' . $chapter;
		$term   = get_term_by( 'slug', $slug, 'category' );

		if ( $term ) {
			// Releases before the level hierarchy created these at the top
			// level; adopt them rather than leaving them stranded.
			if ( $parent && (int) $term->parent !== $parent ) {
				wp_update_term( (int) $term->term_id, 'category', array( 'parent' => $parent ) );
			}
			return (int) $term->term_id;
		}

		$args = array( 'slug' => $slug );
		if ( $parent ) {
			$args['parent'] = $parent;
		}

		$created = wp_insert_term( Chapters::label( $chapter ), 'category', $args );
		if ( is_wp_error( $created ) ) {
			// Most likely a name collision with an existing category; fall back
			// to whatever is already there.
			$term = get_term_by( 'slug', $slug, 'category' );
			return $term ? (int) $term->term_id : 0;
		}

		return (int) $created['term_id'];
	}

	/**
	 * Re-parent chapter categories left at the top level by an earlier
	 * release. Only touches terms that already exist — it does not create all
	 * eighteen chapters on a site that has published two.
	 */
	public static function ensure_chapter_taxonomy() {
		for ( $chapter = 1; $chapter <= 18; $chapter++ ) {
			if ( get_term_by( 'slug', 'chapter-' . $chapter, 'category' ) ) {
				self::chapter_category( $chapter );
			}
		}
	}

	/**
	 * Put the post in its chapter's category.
	 *
	 * Any other chapter category is dropped (the chapter can be changed), but
	 * categories added by hand in wp-admin are left alone — replacing the whole
	 * set would quietly undo an editor's own filing.
	 */
	public static function apply_chapter_category( $post_id, $chapter ) {
		$target = self::chapter_category( $chapter );
		if ( ! $target ) {
			return;
		}

		$keep = array();
		foreach ( wp_get_post_categories( (int) $post_id ) as $term_id ) {
			$term = get_term( $term_id, 'category' );
			if ( $term && ! is_wp_error( $term ) && 0 === strpos( $term->slug, 'chapter-' ) ) {
				continue;
			}
			$keep[] = (int) $term_id;
		}
		$keep[] = $target;

		wp_set_post_categories( (int) $post_id, array_values( array_unique( $keep ) ), false );
	}

	/**
	 * Convert posts published by earlier releases into ordinary posts.
	 *
	 * Runs once per version from Plugin::maybe_provision(). Transcripts keep
	 * pointing at the same post id, so nothing is duplicated and permalinks are
	 * the only thing that change.
	 */
	public static function migrate_legacy_posts() {
		$legacy = get_posts(
			array(
				'post_type'      => self::LEGACY_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $legacy as $post_id ) {
			set_post_type( (int) $post_id, self::POST_TYPE );

			$chapter = (int) get_post_meta( $post_id, self::META_CHAPTER, true );
			if ( $chapter ) {
				self::apply_chapter_category( (int) $post_id, $chapter );
			}
		}

		return count( $legacy );
	}

	public static function find_post_for_transcript( $transcript_id ) {
		$posts = get_posts(
			array(
				// Both types: a transcript published by an earlier release still
				// points at a vivechan post, and must not gain a second one.
				'post_type'      => array( self::POST_TYPE, self::LEGACY_POST_TYPE ),
				'post_status'    => array( 'draft', 'pending', 'publish' ),
				'meta_key'       => self::META_TRANSCRIPT,
				'meta_value'     => (int) $transcript_id,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		return ! empty( $posts ) ? (int) $posts[0] : 0;
	}

	/**
	 * Grant Vivechak + admin the post-type capabilities.
	 */
	public static function add_caps() {
		$caps = array(
			'edit_vivechan_posts',
			'edit_others_vivechan_posts',
			'publish_vivechan_posts',
			'read_vivechan_posts',
			'read_private_vivechan_posts',
		);

		foreach ( array( \Vivechan\Activator::ROLE_VIVECHAK, \Vivechan\Activator::ROLE_EDITOR ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				foreach ( $caps as $cap ) {
					$role->add_cap( $cap );
				}
			}
		}

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( $caps as $cap ) {
				$admin->add_cap( $cap );
			}
			$admin->add_cap( 'delete_vivechan_posts' );
		}
	}

	public static function remove_caps() {
		$caps = array(
			'edit_vivechan_posts',
			'edit_others_vivechan_posts',
			'publish_vivechan_posts',
			'read_vivechan_posts',
			'read_private_vivechan_posts',
			'delete_vivechan_posts',
		);
		foreach ( array( \Vivechan\Activator::ROLE_VIVECHAK, \Vivechan\Activator::ROLE_EDITOR ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				foreach ( $caps as $cap ) {
					$role->remove_cap( $cap );
				}
			}
		}
	}
}
