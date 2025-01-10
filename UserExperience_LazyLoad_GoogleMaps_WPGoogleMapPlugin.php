<?php
/**
 * File: UserExperience_LazyLoad_GoogleMaps_WPGoogleMapPlugin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UserExperience_LazyLoad_GoogleMaps_WPGoogleMapPlugin
 */
class UserExperience_LazyLoad_GoogleMaps_WPGoogleMapPlugin {
	/**
	 * Filters the output buffer to modify Google Maps script initialization for compatibility with
	 * W3 Total Cache lazy loading.
	 *
	 * This method checks the output buffer for a specific Google Maps initialization script. If found,
	 * it modifies the script to enable lazy loading of the maps using W3 Total Cache's lazy load
	 * functionality. Additionally, it registers a callback for lazy loading initialization.
	 *
	 * @param array $data An associative array containing buffer data. The 'buffer' key holds the HTML
	 *                    content to process.
	 *
	 * @return array Modified buffer data with Google Maps lazy load compatibility adjustments.
	 */
	public function w3tc_lazyload_mutator_before( $data ) {
		$buffer = $data['buffer'];
		if ( strpos( $buffer, '<script>jQuery(document).ready(function($) {var map' ) === false ) {
			return $data;
		}

		$buffer = str_replace(
			'<script>jQuery(document).ready(function($) {var map',
			'<script>window.w3tc_wpgmp_load = (function($) {var map',
			$buffer
		);

		add_filter( 'w3tc_lazyload_on_initialized_javascript', array( $this, 'w3tc_lazyload_on_initialized_javascript' ) );

		$data['buffer']   = $buffer;
		$data['modified'] = true;

		return $data;
	}

	/**
	 * Generates the JavaScript code for initializing the lazy loading of Google Maps.
	 *
	 * This method provides the required JavaScript code for integrating Google Maps with W3 Total
	 * Cache's lazy load functionality. It sets up lazy loading for map containers and defines a
	 * callback to initialize maps when they enter the viewport.
	 *
	 * @return string JavaScript code for initializing lazy loading of Google Maps.
	 */
	public function w3tc_lazyload_on_initialized_javascript() {
		return 'window.w3tc_lazyLazy_googlemaps_wpmapplugin = new LazyLoad({' .
			'elements_selector: ".wpgmp_map_container",' .
			'callback_enter: function(e){' .
				'window.w3tc_wpgmp_load(jQuery)' .
			'}});';
	}
}
