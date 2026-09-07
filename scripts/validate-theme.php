<?php
/**
 * Static validation for the Techzei Magazine Theme release package.
 *
 * @package Techzei_TT5
 */

declare( strict_types=1 );

/**
 * Read the version from the theme metadata header.
 *
 * style.css is the WordPress theme metadata source of truth. Keeping this in
 * one function prevents the validator and release workflow from drifting when
 * the theme version changes.
 *
 * @param string $root Theme root.
 * @return string|null
 */
function techzei_theme_version( string $root ): ?string {
	$style = file_get_contents( $root . '/style.css' );

	if ( false === $style || ! preg_match( '/^Version:\s*([^\r\n]+)/mi', $style, $matches ) ) {
		return null;
	}

	$version = trim( $matches[1] );

	return preg_match( '/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version ) ? $version : null;
}

/**
 * Return a path relative to the theme root for readable diagnostics.
 *
 * @param string $root Theme root.
 * @param string $path Absolute path.
 * @return string
 */
function techzei_theme_relative_path( string $root, string $path ): string {
	$relative = str_replace( $root, '', $path );

	return ltrim( str_replace( DIRECTORY_SEPARATOR, '/', $relative ), '/' );
}

/**
 * Validate the block comments in one template or template part.
 *
 * This deliberately validates the serialized block boundaries rather than
 * counting comments. A count can pass when sibling blocks are crossed or a
 * closing block names the wrong block. Attributes are checked as JSON objects
 * because WordPress parses the comment payload before rendering the block.
 *
 * @param string $content File contents.
 * @param string $label   File or fixture label.
 * @return array<int, string>
 */
function techzei_validate_block_markup( string $content, string $label ): array {
	$failures = array();
	$stack    = array();

	$pattern = '/<!--[\t ]*(\/?)wp:([A-Za-z0-9][A-Za-z0-9._\/-]*)(.*?)(\/)?[\t ]*-->/s';
	preg_match_all( $pattern, $content, $matches, PREG_OFFSET_CAPTURE );

	foreach ( $matches[0] as $index => $full_match ) {
		$offset      = $full_match[1];
		$is_closing  = '/' === $matches[1][ $index ][0];
		$name        = $matches[2][ $index ][0];
		$payload     = trim( $matches[3][ $index ][0] );
		$is_self     = '/' === $matches[4][ $index ][0];
		$line        = 1 + substr_count( substr( $content, 0, $offset ), "\n" );
		$location    = $label . ':' . $line;

		if ( $is_closing ) {
			if ( $is_self || '' !== $payload ) {
				$failures[] = $location . ' has invalid closing block syntax for ' . $name . '.';
				continue;
			}

			if ( empty( $stack ) ) {
				$failures[] = $location . ' closes ' . $name . ' without an opening block.';
				continue;
			}

			$open = array_pop( $stack );
			if ( $open['name'] !== $name ) {
				$failures[] = sprintf(
					'%s closes %s, but the open block at %s is %s.',
					$location,
					$name,
					$open['location'],
					$open['name']
				);
			}

			continue;
		}

		$attributes = array();
		if ( '' !== $payload ) {
			if ( '{' !== $payload[0] || '}' !== substr( $payload, -1 ) ) {
				$failures[] = $location . ' has a block attribute payload that is not a JSON object.';
				continue;
			}

			$attributes = json_decode( $payload, true );
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $attributes ) ) {
				$failures[] = $location . ' has invalid block attributes: ' . json_last_error_msg() . '.';
				continue;
			}

			if ( isset( $attributes['className'] ) && ! is_string( $attributes['className'] ) ) {
				$failures[] = $location . ' has a non-string className attribute.';
			}
			if ( isset( $attributes['layout'] ) && ! is_array( $attributes['layout'] ) ) {
				$failures[] = $location . ' has a non-object layout attribute.';
			}
		}

		if ( 'template-part' === $name && ( ! isset( $attributes['slug'] ) || ! is_string( $attributes['slug'] ) || '' === trim( $attributes['slug'] ) ) ) {
			$failures[] = $location . ' must declare a non-empty template-part slug.';
		}

		if ( ! $is_self ) {
			$stack[] = array(
				'name'     => $name,
				'location' => $location,
			);
		}
	}

	while ( ! empty( $stack ) ) {
		$open       = array_pop( $stack );
		$failures[] = $label . ' leaves ' . $open['name'] . ' opened at ' . $open['location'] . '.';
	}

	return $failures;
}

