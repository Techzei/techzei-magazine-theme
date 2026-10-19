<?php
/**
 * Optional author-portfolio integration checks using real WordPress APIs.
 *
 * Run only against an isolated disposable site:
 * TECHZEI_WP_TEST_ROOT=/path/to/wordpress php tests/authors-wordpress.php
 * Its wp-config.php must define TECHZEI_AUTHOR_TEST_SITE=true and use the
 * 'local' environment. Created fixtures are removed in finally, even on failure.
 *
 * @package Techzei_TT5
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$root = getenv( 'TECHZEI_WP_TEST_ROOT' );
if ( ! $root || ! is_file( $root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Set TECHZEI_WP_TEST_ROOT to an isolated local WordPress install.\n" );
	exit( 2 );
}
require rtrim( $root, '/\\' ) . '/wp-load.php';
if ( 'local' !== wp_get_environment_type() || ! defined( 'TECHZEI_AUTHOR_TEST_SITE' ) || true !== TECHZEI_AUTHOR_TEST_SITE || ! function_exists( 'techzei_tt5_author_statistics' ) ) {
	fwrite( STDERR, "Author tests require an explicitly marked local test site with this theme active.\n" );
	exit( 2 );
}
require_once ABSPATH . 'wp-admin/includes/user.php';

$users = array();
$posts = array();
$terms = array();
$failures = array();
$passed = 0;
$old_user = get_current_user_id();
$old_post_data = $_POST;
$old_query = $GLOBALS['wp_query'];
$old_main_query = $GLOBALS['wp_the_query'];

function techzei_author_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$run = static function ( $name, $test ) use ( &$failures, &$passed ) {
	try {
		$test();
		++$passed;
		echo 'PASS ' . $name . "\n";
	} catch ( Throwable $error ) {
		$failures[] = $name . ': ' . $error->getMessage();
		echo 'FAIL ' . end( $failures ) . "\n";
	}
};
$make_post = static function ( $author, $status, $date, $categories = array(), $type = 'post' ) use ( &$posts ) {
	$id = wp_insert_post( array( 'post_author' => $author, 'post_type' => $type, 'post_status' => $status, 'post_date' => $date, 'post_date_gmt' => get_gmt_from_date( $date ), 'post_title' => 'Author integration fixture ' . uniqid(), 'post_content' => 'A local test article.', 'post_category' => $categories ), true );
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( $id->get_error_message() );
	}
	$posts[] = $id;
	return $id;
};
$query_author = static function ( $author, $args = array() ) {
	$query = new WP_Query();
	$GLOBALS['wp_the_query'] = $query;
	$GLOBALS['wp_query'] = $query;
	$query->query( array_merge( array( 'author' => $author, 'posts_per_page' => 20 ), $args ) );
	return $query;
};

try {
	foreach ( array( 'A', 'B', 'Empty' ) as $label ) {
		$id = wp_insert_user( array( 'user_login' => 'tz-author-test-' . strtolower( $label ) . '-' . uniqid(), 'user_pass' => wp_generate_password(), 'display_name' => 'Portfolio Author ' . $label, 'role' => 'author', 'description' => 'Biography for author ' . $label . '.' ) );
		if ( is_wp_error( $id ) ) {
			throw new RuntimeException( $id->get_error_message() );
		}
		$users[ $label ] = $id;
	}
	foreach ( array( 'apple', 'apps', 'news', 'review', 'reviews' ) as $slug ) {
		$created = wp_insert_term( 'Author test ' . $slug . ' ' . uniqid(), 'category', array( 'slug' => 'tz-author-test-' . $slug . '-' . uniqid() ) );
		if ( is_wp_error( $created ) ) {
			throw new RuntimeException( $created->get_error_message() );
		}
		$terms[ $slug ] = $created['term_id'];
	}
	// Review aliases must be their real slugs to exercise distinct-post counting.
	$review_aliases = array();
	foreach ( array( 'review', 'reviews' ) as $slug ) {
		$existing = get_term_by( 'slug', $slug, 'category' );
		if ( $existing ) {
			$review_aliases[] = (int) $existing->term_id;
		} else {
			$created = wp_insert_term( ucfirst( $slug ), 'category', array( 'slug' => $slug ) );
			$terms[ 'real-' . $slug ] = $created['term_id'];
			$review_aliases[] = (int) $created['term_id'];
		}
	}
	$a = $users['A'];
	$b = $users['B'];
	$modern = $make_post( $a, 'publish', '2026-10-14 18:00:00', array( $terms['apple'] ) );
	$oldest = $make_post( $a, 'publish', '2012-12-04 10:58:07', array( $terms['apps'] ) );
	$review = $make_post( $a, 'publish', '2025-05-02 10:00:00', array_merge( array( $terms['apple'] ), $review_aliases ) );
	$year_post = $make_post( $a, 'publish', '2025-06-01 10:00:00', array( $terms['apple'] ) );
	$make_post( $a, 'publish', '2025-07-01 10:00:00', array( $terms['apps'] ) );
	$make_post( $a, 'publish', '2025-08-01 10:00:00', array( $terms['apple'] ) );
	$draft = $make_post( $a, 'draft', '2026-01-01 10:00:00' );
	$private = $make_post( $a, 'private', '2026-01-02 10:00:00' );
	$scheduled = $make_post( $a, 'future', '2030-01-02 10:00:00' );
	$make_post( $a, 'publish', '2026-01-03 10:00:00', array(), 'page' );
	$other = $make_post( $b, 'publish', '2011-01-01 10:00:00', array( $terms['apple'] ) );

	$run( 'published-only, author-specific totals and earliest surviving article', static function () use ( $a, $oldest ) {
		$queries = isset( $GLOBALS['techzei_tt5_author_stats_queries'] ) ? $GLOBALS['techzei_tt5_author_stats_queries'] : 0;
		$stats = techzei_tt5_author_statistics( $a );
		techzei_author_test_assert( 2 === $GLOBALS['techzei_tt5_author_stats_queries'] - $queries, 'Cold author statistics must use exactly two aggregate queries.' );
		techzei_author_test_assert( 6 === $stats['total'], 'Draft/private/future/page/other-author content entered the total.' );
		techzei_author_test_assert( $oldest === $stats['first_id'] && '2012-12-04 10:58:07' === $stats['first_date'], 'The first story must use date ordering, not the smallest post ID.' );
		techzei_author_test_assert( array( 2026, 2025, 2012 ) === $stats['years'], 'Publication years are incorrect.' );
		techzei_author_test_assert( 1 === $stats['reviews'], 'Two review aliases counted one article twice.' );
	} );
	$run( 'transient cache survives a request-local/object-cache reset', static function () use ( $a ) {
		$queries = $GLOBALS['techzei_tt5_author_stats_queries'];
		$first = techzei_tt5_author_statistics( $a );
		unset( $GLOBALS['techzei_tt5_author_stats'] );
		wp_cache_flush();
		$second = techzei_tt5_author_statistics( $a );
		techzei_author_test_assert( $first === $second, 'Cached statistics changed.' );
		techzei_author_test_assert( $queries === $GLOBALS['techzei_tt5_author_stats_queries'], 'A warm transient ran aggregate queries.' );
		echo "  statistics: two cold aggregate queries, zero warm aggregate queries\n";
	} );
	$run( 'topic totals use this author rather than global term counts', static function () use ( $a, $terms ) {
		$stats = techzei_tt5_author_statistics( $a );
		$counts = array_column( $stats['topics'], 'total', 'id' );
		techzei_author_test_assert( 4 === $counts[ $terms['apple'] ], 'Another author entered a topic total.' );
	} );
	$run( 'native topic/year filters and pagination retain the author', static function () use ( $query_author, $a, $terms ) {
		$query = $query_author( $a, array( 'tz_topic' => $terms['apple'], 'tz_year' => 2025, 'posts_per_page' => 1, 'paged' => 2 ) );
		techzei_author_test_assert( 3 === (int) $query->found_posts && 3 === (int) $query->max_num_pages, 'Filtered pagination totals are incorrect.' );
		techzei_author_test_assert( 1 === count( $query->posts ) && $a === (int) $query->posts[0]->post_author, 'The filter lost the author scope.' );
		techzei_author_test_assert( 6 === techzei_tt5_author_statistics( $a )['total'], 'Filtering changed the lifetime total.' );
	} );
	$run( 'invalid compound controls are ignored and do not broaden the archive', static function () use ( $query_author, $a ) {
		$query = $query_author( $a, array( 'tz_topic' => array( 1, 2 ), 'tz_year' => '2025 OR 1=1' ) );
		techzei_author_test_assert( 6 === (int) $query->found_posts, 'Invalid controls changed the public archive.' );
	} );
	$run( 'profile fields validate ownership, status, array shape and limits', static function () use ( $a, $modern, $other, $draft, $private, $terms ) {
		$valid = techzei_tt5_validate_author_profile( array( 'role' => 'Editor-in-Chief', 'topics' => array( $terms['apple'] ), 'stories' => array( get_permalink( $modern ) ) ), $a );
		techzei_author_test_assert( ! is_wp_error( $valid ) && array( $modern ) === $valid['posts'], 'A valid own article URL was rejected.' );
		foreach ( array( $other, $draft, $private ) as $invalid ) {
			techzei_author_test_assert( is_wp_error( techzei_tt5_validate_author_profile( array( 'stories' => array( (string) $invalid ) ), $a ) ), 'An inaccessible or foreign article was accepted.' );
		}
		techzei_author_test_assert( is_wp_error( techzei_tt5_validate_author_profile( array( 'stories' => array( 'https://example.test/not-a-post/' ) ), $a ) ), 'An unresolved URL became the global Loop post.' );
		techzei_author_test_assert( is_wp_error( techzei_tt5_validate_author_profile( array( 'role' => array( 'bad' ) ), $a ) ), 'A malformed role was accepted.' );
		techzei_author_test_assert( is_wp_error( techzei_tt5_validate_author_profile( array( 'stories' => array_fill( 0, 4, (string) $modern ) ), $a ) ), 'More than three selected stories were accepted.' );
	} );
	$run( 'profile saving enforces nonce/capability and preserves data on errors', static function () use ( $a, $b, $modern ) {
		wp_set_current_user( $a );
		$_POST = array( 'techzei_author_profile_nonce' => wp_create_nonce( 'techzei_author_profile_' . $a ), 'techzei_author_portfolio' => array( 'role' => 'Contributing Writer', 'stories' => array( (string) $modern ) ) );
		techzei_tt5_save_author_profile( $a );
		$before = techzei_tt5_author_profile( $a );
		techzei_author_test_assert( 'Contributing Writer' === $before['role'], 'An authorized profile did not save.' );
		$_POST['techzei_author_profile_nonce'] = 'invalid';
		$_POST['techzei_author_portfolio']['role'] = 'Changed';
		techzei_tt5_save_author_profile( $a );
		techzei_author_test_assert( $before === techzei_tt5_author_profile( $a ), 'Invalid nonce changed profile data.' );
		wp_set_current_user( $b );
		$_POST['techzei_author_profile_nonce'] = wp_create_nonce( 'techzei_author_profile_' . $a );
		techzei_tt5_save_author_profile( $a );
		techzei_author_test_assert( $before === techzei_tt5_author_profile( $a ), 'An author could edit another author profile.' );
	} );
	$run( 'header uses the queried author and has one H1', static function () use ( $query_author, $a, $b ) {
		$query_author( $a );
		wp_set_current_user( $b );
		$html = techzei_tt5_render_author_header();
		techzei_author_test_assert( false !== strpos( $html, 'Portfolio Author A' ) && false === strpos( $html, 'Portfolio Author B' ), 'Header used the signed-in user.' );
		techzei_author_test_assert( 1 === substr_count( $html, '<h1 ' ), 'Header must have one H1.' );
		techzei_author_test_assert( false === strpos( $html, 'Administrator' ), 'Permission roles became public editorial roles.' );
	} );
	$run( 'public author links avoid self-links and duplicate biography links', static function () use ( $query_author, $a ) {
		wp_update_user( array( 'ID' => $a, 'user_url' => get_author_posts_url( $a ) ) );
		update_user_meta( $a, 'twitter', 'jashjacob' );
		update_user_meta( $a, 'description', 'Follow this author on <a href="https://x.com/jashjacob">X</a>.' );
		$query_author( $a );
		$html = techzei_tt5_render_author_header();
		techzei_author_test_assert( false === strpos( $html, 'Personal website' ) && 1 === substr_count( $html, 'https://x.com/jashjacob' ), 'A self-link or duplicate X action appeared.' );
		wp_update_user( array( 'ID' => $a, 'user_url' => 'https://example.org/portfolio/' ) );
		$query_author( $a );
		techzei_author_test_assert( false !== strpos( techzei_tt5_render_author_header(), 'Personal website' ), 'A distinct personal website was hidden.' );
	} );
	$run( 'first archived story has a section heading and a nested story heading', static function () use ( $query_author, $a, $oldest ) {
		$query_author( $a );
		$html = techzei_tt5_render_author_topics();
		techzei_author_test_assert( false !== strpos( $html, '<h2 class="tz-section-kicker" id="tz-author-first-story-heading">' ) && false !== strpos( $html, '<h3><a href="' . get_permalink( $oldest ) ), 'First story heading hierarchy is incorrect.' );
	} );
	$run( 'block-template rendering preserves profile grid children without wpautop artifacts', static function () use ( $query_author, $a ) {
		$query_author( $a );
		$block = '<!-- wp:shortcode -->[techzei_author_header]<!-- /wp:shortcode -->';
		$direct = do_blocks( $block );
		$template = do_blocks( do_shortcode( $block ) );
		techzei_author_test_assert( $direct === $template, 'Direct block and full template rendering differ.' );
		$document = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$document->loadHTML( '<?xml encoding="utf-8" ?>' . $template );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		$xpath = new DOMXPath( $document );
		techzei_author_test_assert( 2 === $xpath->query( '//section[contains(@class,"tz-author-header")]/*' )->length, 'A formatter inserted extra grid children into the profile.' );
		$ordinary = do_blocks( '<!-- wp:shortcode -->Ordinary shortcode block<!-- /wp:shortcode -->' );
		techzei_author_test_assert( false !== strpos( $ordinary, '<p>' ), 'The author guard changed ordinary Shortcode block formatting.' );
	} );
	$run( 'selected work rechecks ownership/status and supplies a no-thumbnail state', static function () use ( $query_author, $a, $modern, $other, $private ) {
		update_user_meta( $a, '_techzei_author_portfolio', array( 'posts' => array( $modern, $other, $private ) ) );
		$query_author( $a );
		$html = techzei_tt5_render_author_selected_work();
		techzei_author_test_assert( false !== strpos( $html, get_permalink( $modern ) ), 'Own selected story was omitted.' );
		techzei_author_test_assert( false === strpos( $html, get_permalink( $other ) ) && false === strpos( $html, get_permalink( $private ) ), 'Stored metadata exposed inaccessible work.' );
		techzei_author_test_assert( false !== strpos( $html, 'tz-author-work-placeholder' ), 'The no-thumbnail state is missing.' );
		$query_author( $a, array( 'paged' => 2, 'posts_per_page' => 1 ) );
		techzei_author_test_assert( '' === techzei_tt5_render_author_selected_work(), 'Selected work repeats on archive page two.' );
	} );
	$run( 'selected image keeps meaningful alt text and lazy/low image priority', static function () use ( $a, $modern, $query_author, &$posts ) {
		$upload = wp_upload_bits( 'author-test-' . uniqid() . '.png', null, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jAFcAAAAASUVORK5CYII=' ) );
		techzei_author_test_assert( ! $upload['error'], 'Could not create the local attachment fixture.' );
		$attachment = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'Fixture' ), $upload['file'], $modern );
		$posts[] = $attachment;
		wp_update_attachment_metadata( $attachment, array( 'width' => 1, 'height' => 1, 'file' => _wp_relative_upload_path( $upload['file'] ) ) );
		update_post_meta( $attachment, '_wp_attachment_image_alt', 'Author’s device & “test”' );
		set_post_thumbnail( $modern, $attachment );
		$query_author( $a );
		$tags = new WP_HTML_Tag_Processor( techzei_tt5_render_author_selected_work() );
		techzei_author_test_assert( $tags->next_tag( 'IMG' ), 'Selected image is missing.' );
		techzei_author_test_assert( 'Author’s device & “test”' === $tags->get_attribute( 'alt' ), 'Meaningful image alt text changed.' );
		techzei_author_test_assert( 'lazy' === $tags->get_attribute( 'loading' ) && 'high' !== $tags->get_attribute( 'fetchpriority' ), 'Selected card has an invalid image priority.' );
	} );
	$run( 'empty profiles omit statistics and selections without losing identity', static function () use ( $query_author, $users ) {
		$query_author( $users['Empty'] );
		techzei_author_test_assert( false !== strpos( techzei_tt5_render_author_header(), 'Portfolio Author Empty' ), 'Empty author identity vanished.' );
		techzei_author_test_assert( '' === techzei_tt5_render_author_statistics() && '' === techzei_tt5_render_author_selected_work(), 'An empty author has misleading work statistics.' );
	} );
	$run( 'status transitions invalidate cached public counts', static function () use ( $a, $draft ) {
		$before = techzei_tt5_author_statistics( $a )['total'];
		wp_update_post( array( 'ID' => $draft, 'post_status' => 'publish' ) );
		techzei_author_test_assert( $before + 1 === techzei_tt5_author_statistics( $a )['total'], 'Publishing left a stale count.' );
		wp_update_post( array( 'ID' => $draft, 'post_status' => 'private' ) );
		techzei_author_test_assert( $before === techzei_tt5_author_statistics( $a )['total'], 'Making a story private left it in the public count.' );
	} );
	$run( 'author reassignment invalidates both authors', static function () use ( $a, $b, $modern ) {
		$first = techzei_tt5_author_statistics( $a )['total'];
		$second = techzei_tt5_author_statistics( $b )['total'];
		wp_update_post( array( 'ID' => $modern, 'post_author' => $b ) );
		techzei_author_test_assert( $first - 1 === techzei_tt5_author_statistics( $a )['total'] && $second + 1 === techzei_tt5_author_statistics( $b )['total'], 'Reassignment left old/new author data stale.' );
	} );
	$run( 'direct relationship removal invalidates category totals', static function () use ( $a, $terms, $year_post ) {
		$before = array_column( techzei_tt5_author_statistics( $a )['topics'], 'total', 'id' );
		wp_remove_object_terms( $year_post, $terms['apple'], 'category' );
		$after = array_column( techzei_tt5_author_statistics( $a )['topics'], 'total', 'id' );
		techzei_author_test_assert( $before[ $terms['apple'] ] - 1 === $after[ $terms['apple'] ], 'Relationship removal retained a stale topic count.' );
	} );
	$run( 'permanent deletion invalidates before the post disappears', static function () use ( $a, $oldest ) {
		$before = techzei_tt5_author_statistics( $a );
		wp_delete_post( $oldest, true );
		$after = techzei_tt5_author_statistics( $a );
		techzei_author_test_assert( $before['total'] - 1 === $after['total'] && $oldest !== $after['first_id'], 'Deletion left a stale total or first story.' );
	} );
} catch ( Throwable $error ) {
	$failures[] = 'Fixture setup: ' . $error->getMessage();
} finally {
	$_POST = $old_post_data;
	$GLOBALS['wp_query'] = $old_query;
	$GLOBALS['wp_the_query'] = $old_main_query;
	wp_set_current_user( $old_user );
	foreach ( array_reverse( $posts ) as $post_id ) {
		if ( 'attachment' === get_post_type( $post_id ) ) {
			wp_delete_attachment( $post_id, true );
		} else {
			wp_delete_post( $post_id, true );
		}
	}
	foreach ( $users as $user_id ) {
		wp_delete_user( $user_id );
	}
	foreach ( $terms as $term_id ) {
		wp_delete_term( $term_id, 'category' );
	}
}

printf( "%d integration checks passed; %d failed.\n", $passed, count( $failures ) );
if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}
