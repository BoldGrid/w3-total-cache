<?php
/**
 * File: Cdn_StackPath2_Popup.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_StackPath2_Popup
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */
class Cdn_StackPath2_Popup {
	/**
	 * Initializes AJAX handlers for StackPath CDN integration.
	 *
	 * Registers AJAX actions for various operations like listing stacks,
	 * sites, and configuring a site.
	 *
	 * @return void
	 */
	public static function w3tc_ajax() {
		$o = new Cdn_StackPath2_Popup();

		add_action( 'w3tc_ajax_cdn_stackpath2_intro', array( $o, 'w3tc_ajax_cdn_stackpath2_intro' ) );
		add_action( 'w3tc_ajax_cdn_stackpath2_list_stacks', array( $o, 'w3tc_ajax_cdn_stackpath2_list_stacks' ) );
		add_action( 'w3tc_ajax_cdn_stackpath2_list_sites', array( $o, 'w3tc_ajax_cdn_stackpath2_list_sites' ) );
		add_action( 'w3tc_ajax_cdn_stackpath2_configure_site', array( $o, 'w3tc_ajax_cdn_stackpath2_configure_site' ) );
	}

	/**
	 * Handles the AJAX request to render the introductory view.
	 *
	 * Retrieves the client ID and secret from the configuration and displays
	 * the introductory interface for StackPath CDN setup.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_stackpath2_intro() {
		$config = Dispatcher::config();

		$this->render_intro(
			array(
				'client_id'     => $config->get_string( 'cdn.stackpath2.client_id' ),
				'client_secret' => $config->get_string( 'cdn.stackpath2.client_secret' ),
			)
		);
	}

	/**
	 * Renders the introductory view.
	 *
	 * Includes the view file that displays the introductory page for StackPath CDN.
	 *
	 * @param array $details Associative array containing configuration details like
	 *                       client ID, client secret, and optional error messages.
	 * @return void
	 */
	private function render_intro( $details ) {
		$config         = Dispatcher::config();
		$url_obtain_key = W3TC_STACKPATH2_AUTHORIZE_URL;

		include W3TC_DIR . '/Cdn_StackPath2_Popup_View_Intro.php';
		exit();
	}

	/**
	 * Handles the AJAX request to list available stacks.
	 *
	 * Authenticates with the StackPath API and retrieves a list of active stacks.
	 * If a single active stack is found, proceeds to list sites within it.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_stackpath2_list_stacks() {
		$api_config = array(
			'client_id'     => Util_Request::get_string( 'client_id' ),
			'client_secret' => Util_Request::get_string( 'client_secret' ),
		);

		$api = new Cdn_StackPath2_Api( $api_config );

		try {
			$r      = $api->stacks_list();
			$stacks = $r['results'];
		} catch ( \Exception $ex ) {
			$error_message = 'Can\'t authenticate: ' . $ex->getMessage();

			$this->render_intro(
				array(
					'client_id'     => $api_config['client_id'],
					'client_secret' => $api_config['client_secret'],
					'error_message' => $error_message,
				)
			);
			exit();
		}

		$count    = 0;
		$stack_id = '';
		foreach ( $stacks as $i ) {
			if ( 'ACTIVE' === $i['status'] ) {
				$count++;
				$stack_id = $i['id'];
			}
		}

		if ( 1 === $count ) {
			$api_config['stack_id'] = $stack_id;
			$this->_w3tc_ajax_cdn_stackpath2_list_sites( $api_config );
			exit();
		}

		$details = array(
			'api_config' => $this->api_config_encode( $api_config ),
			'stacks'     => $stacks,
		);

		include W3TC_DIR . '/Cdn_StackPath2_Popup_View_Stacks.php';
		exit();
	}

	/**
	 * Handles the AJAX request to list sites in a stack.
	 *
	 * Retrieves the sites associated with a given stack ID using the StackPath API.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_stackpath2_list_sites() {
		$api_config             = $this->api_config_decode( Util_Request::get_string( 'api_config' ) );
		$api_config['stack_id'] = Util_Request::get_string( 'stack_id' );
		$this->_w3tc_ajax_cdn_stackpath2_list_sites( $api_config );
	}

	/**
	 * Internal method to list sites for a given API configuration.
	 *
	 * Fetches and displays the list of sites associated with a specific stack,
	 * including options for adding a new hostname.
	 *
	 * @param array $api_config Configuration details for StackPath API.
	 *
	 * @return void
	 */
	private function _w3tc_ajax_cdn_stackpath2_list_sites( $api_config ) {
		$api = new Cdn_StackPath2_Api( $api_config );

		try {
			$r     = $api->site_list();
			$sites = $r['results'];
		} catch ( \Exception $ex ) {
			$error_message = 'Can\'t authenticate: ' . $ex->getMessage();

			$this->render_intro(
				array(
					'client_id'     => $api_config['client_id'],
					'client_secret' => $api_config['client_secret'],
					'stack_id'      => $api_config['stack_id'],
					'error_message' => $error_message,
				)
			);
			exit();
		}

		$details = array(
			'api_config'   => $this->api_config_encode( $api_config ),
			'sites'        => $sites,
			'new_hostname' => wp_parse_url( home_url(), PHP_URL_HOST ),
		);

		include W3TC_DIR . '/Cdn_StackPath2_Popup_View_Sites.php';
		exit();
	}

