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
	$GLOBALS['techzei_tt5_editorial_query_count'] = isset( $GLOBALS['techzei_tt5_editorial_query_count'] ) ? absint( $GLOBALS['techzei_tt5_editorial_query_count'] ) + 1 : 1;
	$defaults = array(
		'post_type'           => 'post',
		'posts_per_page'      => 3,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);

	return new WP_Query( wp_parse_args( $args, $defaults ) );
}

/**
 * Return the number of bounded editorial WP_Query calls made in this request.
 *
 * The counter is intentionally lightweight and exists to make optional
 * WordPress integration tests and Query Monitor comparisons reproducible. It
 * is not used to change frontend behaviour.
 *
 * @return int
 */
function techzei_tt5_editorial_query_count() {
	return isset( $GLOBALS['techzei_tt5_editorial_query_count'] ) ? absint( $GLOBALS['techzei_tt5_editorial_query_count'] ) : 0;
}

/** Return the persistent namespace version for editorial ID-list caches. */
function techzei_tt5_editorial_cache_version() {
	$version = absint( get_option( 'techzei_tt5_editorial_cache_version', 1 ) );
	return max( 1, $version );
}

/** Bump the editorial cache namespace after relevant content changes. */
function techzei_tt5_invalidate_editorial_cache() {
	$version = techzei_tt5_editorial_cache_version();
	// Autoloaded: this is read on every editorial cache-key computation (up to
	// several times per single-post request), so it belongs in the one bulk
	// options read WordPress already does on every request rather than
	// costing its own dedicated query on a site with no persistent object
	// cache.
	update_option( 'techzei_tt5_editorial_cache_version', $version + 1, true );
	unset( $GLOBALS['techzei_tt5_editorial_id_cache'], $GLOBALS['techzei_tt5_editorial_post_cache'], $GLOBALS['techzei_tt5_toc_items_cache'] );
}

/** Invalidate discovery IDs for a post mutation. */
function techzei_tt5_invalidate_editorial_post( $post_id ) {
	$post = get_post( absint( $post_id ) );
	if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
		return;
	}
	techzei_tt5_invalidate_editorial_cache();
}

/** Invalidate discovery IDs when a relevant taxonomy relationship changes. */
function techzei_tt5_invalidate_editorial_terms( $object_id, $terms = array(), $tt_ids = array(), $taxonomy = '' ) {
	if ( in_array( $taxonomy, array( 'category', 'post_tag' ), true ) ) {
		techzei_tt5_invalidate_editorial_post( $object_id );
	}
}

/** Invalidate discovery IDs when a featured image or primary category changes. */
function techzei_tt5_invalidate_editorial_postmeta( $meta_id, $object_id, $meta_key, $meta_value ) {
	if ( in_array( $meta_key, array( '_thumbnail_id', '_yoast_wpseo_primary_category' ), true ) ) {
		techzei_tt5_invalidate_editorial_post( $object_id );
	}
}

add_action( 'save_post_post', 'techzei_tt5_invalidate_editorial_post', 20 );
add_action( 'deleted_post', 'techzei_tt5_invalidate_editorial_post', 20 );
add_action( 'set_object_terms', 'techzei_tt5_invalidate_editorial_terms', 20, 4 );
add_action( 'added_post_meta', 'techzei_tt5_invalidate_editorial_postmeta', 20, 4 );
add_action( 'updated_postmeta', 'techzei_tt5_invalidate_editorial_postmeta', 20, 4 );
add_action( 'deleted_post_meta', 'techzei_tt5_invalidate_editorial_postmeta', 20, 4 );
add_action( 'created_term', 'techzei_tt5_invalidate_editorial_cache', 20, 3 );
add_action( 'edited_term', 'techzei_tt5_invalidate_editorial_cache', 20, 3 );
add_action( 'delete_term', 'techzei_tt5_invalidate_editorial_cache', 20, 3 );
add_action( 'added_option_' . ( defined( 'TECHZEI_TT5_SETTINGS_OPTION' ) ? TECHZEI_TT5_SETTINGS_OPTION : 'techzei_tt5_settings' ), 'techzei_tt5_invalidate_editorial_cache' );
add_action( 'updated_option_' . ( defined( 'TECHZEI_TT5_SETTINGS_OPTION' ) ? TECHZEI_TT5_SETTINGS_OPTION : 'techzei_tt5_settings' ), 'techzei_tt5_invalidate_editorial_cache' );
add_action( 'deleted_option_' . ( defined( 'TECHZEI_TT5_SETTINGS_OPTION' ) ? TECHZEI_TT5_SETTINGS_OPTION : 'techzei_tt5_settings' ), 'techzei_tt5_invalidate_editorial_cache' );

/** Build a cache key from post, settings, taxonomy, and component state. */
function techzei_tt5_editorial_cache_key( $component, $post_id = 0, $context = array() ) {
	$post       = $post_id ? get_post( absint( $post_id ) ) : null;
	$categories = $post_id ? wp_get_post_terms( $post_id, 'category', array( 'fields' => 'ids' ) ) : array();
	$tags       = $post_id ? wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'ids' ) ) : array();
	$categories = is_wp_error( $categories ) ? array() : array_map( 'absint', $categories );
	$tags       = is_wp_error( $tags ) ? array() : array_map( 'absint', $tags );
	$state = array(
		'component'         => sanitize_key( $component ),
		'version'           => techzei_tt5_editorial_cache_version(),
		'post_id'           => absint( $post_id ),
		'post_modified_gmt' => $post instanceof WP_Post ? $post->post_modified_gmt : '',
		'thumbnail_id'      => $post_id ? get_post_thumbnail_id( $post_id ) : 0,
		'categories'        => $categories,
		'tags'              => $tags,
		'settings'          => function_exists( 'techzei_tt5_get_settings' ) ? techzei_tt5_get_settings() : array(),
		'context'           => $context,
	);
	if ( function_exists( 'wp_cache_get_last_changed' ) ) {
		$state['terms_last_changed'] = wp_cache_get_last_changed( 'terms' );
	}
	return 'techzei_tt5_' . sanitize_key( $component ) . '_' . md5( wp_json_encode( $state ) );
}

