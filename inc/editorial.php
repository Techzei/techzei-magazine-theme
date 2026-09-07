<?php
/**
 * Server-rendered editorial components.
 *
 * @package Techzei_TT5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get a compact list of published posts for a theme component.
 *
 * @param array $args Query arguments to merge with safe defaults.
 * @return WP_Query
 */
function techzei_tt5_editorial_query( $args = array() ) {
	$defaults = array(
		'post_type'           => 'post',
		'posts_per_page'      => 3,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);

	return new WP_Query( wp_parse_args( $args, $defaults ) );
}

/**
 * Read a theme behaviour setting without owning the settings schema here.
 *
 * The 3.1 settings module provides techzei_tt5_get_settings(). Keeping this
 * optional lets the editorial layer remain safe during a fresh install,
 * upgrade, or partial integration: frontend output uses the documented
 * default until the central resolver is loaded.
 *
 * Expected keys are documented for the settings worker and intentionally use
 * one dotted namespace: articles.*, sidebar.*.
 *
 * @param string $key     Settings resolver key.
 * @param mixed  $default Default when the resolver is unavailable.
 * @return mixed
 */
function techzei_tt5_get_editorial_setting( $key, $default ) {
	if ( function_exists( 'techzei_tt5_get_settings' ) ) {
		$value = techzei_tt5_get_settings( $key );
		return null === $value ? $default : $value;
	}

	// Compatibility with an early settings-worker API shape while the central
	// resolver is integrated. The settings module remains the schema owner.
	if ( function_exists( 'techzei_tt5_get_setting' ) ) {
		$parts = explode( '.', (string) $key, 2 );
		return 2 === count( $parts ) ? techzei_tt5_get_setting( $parts[0], $parts[1], $default ) : $default;
	}

	return $default;
}

/**
 * Render accessible share links for the current post without JavaScript.
 *
 * @return string
 */
function techzei_tt5_share_links() {
	if ( ! is_singular( 'post' ) || ! techzei_tt5_get_editorial_setting( 'articles.share_links', true ) ) {
		return '';
	}

	$url   = rawurlencode( get_permalink() );
	$title = rawurlencode( get_the_title() );
	$available_links = array(
		'X'        => 'https://twitter.com/intent/tweet?url=' . $url . '&text=' . $title,
		'Facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . $url,
		'LinkedIn' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $url,
		'WhatsApp' => 'https://api.whatsapp.com/send?text=' . $title . '%20' . $url,
	);
	$destination_keys = array(
		'x'        => 'X',
		'facebook' => 'Facebook',
		'linkedin' => 'LinkedIn',
		'whatsapp' => 'WhatsApp',
	);
	$destinations = techzei_tt5_get_editorial_setting( 'articles.share_destinations', array_keys( $destination_keys ) );
	$destinations = is_array( $destinations ) ? array_map( 'sanitize_key', $destinations ) : array();
	$destinations = array_values( array_intersect( array_keys( $destination_keys ), $destinations ) );

	if ( empty( $destinations ) ) {
		return '';
	}

	$output = '<div class="tz-share-links" aria-label="' . esc_attr__( 'Share this story', 'techzei-magazine-theme' ) . '">';

	foreach ( $destinations as $destination ) {
		$label = $destination_keys[ $destination ];
		$output .= sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer" aria-label="%2$s">%3$s</a>',
			esc_url( $available_links[ $label ] ),
			esc_attr( sprintf( __( 'Share on %s', 'techzei-magazine-theme' ), $label ) ),
			esc_html( $label )
		);
	}

	return $output . '</div>';
}
add_shortcode( 'techzei_share', 'techzei_tt5_share_links' );

/**
 * Estimate post reading time at 220 words a minute.
 *
 * @return string
 */
function techzei_tt5_reading_time() {
	if ( ! techzei_tt5_get_editorial_setting( 'articles.show_reading_time', true ) ) {
		return '';
	}

	$content = get_post_field( 'post_content', get_the_ID() );
	$content = strip_shortcodes( (string) $content );
	// strip_shortcodes() only knows registered tags. Remove remaining shortcode
	// syntax as text as well, without executing unknown or unsafe handlers.
	$content = preg_replace( '/\[(?:\/?)[a-z][a-z0-9_-]*(?:\s[^\]]*)?\]/i', ' ', $content );
	$content = wp_strip_all_tags( (string) $content );
	$words   = preg_match_all( '/[\p{L}\p{N}]+(?:[\'’\-][\p{L}\p{N}]+)*/u', $content, $matches );
	$words   = false === $words ? str_word_count( $content ) : $words;
	$minutes = max( 1, (int) ceil( $words / 220 ) );

	return esc_html(
		sprintf(
			/* translators: %s: estimated reading time in minutes. */
			_n( '%s min read', '%s min read', $minutes, 'techzei-magazine-theme' ),
			number_format_i18n( $minutes )
		)
	);
}
add_shortcode( 'techzei_reading_time', 'techzei_tt5_reading_time' );

