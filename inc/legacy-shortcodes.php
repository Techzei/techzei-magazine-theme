<?php
/**
 * Compatibility handlers for content created with the retired Valenti theme.
 *
 * These handlers keep existing posts readable after the old theme/plugin was
 * removed. They intentionally output semantic, responsive HTML and escape
 * shortcode attributes before using them.
 *
 * @package Techzei_TT5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render a legacy two-column content block.
 *
 * @param array|string $atts    Shortcode attributes.
 * @param string       $content Shortcode content.
 * @return string
 */
function techzei_tt5_legacy_column( $atts, $content = '' ) {
	$atts = shortcode_atts(
		array(
			'size'     => 'one_half',
			'position' => '',
		),
		$atts,
		'column'
	);

	$size = sanitize_key( $atts['size'] );
	$size_map = array(
		'one_third'  => 'is-one-third',
		'one_half'   => 'is-one-half',
		'two_thirds' => 'is-two-thirds',
		'one_fourth' => 'is-one-fourth',
		'three_fourths' => 'is-three-fourths',
	);
	$size_class = isset( $size_map[ $size ] ) ? $size_map[ $size ] : 'is-one-half';
	$position = sanitize_key( $atts['position'] );
	$position_class = in_array( $position, array( 'first', 'last', 'middle' ), true ) ? 'is-' . $position : '';
	$classes = trim( 'tz-legacy-column ' . $size_class . ' ' . $position_class );
	return sprintf(
		'<div class="%1$s">%2$s</div>',
		esc_attr( $classes ),
		do_shortcode( shortcode_unautop( $content ) )
	);
}

/**
 * Render a legacy alert box.
 *
 * @param array|string $atts    Shortcode attributes.
 * @param string       $content Shortcode content.
 * @return string
 */
function techzei_tt5_legacy_alert( $atts, $content = '' ) {
	$atts = shortcode_atts( array( 'type' => 'blue' ), $atts, 'alert' );
	$type = sanitize_key( $atts['type'] );
	$allowed_types = array( 'blue', 'yellow', 'red', 'green', 'info', 'warning', 'success', 'error' );
	$type_class = in_array( $type, $allowed_types, true ) ? $type : 'blue';

	return sprintf(
		'<aside class="tz-legacy-alert is-%1$s" role="note">%2$s</aside>',
		esc_attr( $type_class ),
		do_shortcode( shortcode_unautop( $content ) )
	);
}

/**
 * Render a legacy button link.
 *
 * @param array|string $atts    Shortcode attributes.
 * @param string       $content Shortcode content.
 * @return string
 */
function techzei_tt5_legacy_button( $atts, $content = '' ) {
	$atts = shortcode_atts(
		array(
			'link'   => '',
			'style'  => 'dark',
			'size'   => '',
			'target' => '',
		),
		$atts,
		'button'
	);

	$url = esc_url( $atts['link'] );
	if ( empty( $url ) ) {
		return do_shortcode( shortcode_unautop( $content ) );
	}

	$style = sanitize_key( $atts['style'] );
	$size  = sanitize_key( $atts['size'] );
	$classes = trim( 'tz-legacy-button is-' . ( $style ? $style : 'dark' ) . ( 'large' === $size ? ' is-large' : '' ) );
	$target = '_blank' === $atts['target'] ? ' target="_blank" rel="noopener noreferrer"' : '';

	return sprintf(
		'<a class="%1$s" href="%2$s"%3$s>%4$s</a>',
		esc_attr( $classes ),
		$url,
		$target,
		wp_kses_post( do_shortcode( shortcode_unautop( $content ) ) )
	);
}

/**
 * Render a legacy pullquote.
 *
 * @param array|string $atts    Shortcode attributes.
 * @param string       $content Shortcode content.
 * @return string
 */
function techzei_tt5_legacy_pullquote( $atts, $content = '' ) {
	$atts = shortcode_atts( array( 'align' => 'right' ), $atts, 'pullquote' );
	$align = sanitize_key( $atts['align'] );
	$align_class = in_array( $align, array( 'left', 'right', 'center' ), true ) ? 'is-' . $align : 'is-right';

	return sprintf(
		'<blockquote class="tz-legacy-pullquote %1$s">%2$s</blockquote>',
		esc_attr( $align_class ),
		do_shortcode( shortcode_unautop( $content ) )
	);
}

/**
 * Render a legacy horizontal rule.
 *
 * @return string
 */
function techzei_tt5_legacy_hr() {
	return '<hr class="tz-legacy-hr" />';
}

/**
 * Render a legacy attention callout.
 *
 * @param array|string $atts    Shortcode attributes.
 * @param string       $content Shortcode content.
 * @return string
 */
function techzei_tt5_legacy_attention( $atts, $content = '' ) {
	return sprintf(
		'<aside class="tz-legacy-attention" role="note"><strong>Attention</strong><div>%s</div></aside>',
		do_shortcode( shortcode_unautop( $content ) )
	);
}

add_shortcode( 'column', 'techzei_tt5_legacy_column' );
add_shortcode( 'alert', 'techzei_tt5_legacy_alert' );
add_shortcode( 'button', 'techzei_tt5_legacy_button' );
add_shortcode( 'pullquote', 'techzei_tt5_legacy_pullquote' );
add_shortcode( 'hr', 'techzei_tt5_legacy_hr' );
add_shortcode( 'attention', 'techzei_tt5_legacy_attention' );
