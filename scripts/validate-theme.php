<?php
/**
 * Static validation for the Techzei Magazine Theme release package.
 *
 * @package Techzei_TT5
 */

declare( strict_types=1 );

$root = dirname( __DIR__ );
$failures = array();

$style = file_get_contents( $root . '/style.css' );
if ( false === $style || ! preg_match( '/^Version:\s+([^\r\n]+)/m', $style, $version_match ) ) {
	$failures[] = 'style.css is missing a Version header.';
} else {
	$version = trim( $version_match[1] );
	if ( '3.1.1' !== $version ) {
		$failures[] = 'Expected style.css version 3.1.1, found ' . $version . '.';
	}
}

$theme_json = file_get_contents( $root . '/theme.json' );
json_decode( (string) $theme_json, true );
if ( JSON_ERROR_NONE !== json_last_error() ) {
	$failures[] = 'theme.json is not valid JSON: ' . json_last_error_msg();
}

$html_files = array_merge(
	glob( $root . '/templates/*.html' ) ?: array(),
	glob( $root . '/parts/*.html' ) ?: array()
);

foreach ( $html_files as $file ) {
	$content = file_get_contents( $file );
	$open = 0;
	$close = 0;
	if ( false !== $content && preg_match_all( '/<!--\s*(\/?)wp:[\s\S]*?-->/i', $content, $matches ) ) {
		foreach ( $matches[0] as $token ) {
			if ( false !== strpos( $token, '/-->' ) ) {
				continue;
			}
			if ( preg_match( '/<!--\s*\/wp:/i', $token ) ) {
				$close++;
			} else {
				$open++;
			}
		}
	}
	if ( $open !== $close ) {
		$failures[] = 'Unbalanced block comments in ' . ltrim( str_replace( $root, '', $file ), '/' ) . ' (' . $open . ' opening, ' . $close . ' closing).';
	}
}

if ( file_exists( $root . '/parts/newsletter.html' ) ) {
	$failures[] = 'Unused newsletter template part is still present.';
}

$javascript = $root . '/assets/js/navigation-fallback.js';
if ( ! file_exists( $javascript ) ) {
	$failures[] = 'Navigation interaction script is missing.';
}

if ( empty( $failures ) ) {
	echo "Theme static validation passed.\n";
	exit( 0 );
}

fwrite( STDERR, implode( "\n", $failures ) . "\n" );
exit( 1 );
