<?php
/**
 * Loads WP_HTML_Tag_Processor from a local WordPress core for tests/test-cdn-srcset-placeholders.php.
 *
 * Core path: WP_CORE_DIR environment variable, else ~/wordpress.
 *
 * @package W3TC\Tests
 * @since   2.11.1
 */

if ( \class_exists( 'WP_HTML_Tag_Processor' ) ) {
	return true;
}

$cdnsp_core = \getenv( 'WP_CORE_DIR' );
if ( ! \is_string( $cdnsp_core ) || '' === $cdnsp_core ) {
	$cdnsp_core = \rtrim( (string) \getenv( 'HOME' ), '/' ) . '/wordpress';
}
$cdnsp_core = \rtrim( $cdnsp_core, '/' ) . '/';

if ( ! \is_file( $cdnsp_core . 'wp-includes/html-api/class-wp-html-tag-processor.php' ) ) {
	return false;
}

if ( ! \defined( 'ABSPATH' ) ) {
	\define( 'ABSPATH', $cdnsp_core );
}
if ( ! \defined( 'WPINC' ) ) {
	\define( 'WPINC', 'wp-includes' );
}

if ( ! \function_exists( '__' ) ) {
	/**
	 * Translation stub.
	 *
	 * @since 2.11.1
	 *
	 * @param string $text Text.
	 *
	 * @return string
	 */
	function __( $text ) {
		return $text;
	}
}

if ( ! \function_exists( '_doing_it_wrong' ) ) {
	/**
	 * Notice stub.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	function _doing_it_wrong() {
	}
}

if ( ! \function_exists( 'esc_url' ) ) {
	/**
	 * URL escaping stub.
	 *
	 * @since 2.11.1
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	function esc_url( $url ) {
		return $url;
	}
}

if ( ! \function_exists( 'esc_attr' ) ) {
	/**
	 * Attribute escaping stub.
	 *
	 * @since 2.11.1
	 *
	 * @param string $text Text.
	 *
	 * @return string
	 */
	function esc_attr( $text ) {
		return \htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! \function_exists( 'wp_kses_uri_attributes' ) ) {
	/**
	 * URI attribute list stub.
	 *
	 * @since 2.11.1
	 *
	 * @return array
	 */
	function wp_kses_uri_attributes() {
		return array( 'href', 'src' );
	}
}

foreach (
	array(
		'compat-utf8.php',
		'compat.php',
		'class-wp-token-map.php',
		'utf8.php',
		'html-api/html5-named-character-references.php',
		'html-api/class-wp-html-attribute-token.php',
		'html-api/class-wp-html-span.php',
		'html-api/class-wp-html-doctype-info.php',
		'html-api/class-wp-html-text-replacement.php',
		'html-api/class-wp-html-decoder.php',
		'html-api/class-wp-html-tag-processor.php',
	) as $cdnsp_file
) {
	if ( \is_file( $cdnsp_core . 'wp-includes/' . $cdnsp_file ) ) {
		require_once $cdnsp_core . 'wp-includes/' . $cdnsp_file;
	}
}

return \class_exists( 'WP_HTML_Tag_Processor' );
