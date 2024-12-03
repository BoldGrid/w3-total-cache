<?php
/**
 * File: Varnish_Plugin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Varnish_Plugin
 */
class Varnish_Plugin {
	/**
	 * Initializes and attaches actions and filters related to cache flushing.
	 *
	 * This method sets up the necessary hooks to integrate cache flushing actions (including for Varnish and specific posts or URLs)
	 * and modifies the admin bar menu.
	 *
	 * @return void
	 */
	public function run() {
		Util_AttachToActions::flush_posts_on_actions();

		add_action( 'w3tc_flush_all', array( $this, 'varnish_flush' ), 2000, 1 );
		add_action( 'w3tc_flush_post', array( $this, 'varnish_flush_post' ), 2000, 2 );
		add_action( 'w3tc_flushable_posts', '__return_true', 2000 );
		add_action( 'w3tc_flush_posts', array( $this, 'varnish_flush' ), 2000 );
		add_action( 'w3tc_flush_url', array( $this, 'varnish_flush_url' ), 2000, 1 );

		add_filter( 'w3tc_admin_bar_menu', array( $this, 'w3tc_admin_bar_menu' ) );
	}

	/**
	 * Flushes all Varnish cache entries.
	 *
	 * This method performs a global flush of Varnish cache. If the `$extras` parameter specifies an exclusion for Varnish, the flush
	 * is not performed.
	 *
	 * @param array $extras Optional. Additional parameters to customize the flush behavior. Default is an empty array.
	 *
	 * @return mixed The result of the flush operation.
	 */
	public function varnish_flush( $extras = array() ) {
		if ( isset( $extras['only'] ) && 'varnish' !== $extras['only'] ) {
			return;
		}

		$varnishflush = Dispatcher::component( 'Varnish_Flush' );
		$v            = $varnishflush->flush();

		return $v;
	}

	/**
	 * Flushes the Varnish cache for a specific post.
	 *
	 * This method targets a specific post by its ID for cache flushing. The flush
	 * can be forced by setting the `$force` parameter to `true`.
	 *
	 * @param int  $post_id The ID of the post to flush.
	 * @param bool $force   Optional. Whether to force the flush. Default is `false`.
	 *
	 * @return mixed The result of the flush operation.
	 */
	public function varnish_flush_post( $post_id, $force = false ) {
		$varnishflush = Dispatcher::component( 'Varnish_Flush' );
		$v            = $varnishflush->flush_post( $post_id, $force );

		return $v;
	}

	/**
	 * Flushes the Varnish cache for a specific URL.
	 *
	 * This method purges the cache for a given URL.
	 *
	 * @param string $url The URL to flush from the cache.
	 *
	 * @return mixed The result of the flush operation.
	 */
	public function varnish_flush_url( $url ) {
		$varnishflush = Dispatcher::component( 'Varnish_Flush' );
		$v            = $varnishflush->flush_url( $url );

		return $v;
	}

	/**
	 * Adds a Varnish-specific item to the admin bar menu.
	 *
	 * This method extends the W3 Total Cache admin bar menu to include an option
	 * for flushing the Varnish cache.
	 *
	 * @param array $menu_items The existing admin bar menu items.
	 *
	 * @return array The modified admin bar menu items.
	 */
	public function w3tc_admin_bar_menu( $menu_items ) {
		$menu_items['20610.varnish'] = array(
			'id'     => 'w3tc_flush_varnish',
			'parent' => 'w3tc_flush',
			'title'  => __( 'Varnish Cache', 'w3-total-cache' ),
			'href'   => wp_nonce_url( admin_url( 'admin.php?page=w3tc_dashboard&amp;w3tc_flush_varnish' ), 'w3tc' ),
		);

		return $menu_items;
	}
}
