<?php
/**
 * Author portfolios: public profiles, bounded statistics, and native archives.
 *
 * @package Techzei_TT5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Return the author being viewed, never the signed-in user or a Loop author. */
function techzei_tt5_archive_author() {
	$author = is_author() ? get_queried_object() : null;
	return $author instanceof WP_User ? $author : null;
}

/** Normalize the optional, portable profile metadata without writing defaults. */
function techzei_tt5_author_profile( $user_id ) {
	$stored = get_user_meta( absint( $user_id ), '_techzei_author_portfolio', true );
	$stored = is_array( $stored ) ? $stored : array();
	return array(
		'version' => 1,
		'role'    => isset( $stored['role'] ) && is_string( $stored['role'] ) ? wp_html_excerpt( sanitize_text_field( $stored['role'] ), 120, '' ) : '',
		'topics'  => isset( $stored['topics'] ) && is_array( $stored['topics'] ) ? array_slice( array_values( array_unique( array_filter( array_map( 'absint', $stored['topics'] ) ) ) ), 0, 5 ) : array(),
		'posts'   => isset( $stored['posts'] ) && is_array( $stored['posts'] ) ? array_slice( array_values( array_unique( array_filter( array_map( 'absint', $stored['posts'] ) ) ) ), 0, 3 ) : array(),
	);
}

/**
 * Resolve public work statistics using two aggregate queries on a cold cache.
 *
 * Never load every post into PHP. The first query returns at most 200 year
 * groups, including an indexed, LIMIT-1 oldest-post lookup; the second returns
 * at most 100 category groups. A post assigned to two review aliases is counted
 * once. Category counts are direct memberships, not site-wide term counts or
 * overlapping parent/child rollups.
 *
 * Only data is cached for 12 hours. The existing editorial namespace covers
 * post saves, author reassignment, taxonomy edits, and relationship changes.
 * Additional hooks below cover scheduled/status transitions, relationship
 * removal, and deletion before the old author/post data disappears. Profiles
 * are read through WordPress user metadata separately, so profile edits need
 * no cached HTML purge. Transients work without a persistent object cache.
 */
function techzei_tt5_author_statistics( $user_id ) {
	global $wpdb;
	$user_id = absint( $user_id );
	$empty   = array( 'total' => 0, 'reviews' => 0, 'first_date' => '', 'first_id' => 0, 'years' => array(), 'topics' => array() );
	if ( ! $user_id || ! get_userdata( $user_id ) ) {
		return $empty;
	}
	$key = 'techzei_author_stats_' . md5( $user_id . ':' . techzei_tt5_editorial_cache_version() );
	if ( isset( $GLOBALS['techzei_tt5_author_stats'][ $key ] ) ) {
		return $GLOBALS['techzei_tt5_author_stats'][ $key ];
	}
	$cached = get_transient( $key );
	if ( is_array( $cached ) && isset( $cached['total'], $cached['years'], $cached['topics'] ) ) {
		$GLOBALS['techzei_tt5_author_stats'][ $key ] = $cached;
		return $cached;
	}
	$GLOBALS['techzei_tt5_author_stats_queries'] = isset( $GLOBALS['techzei_tt5_author_stats_queries'] ) ? $GLOBALS['techzei_tt5_author_stats_queries'] + 1 : 1;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Bounded, prepared aggregate; cached below.
	$years = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT YEAR(p.post_date) AS publication_year, COUNT(DISTINCT p.ID) AS total,
				MIN(p.post_date) AS first_date,
				COUNT(DISTINCT CASE WHEN tt.taxonomy = 'category' AND t.slug IN ('review', 'reviews') THEN p.ID END) AS reviews,
				(SELECT oldest.ID FROM {$wpdb->posts} oldest
				 WHERE oldest.post_author = %d AND oldest.post_type = 'post' AND oldest.post_status = 'publish'
				 ORDER BY oldest.post_date ASC, oldest.ID ASC LIMIT 1) AS first_id
			 FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
			 LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
			 LEFT JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
			 WHERE p.post_author = %d AND p.post_type = 'post' AND p.post_status = 'publish'
			 GROUP BY YEAR(p.post_date) ORDER BY publication_year DESC LIMIT 200",
			$user_id,
			$user_id
		),
		ARRAY_A
	);
	if ( ! is_array( $years ) || $wpdb->last_error ) {
		return $empty;
	}
	$data = $empty;
	foreach ( $years as $year ) {
		$data['total']  += absint( $year['total'] );
		$data['reviews'] += absint( $year['reviews'] );
		$data['years'][] = absint( $year['publication_year'] );
		$data['first_id'] = absint( $year['first_id'] );
		if ( '' === $data['first_date'] || $year['first_date'] < $data['first_date'] ) {
			$data['first_date'] = $year['first_date'];
		}
	}
	if ( $data['total'] ) {
		++$GLOBALS['techzei_tt5_author_stats_queries'];
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Author-specific counts, bounded and cached.
		$topics = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id, t.name, t.slug, COUNT(DISTINCT p.ID) AS total
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'category'
				 INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
				 WHERE p.post_author = %d AND p.post_type = 'post' AND p.post_status = 'publish'
				 GROUP BY t.term_id, t.name, t.slug ORDER BY total DESC, t.name ASC, t.term_id ASC LIMIT 100",
				$user_id
			),
			ARRAY_A
		);
		if ( ! is_array( $topics ) || $wpdb->last_error ) {
			return $empty;
		}
		foreach ( $topics as $topic ) {
			$data['topics'][] = array( 'id' => absint( $topic['term_id'] ), 'name' => $topic['name'], 'slug' => $topic['slug'], 'total' => absint( $topic['total'] ) );
		}
	}
	$ttl = max( MINUTE_IN_SECONDS, min( DAY_IN_SECONDS, (int) apply_filters( 'techzei_tt5_author_stats_ttl', 12 * HOUR_IN_SECONDS, $user_id ) ) );
	set_transient( $key, $data, $ttl );
	$GLOBALS['techzei_tt5_author_stats'][ $key ] = $data;
	return $data;
}

