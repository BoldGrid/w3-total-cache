<?php
/**
 * File: Cdn_TotalCdn_Page.php
 *
 * @since   2.6.0
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdn_TotalCdn_Page
 *
 * @since 2.6.0
 */
class Cdn_TotalCdn_Page {
	/**
	 * Handles the AJAX action to purge a specific URL from Total CDN.
	 *
	 * This method listens for the `w3tc_ajax_cdn_totalcdn_purge_url` AJAX action and processes the URL purging request.
	 * It validates the URL, calls the Bunny CDN API to purge the URL, and sends a JSON response indicating success or failure.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public static function w3tc_ajax() {
		$o = new Cdn_TotalCdn_Page();

		\add_action( 'w3tc_ajax_cdn_totalcdn_purge_url', array( $o, 'w3tc_ajax_cdn_totalcdn_purge_url' ) );
	}

	/**
	 * Checks if Total CDN is active and properly configured.
	 *
	 * This method verifies if Total CDN is enabled and configured correctly by checking the necessary configuration
	 * values, including the account API key and pull zone IDs. It returns true if Total CDN is active, and false otherwise.
	 *
	 * @since 2.6.0
	 *
	 * @return bool True if Total CDN is active, false if not.
	 */
	public static function is_active() {
		$config          = Dispatcher::config();
		$cdn_enabled     = $config->get_boolean( 'cdn.enabled' );
		$cdn_engine      = $config->get_string( 'cdn.engine' );
		$cdn_zone_id     = $config->get_integer( 'cdn.totalcdn.pull_zone_id' );
		$cdnfsd_enabled  = $config->get_boolean( 'cdnfsd.enabled' );
		$cdnfsd_engine   = $config->get_string( 'cdnfsd.engine' );
		$account_api_key = $config->get_string( 'cdn.totalcdn.account_api_key' );

		return ( $account_api_key
			&& (
				( $cdn_enabled && 'totalcdn' === $cdn_engine && $cdn_zone_id )
				|| ( $cdnfsd_enabled && 'totalcdn' === $cdnfsd_engine && $cdn_zone_id )
			)
		);
	}

	/**
	 * Adds actions to the W3 Total Cache dashboard if Total CDN is active.
	 *
	 * This method appends a custom "Empty All Caches Except Total CDN" button to the W3 Total Cache dashboard if Total CDN
	 * is enabled. It also checks if other cache types can be emptied before enabling the button.
	 *
	 * @since 2.6.0
	 *
	 * @param array $actions List of existing actions in the dashboard.
	 *
	 * @return array Modified list of actions with the new button if Total CDN is active.
	 */
	public static function w3tc_dashboard_actions( array $actions ) {
		if ( self::is_active() ) {
			$modules            = Dispatcher::component( 'ModuleStatus' );
			$can_empty_memcache = $modules->can_empty_memcache();
			$can_empty_opcode   = $modules->can_empty_opcode();
			$can_empty_file     = $modules->can_empty_file();
			$can_empty_varnish  = $modules->can_empty_varnish();

			$actions[] = sprintf(
				'<input type="submit" class="dropdown-item" name="w3tc_%1$s_flush_all_except_%1$s" value="%2$s"%3$s>',
				esc_attr( 'totalcdn' ),
				sprintf(
					// translators: 1: CDN name.
					esc_attr__( 'Empty All Caches Except %1$s', 'w3-total-cache' ),
					esc_attr( W3TC_CDN_NAME )
				),
				( ! $can_empty_memcache && ! $can_empty_opcode && ! $can_empty_file && ! $can_empty_varnish ) ? ' disabled="disabled"' : ''
			);
		}

		return $actions;
	}