/** Read an ID-list cache using a request-local layer before the transient. */
function techzei_tt5_editorial_ids_cache_get( $key ) {
	if ( isset( $GLOBALS['techzei_tt5_editorial_id_cache'][ $key ] ) && is_array( $GLOBALS['techzei_tt5_editorial_id_cache'][ $key ] ) ) {
		return $GLOBALS['techzei_tt5_editorial_id_cache'][ $key ];
	}
	$cached = get_transient( $key );
	if ( false === $cached || ! is_array( $cached ) ) {
		return null;
	}
	$GLOBALS['techzei_tt5_editorial_id_cache'][ $key ] = array_map( 'absint', $cached );
	return $GLOBALS['techzei_tt5_editorial_id_cache'][ $key ];
}

/** Store a bounded ID list. Cache values are never rendered HTML. */
function techzei_tt5_editorial_ids_cache_set( $key, $ids ) {
	$ids = array_values( array_map( 'absint', is_array( $ids ) ? $ids : array() ) );
	$GLOBALS['techzei_tt5_editorial_id_cache'][ $key ] = $ids;
	set_transient( $key, $ids, 12 * HOUR_IN_SECONDS );
}

/** Prime request-local post objects so a resolver does not need a second query. */
function techzei_tt5_prime_editorial_posts( $posts ) {
	if ( $posts instanceof WP_Query ) {
		$posts = $posts->posts;
	}
	if ( ! is_array( $posts ) ) {
		return;
	}
	if ( ! isset( $GLOBALS['techzei_tt5_editorial_post_cache'] ) || ! is_array( $GLOBALS['techzei_tt5_editorial_post_cache'] ) ) {
		$GLOBALS['techzei_tt5_editorial_post_cache'] = array();
	}
	foreach ( $posts as $post ) {
		if ( $post instanceof WP_Post ) {
			$GLOBALS['techzei_tt5_editorial_post_cache'][ $post->ID ] = $post;
		}
	}
}

