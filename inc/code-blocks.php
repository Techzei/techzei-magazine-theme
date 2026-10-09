<?php
/**
 * Theme-owned rendering for Enlighter shortcodes and saved code blocks.
 *
 * @package Techzei_TT5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Enlighter owns rendering while active. Leaving it installed makes rollback instant.
if ( defined( 'ENLIGHTER_VERSION' ) ) {
	return;
}

// Conditional declarations matter here: PHP registers unconditional functions
// at compile time even when a top-level return would make the code unreachable.
if ( ! defined( 'ENLIGHTER_VERSION' ) ) {

/**
 * Return the supported Enlighter-to-Prism language map.
 * Grammars not shipped with the theme intentionally fall back to plain text.
 *
 * @return array
 */
function techzei_tt5_code_languages() {
	return array(
		'bash'       => array( 'prism' => 'bash', 'label' => __( 'Bash', 'techzei-magazine-theme' ), 'file' => '', 'deps' => array() ),
		'shell'      => array( 'prism' => 'bash', 'label' => __( 'Bash', 'techzei-magazine-theme' ), 'file' => '', 'deps' => array() ),
		'sh'         => array( 'prism' => 'bash', 'label' => __( 'Bash', 'techzei-magazine-theme' ), 'file' => '', 'deps' => array() ),
		'json'       => array( 'prism' => 'json', 'label' => __( 'JSON', 'techzei-magazine-theme' ), 'file' => '', 'deps' => array() ),
		'js'         => array( 'prism' => 'javascript', 'label' => __( 'JavaScript', 'techzei-magazine-theme' ), 'file' => '', 'deps' => array() ),
		'javascript' => array( 'prism' => 'javascript', 'label' => __( 'JavaScript', 'techzei-magazine-theme' ), 'file' => '', 'deps' => array() ),
		'html'       => array( 'prism' => 'markup', 'label' => __( 'HTML', 'techzei-magazine-theme' ), 'file' => '', 'deps' => array() ),
		'xml'        => array( 'prism' => 'markup', 'label' => __( 'XML', 'techzei-magazine-theme' ), 'file' => '', 'deps' => array() ),
		'markup'     => array( 'prism' => 'markup', 'label' => __( 'Markup', 'techzei-magazine-theme' ), 'file' => '', 'deps' => array() ),
		'ts'         => array( 'prism' => 'typescript', 'label' => __( 'TypeScript', 'techzei-magazine-theme' ), 'file' => 'typescript', 'deps' => array( 'javascript' ) ),
		'typescript' => array( 'prism' => 'typescript', 'label' => __( 'TypeScript', 'techzei-magazine-theme' ), 'file' => 'typescript', 'deps' => array( 'javascript' ) ),
		'css'        => array( 'prism' => 'css', 'label' => __( 'CSS', 'techzei-magazine-theme' ), 'file' => 'css', 'deps' => array( 'markup' ) ),
		'python'     => array( 'prism' => 'python', 'label' => __( 'Python', 'techzei-magazine-theme' ), 'file' => 'python', 'deps' => array() ),
		'py'         => array( 'prism' => 'python', 'label' => __( 'Python', 'techzei-magazine-theme' ), 'file' => 'python', 'deps' => array() ),
		'yaml'       => array( 'prism' => 'yaml', 'label' => __( 'YAML', 'techzei-magazine-theme' ), 'file' => 'yaml', 'deps' => array() ),
		'yml'        => array( 'prism' => 'yaml', 'label' => __( 'YAML', 'techzei-magazine-theme' ), 'file' => 'yaml', 'deps' => array() ),
		'diff'       => array( 'prism' => 'diff', 'label' => __( 'Diff', 'techzei-magazine-theme' ), 'file' => 'diff', 'deps' => array() ),
		'ini'        => array( 'prism' => 'ini', 'label' => __( 'INI', 'techzei-magazine-theme' ), 'file' => 'ini', 'deps' => array() ),
		'php'        => array( 'prism' => 'php', 'label' => __( 'PHP', 'techzei-magazine-theme' ), 'file' => 'php', 'deps' => array( 'markup-templating' ) ),
	);
}

