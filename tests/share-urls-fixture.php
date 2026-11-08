<?php
/**
 * Exercise the actual share URL builder without a WordPress test bootstrap.
 * Run with: php tests/share-urls-fixture.php
 *
 * @package Techzei_TT5
 */

define( 'ABSPATH', __DIR__ . '/' );

function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function add_shortcode( ...$args ) {}
function get_permalink() {
	return 'https://techzei.test/canva-craft/';
}
function get_the_title() {
	return $GLOBALS['techzei_share_fixture_title'];
}
function get_bloginfo( $show ) {
	return 'UTF-8';
}
function wp_strip_all_tags( $text ) {
	return strip_tags( $text );
}

require dirname( __DIR__ ) . '/inc/editorial.php';

$GLOBALS['techzei_share_fixture_title'] = 'Canva&#8217;s &amp; Craft <em>guide</em>';
$data                                  = techzei_tt5_share_destination_data();
$expected                              = 'Canva’s & Craft guide';

foreach ( array( 'x' => 'text', 'reddit' => 'title' ) as $destination => $parameter ) {
	parse_str( parse_url( $data[ $destination ]['url'], PHP_URL_QUERY ), $query );
	if ( $expected !== $query[ $parameter ] ) {
		fwrite( STDERR, "Incorrect {$destination} share title.\n" );
		exit( 1 );
	}
}

parse_str( parse_url( $data['whatsapp']['url'], PHP_URL_QUERY ), $whatsapp_query );
if ( $expected . ' https://techzei.test/canva-craft/' !== $whatsapp_query['text'] ) {
	fwrite( STDERR, "Incorrect WhatsApp share text.\n" );
	exit( 1 );
}

fwrite( STDOUT, "Share URL fixture checks passed.\n" );