/** Invalidate public statistics for status changes that do not need an editor save. */
function techzei_tt5_author_status_changed( $new_status, $old_status, $post ) {
	if ( $post instanceof WP_Post && 'post' === $post->post_type && $new_status !== $old_status && ( 'publish' === $new_status || 'publish' === $old_status ) ) {
		techzei_tt5_invalidate_editorial_cache();
	}
}
add_action( 'transition_post_status', 'techzei_tt5_author_status_changed', 20, 3 );

/** Catch permanent deletion before get_post() can no longer resolve the old post. */
function techzei_tt5_author_before_delete( $post_id, $post ) {
	if ( $post instanceof WP_Post && 'post' === $post->post_type && 'publish' === $post->post_status ) {
		techzei_tt5_invalidate_editorial_cache();
	}
}
add_action( 'before_delete_post', 'techzei_tt5_author_before_delete', 20, 2 );

/** wp_remove_object_terms() does not fire set_object_terms. */
function techzei_tt5_author_terms_removed( $post_id, $term_taxonomy_ids, $taxonomy ) {
	if ( 'category' === $taxonomy && 'post' === get_post_type( $post_id ) ) {
		techzei_tt5_invalidate_editorial_cache();
	}
}
add_action( 'deleted_term_relationships', 'techzei_tt5_author_terms_removed', 20, 3 );

/** Only accept scalar non-negative integer archive controls. */
function techzei_tt5_author_filter_number( $value ) {
	return is_scalar( $value ) && preg_match( '/^\d{1,10}$/D', (string) $value ) ? absint( $value ) : 0;
}

/** Register public, GET-based controls without replacing the main archive query. */
function techzei_tt5_author_query_vars( $vars ) {
	$vars[] = 'tz_topic';
	$vars[] = 'tz_year';
	return $vars;
}
add_filter( 'query_vars', 'techzei_tt5_author_query_vars' );

/** Keep author scope and native pagination when a topic or year is selected. */
function techzei_tt5_filter_author_archive( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_author() || $query->is_feed() ) {
		return;
	}
	$query->set( 'post_status', 'publish' );
	$topic = techzei_tt5_author_filter_number( $query->get( 'tz_topic' ) );
	$year  = techzei_tt5_author_filter_number( $query->get( 'tz_year' ) );
	if ( $topic && term_exists( $topic, 'category' ) ) {
		$tax_query = array( 'relation' => 'AND' );
		$existing  = $query->get( 'tax_query' );
		if ( is_array( $existing ) && $existing ) {
			$tax_query[] = $existing;
		}
		$tax_query[] = array( 'taxonomy' => 'category', 'field' => 'term_id', 'terms' => array( $topic ), 'include_children' => false );
		$query->set( 'tax_query', $tax_query );
	}
	if ( $year >= 1000 && $year <= 9999 ) {
		$query->set( 'year', $year );
	}
}
add_action( 'pre_get_posts', 'techzei_tt5_filter_author_archive', 20 );

