<?php
/**
 * Techzei Magazine Theme bootstrap.
 *
 * @package Techzei_TT5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require get_theme_file_path( 'inc/setup.php' );
require get_theme_file_path( 'inc/settings.php' );
require get_theme_file_path( 'inc/editorial.php' );
require get_theme_file_path( 'inc/legacy-shortcodes.php' );
require get_theme_file_path( 'inc/seo.php' );

/**
 * Add the body contract used to hide the optional article discovery rail on
 * mobile without changing the Site Editor's desktop composition.
 *
 * @param array $classes Existing body classes.
 * @return array
 */
function techzei_tt5_add_mobile_discovery_class( $classes ) {
	if ( is_singular( 'post' ) && function_exists( 'techzei_tt5_get_setting' ) && ! techzei_tt5_get_setting( 'sidebar', 'mobile_discovery', true ) ) {
		$classes[] = 'tz-mobile-sidebar-off';
	}

	return $classes;
}
add_filter( 'body_class', 'techzei_tt5_add_mobile_discovery_class' );
