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
		add_rewrite_rule( '^llms\.txt$', 'index.php?w3tc_llms=1', 'top' );
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
			error_log( 'Extension_AiCrawler_LlmsTxt_Server::maybe_serve_llms called but query var is not set' );
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

		if ( $query->have_posts() ) {
			echo esc_html( self::build_llms_heading() );
		}

		// Output the list of markdown URLs and their canonical counterparts.
		if ( ! empty( $query->posts ) ) {
			echo "> This file contains a list of Markdown URLs in Format: [Post Title](Markdown URL): Canonical URL\n\n";
			echo "## List of Markdown URLs\n\n";
			foreach ( $query->posts as $post_id ) {
				$line = self::build_post_line( $post_id );
				if ( $line ) {
					echo esc_html( $line );
				}
			}
		}

		exit;
	}

	/**
	 * Build the heading for the llms.txt file.
	 *
	 * Builds a heading using standard marketing syntax for the llms.txt file.
	 *
	 * @since X.X.X
	 *
	 * @return string The heading string.
	 */
	public static function build_llms_heading() {
		$site_name   = get_bloginfo( 'name' );
		$site_url    = home_url( '/' );
		$date        = gmdate( 'Y-m-d H:i:s' );

		return "# llms.txt for {$site_name} ({$site_url}) - generated on {$date}\n\n";
	}

	/**
	 * Build post line for llms.txt file.
	 *
	 * Builds a line for a given post in the format [Post Title](Markdown URL): Canonical URL
	 *
	 * @since X.X.X
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string The formatted post line.
	 */
	public static function build_post_line( $post_id ) {
		$post           = get_post( $post_id );
		$markdown_url   = get_post_meta( $post_id, Extension_AiCrawler_Markdown::META_MARKDOWN_URL, true );
		$canonical      = get_permalink( $post_id );
		$post_title     = $post ? get_the_title( $post ) : 'Untitled';
		$escaped_title  = str_replace( array( "\n", "\r", ':' ), ' ', $post_title );
		$escaped_title  = preg_replace( '/\s+/', ' ', $escaped_title );
		$escaped_title  = trim( $escaped_title );
		$formatted_line = '';

		if ( $markdown_url && $canonical ) {
			$formatted_line = "[{$escaped_title}]({$markdown_url}): {$canonical}\n";
		}

		return $formatted_line;
	}
}
