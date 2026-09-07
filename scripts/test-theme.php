<?php
/**
 * Focused, dependency-free quality checks for the theme and release gate.
 *
 * @package Techzei_TT5
 */

declare( strict_types=1 );

require_once __DIR__ . '/validate-theme.php';

function techzei_quality_assert( bool $condition, string $message, array &$failures ): void {
	if ( ! $condition ) {
		$failures[] = $message;
	}
}

function techzei_quality_read( string $path, array &$failures ): string {
	$content = file_get_contents( $path );
	techzei_quality_assert( false !== $content, 'Unable to read ' . $path . '.', $failures );

	return false === $content ? '' : $content;
}

function techzei_quality_image_conflicts( string $html ): array {
	$failures = array();
	preg_match_all( '/<img\b[^>]*>/i', $html, $images );

	foreach ( $images[0] as $image ) {
		$loading  = '';
		$priority = '';
		if ( preg_match( '/\bloading\s*=\s*["\']([^"\']*)["\']/i', $image, $match ) ) {
			$loading = strtolower( $match[1] );
		}
		if ( preg_match( '/\bfetchpriority\s*=\s*["\']([^"\']*)["\']/i', $image, $match ) ) {
			$priority = strtolower( $match[1] );
		}
		if ( 'lazy' === $loading && 'high' === $priority ) {
			$failures[] = 'Image has both loading="lazy" and fetchpriority="high".';
		}
	}

	return $failures;
}

$root     = dirname( __DIR__ );
$failures = array();

$valid_fixture = techzei_quality_read( __DIR__ . '/fixtures/blocks/valid-nested.html', $failures );
techzei_quality_assert( empty( techzei_validate_block_markup( $valid_fixture, 'valid fixture' ) ), 'Valid nested block fixture was rejected.', $failures );

foreach ( array( 'mismatched-nesting.html', 'malformed-attributes.html' ) as $fixture ) {
	$content = techzei_quality_read( __DIR__ . '/fixtures/blocks/' . $fixture, $failures );
	techzei_quality_assert( ! empty( techzei_validate_block_markup( $content, $fixture ) ), 'Invalid block fixture was accepted: ' . $fixture, $failures );
}

$settings = techzei_quality_read( $root . '/inc/settings.php', $failures );
foreach ( array(
	'TECHZEI_TT5_SETTINGS_OPTION',
	"'header'   => array(",
	"'sticky_mobile'",
	"'homepage' => array(",
	"'headline_mode'",
	"'articles' => array(",
	"'default_layout'",
	"'related_stories'",
	"'share_destinations'",
	"'sidebar'  => array(",
	"'review_category'",
	"'sanitize_callback' => 'techzei_tt5_settings_sanitize'",
	"add_action( 'admin_init', 'techzei_tt5_settings_register' )",
	"add_action( 'admin_menu', 'techzei_tt5_settings_menu' )",
	'does not reset templates',
) as $needle ) {
	techzei_quality_assert( false !== strpos( $settings, $needle ), 'Settings contract is missing: ' . $needle, $failures );
}
$resolver_start = strpos( $settings, 'function techzei_tt5_get_settings' );
$resolver_end   = strpos( $settings, 'function techzei_tt5_get_setting( ', (int) $resolver_start );
$resolver       = false !== $resolver_start && false !== $resolver_end ? substr( $settings, $resolver_start, $resolver_end - $resolver_start ) : '';
techzei_quality_assert( '' !== $resolver && 1 !== preg_match( '/\b(?:add|update|delete)_option\s*\(/', $resolver ), 'Settings resolver must remain read-only.', $failures );

