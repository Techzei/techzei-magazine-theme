<?php
/**
 * Techzei Magazine Theme settings page and resolver.
 *
 * This module owns theme behaviour defaults only. Site Editor content,
 * template composition, Global Styles, menus, and plugin settings remain
 * outside this option.
 *
 * @package Techzei_TT5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'TECHZEI_TT5_SETTINGS_OPTION' ) ) {
	define( 'TECHZEI_TT5_SETTINGS_OPTION', 'techzei_tt5_settings' );
}

if ( ! defined( 'TECHZEI_TT5_SETTINGS_GROUP' ) ) {
	define( 'TECHZEI_TT5_SETTINGS_GROUP', 'techzei_tt5_settings_group' );
}

if ( ! defined( 'TECHZEI_TT5_SETTINGS_PAGE' ) ) {
	define( 'TECHZEI_TT5_SETTINGS_PAGE', 'techzei-magazine-settings' );
}

if ( ! defined( 'TECHZEI_TT5_SETTINGS_VERSION' ) ) {
	define( 'TECHZEI_TT5_SETTINGS_VERSION', 1 );
}

/**
 * Return the category used by the fresh-install review default, if present.
 *
 * @return int
 */
function techzei_tt5_settings_default_review_category() {
	$term = get_category_by_slug( 'review' );

	return $term && ! is_wp_error( $term ) ? absint( $term->term_id ) : 0;
}

/**
 * Return the complete fresh-install settings value.
 *
 * This function is read-only. It deliberately does not call add_option(),
 * so a frontend request never creates or changes settings.
 *
 * @return array
 */
function techzei_tt5_settings_defaults() {
	return array(
		'version' => TECHZEI_TT5_SETTINGS_VERSION,
		'values'  => array(
			'header'   => array(
				'sticky_desktop' => true,
				'sticky_mobile'  => true,
				'show_search'    => true,
				'show_topics'    => true,
			),
			'homepage' => array(
				'show_featured_grid' => true,
				'feed_count'         => 8,
				'headline_mode'      => 'marquee',
				'headline_source'    => 'latest',
				'headline_category'  => 0,
				'headline_count'     => 4,
				'marquee_speed'      => 'slow',
			),
			'articles' => array(
				'default_layout'       => 'sidebar',
				'automatic_legacy'     => true,
				'show_reading_time'    => true,
				'updated_date'         => 'later',
				'author_card'          => true,
				'share_links'          => true,
				'share_destinations'   => array( 'x', 'facebook', 'linkedin', 'whatsapp' ),
				'related_stories'      => true,
				'related_count'        => 3,
			),
			'sidebar'  => array(
				'latest_stories' => true,
				'latest_count'   => 5,
				'reviews'        => true,
				'review_category' => techzei_tt5_settings_default_review_category(),
				'review_count'   => 4,
			),
		),
	);
}

/**
 * Return the raw stored values without writing defaults.
 *
 * @return array
 */
function techzei_tt5_settings_stored_values() {
	$stored = get_option( TECHZEI_TT5_SETTINGS_OPTION, array() );

	if ( ! is_array( $stored ) || TECHZEI_TT5_SETTINGS_VERSION !== (int) ( isset( $stored['version'] ) ? $stored['version'] : 0 ) ) {
		return array();
	}

	return isset( $stored['values'] ) && is_array( $stored['values'] ) ? $stored['values'] : array();
}

/**
 * Normalize stored values silently for safe frontend/editor consumption.
 *
 * The sanitize callback reports invalid submitted values. This separate
 * normalizer prevents a manually altered database option from producing an
 * invalid mode, count, or share URL in a frontend request.
 *
 * @param array $values Stored values.
 * @return array
 */
