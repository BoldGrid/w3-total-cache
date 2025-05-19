<?php
/**
 * File: Cdn_TotalCdn_Popup.php
 *
 * @since   2.6.0
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdn_TotalCdn_Popup
 *
 * @since 2.6.0
 */
class Cdn_TotalCdn_Popup {
	/**
	 * Handles the AJAX request for TotalCDN related actions.
	 *
	 * This method registers multiple AJAX actions related to TotalCDN,
	 * including displaying the intro, pulling a list of pull zones, configuring
	 * a pull zone, deauthorizing, and deactivating TotalCDN.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public static function w3tc_ajax() {
		$o = new Cdn_TotalCdn_Popup();

		\add_action(
			'w3tc_ajax_cdn_totalcdn_intro',
			array( $o, 'w3tc_ajax_cdn_totalcdn_intro' )
		);

		\add_action(
			'w3tc_ajax_cdn_totalcdn_list_pull_zones',
			array( $o, 'w3tc_ajax_cdn_totalcdn_list_pull_zones' )
		);

		\add_action(
			'w3tc_ajax_cdn_totalcdn_configure_pull_zone',
			array( $o, 'w3tc_ajax_cdn_totalcdn_configure_pull_zone' )
		);

		\add_action(
			'w3tc_ajax_cdn_totalcdn_deauthorization',
			array( $o, 'w3tc_ajax_cdn_totalcdn_deauthorization' )
		);

		\add_action(
			'w3tc_ajax_cdn_totalcdn_deauthorize',
			array( $o, 'w3tc_ajax_cdn_totalcdn_deauthorize' )
		);

		\add_action(
			'w3tc_ajax_cdn_totalcdn_add_custom_hostname',
			array( $o, 'w3tc_ajax_cdn_totalcdn_add_custom_hostname' )
		);

		\add_action(
			'w3tc_ajax_cdn_totalcdn_save_custom_hostname',
			array( $o, 'w3tc_ajax_cdn_totalcdn_save_custom_hostname' )
		);

		\add_action(
			'w3tc_ajax_cdn_totalcdn_validate_dns',
			array( $o, 'w3tc_ajax_cdn_totalcdn_validate_dns' )
		);

		\add_action(
			'w3tc_ajax_cdn_totalcdn_generate_and_save_ssl',
			array( $o, 'w3tc_ajax_cdn_totalcdn_generate_and_save_ssl' )
		);
	}

	/**
	 * Handles the AJAX request to render the TotalCDN introduction page.
	 *
	 * This method fetches the account API key and renders the introductory
	 * page for TotalCDN configuration, including the option to provide the
	 * account API key if missing.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_totalcdn_intro() {
		$config          = Dispatcher::config();
		$account_api_key = $config->get_string( 'cdn.totalcdn.account_api_key' );

		// Ask for an account API key.
		$this->render_intro(
			array(
				'account_api_key' => empty( $account_api_key ) ? null : $account_api_key,
			)
		);
	}

	/**
	 * Handles the AJAX request to generate a free SSL Certificate
	 * for a custom hostname.
	 */
	public function w3tc_ajax_cdn_totalcdn_validate_dns() {
		$config              = Dispatcher::config();
		$cdn_hostname        = $config->get_string( 'cdn.totalcdn.cdn_hostname' );
		$custom_hostname     = $config->get_string( 'cdn.totalcdn.custom_hostname' );
		$custom_hostname_dns = dns_get_record( $custom_hostname, DNS_CNAME );

		if ( false === $custom_hostname_dns || empty( $custom_hostname_dns ) ) {
			$message = sprintf(
				// translators: 1: Custom hostname, 2: CDN hostname.
				\__(
					'Your custom hostname does not have a CNAME record set. Please set a CNAME record pointing %1$s to %2$s.',
					'w3-total-cache'
				),
				'<strong>' . \esc_html( $custom_hostname ) . '</strong>',
				'<strong>' . \esc_html( $cdn_hostname ) . '</strong>'
			);
			$button_class = 'w3tc_cdn_totalcdn_done';
			include W3TC_DIR . '/Cdn_TotalCdn_Popup_View_Generate_SSL.php';
			\wp_die();
		}

		if ( $cdn_hostname !== $custom_hostname_dns[0]['target'] ) {
			$message = sprintf(
				// translators: 1: Custom hostname, 2: CDN hostname.
				\__(
					'Your custom hostname does not point to the CDN hostname. Please set a CNAME record pointing %1$s to %2$s.',
					'w3-total-cache'
				),
				'<strong>' . \esc_html( $custom_hostname ) . '</strong>',
				'<strong>' . \esc_html( $cdn_hostname ) . '</strong>'
			);
			$button_class = 'w3tc_cdn_totalcdn_done';
			include W3TC_DIR . '/Cdn_TotalCdn_Popup_View_Generate_SSL.php';
			\wp_die();
		}

		$message = \esc_html__(
			'CNAME DNS configuration Confirmed. It may take a minute or two to generate after clicking the "OK" button.',
			'w3-total-cache'
		);
		$button_class = 'w3tc_cdn_totalcdn_generate_and_save_ssl';
		include W3TC_DIR . '/Cdn_TotalCdn_Popup_View_Generate_SSL.php';
		\wp_die();
	}

