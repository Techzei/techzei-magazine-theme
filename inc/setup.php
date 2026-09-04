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
	add_editor_style( 'assets/css/article.css' );

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
	if ( is_singular( 'post' ) || is_page() ) {
		wp_enqueue_style(
			'techzei-magazine-theme-article',
			get_stylesheet_directory_uri() . '/assets/css/article.css',
			array( 'techzei-magazine-theme' ),
			$theme->get( 'Version' )
		);
	}
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
 * Return whether a parsed block has an exact Techzei class token.
 *
 * @param array  $block Parsed block.
 * @param string $class Class token.
 * @return bool
 */
function techzei_tt5_block_has_class( $block, $class ) {
	$class_name = isset( $block['attrs']['className'] ) ? (string) $block['attrs']['className'] : '';
	return techzei_tt5_has_class_token( $class_name, $class );
}

/**
 * Apply central Techzei behaviour settings to recognised theme blocks.
 *
 * This only touches blocks marked by the theme. It never rewrites arbitrary
 * core blocks or recreates components removed in the Site Editor.
 *
 * @param array $block        Parsed block.
 * @param array $source_block Original block.
 * @param array $parent_block Parent block.
 * @return array
 */
function techzei_tt5_apply_block_settings( $block, $source_block, $parent_block ) {
	if ( ! function_exists( 'techzei_tt5_get_setting' ) || ! is_array( $block ) ) {
		return $block;
	}

	if ( 'core/query' === ( isset( $block['blockName'] ) ? $block['blockName'] : '' ) && is_front_page() && techzei_tt5_block_has_class( $block, 'tz-feed' ) ) {
		if ( ! isset( $block['attrs']['query'] ) || ! is_array( $block['attrs']['query'] ) ) {
			$block['attrs']['query'] = array();
		}
		$block['attrs']['query']['perPage'] = min( 20, max( 4, absint( techzei_tt5_get_setting( 'homepage', 'feed_count', 8 ) ) ) );
		$block['attrs']['query']['offset']  = techzei_tt5_get_setting( 'homepage', 'show_featured_grid', true ) ? 5 : 0;
	}

	if ( 'core/latest-posts' === ( isset( $block['blockName'] ) ? $block['blockName'] : '' ) && techzei_tt5_block_has_class( $block, 'tz-ticker-list' ) ) {
		$block['attrs']['postsToShow'] = min( 8, max( 3, absint( techzei_tt5_get_setting( 'homepage', 'headline_count', 4 ) ) ) );
		$source = techzei_tt5_get_setting( 'homepage', 'headline_source', 'latest' );
		if ( 'category' === $source ) {
			$category = absint( techzei_tt5_get_setting( 'homepage', 'headline_category', 0 ) );
			if ( $category ) {
				$block['attrs']['categories'] = array( $category );
			}
		}
	}

	if ( 'core/post-date' === ( isset( $block['blockName'] ) ? $block['blockName'] : '' ) && 'modified' === ( isset( $block['attrs']['displayType'] ) ? $block['attrs']['displayType'] : '' ) ) {
		$updated_mode = techzei_tt5_get_setting( 'articles', 'updated_date', 'later' );
		if ( 'hide' === $updated_mode || get_the_modified_time( 'U' ) <= get_the_time( 'U' ) ) {
			$block['attrs']['className'] = trim( ( isset( $block['attrs']['className'] ) ? $block['attrs']['className'] : '' ) . ' tz-hide-updated-date' );
		}
	}

	return $block;
}
add_filter( 'render_block_data', 'techzei_tt5_apply_block_settings', 10, 3 );

/**
 * Hide or annotate recognised Techzei components from central settings.
 *
 * @param string $block_content Rendered block.
 * @param array  $block         Parsed block.
 * @return string
 */
