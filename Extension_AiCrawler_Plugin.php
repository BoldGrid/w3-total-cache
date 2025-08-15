<?php
/**
 * File: Extension_AiCrawler_Plugin.php
 *
 * @package W3TC
 * @since   X.X.X
 */

namespace W3TC;

/**
 * Class: Extension_AiCrawler_Plugin
 *
 * @since X.X.X
 */
class Extension_AiCrawler_Plugin {
	/**
	 * Initialize the extension.
	 *
	 * @since  X.X.X
	 *
	 * @return void
	 */
	public function run() {
		/**
		 * This filter is documented in Generic_AdminActions_Default.php under the read_request method.
		*/
		add_filter( 'w3tc_config_key_descriptor', array( $this, 'w3tc_config_key_descriptor' ), 10, 2 );

		// Initialize markdown generation queue.
		Extension_AiCrawler_Markdown::init();

		// If the AiCrawler Mock API class exists, run it.
		if ( class_exists( '\W3TC\Extension_AiCrawler_Mock_Api' ) ) {
			( new \W3TC\Extension_AiCrawler_Mock_Api() )->run();
		}
	}

	/**
	 * Specify config key typing for fields that need it.
	 *
	 * @since X.X.X
	 *
	 * @param mixed $descriptor Descriptor.
	 * @param mixed $key Compound key array.
	 *
	 * @return array
	 */
	public function w3tc_config_key_descriptor( $descriptor, $key ) {
		if (
			is_array( $key ) &&
			in_array(
				implode( '.', $key ),
				array(
					'aicrawler.exclusions',
					'aicrawler.exclusions_pts',
					'aicrawler.exclusions_cpts',
				),
				true
			)
		) {
			$descriptor = array( 'type' => 'array' );
		}

		return $descriptor;
	}
}

add_action(
	'wp_loaded',
	function () {
		( new Extension_AiCrawler_Plugin() )->run();

		if ( is_admin() ) {
			( new Extension_AiCrawler_Plugin_Admin() )->run();
		}
	}
);