function techzei_tt5_settings_normalize_values( $values ) {
	$defaults = techzei_tt5_settings_defaults()['values'];
	$values   = is_array( $values ) ? $values : array();
	$output   = $defaults;

	foreach ( array_keys( $defaults ) as $group ) {
		if ( ! isset( $values[ $group ] ) || ! is_array( $values[ $group ] ) ) {
			$values[ $group ] = array();
		}
	}

	$booleans = array(
		'header'   => array( 'sticky_desktop', 'sticky_mobile', 'show_search', 'show_topics' ),
		'homepage' => array( 'show_featured_grid' ),
		'articles' => array( 'automatic_legacy', 'show_reading_time', 'author_card', 'share_links', 'related_stories' ),
		'sidebar'  => array( 'latest_stories', 'reviews' ),
	);

	foreach ( $booleans as $group => $keys ) {
		foreach ( $keys as $key ) {
			if ( isset( $values[ $group ] ) && array_key_exists( $key, $values[ $group ] ) ) {
				$boolean = is_scalar( $values[ $group ][ $key ] ) ? filter_var( $values[ $group ][ $key ], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) : null;
				if ( null !== $boolean ) {
					$output[ $group ][ $key ] = $boolean;
				}
			}
		}
	}

	$enums = array(
		'homepage' => array(
			'headline_mode'   => array( 'hidden', 'static', 'marquee' ),
			'headline_source' => array( 'latest', 'category' ),
			'marquee_speed'   => array( 'slow', 'standard' ),
		),
		'articles' => array(
			'default_layout' => array( 'sidebar', 'no-sidebar' ),
			'updated_date'   => array( 'later', 'hide' ),
		),
	);

	foreach ( $enums as $group => $fields ) {
		foreach ( $fields as $key => $allowed ) {
			if ( isset( $values[ $group ][ $key ] ) && is_scalar( $values[ $group ][ $key ] ) ) {
				$value = sanitize_key( (string) $values[ $group ][ $key ] );
				if ( in_array( $value, $allowed, true ) ) {
					$output[ $group ][ $key ] = $value;
				}
			}
		}
	}

	$bounded_integers = array(
		'homepage' => array(
			'feed_count'     => array( 4, 20 ),
			'headline_count' => array( 3, 8 ),
		),
		'articles' => array( 'related_count' => array( 2, 6 ) ),
		'sidebar'  => array(
			'latest_count' => array( 3, 6 ),
			'review_count' => array( 2, 6 ),
		),
	);

	foreach ( $bounded_integers as $group => $fields ) {
		foreach ( $fields as $key => $range ) {
			if ( isset( $values[ $group ][ $key ] ) && is_scalar( $values[ $group ][ $key ] ) ) {
				$output[ $group ][ $key ] = min( $range[1], max( $range[0], absint( $values[ $group ][ $key ] ) ) );
			}
		}
	}

	if ( isset( $values['homepage']['headline_category'] ) && is_scalar( $values['homepage']['headline_category'] ) ) {
		$output['homepage']['headline_category'] = absint( $values['homepage']['headline_category'] );
	}

	if ( isset( $values['sidebar']['review_category'] ) && is_scalar( $values['sidebar']['review_category'] ) ) {
		$output['sidebar']['review_category'] = absint( $values['sidebar']['review_category'] );
	}

	if ( isset( $values['articles']['share_destinations'] ) && is_array( $values['articles']['share_destinations'] ) ) {
		$allowed = array( 'x', 'facebook', 'linkedin', 'whatsapp' );
		$chosen  = array();
		foreach ( $values['articles']['share_destinations'] as $destination ) {
			if ( is_scalar( $destination ) ) {
				$chosen[] = sanitize_key( (string) $destination );
			}
		}
		$output['articles']['share_destinations'] = array_values( array_intersect( $allowed, $chosen ) );
	}

	return $output;
}

/**
 * Resolve settings for frontend and editor consumers.
 *
 * Pass a group name to receive that group's values, or pass a dotted path
 * such as "homepage.feed_count" for one value. With no argument, the full
 * four-group resolved settings array is returned.
 *
 * @param string|null $path Optional group or dotted setting path.
 * @return mixed
 */
function techzei_tt5_get_settings( $path = null ) {
	$settings = techzei_tt5_settings_normalize_values( techzei_tt5_settings_stored_values() );

	if ( null === $path || '' === $path ) {
		return $settings;
	}
	if ( ! is_scalar( $path ) ) {
		return null;
	}

	$parts = explode( '.', str_replace( '/', '.', (string) $path ) );
	$parts = array_map( 'sanitize_key', $parts );
	$value  = $settings;
	foreach ( $parts as $part ) {
		if ( ! is_array( $value ) || ! array_key_exists( $part, $value ) ) {
			return null;
		}
		$value = $value[ $part ];
	}

	return $value;
}

/**
 * Resolve one setting with an optional fallback.
 *
 * @param string     $group    Settings group.
 * @param string     $key      Setting key.
 * @param mixed|null $fallback Fallback when the setting does not exist.
 * @return mixed
 */
function techzei_tt5_get_setting( $group, $key, $fallback = null ) {
	$value = techzei_tt5_get_settings( sanitize_key( $group ) . '.' . sanitize_key( $key ) );

	return null === $value ? $fallback : $value;
}

/**
 * Whether a value was submitted in a nested settings group.
 *
 * @param array  $input Submitted settings.
 * @param string $group Group name.
 * @param string $key   Setting key.
 * @return bool
 */
function techzei_tt5_settings_has_input( $input, $group, $key ) {
	return isset( $input[ $group ] ) && is_array( $input[ $group ] ) && array_key_exists( $key, $input[ $group ] );
}

/**
 * Add a settings validation error.
 *
 * @param string $message Error message.
 * @return void
 */
function techzei_tt5_settings_error( $message ) {
	add_settings_error( TECHZEI_TT5_SETTINGS_OPTION, 'techzei_tt5_invalid', $message, 'error' );
}

/**
 * Sanitize and validate the one stored settings structure.
 *
 * @param mixed $input Submitted option value.
 * @return array
 */