	/**
	 * Handles the AJAX request to configure a site.
	 *
	 * Creates or updates a site in StackPath CDN and stores the configuration
	 * details, including client ID, stack ID, site ID, and domain information.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_stackpath2_configure_site() {
		$api_config = $this->api_config_decode( Util_Request::get_string( 'api_config' ) );
		$site_id    = Util_Request::get( 'site_id', '' );

		$api          = new Cdn_StackPath2_Api( $api_config );
		$cors_present = false;

		try {
			if ( empty( $site_id ) ) {
				// create new zone mode.
				$hostname = wp_parse_url( home_url(), PHP_URL_HOST );

				$r = $api->site_create(
					array(
						'domain'   => $hostname,
						'origin'   => array(
							'path'       => '/',
							'hostname'   => $hostname,
							'port'       => 80,
							'securePort' => 443,
						),
						'features' => array(
							'CDN',
						),
					)
				);

				$site_id = $r['site']['id'];
			}

			$r       = $api->site_dns_targets_get( $site_id );
			$domains = $r['addresses'];

			$cds = $api->site_cds_get( $site_id );

			if ( isset( $cds['configuration'] ) && isset( $cds['configuration']['staticHeader'] ) ) {
				$headers = $cds['configuration']['staticHeader'];

				$cors_present = isset( $headers[0] ) &&
					isset( $headers[0]['http'] ) &&
					preg_match( '/access\-control\-allow\-origin/i', $headers[0]['http'] );
			}
		} catch ( \Exception $ex ) {
			$this->render_intro(
				array(
					'client_id'     => $api_config['client_id'],
					'client_secret' => $api_config['client_secret'],
					'stack_id'      => $api_config['stack_id'],
					'error_message' => 'Can\'t obtain site: ' . $ex->getMessage(),
				)
			);
			exit();
		}

		$c = Dispatcher::config();
		$c->set( 'cdn.stackpath2.client_id', $api_config['client_id'] );
		$c->set( 'cdn.stackpath2.client_secret', $api_config['client_secret'] );
		$c->set( 'cdn.stackpath2.stack_id', $api_config['stack_id'] );
		$c->set( 'cdn.stackpath2.site_id', $site_id );
		$c->set( 'cdn.stackpath2.site_root_domain', $domains[0] );
		$c->set( 'cdn.stackpath2.domain', $domains );
		$c->set( 'cdn.cors_header', ! $cors_present );
		$c->save();

		include W3TC_DIR . '/Cdn_StackPath2_Popup_View_Success.php';
		exit();
	}

	/**
	 * Encodes API configuration details into a string.
	 *
	 * Converts client ID, client secret, and optional stack ID into a semicolon-separated string.
	 *
	 * @param array $c Associative array with API configuration details.
	 *
	 * @return string Encoded configuration string.
	 */
	private function api_config_encode( $c ) {
		return implode(
			';',
			array(
				$c['client_id'],
				$c['client_secret'],
				isset( $c['stack_id'] ) ? $c['stack_id'] : '',
			)
		);
	}

	/**
	 * Decodes a configuration string into an associative array.
	 *
	 * Splits a semicolon-separated string into individual API configuration details
	 * like client ID, client secret, and stack ID.
	 *
	 * @param string $s Configuration string to decode.
	 *
	 * @return array Associative array with API configuration details.
	 */
	private function api_config_decode( $s ) {
		$a = explode( ';', $s );
		return array(
			'client_id'     => $a[0],
			'client_secret' => $a[1],
			'stack_id'      => $a[2],
		);
	}
}
