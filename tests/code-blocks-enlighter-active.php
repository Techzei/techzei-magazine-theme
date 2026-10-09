<?php
/** Ensure the theme feature registers nothing while Enlighter is active. */

define( 'ABSPATH', __DIR__ );
define( 'ENLIGHTER_VERSION', '4.7.0' );
require dirname( __DIR__ ) . '/inc/code-blocks.php';

if ( function_exists( 'techzei_tt5_code_markup' ) || function_exists( 'techzei_tt5_code_register_compatibility' ) ) {
	fwrite( STDERR, "Theme code renderer did not stay dormant with Enlighter active.\n" );
	exit( 1 );
}

fwrite( STDOUT, "Enlighter-active guard check passed.\n" );