/** Return post objects in a requested order, querying only uncached IDs. */
function techzei_tt5_editorial_posts_by_ids( $ids ) {
	$ids = techzei_tt5_normalize_editorial_ids( $ids, 18 );
	if ( empty( $ids ) ) {
		return array();
	}
	$missing = array();
	foreach ( $ids as $id ) {
		if ( empty( $GLOBALS['techzei_tt5_editorial_post_cache'][ $id ] ) ) {
			$missing[] = $id;
		}
	}
	if ( ! empty( $missing ) ) {
		$query = techzei_tt5_editorial_query( array( 'posts_per_page' => count( $missing ), 'post__in' => $missing, 'orderby' => 'post__in' ) );
		techzei_tt5_prime_editorial_posts( $query );
	}
	$posts = array();
	foreach ( $ids as $id ) {
		if ( ! empty( $GLOBALS['techzei_tt5_editorial_post_cache'][ $id ] ) ) {
			$posts[] = $GLOBALS['techzei_tt5_editorial_post_cache'][ $id ];
		}
	}
	return $posts;
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
 * Return the share destinations and URLs supported by the lightweight theme
 * share components. No social SDK is loaded or needed.
 *
 * @return array
 */
function techzei_tt5_share_destination_data() {
	$url   = rawurlencode( get_permalink() );
	$title = rawurlencode( get_the_title() );

	return array(
		'x'        => array( 'label' => 'X', 'url' => 'https://twitter.com/intent/tweet?url=' . $url . '&text=' . $title ),
		'facebook' => array( 'label' => 'Facebook', 'url' => 'https://www.facebook.com/sharer/sharer.php?u=' . $url ),
		'linkedin' => array( 'label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $url ),
		'whatsapp' => array( 'label' => 'WhatsApp', 'url' => 'https://api.whatsapp.com/send?text=' . $title . '%20' . $url ),
	);
}

/**
 * Whether at least one configured, supported sharing destination is available.
 *
 * @return bool
 */
function techzei_tt5_has_enabled_share_destinations() {
	$available    = techzei_tt5_share_destination_data();
	$destinations = techzei_tt5_get_editorial_setting( 'articles.share_destinations', array_keys( $available ) );
	$destinations = is_array( $destinations ) ? array_map( 'sanitize_key', $destinations ) : array();

	return ! empty( array_intersect( array_keys( $available ), $destinations ) );
}

/** Return the small inline brand mark used by a share destination. */
function techzei_tt5_share_icon( $destination ) {
	$icons = array(
		'x'        => '<svg viewBox="0 0 24 24" focusable="false"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817-5.964 6.817H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231 5.45-6.231Zm-1.161 17.52h1.833L7.084 4.126H5.117L17.083 19.77Z" /></svg>',
		'facebook' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M14.45 8.2h2.55V4.35c-.44-.06-1.95-.2-3.71-.2-3.67 0-6.18 2.24-6.18 6.35v3.55H3v4.78h4.11v5.17h5.05v-5.17h4.28l.68-4.78h-4.96v-3.06c0-1.39.38-2.34 2.29-2.34Z" /></svg>',
		'linkedin' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M5.16 7.04a2.54 2.54 0 1 0 0-5.08 2.54 2.54 0 0 0 0 5.08ZM2.87 21.99h4.58V8.24H2.87v13.75ZM10.31 8.24h4.39v1.88h.06c.61-1.15 2.1-2.36 4.33-2.36 4.63 0 5.49 3.05 5.49 7.01v7.22H20v-6.4c0-1.53-.03-3.49-2.13-3.49-2.13 0-2.46 1.67-2.46 3.38v6.51h-4.57V8.24h-.53Z" /></svg>',
		'whatsapp' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M20.52 3.48A11.87 11.87 0 0 0 12.05 0C5.49 0 .15 5.34.15 11.9c0 2.1.55 4.15 1.6 5.95L.05 24l6.3-1.65a11.9 11.9 0 0 0 5.7 1.45h.01c6.55 0 11.89-5.34 11.89-11.9 0-3.18-1.24-6.16-3.43-8.42ZM12.06 21.8h-.01a9.9 9.9 0 0 1-5.04-1.38l-.36-.22-3.74.98 1-3.65-.24-.37a9.88 9.88 0 0 1-1.52-5.27C3.15 6.45 7.15 2.45 12.06 2.45c2.38 0 4.61.93 6.29 2.62a8.84 8.84 0 0 1 2.6 6.3c0 4.91-4 8.91-8.89 8.91Zm4.88-6.68c-.27-.14-1.59-.78-1.84-.87-.25-.09-.43-.14-.61.14-.18.27-.7.87-.86 1.05-.16.18-.32.2-.59.07-.27-.14-1.12-.41-2.13-1.31-.79-.7-1.32-1.57-1.48-1.84-.16-.27-.02-.42.12-.56.12-.12.27-.32.41-.48.14-.16.18-.27.27-.45.09-.18.05-.34-.02-.48-.07-.14-.61-1.47-.84-2.01-.22-.53-.45-.46-.61-.47h-.52c-.18 0-.48.07-.73.34-.25.27-.95.93-.95 2.27s.98 2.63 1.11 2.82c.14.18 1.92 2.93 4.65 4.11.65.28 1.16.45 1.55.58.65.2 1.24.17 1.71.1.52-.08 1.59-.65 1.81-1.28.22-.63.22-1.17.16-1.28-.07-.11-.25-.18-.52-.32Z" /></svg>',
	);

	return isset( $icons[ $destination ] ) ? $icons[ $destination ] : '';
}

/**
 * Render share links for either the normal article row or compact mobile dock.
 *
 * @param bool $mobile_dock Whether WhatsApp-first compact mobile output is needed.
 * @return string
 */
function techzei_tt5_render_share_links( $mobile_dock = false ) {
	if ( ! is_singular( 'post' ) || ! techzei_tt5_get_editorial_setting( 'articles.share_links', true ) ) {
		return '';
	}
	if ( $mobile_dock && ! techzei_tt5_get_editorial_setting( 'articles.mobile_share_dock', true ) ) {
		return '';
	}

	$available    = techzei_tt5_share_destination_data();
	$destinations = techzei_tt5_get_editorial_setting( 'articles.share_destinations', array_keys( $available ) );
	$destinations = is_array( $destinations ) ? array_map( 'sanitize_key', $destinations ) : array();
	$destinations = array_values( array_intersect( array_keys( $available ), $destinations ) );

	if ( $mobile_dock ) {
		$preferred     = array( 'whatsapp', 'x', 'facebook', 'linkedin' );
		$destinations  = array_values( array_intersect( $preferred, $destinations ) );
	}
	if ( empty( $destinations ) ) {
		return '';
	}

	$class = $mobile_dock ? 'tz-mobile-share-dock' : 'tz-share-links';
	// The in-flow row and the mobile dock can both be present on the same
	// article at once (their settings are independent), so they need distinct
	// accessible names — otherwise assistive tech reports two identically
	// named "Share this story" landmarks with no way to tell them apart.
	$label  = $mobile_dock
		? __( 'Share this story (mobile)', 'techzei-magazine-theme' )
		: __( 'Share this story', 'techzei-magazine-theme' );
	$output = '<nav class="' . esc_attr( $class ) . '" aria-label="' . esc_attr( $label ) . '">';

	foreach ( $destinations as $destination ) {
		$output .= sprintf(
			'<a class="tz-share-%1$s" href="%2$s" target="_blank" rel="noopener noreferrer" aria-label="%3$s"><span class="tz-share-icon" aria-hidden="true">%4$s</span><span class="tz-share-label">%5$s</span></a>',
			esc_attr( $destination ),
			esc_url( $available[ $destination ]['url'] ),
			esc_attr(
				sprintf(
					/* translators: %s: share destination name, e.g. "X" or "WhatsApp". */
					__( 'Share on %s', 'techzei-magazine-theme' ),
					$available[ $destination ]['label']
				)
			),
			techzei_tt5_share_icon( $destination ),
			esc_html( $available[ $destination ]['label'] )
		);
	}

	return $output . '</nav>';
}

/** Render the normal in-flow share row. */
function techzei_tt5_share_links() {
	return techzei_tt5_render_share_links();
}
add_shortcode( 'techzei_share', 'techzei_tt5_share_links' );

/** Render a WhatsApp-first mobile sticky share dock. */
function techzei_tt5_mobile_share_dock() {
	return techzei_tt5_render_share_links( true );
}
add_shortcode( 'techzei_mobile_share_dock', 'techzei_tt5_mobile_share_dock' );

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
 * Return the current post's primary WordPress category.
 *
 * Yoast's primary category value is respected when it points to an assigned
 * category. Otherwise the deepest assigned category gives topic modules a
 * useful default without adding content metadata of our own.
 *
 * @param int $post_id Post ID.
 * @return WP_Term|false
 */
function techzei_tt5_primary_category( $post_id ) {
	$post_id   = absint( $post_id );
	$categories = get_the_category( $post_id );
	if ( empty( $categories ) || is_wp_error( $categories ) ) {
		return false;
	}

	$assigned_ids = wp_list_pluck( $categories, 'term_id' );
	$yoast_id     = absint( get_post_meta( $post_id, '_yoast_wpseo_primary_category', true ) );
	if ( $yoast_id && in_array( $yoast_id, $assigned_ids, true ) ) {
		$term = get_term( $yoast_id, 'category' );
		if ( $term && ! is_wp_error( $term ) ) {
			return $term;
		}
	}

	usort(
		$categories,
		function ( $left, $right ) {
			$left_depth  = count( get_ancestors( $left->term_id, 'category', 'taxonomy' ) );
			$right_depth = count( get_ancestors( $right->term_id, 'category', 'taxonomy' ) );
			if ( $left_depth === $right_depth ) {
				return $left->term_id <=> $right->term_id;
			}
			return $left_depth > $right_depth ? -1 : 1;
		}
	);

	return $categories[0];
}

/**
 * Render visible breadcrumbs while leaving schema ownership with Yoast.
 *
 * Yoast remains the owner of breadcrumb structured data. The theme renders a
 * concise visible trail so a single post does not repeat its full headline
 * immediately before the actual post title.
 *
 * @return string
 */
function techzei_tt5_visible_breadcrumbs() {
	if ( ! techzei_tt5_get_editorial_setting( 'articles.breadcrumbs', true ) || ( ! is_singular( 'post' ) && ! is_archive() ) ) {
		return '';
	}

	$items   = array();
	$items[] = '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'techzei-magazine-theme' ) . '</a>';

	if ( is_singular( 'post' ) ) {
		$category = techzei_tt5_primary_category( get_the_ID() );
		if ( $category ) {
			$items[] = '<a href="' . esc_url( get_category_link( $category ) ) . '">' . esc_html( $category->name ) . '</a>';
		}
	} else {
		$items[] = '<span aria-current="page">' . esc_html( wp_strip_all_tags( get_the_archive_title() ) ) . '</span>';
	}

	// The separator is a CSS ::after on each non-last <li> (see style.css)
	// rather than its own <li aria-hidden> node, so a screen reader announces
	// this as a list of N real breadcrumb items instead of N plus N-1 hidden
	// decorative ones inflating the reported list length.
	return '<nav class="tz-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'techzei-magazine-theme' ) . '"><ol><li>' . implode( '</li><li>', $items ) . '</li></ol></nav>';
}
add_shortcode( 'techzei_breadcrumbs', 'techzei_tt5_visible_breadcrumbs' );

/**
 * Return the instructional type represented by a category or ancestor.
 *
 * @param WP_Term $term Category term.
 * @return string Empty, how-to, or explainer.
 */
function techzei_tt5_instructional_category_type( $term ) {
	if ( ! $term || is_wp_error( $term ) ) {
		return '';
	}

	$terms = array( $term );
	foreach ( get_ancestors( $term->term_id, 'category', 'taxonomy' ) as $ancestor_id ) {
		$ancestor = get_term( $ancestor_id, 'category' );
		if ( $ancestor && ! is_wp_error( $ancestor ) ) {
			$terms[] = $ancestor;
		}
	}

	foreach ( $terms as $candidate ) {
		$slug = sanitize_title( $candidate->slug . ' ' . $candidate->name );
		if ( false !== strpos( $slug, 'how-to' ) || false !== strpos( $slug, 'howto' ) ) {
			return 'how-to';
		}
		if ( false !== strpos( $slug, 'explainer' ) ) {
			return 'explainer';
		}
	}

	return '';
}

/**
 * Whether a category describes a How To or Explainer article.
 *
 * @param WP_Term $term Category term.
 * @return bool
 */
function techzei_tt5_is_instructional_category( $term ) {
	return '' !== techzei_tt5_instructional_category_type( $term );
}

/** Return a stable, valid, unique heading ID for one TOC item. */
function techzei_tt5_toc_heading_id( $label, $existing, &$used ) {
	$existing = trim( html_entity_decode( (string) $existing, ENT_QUOTES, get_bloginfo( 'charset' ) ) );
	$base     = preg_match( '/^[A-Za-z][A-Za-z0-9_.:-]*$/D', $existing ) ? $existing : sanitize_title( $existing );
	if ( '' === $base ) {
		$base = sanitize_title( $label );
	}
	if ( '' === $base ) {
		$base = 'section';
	}
	if ( preg_match( '/^[0-9]/', $base ) ) {
		$base = 'section-' . $base;
	}
	$base_key = strtolower( $base );
	$count    = isset( $used[ $base_key ] ) ? absint( $used[ $base_key ] ) + 1 : 1;
	$id       = 1 === $count ? $base : $base . '-' . $count;
	while ( isset( $used[ strtolower( $id ) ] ) ) {
		$count++;
		$id = $base . '-' . $count;
	}
	$used[ strtolower( $id ) ] = $count;
	return $id;
}

/**
 * Return readable H2/H3 items from post content for the article TOC.
 *
 * This scans raw post_content rather than post-render output, so the IDs
 * this returns must line up positionally with the real H2/H3 tags that
 * techzei_tt5_add_article_toc_ids() later finds in the rendered markup with
 * WP_HTML_Tag_Processor, a real HTML parser. An HTML comment left behind
 * from editing (e.g. `<!-- <h2>Draft heading</h2> -->`) is invisible to that
 * parser but would otherwise still be matched by a naive regex scan here,
 * silently shifting every anchor after it onto the wrong heading. Stripping
 * comments first keeps the two passes in agreement for that case.
 *
 * A heading sourced from a synced pattern/reusable block, which post_content
 * only references rather than containing, is a known remaining gap: it has
 * no raw markup here to find at all, so it is omitted from the TOC rather
 * than mismatched.
 *
 * Request-cached per post, since should-show, render, and ID-assignment
 * each call this for the same post within one request.
 *
 * @param int $post_id Post ID.
 * @return array
 */
function techzei_tt5_article_toc_items( $post_id ) {
	$post_id = absint( $post_id );

	if ( isset( $GLOBALS['techzei_tt5_toc_items_cache'][ $post_id ] ) ) {
		return $GLOBALS['techzei_tt5_toc_items_cache'][ $post_id ];
	}

	$content = (string) get_post_field( 'post_content', $post_id );
	$content = preg_replace( '/<!--.*?-->/s', ' ', $content );
	$matches = array();
	$items   = array();
	$used    = array();
	preg_match_all( '/<h([23])\\b([^>]*)>(.*?)<\\/h\\1\\s*>/is', $content, $matches, PREG_SET_ORDER );
	foreach ( $matches as $match ) {
		$label = trim( html_entity_decode( wp_strip_all_tags( $match[3] ), ENT_QUOTES, get_bloginfo( 'charset' ) ) );
		if ( '' === $label ) {
			continue;
		}
		$existing_id = '';
		if ( preg_match( '/\\bid\\s*=\\s*(["\\\'])(.*?)\\1/i', $match[2], $id_match ) ) {
			$existing_id = $id_match[2];
		}
		$items[] = array(
			'id'    => techzei_tt5_toc_heading_id( $label, $existing_id, $used ),
			'label' => $label,
			'level' => absint( $match[1] ),
		);
	}

	$GLOBALS['techzei_tt5_toc_items_cache'][ $post_id ] = $items;

	return $items;
}

/** Return whether the current post should offer an automatic TOC. */
function techzei_tt5_should_show_article_toc( $post_id ) {
	if ( ! is_singular( 'post' ) || ! techzei_tt5_get_editorial_setting( 'articles.toc', true ) ) {
		return false;
	}
	$categories = get_the_category( absint( $post_id ) );
	if ( empty( $categories ) || is_wp_error( $categories ) || count( techzei_tt5_article_toc_items( $post_id ) ) < 3 ) {
		return false;
	}
	foreach ( $categories as $category ) {
		if ( techzei_tt5_is_instructional_category( $category ) ) {
			return true;
		}
	}
	return false;
}

/** Render the no-JavaScript, mobile-collapsible article table of contents. */
function techzei_tt5_article_toc() {
	$post_id = get_the_ID();
	if ( ! techzei_tt5_should_show_article_toc( $post_id ) ) {
		return '';
	}
	$items  = techzei_tt5_article_toc_items( $post_id );
	$output = '<nav class="tz-article-toc" aria-label="' . esc_attr__( 'On this page', 'techzei-magazine-theme' ) . '"><details open><summary>' . esc_html__( 'On this page', 'techzei-magazine-theme' ) . '</summary><ol>';
	foreach ( $items as $item ) {
		$output .= '<li class="tz-toc-level-' . absint( $item['level'] ) . '"><a href="#' . esc_attr( $item['id'] ) . '">' . esc_html( $item['label'] ) . '</a></li>';
	}
	return $output . '</ol></details></nav>';
}
add_shortcode( 'techzei_article_toc', 'techzei_tt5_article_toc' );

/** Add stable IDs to H2/H3 elements when the automatic TOC is present. */
function techzei_tt5_add_article_toc_ids( $content, $block ) {
	$post_id = get_the_ID();
	if ( ! techzei_tt5_should_show_article_toc( $post_id ) || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return $content;
	}

	$items = techzei_tt5_article_toc_items( $post_id );
	$index = 0;
	$tags  = new WP_HTML_Tag_Processor( $content );
	while ( $tags->next_tag() ) {
		$tag = strtolower( (string) $tags->get_tag() );
		if ( ! in_array( $tag, array( 'h2', 'h3' ), true ) || ! isset( $items[ $index ] ) ) {
			continue;
		}
		$tags->set_attribute( 'id', $items[ $index ]['id'] );
		$index++;
	}

	return $tags->get_updated_html();
}
add_filter( 'render_block_core/post-content', 'techzei_tt5_add_article_toc_ids', 10, 2 );

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
			'post_status'    => 'publish',
		)
	);

	techzei_tt5_prime_editorial_posts( $query );

	return techzei_tt5_normalize_editorial_ids( wp_list_pluck( $query->posts, 'ID' ), $limit, $exclude );
}