/**
 * Normalize a bounded list of post IDs used by editorial modules.
 *
 * @param array $ids       Candidate post IDs.
 * @param int   $limit     Maximum number of IDs to return.
 * @param array $exclude   IDs that must not be returned.
 * @return array
 */
function techzei_tt5_normalize_editorial_ids( $ids, $limit, $exclude = array() ) {
	$ids     = is_array( $ids ) ? $ids : array();
	$exclude = array_map( 'absint', is_array( $exclude ) ? $exclude : array() );
	$clean   = array();

	foreach ( $ids as $id ) {
		$id = absint( $id );
		if ( $id && ! in_array( $id, $exclude, true ) && ! in_array( $id, $clean, true ) ) {
			$clean[] = $id;
		}
		if ( count( $clean ) >= absint( $limit ) ) {
			break;
		}
	}

	return $clean;
}

/**
 * Return category IDs from most specific to least specific.
 *
 * Child categories are a stronger editorial signal than their ancestors.
 * The list is capped so a post with unusually many terms cannot cause an
 * unbounded sequence of related-story queries.
 *
 * @param array $category_ids Current post category IDs.
 * @return array
 */
function techzei_tt5_order_related_categories( $category_ids ) {
	$category_ids = techzei_tt5_normalize_editorial_ids( $category_ids, 8 );
	$categories   = array();

	foreach ( $category_ids as $category_id ) {
		$categories[] = array(
			'id'    => $category_id,
			'depth' => count( get_ancestors( $category_id, 'category', 'taxonomy' ) ),
		);
	}

	usort(
		$categories,
		function ( $left, $right ) {
			if ( $left['depth'] === $right['depth'] ) {
				return $left['id'] <=> $right['id'];
			}
			return $left['depth'] > $right['depth'] ? -1 : 1;
		}
	);

	return wp_list_pluck( $categories, 'id' );
}

/**
 * Query a bounded set of optional editorial selections.
 *
 * Sticky posts are WordPress's native manual editorial-selection mechanism.
 * They are only consulted when the related-story mode is explicitly set to
 * editorial/editorial-first, or when an integration opts in through the
 * filter below. No post content or schema changes are required.
 *
 * @param array $selection Candidate post IDs, in editorial order.
 * @param int   $limit     Maximum number of posts to return.
 * @param array $exclude   IDs that must not be returned.
 * @return array
 */
function techzei_tt5_editorial_selection_ids( $selection, $limit, $exclude = array() ) {
	$selection = techzei_tt5_normalize_editorial_ids( $selection, 12, $exclude );
	if ( empty( $selection ) ) {
		return array();
	}

	$query = techzei_tt5_editorial_query(
		array(
			'posts_per_page' => min( 6, max( 1, absint( $limit ) ) ),
			'post__in'       => $selection,
			'orderby'        => 'post__in',
			'fields'         => 'ids',
			'post_status'    => 'publish',
		)
	);

	return techzei_tt5_normalize_editorial_ids( $query->posts, $limit, $exclude );
}

/**
 * Resolve related-story IDs in relevance order without unbounded work.
 *
 * The resolver uses editorial selections only when explicitly enabled, then
 * shared tags, then one bounded query per most-specific shared category. Each
 * query is ordered by publication date and post ID so equal-date results are
 * stable. Unrelated posts are not added merely to fill the requested count.
 *
 * @param int $post_id Current post ID.
 * @param int $limit   Maximum number of related posts.
 * @return array
 */
