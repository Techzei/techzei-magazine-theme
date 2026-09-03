<?php
/**
 * Theme setup and asset loading.
 *
 * @package Techzei_TT5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Techzei image crops and translations.
 */
function techzei_tt5_setup() {
	load_child_theme_textdomain( 'techzei-magazine-theme', get_stylesheet_directory() . '/languages' );

	add_image_size( 'techzei-hero', 1440, 810, true );
	add_image_size( 'techzei-tile', 720, 540, true );
	add_image_size( 'techzei-feed', 560, 360, true );
}
add_action( 'after_setup_theme', 'techzei_tt5_setup' );

/**
 * Load the small, local stylesheets used by the child theme.
 */
function techzei_tt5_enqueue_assets() {
	$theme = wp_get_theme();

	wp_enqueue_style( 'techzei-magazine-theme', get_stylesheet_uri(), array(), $theme->get( 'Version' ) );
	wp_enqueue_style(
		'techzei-magazine-theme-article',
		get_stylesheet_directory_uri() . '/assets/css/article.css',
		array( 'techzei-magazine-theme' ),
		$theme->get( 'Version' )
	);
	wp_enqueue_script(
		'techzei-magazine-theme-navigation-fallback',
		get_stylesheet_directory_uri() . '/assets/js/navigation-fallback.js',
		array(),
		$theme->get( 'Version' ),
		array( 'in_footer' => true, 'strategy' => 'defer' )
	);
}
add_action( 'wp_enqueue_scripts', 'techzei_tt5_enqueue_assets' );

/**
 * Keep the article hero available in the initial viewport.
 *
 * WordPress may mark featured images as lazy-loaded by default. The article
 * hero is the page's primary visual and should be requested immediately;
 * other images keep the normal lazy-loading behavior.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Parsed block data.
 * @return string
 */
function techzei_tt5_prioritize_article_hero( $block_content, $block ) {
	if ( empty( $block['attrs']['className'] ) || false === strpos( $block['attrs']['className'], 'tz-feature-image' ) ) {
		return $block_content;
	}

	$block_content = preg_replace( '/\sloading=(?:"[^"]*"|\'[^\']*\')/i', '', $block_content, 1 );
	$block_content = preg_replace( '/\sfetchpriority=(?:"[^"]*"|\'[^\']*\')/i', '', $block_content, 1 );

	return preg_replace(
		'/<img\b/i',
		'<img loading="eager" fetchpriority="high"',
		$block_content,
		1
	);
}
add_filter( 'render_block_core/post-featured-image', 'techzei_tt5_prioritize_article_hero', 10, 2 );

/**
 * Supply meaningful alt text for featured images when the attachment is
 * missing it. Existing non-empty alt text is always preserved.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Parsed block data.
 * @return string
 */
function techzei_tt5_ensure_featured_image_alt( $block_content, $block ) {
	if ( false === strpos( $block_content, '<img' ) ) {
		return $block_content;
	}

	$post_id       = get_the_ID();
	$thumbnail_id  = get_post_thumbnail_id( $post_id );
	$attachment_alt = $thumbnail_id ? get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) : '';
	$alt           = $attachment_alt ? $attachment_alt : get_the_title( $post_id );

	if ( ! $alt ) {
		return $block_content;
	}

	$alt = wp_strip_all_tags( $alt );

	if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
		$tags = new WP_HTML_Tag_Processor( $block_content );
		if ( $tags->next_tag( 'IMG' ) ) {
			$tags->set_attribute( 'alt', $alt );
			return $tags->get_updated_html();
		}
	}

	// Fallback for older WordPress versions. Use a callback so replacement
	// sequences such as $0 in alt text are never interpreted by preg_replace.
	$alt_attribute = ' alt="' . esc_attr( $alt ) . '"';
	if ( preg_match( '/\salt=(?:"[^"]*"|\'[^\']*\')/i', $block_content ) ) {
		return preg_replace_callback(
			'/\salt=(?:"[^"]*"|\'[^\']*\')/i',
			static function () use ( $alt_attribute ) {
				return $alt_attribute;
			},
			$block_content,
			1
		);
	}

	return preg_replace_callback(
		'/<img\b/i',
		static function ( $match ) use ( $alt_attribute ) {
			return $match[0] . $alt_attribute;
		},
		$block_content,
		1
	);
}
add_filter( 'render_block_core/post-featured-image', 'techzei_tt5_ensure_featured_image_alt', 11, 2 );

/**
 * Keep card images lazy and tell the browser their real display widths.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Parsed block data.
 * @return string
 */
function techzei_tt5_optimize_card_images( $block_content, $block ) {
	$class_name = isset( $block['attrs']['className'] ) ? $block['attrs']['className'] : '';
	$size_slug  = isset( $block['attrs']['sizeSlug'] ) ? sanitize_key( $block['attrs']['sizeSlug'] ) : '';

	if ( false === strpos( $block_content, '<img' ) ) {
		return $block_content;
	}

	$size_hint = '';
	$is_hero = 'techzei-hero' === $size_slug || false !== strpos( $class_name, 'tz-feature-image' );
	if ( $is_hero ) {
		$size_hint = '(max-width: 900px) 100vw, 1220px';
	} elseif ( 'techzei-tile' === $size_slug || false !== strpos( $class_name, 'tz-feature-card-small' ) ) {
		$size_hint = '(max-width: 900px) 50vw, 21vw';
	} elseif ( 'techzei-feed' === $size_slug || false !== strpos( $class_name, 'tz-feed-card' ) ) {
		$size_hint = '(max-width: 600px) 108px, 240px';
	}

	if ( ! $size_hint ) {
		return $block_content;
	}

	if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
		$tags = new WP_HTML_Tag_Processor( $block_content );
		if ( $tags->next_tag( 'IMG' ) ) {
			$tags->set_attribute( 'loading', $is_hero ? 'eager' : 'lazy' );
			$tags->set_attribute( 'sizes', $size_hint );
			if ( $is_hero ) {
				$tags->set_attribute( 'fetchpriority', 'high' );
			}
			return $tags->get_updated_html();
		}
	}

	return $block_content;
}
add_filter( 'render_block_core/post-featured-image', 'techzei_tt5_optimize_card_images', 12, 2 );

/**
 * Identify posts authored under the former Valenti system without changing
 * their content. Sites can override this decision with the theme filter.
 *
 * @param WP_Post $post Post being inspected.
 * @return bool
 */
function techzei_tt5_is_legacy_post( $post ) {
	if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
		return false;
	}

	$legacy_shortcodes = array( 'column', 'alert', 'button', 'pullquote', 'hr', 'attention' );
	$has_legacy_markup = false;
	foreach ( $legacy_shortcodes as $shortcode ) {
		if ( has_shortcode( $post->post_content, $shortcode ) ) {
			$has_legacy_markup = true;
			break;
		}
	}

	$is_pre_relaunch = strtotime( $post->post_date_gmt ) < strtotime( '2024-01-01 00:00:00' );

	return (bool) apply_filters( 'techzei_tt5_is_legacy_post', $has_legacy_markup || $is_pre_relaunch, $post );
}

/** Add an intentional layout class to each individual post. */
function techzei_tt5_post_layout_class( $classes ) {
	if ( ! is_singular( 'post' ) ) {
		return $classes;
	}

	return array_merge( $classes, array( techzei_tt5_is_legacy_post( get_post() ) ? 'tz-legacy-post' : 'tz-current-post' ) );
}
add_filter( 'body_class', 'techzei_tt5_post_layout_class' );
