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
 * Render related posts from the current post's WordPress categories.
 *
 * @return string
 */
function techzei_tt5_related_stories() {
	if ( ! is_singular( 'post' ) || ! techzei_tt5_get_editorial_setting( 'articles.related_stories', true ) ) {
		return '';
	}

	$post_id  = get_the_ID();
	$term_ids = wp_get_post_terms( $post_id, 'category', array( 'fields' => 'ids' ) );

	if ( empty( $term_ids ) || is_wp_error( $term_ids ) ) {
		return '';
	}

	$posts = techzei_tt5_editorial_query(
		array(
			'posts_per_page' => min( 6, max( 2, absint( techzei_tt5_get_editorial_setting( 'articles.related_count', 3 ) ) ) ),
			'post__not_in' => array( $post_id ),
			'orderby'      => 'date',
			'order'        => 'DESC',
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