/** Redirect malformed or empty portfolio controls to the clean author URL. */
function techzei_tt5_canonical_author_filters() {
	if ( ! is_author() || is_feed() || is_preview() ) {
		return;
	}
	$author = techzei_tt5_archive_author();
	if ( ! $author ) {
		return;
	}
	$has_topic = isset( $_GET['tz_topic'] );
	$has_year  = isset( $_GET['tz_year'] );
	if ( ! $has_topic && ! $has_year ) {
		return;
	}
	$raw_topic = $has_topic ? wp_unslash( $_GET['tz_topic'] ) : null;
	$raw_year  = $has_year ? wp_unslash( $_GET['tz_year'] ) : null;
	$topic     = techzei_tt5_author_filter_number( $raw_topic );
	$year      = techzei_tt5_author_filter_number( $raw_year );
	$valid_topic = $has_topic && is_scalar( $raw_topic ) && $topic && (string) $topic === (string) $raw_topic && term_exists( $topic, 'category' );
	$valid_year  = $has_year && is_scalar( $raw_year ) && $year >= 1000 && $year <= 9999 && (string) $year === (string) $raw_year;
	if ( ( ! $has_topic || $valid_topic ) && ( ! $has_year || $valid_year ) ) {
		return;
	}
	$url = get_author_posts_url( $author->ID );
	if ( $valid_topic ) {
		$url = add_query_arg( 'tz_topic', $topic, $url );
	}
	if ( $valid_year ) {
		$url = add_query_arg( 'tz_year', $year, $url );
	}
	wp_safe_redirect( $url, 301 );
	exit;
}
add_action( 'template_redirect', 'techzei_tt5_canonical_author_filters', 1 );

/** Use the existing avatar API, or readable initials when avatars are disabled. */
function techzei_tt5_author_portrait( $author ) {
	$avatar = get_avatar( $author->ID, 192, '', $author->display_name, array( 'class' => 'tz-author-portrait', 'loading' => 'eager', 'decoding' => 'async', 'fetchpriority' => 'low' ) );
	if ( $avatar ) {
		return $avatar;
	}
	$words    = preg_split( '/\s+/u', trim( $author->display_name ), 2 );
	$initials = '';
	foreach ( $words as $word ) {
		$initials .= wp_html_excerpt( $word, 1, '' );
	}
	return '<span class="tz-author-portrait tz-author-initials" aria-hidden="true">' . esc_html( $initials ) . '</span>';
}

/** Compare public URLs while ignoring scheme and trailing slash. */
function techzei_tt5_author_link_identity( $url ) {
	$host = wp_parse_url( $url, PHP_URL_HOST );
	$path = wp_parse_url( $url, PHP_URL_PATH );
	return $host ? strtolower( preg_replace( '/^www\./i', '', $host ) ) . untrailingslashit( (string) $path ) : '';
}

/** Detect a social link already placed in the editor-owned biography. */
function techzei_tt5_author_bio_has_link( $bio, $url ) {
	if ( ! $bio || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return false;
	}
	$target = techzei_tt5_author_link_identity( $url );
	$tags   = new WP_HTML_Tag_Processor( $bio );
	while ( $tags->next_tag( 'A' ) ) {
		if ( $target && $target === techzei_tt5_author_link_identity( (string) $tags->get_attribute( 'href' ) ) ) {
			return true;
		}
	}
	return false;
}

/** Public profile links; email addresses and WordPress permission roles stay private. */
function techzei_tt5_author_links( $author, $bio = '' ) {
	$links   = array();
	$website = esc_url( $author->user_url, array( 'http', 'https' ) );
	if ( $website && techzei_tt5_author_link_identity( $website ) === techzei_tt5_author_link_identity( get_author_posts_url( $author->ID ) ) ) {
		$website = '';
	}
	$twitter = trim( (string) get_user_meta( $author->ID, 'twitter', true ) );
	if ( $twitter && preg_match( '~^https?://(?:www\.)?(?:twitter\.com|x\.com)/([A-Za-z0-9_]{1,15})/?$~i', $twitter, $match ) ) {
		$twitter = $match[1];
	}
	$twitter = ltrim( $twitter, '@' );
	if ( preg_match( '/^[A-Za-z0-9_]{1,15}$/D', $twitter ) ) {
		$x_url = 'https://x.com/' . $twitter;
		if ( ! techzei_tt5_author_bio_has_link( $bio, $x_url ) ) {
			$links[] = '<a href="' . esc_url( $x_url ) . '">' . esc_html__( 'Follow on X', 'techzei-magazine-theme' ) . '</a>';
		}
	}
	if ( $website ) {
		$links[] = '<a href="' . $website . '">' . esc_html__( 'Personal website', 'techzei-magazine-theme' ) . '</a>';
	}
	$links[] = '<a href="' . esc_url( get_author_feed_link( $author->ID ) ) . '">' . esc_html__( 'Article feed', 'techzei-magazine-theme' ) . '</a>';
	return '<nav class="tz-author-links" aria-label="' . esc_attr__( 'Author links', 'techzei-magazine-theme' ) . '">' . implode( '', $links ) . '</nav>';
}

