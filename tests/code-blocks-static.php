<?php
/**
 * Dependency-free fixtures for code rendering. Run with: php tests/code-blocks-static.php
 * These exercise the theme callbacks with minimal WordPress API stubs; they do
 * not replace the optional WordPress integration or browser tests.
 *
 * @package Techzei_TT5
 */

define( 'ABSPATH', __DIR__ );
$GLOBALS['tz_code_test_hooks'] = array();
$GLOBALS['tz_code_test_shortcodes'] = array();
$GLOBALS['tz_code_test_styles'] = array();
$GLOBALS['tz_code_test_scripts'] = array();
$GLOBALS['tz_code_test_content'] = '';
$GLOBALS['tz_code_test_singular'] = false;
$GLOBALS['tz_code_test_code_enabled'] = true;

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['tz_code_test_hooks'][ $hook ][] = $callback;
}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['tz_code_test_hooks'][ $hook ][] = $callback;
}
function add_shortcode( $tag, $callback ) {
	$GLOBALS['tz_code_test_shortcodes'][ $tag ] = $callback;
}
function shortcode_exists( $tag ) {
	return isset( $GLOBALS['tz_code_test_shortcodes'][ $tag ] );
}
function shortcode_parse_atts( $text ) {
	$attributes = array();
	if ( preg_match_all( '/([a-zA-Z0-9_-]+)\s*=\s*(["\'])(.*?)\2/', (string) $text, $matches, PREG_SET_ORDER ) ) {
		foreach ( $matches as $match ) {
			$attributes[ strtolower( $match[1] ) ] = $match[3];
		}
	}
	return $attributes;
}
function __( $text, $domain = '' ) {
	return $text;
}
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}
function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $key ) );
}
function sanitize_html_class( $class ) {
	return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
}
function apply_filters( $hook, $value ) {
	return 'techzei_tt5_code_blocks_enabled' === $hook ? $GLOBALS['tz_code_test_code_enabled'] : $value;
}
function wp_unique_id( $prefix = '' ) {
	static $id = 0;
	return $prefix . ++$id;
}
function wp_get_theme() {
	return new class {
		public function get( $field ) {
			return '3.6.0';
		}
	};
}
function get_stylesheet_directory_uri() {
	return 'https://example.test/theme';
}
function get_stylesheet_directory() {
	return '/theme';
}
function wp_enqueue_style( $handle, $src, $dependencies = array(), $version = false ) {
	$GLOBALS['tz_code_test_styles'][ $handle ] = $src;
}
function wp_enqueue_script( $handle, $src, $dependencies = array(), $version = false, $args = array() ) {
	$GLOBALS['tz_code_test_scripts'][ $handle ] = $src;
}
function wp_add_inline_script( $handle, $data, $position = 'after' ) {}
function wp_localize_script( $handle, $object_name, $data ) {}
function wp_set_script_translations( $handle, $domain, $path = '' ) {}
function is_singular() {
	return $GLOBALS['tz_code_test_singular'];
}
function get_queried_object_id() {
	return 1;
}
function get_post_field( $field, $post_id ) {
	return $GLOBALS['tz_code_test_content'];
}

require dirname( __DIR__ ) . '/inc/code-blocks.php';

$failures = array();
function tz_code_assert( $condition, $message ) {
	global $failures;
	if ( ! $condition ) {
		$failures[] = $message;
	}
}
function tz_code_extract_text( $html ) {
	if ( ! preg_match( '~<code\b[^>]*>([\s\S]*?)</code>~i', $html, $matches ) ) {
		return false;
	}
	return html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
}

$GLOBALS['tz_code_test_singular'] = true;
$GLOBALS['tz_code_test_content'] = '<!-- wp:paragraph --><p>no code</p><!-- /wp:paragraph -->';
techzei_tt5_code_enqueue_singular_assets();
tz_code_assert( empty( $GLOBALS['tz_code_test_styles'] ) && empty( $GLOBALS['tz_code_test_scripts'] ), 'Code assets loaded for a singular page without code.' );

$source = <<<'CODE'
$schema = "<name>" && '$value' `tick`;

echo 'keep trailing spaces';
CODE;
$source .= '  ';
$content = "[enlighter lang=\"bash\"]\n" . $source . "\n[/enlighter]";
$stripped = techzei_tt5_code_strip_shortcodes( $content );
tz_code_assert( false === strpos( $stripped, '$schema' ), 'Shortcode source was not protected before text filters.' );
tz_code_assert( 1 === preg_match( '/<pre>\{\{TZCODE[A-Z0-9]+\}\}<\/pre>/', $stripped ), 'Multiline shortcode did not become a block placeholder.' );
$restored = techzei_tt5_code_restore_shortcodes( '<p>' . $stripped . '</p>' );
tz_code_assert( false !== strpos( $restored, 'class="tz-code" data-lang="bash"' ), 'Shortcode block did not render the normalized wrapper.' );
tz_code_assert( false !== strpos( $restored, 'role="group" aria-label="Code: Bash"' ) && false !== strpos( $restored, 'class="tz-code__pre language-bash" tabindex="0"' ), 'Code block is missing accessible focus or language metadata.' );
tz_code_assert( $source === tz_code_extract_text( $restored ), 'Shortcode output changed code text, special characters, blank lines or trailing spaces.' );
tz_code_assert( false === strpos( $restored, '<p><div class="tz-code"' ), 'wpautop paragraph wrapper was not removed.' );

