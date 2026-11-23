<?php
/**
 * File: Util_Theme.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Util_Theme
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class Util_Theme {
	/**
	 * Get
	 *
	 * @param string $themename Theme name.
	 *
	 * @return array
	 */
	public static function get( $themename ) {
		$wp_themes = self::get_themes();

		if ( is_array( $wp_themes ) && array_key_exists( $themename, $wp_themes ) ) {
			return $wp_themes[ $themename ];
		}

		return array();
	}

	/**
	 * Get a theme by its W3TC theme key (see get_theme_key()).
	 *
	 * Unlike get(), this tells apart installed themes that share a display name.
	 *
	 * @param string $theme_key Theme key.
	 *
	 * @return array|object Theme, or an empty array when no installed theme has the key.
	 */
	public static function get_by_key( $theme_key ) {
		foreach ( self::get_themes() as $theme ) {
			if ( is_array( $theme ) && ! isset( $theme['Theme Root'] ) ) {
				continue;
			}

			if ( self::get_theme_key( $theme['Theme Root'], $theme['Template'], $theme['Stylesheet'] ) === $theme_key ) {
				return $theme;
			}
		}

		return array();
	}

	/**
	 * Get a theme by W3TC theme key, falling back to the display name.
	 *
	 * Name fallback keeps links created before theme keys were used working.
	 *
	 * @param string $theme_key_or_name Theme key or theme name.
	 *
	 * @return array|object
	 */
	public static function get_by_key_or_name( $theme_key_or_name ) {
		$theme = self::get_by_key( $theme_key_or_name );

		return $theme ? $theme : self::get( $theme_key_or_name );
	}

	/**
	 * Get the W3TC theme key of the active theme.
	 *
	 * @return string
	 */
	public static function get_current_theme_key() {
		return self::get_theme_key( get_theme_root(), get_template(), get_stylesheet() );
	}

	/**
	 * Get current theme name
	 *
	 * @return string
	 */
	public static function get_current_theme_name() {
		return wp_get_theme()->get( 'Name' );
	}

	/**
	 * Get current theme
	 *
	 * @return object
	 */
	public static function get_current_theme() {
		return wp_get_theme();
	}

	/**
	 * Get themes
	 *
	 * @return object
	 */
	public static function get_themes() {
		global $wp_themes;
		if ( isset( $wp_themes ) ) {
			return $wp_themes;
		}

		$themes = array();
		foreach ( wp_get_themes() as $theme ) {
			$w3tc_name = $theme->get( 'Name' );
			// Check the array being built: $wp_themes is not set on this path.
			if ( isset( $themes[ $w3tc_name ] ) ) {
				$themes[ $w3tc_name . '/' . $theme->get_stylesheet() ] = $theme;
			} else {
				$themes[ $w3tc_name ] = $theme;
			}
		}

		return $themes;
	}

	/**
	 * Returns theme key
	 *
	 * @param string $theme_root Theme root.
	 * @param string $template   Template.
	 * @param string $stylesheet Stylesheet.
	 *
	 * @return string
	 */
	public static function get_theme_key( $theme_root, $template, $stylesheet ) {
		$theme_path = ltrim( str_replace( WP_CONTENT_DIR, '', Util_Environment::normalize_path( $theme_root ) ), '/' );

		return substr( md5( $theme_path . $template . $stylesheet ), 0, 5 );
	}

	/**
	 * Returns themes array
	 *
	 * @return array
	 */
	public static function get_themes_by_key() {
		$themes    = array();
		$wp_themes = self::get_themes();

		foreach ( $wp_themes as $wp_theme ) {
			$w3tc_theme_key            = self::get_theme_key( $wp_theme['Theme Root'], $wp_theme['Template'], $wp_theme['Stylesheet'] );
			$themes[ $w3tc_theme_key ] = $wp_theme['Name'];
		}

		return $themes;
	}

	/**
	 * Returns minify groups
	 *
	 * @param string $theme_key_or_name Theme key (or name, for older callers).
	 *
	 * @return array
	 */
	public static function get_theme_templates( $theme_key_or_name ) {
		$groups = array(
			'default' => __( 'All Templates', 'w3-total-cache' ),
		);

		$templates = self::get_theme_files( $theme_key_or_name );

		foreach ( $templates as $template ) {
			$basename = basename( $template, '.php' );

			$groups[ $basename ] = ucfirst( $basename );
		}

		return $groups;
	}

	/**
	 * Returns array of theme groups
	 *
	 * @param string $theme_key_or_name Theme key (or name, for older callers).
	 *
	 * @return array
	 */
	public static function get_theme_files( $theme_key_or_name ) {
		$patterns = array(
			'404',
			'search',
			'taxonomy((-|_).*)?',
			'front-page',
			'home',
			'index',
			'(image|video|text|audio|application).*',
			'attachment',
			'single((-|_).*)?',
			'page((-|_).*)?',
			'category((-|_).*)?',
			'tag((-|_).*)?',
			'author((-|_).*)?',
			'date',
			'archive',
			'comments-popup',
			'paged',
		);

		$templates = array();
		$theme     = self::get_by_key_or_name( $theme_key_or_name );

		if ( $theme && isset( $theme['Template Files'] ) ) {
			$template_files = (array) $theme['Template Files'];

			foreach ( $template_files as $template_file ) {
				/**
				 * Check file name
				 */
				$template = basename( $template_file, '.php' );

				foreach ( $patterns as $pattern ) {
					$regexp = '~^' . $pattern . '$~';

					if ( preg_match( $regexp, $template ) ) {
						$templates[] = $template_file;
						continue 2;
					}
				}

				/**
				 * Check get_header function call
				 */
				$template_content = @file_get_contents( $template_file );

				if ( $template_content && preg_match( '~\s*get_header[0-9_]*\s*\(~', $template_content ) ) {
					$templates[] = $template_file;
				}
			}

			sort( $templates );
			reset( $templates );
		}

		return $templates;
	}
}