	public function w3tc_ajax_cdn_totalcdn_generate_and_save_ssl() {
		$config          = Dispatcher::config();
		$account_api_key = $config->get_string( 'cdn.totalcdn.account_api_key' );
		$pull_zone_id    = $config->get_string( 'cdn.totalcdn.pull_zone_id' );
		$custom_hostname = $config->get_string( 'cdn.totalcdn.custom_hostname' );
		$api             = new Cdn_TotalCdn_Api(
			array(
				'account_api_key' => $account_api_key,
				'pull_zone_id'    => $pull_zone_id,
			)
		);

		try {
			$api->load_free_certificate( $custom_hostname );
		} catch ( \Exception $ex ) {
			error_log( 'Generate Free Certificate: ' . $ex->getMessage() );
			$message = \esc_html__(
				'Failed to generate SSL certificate. Please try again later.',
				'w3-total-cache'
			);
			$button_class = 'w3tc_cdn_totalcdn_done';
			include W3TC_DIR . '/Cdn_TotalCdn_Popup_View_Generate_SSL.php';
			\wp_die();
		}

		$config->set( 'cdn.totalcdn.custom_hostname_ssl_loaded', true );
		$config->save();

		$message = \esc_html__(
			'Successfully generated and loaded SSL certificate.',
			'w3-total-cache'
		);

		$button_class = 'w3tc_cdn_totalcdn_done';

		include W3TC_DIR . '/Cdn_TotalCdn_Popup_View_Generate_SSL.php';
		\wp_die();
	}

	/**
	 * Handles the AJAX request to add a custom hostname to the pull zone.
	 *
	 * This method validates the custom hostname and adds it to the pull zone
	 */
	public function w3tc_ajax_cdn_totalcdn_add_custom_hostname() {
		$config       = Dispatcher::config();
		$cdn_hostname = $config->get_string( 'cdn.totalcdn.cdn_hostname' );
		include W3TC_DIR . '/Cdn_TotalCdn_Popup_View_Add_Custom_Hostname.php';
		\wp_die();
	}

