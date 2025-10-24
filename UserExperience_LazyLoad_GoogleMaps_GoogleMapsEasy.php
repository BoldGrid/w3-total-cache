<?php
/**
 * File: UserExperience_LazyLoad_GoogleMaps_GoogleMapsEasy.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UserExperience_LazyLoad_GoogleMaps_GoogleMapsEasy
 */
class UserExperience_LazyLoad_GoogleMaps_GoogleMapsEasy {
	/**
	 * Stores the URL of the script to be preloaded.
	 *
	 * @var string
	 */
	private $preload_url = '';

	/**
	 * Modifies the buffer to optimize script loading for Google Maps integration.
	 *
	 * Adjusts the output buffer to include a preload tag for the Google Maps script if applicable.
	 * It also modifies the buffer for lazy loading and applies necessary filters.
	 *
	 * @param array $data {
	 *     An array containing the buffer and a modified flag.
	 *
	 *     @type string $buffer   The HTML buffer to be modified.
	 *     @type bool   $modified A flag indicating if the buffer was modified.
	 * }
	 *
	 * @return array The modified `$data` array.
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
	 * Processes a script tag to identify and handle specific Google Maps scripts.
	 *
	 * Examines the script tag to check if it matches the Google Maps Easy plugin's
	 * frontend script. If a match is found, it updates `$preload_url` and removes
	 * the tag from the buffer.
	 *
	 * @param array $m {
	 *     Matches from the regular expression applied to the buffer.
	 *
	 *     @type string $0 The full matched script tag.
	 * }
	 *
	 * @return string The modified or original script tag.
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

		if ( ! $this->starts_with( $script_src, WP_PLUGIN_URL . '/google-maps-easy/modules/gmap/js/frontend.gmap.js' ) ) {
			return $script_tag;
		}

		$this->preload_url = $script_src;
		return '';
	}

	/**
	 * Checks if a string starts with a given prefix.
	 *
	 * Utility function to determine if the provided string begins with the specified prefix.
	 *
	 * @param string $v      The string to check.
	 * @param string $prefix The prefix to look for.
	 *
	 * @return bool True if the string starts with the prefix, false otherwise.
	 */
	private function starts_with( $v, $prefix ) {
		return substr( $v, 0, strlen( $prefix ) ) === $prefix;
	}

	/**
	 * Generates the JavaScript initialization code for lazy loading Google Maps.
	 *
	 * This method returns the JavaScript code that initializes lazy loading for Google Maps
	 * using the LazyLoad library. It includes a callback to dynamically load the required script
	 * when the map element enters the viewport.
	 *
	 * @return string The JavaScript code for initializing lazy loading.
	 */
	public function w3tc_lazyload_on_initialized_javascript() {
		return 'window.w3tc_lazyLazy_googlemaps_wpmaps = new LazyLoad({' .
			'elements_selector: ".gmp_map_opts",' .
			'callback_enter: function(e){' .

				// w3tc_load_js function.
				'function w3tc_load_js(t,n){"use strict";var o=document.getElementsByTagName("script")[0],r=document.createElement("script");return r.src=t,r.async=!0,o.parentNode.insertBefore(r,o),n&&"function"==typeof n&&(r.onload=n),r};' .

				'w3tc_load_js("' . esc_url( $this->preload_url ) . '");' .
			'}});';
	}
}
