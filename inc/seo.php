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
		$post = get_post();
		if ( $post && '' !== $post->post_excerpt ) {
			// A manual excerpt is cheap: get_the_excerpt() returns it directly.
			$description = get_the_excerpt( $post );
		} elseif ( $post ) {
			// Without one, get_the_excerpt() falls back to wp_trim_excerpt(),
			// which runs the full 'the_content' filter chain (shortcodes,
			// embeds, blocks) just to build a short meta description here —
			// needless work on every request, and a real risk of an extra
			// oEmbed HTTP request in wp_head for a post with an un-cached
			// embed. Everything below already strips tags and truncates, so
			// build this straight from the raw content instead.
			$description = strip_shortcodes( $post->post_content );
		}
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
		|| defined( 'SEOPRESS_VERSION' )
		|| defined( 'THE_SEO_FRAMEWORK_VERSION' );
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
	// The request URI, not just its path, matters here: a query-string-driven
	// view (search, or an archive on the default, non-pretty permalink
	// structure) has no distinct path of its own, so reporting the path alone
	// collapses every such page to og:url = the homepage.
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
	$url         = is_singular() ? get_permalink() : ( is_front_page() ? home_url( '/' ) : home_url( $request_uri ) );
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

/**
 * Give the front page a useful title tagline when none is configured.
 *
 * wp_get_document_title() always builds the front-page title from a
 * 'tagline' part sourced from the site's configured tagline (Settings >
 * General), then drops empty parts before assembling the final string — so
 * hooking the earlier pre_get_document_title filter, which only fires with
 * an empty starting string, can never reach a site-name-only front page:
 * that comparison never has anything to match against. document_title_parts
 * fires after core has already assembled its parts, matching how the
 * tagline is meant to be supplied.
 *
 * @param array $parts Document title parts.
 * @return array
 */
function techzei_tt5_front_page_title( $parts ) {
	if ( ! is_front_page() || ! empty( $parts['tagline'] ) || techzei_tt5_has_seo_provider() ) {
		return $parts;
	}

	$parts['tagline'] = __( 'Independent technology journalism', 'techzei-magazine-theme' );
	return $parts;
}
add_filter( 'document_title_parts', 'techzei_tt5_front_page_title' );