function techzei_tt5_settings_sanitize( $input ) {
	$defaults = techzei_tt5_settings_defaults();
	$current  = techzei_tt5_settings_normalize_values( techzei_tt5_settings_stored_values() );
	$input    = is_array( $input ) ? wp_unslash( $input ) : array();
	$output   = array( 'version' => TECHZEI_TT5_SETTINGS_VERSION, 'values' => $current );

	$boolean_fields = array(
		'header'   => array( 'sticky_desktop', 'sticky_mobile', 'show_search', 'show_topics' ),
		'homepage' => array( 'show_featured_grid' ),
		'articles' => array( 'automatic_legacy', 'show_reading_time', 'author_card', 'share_links', 'related_stories' ),
		'sidebar'  => array( 'latest_stories', 'reviews' ),
	);

	foreach ( $boolean_fields as $group => $keys ) {
		foreach ( $keys as $key ) {
			if ( isset( $input[ $group ] ) && is_array( $input[ $group ] ) ) {
				$output['values'][ $group ][ $key ] = ! empty( $input[ $group ][ $key ] );
			}
		}
	}

	$enum_fields = array(
		'homepage' => array(
			'headline_mode'   => array( 'hidden', 'static', 'marquee' ),
			'headline_source' => array( 'latest', 'category' ),
			'marquee_speed'   => array( 'slow', 'standard' ),
		),
		'articles' => array(
			'default_layout' => array( 'sidebar', 'no-sidebar' ),
			'updated_date'   => array( 'later', 'hide' ),
		),
	);

	foreach ( $enum_fields as $group => $fields ) {
		foreach ( $fields as $key => $allowed ) {
			if ( ! techzei_tt5_settings_has_input( $input, $group, $key ) ) {
				continue;
			}

			$value = is_scalar( $input[ $group ][ $key ] ) ? sanitize_key( (string) $input[ $group ][ $key ] ) : '';
			if ( ! in_array( $value, $allowed, true ) ) {
				techzei_tt5_settings_error(
					sprintf(
						/* translators: 1: setting label, 2: allowed values. */
						__( '%1$s was reset because it is not one of the allowed values: %2$s.', 'techzei-magazine-theme' ),
						ucwords( str_replace( '_', ' ', $key ) ),
						implode( ', ', $allowed )
					)
				);
				continue;
			}
			$output['values'][ $group ][ $key ] = $value;
		}
	}

	$integer_fields = array(
		'homepage' => array(
			'feed_count'     => array( 4, 20 ),
			'headline_count' => array( 3, 8 ),
		),
		'articles' => array( 'related_count' => array( 2, 6 ) ),
		'sidebar'  => array(
			'latest_count' => array( 3, 6 ),
			'review_count' => array( 2, 6 ),
		),
	);

	foreach ( $integer_fields as $group => $fields ) {
		foreach ( $fields as $key => $range ) {
			if ( ! techzei_tt5_settings_has_input( $input, $group, $key ) ) {
				continue;
			}

			$raw = $input[ $group ][ $key ];
			if ( ! is_scalar( $raw ) || '' === (string) $raw || ! is_numeric( $raw ) ) {
				techzei_tt5_settings_error(
					sprintf(
						/* translators: %s: setting label. */
						__( '%s must be a number within the permitted range.', 'techzei-magazine-theme' ),
						ucwords( str_replace( '_', ' ', $key ) )
					)
				);
				continue;
			}

			$value = absint( $raw );
			if ( $value < $range[0] || $value > $range[1] ) {
				techzei_tt5_settings_error(
					sprintf(
						/* translators: 1: setting label, 2: minimum, 3: maximum. */
						__( '%1$s must be between %2$d and %3$d. The nearest allowed value was used.', 'techzei-magazine-theme' ),
						ucwords( str_replace( '_', ' ', $key ) ),
						$range[0],
						$range[1]
					)
				);
				$value = min( $range[1], max( $range[0], $value ) );
			}
			$output['values'][ $group ][ $key ] = $value;
		}
	}

	$category_fields = array(
		'homepage' => array( 'headline_category' => true ),
		'sidebar'  => array( 'review_category' => true ),
	);

	foreach ( $category_fields as $group => $fields ) {
		foreach ( $fields as $key => $allow_empty ) {
			if ( ! techzei_tt5_settings_has_input( $input, $group, $key ) ) {
				continue;
			}

			$value = is_scalar( $input[ $group ][ $key ] ) ? absint( $input[ $group ][ $key ] ) : 0;
			$term  = $value ? get_term( $value, 'category' ) : false;
			if ( $value && ( ! $term || is_wp_error( $term ) ) ) {
				techzei_tt5_settings_error(
					sprintf(
						/* translators: %s: setting label. */
						__( '%s must reference an existing WordPress category. Choose another category.', 'techzei-magazine-theme' ),
						ucwords( str_replace( '_', ' ', $key ) )
					)
				);
				$value = 0;
			}
			$output['values'][ $group ][ $key ] = $value;
		}
	}

	if ( 'category' === $output['values']['homepage']['headline_source'] && ! $output['values']['homepage']['headline_category'] ) {
		techzei_tt5_settings_error(
			__( 'Headline source is set to Selected category. Choose an existing category or switch the source back to Latest published posts; the strip will remain omitted until then.', 'techzei-magazine-theme' )
		);
	}

	if ( isset( $input['articles'] ) && is_array( $input['articles'] ) && $output['values']['articles']['share_links'] ) {
		$allowed = array( 'x', 'facebook', 'linkedin', 'whatsapp' );
		$chosen = isset( $input['articles']['share_destinations'] ) && is_array( $input['articles']['share_destinations'] ) ? $input['articles']['share_destinations'] : array();
		$chosen = array_filter(
			array_map(
				static function ( $destination ) {
					return is_scalar( $destination ) ? sanitize_key( (string) $destination ) : '';
				},
				$chosen
			),
			static function ( $destination ) {
				return '' !== $destination;
			}
		);
		$output['values']['articles']['share_destinations'] = array_values( array_intersect( $allowed, $chosen ) );
	}

	return $output;
}