/**
 * Resolve related-story IDs in relevance order without unbounded work.
 *
 * The resolver uses editorial selections only when explicitly enabled, then
 * a shared product-tag plus topic intersection, shared tags, and finally the
 * most-specific shared categories. A five-year freshness window prevents an
 * old, only loosely related post from filling a modern article's module; sites
 * with a different archive policy can adjust it through the documented filter.
 *
 * @param int $post_id Current post ID.
 * @param int $limit   Maximum number of related posts.
 * @return array
 */
function techzei_tt5_related_story_ids( $post_id, $limit ) {
	$post_id = absint( $post_id );
	$limit   = min( 6, max( 2, absint( $limit ) ) );
	$mode    = sanitize_key( (string) techzei_tt5_get_editorial_setting( 'articles.related_mode', 'automatic' ) );
	$editorial_first = in_array( $mode, array( 'editorial', 'editorial-first' ), true );
	$editorial_first = (bool) apply_filters( 'techzei_tt5_related_editorial_first', $editorial_first, $post_id, $limit );
	$category_ids = wp_get_post_terms( $post_id, 'category', array( 'fields' => 'ids', 'orderby' => 'term_id', 'order' => 'ASC' ) );
	$category_ids = is_wp_error( $category_ids ) ? array() : array_slice( techzei_tt5_order_related_categories( $category_ids ), 0, 3 );
	$tag_ids = wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'ids', 'orderby' => 'term_id', 'order' => 'ASC' ) );
	$tag_ids = is_wp_error( $tag_ids ) ? array() : techzei_tt5_normalize_editorial_ids( $tag_ids, 8 );
	$freshness_date_query = apply_filters( 'techzei_tt5_related_freshness_date_query', array( array( 'after' => gmdate( 'Y-m-d', strtotime( '-5 years' ) ), 'inclusive' => true ) ), $post_id );
	$freshness_date_query = is_array( $freshness_date_query ) ? $freshness_date_query : array();
	$cache_key = techzei_tt5_editorial_cache_key( 'related', $post_id, array( 'limit' => $limit, 'mode' => $mode, 'editorial_first' => $editorial_first, 'categories' => $category_ids, 'tags' => $tag_ids, 'freshness' => $freshness_date_query ) );
	$cached = techzei_tt5_editorial_ids_cache_get( $cache_key );
	if ( null !== $cached ) {
		return techzei_tt5_normalize_editorial_ids( apply_filters( 'techzei_tt5_related_story_ids', $cached, $post_id, $limit ), $limit, array( $post_id ) );
	}
	$ids     = array();
	$exclude = array( $post_id );
	if ( $editorial_first ) {
		$selection = apply_filters( 'techzei_tt5_editorial_selection', get_option( 'sticky_posts', array() ), $post_id, $limit );
		$editorial_ids = techzei_tt5_editorial_selection_ids( $selection, $limit, $exclude );
		$ids = array_merge( $ids, $editorial_ids );
		$exclude = array_merge( $exclude, $editorial_ids );
	}
	$tax_query = array( 'relation' => 'OR' );
	if ( ! empty( $tag_ids ) ) {
		$tax_query[] = array( 'taxonomy' => 'post_tag', 'field' => 'term_id', 'terms' => $tag_ids, 'operator' => 'IN', 'include_children' => false );
	}
	if ( ! empty( $category_ids ) ) {
		$tax_query[] = array( 'taxonomy' => 'category', 'field' => 'term_id', 'terms' => $category_ids, 'operator' => 'IN', 'include_children' => false );
	}
	if ( count( $tax_query ) > 1 && count( $ids ) < $limit ) {
		$candidate_query = techzei_tt5_editorial_query( array( 'posts_per_page' => min( 30, max( 12, $limit * 5 ) ), 'post__not_in' => $exclude, 'orderby' => array( 'date' => 'DESC', 'ID' => 'DESC' ), 'date_query' => $freshness_date_query, 'tax_query' => $tax_query ) );
		techzei_tt5_prime_editorial_posts( $candidate_query );
		$tag_lookup = array_fill_keys( $tag_ids, true );
		$category_lookup = array_fill_keys( $category_ids, true );
		$ranked = array();
		foreach ( $candidate_query->posts as $candidate ) {
			$candidate_tags = wp_get_post_terms( $candidate->ID, 'post_tag', array( 'fields' => 'ids' ) );
			$candidate_categories = wp_get_post_terms( $candidate->ID, 'category', array( 'fields' => 'ids' ) );
			$candidate_tags = is_wp_error( $candidate_tags ) ? array() : $candidate_tags;
			$candidate_categories = is_wp_error( $candidate_categories ) ? array() : $candidate_categories;
			$shared_tags = count( array_intersect( $candidate_tags, array_keys( $tag_lookup ) ) );
			$shared_categories = count( array_intersect( $candidate_categories, array_keys( $category_lookup ) ) );
			$depth = 0;
			foreach ( $candidate_categories as $candidate_category ) {
				if ( isset( $category_lookup[ $candidate_category ] ) ) {
					$depth = max( $depth, count( get_ancestors( $candidate_category, 'category', 'taxonomy' ) ) );
				}
			}
			$ranked[] = array( 'post' => $candidate, 'score' => ( $shared_tags * 100 ) + ( $shared_categories * 35 ) + ( $depth * 5 ) + ( has_post_thumbnail( $candidate->ID ) ? 10 : 0 ) );
		}
		usort( $ranked, function ( $left, $right ) {
			if ( $left['score'] === $right['score'] ) {
				$left_date = isset( $left['post']->post_date_gmt ) ? $left['post']->post_date_gmt : '';
				$right_date = isset( $right['post']->post_date_gmt ) ? $right['post']->post_date_gmt : '';
				if ( $left_date === $right_date ) {
					return $left['post']->ID === $right['post']->ID ? 0 : ( $left['post']->ID > $right['post']->ID ? -1 : 1 );
				}
				return $left_date > $right_date ? -1 : 1;
			}
			return $left['score'] > $right['score'] ? -1 : 1;
		} );
		foreach ( $ranked as $item ) {
			if ( count( $ids ) >= $limit ) {
				break;
			}
			$ids[] = $item['post']->ID;
			$exclude[] = $item['post']->ID;
		}
	}
	$ids = techzei_tt5_normalize_editorial_ids( $ids, $limit, array( $post_id ) );
	techzei_tt5_editorial_ids_cache_set( $cache_key, $ids );
	return techzei_tt5_normalize_editorial_ids( apply_filters( 'techzei_tt5_related_story_ids', $ids, $post_id, $limit ), $limit, array( $post_id ) );
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
	$GLOBALS['techzei_tt5_rendered_related_ids'][ $post_id ] = $ids;

	if ( empty( $ids ) ) {
		return '';
	}

	$posts = techzei_tt5_editorial_posts_by_ids( $ids );

	$atts = shortcode_atts( array( 'heading' => '1' ), $atts, 'techzei_related_stories' );
	if ( in_array( sanitize_key( (string) $atts['heading'] ), array( '0', 'false', 'no', 'none' ), true ) ) {
		return techzei_tt5_render_story_list( $posts, 'tz-related-stories' );
	}

	return techzei_tt5_render_story_list( $posts, 'tz-related-stories', __( 'Keep reading', 'techzei-magazine-theme' ), __( 'More on this topic', 'techzei-magazine-theme' ) );
}
add_shortcode( 'techzei_related_stories', 'techzei_tt5_related_stories' );

