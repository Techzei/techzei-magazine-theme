<?php
/**
 * Focused static checks for the Techzei settings workstream.
 *
 * @package Techzei_TT5
 */

declare( strict_types=1 );

$root     = dirname( __DIR__ );
$settings = file_get_contents( $root . '/inc/settings.php' );
$docs     = file_get_contents( $root . '/docs/3.4.0-discovery.md' );
$failures = array();

if ( false === $settings ) {
	$failures[] = 'inc/settings.php could not be read.';
} else {
	$required_fragments = array(
		'cache resolver'       => 'function techzei_tt5_settings_resolved_values()',
		'cache invalidation'   => 'function techzei_tt5_settings_invalidate_cache()',
		'previous-schema compatibility' => '$version < 1 || $version > TECHZEI_TT5_SETTINGS_VERSION',
		'mobile sidebar key'   => "'mobile_discovery' => true",
		'related mode key'     => "'related_mode'         => 'automatic'",
		'breadcrumb key'       => "'breadcrumbs'          => true",
		'toc key'              => "'toc'                  => true",
		'mobile share dock key' => "'mobile_share_dock'    => true",
		'logo alignment key' => "'logo_alignment' => 'left'",
		'logo alignment allowlist' => "'logo_alignment' => array( 'left', 'center' )",
		'more-in-topic key'    => "'more_in_topic'  => true",
		'newsletter slot key'  => "'newsletter_slot' => false",
		'review freshness key' => "'review_max_age' => 5",
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

if ( false === $docs || false === strpos( (string) $docs, 'more_in_topic' ) || false === strpos( (string) $docs, 'breadcrumbs' ) || false === strpos( (string) $docs, 'mobile_share_dock' ) ) {
	$failures[] = '3.4.0 settings documentation is missing the new schema values.';
}

if ( empty( $failures ) ) {
	echo "Settings static validation passed.\n";
	exit( 0 );
}

fwrite( STDERR, implode( "\n", $failures ) . "\n" );
exit( 1 );