/** Profile shortcode used by the editable author header part. */
function techzei_tt5_render_author_header() {
	$author = techzei_tt5_archive_author();
	if ( ! $author ) {
		return '';
	}
	$profile = techzei_tt5_author_profile( $author->ID );
	$bio     = get_the_author_meta( 'description', $author->ID );
	$html    = '<section class="tz-author-header" aria-labelledby="tz-author-name">' . techzei_tt5_author_portrait( $author ) . '<div class="tz-author-intro">';
	if ( $profile['role'] ) {
		$html .= '<p class="tz-section-kicker tz-author-role">' . esc_html( $profile['role'] ) . '</p>';
	}
	$html .= '<h1 class="tz-author-name" id="tz-author-name">' . esc_html( $author->display_name ) . '</h1>';
	if ( $bio ) {
		$html .= '<div class="tz-author-bio">' . wp_kses_post( wpautop( $bio ) ) . '</div>';
	}
	return $html . techzei_tt5_author_links( $author, $bio ) . '</div></section>';
}
add_shortcode( 'techzei_author_header', 'techzei_tt5_render_author_header' );

/** Lifetime figures deliberately do not change when the article feed is filtered. */
function techzei_tt5_render_author_statistics() {
	$author = techzei_tt5_archive_author();
	if ( ! $author ) {
		return '';
	}
	$data = techzei_tt5_author_statistics( $author->ID );
	if ( ! $data['total'] ) {
		return '';
	}
	$figures = array(
		array( number_format_i18n( $data['total'] ), _n( 'published article', 'published articles', $data['total'], 'techzei-magazine-theme' ) ),
		array( mysql2date( 'Y', $data['first_date'] ), __( 'first archived story', 'techzei-magazine-theme' ) ),
	);
	if ( $data['reviews'] ) {
		$figures[] = array( number_format_i18n( $data['reviews'] ), _n( 'review article', 'review articles', $data['reviews'], 'techzei-magazine-theme' ) );
	}
	$html = '<dl class="tz-author-statistics" aria-label="' . esc_attr__( 'Published work', 'techzei-magazine-theme' ) . '">';
	foreach ( $figures as $figure ) {
		$html .= '<div><dt>' . esc_html( $figure[1] ) . '</dt><dd>' . esc_html( $figure[0] ) . '</dd></div>';
	}
	return $html . '</dl>';
}
add_shortcode( 'techzei_author_statistics', 'techzei_tt5_render_author_statistics' );

/** Only show explicitly selected, currently public posts belonging to this author. */
function techzei_tt5_render_author_selected_work() {
	$author = techzei_tt5_archive_author();
	if ( ! $author || is_paged() || get_query_var( 'tz_topic' ) || get_query_var( 'tz_year' ) ) {
		return '';
	}
	$profile = techzei_tt5_author_profile( $author->ID );
	if ( ! $profile['posts'] ) {
		return '';
	}
	$posts = get_posts( array( 'author' => $author->ID, 'post_type' => 'post', 'post_status' => 'publish', 'post__in' => $profile['posts'], 'posts_per_page' => 3, 'orderby' => 'post__in', 'ignore_sticky_posts' => true, 'no_found_rows' => true, 'suppress_filters' => false ) );
	if ( ! $posts ) {
		return '';
	}
	$html = '<section class="tz-author-selected-work" aria-labelledby="tz-author-selected-heading"><p class="tz-section-kicker">' . esc_html__( 'A place to start', 'techzei-magazine-theme' ) . '</p><h2 id="tz-author-selected-heading">' . esc_html__( 'Selected work', 'techzei-magazine-theme' ) . '</h2><div class="tz-author-work-grid">';
	foreach ( $posts as $post ) {
		$image = get_the_post_thumbnail( $post, 'techzei-feed', array( 'loading' => 'lazy', 'fetchpriority' => 'low', 'decoding' => 'async', 'sizes' => '(max-width: 600px) calc(100vw - 30px), (max-width: 900px) calc((100vw - 48px) / 2), 440px' ) );
		$label = get_the_title( $post );
		if ( '' === trim( $label ) ) {
			$label = __( 'Untitled story', 'techzei-magazine-theme' );
		}
		$html .= '<article class="tz-author-work"><a class="tz-author-work-image" href="' . esc_url( get_permalink( $post ) ) . '" tabindex="-1" aria-hidden="true">' . ( $image ? $image : '<span class="tz-author-work-placeholder">' . esc_html__( 'Techzei', 'techzei-magazine-theme' ) . '</span>' ) . '</a><h3><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( $label ) . '</a></h3><p class="tz-author-work-meta"><time datetime="' . esc_attr( get_the_date( DATE_W3C, $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time>';
		$category = techzei_tt5_primary_category( $post->ID );
		if ( $category ) {
			$html .= '<span>' . esc_html( $category->name ) . '</span>';
		}
		$html .= '</p></article>';
	}
	return $html . '</div></section>';
}
add_shortcode( 'techzei_author_selected_work', 'techzei_tt5_render_author_selected_work' );