function techzei_tt5_render_block_settings( $block_content, $block ) {
	if ( ! function_exists( 'techzei_tt5_get_setting' ) || ! is_array( $block ) ) {
		return $block_content;
	}

	$block_name = isset( $block['blockName'] ) ? $block['blockName'] : '';
	$class_name = isset( $block['attrs']['className'] ) ? (string) $block['attrs']['className'] : '';
	$slug       = isset( $block['attrs']['slug'] ) ? sanitize_key( $block['attrs']['slug'] ) : '';

	if ( 'core/template-part' === $block_name ) {
		$part_settings = array(
			'topic-nav'       => array( 'header', 'show_topics', true ),
			'author-profile'  => array( 'articles', 'author_card', true ),
			'share'           => array( 'articles', 'share_links', true ),
			'related-stories' => array( 'articles', 'related_stories', true ),
		);
		if ( isset( $part_settings[ $slug ] ) && ! techzei_tt5_get_setting( $part_settings[ $slug ][0], $part_settings[ $slug ][1], $part_settings[ $slug ][2] ) ) {
			return '';
		}
		if ( 'share' === $slug && false === strpos( $block_content, 'tz-share-links' ) ) {
			return '';
		}
		if ( 'related-stories' === $slug && false === strpos( $block_content, 'tz-related-stories' ) ) {
			return '';
		}
	}

	if ( techzei_tt5_has_class_token( $class_name, 'tz-hero' ) && is_front_page() && ! techzei_tt5_get_setting( 'homepage', 'show_featured_grid', true ) ) {
		return '';
	}

	if ( techzei_tt5_has_class_token( $class_name, 'tz-topic-nav' ) && ! techzei_tt5_get_setting( 'header', 'show_topics', true ) ) {
		return '';
	}

	if ( techzei_tt5_has_class_token( $class_name, 'tz-sidebar-latest-heading' ) && ! techzei_tt5_get_setting( 'sidebar', 'latest_stories', true ) ) {
		return '';
	}

	if ( techzei_tt5_has_class_token( $class_name, 'tz-sidebar-latest-module' ) && ( ! techzei_tt5_get_setting( 'sidebar', 'latest_stories', true ) || false === strpos( $block_content, 'tz-latest-stories' ) ) ) {
		return '';
	}

	if ( techzei_tt5_has_class_token( $class_name, 'tz-sidebar-reviews' ) && ( ! techzei_tt5_get_setting( 'sidebar', 'reviews', true ) || false === strpos( $block_content, 'tz-latest-reviews' ) ) ) {
		return '';
	}

	if ( techzei_tt5_has_class_token( $class_name, 'tz-search-controls' ) && ! techzei_tt5_get_setting( 'header', 'show_search', true ) ) {
		return '';
	}

	if ( techzei_tt5_has_class_token( $class_name, 'tz-updated-label' ) && ( 'hide' === techzei_tt5_get_setting( 'articles', 'updated_date', 'later' ) || get_the_modified_time( 'U' ) <= get_the_time( 'U' ) ) ) {
		return '';
	}

	if ( techzei_tt5_has_class_token( $class_name, 'tz-hide-updated-date' ) ) {
		return '';
	}

	if ( techzei_tt5_has_class_token( $class_name, 'tz-trending' ) ) {
		$mode   = techzei_tt5_get_setting( 'homepage', 'headline_mode', 'marquee' );
		$source = techzei_tt5_get_setting( 'homepage', 'headline_source', 'latest' );
		if ( 'hidden' === $mode ) {
			return '';
		}
		if ( 'category' === $source ) {
			$category = absint( techzei_tt5_get_setting( 'homepage', 'headline_category', 0 ) );
			$term = $category ? get_term( $category, 'category' ) : false;
			if ( ! $term || is_wp_error( $term ) ) {
				return '';
			}
		}

		if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$tags = new WP_HTML_Tag_Processor( $block_content );
			if ( $tags->next_tag( array( 'tag_name' => 'DIV', 'class_name' => 'tz-trending' ) ) ) {
				$tags->set_attribute( 'data-tz-ticker', 'true' );
				$tags->set_attribute( 'data-tz-ticker-mode', $mode );
				$tags->set_attribute( 'data-tz-ticker-speed', techzei_tt5_get_setting( 'homepage', 'marquee_speed', 'slow' ) );
			}
			$block_content = $tags->get_updated_html();
		}
	}

	return $block_content;
}
add_filter( 'render_block', 'techzei_tt5_render_block_settings', 10, 2 );

