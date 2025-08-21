<?php
/**
 * File: Extension_AiCrawler_Markdown_Server.php
 *
 * Serves stored markdown and advertises alternate markdown URLs.
 *
 * @package W3TC
 * @since   X.X.X
 */

namespace W3TC;

/**
 * Class Extension_AiCrawler_Markdown_Server
 *
 * @since X.X.X
 */
class Extension_AiCrawler_Markdown_Server {
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
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve_markdown' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_add_alternate_header' ) );
		add_action( 'wp_head', array( __CLASS__, 'maybe_add_link_tag' ) );
	}

	/**
	 * Add query var used for markdown requests.
	 *
	 * @since X.X.X
	 *
	 * @param array $vars Existing query vars.
	 *
	 * @return array
	 */
	public static function add_query_var( array $vars ) {
		$vars[] = 'w3tc_aicrawler_markdown';

		return $vars;
	}

	/**
	 * Register rewrite rule for ".md" requests.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function add_rewrite_rules() {
		add_rewrite_rule( '^(.*)\.md$', 'index.php?w3tc_aicrawler_markdown=$matches[1]', 'top' );
	}

	/**
	 * Maybe serve markdown when the query var is present.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function maybe_serve_markdown() {
		$flag = get_query_var( 'w3tc_aicrawler_markdown' );

		if ( '' === $flag ) {
			return;
		}

		$post_id = 0;

		if ( is_numeric( $flag ) ) {
			$post_id = (int) $flag;
		} else {
			$path    = ltrim( (string) $flag, '/' );
			$post_id = url_to_postid( home_url( '/' . $path ) );
		}

		if ( ! $post_id ) {
			status_header( 404 );
			exit;
		}

		$markdown = get_post_meta( $post_id, Extension_AiCrawler_Markdown::META_MARKDOWN, true );

		if ( '' === $markdown ) {
			status_header( 404 );
			exit;
		}

		$canonical = get_permalink( $post_id );

		header( 'Content-Type: text/markdown; charset=' . get_bloginfo( 'charset' ) );
		header( 'Link: <' . esc_url_raw( $canonical ) . '>; rel="canonical"', false );

		echo $markdown; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Send alternate markdown header on singular pages.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function maybe_add_alternate_header() {
		if ( get_query_var( 'w3tc_aicrawler_markdown' ) ) {
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_queried_object_id();
		$url     = get_post_meta( $post_id, Extension_AiCrawler_Markdown::META_MARKDOWN_URL, true );

		if ( $url ) {
			header( 'Link: <' . esc_url_raw( $url ) . '>; rel="alternate"; type="text/markdown"', false );
		}
	}

	/**
	 * Output alternate markdown link tag in the head.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function maybe_add_link_tag() {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_queried_object_id();
		$url     = get_post_meta( $post_id, Extension_AiCrawler_Markdown::META_MARKDOWN_URL, true );

		if ( $url ) {
			echo '<link rel="alternate" type="text/markdown" href="' . esc_url( $url ) . '" />';
		}
	}

	/**
	 * Build the markdown URL for a post.
	 *
	 * @since X.X.X
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	public static function get_markdown_url( $post_id ) {
		if ( get_option( 'permalink_structure' ) ) {
			return untrailingslashit( get_permalink( $post_id ) ) . '.md';
		}

		return add_query_arg( array( 'w3tc_aicrawler_markdown' => $post_id ), home_url( '/' ) );
	}
}