$inline = techzei_tt5_code_shortcode( array(), 'opencode mcp list' );
tz_code_assert( false !== strpos( $inline, '<code class="tz-code-inline language-bash">' ), 'One-line shortcode without a language did not default to inline Bash.' );
tz_code_assert( false === strpos( $inline, 'tz-code__copy' ), 'Inline code received an unwanted Copy button placeholder.' );
$inline_detection = techzei_tt5_code_detect_content( '[enlighter lang="json"]{"ok":true}[/enlighter]' );
tz_code_assert( ! $inline_detection['block'] && $inline_detection['inline'] && in_array( 'json', $inline_detection['languages'], true ), 'Single-line shortcode was not detected as inline-only content.' );
$block_detection = techzei_tt5_code_detect_content( "[enlighter]\nline one\nline two\n[/enlighter]" );
tz_code_assert( $block_detection['block'] && in_array( 'bash', $block_detection['languages'], true ), 'Multiline shortcode without a language did not pre-detect default Bash.' );

$enlighter_block = techzei_tt5_code_render_block(
	'<pre class="EnlighterJSRAW" data-enlighter-language="json">{&quot;name&quot;:&quot;MCP&quot;,&quot;enabled&quot;:true}</pre>',
	array( 'blockName' => 'enlighter/codeblock', 'attrs' => array( 'language' => 'json' ) )
);
tz_code_assert( false !== strpos( $enlighter_block, 'data-lang="json"' ), 'Saved Enlighter block language was not preserved.' );
tz_code_assert( '{"name":"MCP","enabled":true}' === tz_code_extract_text( $enlighter_block ), 'Escaped JSON block text was changed.' );
tz_code_assert( false === strpos( $enlighter_block, 'EnlighterJSRAW' ), 'Theme output retained the Enlighter marker class.' );

$core_code = techzei_tt5_code_render_block(
	'<pre class="wp-block-code"><code>&lt;value&gt; &amp; &quot;quoted&quot;</code></pre>',
	array( 'blockName' => 'core/code', 'attrs' => array( 'className' => 'tz-custom', 'anchor' => 'code-example' ) )
);
tz_code_assert( false !== strpos( $core_code, 'data-lang="plain"' ), 'Unlabelled core Code did not fall back to plain text.' );
tz_code_assert( false !== strpos( $core_code, 'class="tz-code tz-custom"' ) && false !== strpos( $core_code, 'language-plain wp-block-code' ) && false !== strpos( $core_code, 'id="code-example"' ), 'Saved core block classes or anchor were discarded.' );
tz_code_assert( '<value> & "quoted"' === tz_code_extract_text( $core_code ), 'Core Code text was corrupted or double-escaped.' );

$unknown = techzei_tt5_code_markup( '<not-a-tag>', 'not-installed-language' );
tz_code_assert( false !== strpos( $unknown, 'data-lang="plain"' ), 'Unknown language did not fall back to plain text.' );
tz_code_assert( false !== strpos( $unknown, '&lt;not-a-tag&gt;' ), 'Unknown-language code was not escaped safely.' );

$preformatted = techzei_tt5_code_render_block(
	'<pre class="wp-block-preformatted">line one&#10;line two</pre>',
	array( 'blockName' => 'core/preformatted', 'attrs' => array() )
);
tz_code_assert( "line one\nline two" === tz_code_extract_text( $preformatted ), 'Core Preformatted content was not retained.' );

$legacy_pre = '<pre class="toolbar:2 nums:false lang:c decode:true">legacy &lt;pre&gt;</pre>';
tz_code_assert( $legacy_pre === techzei_tt5_code_render_block( $legacy_pre, array( 'blockName' => 'core/html', 'attrs' => array() ) ), 'Legacy Crayon/plain pre markup changed unexpectedly.' );

$GLOBALS['tz_code_test_shortcodes']['enlighter'] = 'existing-plugin-callback';
techzei_tt5_code_register_compatibility();
tz_code_assert( 'existing-plugin-callback' === $GLOBALS['tz_code_test_shortcodes']['enlighter'], 'The compatibility feature overwrote an existing Enlighter shortcode callback.' );
$hook_count = array_sum( array_map( 'count', $GLOBALS['tz_code_test_hooks'] ) );
$GLOBALS['tz_code_test_code_enabled'] = false;
techzei_tt5_code_register_compatibility();
tz_code_assert( $hook_count === array_sum( array_map( 'count', $GLOBALS['tz_code_test_hooks'] ) ), 'The kill switch did not disable compatibility hook registration.' );
$GLOBALS['tz_code_test_code_enabled'] = true;

$GLOBALS['tz_code_test_content'] = '<!-- wp:code --><pre class="wp-block-code"><code>plain</code></pre><!-- /wp:code -->';
techzei_tt5_code_enqueue_singular_assets();
tz_code_assert( isset( $GLOBALS['tz_code_test_styles']['techzei-code-blocks'] ), 'Code stylesheet was not detected for core Code.' );
tz_code_assert( isset( $GLOBALS['tz_code_test_scripts']['techzei-prism'], $GLOBALS['tz_code_test_scripts']['techzei-code-blocks'] ), 'Code scripts were not detected for core Code.' );
tz_code_assert( ! isset( $GLOBALS['tz_code_test_scripts']['techzei-prism-rust'] ), 'Unknown grammar created a remote or unshipped dependency.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . "\n" );
	}
	exit( 1 );
}

fwrite( STDOUT, "Code-block fixture checks passed.\n" );