/**
 * Collect block template files shipped by the theme.
 *
 * @param string $root Theme root.
 * @return array<int, string>
 */
function techzei_theme_block_files( string $root ): array {
	$files = array();

	foreach ( array( 'templates', 'parts' ) as $directory ) {
		$matches = glob( $root . '/' . $directory . '/*.html' );
		if ( false !== $matches ) {
			$files = array_merge( $files, $matches );
		}
	}

	sort( $files );

	return $files;
}

/**
 * Validate the checked-in theme.
 *
 * @param string      $root             Theme root.
 * @param string|null $expected_version Optional release version to compare.
 * @return array<int, string>
 */
function techzei_validate_theme( string $root, ?string $expected_version = null ): array {
	$failures = array();
	$version  = techzei_theme_version( $root );

	if ( null === $version ) {
		$failures[] = 'style.css is missing a valid WordPress Version header.';
	} elseif ( null !== $expected_version && $expected_version !== $version ) {
		$failures[] = 'Release version ' . $expected_version . ' does not match theme metadata version ' . $version . '.';
	}

	$theme_json_path = $root . '/theme.json';
	$theme_json      = file_get_contents( $theme_json_path );
	if ( false === $theme_json ) {
		$failures[] = 'theme.json is missing.';
	} else {
		$decoded = json_decode( $theme_json, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			$failures[] = 'theme.json is not valid JSON: ' . json_last_error_msg() . '.';
		} elseif ( ! isset( $decoded['version'] ) || 3 !== (int) $decoded['version'] ) {
			$failures[] = 'theme.json must declare settings format version 3.';
		}
	}

	foreach ( techzei_theme_block_files( $root ) as $file ) {
		$content = file_get_contents( $file );
		if ( false === $content ) {
			$failures[] = 'Unable to read ' . techzei_theme_relative_path( $root, $file ) . '.';
			continue;
		}

		$failures = array_merge(
			$failures,
			techzei_validate_block_markup( $content, techzei_theme_relative_path( $root, $file ) )
		);
	}

	if ( file_exists( $root . '/parts/newsletter.html' ) ) {
		$failures[] = 'Unused newsletter template part is still present.';
	}

	if ( ! file_exists( $root . '/assets/js/navigation-fallback.js' ) ) {
		$failures[] = 'Navigation interaction script is missing.';
	}

	return $failures;
}

/**
 * Run the command-line validator when this file is executed directly.
 *
 * @return void
 */
function techzei_validate_theme_cli(): void {
	$options = getopt( '', array( 'expected-version:', 'print-version', 'root:' ) );
	$root    = isset( $options['root'] ) ? realpath( (string) $options['root'] ) : dirname( __DIR__ );

	if ( false === $root || ! is_string( $root ) ) {
		fwrite( STDERR, "Theme root does not exist.\n" );
		exit( 1 );
	}

	$version = techzei_theme_version( $root );
	if ( isset( $options['print-version'] ) ) {
		if ( null === $version ) {
			fwrite( STDERR, "Unable to read a valid Version header from style.css.\n" );
			exit( 1 );
		}

		echo $version . "\n";
		exit( 0 );
	}

	$expected_version = isset( $options['expected-version'] ) ? trim( (string) $options['expected-version'] ) : null;
	$failures         = techzei_validate_theme( $root, $expected_version );

	if ( empty( $failures ) ) {
		echo 'Theme static validation passed for version ' . ( $version ?? 'unknown' ) . ".\n";
		exit( 0 );
	}

	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

if ( isset( $_SERVER['SCRIPT_FILENAME'] ) && realpath( (string) $_SERVER['SCRIPT_FILENAME'] ) === realpath( __FILE__ ) ) {
	techzei_validate_theme_cli();
}
