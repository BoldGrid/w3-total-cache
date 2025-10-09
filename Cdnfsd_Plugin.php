<?php
/**
 * File: Cdnfsd_Plugin_Admin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdnfsd_Plugin
 *
 * W3 Total Cache CDN Plugin
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class Cdnfsd_Plugin {
	/**
	 * Config
	 *
	 * @var Config
	 */
	private $_config = null;

	/**
	 * Constructor for the Cdnfsd_Plugin class.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Runs the plugin, setting up necessary actions and filters.
	 *
	 * @return void
	 */
	public function run() {
		$engine = $this->_config->get_string( 'cdnfsd.engine' );

		if ( ! Util_Environment::is_w3tc_pro( $this->_config ) || empty( $engine ) ) {
			return;
		}

		add_filter( 'w3tc_footer_comment', array( $this, 'w3tc_footer_comment' ) );
		add_action( 'send_headers', array( $this, 'send_headers' ), 10, 0 );
		add_action( 'w3tc_flush_all', array( '\W3TC\Cdnfsd_CacheFlush', 'w3tc_flush_all' ), 3000, 1 );
		add_action( 'w3tc_flush_post', array( '\W3TC\Cdnfsd_CacheFlush', 'w3tc_flush_post' ), 3000, 3 );
		add_action( 'w3tc_flushable_posts', '__return_true', 3000 );
		add_action( 'w3tc_flush_posts', array( '\W3TC\Cdnfsd_CacheFlush', 'w3tc_flush_all' ), 3000, 1 );
		add_action( 'w3tc_flush_url', array( '\W3TC\Cdnfsd_CacheFlush', 'w3tc_flush_url' ), 3000, 2 );
		add_filter( 'w3tc_flush_execute_delayed_operations', array( '\W3TC\Cdnfsd_CacheFlush', 'w3tc_flush_execute_delayed_operations' ), 3000 );

		Util_AttachToActions::flush_posts_on_actions();
	}

	/**
	 * Send CDNFSD Headers.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function send_headers() {
		$cdnfsd_engine     = $this->_config->get_string( 'cdnfsd.engine' );
		$is_cdnfsd_enabled = $this->_config->get_boolean( 'cdnfsd.enabled' );

		if ( $is_cdnfsd_enabled && $cdnfsd_engine ) {
			@header( 'X-W3TC-CDNFSD: ' . $cdnfsd_engine ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			@header( 'X-W3TC-HOSTNAME: ' . Util_Environment::get_site_hostname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
	}

	/**
	 * Adds a footer comment with the CDN engine information.
	 *
	 * @param array $strings Array of strings to append the footer comment to.
	 * @return array Modified array of strings with the CDN footer comment.
	 */
	public function w3tc_footer_comment( $strings ) {
		$config = Dispatcher::config();
		$via    = $config->get_string( 'cdnfsd.engine' );

		$strings[] = sprintf(
			// Translators: 1 via value.
			__(
				'Content Delivery Network Full Site Delivery via %1$s',
				'w3-total-cache'
			),
			( $via ? $via : 'N/A' )
		);

		return $strings;
	}
}