$setup = techzei_quality_read( $root . '/inc/setup.php', $failures );
foreach ( array( 'WP_HTML_Tag_Processor', "'fetchpriority'", "remove_attribute( 'fetchpriority' )", "'article-hero'", "'homepage-lead'" ) as $needle ) {
	techzei_quality_assert( false !== strpos( $setup, $needle ), 'Image handling contract is missing: ' . $needle, $failures );
}
techzei_quality_assert( 1 === preg_match( '/if \( \'lazy\' === strtolower\(.*?remove_attribute\( \'fetchpriority\' \)/s', $setup ), 'Image handling must remove high priority from lazy images.', $failures );
$image_source = $setup . techzei_quality_read( $root . '/inc/editorial.php', $failures );
techzei_quality_assert( 0 === preg_match( '/<img\b[^>]*loading=["\']lazy["\'][^>]*fetchpriority=["\']high["\']/i', $image_source ), 'Theme source contains a lazy/high image combination.', $failures );
$valid_images = techzei_quality_read( __DIR__ . '/fixtures/images-valid.html', $failures );
$invalid_images = techzei_quality_read( __DIR__ . '/fixtures/images-invalid.html', $failures );
techzei_quality_assert( empty( techzei_quality_image_conflicts( $valid_images ) ), 'Valid image invariant fixture was rejected.', $failures );
techzei_quality_assert( ! empty( techzei_quality_image_conflicts( $invalid_images ) ), 'Invalid image invariant fixture was accepted.', $failures );

$legacy = techzei_quality_read( $root . '/inc/legacy-shortcodes.php', $failures );
$legacy_fixture = json_decode( techzei_quality_read( __DIR__ . '/fixtures/legacy-shortcodes.json', $failures ), true );
techzei_quality_assert( JSON_ERROR_NONE === json_last_error() && is_array( $legacy_fixture ), 'Legacy shortcode fixture is not valid JSON.', $failures );
foreach ( $legacy_fixture['shortcodes'] ?? array() as $shortcode ) {
	techzei_quality_assert( false !== strpos( $legacy, "add_shortcode( '" . $shortcode . "'" ), 'Legacy shortcode registration is missing: ' . $shortcode, $failures );
}
foreach ( array( 'shortcode_atts', 'shortcode_unautop', 'wp_kses_post', 'esc_url', 'sanitize_key', 'noopener noreferrer' ) as $needle ) {
	techzei_quality_assert( false !== strpos( $legacy, $needle ), 'Legacy shortcode safety contract is missing: ' . $needle, $failures );
}
techzei_quality_assert( count( $legacy_fixture['cases'] ?? array() ) >= 5, 'Legacy shortcode fixture coverage is too small.', $failures );

$theme_json = json_decode( techzei_quality_read( $root . '/theme.json', $failures ), true );
techzei_quality_assert( JSON_ERROR_NONE === json_last_error() && is_array( $theme_json ), 'Theme metadata is not valid JSON for layout checks.', $failures );
$template_names = array();
foreach ( $theme_json['customTemplates'] ?? array() as $template ) {
	if ( isset( $template['name'] ) ) {
		$template_names[] = $template['name'];
	}
}
techzei_quality_assert( in_array( 'single-with-sidebar', $template_names, true ) && in_array( 'single-no-sidebar', $template_names, true ), 'Both explicit article templates must remain registered.', $failures );
techzei_quality_assert( false !== strpos( $setup, 'get_page_template_slug' ) && false !== strpos( $setup, "! \$selected_template || 'default' === \$selected_template" ), 'Layout precedence check is missing the explicit-template guard.', $failures );

$release_workflow = techzei_quality_read( $root . '/.github/workflows/release-theme.yml', $failures );
$quality_workflow = techzei_quality_read( $root . '/.github/workflows/quality.yml', $failures );
techzei_quality_assert( false !== strpos( $release_workflow, "types: [published]" ), 'Release packaging must remain limited to published releases.', $failures );
techzei_quality_assert( false !== strpos( $release_workflow, 'gh release upload' ), 'Published release workflow must attach the ZIP.', $failures );
techzei_quality_assert( false !== strpos( $release_workflow, 'package-theme.sh' ), 'Release workflow must use the packaging integrity helper.', $failures );
techzei_quality_assert( false !== strpos( $quality_workflow, 'push:' ) && false !== strpos( $quality_workflow, 'pull_request:' ), 'Quality workflow must run on pushes and pull requests.', $failures );
techzei_quality_assert( is_file( $root . '/scripts/package-theme.sh' ), 'Packaging helper is missing.', $failures );

if ( empty( $failures ) ) {
	echo "Focused theme quality checks passed.\n";
	exit( 0 );
}

fwrite( STDERR, implode( "\n", $failures ) . "\n" );
exit( 1 );