function techzei_tt5_related_story_ids( $post_id, $limit ) {
	$post_id = absint( $post_id );
	$limit   = min( 6, max( 2, absint( $limit ) ) );
	$ids     = array();
	$exclude = array( $post_id );

	$mode = sanitize_key( (string) techzei_tt5_get_editorial_setting( 'articles.related_mode', 'automatic' ) );
	$editorial_first = in_array( $mode, array( 'editorial', 'editorial-first' ), true );
	$editorial_first = (bool) apply_filters( 'techzei_tt5_related_editorial_first', $editorial_first, $post_id, $limit );

	if ( $editorial_first ) {
		$selection = apply_filters(
			'techzei_tt5_editorial_selection',
			get_option( 'sticky_posts', array() ),
			$post_id,
			$limit
		);
		$editorial_ids = techzei_tt5_editorial_selection_ids( $selection, $limit, $exclude );
		$ids          = array_merge( $ids, $editorial_ids );
		$exclude      = array_merge( $exclude, $editorial_ids );
	}

	$tag_ids = wp_get_post_terms(
		$post_id,
		'post_tag',
		array(
			'fields'  => 'ids',
			'orderby' => 'term_id',
			'order'   => 'ASC',
		)
	);
	$tag_ids = is_wp_error( $tag_ids ) ? array() : techzei_tt5_normalize_editorial_ids( $tag_ids, 8 );

	if ( ! empty( $tag_ids ) && count( $ids ) < $limit ) {
		$tag_query = techzei_tt5_editorial_query(
			array(
				'posts_per_page' => min( 18, max( $limit, $limit * 3 ) ),
				'post__not_in'   => $exclude,
				'fields'        => 'ids',
				'orderby'       => array(
					'date' => 'DESC',
					'ID'   => 'DESC',
				),
				'tax_query'     => array(
					array(
						'taxonomy'         => 'post_tag',
						'field'            => 'term_id',
						'terms'            => $tag_ids,
						'operator'         => 'IN',
						'include_children' => false,
					),
				),
			)
		);
		$tag_matches = techzei_tt5_normalize_editorial_ids( $tag_query->posts, $limit - count( $ids ), $exclude );
		$ids         = array_merge( $ids, $tag_matches );
		$exclude     = array_merge( $exclude, $tag_matches );
	}

	$category_ids = wp_get_post_terms(
		$post_id,
		'category',
		array(
			'fields'  => 'ids',
			'orderby' => 'term_id',
			'order'   => 'ASC',
		)
	);
	$category_ids = is_wp_error( $category_ids ) ? array() : techzei_tt5_order_related_categories( $category_ids );

	foreach ( $category_ids as $category_id ) {
		if ( count( $ids ) >= $limit ) {
			break;
		}

		$category_query = techzei_tt5_editorial_query(
			array(
				'posts_per_page' => $limit - count( $ids ),
				'post__not_in'   => $exclude,
				'fields'        => 'ids',
				'orderby'       => array(
					'date' => 'DESC',
					'ID'   => 'DESC',
				),
				'tax_query'     => array(
					array(
						'taxonomy'         => 'category',
						'field'            => 'term_id',
						'terms'            => array( $category_id ),
						'operator'         => 'IN',
						'include_children' => false,
					),
				),
			)
		);
		$category_matches = techzei_tt5_normalize_editorial_ids( $category_query->posts, $limit - count( $ids ), $exclude );
		$ids              = array_merge( $ids, $category_matches );
		$exclude          = array_merge( $exclude, $category_matches );
	}

	$ids = apply_filters( 'techzei_tt5_related_story_ids', $ids, $post_id, $limit );

	return techzei_tt5_normalize_editorial_ids( $ids, $limit, array( $post_id ) );
}

/**
 * Render related posts, preferring shared tags and specific shared categories.
 *
 * @param array $atts Optional shortcode attributes.
 * @return string
 */
function techzei_tt5_related_stories( $atts = array() ) {
	if ( ! is_singular( 'post' ) || ! techzei_tt5_get_editorial_setting( 'articles.related_stories', true ) ) {
		return '';
	}

	$post_id = get_the_ID();
	$limit   = min( 6, max( 2, absint( techzei_tt5_get_editorial_setting( 'articles.related_count', 3 ) ) ) );
	$ids     = techzei_tt5_related_story_ids( $post_id, $limit );

	if ( empty( $ids ) ) {
		return '';
	}

	$posts = techzei_tt5_editorial_query(
		array(
			'posts_per_page' => $limit,
			'post__in'       => $ids,
			'orderby'        => 'post__in',
		)
	);

	$atts = shortcode_atts( array( 'heading' => '1' ), $atts, 'techzei_related_stories' );
	if ( in_array( sanitize_key( (string) $atts['heading'] ), array( '0', 'false', 'no', 'none' ), true ) ) {
		return techzei_tt5_render_story_list( $posts, 'tz-related-stories' );
	}

	return techzei_tt5_render_story_list( $posts, 'tz-related-stories', __( 'Keep reading', 'techzei-magazine-theme' ), __( 'More on this topic', 'techzei-magazine-theme' ) );
}
add_shortcode( 'techzei_related_stories', 'techzei_tt5_related_stories' );

/**
 * Render latest stories without repeating the current post.
 *
 * @return string
 */