/**
 * Return existing categories for dependency-aware select fields.
 *
 * @return WP_Term[]
 */
function techzei_tt5_settings_categories() {
	$categories = get_terms(
		array(
			'taxonomy'   => 'category',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);

	return is_wp_error( $categories ) ? array() : $categories;
}

/**
 * Register the Settings API option and fields.
 *
 * @return void
 */
function techzei_tt5_settings_register() {
	register_setting(
		TECHZEI_TT5_SETTINGS_GROUP,
		TECHZEI_TT5_SETTINGS_OPTION,
		array(
			'type'              => 'array',
			'description'       => __( 'Techzei Magazine Theme behaviour settings.', 'techzei-magazine-theme' ),
			'sanitize_callback' => 'techzei_tt5_settings_sanitize',
			'default'           => techzei_tt5_settings_defaults(),
			'show_in_rest'      => false,
		)
	);

	add_settings_section(
		'techzei_header',
		__( 'Header', 'techzei-magazine-theme' ),
		'techzei_tt5_settings_section',
		TECHZEI_TT5_SETTINGS_PAGE
	);
	add_settings_section(
		'techzei_homepage',
		__( 'Homepage', 'techzei-magazine-theme' ),
		'techzei_tt5_settings_section',
		TECHZEI_TT5_SETTINGS_PAGE
	);
	add_settings_section(
		'techzei_articles',
		__( 'Articles', 'techzei-magazine-theme' ),
		'techzei_tt5_settings_section',
		TECHZEI_TT5_SETTINGS_PAGE
	);
	add_settings_section(
		'techzei_sidebar',
		__( 'Sidebar', 'techzei-magazine-theme' ),
		'techzei_tt5_settings_section',
		TECHZEI_TT5_SETTINGS_PAGE
	);

	$fields = array(
		'techzei_header'   => array(
			'sticky_desktop' => __( 'Sticky header — desktop/tablet', 'techzei-magazine-theme' ),
			'sticky_mobile'  => __( 'Sticky header — mobile', 'techzei-magazine-theme' ),
			'show_search'    => __( 'Header search', 'techzei-magazine-theme' ),
			'show_topics'    => __( 'Topics navigation', 'techzei-magazine-theme' ),
		),
		'techzei_homepage' => array(
			'show_featured_grid' => __( 'Featured stories grid', 'techzei-magazine-theme' ),
			'feed_count'         => __( 'Stories per feed page', 'techzei-magazine-theme' ),
			'headline_mode'      => __( 'Headline strip', 'techzei-magazine-theme' ),
			'headline_source'    => __( 'Headline source', 'techzei-magazine-theme' ),
			'headline_category'  => __( 'Headline category', 'techzei-magazine-theme' ),
			'headline_count'     => __( 'Headline count', 'techzei-magazine-theme' ),
			'marquee_speed'      => __( 'Marquee speed', 'techzei-magazine-theme' ),
		),
		'techzei_articles' => array(
			'default_layout'     => __( 'Default article layout', 'techzei-magazine-theme' ),
			'automatic_legacy'   => __( 'Automatic legacy styling', 'techzei-magazine-theme' ),
			'show_reading_time'  => __( 'Reading time', 'techzei-magazine-theme' ),
			'updated_date'       => __( 'Updated date', 'techzei-magazine-theme' ),
			'author_card'        => __( 'Author profile card', 'techzei-magazine-theme' ),
			'share_links'        => __( 'Share links', 'techzei-magazine-theme' ),
			'share_destinations' => __( 'Share destinations', 'techzei-magazine-theme' ),
			'related_stories'    => __( 'Related stories', 'techzei-magazine-theme' ),
			'related_count'      => __( 'Related story count', 'techzei-magazine-theme' ),
		),
		'techzei_sidebar' => array(
			'latest_stories' => __( 'Latest stories module', 'techzei-magazine-theme' ),
			'latest_count'   => __( 'Latest story count', 'techzei-magazine-theme' ),
			'reviews'        => __( 'Reviews module', 'techzei-magazine-theme' ),
			'review_category' => __( 'Review category', 'techzei-magazine-theme' ),
			'review_count'   => __( 'Review count', 'techzei-magazine-theme' ),
		),
	);

	foreach ( $fields as $section => $section_fields ) {
		foreach ( $section_fields as $key => $label ) {
			add_settings_field(
				'techzei_' . $key,
				$label,
				'techzei_tt5_settings_field',
				TECHZEI_TT5_SETTINGS_PAGE,
				$section,
				array( 'group' => substr( $section, strlen( 'techzei_' ) ), 'key' => $key )
			);
		}
	}
}
add_action( 'admin_init', 'techzei_tt5_settings_register' );

/**
 * Keep the Settings API save capability aligned with the page capability.
 *
 * @return string
 */
function techzei_tt5_settings_capability() {
	return 'edit_theme_options';
}
add_filter( 'option_page_capability_' . TECHZEI_TT5_SETTINGS_GROUP, 'techzei_tt5_settings_capability' );

/** Add the settings page below Appearance. */
function techzei_tt5_settings_menu() {
	add_theme_page(
		__( 'Techzei Settings', 'techzei-magazine-theme' ),
		__( 'Techzei Settings', 'techzei-magazine-theme' ),
		'edit_theme_options',
		TECHZEI_TT5_SETTINGS_PAGE,
		'techzei_tt5_settings_page'
	);
}
add_action( 'admin_menu', 'techzei_tt5_settings_menu' );

/**
 * Render a section's explanatory copy.
 *
 * @param array $section Section arguments.
 * @return void
 */
function techzei_tt5_settings_section( $section ) {
	$copy = array(
		'techzei_header'   => __( 'Control header behaviour. The Site Editor still owns the logo, menus, and visual header composition.', 'techzei-magazine-theme' ),
		'techzei_homepage' => __( 'Control homepage modules and headline behaviour. Archive, search, and WordPress Reading settings are not changed here.', 'techzei-magazine-theme' ),
		'techzei_articles' => __( 'Set defaults for recognised Techzei article components. An explicitly selected post template takes precedence.', 'techzei-magazine-theme' ),
		'techzei_sidebar'  => __( 'Control article-sidebar discovery modules. Disabled or empty modules do not create replacement content.', 'techzei-magazine-theme' ),
	);

	if ( isset( $copy[ $section['id'] ] ) ) {
		printf( '<p class="description">%s</p>', esc_html( $copy[ $section['id'] ] ) );
	}
}

/**
 * Return a nested option input name.
 *
 * @param string $group Group name.
 * @param string $key   Setting key.
 * @return string
 */
function techzei_tt5_settings_input_name( $group, $key ) {
	return TECHZEI_TT5_SETTINGS_OPTION . '[' . $group . '][' . $key . ']';
}

/**
 * Render one Settings API field.
 *
 * @param array $args Field arguments.
 * @return void
 */
function techzei_tt5_settings_field( $args ) {
	$group    = $args['group'];
	$key      = $args['key'];
	$settings = techzei_tt5_get_settings();
	$value    = isset( $settings[ $group ][ $key ] ) ? $settings[ $group ][ $key ] : null;
	$name     = techzei_tt5_settings_input_name( $group, $key );

	$descriptions = array(
		'sticky_desktop'     => __( 'Keep the outer header visible on desktop and tablet screens.', 'techzei-magazine-theme' ),
		'sticky_mobile'      => __( 'Keep the compact header visible below the mobile breakpoint.', 'techzei-magazine-theme' ),
		'show_search'        => __( 'Show the desktop search and mobile search toggle together.', 'techzei-magazine-theme' ),
		'show_topics'        => __( 'Show the secondary Topics navigation; it does not change the primary menu.', 'techzei-magazine-theme' ),
		'show_featured_grid' => __( 'Hide the complete lead-and-tile section; those posts return to the main feed.', 'techzei-magazine-theme' ),
		'feed_count'         => __( 'Homepage feed only. This does not change archive or search pagination.', 'techzei-magazine-theme' ),
		'headline_mode'      => __( 'Mobile and reduced-motion readers always receive a static, swipeable list.', 'techzei-magazine-theme' ),
		'headline_source'    => __( 'The label remains editable in the Site Editor and does not imply popularity tracking.', 'techzei-magazine-theme' ),
		'headline_category'  => __( 'Required when Selected category is active. An unavailable category omits the strip until corrected.', 'techzei-magazine-theme' ),
		'headline_count'     => __( 'Visual marquee copies do not count as additional headlines.', 'techzei-magazine-theme' ),
		'marquee_speed'      => __( 'Only applies to desktop marquee mode; readers retain a Pause control.', 'techzei-magazine-theme' ),
		'default_layout'     => __( 'An explicit post template selection wins over this site default.', 'techzei-magazine-theme' ),
		'automatic_legacy'   => __( 'Keeps the established shortcode/date treatment without disabling shortcode support.', 'techzei-magazine-theme' ),
		'show_reading_time'  => __( 'Controls the reading-time item in the article metadata row.', 'techzei-magazine-theme' ),
		'updated_date'       => __( 'The later-only mode avoids repeating an identical publication date.', 'techzei-magazine-theme' ),
		'author_card'        => __( 'The title-area byline remains even when this card is hidden.', 'techzei-magazine-theme' ),
		'share_links'        => __( 'With no destinations selected, the complete share row is omitted.', 'techzei-magazine-theme' ),
		'share_destinations' => __( 'Fixed display order: X, Facebook, LinkedIn, WhatsApp.', 'techzei-magazine-theme' ),
		'related_stories'    => __( 'Uses shared categories and excludes the current article.', 'techzei-magazine-theme' ),
		'related_count'      => __( 'A smaller result set is allowed; unrelated fallback stories are not inserted.', 'techzei-magazine-theme' ),
		'latest_stories'     => __( 'This affects article sidebars only.', 'techzei-magazine-theme' ),
		'latest_count'       => __( 'The current article is excluded.', 'techzei-magazine-theme' ),
		'reviews'            => __( 'If no valid review category exists, the module is omitted and a notice explains why.', 'techzei-magazine-theme' ),
		'review_category'    => __( 'Choose an existing category. The default matches the review slug when available.', 'techzei-magazine-theme' ),
		'review_count'       => __( 'A smaller result set is allowed.', 'techzei-magazine-theme' ),
	);

	$disabled = false;
	if ( 'headline_category' === $key ) {
		$disabled = 'category' !== $settings['homepage']['headline_source'];
	} elseif ( 'marquee_speed' === $key ) {
		$disabled = 'marquee' !== $settings['homepage']['headline_mode'];
	} elseif ( 'share_destinations' === $key ) {
		$disabled = ! $settings['articles']['share_links'];
	} elseif ( in_array( $key, array( 'related_count' ), true ) ) {
		$disabled = ! $settings['articles']['related_stories'];
	} elseif ( in_array( $key, array( 'latest_count' ), true ) ) {
		$disabled = ! $settings['sidebar']['latest_stories'];
	} elseif ( in_array( $key, array( 'review_category', 'review_count' ), true ) ) {
		$disabled = ! $settings['sidebar']['reviews'];
	}

	if ( in_array( $key, array( 'sticky_desktop', 'sticky_mobile', 'show_search', 'show_topics', 'show_featured_grid', 'automatic_legacy', 'show_reading_time', 'author_card', 'share_links', 'related_stories', 'latest_stories', 'reviews' ), true ) ) {
		printf( '<input type="hidden" name="%1$s" value="0" />', esc_attr( $name ) );
		printf( '<label><input type="checkbox" name="%1$s" value="1" %2$s%3$s /> %4$s</label>', esc_attr( $name ), checked( $value, true, false ), disabled( $disabled, true, false ), esc_html__( 'Enabled', 'techzei-magazine-theme' ) );
	} elseif ( 'share_destinations' === $key ) {
		$choices = array(
			'x'        => 'X',
			'facebook' => 'Facebook',
			'linkedin' => 'LinkedIn',
			'whatsapp' => 'WhatsApp',
		);
		foreach ( $choices as $choice => $label ) {
			printf( '<label style="margin-right:1.25em"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s%4$s /> %5$s</label>', esc_attr( $name ), esc_attr( $choice ), checked( in_array( $choice, (array) $value, true ), true, false ), disabled( $disabled, true, false ), esc_html( $label ) );
		}
	} elseif ( in_array( $key, array( 'feed_count', 'headline_count', 'related_count', 'latest_count', 'review_count' ), true ) ) {
		$ranges = array(
			'feed_count'     => array( 4, 20 ),
			'headline_count' => array( 3, 8 ),
			'related_count'  => array( 2, 6 ),
			'latest_count'   => array( 3, 6 ),
			'review_count'   => array( 2, 6 ),
		);
		printf( '<input class="small-text" type="number" name="%1$s" value="%2$d" min="%3$d" max="%4$d" step="1"%5$s />', esc_attr( $name ), absint( $value ), $ranges[ $key ][0], $ranges[ $key ][1], disabled( $disabled, true, false ) );
	} elseif ( 'headline_mode' === $key ) {
		techzei_tt5_settings_select( $name, $value, array( 'hidden' => __( 'Hidden', 'techzei-magazine-theme' ), 'static' => __( 'Static list', 'techzei-magazine-theme' ), 'marquee' => __( 'Desktop marquee', 'techzei-magazine-theme' ) ), false );
	} elseif ( 'headline_source' === $key ) {
		techzei_tt5_settings_select( $name, $value, array( 'latest' => __( 'Latest published posts', 'techzei-magazine-theme' ), 'category' => __( 'Selected category', 'techzei-magazine-theme' ) ), false );
	} elseif ( 'marquee_speed' === $key ) {
		techzei_tt5_settings_select( $name, $value, array( 'slow' => __( 'Slow', 'techzei-magazine-theme' ), 'standard' => __( 'Standard', 'techzei-magazine-theme' ) ), $disabled );
	} elseif ( 'default_layout' === $key ) {
		techzei_tt5_settings_select( $name, $value, array( 'sidebar' => __( 'With sidebar', 'techzei-magazine-theme' ), 'no-sidebar' => __( 'Without sidebar', 'techzei-magazine-theme' ) ), false );
	} elseif ( 'updated_date' === $key ) {
		techzei_tt5_settings_select( $name, $value, array( 'later' => __( 'Show when later', 'techzei-magazine-theme' ), 'hide' => __( 'Hide', 'techzei-magazine-theme' ) ), false );
	} elseif ( in_array( $key, array( 'headline_category', 'review_category' ), true ) ) {
		$categories = techzei_tt5_settings_categories();
		$options    = array( 0 => __( 'No category selected', 'techzei-magazine-theme' ) );
		foreach ( $categories as $category ) {
			$options[ absint( $category->term_id ) ] = $category->name;
		}
		techzei_tt5_settings_select( $name, absint( $value ), $options, $disabled );
	}

	if ( isset( $descriptions[ $key ] ) ) {
		printf( '<p class="description">%s</p>', esc_html( $descriptions[ $key ] ) );
	}
	if ( $disabled ) {
		printf( '<p class="description">%s</p>', esc_html__( 'This control is disabled because its parent setting is off or uses another mode.', 'techzei-magazine-theme' ) );
	}
}

/**
 * Render a select field.
 *
 * @param string $name     Input name.
 * @param mixed  $selected Selected value.
 * @param array  $options  Options.
 * @param bool   $disabled Whether the field is disabled.
 * @return void
 */
function techzei_tt5_settings_select( $name, $selected, $options, $disabled ) {
	printf( '<select name="%1$s"%2$s>', esc_attr( $name ), disabled( $disabled, true, false ) );
	foreach ( $options as $value => $label ) {
		printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $value ), selected( (string) $selected, (string) $value, false ), esc_html( $label ) );
	}
	echo '</select>';
}