/** Return IDs reserved by the in-flow related module for overlap prevention. */
function techzei_tt5_inflow_related_exclusions( $post_id ) {
	$post_id = absint( $post_id );
	$ids = isset( $GLOBALS['techzei_tt5_rendered_related_ids'][ $post_id ] ) ? $GLOBALS['techzei_tt5_rendered_related_ids'][ $post_id ] : array();
	if ( empty( $ids ) && techzei_tt5_get_editorial_setting( 'articles.related_stories', true ) ) {
		$limit = min( 6, max( 2, absint( techzei_tt5_get_editorial_setting( 'articles.related_count', 3 ) ) ) );
		$ids = techzei_tt5_related_story_ids( $post_id, $limit );
	}
	return techzei_tt5_normalize_editorial_ids( $ids, 6, array( $post_id ) );
}

/**
 * Return a topic-aware title for the sidebar discovery module.
 *
 * @param WP_Term $term Primary category.
 * @return array
 */
function techzei_tt5_more_in_topic_heading( $term ) {
	$type = techzei_tt5_instructional_category_type( $term );
	if ( 'how-to' === $type ) {
		return array( __( 'How To', 'techzei-magazine-theme' ), __( 'More How Tos', 'techzei-magazine-theme' ) );
	}
	if ( 'explainer' === $type ) {
		return array( __( 'Explainers', 'techzei-magazine-theme' ), __( 'More Explainers', 'techzei-magazine-theme' ) );
	}

	return array( $term->name, __( 'More in this topic', 'techzei-magazine-theme' ) );
}

