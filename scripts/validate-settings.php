<?php
/**
 * Focused static checks for the Techzei settings workstream.
 *
 * @package Techzei_TT5
 */

declare( strict_types=1 );

$root     = dirname( __DIR__ );
$settings = file_get_contents( $root . '/inc/settings.php' );
$docs     = file_get_contents( $root . '/docs/3.2.0-settings.md' );
$failures = array();

if ( false === $settings ) {
	$failures[] = 'inc/settings.php could not be read.';
} else {
	$required_fragments = array(
		'cache resolver'       => 'function techzei_tt5_settings_resolved_values()',
		'cache invalidation'   => 'function techzei_tt5_settings_invalidate_cache()',
		'mobile sidebar key'   => "'mobile_discovery' => true",
		'related mode key'     => "'related_mode'         => 'automatic'",
		'related mode allowlist' => "'related_mode'   => array( 'automatic', 'editorial-first' )",
		'added-option hook'    => "added_option_' . TECHZEI_TT5_SETTINGS_OPTION",
		'updated-option hook'  => "updated_option_' . TECHZEI_TT5_SETTINGS_OPTION",
		'deleted-option hook'  => "deleted_option_' . TECHZEI_TT5_SETTINGS_OPTION",
		'read-only template API' => 'get_block_templates',
		'scoped reset'         => 'delete_option( TECHZEI_TT5_SETTINGS_OPTION )',
	);

	foreach ( $required_fragments as $label => $fragment ) {
		if ( false === strpos( $settings, $fragment ) ) {
			$failures[] = 'Missing ' . $label . ' implementation marker.';
		}
	}

	if ( false !== strpos( $settings, 'wp_delete_post' ) || false !== strpos( $settings, 'wp_update_post' ) ) {
		$failures[] = 'Settings module contains a template/content mutation call.';
	}
}

if ( false === $docs || false === strpos( (string) $docs, 'mobile_discovery' ) || false === strpos( (string) $docs, 'related_mode' ) ) {
	$failures[] = '3.2.0 settings documentation is missing the new schema values.';
}

if ( empty( $failures ) ) {
	echo "Settings static validation passed.\n";
	exit( 0 );
}

fwrite( STDERR, implode( "\n", $failures ) . "\n" );
exit( 1 );
