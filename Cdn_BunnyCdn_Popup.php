<?php
/**
 * File: Cdn_BunnyCDN_Popup.php
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdn_BunnyCdn_Popup
 *
 * @since X.X.X
 */
class Cdn_BunnyCdn_Popup {
	/**
	 * W3TC AJAX.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function w3tc_ajax() {
		$o = new Cdn_BunnyCdn_Popup();

		add_action( 'w3tc_ajax_cdn_bunnycdn_intro', array( $o, 'w3tc_ajax_cdn_bunnycdn_intro' ) );
		add_action( 'w3tc_ajax_cdn_bunnycdn_list_stacks', array( $o, 'w3tc_ajax_cdn_bunnycdn_list_stacks' ) );
		add_action( 'w3tc_ajax_cdn_bunnycdn_list_sites', array( $o, 'w3tc_ajax_cdn_bunnycdn_list_sites' ) );
		add_action( 'w3tc_ajax_cdn_bunnycdn_configure_site', array( $o, 'w3tc_ajax_cdn_bunnycdn_configure_site' ) );
	}

	/**
	 * W3TC AJAX: Render intro.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_bunnycdn_intro() {
		$config = Dispatcher::config();

		$this->render_intro(
			array(
				'client_id'     => $config->get_string( 'cdn.bunnycdn.client_id' ),
				'client_secret' => $config->get_string( 'cdn.bunnycdn.client_secret' ),
			)
		);
	}

	/**
	 * Render intro.
	 *
	 * @since X.X.X
	 *
	 * @param  array $details Details.
	 * @return void
	 */
	private function render_intro( $details ) {
		$config         = Dispatcher::config();
		$url_obtain_key = W3TC_BUNNYCDN_AUTHORIZE_URL;

		include W3TC_DIR . '/Cdn_BunnyCdn_Popup_View_Intro.php';
		wp_die();
	}

	/**
	 * W3TC AJAX: List stacks.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_bunnycdn_list_stacks() {
		$api_config = array(
			'client_id'     => Util_Request::get_string( 'client_id' ),
			'client_secret' => Util_Request::get_string( 'client_secret' ),
		);

		$api = new Cdn_BunnyCdn_Api( $api_config );

		try {
			$response = json_decode( $api->stacks_list(), true );
			$stacks   = $response['results'];
		} catch ( \Exception $ex ) {
			$error_message = 'Can\'t authenticate: ' . $ex->getMessage();

			$this->render_intro(
				array(
					'client_id'     => $api_config['client_id'],
					'client_secret' => $api_config['client_secret'],
					'error_message' => $error_message,
				)
			);
			wp_die();
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
			$this->_w3tc_ajax_cdn_bunnycdn_list_sites( $api_config );
			wp_die();
		}

		$details = array(
			'api_config' => $this->api_config_encode( $api_config ),
			'stacks'     => $stacks,
		);

		include W3TC_DIR . '/Cdn_BunnyCdn_Popup_View_Stacks.php';
		wp_die();
	}

	/**
	 * W3TC AJAX: List sites.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_bunnycdn_list_sites() {
		$api_config = $this->api_config_decode( Util_Request::get_string( 'api_config' ) );
		$api_config['stack_id'] = Util_Request::get_string( 'stack_id' );
		$this->_w3tc_ajax_cdn_bunnycdn_list_sites( $api_config );
	}

	/**
	 * W3TC AJAX: List sites.
	 *
	 * @since X.X.X
	 *
	 * @param array $api_config API Configuration.
	 * @return void
	 */
	private function _w3tc_ajax_cdn_bunnycdn_list_sites( array $api_config ) {
		$api = new Cdn_BunnyCdn_Api( $api_config );

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
			wp_die();
		}

		$details = array(
			'api_config'   => $this->api_config_encode( $api_config ),
			'sites'        => $sites,
			'new_hostname' => parse_url( home_url(), PHP_URL_HOST ),
		);

		include W3TC_DIR . '/Cdn_BunnyCdn_Popup_View_Sites.php';
		wp_die();
	}

	/**
	 * W3TC AJAX: Configure site.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_bunnycdn_configure_site() {
		$api_config   = $this->api_config_decode( Util_Request::get_string( 'api_config' ) );
		$site_id      = Util_Request::get( 'site_id', '' );
		$api          = new Cdn_BunnyCdn_Api( $api_config );
		$cors_present = false;

		try {
			if ( empty( $site_id ) ) {
				// Create new zone mode.
				$hostname = parse_url( home_url(), PHP_URL_HOST );

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
			$cds     = $api->site_cds_get( $site_id );

			if ( isset( $cds['configuration'] ) && isset( $cds['configuration']['staticHeader'] ) ) {
				$headers      = $cds['configuration']['staticHeader'];
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
			wp_die();
		}

		$c = Dispatcher::config();
		$c->set( 'cdn.bunnycdn.client_id', $api_config['client_id'] );
		$c->set( 'cdn.bunnycdn.client_secret', $api_config['client_secret'] );
		$c->set( 'cdn.bunnycdn.stack_id', $api_config['stack_id'] );
		$c->set( 'cdn.bunnycdn.site_id', $site_id );
		$c->set( 'cdn.bunnycdn.site_root_domain', $domains[0] );
		$c->set( 'cdn.bunnycdn.domain', $domains );
		$c->set( 'cdn.cors_header', ! $cors_present );
		$c->save();

		include W3TC_DIR . '/Cdn_BunnyCdn_Popup_View_Success.php';
		wp_die();
	}

	/**
	 * Encode the API configuration.
	 *
	 * @since X.X.X
	 *
	 * @param  array $config API configuration.
	 * @return string JSON string.
	 */
	private function api_config_encode( array $config ) {
		return wp_json_encode(
			array(
				'client_id'     => $config['client_id'],
				'client_secret' => $config['client_secret'],
				'stack_id'      => isset( $config['stack_id'] ) ? $config['stack_id'] : '',
			)
		);
	}

	/**
	 * Decode the API configuration.
	 *
	 * @since X.X.X
	 *
	 * @param  string $encoded_config JSON-encoded API configuration.
	 * @return array
	 */
	private function api_config_decode( $encoded_config ) {
		return json_decode( $encoded_config, true );
	}
}