function techzei_tt5_more_in_topic( $atts = array() ) {
	if ( ! is_singular( 'post' ) || ! techzei_tt5_get_editorial_setting( 'sidebar.more_in_topic', true ) ) {
		return '';
	}
	$post_id = get_the_ID();
	$term = techzei_tt5_primary_category( $post_id );
	if ( ! $term ) {
		return '';
	}
	$atts = shortcode_atts( array( 'count' => techzei_tt5_get_editorial_setting( 'sidebar.more_topic_count', 4 ) ), $atts, 'techzei_more_in_topic' );
	$limit = min( 6, max( 2, absint( $atts['count'] ) ) );
	$exclude = array_merge( array( $post_id ), techzei_tt5_inflow_related_exclusions( $post_id ) );
	$cache_key = techzei_tt5_editorial_cache_key( 'topic', $post_id, array( 'term' => $term->term_id, 'limit' => $limit, 'exclude' => $exclude ) );
	$ids = techzei_tt5_editorial_ids_cache_get( $cache_key );
	if ( null === $ids ) {
		$query = techzei_tt5_editorial_query( array( 'posts_per_page' => min( 24, max( 10, $limit * 4 ) ), 'post__not_in' => $exclude, 'orderby' => array( 'date' => 'DESC', 'ID' => 'DESC' ), 'tax_query' => array( array( 'taxonomy' => 'category', 'field' => 'term_id', 'terms' => array( $term->term_id ), 'include_children' => true ) ) ) );
		techzei_tt5_prime_editorial_posts( $query );
		$ranked = array();
		foreach ( $query->posts as $candidate ) {
			$ranked[] = array( 'id' => $candidate->ID, 'has_thumbnail' => has_post_thumbnail( $candidate->ID ) );
		}
		usort( $ranked, function ( $left, $right ) {
			if ( $left['has_thumbnail'] === $right['has_thumbnail'] ) {
				return 0;
			}
			return $left['has_thumbnail'] ? -1 : 1;
		} );
		$ids = techzei_tt5_normalize_editorial_ids( wp_list_pluck( $ranked, 'id' ), $limit, $exclude );
		techzei_tt5_editorial_ids_cache_set( $cache_key, $ids );
	}
	if ( empty( $ids ) ) {
		return '';
	}
	$posts = techzei_tt5_editorial_posts_by_ids( $ids );
	$heading = techzei_tt5_more_in_topic_heading( $term );
	return techzei_tt5_render_story_list( $posts, 'tz-more-in-topic', $heading[0], $heading[1] );
}
add_shortcode( 'techzei_more_in_topic', 'techzei_tt5_more_in_topic' );

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
	$limit = min( 6, max( 3, absint( techzei_tt5_get_editorial_setting( 'sidebar.latest_count', 5 ) ) ) );
	$cache_key = techzei_tt5_editorial_cache_key( 'latest', $current_post_id, array( 'limit' => $limit ) );
	$ids = techzei_tt5_editorial_ids_cache_get( $cache_key );
	if ( null === $ids ) {
		$query = techzei_tt5_editorial_query( array( 'posts_per_page' => $limit, 'post__not_in' => array( $current_post_id ), 'orderby' => 'date', 'order' => 'DESC' ) );
		techzei_tt5_prime_editorial_posts( $query );
		$ids = techzei_tt5_normalize_editorial_ids( wp_list_pluck( $query->posts, 'ID' ), $limit, array( $current_post_id ) );
		techzei_tt5_editorial_ids_cache_set( $cache_key, $ids );
	}
	return techzei_tt5_render_story_list( techzei_tt5_editorial_posts_by_ids( $ids ), 'tz-latest-stories' );
}
add_shortcode( 'techzei_latest_stories', 'techzei_tt5_latest_stories' );

