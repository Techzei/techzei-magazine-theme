<?php
/**
 * Lightweight editorial guardrail checks that do not require a WordPress test
 * bootstrap. Run with: php tests/editorial-static.php
 *
 * @package Techzei_TT5
 */

$theme_dir = dirname( __DIR__ );
$editorial = file_get_contents( $theme_dir . '/inc/editorial.php' );

if ( false === $editorial ) {
	fwrite( STDERR, "Unable to read inc/editorial.php\n" );
	exit( 1 );
}

$required_editorial_markers = array(
	"function techzei_tt5_related_story_ids",
	"'post_tag'",
	"'include_children' => false",
	"'post__not_in'",
	"'orderby'       => array",
	"'techzei_tt5_related_story_ids'",
	"add_shortcode( 'techzei_related_stories'",
);

foreach ( $required_editorial_markers as $marker ) {
	if ( false === strpos( $editorial, $marker ) ) {
		fwrite( STDERR, "Missing editorial marker: {$marker}\n" );
		exit( 1 );
	}
}

$patterns = array(
	'review-verdict.php'          => array( 'tz-review-verdict', 'esc_html_e' ),
	'product-specifications.php' => array( 'wp:table', 'scope="col"', 'overflow-x:auto' ),
	'related-reading.php'         => array( 'tz-related-reading', '[techzei_related_stories heading="0"]' ),
);

foreach ( $patterns as $pattern => $markers ) {
	$contents = file_get_contents( $theme_dir . '/patterns/' . $pattern );
	if ( false === $contents ) {
		fwrite( STDERR, "Unable to read pattern: {$pattern}\n" );
		exit( 1 );
	}
	foreach ( $markers as $marker ) {
		if ( false === strpos( $contents, $marker ) ) {
			fwrite( STDERR, "Missing pattern marker in {$pattern}: {$marker}\n" );
			exit( 1 );
		}
	}
}

fwrite( STDOUT, "Editorial static checks passed.\n" );