	/**
	 * Handles the action to save a custum hostname to the pull zone.
	 *
	 * This method listens for the `w3tc_ajax_cdn_totalcdn_save_custom_hostname` AJAX action and processes the request.
	 * It validates the hostname, calls the Bunny CDN API to add the custom hostname,
	 * and sends a JSON response indicating success or failure.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public static function w3tc_ajax_cdn_totalcdn_save_custom_hostname() {
		$hostname = Util_Request::get_string( 'custom_hostname' );

		if ( empty( $hostname ) || ! filter_var( $hostname, FILTER_VALIDATE_DOMAIN ) ) {
			\wp_send_json_error(
				array( 'error_message' => \esc_html__( 'Invalid hostname', 'w3-total-cache' ) ),
				400
			);
		}

		$config          = Dispatcher::config();
		$account_api_key = $config->get_string( 'cdn.totalcdn.account_api_key' );
		$pull_zone_id    = $config->get_string( 'cdn.totalcdn.pull_zone_id' );

		$api = new Cdn_TotalCdn_Api( array( 'account_api_key' => $account_api_key, 'pull_zone_id' => $pull_zone_id ) );

		try {
			$api->add_custom_hostname( $hostname );
		} catch ( \Exception $ex ) {
			\wp_send_json_error( array( 'error_message' => $ex->getMessage() ), 422 );
		}

		// Save the custom hostname.
		$config->set( 'cdn.totalcdn.custom_hostname', $hostname );
		$config->save();

		\wp_send_json_success();
	}

	/**
	 * Handles the AJAX request to list TotalCDN pull zones.
	 *
	 * This method retrieves and displays a list of pull zones from TotalCDN
	 * after authenticating with the provided account API key. If an error
	 * occurs, the user is prompted to reauthorize.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_totalcdn_list_pull_zones() {
		$account_api_key = Util_Request::get_string( 'account_api_key' );
		$api             = new Cdn_TotalCdn_Api( array( 'account_api_key' => $account_api_key ) );

		// Try to retrieve pull zones.
		try {
			$pull_zones = $api->list_pull_zones();
		} catch ( \Exception $ex ) {
			// Reauthorize: Ask for a new account API key.
			$this->render_intro(
				array(
					'account_api_key' => empty( $account_api_key ) ? null : $account_api_key,
					'error_message'   => \esc_html( \__( 'Cannot list pull zones', 'w3-total-cache' ) . '; ' . $ex->getMessage() ),
				)
			);
		}

		// Save the account API key, if added or changed.
		$config = Dispatcher::config();

		if ( $config->get_string( 'cdn.totalcdn.account_api_key' ) !== $account_api_key ) {
			$config->set( 'cdn.totalcdn.account_api_key', $account_api_key );
			$config->save();
		}

		// Print the view.
		$server_ip = ! empty( $_SERVER['SERVER_ADDR'] ) && \filter_var( \wp_unslash( $_SERVER['SERVER_ADDR'] ), FILTER_VALIDATE_IP ) ?
			\filter_var( \wp_unslash( $_SERVER['SERVER_ADDR'] ), FILTER_SANITIZE_URL ) : null;

		$details = array(
			'pull_zones'           => $pull_zones,
			'suggested_origin_url' => \home_url(), // Suggested origin URL or IP.
			'suggested_zone_name'  => \substr( \str_replace( '.', '-', \wp_parse_url( \home_url(), PHP_URL_HOST ) ), 0, 60 ), // Suggested pull zone name.
			'pull_zone_id'         => $config->get_integer( 'cdn.totalcdn.pull_zone_id' ),
		);

		foreach ( $details['pull_zones'] as $key => $pull_zone ) {
			if ( $details['suggested_zone_name'] === $pull_zone['Name'] ) {
				$details['pull_zone_id'] = $pull_zone['Id'];
				$details['cdn_hostname'] = $pull_zone['ExtCdnDomain'];
			}
		}

		include W3TC_DIR . '/Cdn_TotalCdn_Popup_View_Pull_Zones.php';
		\wp_die();
	}

	/**
	 * Handles the AJAX request to configure a TotalCDN pull zone.
	 *
	 * This method configures an existing or new pull zone in TotalCDN. If
	 * a pull zone is not selected, a new one is created. The method also
	 * applies default edge rules to the newly created pull zone.
	 *
	 * @since 2.6.0
	 *
	 * @see Cdn_TotalCdn_Api::get_default_edge_rules()
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_totalcdn_configure_pull_zone() {
		$config           = Dispatcher::config();
		$account_api_key  = $config->get_string( 'cdn.totalcdn.account_api_key' );
		$pull_zone_id     = Util_Request::get_integer( 'pull_zone_id' );
		$origin_url       = Util_Request::get_string( 'origin_url' ); // Origin URL or IP.
		$name             = Util_Request::get_string( 'name' ); // Pull zone name.
		$cdn_hostname     = Util_Request::get_string( 'cdn_hostname' ); // Pull zone CDN hostname (system).
		$custom_hostnames = Util_Request::get_string( 'custom_hostnames' ); // Custom hostnames.
		$custom_hostnames = \explode( ',', $custom_hostnames );

		// If not selecting a pull zone. then create a new one.
		if ( empty( $pull_zone_id ) ) {
			$api = new Cdn_TotalCdn_Api( array( 'account_api_key' => $account_api_key ) );

			// Try to create a new pull zone.
			try {
				$response = $api->add_pull_zone(
					array(
						'Name'                  => $name, // The name/hostname for the pull zone where the files will be accessible; only letters, numbers, and dashes.
						'OriginUrl'             => $origin_url, // Origin URL or IP (with optional port number).
						'CacheErrorResponses'   => true, // If enabled, total.net will temporarily cache error responses (304+ HTTP status codes) from your servers for 5 seconds to prevent DDoS attacks on your origin. If disabled, error responses will be set to no-cache.
						'DisableCookies'        => false, // Determines if the Pull Zone should automatically remove cookies from the responses.
						'EnableTLS1'            => false, // TLS 1.0 was deprecated in 2018.
						'EnableTLS1_1'          => false, // TLS 1.1 was EOL's on March 31,2020.
						'ErrorPageWhitelabel'   => true, // Any total.net branding will be removed from the error page and replaced with a generic term.
						'OriginHostHeader'      => \wp_parse_url( \home_url(), PHP_URL_HOST ), // Sets the host header that will be sent to the origin.
						'UseStaleWhileUpdating' => true, // Serve stale content while updating.  If Stale While Updating is enabled, cache will not be refreshed if the origin responds with a non-cacheable resource.
						'UseStaleWhileOffline'  => true, // Serve stale content if the origin is offline.
					)
				);

				error_log( 'Pull Zone Created: ' . json_encode( $response ) ) ;

				$pull_zone_id = (int) $response['Id'];
				$name         = $response['Name'];
				$cdn_hostname = $response['ExtCdnDomain'];
			} catch ( \Exception $ex ) {
				// Reauthorize: Ask for a new account API key.
				$this->render_intro(
					array(
						'account_api_key' => empty( $account_api_key ) ? null : $account_api_key,
						'error_message'   => \esc_html( \__( 'Cannot select or add a pull zone', 'w3-total-cache' ) . '; ' . $ex->getMessage() ),
					)
				);
			}

			// Initialize an error messages array.
			$error_messages = array();

			// Add Edge Rules.
			foreach ( Cdn_TotalCdn_Api::get_default_edge_rules() as $edge_rule ) {
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
		$config->set( 'cdn.totalcdn.pull_zone_id', $pull_zone_id );
		$config->set( 'cdn.totalcdn.name', $name );
		$config->set( 'cdn.totalcdn.origin_url', $origin_url );
		$config->set( 'cdn.totalcdn.cdn_hostname', $cdn_hostname );
		$config->set( 'cdn.totalcdn.custom_hostnames', $custom_hostnames );
		$config->save();

		// Print success view.
		include W3TC_DIR . '/Cdn_TotalCdn_Popup_View_Configured.php';
		\wp_die();
	}

	/**
	 * Handles the AJAX request for deauthorization of TotalCDN.
	 *
	 * This method renders a page that allows the user to deauthorize TotalCDN
	 * and optionally delete the pull zone associated with the current configuration.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_totalcdn_deauthorization() {
		$config              = Dispatcher::config();
		$origin_url          = $config->get_string( 'cdn.totalcdn.origin_url' ); // Origin URL or IP.
		$name                = $config->get_string( 'cdn.totalcdn.name' ); // Pull zone name.
		$cdn_hostname        = $config->get_string( 'cdn.totalcdn.cdn_hostname' ); // Pull zone CDN hostname.
		$cdn_pull_zone_id    = $config->get_integer( 'cdn.totalcdn.pull_zone_id' ); // CDN pull zone id.
		$cdnfsd_pull_zone_id = $config->get_integer( 'cdnfsd.totalcdn.pull_zone_id' ); // CDN FSD pull zone id.

		// Present details and ask to deauthorize and optionally delete the pull zone.
		include W3TC_DIR . '/Cdn_TotalCdn_Popup_View_Deauthorize.php';
		\wp_die();
	}

	/**
	 * Handles the AJAX request to deauthorize TotalCDN and optionally delete the pull zone.
	 *
	 * This method removes the TotalCDN pull zone configuration and deauthorizes
	 * the API key. It also provides an option to delete the pull zone if requested.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_totalcdn_deauthorize() {
		$config              = Dispatcher::config();
		$account_api_key     = $config->get_string( 'cdn.totalcdn.account_api_key' );
		$cdn_pull_zone_id    = $config->get_integer( 'cdn.totalcdn.pull_zone_id' ); // CDN pull zone id.
		$cdnfsd_pull_zone_id = $config->get_integer( 'cdnfsd.totalcdn.pull_zone_id' ); // CDN FSD pull zone id.
		$delete_pull_zone    = Util_Request::get_string( 'delete_pull_zone' );

		// Delete pull zone, if requested.
		if ( 'yes' === $delete_pull_zone ) {
			$api = new Cdn_TotalCdn_Api( array( 'account_api_key' => $account_api_key ) );

			// Try to delete pull zone.
			try {
				$api->delete_pull_zone( $cdn_pull_zone_id );
			} catch ( \Exception $ex ) {
				$delete_error_message = $ex->getMessage();
			}

			// If the same pull zone is used for FSD, then deauthorize that too.
			if ( ! empty( $cdn_pull_zone_id ) && $cdn_pull_zone_id === $cdnfsd_pull_zone_id ) {
				$config->set( 'cdnfsd.totalcdn.pull_zone_id', null );
				$config->set( 'cdnfsd.totalcdn.name', null );
				$config->set( 'cdnfsd.totalcdn.origin_url', null );
				$config->set( 'cdnfsd.totalcdn.cdn_hostname', null );
			}
		}

		$config->set( 'cdn.totalcdn.pull_zone_id', null );
		$config->set( 'cdn.totalcdn.name', null );
		$config->set( 'cdn.totalcdn.origin_url', null );
		$config->set( 'cdn.totalcdn.cdn_hostname', null );
		$config->set( 'cdn.totalcdn.custom_hostname', null );
		$config->set( 'cdn.totalcdn.custom_hostname_ssl_loaded', null );
		$config->save();

		// Print success view.
		include W3TC_DIR . '/Cdn_TotalCdn_Popup_View_Deauthorized.php';
		\wp_die();
	}

	/**
	 * Renders the introductory page for TotalCDN setup.
	 *
	 * This private method is used to render the introductory page that includes
	 * the TotalCDN setup information and the option to input the account API key.
	 *
	 * @since 2.6.0
	 *
	 * @param array $details Details to pass to the view.
	 *
	 * @return void
	 */
	private function render_intro( array $details ) {
		include W3TC_DIR . '/Cdn_TotalCdn_Popup_View_Intro.php';
		\wp_die();
	}
}
