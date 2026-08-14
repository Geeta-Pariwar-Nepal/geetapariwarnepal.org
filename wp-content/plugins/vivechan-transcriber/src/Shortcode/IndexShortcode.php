<?php

namespace Vivechan\Shortcode;

defined('ABSPATH') || exit;

use Vivechan\Helpers\Chapters;
use Vivechan\Models\ChapterImages;
use Vivechan\Services\Publication;

/**
 * [vivechan_index] — public catalogue of published Vivechans.
 *
 * Four level tabs (L1–L4). Within a level each chapter is a section with its
 * cover image, followed by a grid of cards — one per published Vivechan.
 *
 * Chapters with nothing published are skipped rather than listed empty: this
 * is a reader-facing catalogue, not an inventory of what is missing.
 */
final class IndexShortcode {

	public static function register() {
		add_shortcode( 'vivechan_index', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'level' => '', // optional: only show one level
			),
			$atts,
			'vivechan_index'
		);

		$levels = array( 'L1', 'L2', 'L3', 'L4' );

		// level="L2" pins the shortcode to one level and drops the tabs; with
		// no attribute the reader gets everything, filterable by level.
		$pinned   = $atts['level'] ? strtoupper( (string) $atts['level'] ) : '';
		$show_all = '' === $pinned;
		if ( $pinned ) {
			$levels = array( $pinned );
		}

		$covers = ChapterImages::all();

		// Render each level once; the "All" pane reuses the same sections.
		$sections_by_level = array();
		$counts_by_level   = array();

		foreach ( $levels as $level ) {
			$sections = '';
			$total    = 0;

			foreach ( Chapters::chapters_for_level( $level ) as $chapter ) {
				$posts = self::chapter_posts( $chapter );
				if ( empty( $posts ) ) {
					continue;
				}
				$total    += count( $posts );
				$sections .= self::chapter_section( $chapter, $posts, $covers );
			}

			$sections_by_level[ $level ] = $sections;
			$counts_by_level[ $level ]   = $total;
		}

		$tabs  = '';
		$panes = '';

		if ( $show_all ) {
			$all       = implode( '', $sections_by_level );
			$all_total = array_sum( $counts_by_level );

			$tabs .= '<button class="vi-tab active" data-level="all">All</button>';
			$panes .= '<div class="vi-pane active" data-level="all">'
				. ( $all_total > 0 ? $all : '<p class="vi-empty">Nothing published yet.</p>' )
				. '</div>';
		}

		foreach ( $levels as $i => $level ) {
			// Without the All tab the first level is the default view.
			$active = ( ! $show_all && 0 === $i ) ? ' active' : '';

			$tabs .= '<button class="vi-tab' . $active . '" data-level="' . esc_attr( $level ) . '">'
				. esc_html( $level )
				. '</button>';

			$sections = $counts_by_level[ $level ] > 0
				? $sections_by_level[ $level ]
				: '<p class="vi-empty">Nothing published in ' . esc_html( $level ) . ' yet.</p>';

			$panes .= '<div class="vi-pane' . $active . '" data-level="' . esc_attr( $level ) . '">'
				. $sections
				. '</div>';
		}

		wp_enqueue_style( 'vivechan-index', VIVECHAN_URL . 'assets/css/vivechan-index.css', array(), VIVECHAN_VERSION );

		return '<div class="vi-wrap">'
			. '<div class="vi-tabs">' . $tabs . '</div>'
			. $panes
			. self::script()
			. '</div>';
	}

	/**
	 * One chapter: a header carrying the chapter's cover, then its cards.
	 */
	private static function chapter_section( $chapter, $posts, $covers ) {
		$cover_id  = isset( $covers[ $chapter ] ) ? (int) $covers[ $chapter ] : 0;
		$cover_url = $cover_id ? wp_get_attachment_image_url( $cover_id, 'thumbnail' ) : '';

		$thumb = $cover_url
			? '<img class="vi-chapter-thumb" src="' . esc_url( $cover_url ) . '" alt="" loading="lazy" />'
			: '<span class="vi-chapter-thumb vi-chapter-thumb--blank">' . esc_html( Chapters::nepali( $chapter ) ) . '</span>';

		$cards = '';
		foreach ( $posts as $post ) {
			$cards .= self::card( $post, $chapter, $cover_url );
		}

		$count = count( $posts );

		return '<section class="vi-chapter">'
			. '<header class="vi-chapter-head">'
			. $thumb
			. '<div class="vi-chapter-meta">'
			. '<h3 class="vi-chapter-title">' . esc_html( Chapters::label( $chapter ) ) . '</h3>'
			. '<span class="vi-count">' . esc_html( $count ) . ' ' . esc_html( 1 === $count ? 'vivechan' : 'vivechans' ) . '</span>'
			. '</div>'
			. '</header>'
			. '<div class="vi-grid">' . $cards . '</div>'
			. '</section>';
	}

	/**
	 * A published Vivechan. Falls back to the chapter cover, then to the
	 * chapter numeral, so a card is never a blank rectangle.
	 */
	private static function card( $post, $chapter, $chapter_cover ) {
		$image = get_the_post_thumbnail_url( $post, 'medium' );
		if ( ! $image ) {
			$image = $chapter_cover;
		}

		$media = $image
			? '<img src="' . esc_url( $image ) . '" alt="" loading="lazy" />'
			: '<span class="vi-card-numeral">' . esc_html( Chapters::nepali( $chapter ) ) . '</span>';

		return '<a class="vi-card" href="' . esc_url( get_permalink( $post ) ) . '">'
			. '<div class="vi-card-media">' . $media . '</div>'
			. '<div class="vi-card-body">'
			. '<h4 class="vi-card-title">' . esc_html( get_the_title( $post ) ) . '</h4>'
			. '<span class="vi-card-date">' . esc_html( get_the_date( 'F j, Y', $post ) ) . '</span>'
			. '</div>'
			. '</a>';
	}

	private static function chapter_posts( $chapter ) {
		$posts = get_posts(
			array(
				// Both, so anything not yet converted still appears.
				'post_type'      => array( Publication::POST_TYPE, Publication::LEGACY_POST_TYPE ),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_key'       => Publication::META_CHAPTER,
				'meta_value'     => (int) $chapter,
			)
		);
		return $posts ? $posts : array();
	}

	private static function script() {
		ob_start();
		?>
<script>
(function () {
	var wrap = document.querySelector('.vi-wrap');
	if (!wrap) return;
	var tabs = wrap.querySelectorAll('.vi-tab');
	var panes = wrap.querySelectorAll('.vi-pane');
	tabs.forEach(function (tab) {
		tab.addEventListener('click', function () {
			tabs.forEach(function (t) { t.classList.remove('active'); });
			panes.forEach(function (p) { p.classList.remove('active'); });
			tab.classList.add('active');
			var target = tab.getAttribute('data-level');
			panes.forEach(function (p) {
				if (p.getAttribute('data-level') === target) p.classList.add('active');
			});
		});
	});
})();
</script>
		<?php
		return ob_get_clean();
	}
}
