<?php
/**
 * Optional WordPress test bootstrap.
 *
 * @package Techzei_TT5
 */

$wp_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $wp_tests_dir || ! is_file( $wp_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "Set WP_TESTS_DIR to a WordPress test suite before running integration tests.\n" );
	exit( 1 );
}

require_once $wp_tests_dir . '/includes/functions.php';

register_theme_directory( dirname( __DIR__ ) );

tests_add_filter(
	'muplugins_loaded',
	static function () {
		switch_theme( 'techzei-magazine-theme' );
	}
);

require $wp_tests_dir . '/includes/bootstrap.php';