function techzei_tt5_latest_stories() {
	if ( ! techzei_tt5_get_editorial_setting( 'sidebar.latest_stories', true ) ) {
		return '';
	}

	$current_post_id = get_the_ID();
	$posts           = techzei_tt5_editorial_query(
		array(
			'posts_per_page' => min( 6, max( 3, absint( techzei_tt5_get_editorial_setting( 'sidebar.latest_count', 5 ) ) ) ),
			'post__not_in'   => array( $current_post_id ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	return techzei_tt5_render_story_list( $posts, 'tz-latest-stories' );
}
add_shortcode( 'techzei_latest_stories', 'techzei_tt5_latest_stories' );

/** Render the old-theme-style review discovery list for article sidebars. */
function techzei_tt5_latest_reviews() {
	if ( ! techzei_tt5_get_editorial_setting( 'sidebar.reviews', true ) ) {
		return '';
	}

	$current_post_id = get_the_ID();
	$settings_loaded = function_exists( 'techzei_tt5_get_settings' ) || function_exists( 'techzei_tt5_get_setting' );
	$review_term_id  = absint( techzei_tt5_get_editorial_setting( 'sidebar.review_category', 0 ) );

	if ( $settings_loaded ) {
		$review_term = $review_term_id ? get_term( $review_term_id, 'category' ) : false;
		if ( ! $review_term || is_wp_error( $review_term ) ) {
			return '';
		}
		$review_tax_query = array(
			array(
				'taxonomy' => 'category',
				'field'    => 'term_id',
				'terms'    => array( $review_term_id ),
			),
		);
	} else {
		$review_tax_query = array(
			array(
				'taxonomy' => 'category',
				'field'    => 'slug',
				'terms'    => array( 'review' ),
			),
		);
	}

	$reviews         = techzei_tt5_editorial_query(
		array(
			'posts_per_page' => min( 6, max( 2, absint( techzei_tt5_get_editorial_setting( 'sidebar.review_count', 4 ) ) ) ),
			'post__not_in'   => array( $current_post_id ),
			'orderby'        => 'date',
			'order'          => 'DESC',
			'tax_query'      => $review_tax_query,
		)
	);

	return techzei_tt5_render_story_list( $reviews, 'tz-latest-reviews', __( 'Reviews', 'techzei-magazine-theme' ), __( 'Latest reviews', 'techzei-magazine-theme' ) );
}
add_shortcode( 'techzei_latest_reviews', 'techzei_tt5_latest_reviews' );

/**
 * Render a date-labelled story list and reset its query.
 *
 * @param WP_Query $posts   Posts to render.
 * @param string   $class   CSS class for the list or section.
 * @param string   $kicker  Optional section kicker.
 * @param string   $heading Optional section heading.
 * @return string
 */
function techzei_tt5_render_story_list( $posts, $class, $kicker = '', $heading = '' ) {
	if ( ! $posts->have_posts() ) {
		return '';
	}

	$output = $heading ? '<section class="' . esc_attr( $class ) . '">' : '<ol class="' . esc_attr( $class ) . '">';

	if ( $heading ) {
		$output .= '<p class="tz-section-kicker">' . esc_html( $kicker ) . '</p>';
		$output .= '<h2>' . esc_html( $heading ) . '</h2><ol>';
	}

	while ( $posts->have_posts() ) {
		$posts->the_post();
		$image_size = 'tz-related-stories' === $class ? '(max-width: 600px) 88px, 132px' : '88px';
		$thumbnail = get_the_post_thumbnail(
			get_the_ID(),
			'techzei-feed',
			array(
				'class'    => 'tz-story-thumb-image',
				'alt'      => '',
				'loading'  => 'lazy',
				'decoding' => 'async',
				'sizes'    => $image_size,
			)
		);
		$thumbnail = $thumbnail ? techzei_tt5_process_image_html( $thumbnail, 'list-card', $image_size ) : '';
		$output .= '<li>';
		if ( $thumbnail ) {
			$output .= '<a class="tz-story-thumb" href="' . esc_url( get_permalink() ) . '" tabindex="-1" aria-hidden="true">' . $thumbnail . '</a>';
		} else {
			$output .= '<span class="tz-story-thumb tz-story-thumb-placeholder" aria-hidden="true"></span>';
		}
		$output .= '<div class="tz-story-copy"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a>';
		$output .= '<time datetime="' . esc_attr( get_the_date( DATE_W3C ) ) . '">' . esc_html( get_the_date() ) . '</time></div></li>';
	}

	wp_reset_postdata();

	return $heading ? $output . '</ol></section>' : $output . '</ol>';
}