/**
 * Add a dependency warning for missing selected categories.
 *
 * @param array $settings Resolved settings.
 * @return void
 */
function techzei_tt5_settings_dependency_notices( $settings ) {
	if ( 'category' === $settings['homepage']['headline_source'] ) {
		$category = $settings['homepage']['headline_category'] ? get_term( $settings['homepage']['headline_category'], 'category' ) : false;
		if ( ! $category || is_wp_error( $category ) ) {
			printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html__( 'The headline strip is set to Selected category, but its category is missing or unavailable. Choose another category; the strip remains omitted until corrected.', 'techzei-magazine-theme' ) );
		}
	}

	if ( $settings['sidebar']['reviews'] ) {
		$category = $settings['sidebar']['review_category'] ? get_term( $settings['sidebar']['review_category'], 'category' ) : false;
		if ( ! $category || is_wp_error( $category ) ) {
			printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html__( 'The Reviews module is enabled, but no valid review category is selected. Choose a category to show the module; the theme will not substitute all posts.', 'techzei-magazine-theme' ) );
		}
	}
}

/**
 * Build a Site Editor URL without storing a site-specific value.
 *
 * @param string $path Site Editor path.
 * @return string
 */
function techzei_tt5_settings_site_editor_url( $path = '' ) {
	$url = admin_url( 'site-editor.php' );

	return $path ? add_query_arg( 'path', $path, $url ) : $url;
}