	/**
	 * Enqueues scripts and localizes variables for Total CDN on the admin page.
	 *
	 * This method registers and enqueues the necessary JavaScript for Total CDN functionality in the W3 Total Cache admin
	 * panel. It also localizes important variables like authorization status and localized strings for use in the script.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public static function admin_print_scripts_w3tc_cdn() {
		$config        = Dispatcher::config();
		$has_api_key   = ! empty( $config->get_string( 'cdn.totalcdn.account_api_key' ) );
		$is_authorized = $has_api_key && $config->get_string( 'cdn.totalcdn.pull_zone_id' );
		$license_key   = $config->get_string( 'plugin.license_key' );

		\wp_register_script(
			'w3tc_cdn_totalcdn',
			\plugins_url( 'Cdn_TotalCdn_Page_View.js', W3TC_FILE ),
			array( 'jquery' ),
			W3TC_VERSION,
			false
		);

		\wp_localize_script(
			'w3tc_cdn_totalcdn',
			'W3TC_TotalCdn',
			array(
				'is_authorized' => $is_authorized,
				'has_api_key'   => $has_api_key,
				'license_key'   => $license_key,
				'lang'          => array(
					'empty_url'                           => \esc_html__( 'No URL specified', 'w3-total-cache' ),
					'success_purging'                     => \esc_html__( 'Successfully purged URL', 'w3-total-cache' ),
					'error_purging'                       => \esc_html__( 'Error purging URL', 'w3-total-cache' ),
					'error_ajax'                          => \esc_html__( 'Error with AJAX', 'w3-total-cache' ),
					'error_updating'                      => \esc_html__( 'Error updating CDN configuration', 'w3-total-cache' ),
					'error_saving_custom_hostname'        => \esc_html__( 'Unable to save the custom hostname. Please check the hostname format and try again.', 'w3-total-cache' ),
					'remove_custom_hostname_confirmation' => \esc_html__( 'Are you sure you want to remove the custom hostname? This action cannot be undone.', 'w3-total-cache' ),
					'error_removing_custom_hostname'      => \esc_html__( 'Unable to remove the custom hostname. Please try again.', 'w3-total-cache' ),
					'error_unexpected'                    => \esc_html__( 'An unexpected error occurred. Please try again.', 'w3-total-cache' ),
				),
			)
		);

		\wp_enqueue_script( 'w3tc_cdn_totalcdn' );
	}

	/**
	 * Displays the configuration settings for Total CDN in the W3 Total Cache settings page.
	 *
	 * This method includes the view file for Total CDN configuration options, allowing users to modify the
	 * settings from the W3 Total Cache admin panel.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public static function w3tc_settings_cdn_boxarea_configuration() {
		$config = Dispatcher::config();

		include W3TC_DIR . '/Cdn_TotalCdn_Page_View.php';
	}

	/**
	 * Displays the URL purge settings in the W3 Total Cache admin panel.
	 *
	 * This method includes the view file for managing the Total CDN URL purge functionality, where users can specify
	 * URLs to purge from Total CDN.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public static function w3tc_purge_urls_box() {
		// Only run once, to prevent duplicate output.
		static $ran = false;
		if ( $ran ) {
			return;
		}
		$ran = true;

		// Get the configuration.
		$config = Dispatcher::config();

		// Include the view file for the purge URLs box.
		include W3TC_DIR . '/Cdn_TotalCdn_Page_View_Purge_Urls.php';
	}

	/**
	 * Processes the AJAX request to purge a specified URL from Total CDN.
	 *
	 * This method validates the provided URL, sends a purge request to the Total CDN API, and returns a JSON response
	 * indicating the success or failure of the operation. If the URL is invalid or an error occurs, a failure response
	 * is sent with the appropriate error message.
	 *
	 * Purging a URL will remove the file from the CDN cache and re-download it from your origin server.
	 * Please enter the exact CDN URL of each individual file.
	 * You can also purge folders or wildcard files using * inside of the URL path.
	 * Wildcard values are not supported if using Perma-Cache.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_totalcdn_purge_url() {
		$url = Util_Request::get_string( 'url' );

		// Check if URL starts with "http", starts with a valid protocol, and passes a URL validation check.
		if ( 0 !== \strpos( $url, 'http' ) || ! \preg_match( '~^http(s?)://(.+)~i', $url ) || ! \filter_var( $url, FILTER_VALIDATE_URL ) ) {
			\wp_send_json_error(
				array( 'error_message' => \esc_html__( 'Invalid URL', 'w3-total-cache' ) ),
				400
			);
		}

		$config          = Dispatcher::config();
		$account_api_key = $config->get_string( 'cdn.totalcdn.account_api_key' );

		$api = new Cdn_TotalCdn_Api( array( 'account_api_key' => $account_api_key ) );

		// Try to purge the URL.
		try {
			$api->purge( array( 'url' => \esc_url( $url, array( 'http', 'https' ) ) ) );
		} catch ( \Exception $ex ) {
			\wp_send_json_error( array( 'error_message' => $ex->getMessage() ), 422 );
		}

		\wp_send_json_success();
	}

	/**
	 * If Total CDN is active, adds the CDN cache purge actions to the dashboard.
	 *
	 * @param array $actions The existing dashboard actions.
	 * @return array The modified dashboard actions with CDN purge options.
	 */
	public static function total_cdn_dashboard_actions( $actions ) {
		if ( ! self::is_active() ) {
			return $actions;
		}
		$modules            = Dispatcher::component( 'ModuleStatus' );
		$can_empty_memcache = $modules->can_empty_memcache();
		$can_empty_opcode   = $modules->can_empty_opcode();
		$can_empty_file     = $modules->can_empty_file();
		$can_empty_varnish  = $modules->can_empty_varnish();

		$actions[] = sprintf(
			'<input type="submit" class="dropdown-item" name="w3tc_flush_all_except_w3tc_cdn" value="%1$s %2$s"%3$s>',
			esc_attr__( 'Empty All Caches Except', 'w3-total-cache' ),
			esc_attr( W3TC_CDN_NAME ),
			( ! $can_empty_memcache && ! $can_empty_opcode && ! $can_empty_file && ! $can_empty_varnish ) ? ' disabled="disabled"' : ''
		);

		return $actions;
	}
}
