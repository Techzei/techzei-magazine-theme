<?php
/**
 * Small server-rendered SEO and social metadata fallback.
 *
 * @package Techzei_TT5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return a compact description suitable for search and social cards.
 *
 * @return string
 */
function techzei_tt5_meta_description() {
	$description = '';

	if ( is_singular() ) {
		$description = get_the_excerpt();
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$description = term_description();
	} elseif ( is_search() ) {
		$description = sprintf(
			/* translators: %s: search query. */
			__( 'Search results for %s on Techzei.', 'techzei-magazine-theme' ),
			get_search_query()
		);
	}

	if ( empty( $description ) ) {
		$description = get_bloginfo( 'description' );
	}

	if ( empty( $description ) ) {
		$description = __( 'Independent technology journalism, practical guides, and thoughtful reviews from Techzei.', 'techzei-magazine-theme' );
	}

	$description = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $description ) ) );

	return wp_html_excerpt( $description, 155, '…' );
}

/**
 * Detect SEO plugins that already own document and social metadata.
 *
 * @return bool
 */
function techzei_tt5_has_seo_provider() {
	return defined( 'WPSEO_VERSION' )
		|| class_exists( 'WPSEO_Options' )
		|| defined( 'RANK_MATH_VERSION' )
		|| class_exists( 'AIOSEO\\Plugin\\Common\\Main' )
		|| defined( 'SEOPRESS_VERSION' );
}

/**
 * Output fallback description, Open Graph, and Twitter Card metadata.
 *
 * @return void
 */
function techzei_tt5_output_meta_tags() {
	if ( is_admin() || is_feed() || is_trackback() || techzei_tt5_has_seo_provider() ) {
		return;
	}

	$title       = wp_get_document_title();
	$description = techzei_tt5_meta_description();
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
	$request_path = wp_parse_url( $request_uri, PHP_URL_PATH );
	$url         = is_singular() ? get_permalink() : ( is_front_page() ? home_url( '/' ) : home_url( $request_path ? $request_path : '/' ) );
	$url         = remove_query_arg( array( 'replytocom' ), $url );
	$site_name   = get_bloginfo( 'name' );
	$type        = is_singular( 'post' ) ? 'article' : 'website';
	$image       = is_singular() ? get_the_post_thumbnail_url( get_the_ID(), 'large' ) : '';

	echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
	echo '<meta property="og:type" content="' . esc_attr( $type ) . '" />' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '" />' . "\n";

	if ( $image ) {
		echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
		echo '<meta property="og:image:alt" content="' . esc_attr( get_the_title() ) . '" />' . "\n";
	}

	echo '<meta name="twitter:card" content="' . esc_attr( $image ? 'summary_large_image' : 'summary' ) . '" />' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
	echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '" />' . "\n";

	if ( $image ) {
		echo '<meta name="twitter:image" content="' . esc_url( $image ) . '" />' . "\n";
	}

	if ( is_singular( 'post' ) ) {
		echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( DATE_W3C ) ) . '" />' . "\n";
		echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( DATE_W3C ) ) . '" />' . "\n";
	}
}
add_action( 'wp_head', 'techzei_tt5_output_meta_tags', 1 );

/** Give the front page a useful title when the SEO provider has no title. */
function techzei_tt5_front_page_title( $title ) {
	if ( ! is_front_page() || ! $title || techzei_tt5_has_seo_provider() ) {
		return $title;
	}

	$site_name = get_bloginfo( 'name' );
	return $site_name === wp_strip_all_tags( $title )
		? sprintf( '%s — Independent technology journalism', $site_name )
		: $title;
}
add_filter( 'pre_get_document_title', 'techzei_tt5_front_page_title', 20 );
