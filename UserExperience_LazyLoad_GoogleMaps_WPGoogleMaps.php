<?php
/**
 * File: UserExperience_LazyLoad_GoogleMaps_WPGoogleMaps.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UserExperience_LazyLoad_GoogleMaps_WPGoogleMaps
 */
class UserExperience_LazyLoad_GoogleMaps_WPGoogleMaps {
	/**
	 * The URL of the script to preload. Set when a specific script is identified for lazy loading.
	 *
	 * @var string
	 */
	private $preload_url = '';

	/**
	 * Modifies the buffer before the lazy load mutator processes it.
	 * This method identifies and processes `<script>` tags in the buffer to determine if a specific
	 * script (e.g., wp-google-maps JavaScript) needs to be preloaded. If a preload URL is set, it
	 * injects a `<link>` tag for preloading the script.
	 *
	 * @param array $data {
	 *     Array of data passed to the mutator.
	 *
	 *     @type string $buffer  The HTML buffer to modify.
	 *     @type bool   $modified Whether the buffer has been modified.
	 * }
	 *
	 * @return array Modified data with updated buffer and modified flag.
	 */
	public function w3tc_lazyload_mutator_before( $data ) {
		$buffer = $data['buffer'];
		$buffer = preg_replace_callback(
			'~(<script\s[^>]+>)~i',
			array( $this, 'tag_script' ),
			$buffer
		);

		if ( ! empty( $this->preload_url ) ) {
			$preload_html = '<link rel="preload" href="' . esc_url( $this->preload_url ) . '" as="script">';

			$buffer = preg_replace(
				'~<head(\s+[^>]*)*>~Ui',
				'\\0' . $preload_html,
				$buffer,
				1
			);

			add_filter( 'w3tc_lazyload_on_initialized_javascript', array( $this, 'w3tc_lazyload_on_initialized_javascript' ) );
		}

		$data['buffer']    = $buffer;
		$data['modified'] |= ! empty( $this->preload_url );

		return $data;
	}

	/**
	 * Callback function to process `<script>` tags.
	 * This method checks if a `<script>` tag matches a specific JavaScript file (e.g., wp-google-maps).
	 * If it matches, it sets the preload URL and removes the `<script>` tag from the buffer.
	 *
	 * @param array $m Array of matches from the regex callback.
	 *
	 * @return string Modified `<script>` tag or an empty string if the script is processed for preload.
	 */
	public function tag_script( $m ) {
		$script_tag = $m[0];
		if (
			! preg_match(
				'~<script\s+[^<>]*src=["\']?([^"\'> ]+)["\'> ]~is',
				$script_tag,
				$match
			)
		) {
			return $script_tag;
		}

		$script_src = $match[1];
		$script_src = Util_Environment::url_relative_to_full( $script_src );

		if ( ! $this->starts_with( $script_src, WP_PLUGIN_URL . '/wp-google-maps/js/wpgmaps.js' ) ) {
			return $script_tag;
		}

		$this->preload_url = $script_src;

		return '';
	}

	/**
	 * Determines if a string starts with a given prefix.
	 * Utility function to check if a string starts with a specific prefix.
	 *
	 * @param string $v      The string to check.
	 * @param string $prefix The prefix to check against.
	 *
	 * @return bool True if the string starts with the prefix, false otherwise.
	 */
	private function starts_with( $v, $prefix ) {
		return substr( $v, 0, strlen( $prefix ) ) === $prefix;
	}


	/**
	 * Generates JavaScript code for lazy loading Google Maps.
	 * This method returns JavaScript code that initializes a `LazyLoad` instance for Google Maps.
	 * The code dynamically loads the Google Maps script when the map element is in view.
	 *
	 * @return string JavaScript code for lazy loading Google Maps.
	 */
	public function w3tc_lazyload_on_initialized_javascript() {
		return 'window.w3tc_lazyLazy_googlemaps_wpmaps = new LazyLoad({' .
			'elements_selector: "#wpgmza_map",' .
			'callback_enter: function(e){' .

				// w3tc_load_js function.
				'function w3tc_load_js(t,n){"use strict";var o=document.getElementsByTagName("script")[0],r=document.createElement("script");return r.src=t,r.async=!0,o.parentNode.insertBefore(r,o),n&&"function"==typeof n&&(r.onload=n),r};' .

				// hack to allow initialize-on-load script pass.
				'MYMAP = {init: function() {},placeMarkers: function() {}};' .

				'w3tc_load_js("' . esc_url( $this->preload_url ) . '", function() {InitMap()});' .
			'}});';
	}
}