/** Render ownership shortcuts to native WordPress screens. */
function techzei_tt5_settings_shortcuts() {
	$links = array(
		array( __( 'Colours, fonts, widths, and spacing', 'techzei-magazine-theme' ), techzei_tt5_settings_site_editor_url( '/styles' ) ),
		array( __( 'Logo, site title, and tagline', 'techzei-magazine-theme' ), techzei_tt5_settings_site_editor_url() ),
		array( __( 'Primary, Topics, and footer navigation', 'techzei-magazine-theme' ), techzei_tt5_settings_site_editor_url( '/navigation' ) ),
		array( __( 'Templates and template parts', 'techzei-magazine-theme' ), techzei_tt5_settings_site_editor_url( '/wp_template' ) ),
		array( __( 'Footer copy, India mark, and builder credit', 'techzei-magazine-theme' ), techzei_tt5_settings_site_editor_url( '/wp_template_part/footer' ) ),
		array( __( 'Per-post article template', 'techzei-magazine-theme' ), admin_url( 'edit.php?post_type=post' ) ),
		array( __( 'Comments and moderation', 'techzei-magazine-theme' ), admin_url( 'options-discussion.php' ) ),
		array( __( 'Reading settings and archive page size', 'techzei-magazine-theme' ), admin_url( 'options-reading.php' ) ),
	);

	echo '<ul>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static list wrapper.
	foreach ( $links as $link ) {
		printf( '<li><a href="%1$s">%2$s</a></li>', esc_url( $link[1] ), esc_html( $link[0] ) );
	}
	echo '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static list wrapper.
	printf( '<p class="description">%s</p>', esc_html__( 'These shortcuts open WordPress-owned screens. Techzei Settings stores no duplicate logo, colour, menu, footer, SEO, comment, or template data.', 'techzei-magazine-theme' ) );
}