/**
 * Whether the theme code feature is enabled.
 *
 * @return bool
 */
function techzei_tt5_code_blocks_enabled() {
	return (bool) apply_filters( 'techzei_tt5_code_blocks_enabled', true );
}

/**
 * Resolve a requested language to a shipped Prism grammar or plain text.
 *
 * @param string $language Requested language.
 * @return array
 */
function techzei_tt5_code_language( $language ) {
	$language = sanitize_key( strtolower( (string) $language ) );
	$languages = techzei_tt5_code_languages();

	if ( isset( $languages[ $language ] ) ) {
		return $languages[ $language ];
	}

	return array(
		'prism' => 'plain',
		'label' => __( 'Code', 'techzei-magazine-theme' ),
		'file'  => '',
	);
}

/**
 * Read an attribute from a named HTML tag with the WordPress parser where available.
 *
 * @param string $html HTML fragment.
 * @param string $tag  Tag name.
 * @param string $name Attribute name.
 * @return string
 */
function techzei_tt5_code_get_attribute( $html, $tag, $name ) {
	if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
		$processor = new WP_HTML_Tag_Processor( $html );
		if ( $processor->next_tag( array( 'tag_name' => strtoupper( $tag ) ) ) ) {
			$value = $processor->get_attribute( $name );
			return is_string( $value ) ? $value : '';
		}
	}

	$pattern = '~<' . preg_quote( $tag, '~' ) . '\b([^>]*)>~i';
	if ( preg_match( $pattern, $html, $tag_match ) && preg_match( '~\b' . preg_quote( $name, '~' ) . '\s*=\s*(["\'])(.*?)\1~i', $tag_match[1], $attribute_match ) ) {
		return html_entity_decode( $attribute_match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	return '';
}

/**
 * Extract escaped inner HTML for a simple saved code element.
 *
 * @param string $html HTML fragment.
 * @param string $tag  Expected element.
 * @return string|false Escaped inner content, or false when absent.
 */
function techzei_tt5_code_inner_html( $html, $tag ) {
	if ( preg_match( '~<' . preg_quote( $tag, '~' ) . '\b[^>]*>([\s\S]*?)</' . preg_quote( $tag, '~' ) . '\s*>~i', $html, $match ) ) {
		return $match[1];
	}

	return false;
}

/**
 * Decode one saved-HTML layer and escape it again for safe code output.
 *
 * @param string $saved_html Escaped code saved in a block.
 * @return string
 */
function techzei_tt5_code_escape_saved_html( $saved_html ) {
	return esc_html( html_entity_decode( (string) $saved_html, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
}

/**
 * Keep safe class tokens from saved core blocks on the generated wrapper.
 *
 * @param string $class_names Whitespace-separated class names.
 * @return array
 */
function techzei_tt5_code_safe_classes( $class_names ) {
	$classes = preg_split( '/\s+/', trim( (string) $class_names ) );
	$classes = array_filter( array_map( 'sanitize_html_class', $classes ) );
	return array_values( array_unique( $classes ) );
}

/**
 * Enqueue only the local code assets required by content that renders code.
 *
 * @param string $language Requested language.
 * @param bool   $with_copy Whether this is a code block rather than inline code.
 * @param bool   $enqueue_runtime Whether to enqueue the shared copy runtime now.
 * @return void
 */
function techzei_tt5_code_require_assets( $language, $with_copy = true, $enqueue_runtime = true ) {
	static $style_loaded = false;
	static $script_loaded = false;
	static $prism_configured = false;
	static $grammars_loaded = array();

	if ( ! techzei_tt5_code_blocks_enabled() ) {
		return;
	}

	$theme = wp_get_theme();
	$version = $theme->get( 'Version' );
	$resolved = techzei_tt5_code_language( $language );

	if ( ! $style_loaded ) {
		wp_enqueue_style(
			'techzei-code-blocks',
			get_stylesheet_directory_uri() . '/assets/css/code-blocks.css',
			array( 'techzei-magazine-theme-article' ),
			$version
		);
		$style_loaded = true;
	}

	if ( ! $with_copy ) {
		return;
	}

	wp_enqueue_script(
		'techzei-prism',
		get_stylesheet_directory_uri() . '/assets/vendor/prism/prism-core-bundle.min.js',
		array(),
		$version,
		array( 'in_footer' => true, 'strategy' => 'defer' )
	);
	if ( ! $prism_configured ) {
		wp_add_inline_script( 'techzei-prism', 'window.Prism = window.Prism || {}; window.Prism.manual = true;', 'before' );
		$prism_configured = true;
	}

	if ( '' !== $resolved['file'] && ! isset( $grammars_loaded[ $resolved['file'] ] ) ) {
		$dependencies = array( 'techzei-prism' );
		foreach ( $resolved['deps'] as $dependency ) {
			if ( 'markup-templating' !== $dependency ) {
				continue;
			}
			wp_enqueue_script(
				'techzei-prism-markup-templating',
				get_stylesheet_directory_uri() . '/assets/vendor/prism/components/prism-markup-templating.min.js',
				array( 'techzei-prism' ),
				$version,
				array( 'in_footer' => true, 'strategy' => 'defer' )
			);
			$dependencies[] = 'techzei-prism-markup-templating';
		}
		wp_enqueue_script(
			'techzei-prism-' . $resolved['file'],
			get_stylesheet_directory_uri() . '/assets/vendor/prism/components/prism-' . $resolved['file'] . '.min.js',
			$dependencies,
			$version,
			array( 'in_footer' => true, 'strategy' => 'defer' )
		);
		$grammars_loaded[ $resolved['file'] ] = true;
	}

	if ( $enqueue_runtime && ! $script_loaded ) {
		wp_enqueue_script(
			'techzei-code-blocks',
			get_stylesheet_directory_uri() . '/assets/js/code-blocks.min.js',
			array( 'techzei-prism' ),
			$version,
			array( 'in_footer' => true, 'strategy' => 'defer' )
		);
		wp_localize_script(
			'techzei-code-blocks',
			'TechzeiCodeI18n',
			array(
				'copy'       => __( 'Copy', 'techzei-magazine-theme' ),
				'copied'     => __( 'Copied', 'techzei-magazine-theme' ),
				'copyFailed' => __( 'Copy failed. Text selected.', 'techzei-magazine-theme' ),
				'copyCode'   => __( 'Copy code', 'techzei-magazine-theme' ),
				'code'       => __( 'Code', 'techzei-magazine-theme' ),
			)
		);
		wp_set_script_translations(
			'techzei-code-blocks',
			'techzei-magazine-theme',
			get_stylesheet_directory() . '/languages'
		);
		$script_loaded = true;
	}
}

/**
 * Build normalized, escaped markup from code text.
 *
 * @param string $code        Code text or saved escaped code.
 * @param string $language    Language name.
 * @param bool   $saved_html  Whether code is already HTML-escaped block content.
 * @param bool   $with_copy   Whether this is a block with a Copy control.
 * @param array  $block_attrs Saved block attributes to preserve.
 * @param string $pre_classes Safe source classes from the saved pre element.
 * @return string
 */
function techzei_tt5_code_markup( $code, $language, $saved_html = false, $with_copy = true, $block_attrs = array(), $pre_classes = '' ) {
	$resolved = techzei_tt5_code_language( $language );
	$prism_language = $resolved['prism'];
	$language_class = 'plain' === $prism_language ? 'plain' : $prism_language;
	$label = sprintf( __( 'Code: %s', 'techzei-magazine-theme' ), $resolved['label'] );
	$escaped_code = $saved_html ? techzei_tt5_code_escape_saved_html( $code ) : esc_html( $code );
	$wrapper_classes = array_merge( array( 'tz-code' ), techzei_tt5_code_safe_classes( isset( $block_attrs['className'] ) ? $block_attrs['className'] : '' ) );
	$pre_classes = array_merge( array( 'tz-code__pre', 'language-' . $language_class ), techzei_tt5_code_safe_classes( $pre_classes ) );
	$anchor = isset( $block_attrs['anchor'] ) ? sanitize_html_class( $block_attrs['anchor'] ) : '';
	$anchor_attribute = '' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : '';

	techzei_tt5_code_require_assets( $language, $with_copy );

	if ( ! $with_copy ) {
		return '<code class="tz-code-inline language-' . esc_attr( $language_class ) . '">' . $escaped_code . '</code>';
	}

	return '<div class="' . esc_attr( implode( ' ', array_unique( $wrapper_classes ) ) ) . '" data-lang="' . esc_attr( $language_class ) . '"' . $anchor_attribute . ' role="group" aria-label="' . esc_attr( $label ) . '"><pre class="' . esc_attr( implode( ' ', array_unique( $pre_classes ) ) ) . '" tabindex="0"><code class="language-' . esc_attr( $language_class ) . '">' . $escaped_code . '</code></pre></div>';
}

/**
 * Parse shortcode attributes and return the safe code markup.
 *
 * @param array|string $attributes Shortcode attributes.
 * @param string       $content    Code contents.
 * @return string
 */
function techzei_tt5_code_shortcode( $attributes, $content = '' ) {
	$attributes = is_array( $attributes ) ? $attributes : shortcode_parse_atts( $attributes );
	$language = is_array( $attributes ) && ! empty( $attributes['lang'] ) ? $attributes['lang'] : 'bash';
	$code = (string) $content;
	$code = preg_replace( '/^\r?\n/', '', $code );
	$code = preg_replace( '/\r?\n$/', '', $code );

	if ( false === strpos( $code, "\n" ) && false === strpos( $code, "\r" ) ) {
		return techzei_tt5_code_markup( $code, $language, false, false );
	}

	return techzei_tt5_code_markup( $code, $language );
}

/**
 * Protect Enlighter shortcode source from wptexturize and wpautop.
 *
 * @param string $content Post content.
 * @return string
 */
function techzei_tt5_code_strip_shortcodes( $content ) {
	if ( ! techzei_tt5_code_blocks_enabled() || false === stripos( $content, '[enlighter' ) ) {
		return $content;
	}

	if ( ! isset( $GLOBALS['techzei_tt5_code_buffers'] ) || ! is_array( $GLOBALS['techzei_tt5_code_buffers'] ) ) {
		$GLOBALS['techzei_tt5_code_buffers'] = array();
	}

	return preg_replace_callback(
		'/\[(enlighter)(\s[^\]]*)?\]([\s\S]*?)\[\/\1\]/i',
		function ( $matches ) {
			$attributes = shortcode_parse_atts( isset( $matches[2] ) ? $matches[2] : '' );
			$language = is_array( $attributes ) && ! empty( $attributes['lang'] ) ? $attributes['lang'] : 'bash';
			$code = preg_replace( '/^\r?\n/', '', $matches[3] );
			$code = preg_replace( '/\r?\n$/', '', $code );
			$inline = false === strpos( $code, "\n" ) && false === strpos( $code, "\r" );
			$markup = techzei_tt5_code_markup( $code, $language, false, ! $inline );
			$token = strtoupper( str_replace( '-', '', wp_unique_id( 'tzcode-' ) ) );
			$GLOBALS['techzei_tt5_code_buffers'][ $token ] = $markup;

			return '<' . ( $inline ? 'code' : 'pre' ) . '>{{' . $token . '}}</' . ( $inline ? 'code' : 'pre' ) . '>';
		},
		$content
	);
}

/**
 * Restore shortcode output after wpautop and remove an autop wrapper if present.
 *
 * @param string $content Filtered content.
 * @return string
 */
function techzei_tt5_code_restore_shortcodes( $content ) {
	if ( empty( $GLOBALS['techzei_tt5_code_buffers'] ) || ! is_array( $GLOBALS['techzei_tt5_code_buffers'] ) ) {
		return $content;
	}

	$content = preg_replace_callback(
		'~<p>\s*(<(?:pre|code)>\{\{TZCODE[A-Z0-9]+\}\}</(?:pre|code)>)\s*</p>~i',
		function ( $matches ) {
			return $matches[1];
		},
		$content
	);
	$content = preg_replace_callback(
		'~<(pre|code)>\{\{(TZCODE[A-Z0-9]+)\}\}</\1>~i',
		function ( $matches ) {
			$token = strtoupper( $matches[2] );
			if ( isset( $GLOBALS['techzei_tt5_code_buffers'][ $token ] ) ) {
				$markup = $GLOBALS['techzei_tt5_code_buffers'][ $token ];
				unset( $GLOBALS['techzei_tt5_code_buffers'][ $token ] );
				return $markup;
			}
			return $matches[0];
		},
		$content
	);

	return $content;
}

/**
 * Rewrite inline Enlighter rich-text code without adding a copy control.
 *
 * @param string $content Rendered content.
 * @return string
 */
function techzei_tt5_code_rewrite_inline( $content ) {
	if ( ! techzei_tt5_code_blocks_enabled() || false === strpos( $content, 'EnlighterJSRAW' ) || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return $content;
	}

	$processor = new WP_HTML_Tag_Processor( $content );
	while ( $processor->next_tag( array( 'tag_name' => 'CODE', 'class_name' => 'EnlighterJSRAW' ) ) ) {
		$language = $processor->get_attribute( 'data-enlighter-language' );
		$resolved = techzei_tt5_code_language( is_string( $language ) ? $language : '' );
		$processor->set_attribute( 'class', 'tz-code-inline language-' . $resolved['prism'] );
		$processor->remove_attribute( 'data-enlighter-language' );
		$processor->remove_attribute( 'data-enlighter-theme' );
		techzei_tt5_code_require_assets( $resolved['prism'], false );
	}

	return $processor->get_updated_html();
}

/**
 * Wrap saved Enlighter and core code blocks in the theme's stable markup.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Parsed block.
 * @return string
 */
function techzei_tt5_code_render_block( $block_content, $block ) {
	if ( ! techzei_tt5_code_blocks_enabled() || ! is_array( $block ) ) {
		return $block_content;
	}

	$block_name = isset( $block['blockName'] ) ? $block['blockName'] : '';
	$block_attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
	$language = '';
	$code_html = false;
	$pre_classes = '';

	if ( 'enlighter/codeblock' === $block_name ) {
		$language = isset( $block['attrs']['language'] ) ? $block['attrs']['language'] : techzei_tt5_code_get_attribute( $block_content, 'pre', 'data-enlighter-language' );
		$code_html = techzei_tt5_code_inner_html( $block_content, 'pre' );
		$language = '' !== $language ? $language : 'bash';
	} elseif ( 'core/code' === $block_name ) {
		$code_html = techzei_tt5_code_inner_html( $block_content, 'code' );
		$class = techzei_tt5_code_get_attribute( $block_content, 'code', 'class' );
		$pre_classes = techzei_tt5_code_get_attribute( $block_content, 'pre', 'class' );
		if ( preg_match( '/(?:^|\s)language-([a-z0-9_-]+)/i', $class, $match ) ) {
			$language = $match[1];
		}
	} elseif ( 'core/preformatted' === $block_name ) {
		$code_html = techzei_tt5_code_inner_html( $block_content, 'pre' );
		$class = techzei_tt5_code_get_attribute( $block_content, 'pre', 'class' );
		$pre_classes = $class;
		if ( preg_match( '/(?:^|\s)language-([a-z0-9_-]+)/i', $class, $match ) ) {
			$language = $match[1];
		}
	} else {
		return $block_content;
	}

	if ( false === $code_html ) {
		return $block_content;
	}

	return techzei_tt5_code_markup( $code_html, $language ? $language : 'plain', true, true, $block_attrs, $pre_classes );
}

/**
 * Detect supported code markers and languages for a singular post before wp_head.
 *
 * @param string $content Saved post content.
 * @return array
 */
function techzei_tt5_code_detect_content( $content ) {
	$result = array( 'block' => false, 'inline' => false, 'languages' => array() );

	if ( preg_match_all( '/\[(enlighter)(\s[^\]]*)?\]([\s\S]*?)\[\/\1\]/i', $content, $shortcodes, PREG_SET_ORDER ) ) {
		foreach ( $shortcodes as $shortcode ) {
			$attributes = shortcode_parse_atts( isset( $shortcode[2] ) ? $shortcode[2] : '' );
			$code = preg_replace( '/^\r?\n/', '', $shortcode[3] );
			$code = preg_replace( '/\r?\n$/', '', $code );
			$result['languages'][] = is_array( $attributes ) && ! empty( $attributes['lang'] ) ? $attributes['lang'] : 'bash';
			if ( false === strpos( $code, "\n" ) && false === strpos( $code, "\r" ) ) {
				$result['inline'] = true;
			} else {
				$result['block'] = true;
			}
		}
	}

	if ( false !== strpos( $content, 'wp:enlighter/codeblock' ) ) {
		$result['block'] = true;
		if ( preg_match_all( '/<!--\s*wp:enlighter\/codeblock\s+(\{[\s\S]*?\})\s*-->/', $content, $blocks ) ) {
			foreach ( $blocks[1] as $attributes_json ) {
				$attributes = json_decode( $attributes_json, true );
				$result['languages'][] = is_array( $attributes ) && ! empty( $attributes['language'] ) ? $attributes['language'] : 'bash';
			}
		}
		if ( empty( $result['languages'] ) ) {
			$result['languages'][] = 'bash';
		}
	}

	if ( false !== strpos( $content, 'wp:code' ) || false !== strpos( $content, 'wp:preformatted' ) ) {
		$result['block'] = true;
		if ( preg_match_all( '/\bclass\s*=\s*(["\'])[^"\']*\blanguage-([a-z0-9_-]+)[^"\']*\1/i', $content, $classes ) ) {
			$result['languages'] = array_merge( $result['languages'], $classes[2] );
		}
		if ( empty( $result['languages'] ) ) {
			$result['languages'][] = 'plain';
		}
	}

	if ( false !== strpos( $content, 'EnlighterJSRAW' ) ) {
		$result['inline'] = true;
		if ( preg_match_all( '/data-enlighter-language\s*=\s*(["\'])(.*?)\1/i', $content, $inline_languages ) ) {
			$result['languages'] = array_merge( $result['languages'], $inline_languages[2] );
		}
	}

	$result['languages'] = array_values( array_unique( array_filter( $result['languages'] ) ) );
	return $result;
}

/**
 * Enqueue assets for actual code markers in a singular post's saved content.
 *
 * @return void
 */
function techzei_tt5_code_enqueue_singular_assets() {
	if ( ! techzei_tt5_code_blocks_enabled() || ! is_singular() ) {
		return;
	}

	$post_id = get_queried_object_id();
	$content = $post_id ? get_post_field( 'post_content', $post_id ) : '';
	if ( ! is_string( $content ) || '' === $content ) {
		return;
	}

	$detected = techzei_tt5_code_detect_content( $content );
	if ( $detected['block'] ) {
		$languages = empty( $detected['languages'] ) ? array( 'plain' ) : $detected['languages'];
		foreach ( $languages as $language ) {
			techzei_tt5_code_require_assets( $language, true, false );
		}
		techzei_tt5_code_require_assets( 'plain', true );
	} elseif ( $detected['inline'] ) {
		techzei_tt5_code_require_assets( 'plain', false );
	}
}

/** Register render-time compatibility after all plugins have loaded. */
function techzei_tt5_code_register_compatibility() {
	if ( ! techzei_tt5_code_blocks_enabled() ) {
		return;
	}

	add_filter( 'the_content', 'techzei_tt5_code_strip_shortcodes', 0 );
	add_filter( 'the_content', 'techzei_tt5_code_restore_shortcodes', 9998 );
	add_filter( 'the_content', 'techzei_tt5_code_rewrite_inline', 9999 );
	add_filter( 'render_block', 'techzei_tt5_code_render_block', 10, 2 );
	if ( ! shortcode_exists( 'enlighter' ) ) {
		add_shortcode( 'enlighter', 'techzei_tt5_code_shortcode' );
	}
}
add_action( 'init', 'techzei_tt5_code_register_compatibility', 20 );
add_action( 'wp_enqueue_scripts', 'techzei_tt5_code_enqueue_singular_assets', 20 );

}
