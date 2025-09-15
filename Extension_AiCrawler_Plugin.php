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
	 * Option name used to track rewrite rule flushing.
	 *
	 * @since X.X.X
	 *
	 * @var string
	 */
	const OPTION_REWRITE_VERSION = 'w3tc_aicrawler_rewrite_rules_version';
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

		// Set up serving and discovery of markdown content.
		Extension_AiCrawler_Markdown_Server::init();

		// Serve dynamically generated llms.txt file.
		Extension_AiCrawler_LlmsTxt_Server::init();

		// If the AiCrawler Mock API class exists, run it.
		if ( class_exists( '\W3TC\Extension_AiCrawler_Mock_Api' ) ) {
			( new \W3TC\Extension_AiCrawler_Mock_Api() )->run();
		}

		add_action( 'save_post', array( '\W3TC\Extension_AiCrawler_Markdown', 'generate_markdown_on_save' ), 10, 3 );

		// Ensure rewrite rules are flushed when needed.
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite_rules' ), 99 );
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

		/**
		 * Flush rewrite rules when the plugin version changes.
		 *
		 * @since X.X.X
		 *
		 * @return void
		 */
	public static function maybe_flush_rewrite_rules() {
			$version = get_option( self::OPTION_REWRITE_VERSION );

		if ( W3TC_VERSION !== $version ) {
				flush_rewrite_rules();
				update_option( self::OPTION_REWRITE_VERSION, W3TC_VERSION );
		}
	}
}

add_action(
	'plugins_loaded',
	function () {
		// Check if the environment is allowed to run the AI Crawler extension.
		if ( ! Extension_AiCrawler_Util::is_allowed_env() ) {
			return;
		}

		( new Extension_AiCrawler_Plugin() )->run();

		if ( is_admin() ) {
			( new Extension_AiCrawler_Plugin_Admin() )->run();
		}
	}
);
