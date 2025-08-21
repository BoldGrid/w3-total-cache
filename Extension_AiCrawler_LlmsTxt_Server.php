<?php
/**
 * File: Extension_AiCrawler_LlmsTxt_Server.php
 *
 * Serves a dynamic llms.txt file listing markdown URLs.
 *
 * @package W3TC
 * @since   X.X.X
 */

namespace W3TC;

/**
 * Class Extension_AiCrawler_LlmsTxt_Server
 *
 * @since X.X.X
 */
class Extension_AiCrawler_LlmsTxt_Server {
	/**
	 * Initialize hooks.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'query_vars', array( __CLASS__, 'add_query_var' ) );
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve_llms' ), 0 );
	}

	/**
	 * Register query var for llms.txt requests.
	 *
	 * @since X.X.X
	 *
	 * @param array $vars Existing query vars.
	 *
	 * @return array
	 */
	public static function add_query_var( array $vars ) {
		$vars[] = 'w3tc_llms';

		return $vars;
	}

	/**
	 * Add rewrite rule for llms.txt.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function add_rewrite_rules() {
		add_rewrite_rule( '^llms\\.txt$', 'index.php?w3tc_llms=1', 'top' );
	}

	/**
	 * Maybe serve the dynamic llms.txt file.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function maybe_serve_llms() {
		if ( ! get_query_var( 'w3tc_llms' ) ) {
			return;
		}

		$query = new \WP_Query(
			array(
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => Extension_AiCrawler_Markdown::META_MARKDOWN,
						'compare' => '!=',
						'value'   => '',
					),
					array(
						'key'     => Extension_AiCrawler_Markdown::META_MARKDOWN_URL,
						'compare' => '!=',
						'value'   => '',
					),
				),
			)
		);

		header( 'Content-Type: text/plain; charset=' . get_bloginfo( 'charset' ) );

		if ( ! empty( $query->posts ) ) {
			foreach ( $query->posts as $post_id ) {
				$markdown_url = get_post_meta( $post_id, Extension_AiCrawler_Markdown::META_MARKDOWN_URL, true );
				$canonical    = get_permalink( $post_id );

				if ( $markdown_url && $canonical ) {
					echo esc_url_raw( $markdown_url ) . ' ' . esc_url_raw( $canonical ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			}
		}

		exit;
	}
}
