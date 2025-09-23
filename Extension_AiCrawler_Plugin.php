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

		/**
		 * Fires once a post has been saved.
		 *
		 * @since 1.5.0
		 *
		 * @link https://developer.wordpress.org/reference/hooks/save_post/
		 * @link https://github.com/WordPress/wordpress-develop/blob/6.8.2/src/wp-includes/post.php#L5110
		 *
		 * @param int     $post_id Post ID.
		 * @param WP_Post $post    Post object.
		 * @param bool    $update  Whether this is an existing post being updated.
		 */
		add_action( 'save_post', array( '\W3TC\Extension_AiCrawler_Markdown', 'generate_markdown_on_save' ), 10, 3 );
		add_action( 'save_post', array( '\W3TC\Extension_AiCrawler_Markdown', 'flush_markdown_on_save' ), 10, 3 );

		/**
		 * Fires once an existing post has been updated.
		 *
		 * @since 3.0.0
		 *
		 * @link https://developer.wordpress.org/reference/hooks/post_updated/
		 * @link https://github.com/WordPress/wordpress-develop/blob/6.8.2/src/wp-includes/post.php#L5079
		 *
		 * @param int     $post_id      Post ID.
		 * @param WP_Post $post_after   Post object following the update.
		 * @param WP_Post $post_before  Post object before the update.
		 */
		add_action( 'post_updated', array( '\W3TC\Extension_AiCrawler_Markdown', 'flush_markdown_on_update' ), 10, 3 );

		/**
		 * Fires when a post is transitioned from one status to another.
		 *
		 * @since 2.3.0
		 *
		 * @link https://developer.wordpress.org/reference/hooks/transition_post_status/
		 * @link https://github.com/WordPress/wordpress-develop/blob/6.8.2/src/wp-includes/post.php#L5740
		 *
		 * @param string  $new_status New post status.
		 * @param string  $old_status Old post status.
		 * @param WP_Post $post       Post object.
		 */
		add_action( 'transition_post_status', array( '\W3TC\Extension_AiCrawler_Markdown', 'flush_markdown_on_status_change' ), 10, 3 );

		/**
		 * Filters whether a post trashing should take place.
		 *
		 * @since 4.9.0
		 * @since 6.3.0 Added the `$previous_status` parameter.
		 *
		 * @link https://developer.wordpress.org/reference/hooks/pre_trash_post/
		 * @link https://github.com/WordPress/wordpress-develop/blob/6.8.2/src/wp-includes/post.php#L3951-L3961
		 *
		 * @param bool|null $trash           Whether to go forward with trashing.
		 * @param WP_Post   $post            Post object.
		 * @param string    $previous_status The status of the post about to be trashed.
		 */
		add_filter( 'pre_trash_post', array( '\W3TC\Extension_AiCrawler_Markdown', 'flush_markdown_cache_on_trash' ), 10, 2 );

		/**
		 * Fires before a post is deleted, at the start of wp_delete_post().
		 *
		 * @since 3.2.0
		 * @since 5.5.0 Added the `$post` parameter.
		 *
		 * @link https://developer.wordpress.org/reference/hooks/before_delete_post/
		 * @link https://github.com/WordPress/wordpress-develop/blob/6.8.2/src/wp-includes/post.php#L3753
		 *
		 * @see wp_delete_post()
		 *
		 * @param int     $post_id Post ID.
		 * @param WP_Post $post    Post object.
		 */
		add_action( 'before_delete_post', array( '\W3TC\Extension_AiCrawler_Markdown', 'flush_markdown_cache_on_delete' ), 10, 1 );

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