/**
 * Render Appearance > Techzei Settings.
 *
 * @return void
 */
function techzei_tt5_settings_page() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage Techzei settings.', 'techzei-magazine-theme' ), 403 );
	}

	$settings = techzei_tt5_get_settings();
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'Techzei Settings', 'techzei-magazine-theme' ); ?></h1>
		<p><?php echo esc_html__( 'Theme behaviour defaults for Techzei Magazine Theme. Use the Site Editor for visual styles, branding, menus, footer content, and template composition.', 'techzei-magazine-theme' ); ?></p>
		<?php
		if ( isset( $_GET['settings-reset'] ) && '1' === $_GET['settings-reset'] ) {
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html__( 'Techzei settings were restored to their defaults. Site Editor customisations and content were not changed.', 'techzei-magazine-theme' ) );
		}
		techzei_tt5_settings_dependency_notices( $settings );
		settings_errors( TECHZEI_TT5_SETTINGS_OPTION );
		?>
		<form action="options.php" method="post">
			<?php
			settings_fields( TECHZEI_TT5_SETTINGS_GROUP );
			do_settings_sections( TECHZEI_TT5_SETTINGS_PAGE );
			submit_button( __( 'Save changes', 'techzei-magazine-theme' ) );
			?>
		</form>

		<hr />
		<h2><?php echo esc_html__( 'WordPress editor ownership', 'techzei-magazine-theme' ); ?></h2>
		<?php techzei_tt5_settings_shortcuts(); ?>

		<hr />
		<h2><?php echo esc_html__( 'Restore Techzei defaults', 'techzei-magazine-theme' ); ?></h2>
		<p><?php echo esc_html__( 'This removes only the Techzei settings option. It does not reset templates, template parts, Global Styles, menus, posts, media, comments, Yoast, or any other plugin settings.', 'techzei-magazine-theme' ); ?></p>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" onsubmit="return window.confirm('<?php echo esc_js( __( 'Restore only Techzei settings to their defaults?', 'techzei-magazine-theme' ) ); ?>');">
			<input type="hidden" name="action" value="techzei_tt5_restore_defaults" />
			<?php wp_nonce_field( 'techzei_tt5_restore_defaults' ); ?>
			<?php submit_button( __( 'Restore Techzei defaults', 'techzei-magazine-theme' ), 'secondary', 'submit', false ); ?>
		</form>

		<hr />
		<h2><?php echo esc_html__( 'Theme information', 'techzei-magazine-theme' ); ?></h2>
		<p>
			<?php
			$theme = wp_get_theme();
			printf(
				/* translators: 1: theme version, 2: parent theme name, 3: parent theme version. */
				esc_html__( 'Techzei Magazine Theme %1$s · Parent: %2$s %3$s', 'techzei-magazine-theme' ),
				esc_html( $theme->get( 'Version' ) ),
				esc_html( $theme->parent() ? $theme->parent()->get( 'Name' ) : __( 'Not detected', 'techzei-magazine-theme' ) ),
				esc_html( $theme->parent() ? $theme->parent()->get( 'Version' ) : '—' )
			);
			?>
		</p>
		<p><a href="<?php echo esc_url( 'https://github.com/Techzei/techzei-magazine-theme' ); ?>"><?php echo esc_html__( 'Open theme documentation', 'techzei-magazine-theme' ); ?></a></p>
	</div>
	<?php
}

/**
 * Handle the scoped default reset action.
 *
 * @return void
 */
function techzei_tt5_restore_defaults() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to restore Techzei settings.', 'techzei-magazine-theme' ), 403 );
	}

	check_admin_referer( 'techzei_tt5_restore_defaults' );
	delete_option( TECHZEI_TT5_SETTINGS_OPTION );

	$url = add_query_arg(
		array(
			'page'            => TECHZEI_TT5_SETTINGS_PAGE,
			'settings-reset'  => '1',
		),
		admin_url( 'themes.php' )
	);
	wp_safe_redirect( $url );
	exit;
}
add_action( 'admin_post_techzei_tt5_restore_defaults', 'techzei_tt5_restore_defaults' );