/** Keep topic links inside this author's work, including plain-permalink sites. */
function techzei_tt5_author_topic_url( $user_id, $topic_id ) {
	return add_query_arg( 'tz_topic', absint( $topic_id ), get_author_posts_url( absint( $user_id ) ) );
}

/** Author-specific coverage, personal topic selections, and the dated first story. */
function techzei_tt5_render_author_topics() {
	$author = techzei_tt5_archive_author();
	if ( ! $author ) {
		return '';
	}
	$data       = techzei_tt5_author_statistics( $author->ID );
	$profile    = techzei_tt5_author_profile( $author->ID );
	$excluded   = apply_filters( 'techzei_tt5_author_topic_exclusions', array( 'news', 'featured', 'general', 'how-to', 'howto', 'tutorial', 'tutorials', 'explainer', 'explainers', 'review', 'reviews', 'buying-guides', 'india', 'quick', 'uncategorized' ), $author->ID );
	$topics     = array();
	foreach ( $data['topics'] as $topic ) {
		if ( ! in_array( $topic['slug'], (array) $excluded, true ) ) {
			$topics[] = $topic;
		}
		if ( count( $topics ) >= 5 ) {
			break;
		}
	}
	$html = '';
	if ( $topics ) {
		$html .= '<section class="tz-author-topic-section" aria-labelledby="tz-author-topics-heading"><p class="tz-section-kicker">' . esc_html__( 'In the archive', 'techzei-magazine-theme' ) . '</p><h2 id="tz-author-topics-heading">' . esc_html__( 'Most covered topics', 'techzei-magazine-theme' ) . '</h2><ul class="tz-author-topics">';
		$max = max( 1, $topics[0]['total'] );
		foreach ( $topics as $topic ) {
			$width = number_format( min( 100, 100 * $topic['total'] / $max ), 2, '.', '' );
			$html .= '<li><a href="' . esc_url( techzei_tt5_author_topic_url( $author->ID, $topic['id'] ) ) . '"><span>' . esc_html( $topic['name'] ) . '</span><span class="tz-author-topic-count">' . esc_html( sprintf( _n( '%s article', '%s articles', $topic['total'], 'techzei-magazine-theme' ), number_format_i18n( $topic['total'] ) ) ) . '</span></a><span class="tz-author-topic-track" aria-hidden="true"><span style="width:' . esc_attr( $width ) . '%"></span></span></li>';
		}
		$html .= '</ul><p class="tz-author-note">' . esc_html__( 'Published articles, all time. An article can cover more than one topic.', 'techzei-magazine-theme' ) . '</p></section>';
	}
	$favourites = '';
	foreach ( $profile['topics'] as $term_id ) {
		$term = get_term( $term_id, 'category' );
		if ( $term && ! is_wp_error( $term ) ) {
			$favourites .= '<li><a href="' . esc_url( techzei_tt5_author_topic_url( $author->ID, $term_id ) ) . '">' . esc_html( $term->name ) . '</a></li>';
		}
	}
	if ( $favourites ) {
		$html .= '<section class="tz-author-topic-section" aria-labelledby="tz-author-favourites-heading"><h2 id="tz-author-favourites-heading">' . esc_html__( 'Favourite topics', 'techzei-magazine-theme' ) . '</h2><ul class="tz-author-favourites">' . $favourites . '</ul></section>';
	}
	$first = $data['first_id'] ? get_post( $data['first_id'] ) : null;
	if ( $first && 'publish' === $first->post_status && 'post' === $first->post_type && (int) $first->post_author === (int) $author->ID ) {
		$html .= '<section class="tz-author-topic-section tz-author-first-story" aria-labelledby="tz-author-first-story-heading"><h2 class="tz-section-kicker" id="tz-author-first-story-heading">' . esc_html__( 'First in the archive', 'techzei-magazine-theme' ) . '</h2><h3><a href="' . esc_url( get_permalink( $first ) ) . '">' . esc_html( get_the_title( $first ) ) . '</a></h3><p class="tz-author-note">' . esc_html( get_the_date( '', $first ) ) . '</p></section>';
	}
	return $html;
}
add_shortcode( 'techzei_author_topics', 'techzei_tt5_render_author_topics' );

