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
 * Render accessible share links for the current post without JavaScript.
 *
 * @return string
 */
function techzei_tt5_share_links() {
	if ( ! is_singular( 'post' ) ) {
		return '';
	}

	$url   = rawurlencode( get_permalink() );
	$title = rawurlencode( get_the_title() );
	$links = array(
		'X'        => 'https://twitter.com/intent/tweet?url=' . $url . '&text=' . $title,
		'Facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . $url,
		'LinkedIn' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $url,
		'WhatsApp' => 'https://api.whatsapp.com/send?text=' . $title . '%20' . $url,
	);
	$output = '<div class="tz-share-links" aria-label="' . esc_attr__( 'Share this story', 'techzei-magazine-theme' ) . '">';

	foreach ( $links as $label => $href ) {
		$output .= sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer" aria-label="%2$s">%3$s</a>',
			esc_url( $href ),
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
	$content = get_post_field( 'post_content', get_the_ID() );
	$words   = str_word_count( wp_strip_all_tags( $content ) );
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
 * Render related posts from the current post's WordPress categories.
 *
 * @return string
 */
function techzei_tt5_related_stories() {
	if ( ! is_singular( 'post' ) ) {
		return '';
	}

	$post_id  = get_the_ID();
	$term_ids = wp_get_post_terms( $post_id, 'category', array( 'fields' => 'ids' ) );

	if ( empty( $term_ids ) || is_wp_error( $term_ids ) ) {
		return '';
	}

	$posts = techzei_tt5_editorial_query(
		array(
			'post__not_in' => array( $post_id ),
			'tax_query'    => array(
				array(
					'taxonomy' => 'category',
					'field'    => 'term_id',
					'terms'    => $term_ids,
				),
			),
		)
	);

	return techzei_tt5_render_story_list( $posts, 'tz-related-stories', __( 'Keep reading', 'techzei-magazine-theme' ), __( 'More on this topic', 'techzei-magazine-theme' ) );
}
add_shortcode( 'techzei_related_stories', 'techzei_tt5_related_stories' );

/**
 * Render latest stories without repeating the current post.
 *
 * @return string
 */
function techzei_tt5_latest_stories() {
	$current_post_id = get_the_ID();
	$posts           = techzei_tt5_editorial_query(
		array(
			'posts_per_page' => 5,
			'post__not_in'   => array( $current_post_id ),
			'orderby'        => 'date',
			'order'          => 'DESC',
			'date_query'     => array( array( 'after' => '5 years ago' ) ),
		)
	);

	// Prefer current coverage, but retain a fallback for sites with fewer than
	// five recent posts so the module never appears empty.
	if ( count( $posts->posts ) < 5 ) {
		$posts = techzei_tt5_editorial_query(
			array(
				'posts_per_page' => 5,
				'post__not_in'   => array( $current_post_id ),
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
	}

	return techzei_tt5_render_story_list( $posts, 'tz-latest-stories' );
}
add_shortcode( 'techzei_latest_stories', 'techzei_tt5_latest_stories' );

/** Render the old-theme-style review discovery list for article sidebars. */
function techzei_tt5_latest_reviews() {
	$current_post_id = get_the_ID();
	$reviews         = techzei_tt5_editorial_query(
		array(
			'posts_per_page' => 4,
			'post__not_in'   => array( $current_post_id ),
			'category_name'  => 'review',
			'orderby'        => 'date',
			'order'          => 'DESC',
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
		$thumbnail = get_the_post_thumbnail(
			get_the_ID(),
			'techzei-feed',
			array(
				'class'   => 'tz-story-thumb-image',
				'loading' => 'lazy',
				'decoding' => 'async',
			)
		);
		$output .= '<li>';
		if ( $thumbnail ) {
			$output .= '<a class="tz-story-thumb" href="' . esc_url( get_permalink() ) . '" tabindex="-1" aria-hidden="true">' . $thumbnail . '</a>';
		}
		$output .= '<div class="tz-story-copy"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a>';
		$output .= '<time datetime="' . esc_attr( get_the_date( DATE_W3C ) ) . '">' . esc_html( get_the_date() ) . '</time></div></li>';
	}

	wp_reset_postdata();

	return $heading ? $output . '</ol></section>' : $output . '</ol>';
}
