<?php
/**
 * File: Cdnfsd_StackPath2_Popup.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdnfsd_StackPath2_Popup
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */
class Cdnfsd_StackPath2_Popup {
	/**
	 * Sets up the AJAX requests for the StackPath2 CDN functionality.
	 *
	 * @return void
	 */
	public static function w3tc_ajax() {
		$o = new Cdnfsd_StackPath2_Popup();

		add_action( 'w3tc_ajax_cdn_stackpath2_fsd_intro', array( $o, 'w3tc_ajax_cdn_stackpath2_fsd_intro' ) );
		add_action( 'w3tc_ajax_cdn_stackpath2_fsd_list_stacks', array( $o, 'w3tc_ajax_cdn_stackpath2_fsd_list_stacks' ) );
		add_action( 'w3tc_ajax_cdn_stackpath2_fsd_list_sites', array( $o, 'w3tc_ajax_cdn_stackpath2_fsd_list_sites' ) );
		add_action( 'w3tc_ajax_cdn_stackpath2_fsd_configure_site', array( $o, 'w3tc_ajax_cdn_stackpath2_fsd_configure_site' ) );
	}

	/**
	 * Displays the introductory view for the StackPath2 CDN configuration.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_stackpath2_fsd_intro() {
		$config = Dispatcher::config();

		$this->render_intro(
			array(
				'client_id'     => $config->get_string( 'cdnfsd.stackpath2.client_id' ),
				'client_secret' => $config->get_string( 'cdnfsd.stackpath2.client_secret' ),
				'stack_id'      => $config->get_string( 'cdnfsd.stackpath2.stack_id' ),
			)
		);
	}

	/**
	 * Renders the introductory view with the provided details.
	 *
	 * @param array $details Configuration details for rendering the view.
	 *
	 * @return void
	 */
	private function render_intro( $details ) {
		$config         = Dispatcher::config();
		$url_obtain_key = W3TC_STACKPATH2_AUTHORIZE_URL;

		include W3TC_DIR . '/Cdnfsd_StackPath2_Popup_View_Intro.php';
		exit();
	}

	/**
	 * Handles the AJAX request to list all stacks available in the StackPath2 account.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_stackpath2_fsd_list_stacks() {
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
			$this->_w3tc_ajax_cdn_stackpath2_fsd_list_sites( $api_config );
			exit();
		}

		$details = array(
			'api_config' => $this->api_config_encode( $api_config ),
			'stacks'     => $stacks,
		);

		include W3TC_DIR . '/Cdnfsd_StackPath2_Popup_View_Stacks.php';
		exit();
	}

	/**
	 * Handles the AJAX request to list all sites within a specific StackPath2 stack.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_stackpath2_fsd_list_sites() {
		$api_config             = $this->api_config_decode( Util_Request::get_string( 'api_config' ) );
		$api_config['stack_id'] = Util_Request::get_string( 'stack_id' );

		$this->_w3tc_ajax_cdn_stackpath2_fsd_list_sites( $api_config );
	}

	/**
	 * Retrieves and displays the list of sites for a specific StackPath2 stack.
	 *
	 * @param array $api_config The API configuration for authentication and stack identification.
	 *
	 * @return void
	 */
	public function _w3tc_ajax_cdn_stackpath2_fsd_list_sites( $api_config ) {
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

		include W3TC_DIR . '/Cdnfsd_StackPath2_Popup_View_Sites.php';
		exit();
	}

	/**
	 * Configures a site within the StackPath2 CDN service, either by creating a new site or updating an existing one.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_stackpath2_fsd_configure_site() {
		$api_config = $this->api_config_decode( Util_Request::get_string( 'api_config' ) );
		$site_id    = Util_Request::get( 'site_id', '' );

		$api = new Cdn_StackPath2_Api( $api_config );

		try {
			if ( empty( $site_id ) ) {
				// create new zone mode.
				$hostname = wp_parse_url( home_url(), PHP_URL_HOST );
				$hostname = 'an6.w3-edge.com';

				$r = $api->site_create(
					array(
						'domain'   => $hostname,
						'origin'   => array(
							'path'       => '/',
							'hostname'   => $hostname,
							'port'       => 80,
							'securePort' => 443,
						),
						'features' => array( 'CDN' ),
					)
				);

				$site_id = $r['site']['id'];
			}

			$r       = $api->site_dns_targets_get( $site_id );
			$domains = $r['addresses'];
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
		$c->set( 'cdnfsd.stackpath2.client_id', $api_config['client_id'] );
		$c->set( 'cdnfsd.stackpath2.client_secret', $api_config['client_secret'] );
		$c->set( 'cdnfsd.stackpath2.stack_id', $api_config['stack_id'] );
		$c->set( 'cdnfsd.stackpath2.site_id', $site_id );
		$c->set( 'cdnfsd.stackpath2.site_root_domain', $domains[0] );
		$c->set( 'cdnfsd.stackpath2.domain', $domains );
		$c->save();

		include W3TC_DIR . '/Cdnfsd_StackPath2_Popup_View_Success.php';
		exit();
	}

	/**
	 * Encodes the API configuration into a string format.
	 *
	 * @param array $c API configuration array to encode.
	 *
	 * @return string Encoded API configuration string.
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
	 * Decodes the API configuration string into an array.
	 *
	 * @param string $s The encoded API configuration string.
	 *
	 * @return array Decoded API configuration array.
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
