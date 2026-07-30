<?php
/**
 * File: Cdn_BunnyCdn_Popup.php
 *
 * @since   2.6.0
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdn_BunnyCdn_Popup
 *
 * @since 2.6.0
 *
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class Cdn_BunnyCdn_Popup {
	/**
	 * Handles the AJAX request for BunnyCDN related actions.
	 *
	 * This method registers multiple AJAX actions related to BunnyCDN,
	 * including displaying the intro, pulling a list of pull zones, configuring
	 * a pull zone, deauthorizing, and deactivating BunnyCDN.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public static function w3tc_ajax() {
		$w3tc_o = new Cdn_BunnyCdn_Popup();

		\add_action(
			'w3tc_ajax_cdn_bunnycdn_intro',
			array( $w3tc_o, 'w3tc_ajax_cdn_bunnycdn_intro' )
		);

		\add_action(
			'w3tc_ajax_cdn_bunnycdn_list_pull_zones',
			array( $w3tc_o, 'w3tc_ajax_cdn_bunnycdn_list_pull_zones' )
		);

		\add_action(
			'w3tc_ajax_cdn_bunnycdn_configure_pull_zone',
			array( $w3tc_o, 'w3tc_ajax_cdn_bunnycdn_configure_pull_zone' )
		);

		\add_action(
			'w3tc_ajax_cdn_bunnycdn_deauthorization',
			array( $w3tc_o, 'w3tc_ajax_cdn_bunnycdn_deauthorization' )
		);

		\add_action(
			'w3tc_ajax_cdn_bunnycdn_deauthorize',
			array( $w3tc_o, 'w3tc_ajax_cdn_bunnycdn_deauthorize' )
		);
	}

	/**
	 * Handles the AJAX request to render the BunnyCDN introduction page.
	 *
	 * This method fetches the account API key and renders the introductory
	 * page for BunnyCDN configuration, including the option to provide the
	 * account API key if missing.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_bunnycdn_intro() {
		$w3tc_config          = Dispatcher::config();
		$w3tc_account_api_key = $w3tc_config->get_string( 'cdn.bunnycdn.account_api_key' );

		// Ask for an account API key.
		$this->render_intro(
			array(
				'account_api_key' => empty( $w3tc_account_api_key ) ? null : $w3tc_account_api_key,
			)
		);
	}

	/**
	 * Handles the AJAX request to list BunnyCDN pull zones.
	 *
	 * This method retrieves and displays a list of pull zones from BunnyCDN
	 * after authenticating with the provided account API key. If an error
	 * occurs, the user is prompted to reauthorize.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_bunnycdn_list_pull_zones() {
		$w3tc_account_api_key = Util_Request::get_string( 'account_api_key' );
		$api                  = new Cdn_BunnyCdn_Api( array( 'account_api_key' => $w3tc_account_api_key ) );

		// Try to retrieve pull zones.
		try {
			$pull_zones = $api->list_pull_zones();
		} catch ( \Exception $ex ) {
			Util_Debug::log( 'bunnycdn', 'list_pull_zones failed: ' . $ex->getMessage() );
			$this->render_intro(
				array(
					'account_api_key' => empty( $w3tc_account_api_key ) ? null : $w3tc_account_api_key,
					/**
					 * Copilot review (PR #4) flagged the prior
					 * `\esc_html(...)` wrap as double-escaping the
					 * view's sink-side `esc_html()` in
					 * Cdn_BunnyCdn_Popup_View_Intro.php (cosmetic
					 * `&amp;amp;`-style entities visible to the
					 * admin). Suppliers in this code path pass raw
					 * translated strings; the view is the single
					 * escape point — matches the sec-xss skill
					 * "strip the now-redundant supplier escapes"
					 * rule.
					 */
					'error_message'   => \__( 'Cannot list pull zones; see the W3TC debug log for details.', 'w3-total-cache' ),
				)
			);
		}

		// Save the account API key, if added or changed.
		$w3tc_config = Dispatcher::config();

		if ( $w3tc_config->get_string( 'cdn.bunnycdn.account_api_key' ) !== $w3tc_account_api_key ) {
			$w3tc_config->set( 'cdn.bunnycdn.account_api_key', $w3tc_account_api_key );
			$w3tc_config->save();
		}

		// Print the view.
		$server_ip = ! empty( $_SERVER['SERVER_ADDR'] ) && \filter_var( \wp_unslash( $_SERVER['SERVER_ADDR'] ), FILTER_VALIDATE_IP ) ?
			\filter_var( \wp_unslash( $_SERVER['SERVER_ADDR'] ), FILTER_SANITIZE_URL ) : null;

		$details = array(
			'pull_zones'           => $pull_zones,
			'suggested_origin_url' => \home_url(), // Suggested origin URL or IP.
			'suggested_zone_name'  => \substr( \str_replace( '.', '-', \wp_parse_url( \home_url(), PHP_URL_HOST ) ), 0, 60 ), // Suggested pull zone name.
			'pull_zone_id'         => $w3tc_config->get_integer( 'cdn.bunnycdn.pull_zone_id' ),
		);

		include W3TC_DIR . '/Cdn_BunnyCdn_Popup_View_Pull_Zones.php';
		\wp_die();
	}

	/**
	 * Handles the AJAX request to configure a BunnyCDN pull zone.
	 *
	 * This method configures an existing or new pull zone in BunnyCDN. If
	 * a pull zone is not selected, a new one is created. The method also
	 * applies default edge rules to the newly created pull zone.
	 *
	 * @since 2.6.0
	 *
	 * @see Cdn_BunnyCdn_Api::get_default_edge_rules()
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_bunnycdn_configure_pull_zone() {
		$w3tc_config          = Dispatcher::config();
		$w3tc_account_api_key = $w3tc_config->get_string( 'cdn.bunnycdn.account_api_key' );
		$pull_zone_id         = Util_Request::get_integer( 'pull_zone_id' );
		$w3tc_origin_url      = Util_Request::get_string( 'origin_url' ); // Origin URL or IP.
		$w3tc_name            = Util_Request::get_string( 'name' ); // Pull zone name.
		$w3tc_cdn_hostname    = Util_Request::get_string( 'cdn_hostname' ); // Pull zone CDN hostname (system).

		// If not selecting a pull zone. then create a new one.
		if ( empty( $pull_zone_id ) ) {
			$api = new Cdn_BunnyCdn_Api( array( 'account_api_key' => $w3tc_account_api_key ) );

			// Try to create a new pull zone.
			try {
				$response = $api->add_pull_zone(
					array(
						'Name'                  => $w3tc_name, // The name/hostname for the pull zone where the files will be accessible; only letters, numbers, and dashes.
						'OriginUrl'             => $w3tc_origin_url, // Origin URL or IP (with optional port number).
						'CacheErrorResponses'   => true, // If enabled, bunny.net will temporarily cache error responses (304+ HTTP status codes) from your servers for 5 seconds to prevent DDoS attacks on your origin. If disabled, error responses will be set to no-cache.
						'DisableCookies'        => false, // Determines if the Pull Zone should automatically remove cookies from the responses.
						'EnableTLS1'            => false, // TLS 1.0 was deprecated in 2018.
						'EnableTLS1_1'          => false, // TLS 1.1 was EOL's on March 31,2020.
						'ErrorPageWhitelabel'   => true, // Any bunny.net branding will be removed from the error page and replaced with a generic term.
						'OriginHostHeader'      => \wp_parse_url( \home_url(), PHP_URL_HOST ), // Sets the host header that will be sent to the origin.
						'UseStaleWhileUpdating' => true, // Serve stale content while updating.  If Stale While Updating is enabled, cache will not be refreshed if the origin responds with a non-cacheable resource.
						'UseStaleWhileOffline'  => true, // Serve stale content if the origin is offline.
					)
				);

				$pull_zone_id      = (int) $response['Id'];
				$w3tc_name         = $response['Name'];
				$w3tc_cdn_hostname = $response['Hostnames'][0]['Value'];
			} catch ( \Exception $ex ) {
				Util_Debug::log( 'bunnycdn', 'configure_pull_zone failed: ' . $ex->getMessage() );
				$this->render_intro(
					array(
						'account_api_key' => empty( $w3tc_account_api_key ) ? null : $w3tc_account_api_key,
						/**
						 * Same supplier-passes-raw contract as the
						 * list_pull_zones catch above — see that
						 * comment for the rationale.
						 */
						'error_message'   => \__( 'Cannot select or add a pull zone; see the W3TC debug log for details.', 'w3-total-cache' ),
					)
				);
			}

			// Initialize an error messages array.
			$error_messages = array();

			// Add Edge Rules.
			foreach ( Cdn_BunnyCdn_Api::get_default_edge_rules() as $edge_rule ) {
				try {
					$api->add_edge_rule( $edge_rule, $pull_zone_id );
				} catch ( \Exception $ex ) {
					$error_messages[] = sprintf(
						// translators: 1: Edge Rule description/name.
						\__( 'Could not add Edge Rule "%1$s".', 'w3-total-cache' ) . '; ',
						\esc_html( $edge_rule['Description'] )
					) . $ex->getMessage();
				}
			}

			// Convert error messages array to a string.
			$error_messages = \implode( "\r\n", $error_messages );
		}

		// Save configuration.
		$w3tc_config->set( 'cdn.bunnycdn.pull_zone_id', $pull_zone_id );
		$w3tc_config->set( 'cdn.bunnycdn.name', $w3tc_name );
		$w3tc_config->set( 'cdn.bunnycdn.origin_url', $w3tc_origin_url );
		$w3tc_config->set( 'cdn.bunnycdn.cdn_hostname', $w3tc_cdn_hostname );
		$w3tc_config->save();

		// Print success view.
		include W3TC_DIR . '/Cdn_BunnyCdn_Popup_View_Configured.php';
		\wp_die();
	}

	/**
	 * Handles the AJAX request for deauthorization of BunnyCDN.
	 *
	 * This method renders a page that allows the user to deauthorize BunnyCDN
	 * and optionally delete the pull zone associated with the current configuration.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_bunnycdn_deauthorization() {
		$w3tc_config         = Dispatcher::config();
		$w3tc_origin_url     = $w3tc_config->get_string( 'cdn.bunnycdn.origin_url' ); // Origin URL or IP.
		$w3tc_name           = $w3tc_config->get_string( 'cdn.bunnycdn.name' ); // Pull zone name.
		$w3tc_cdn_hostname   = $w3tc_config->get_string( 'cdn.bunnycdn.cdn_hostname' ); // Pull zone CDN hostname.
		$cdn_pull_zone_id    = $w3tc_config->get_integer( 'cdn.bunnycdn.pull_zone_id' ); // CDN pull zone id.
		$cdnfsd_pull_zone_id = $w3tc_config->get_integer( 'cdnfsd.bunnycdn.pull_zone_id' ); // CDN FSD pull zone id.

		// Present details and ask to deauthorize and optionally delete the pull zone.
		include W3TC_DIR . '/Cdn_BunnyCdn_Popup_View_Deauthorize.php';
		\wp_die();
	}

	/**
	 * Handles the AJAX request to deauthorize BunnyCDN and optionally delete the pull zone.
	 *
	 * This method removes the BunnyCDN pull zone configuration and deauthorizes
	 * the API key. It also provides an option to delete the pull zone if requested.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_bunnycdn_deauthorize() {
		$w3tc_config          = Dispatcher::config();
		$w3tc_account_api_key = $w3tc_config->get_string( 'cdn.bunnycdn.account_api_key' );
		$cdn_pull_zone_id     = $w3tc_config->get_integer( 'cdn.bunnycdn.pull_zone_id' ); // CDN pull zone id.
		$cdnfsd_pull_zone_id  = $w3tc_config->get_integer( 'cdnfsd.bunnycdn.pull_zone_id' ); // CDN FSD pull zone id.
		$delete_pull_zone     = Util_Request::get_string( 'delete_pull_zone' );

		// Delete pull zone, if requested.
		if ( 'yes' === $delete_pull_zone ) {
			$api = new Cdn_BunnyCdn_Api( array( 'account_api_key' => $w3tc_account_api_key ) );

			// Try to delete pull zone.
			try {
				$api->delete_pull_zone( $cdn_pull_zone_id );
			} catch ( \Exception $ex ) {
				$delete_error_message = $ex->getMessage();
			}

			// If the same pull zone is used for FSD, then deauthorize that too.
			if ( ! empty( $cdn_pull_zone_id ) && $cdn_pull_zone_id === $cdnfsd_pull_zone_id ) {
				$w3tc_config->set( 'cdnfsd.bunnycdn.pull_zone_id', null );
				$w3tc_config->set( 'cdnfsd.bunnycdn.name', null );
				$w3tc_config->set( 'cdnfsd.bunnycdn.origin_url', null );
				$w3tc_config->set( 'cdnfsd.bunnycdn.cdn_hostname', null );
			}
		}

		$w3tc_config->set( 'cdn.bunnycdn.pull_zone_id', null );
		$w3tc_config->set( 'cdn.bunnycdn.name', null );
		$w3tc_config->set( 'cdn.bunnycdn.origin_url', null );
		$w3tc_config->set( 'cdn.bunnycdn.cdn_hostname', null );
		$w3tc_config->save();

		// Print success view.
		include W3TC_DIR . '/Cdn_BunnyCdn_Popup_View_Deauthorized.php';
		\wp_die();
	}

	/**
	 * Renders the introductory page for BunnyCDN setup.
	 *
	 * This private method is used to render the introductory page that includes
	 * the BunnyCDN setup information and the option to input the account API key.
	 *
	 * @since 2.6.0
	 *
	 * @param array $details Details to pass to the view.
	 *
	 * @return void
	 */
	private function render_intro( array $details ) {
		include W3TC_DIR . '/Cdn_BunnyCdn_Popup_View_Intro.php';
		\wp_die();
	}
}