/**
 * Test whether a block class list contains one exact class token.
 *
 * @param string $class_name Class list.
 * @param string $needle     Class to find.
 * @return bool
 */
function techzei_tt5_has_class_token( $class_name, $needle ) {
	$tokens = preg_split( '/\s+/', trim( (string) $class_name ) );
	return in_array( $needle, $tokens, true );
}

/**
 * Classify a featured-image block by its rendered component.
 *
 * A crop name alone is not enough to infer priority: editors can select the
 * same crop in more than one component. The templates' role classes and the
 * current request determine the intended layout instead.
 *
 * @param array $block Parsed block data.
 * @return string
 */
function techzei_tt5_featured_image_role( $block ) {
	$attrs      = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
	$class_name = isset( $attrs['className'] ) ? (string) $attrs['className'] : '';
	$size_slug  = isset( $attrs['sizeSlug'] ) ? sanitize_key( $attrs['sizeSlug'] ) : '';

	if ( is_singular( 'post' ) && techzei_tt5_has_class_token( $class_name, 'tz-feature-image' ) ) {
		return 'article-hero';
	}

	if ( is_front_page() && 'techzei-hero' === $size_slug ) {
		return 'homepage-lead';
	}

	if ( 'techzei-tile' === $size_slug || techzei_tt5_has_class_token( $class_name, 'tz-feature-card-small' ) ) {
		return 'homepage-tile';
	}

	if ( 'techzei-feed' === $size_slug || techzei_tt5_has_class_token( $class_name, 'tz-feed-card' ) ) {
		return 'list-card';
	}

	if ( 'techzei-hero' === $size_slug || techzei_tt5_has_class_token( $class_name, 'tz-feature-image' ) ) {
		return 'hero';
	}

	return 'unknown';
}

/**
 * Return the responsive width hint for a known image component.
 *
 * @param string $role Image role.
 * @return string
 */
function techzei_tt5_image_sizes_for_role( $role ) {
	$sizes = array(
		'article-hero'   => '(max-width: 900px) 100vw, 1220px',
		'homepage-lead'  => '(max-width: 900px) 100vw, 708px',
		'homepage-tile'  => '(max-width: 900px) 50vw, 255px',
		'list-card'      => '(max-width: 600px) 108px, 240px',
		'hero'           => '(max-width: 900px) 100vw, 1220px',
	);

	return isset( $sizes[ $role ] ) ? $sizes[ $role ] : '';
}

/**
 * Update the first image in rendered markup without regex-rewriting HTML.
 *
 * The theme requires WordPress 6.7+, where WP_HTML_Tag_Processor is present.
 * Keeping the no-processor branch unchanged is safer than trying to parse
 * arbitrary HTML with a replacement expression on unsupported installations.
 *
 * @param string $html       Rendered image markup.
 * @param string $role       Image role.
 * @param string $sizes      Responsive sizes value.
 * @param string $fallback_alt Optional fallback alt text.
 * @return string
 */
function techzei_tt5_process_image_html( $html, $role = 'unknown', $sizes = '', $fallback_alt = '' ) {
	if ( false === stripos( $html, '<img' ) || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return $html;
	}

	$tags = new WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag( 'IMG' ) ) {
		return $html;
	}

	$promotion_roles = array( 'article-hero', 'homepage-lead' );
	$is_promotion   = in_array( $role, $promotion_roles, true );
	$promoted       = false;

	if ( $is_promotion ) {
		global $techzei_tt5_promoted_image;
		if ( empty( $techzei_tt5_promoted_image ) ) {
			$techzei_tt5_promoted_image = true;
			$promoted                    = true;
		}
	}

	if ( $sizes ) {
		$tags->set_attribute( 'sizes', $sizes );
	}

	if ( $promoted ) {
		$tags->set_attribute( 'loading', 'eager' );
		$tags->set_attribute( 'fetchpriority', 'high' );
	} elseif ( in_array( $role, array( 'homepage-tile', 'list-card' ), true ) ) {
		$tags->set_attribute( 'loading', 'lazy' );
		$tags->remove_attribute( 'fetchpriority' );
	}

	if ( 'lazy' === strtolower( (string) $tags->get_attribute( 'loading' ) ) ) {
		// Core or a plugin may have supplied both attributes. Never retain the
		// invalid lazy/high combination, regardless of who added it.
		$tags->remove_attribute( 'fetchpriority' );
	}

	$current_alt = $tags->get_attribute( 'alt' );
	if ( ( null === $current_alt || '' === trim( (string) $current_alt ) ) && $fallback_alt ) {
		$tags->set_attribute(
			'alt',
			wp_specialchars_decode( wp_strip_all_tags( $fallback_alt ), ENT_QUOTES )
		);
	}

	return $tags->get_updated_html();
}