/** Render the old-theme-style review discovery list for article sidebars. */
function techzei_tt5_latest_reviews() {
	if ( ! techzei_tt5_get_editorial_setting( 'sidebar.reviews', true ) ) {
		return '';
	}
	$current_post_id = get_the_ID();
	$settings_loaded = function_exists( 'techzei_tt5_get_settings' ) || function_exists( 'techzei_tt5_get_setting' );
	$review_term_id = absint( techzei_tt5_get_editorial_setting( 'sidebar.review_category', 0 ) );
	if ( $settings_loaded ) {
		$review_term = $review_term_id ? get_term( $review_term_id, 'category' ) : false;
		if ( ! $review_term || is_wp_error( $review_term ) ) {
			return '';
		}
		$review_tax_query = array( array( 'taxonomy' => 'category', 'field' => 'term_id', 'terms' => array( $review_term_id ) ) );
	} else {
		$review_tax_query = array( array( 'taxonomy' => 'category', 'field' => 'slug', 'terms' => array( 'review' ) ) );
	}
	$max_age = min( 10, max( 1, absint( techzei_tt5_get_editorial_setting( 'sidebar.review_max_age', 5 ) ) ) );
	$limit = min( 6, max( 2, absint( techzei_tt5_get_editorial_setting( 'sidebar.review_count', 4 ) ) ) );
	$cache_key = techzei_tt5_editorial_cache_key( 'reviews', $current_post_id, array( 'limit' => $limit, 'term' => $review_term_id, 'max_age' => $max_age ) );
	$ids = techzei_tt5_editorial_ids_cache_get( $cache_key );
	if ( null === $ids ) {
		$query = techzei_tt5_editorial_query( array( 'posts_per_page' => $limit, 'post__not_in' => array( $current_post_id ), 'orderby' => 'date', 'order' => 'DESC', 'tax_query' => $review_tax_query, 'date_query' => array( array( 'after' => gmdate( 'Y-m-d', strtotime( '-' . $max_age . ' years' ) ), 'inclusive' => true ) ) ) );
		techzei_tt5_prime_editorial_posts( $query );
		$ids = techzei_tt5_normalize_editorial_ids( wp_list_pluck( $query->posts, 'ID' ), $limit, array( $current_post_id ) );
		techzei_tt5_editorial_ids_cache_set( $cache_key, $ids );
	}
	return techzei_tt5_render_story_list( techzei_tt5_editorial_posts_by_ids( $ids ), 'tz-latest-reviews', __( 'Reviews', 'techzei-magazine-theme' ), __( 'Latest reviews', 'techzei-magazine-theme' ) );
}
add_shortcode( 'techzei_latest_reviews', 'techzei_tt5_latest_reviews' );

