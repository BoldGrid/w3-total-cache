<?php
/**
 * File: Minify_Extract.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Minify_Extract
 */
class Minify_Extract {
	/**
	 * Extracts JavaScript file URLs from the given HTML content.
	 *
	 * Removes HTML comments from the content and identifies all JavaScript file URLs
	 * by analyzing `<script>` tags with a `src` attribute.
	 *
	 * w3tc-url-escaping: When rendered, URLs need to be escaped via
	 * htmlspecialchars instead of esc_attr to not change the way it is encoded
	 * in source html. E.g. html contains a&amp;b Using esc_attr that will not
	 * double escape it as a result config value will be a&b.
	 *
	 * @param string $content The HTML content to analyze for JavaScript file URLs.
	 *
	 * @return array An array of unique JavaScript file URLs extracted from the content.
	 */
	public static function extract_js( $content ) {
		$matches = null;
		$files   = array();

		$content = preg_replace( '~<!--.*?-->~s', '', $content );

		if ( preg_match_all( '~<script\s+[^<>]*src=["\']?([^"\']+)["\']?[^<>]*>\s*</script>~is', $content, $matches ) ) {
			$files = $matches[1];
		}

		$files = array_values( array_unique( $files ) );

		return $files;
	}

	/**
	 * Extracts CSS file URLs and associated tag information from the given HTML content.
	 *
	 * Removes HTML comments and identifies all CSS file URLs by analyzing `<link>` tags
	 * with a `rel` attribute containing 'stylesheet' and avoiding those with 'print' media.
	 * Also includes CSS files from `@import` statements in the content.
	 *
	 * @param string $content The HTML content to analyze for CSS file URLs and tags.
	 *
	 * @return array An array of arrays where each sub-array contains the matched tag and the CSS file URL.
	 */
	public static function extract_css( $content ) {
		$content = preg_replace( '~<!--.*?-->~s', '', $content );

		$tags_files = array();

		$matches = null;
		if ( preg_match_all( '~<link\s+([^>]+)/?>(.*</link>)?~Uis', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$attrs        = array();
				$attr_matches = null;
				if ( preg_match_all( '~(\w+)=["\']([^"\']*)["\']~', $match[1], $attr_matches, PREG_SET_ORDER ) ) {
					foreach ( $attr_matches as $attr_match ) {
						$attrs[ $attr_match[1] ] = trim( $attr_match[2] );
					}
				}

				if (
					isset( $attrs['href'] ) &&
					isset( $attrs['rel'] ) &&
					stristr( $attrs['rel'], 'stylesheet' ) !== false &&
					(
						! isset( $attrs['media'] ) ||
						stristr( $attrs['media'], 'print' ) === false
					)
				) {
					$tags_files[] = array( $match[0], $attrs['href'] );
				}
			}
		}

		if ( preg_match_all( '~@import\s+(url\s*)?\(?["\']?\s*([^"\'\)\s]+)\s*["\']?\)?[^;]*;?~is', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$tags_files[] = array( $match[0], $match[2] );
			}
		}

		return $tags_files;
	}
}