/** Native GET controls reset pagination but never remove the author scope. */
function techzei_tt5_render_author_archive_controls() {
	global $wp_query;
	$author = techzei_tt5_archive_author();
	if ( ! $author ) {
		return '';
	}
	$data  = techzei_tt5_author_statistics( $author->ID );
	$topic = techzei_tt5_author_filter_number( get_query_var( 'tz_topic' ) );
	$year  = techzei_tt5_author_filter_number( get_query_var( 'tz_year' ) );
	$count = absint( $wp_query->found_posts );
	$html  = '<div class="tz-author-archive-heading"><h2 id="tz-author-archive-heading">' . esc_html__( 'Articles', 'techzei-magazine-theme' ) . '</h2><p>' . esc_html( sprintf( _n( '%s article', '%s articles', $count, 'techzei-magazine-theme' ), number_format_i18n( $count ) ) ) . '</p></div>';
	if ( ! $data['total'] ) {
		return $html;
	}
	$html .= '<form class="tz-author-filters" method="get" action="' . esc_url( get_author_posts_url( $author->ID ) ) . '">';
	if ( ! get_option( 'permalink_structure' ) ) {
		$html .= '<input type="hidden" name="author" value="' . esc_attr( $author->ID ) . '">';
	}
	$html .= '<div><label for="tz-author-topic">' . esc_html__( 'Topic', 'techzei-magazine-theme' ) . '</label><select id="tz-author-topic" name="tz_topic"><option value="0">' . esc_html__( 'All topics', 'techzei-magazine-theme' ) . '</option>';
	foreach ( $data['topics'] as $category ) {
		$html .= '<option value="' . esc_attr( $category['id'] ) . '"' . selected( $topic, $category['id'], false ) . '>' . esc_html( $category['name'] ) . '</option>';
	}
	$html .= '</select></div><div><label for="tz-author-year">' . esc_html__( 'Year', 'techzei-magazine-theme' ) . '</label><select id="tz-author-year" name="tz_year"><option value="0">' . esc_html__( 'All years', 'techzei-magazine-theme' ) . '</option>';
	foreach ( $data['years'] as $publication_year ) {
		$html .= '<option value="' . esc_attr( $publication_year ) . '"' . selected( $year, $publication_year, false ) . '>' . esc_html( $publication_year ) . '</option>';
	}
	$html .= '</select></div><button type="submit">' . esc_html__( 'Filter articles', 'techzei-magazine-theme' ) . '</button>';
	if ( $topic || $year ) {
		$html .= '<a href="' . esc_url( get_author_posts_url( $author->ID ) ) . '">' . esc_html__( 'Clear filters', 'techzei-magazine-theme' ) . '</a>';
	}
	return $html . '</form>';
}
add_shortcode( 'techzei_author_archive_controls', 'techzei_tt5_render_author_archive_controls' );

/** Author-specific empty copy rather than the generic category empty state. */
function techzei_tt5_render_author_empty() {
	$author = techzei_tt5_archive_author();
	if ( $author && ! techzei_tt5_author_statistics( $author->ID )['total'] ) {
		return '<p class="tz-empty-state tz-author-empty">' . esc_html__( 'Articles from this author will appear here once published.', 'techzei-magazine-theme' ) . '</p>';
	}
	return '<p class="tz-empty-state tz-author-empty">' . esc_html__( 'No articles to show here yet. Try another topic or year, or return to this author’s full archive.', 'techzei-magazine-theme' ) . '</p>';
}
add_shortcode( 'techzei_author_empty', 'techzei_tt5_render_author_empty' );

/**
 * Preserve our block markup through core's two-stage template rendering.
 *
 * Block templates expand shortcodes before do_blocks(), after which the core
 * Shortcode block applies wpautop(). Applying that formatter to this already
 * structured HTML inserts paragraphs between grid children. Bypass only these
 * known author components, keeping ordinary Shortcode blocks unchanged. In a
 * direct block preview the inner HTML is still the original shortcode, so
 * resolve that exact, attribute-free shortcode once instead.
 */