/** Render a small provider-free follow module for article and homepage sidebars. */
function techzei_tt5_follow_techzei() {
	if ( ( ! is_singular( 'post' ) && ! is_front_page() ) || ! techzei_tt5_get_editorial_setting( 'sidebar.follow_techzei', true ) ) {
		return '';
	}

	$links = array(
		__( 'RSS', 'techzei-magazine-theme' )       => home_url( '/feed/' ),
		__( 'X / Twitter', 'techzei-magazine-theme' ) => 'https://twitter.com/techzei',
		__( 'YouTube', 'techzei-magazine-theme' )   => 'https://www.youtube.com/@techzei',
	);
	$output = '<section class="tz-follow-techzei"><p class="tz-section-kicker">' . esc_html__( 'Stay connected', 'techzei-magazine-theme' ) . '</p><h2>' . esc_html__( 'Follow Techzei', 'techzei-magazine-theme' ) . '</h2><ul>';
	foreach ( $links as $label => $url ) {
		$output .= '<li><a href="' . esc_url( $url ) . '"' . ( 0 === strpos( $url, 'http' ) ? ' target="_blank" rel="noopener noreferrer"' : '' ) . '>' . esc_html( $label ) . '</a></li>';
	}
	return $output . '</ul></section>';
}
add_shortcode( 'techzei_follow_techzei', 'techzei_tt5_follow_techzei' );

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
	if ( $posts instanceof WP_Query ) {
		if ( ! $posts->have_posts() ) {
			return '';
		}
	} elseif ( ! is_array( $posts ) || empty( $posts ) ) {
		return '';
	}
	$output = $heading ? '<section class="' . esc_attr( $class ) . '">' : '<ol class="' . esc_attr( $class ) . '">';
	if ( $heading ) {
		$output .= '<p class="tz-section-kicker">' . esc_html( $kicker ) . '</p>';
		$output .= '<h2>' . esc_html( $heading ) . '</h2><ol>';
	}
	if ( $posts instanceof WP_Query ) {
		$items = $posts->posts;
	} else {
		$items = $posts;
	}
	foreach ( $items as $post ) {
		if ( ! $post instanceof WP_Post ) {
			continue;
		}
		$GLOBALS['post'] = $post;
		setup_postdata( $post );
		$image_size = 'tz-related-stories' === $class ? '(max-width: 600px) 88px, 132px' : '88px';
		$thumbnail = get_the_post_thumbnail( get_the_ID(), 'techzei-feed', array( 'class' => 'tz-story-thumb-image', 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => $image_size ) );
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
