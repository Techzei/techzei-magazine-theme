<?php
/**
 * Optional WordPress integration coverage for theme code rendering.
 *
 * @package Techzei_TT5
 */

class Techzei_TT5_Code_Blocks_Test extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();
		if ( defined( 'ENLIGHTER_VERSION' ) || ! function_exists( 'techzei_tt5_code_markup' ) ) {
			$this->markTestSkipped( 'The theme code renderer is dormant while Enlighter is active.' );
		}
	}

	public function test_shortcode_markup_preserves_and_escapes_source() {
		$source = <<<'CODE'
$schema = "<name>" && '$value' `tick`;

echo 'done';
CODE;
		$source .= '  ';
		$markup = techzei_tt5_code_shortcode( array( 'lang' => 'bash' ), $source );
		$code = techzei_tt5_code_inner_html( $markup, 'code' );

		$this->assertStringContainsString( 'data-lang="bash"', $markup );
		$this->assertStringContainsString( '&lt;name&gt;', $markup );
		$this->assertSame( $source, html_entity_decode( $code, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	public function test_saved_enlighter_block_becomes_safe_theme_markup() {
		$block = techzei_tt5_code_render_block(
			'<pre class="EnlighterJSRAW" data-enlighter-language="json">{&quot;enabled&quot;:true,&quot;value&quot;:&quot;&lt;test&gt;&quot;}</pre>',
			array( 'blockName' => 'enlighter/codeblock', 'attrs' => array( 'language' => 'json' ) )
		);
		$code = techzei_tt5_code_inner_html( $block, 'code' );

		$this->assertStringContainsString( 'data-lang="json"', $block );
		$this->assertStringNotContainsString( 'EnlighterJSRAW', $block );
		$this->assertSame( '{"enabled":true,"value":"<test>"}', html_entity_decode( $code, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	public function test_the_content_shortcode_pipeline_protects_code_from_texturize_and_autop() {
		$source = <<<'CODE'
command --name "$schema" --value '<name>' && echo `ok`

second line with two spaces
CODE;
		$source .= '  ';
		$content = "[enlighter lang=\"bash\"]\n" . $source . "\n[/enlighter]";
		$html = apply_filters( 'the_content', $content );
		$code = techzei_tt5_code_inner_html( $html, 'code' );

		$this->assertStringNotContainsString( '[enlighter', $html );
		$this->assertStringNotContainsString( '<br', $html );
		$this->assertSame( $source, html_entity_decode( $code, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	public function test_shortcode_is_registered_for_excerpt_stripping() {
		$this->assertTrue( shortcode_exists( 'enlighter' ) );
		$this->assertSame( '', trim( strip_shortcodes( '[enlighter lang="bash"]echo ok[/enlighter]' ) ) );
	}

	public function test_unknown_language_is_plain_and_not_executable_html() {
		$markup = techzei_tt5_code_markup( '<script>not markup</script>', 'no-such-grammar' );

		$this->assertStringContainsString( 'data-lang="plain"', $markup );
		$this->assertStringNotContainsString( '<script>', $markup );
		$this->assertStringContainsString( '&lt;script&gt;', $markup );
	}

	public function test_inline_enlighter_code_keeps_inline_semantics() {
		$markup = techzei_tt5_code_rewrite_inline( '<p><code class="EnlighterJSRAW" data-enlighter-language="bash">echo &quot;hi&quot;</code></p>' );

		$this->assertStringContainsString( 'tz-code-inline language-bash', $markup );
		$this->assertStringNotContainsString( 'EnlighterJSRAW', $markup );
		$this->assertStringNotContainsString( 'tz-code__copy', $markup );
	}

	public function test_unrelated_legacy_pre_markup_is_unchanged() {
		$legacy = '<pre class="toolbar:2 nums:false lang:c decode:true">printf(&quot;ok&quot;);</pre>';

		$this->assertSame( $legacy, techzei_tt5_code_render_block( $legacy, array( 'blockName' => 'core/html', 'attrs' => array() ) ) );
	}
}