function techzei_tt5_author_shortcode_block( $pre_render, $block ) {
	if ( null !== $pre_render || 'core/shortcode' !== $block['blockName'] ) {
		return $pre_render;
	}
	$html = isset( $block['innerHTML'] ) ? trim( $block['innerHTML'] ) : '';
	if ( preg_match( '/^\[techzei_author_(?:header|statistics|selected_work|topics|archive_controls|empty)\]$/D', $html ) ) {
		return do_shortcode( $html );
	}
	foreach ( array( '<section class="tz-author-header"', '<dl class="tz-author-statistics"', '<section class="tz-author-selected-work"', '<section class="tz-author-topic-section"', '<div class="tz-author-archive-heading"', '<p class="tz-empty-state tz-author-empty"' ) as $prefix ) {
		if ( 0 === strpos( $html, $prefix ) ) {
			return $html;
		}
	}
	return $pre_render;
}
add_filter( 'pre_render_block', 'techzei_tt5_author_shortcode_block', 10, 2 );

/** Load the portfolio stylesheet only on author archives. */
function techzei_tt5_author_assets() {
	if ( is_author() ) {
		wp_enqueue_style( 'techzei-magazine-theme-author', get_stylesheet_directory_uri() . '/assets/css/author.css', array( 'techzei-magazine-theme' ), wp_get_theme()->get( 'Version' ) );
	}
}
add_action( 'wp_enqueue_scripts', 'techzei_tt5_author_assets', 20 );

/** Optional author-owned fields, kept out of the global theme settings option. */
function techzei_tt5_author_profile_fields( $user ) {
	if ( ! current_user_can( 'edit_user', $user->ID ) ) {
		return;
	}
	$profile = techzei_tt5_author_profile( $user->ID );
	$terms   = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'number' => 100, 'orderby' => 'name' ) );
	if ( is_wp_error( $terms ) ) {
		$terms = array();
	}
	$seen = wp_list_pluck( $terms, 'term_id' );
	foreach ( array_diff( $profile['topics'], $seen ) as $term_id ) {
		$term = get_term( $term_id, 'category' );
		if ( $term && ! is_wp_error( $term ) ) {
			$terms[] = $term;
		}
	}
	?>
	<h2><?php esc_html_e( 'Techzei author portfolio', 'techzei-magazine-theme' ); ?></h2>
	<p><?php esc_html_e( 'Your portrait, biography, website and social links use your existing WordPress profile. The fields below are optional.', 'techzei-magazine-theme' ); ?></p>
	<?php wp_nonce_field( 'techzei_author_profile_' . $user->ID, 'techzei_author_profile_nonce' ); ?>
	<table class="form-table" role="presentation">
		<tr><th><label for="tz-author-role"><?php esc_html_e( 'Editorial role', 'techzei-magazine-theme' ); ?></label></th><td><input class="regular-text" id="tz-author-role" name="techzei_author_portfolio[role]" type="text" maxlength="120" value="<?php echo esc_attr( $profile['role'] ); ?>"><p class="description"><?php esc_html_e( 'Public wording such as Editor-in-Chief or Contributing Writer.', 'techzei-magazine-theme' ); ?></p></td></tr>
		<tr><th><?php esc_html_e( 'Favourite topics', 'techzei-magazine-theme' ); ?></th><td><fieldset><legend class="screen-reader-text"><?php esc_html_e( 'Choose up to five favourite topics', 'techzei-magazine-theme' ); ?></legend>
		<?php foreach ( $terms as $term ) : ?>
			<label><input type="checkbox" name="techzei_author_portfolio[topics][]" value="<?php echo esc_attr( $term->term_id ); ?>" <?php checked( in_array( (int) $term->term_id, $profile['topics'], true ) ); ?>> <?php echo esc_html( $term->name ); ?></label><br>
		<?php endforeach; ?>
		<p class="description"><?php esc_html_e( 'Choose up to five interests. Most covered topics are calculated separately from your published articles.', 'techzei-magazine-theme' ); ?></p></fieldset></td></tr>
		<tr><th><?php esc_html_e( 'Selected work', 'techzei-magazine-theme' ); ?></th><td>
		<?php for ( $index = 0; $index < 3; ++$index ) : ?>
			<p><label for="tz-author-story-<?php echo esc_attr( $index ); ?>"><?php echo esc_html( sprintf( __( 'Story %d', 'techzei-magazine-theme' ), $index + 1 ) ); ?></label><br><input type="url" class="large-text" id="tz-author-story-<?php echo esc_attr( $index ); ?>" name="techzei_author_portfolio[stories][]" value="<?php echo isset( $profile['posts'][ $index ] ) ? esc_url( get_permalink( $profile['posts'][ $index ] ) ) : ''; ?>"></p>
		<?php endfor; ?>
		<p class="description"><?php esc_html_e( 'Paste up to three links to your own published articles, in the order you want them displayed. Leave blank to show the regular archive without a selected-work section.', 'techzei-magazine-theme' ); ?></p></td></tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'techzei_tt5_author_profile_fields' );