/**
 * Resolve the featured-image fallback alt text for the current post.
 *
 * @return string
 */
function techzei_tt5_featured_image_fallback_alt() {
	$post_id        = get_the_ID();
	$thumbnail_id   = $post_id ? get_post_thumbnail_id( $post_id ) : 0;
	$attachment_alt = $thumbnail_id ? get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) : '';
	$fallback       = $attachment_alt ? $attachment_alt : ( $post_id ? get_the_title( $post_id ) : '' );

	return wp_specialchars_decode( wp_strip_all_tags( (string) $fallback ), ENT_QUOTES );
}

/**
 * Consolidated featured-image renderer.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Parsed block data.
 * @return string
 */
function techzei_tt5_process_featured_image( $block_content, $block ) {
	$role = techzei_tt5_featured_image_role( $block );
	return techzei_tt5_process_image_html(
		$block_content,
		$role,
		techzei_tt5_image_sizes_for_role( $role ),
		techzei_tt5_featured_image_fallback_alt()
	);
}
add_filter( 'render_block_core/post-featured-image', 'techzei_tt5_process_featured_image', 10, 2 );

/**
 * Backwards-compatible entry points retained for integrations that called the
 * former split handlers directly. Rendering uses the consolidated filter.
 */
function techzei_tt5_prioritize_article_hero( $block_content, $block ) {
	return techzei_tt5_process_featured_image( $block_content, $block );
}

function techzei_tt5_ensure_featured_image_alt( $block_content, $block ) {
	return techzei_tt5_process_featured_image( $block_content, $block );
}

function techzei_tt5_optimize_card_images( $block_content, $block ) {
	return techzei_tt5_process_featured_image( $block_content, $block );
}

/* Preserve the three historical callback registrations for integrations that
 * remove or replace them by name. The consolidated handler remains the first
 * pass, and each compatibility pass is idempotent. */
add_filter( 'render_block_core/post-featured-image', 'techzei_tt5_prioritize_article_hero', 11, 2 );
add_filter( 'render_block_core/post-featured-image', 'techzei_tt5_ensure_featured_image_alt', 12, 2 );
add_filter( 'render_block_core/post-featured-image', 'techzei_tt5_optimize_card_images', 13, 2 );

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
	if ( function_exists( 'techzei_tt5_get_setting' ) ) {
		if ( ! techzei_tt5_get_setting( 'header', 'sticky_desktop', true ) ) {
			$classes[] = 'tz-sticky-desktop-off';
		}
		if ( ! techzei_tt5_get_setting( 'header', 'sticky_mobile', true ) ) {
			$classes[] = 'tz-sticky-mobile-off';
		}
	}

	if ( ! is_singular( 'post' ) ) {
		return $classes;
	}

	$legacy_enabled = ! function_exists( 'techzei_tt5_get_setting' ) || techzei_tt5_get_setting( 'articles', 'automatic_legacy', true );
	$classes[]       = $legacy_enabled && techzei_tt5_is_legacy_post( get_post() ) ? 'tz-legacy-post' : 'tz-current-post';

	$selected_template = get_page_template_slug( get_queried_object_id() );
	if ( ! $selected_template || 'default' === $selected_template ) {
		$default_layout = function_exists( 'techzei_tt5_get_setting' ) ? techzei_tt5_get_setting( 'articles', 'default_layout', 'sidebar' ) : 'sidebar';
		if ( 'no-sidebar' === $default_layout ) {
			$classes[] = 'tz-default-no-sidebar';
		}
	}

	return $classes;
}
add_filter( 'body_class', 'techzei_tt5_post_layout_class' );
