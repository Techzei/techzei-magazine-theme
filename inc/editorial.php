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

	$class  = $mobile_dock ? 'tz-mobile-share-dock' : 'tz-share-links';
	$output = '<nav class="' . esc_attr( $class ) . '" aria-label="' . esc_attr__( 'Share this story', 'techzei-magazine-theme' ) . '">';

	foreach ( $destinations as $destination ) {
		$output .= sprintf(
			'<a class="tz-share-%1$s" href="%2$s" target="_blank" rel="noopener noreferrer" aria-label="%3$s">%4$s</a>',
			esc_attr( $destination ),
			esc_url( $available[ $destination ]['url'] ),
			esc_attr( sprintf( __( 'Share on %s', 'techzei-magazine-theme' ), $available[ $destination ]['label'] ) ),
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
 * When Yoast is active its own breadcrumb output is used, so presentation and
 * structured-data configuration remain in one plugin. The fallback is visible
 * HTML only and deliberately does not emit a second BreadcrumbList schema.
 *
 * @return string
 */
function techzei_tt5_visible_breadcrumbs() {
	if ( ! techzei_tt5_get_editorial_setting( 'articles.breadcrumbs', true ) || ( ! is_singular( 'post' ) && ! is_archive() ) ) {
		return '';
	}

	if ( function_exists( 'yoast_breadcrumb' ) ) {
		$yoast = yoast_breadcrumb( '', '', false );
		if ( is_string( $yoast ) && '' !== trim( $yoast ) ) {
			return '<nav class="tz-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'techzei-magazine-theme' ) . '">' . $yoast . '</nav>';
		}
	}

	$items   = array();
	$items[] = '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'techzei-magazine-theme' ) . '</a>';

	if ( is_singular( 'post' ) ) {
		$category = techzei_tt5_primary_category( get_the_ID() );
		if ( $category ) {
			$items[] = '<a href="' . esc_url( get_category_link( $category ) ) . '">' . esc_html( $category->name ) . '</a>';
		}
		$items[] = '<span aria-current="page">' . esc_html( get_the_title() ) . '</span>';
	} else {
		$items[] = '<span aria-current="page">' . esc_html( wp_strip_all_tags( get_the_archive_title() ) ) . '</span>';
	}

	return '<nav class="tz-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'techzei-magazine-theme' ) . '"><ol><li>' . implode( '</li><li aria-hidden="true" class="tz-breadcrumb-separator">→</li><li>', $items ) . '</li></ol></nav>';
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

/**
 * Return readable H2/H3 items from post content for the article TOC.
 *
 * @param int $post_id Post ID.
 * @return array
 */
function techzei_tt5_article_toc_items( $post_id ) {
	$content = (string) get_post_field( 'post_content', absint( $post_id ) );
	$matches = array();
	$items   = array();
	$used    = array();

	preg_match_all( '/<h([23])\\b([^>]*)>(.*?)<\\/h\\1>/is', $content, $matches, PREG_SET_ORDER );
	foreach ( $matches as $match ) {
		$label = trim( html_entity_decode( wp_strip_all_tags( $match[3] ), ENT_QUOTES, get_bloginfo( 'charset' ) ) );
		if ( '' === $label ) {
			continue;
		}

		$existing_id = '';
		if ( preg_match( '/\\bid\\s*=\\s*(["\\\'])(.*?)\\1/i', $match[2], $id_match ) ) {
			$existing_id = trim( html_entity_decode( $id_match[2], ENT_QUOTES, get_bloginfo( 'charset' ) ) );
		}

		$id = $existing_id ? $existing_id : sanitize_title( $label );
		$id = '' === $id ? 'section' : $id;
		if ( ! $existing_id ) {
			$used[ $id ] = isset( $used[ $id ] ) ? $used[ $id ] + 1 : 1;
			if ( $used[ $id ] > 1 ) {
				$id .= '-' . $used[ $id ];
			}
		}
		$items[] = array(
			'id'    => $id,
			'label' => $label,
			'level' => absint( $match[1] ),
		);
	}

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
		if ( ! $tags->get_attribute( 'id' ) ) {
			$tags->set_attribute( 'id', $items[ $index ]['id'] );
		}
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

	$category_ids = wp_get_post_terms(
		$post_id,
		'category',
		array(
			'fields'  => 'ids',
			'orderby' => 'term_id',
			'order'   => 'ASC',
		)
	);
	$category_ids = is_wp_error( $category_ids ) ? array() : array_slice( techzei_tt5_order_related_categories( $category_ids ), 0, 3 );

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
	$freshness_date_query = apply_filters(
		'techzei_tt5_related_freshness_date_query',
		array(
			array(
				'after'     => gmdate( 'Y-m-d', strtotime( '-5 years' ) ),
				'inclusive' => true,
			),
		),
		$post_id
	);
	$freshness_date_query = is_array( $freshness_date_query ) ? $freshness_date_query : array();

	if ( ! empty( $tag_ids ) && ! empty( $category_ids ) && count( $ids ) < $limit ) {
		$intersection_query = techzei_tt5_editorial_query(
			array(
				'posts_per_page' => $limit - count( $ids ),
				'post__not_in'   => $exclude,
				'fields'         => 'ids',
				'orderby'        => array( 'date' => 'DESC', 'ID' => 'DESC' ),
				'date_query'     => $freshness_date_query,
				'tax_query'      => array(
					'relation' => 'AND',
					array( 'taxonomy' => 'post_tag', 'field' => 'term_id', 'terms' => $tag_ids, 'operator' => 'IN', 'include_children' => false ),
					array( 'taxonomy' => 'category', 'field' => 'term_id', 'terms' => $category_ids, 'operator' => 'IN', 'include_children' => false ),
				),
			)
		);
		$intersection_matches = techzei_tt5_normalize_editorial_ids( $intersection_query->posts, $limit - count( $ids ), $exclude );
		$ids                  = array_merge( $ids, $intersection_matches );
		$exclude              = array_merge( $exclude, $intersection_matches );
	}

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
				'date_query'     => $freshness_date_query,
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
				'date_query'     => $freshness_date_query,
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

/**
 * Render current-topic stories, preferring candidates with featured images.
 *
 * The second bounded query fills an otherwise sparse category while the
 * renderer supplies a deliberate no-thumbnail state when necessary.
 *
 * @param array $atts Optional count override.
 * @return string
 */
function techzei_tt5_more_in_topic( $atts = array() ) {
	if ( ! is_singular( 'post' ) || ! techzei_tt5_get_editorial_setting( 'sidebar.more_in_topic', true ) ) {
		return '';
	}

	$post_id = get_the_ID();
	$term    = techzei_tt5_primary_category( $post_id );
	if ( ! $term ) {
		return '';
	}

	$atts  = shortcode_atts( array( 'count' => techzei_tt5_get_editorial_setting( 'sidebar.more_topic_count', 4 ) ), $atts, 'techzei_more_in_topic' );
	$limit = min( 6, max( 2, absint( $atts['count'] ) ) );
	$args  = array(
		'posts_per_page' => $limit,
		'post__not_in'   => array( $post_id ),
		'orderby'        => 'date',
		'order'          => 'DESC',
		'tax_query'      => array(
			array(
				'taxonomy'         => 'category',
				'field'            => 'term_id',
				'terms'            => array( $term->term_id ),
				'include_children' => true,
			),
		),
	);

	$with_thumbnails = techzei_tt5_editorial_query(
		wp_parse_args(
			array(
				'meta_query' => array(
					array(
						'key'     => '_thumbnail_id',
						'compare' => 'EXISTS',
					),
				),
				'fields' => 'ids',
			),
			$args
		)
	);
	$ids = techzei_tt5_normalize_editorial_ids( $with_thumbnails->posts, $limit, array( $post_id ) );

	if ( count( $ids ) < $limit ) {
		$fallback = techzei_tt5_editorial_query(
			wp_parse_args(
				array(
					'posts_per_page' => $limit - count( $ids ),
					'post__not_in'   => array_merge( array( $post_id ), $ids ),
					'fields'         => 'ids',
				),
				$args
			)
		);
		$ids = array_merge( $ids, techzei_tt5_normalize_editorial_ids( $fallback->posts, $limit - count( $ids ), array_merge( array( $post_id ), $ids ) ) );
	}

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

	$max_age = min( 10, max( 1, absint( techzei_tt5_get_editorial_setting( 'sidebar.review_max_age', 5 ) ) ) );
	$reviews = techzei_tt5_editorial_query(
		array(
			'posts_per_page' => min( 6, max( 2, absint( techzei_tt5_get_editorial_setting( 'sidebar.review_count', 4 ) ) ) ),
			'post__not_in'   => array( $current_post_id ),
			'orderby'        => 'date',
			'order'          => 'DESC',
			'tax_query'      => $review_tax_query,
			'date_query'     => array(
				array(
					'after'     => gmdate( 'Y-m-d', strtotime( '-' . $max_age . ' years' ) ),
					'inclusive' => true,
				),
			),
		)
	);

	return techzei_tt5_render_story_list( $reviews, 'tz-latest-reviews', __( 'Reviews', 'techzei-magazine-theme' ), __( 'Latest reviews', 'techzei-magazine-theme' ) );
}
add_shortcode( 'techzei_latest_reviews', 'techzei_tt5_latest_reviews' );

/** Render a small provider-free follow module for the article sidebar. */
function techzei_tt5_follow_techzei() {
	if ( ! is_singular( 'post' ) || ! techzei_tt5_get_editorial_setting( 'sidebar.follow_techzei', true ) ) {
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