add_action( 'edit_user_profile', 'techzei_tt5_author_profile_fields' );

/** Validate the complete profile atomically, including ownership and visibility. */
function techzei_tt5_validate_author_profile( $input, $user_id ) {
	if ( ! is_array( $input ) || ( isset( $input['role'] ) && ! is_scalar( $input['role'] ) ) || ( isset( $input['topics'] ) && ! is_array( $input['topics'] ) ) || ( isset( $input['stories'] ) && ! is_array( $input['stories'] ) ) ) {
		return new WP_Error( 'techzei_author_profile', __( 'Please review the author portfolio fields.', 'techzei-magazine-theme' ) );
	}
	$profile = array( 'version' => 1, 'role' => isset( $input['role'] ) ? wp_html_excerpt( sanitize_text_field( $input['role'] ), 120, '' ) : '', 'topics' => array(), 'posts' => array() );
	foreach ( isset( $input['topics'] ) ? $input['topics'] : array() as $value ) {
		$term_id = techzei_tt5_author_filter_number( $value );
		if ( ! $term_id || ! term_exists( $term_id, 'category' ) ) {
			return new WP_Error( 'techzei_author_topics', __( 'Choose existing categories for your favourite topics.', 'techzei-magazine-theme' ) );
		}
		$profile['topics'][] = $term_id;
	}
	$profile['topics'] = array_values( array_unique( $profile['topics'] ) );
	if ( count( $profile['topics'] ) > 5 ) {
		return new WP_Error( 'techzei_author_topics', __( 'Choose no more than five favourite topics.', 'techzei-magazine-theme' ) );
	}
	$stories = isset( $input['stories'] ) ? $input['stories'] : array();
	if ( count( $stories ) > 3 ) {
		return new WP_Error( 'techzei_author_posts', __( 'Choose no more than three selected articles.', 'techzei-magazine-theme' ) );
	}
	foreach ( $stories as $value ) {
		if ( ! is_string( $value ) || strlen( $value ) > 2048 ) {
			return new WP_Error( 'techzei_author_posts', __( 'Enter a valid article link for selected work.', 'techzei-magazine-theme' ) );
		}
		$value = trim( $value );
		if ( '' === $value ) {
			continue;
		}
		$post_id = ctype_digit( $value ) ? absint( $value ) : url_to_postid( esc_url_raw( $value ) );
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status || (int) $post->post_author !== (int) $user_id ) {
			return new WP_Error( 'techzei_author_posts', __( 'Selected work must link to published articles belonging to this author.', 'techzei-magazine-theme' ) );
		}
		$profile['posts'][] = $post_id;
	}
	$profile['posts'] = array_values( array_unique( $profile['posts'] ) );
	return $profile;
}

/** Validate before WordPress accepts a profile update; never partially save fields. */
function techzei_tt5_author_profile_errors( $errors, $update, $user ) {
	if ( ! isset( $_POST['techzei_author_portfolio'] ) ) {
		return;
	}
	$nonce = isset( $_POST['techzei_author_profile_nonce'] ) && is_string( $_POST['techzei_author_profile_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['techzei_author_profile_nonce'] ) ) : '';
	if ( ! current_user_can( 'edit_user', $user->ID ) || ! wp_verify_nonce( $nonce, 'techzei_author_profile_' . $user->ID ) ) {
		$errors->add( 'techzei_author_permission', __( 'The author portfolio could not be saved. Please reload the profile and try again.', 'techzei-magazine-theme' ) );
		return;
	}
	$profile = techzei_tt5_validate_author_profile( wp_unslash( $_POST['techzei_author_portfolio'] ), $user->ID );
	if ( is_wp_error( $profile ) ) {
		$errors->add( $profile->get_error_code(), $profile->get_error_message() );
	}
}
add_action( 'user_profile_update_errors', 'techzei_tt5_author_profile_errors', 10, 3 );

/** The save hooks repeat capability, nonce, and input checks as a final guard. */
function techzei_tt5_save_author_profile( $user_id ) {
	if ( ! isset( $_POST['techzei_author_portfolio'] ) || ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}
	$nonce = isset( $_POST['techzei_author_profile_nonce'] ) && is_string( $_POST['techzei_author_profile_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['techzei_author_profile_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'techzei_author_profile_' . $user_id ) ) {
		return;
	}
	$profile = techzei_tt5_validate_author_profile( wp_unslash( $_POST['techzei_author_portfolio'] ), $user_id );
	if ( ! is_wp_error( $profile ) ) {
		update_user_meta( $user_id, '_techzei_author_portfolio', $profile );
	}
}
add_action( 'profile_update', 'techzei_tt5_save_author_profile' );
