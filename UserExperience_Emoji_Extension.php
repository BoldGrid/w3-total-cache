<?php
/**
 * File: UserExperience_Emoji_Extension.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UserExperience_Emoji_Extension
 */
class UserExperience_Emoji_Extension {
	/**
	 * Executes the necessary actions to disable emoji functionality in WordPress.
	 *
	 * This method removes WordPress's default emoji-related actions and filters to prevent emojis from being added
	 * to the HTML output, email content, and RSS feeds. Additionally, it hooks into other filters to manage TinyMCE
	 * plugins and resource hints related to emojis.
	 *
	 * @return void
	 */
	public function run() {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );

		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );

		add_filter( 'tiny_mce_plugins', array( $this, 'tiny_mce_plugins' ) );
		add_filter( 'wp_resource_hints', array( $this, 'wp_resource_hints' ), 10, 2 );
	}

	/**
	 * Filters TinyMCE plugins to remove the emoji plugin.
	 *
	 * This method checks if the provided `$plugins` array is valid, then filters it to remove the 'wpemoji' plugin
	 * from the list, which disables the emoji plugin in the TinyMCE editor.
	 *
	 * @param array $plugins An array of TinyMCE plugins.
	 *
	 * @return array The filtered array of TinyMCE plugins with 'wpemoji' removed.
	 */
	public function tiny_mce_plugins( $plugins ) {
		if ( ! is_array( $plugins ) ) {
			return array();
		}

		return array_filter(
			$plugins,
			function ( $v ) {
				return 'wpemoji' !== $v;
			}
		);
	}

	/**
	 * Filters the resource hints to remove unnecessary DNS prefetch for emoji-related resources.
	 *
	 * This method checks if the provided `$urls` is a valid array and if the `$relation_type` is 'dns-prefetch'.
	 * It then filters out the 'https://s.w.org/' URL used by WordPress for emoji DNS prefetching.
	 *
	 * @param array  $urls          An array of resource URLs.
	 * @param string $relation_type The type of relation for the resource hints (e.g., 'dns-prefetch').
	 *
	 * @return array The filtered array of resource URLs, excluding the emoji DNS prefetch URL.
	 */
	public function wp_resource_hints( $urls, $relation_type ) {
		if ( ! is_array( $urls ) || 'dns-prefetch' !== $relation_type ) {
			return $urls;
		}

		// remove s.w.org dns-prefetch used by emojis.
		return array_filter(
			$urls,
			function ( $hint ) {
				// Handle the case where each hint is an array with 'href' key.
				if ( is_array( $hint ) && isset( $hint['href'] ) ) {
					return strpos( $hint['href'], '//s.w.org' ) === false;
				}

				// Handle the case where hint is a simple string URL.
				if ( is_string( $hint ) ) {
					return strpos( $hint, '//s.w.org' ) === false;
				}

				// In any other case, keep the item.
				return true;
			}
		);
	}
}

$o = new UserExperience_Emoji_Extension();
$o->run();
