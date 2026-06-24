<?php
/**
 * File: Cdn_RackSpaceCdn_Popup.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_RackSpaceCdn_Popup
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */
class Cdn_RackSpaceCdn_Popup {
	/**
	 * Handles AJAX registration for Rackspace CDN popup actions.
	 *
	 * Registers multiple AJAX handlers for Rackspace CDN popup interactions
	 * using WordPress's `add_action()` for the corresponding AJAX hooks.
	 *
	 * @return void
	 */
	public static function w3tc_ajax() {
		$w3tc_o = new Cdn_RackSpaceCdn_Popup();

		add_action( 'w3tc_ajax_cdn_rackspace_intro', array( $w3tc_o, 'w3tc_ajax_cdn_rackspace_intro' ) );
		add_action( 'w3tc_ajax_cdn_rackspace_intro_done', array( $w3tc_o, 'w3tc_ajax_cdn_rackspace_intro_done' ) );
		add_action( 'w3tc_ajax_cdn_rackspace_regions_done', array( $w3tc_o, 'w3tc_ajax_cdn_rackspace_regions_done' ) );
		add_action( 'w3tc_ajax_cdn_rackspace_services_done', array( $w3tc_o, 'w3tc_ajax_cdn_rackspace_services_done' ) );
		add_action( 'w3tc_ajax_cdn_rackspace_service_create_done', array( $w3tc_o, 'w3tc_ajax_cdn_rackspace_service_create_done' ) );
		add_action( 'w3tc_ajax_cdn_rackspace_service_get_state', array( $w3tc_o, 'w3tc_ajax_cdn_rackspace_service_get_state' ) );
		add_action( 'w3tc_ajax_cdn_rackspace_service_created_done', array( $w3tc_o, 'w3tc_ajax_cdn_rackspace_service_created_done' ) );
		add_action( 'w3tc_ajax_cdn_rackspace_service_actualize_done', array( $w3tc_o, 'w3tc_ajax_cdn_rackspace_service_actualize_done' ) );
		add_action( 'w3tc_ajax_cdn_rackspace_configure_domains', array( $w3tc_o, 'w3tc_ajax_cdn_rackspace_configure_domains' ) );
		add_action( 'w3tc_ajax_cdn_rackspace_configure_domains_done', array( $w3tc_o, 'w3tc_ajax_cdn_rackspace_configure_domains_done' ) );
	}

	/**
	 * Handles the introduction popup view for Rackspace CDN.
	 *
	 * Fetches Rackspace CDN user credentials from the configuration
	 * and renders the introductory view.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_rackspace_intro() {
		$w3tc_c = Dispatcher::config();

		$details = array(
			'user_name' => $w3tc_c->get_string( 'cdn.rackspace_cdn.user_name' ),
			'api_key'   => $w3tc_c->get_string( 'cdn.rackspace_cdn.api_key' ),
		);

		include W3TC_DIR . '/Cdn_RackSpaceCdn_Popup_View_Intro.php';
		exit();
	}

	/**
	 * Completes the introduction step and renders the Rackspace regions view.
	 *
	 * Processes the user credentials provided via AJAX and fetches region
	 * data for Rackspace CDN.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_rackspace_intro_done() {
		$this->_render_cdn_rackspace_regions(
			array(
				'user_name' => Util_Request::get_string( 'user_name' ),
				'api_key'   => Util_Request::get_string( 'api_key' ),
			)
		);
	}

	/**
	 * Renders the list of available regions for Rackspace CDN.
	 *
	 * Authenticates the user with Rackspace API and fetches regions
	 * along with the associated services.
	 *
	 * @param array $details Array containing user credentials and other necessary details.
	 *
	 * @return void
	 */
	private function _render_cdn_rackspace_regions( $details ) {
		$user_name = $details['user_name'];
		$api_key   = $details['api_key'];

		try {
			$w3tc_r = Cdn_RackSpace_Api_Tokens::authenticate( $user_name, $api_key );
		} catch ( \Exception $ex ) {
			$details = array(
				'user_name'     => $user_name,
				'api_key'       => $api_key,
				'error_message' => 'Can\'t authenticate: ' . $ex->getMessage(),
			);
			include W3TC_DIR . '/Cdn_RackSpaceCdn_Popup_View_Intro.php';
			exit();
		}

		$w3tc_r['regions'] = Cdn_RackSpace_Api_Tokens::cdn_services_by_region( $w3tc_r['services'] );

		$details['access_token']       = $w3tc_r['access_token'];
		$details['region_descriptors'] = $w3tc_r['regions'];

		// avoid fights with quotes, magic_quotes may break randomly.
		$details['region_descriptors_serialized'] = strtr( wp_json_encode( $w3tc_r['regions'] ), '"\\', '!^' );

		include W3TC_DIR . '/Cdn_RackSpaceCdn_Popup_View_Regions.php';
		exit();
	}

	/**
	 * Processes the selected region and renders available services.
	 *
	 * Validates the selected region and fetches services for the specified
	 * region using the Rackspace API.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_rackspace_regions_done() {
		$user_name          = Util_Request::get_string( 'user_name' );
		$api_key            = Util_Request::get_string( 'api_key' );
		$w3tc_access_token  = Util_Request::get_string( 'access_token' );
		$w3tc_region        = Util_Request::get_string( 'region' );
		$region_descriptors = json_decode(
			strtr( Util_Request::get_string( 'region_descriptors' ), '!^', '"\\' ),
			true
		);

		if ( ! isset( $region_descriptors[ $w3tc_region ] ) ) {
			$this->_render_cdn_rackspace_regions(
				array(
					'user_name'     => $user_name,
					'api_key'       => $api_key,
					'error_message' => 'Please select region ' . $w3tc_region,
				)
			);
		}

		$api = new Cdn_RackSpace_Api_Cdn(
			array(
				'access_token'             => $w3tc_access_token,
				'access_region_descriptor' => $region_descriptors[ $w3tc_region ],
				'new_access_required'      => '',
			)
		);

		try {
			$w3tc_services = $api->services();
		} catch ( \Exception $ex ) {
			$details = array(
				'user_name'     => $user_name,
				'api_key'       => $api_key,
				'error_message' => $ex->getMessage(),
			);
			include W3TC_DIR . '/Cdn_RackSpaceCdn_Popup_View_Intro.php';
			exit();
		}

		$details = array(
			'user_name'                           => $user_name,
			'api_key'                             => $api_key,
			'access_token'                        => $w3tc_access_token,
			'access_region_descriptor_serialized' => strtr( wp_json_encode( $region_descriptors[ $w3tc_region ] ), '"\\', '!^' ),
			'region'                              => $w3tc_region,
			// avoid fights with quotes, magic_quotes may break randomly.
			'services'                            => $w3tc_services,
		);

		include W3TC_DIR . '/Cdn_RackSpaceCdn_Popup_View_Services.php';
		exit();
	}

	/**
	 * Handles the completion of service selection for Rackspace CDN.
	 *
	 * Processes the selected service or renders the service creation view
	 * if no service is selected.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_rackspace_services_done() {
		$user_name                = Util_Request::get_string( 'user_name' );
		$api_key                  = Util_Request::get_string( 'api_key' );
		$w3tc_access_token        = Util_Request::get_string( 'access_token' );
		$access_region_descriptor = json_decode( strtr( Util_Request::get_string( 'access_region_descriptor' ), '!^', '"\\' ), true );
		$w3tc_region              = Util_Request::get_string( 'region' );
		$w3tc_service             = Util_Request::get( 'service' );

		if ( ! empty( $w3tc_service ) ) {
			$this->_render_service_actualize(
				array(
					'user_name'                           => $user_name,
					'api_key'                             => $api_key,
					'access_token'                        => $w3tc_access_token,
					'access_region_descriptor_serialized' => strtr( wp_json_encode( $access_region_descriptor ), '"\\', '!^' ),
					'region'                              => $w3tc_region,
					'service_id'                          => $w3tc_service,
				)
			);

			exit();
		}

		$home_url = get_home_url();
		$parsed   = wp_parse_url( $home_url );

		$is_https = ( 'https' === $parsed['scheme'] );

		$details = array(
			'user_name'                           => $user_name,
			'api_key'                             => $api_key,
			'access_token'                        => $w3tc_access_token,
			'access_region_descriptor_serialized' => strtr( wp_json_encode( $access_region_descriptor ), '"\\', '!^' ),
			'region'                              => $w3tc_region,
			'name'                                => '',
			'protocol'                            => ( $is_https ? 'https' : 'http' ),
			'cname_http'                          => '',
			'cname_http_style'                    => ( $is_https ? 'display: none' : '' ),
			'cname_https_prefix'                  => '',
			'cname_https_style'                   => ( $is_https ? '' : 'display: none' ),
			'origin'                              => Util_Environment::home_url_host(),
		);

		include W3TC_DIR . '/Cdn_RackSpaceCdn_Popup_View_Service_Create.php';
		exit();
	}

	/**
	 * Creates a new service in Rackspace CDN.
	 *
	 * Processes the details for service creation including domain and origin settings,
	 * and sends a request to the Rackspace API to create the service.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_rackspace_service_create_done() {
		$user_name                = Util_Request::get_string( 'user_name' );
		$api_key                  = Util_Request::get_string( 'api_key' );
		$w3tc_access_token        = Util_Request::get_string( 'access_token' );
		$access_region_descriptor = json_decode( strtr( Util_Request::get_string( 'access_region_descriptor' ), '!^', '"\\' ), true );
		$w3tc_region              = Util_Request::get_string( 'region' );
		$w3tc_name                = Util_Request::get_string( 'name' );
		$protocol                 = Util_Request::get_string( 'protocol' );
		$cname_http               = Util_Request::get_string( 'cname_http' );
		$cname_https_prefix       = Util_Request::get_string( 'cname_https_prefix' );
		$is_https                 = ( 'https' === $protocol );
		$w3tc_cname               = ( $is_https ? $cname_https_prefix : $cname_http );
		$api                      = new Cdn_RackSpace_Api_Cdn(
			array(
				'access_token'             => $w3tc_access_token,
				'access_region_descriptor' => $access_region_descriptor,
				'new_access_required'      => '',
			)
		);
		$service_id               = null;
		$access_url               = null;

		try {
			$domain = array(
				'domain'   => $w3tc_cname,
				'protocol' => ( $is_https ? 'https' : 'http' ),
			);

			if ( $is_https ) {
				$domain['certificate'] = 'shared';
			}

			$service_id = $api->service_create(
				array(
					'name'    => $w3tc_name,
					'domains' => array( $domain ),
					'origins' => array(
						array(
							'origin'         => Util_Environment::home_url_host(),
							'port'           => ( $is_https ? 443 : 80 ),
							'ssl'            => $is_https,
							'hostheadertype' => 'origin',
							'rules'          => array(),
						),
					),
					'caching' => array(
						array(
							'name' => 'default',
							'ttl'  => 86400,
						),
					),
				)
			);
		} catch ( \Exception $ex ) {
			$details = array(
				'user_name'                           => $user_name,
				'api_key'                             => $api_key,
				'access_token'                        => $w3tc_access_token,
				'access_region_descriptor_serialized' => strtr( wp_json_encode( $access_region_descriptor ), '"\\', '!^' ),
				'region'                              => $w3tc_region,
				'name'                                => $w3tc_name,
				'protocol'                            => ( $is_https ? 'https' : 'http' ),
				'cname_http'                          => $cname_http,
				'cname_http_style'                    => ( $is_https ? 'display: none' : '' ),
				'cname_https_prefix'                  => $cname_https_prefix,
				'cname_https_style'                   => ( $is_https ? '' : 'display: none' ),
				'origin'                              => Util_Environment::home_url_host(),
				'error_message'                       => $ex->getMessage(),
			);

			include W3TC_DIR . '/Cdn_RackSpaceCdn_Popup_View_Service_Create.php';
			exit();
		}

		$details = array(
			'user_name'                           => $user_name,
			'api_key'                             => $api_key,
			'access_token'                        => $w3tc_access_token,
			'access_region_descriptor_serialized' => strtr( wp_json_encode( $access_region_descriptor ), '"\\', '!^' ),
			'region'                              => $w3tc_region,
			'name'                                => $w3tc_name,
			'is_https'                            => $is_https,
			'cname'                               => $w3tc_cname,
			'service_id'                          => $service_id,
		);

		include W3TC_DIR . '/Cdn_RackSpaceCdn_Popup_View_Service_Created.php';
	}

	/**
	 * Handles AJAX request to retrieve the state of a Rackspace service.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_rackspace_service_get_state() {
		$w3tc_access_token        = Util_Request::get_string( 'access_token' );
		$access_region_descriptor = json_decode( strtr( Util_Request::get_string( 'access_region_descriptor' ), '!^', '"\\' ), true );
		$service_id               = Util_Request::get_string( 'service_id' );
		$api                      = new Cdn_RackSpace_Api_Cdn(
			array(
				'access_token'             => $w3tc_access_token,
				'access_region_descriptor' => $access_region_descriptor,
				'new_access_required'      => '',
			)
		);
		$w3tc_service             = $api->service_get( $service_id );
		$response                 = array( 'status' => 'Unknown' );

		if ( isset( $w3tc_service['status'] ) ) {
			$response['status'] = $w3tc_service['status'];
		}

		if ( isset( $w3tc_service['links_by_rel']['access_url'] ) ) {
			$response['access_url'] = $w3tc_service['links_by_rel']['access_url']['href'];
		}

		if ( isset( $w3tc_service['domains'] ) ) {
			$response['cname'] = $w3tc_service['domains'][0]['domain'];
		}

		// decode to friendly name.
		if ( 'create_in_progress' === $response['status'] ) {
			$response['status'] = 'Creation in progress...';
		}

		echo esc_html( wp_json_encode( $response ) );
	}

	/**
	 * Handles the completion of Rackspace service creation.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_rackspace_service_created_done() {
		$this->_save_config();
	}

	/**
	 * Renders the form for updating a Rackspace service with the provided details.
	 *
	 * @param array $details Array containing the service details.
	 *
	 * @return void
	 */
	private function _render_service_actualize( $details ) {
		$access_region_descriptor = json_decode( strtr( $details['access_region_descriptor_serialized'], '!^', '"\\' ), true );

		$api = new Cdn_RackSpace_Api_Cdn(
			array(
				'access_token'             => $details['access_token'],
				'access_region_descriptor' => $access_region_descriptor,
				'new_access_required'      => '',
			)
		);

		$w3tc_service = null;
		try {
			$w3tc_service = $api->service_get( $details['service_id'] );
		} catch ( \Exception $ex ) {
			$details['error_message'] = $ex->getMessage();
			include W3TC_DIR . '/Cdn_RackSpaceCdn_Popup_View_Intro.php';
			exit();
		}

		$origin   = '';
		$protocol = 'http';
		if ( isset( $w3tc_service['origins'] ) && $w3tc_service['origins'][0]['origin'] ) {
			$protocol = $w3tc_service['origins'][0]['ssl'] ? 'https' : 'http';
			$origin   = $w3tc_service['origins'][0]['origin'];
		}

		$details['name']     = $w3tc_service['name'];
		$details['protocol'] = $protocol;
		$details['origin']   = array(
			'current' => $origin,
			'new'     => Util_Environment::home_url_host(),
		);

		include W3TC_DIR . '/Cdn_RackSpaceCdn_Popup_View_Service_Actualize.php';
		exit();
	}

	/**
	 * Handles AJAX request to finalize Rackspace service updates.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_rackspace_service_actualize_done() {
		$user_name                = Util_Request::get_string( 'user_name' );
		$api_key                  = Util_Request::get_string( 'api_key' );
		$w3tc_access_token        = Util_Request::get_string( 'access_token' );
		$access_region_descriptor = json_decode( strtr( Util_Request::get_string( 'access_region_descriptor' ), '!^', '"\\' ), true );
		$w3tc_region              = Util_Request::get_string( 'region' );
		$service_id               = Util_Request::get_string( 'service_id' );
		$api                      = new Cdn_RackSpace_Api_Cdn(
			array(
				'access_token'             => $w3tc_access_token,
				'access_region_descriptor' => $access_region_descriptor,
				'new_access_required'      => '',
			)
		);

		try {
			$w3tc_service = $api->service_get( $service_id );

			$is_https = false;
			$origin   = '';
			if ( isset( $w3tc_service['origins'] ) && $w3tc_service['origins'][0]['ssl'] ) {
				$is_https = $w3tc_service['origins'][0]['ssl'];
				$origin   = $w3tc_service['origins'][0]['origin'];
			}

			$new_origin = Util_Environment::home_url_host();
			if ( $origin !== $new_origin ) {
				$api->service_set(
					$service_id,
					array(
						array(
							'op'    => 'replace',
							'path'  => '/origins',
							'value' => array(
								array(
									'origin'         => $new_origin,
									'port'           => ( $is_https ? 443 : 80 ),
									'ssl'            => $is_https,
									'hostheadertype' => 'origin',
									'rules'          => array(),
								),
							),
						),
					)
				);
			}
		} catch ( \Exception $ex ) {
			$details = array(
				'user_name'     => $user_name,
				'api_key'       => $api_key,
				'error_message' => $ex->getMessage(),
			);
			include W3TC_DIR . '/Cdn_RackSpaceCdn_Popup_View_Intro.php';
			exit();
		}

		$this->_save_config();
	}

	/**
	 * Saves Rackspace CDN configuration to the plugin settings.
	 *
	 * @return void
	 */
	private function _save_config() {
		$user_name                = Util_Request::get_string( 'user_name' );
		$api_key                  = Util_Request::get_string( 'api_key' );
		$w3tc_access_token        = Util_Request::get_string( 'access_token' );
		$access_region_descriptor = json_decode( strtr( Util_Request::get_string( 'access_region_descriptor' ), '!^', '"\\' ), true );
		$w3tc_region              = Util_Request::get_string( 'region' );
		$service_id               = Util_Request::get_string( 'service_id' );
		$api                      = new Cdn_RackSpace_Api_Cdn(
			array(
				'access_token'             => $w3tc_access_token,
				'access_region_descriptor' => $access_region_descriptor,
				'new_access_required'      => '',
			)
		);
		$w3tc_service             = $api->service_get( $service_id );
		$access_url               = $w3tc_service['links_by_rel']['access_url']['href'];
		$protocol                 = 'http';
		$domain                   = '';

		if ( isset( $w3tc_service['domains'] ) && $w3tc_service['domains'][0]['protocol'] ) {
			$protocol = $w3tc_service['domains'][0]['protocol'];
			$domain   = $w3tc_service['domains'][0]['domain'];
		}

		$w3tc_c = Dispatcher::config();

		$w3tc_c->set( 'cdn.rackspace_cdn.user_name', $user_name );
		$w3tc_c->set( 'cdn.rackspace_cdn.api_key', $api_key );
		$w3tc_c->set( 'cdn.rackspace_cdn.region', $w3tc_region );
		$w3tc_c->set( 'cdn.rackspace_cdn.service.name', $w3tc_service['name'] );
		$w3tc_c->set( 'cdn.rackspace_cdn.service.id', $service_id );
		$w3tc_c->set( 'cdn.rackspace_cdn.service.access_url', $access_url );
		$w3tc_c->set( 'cdn.rackspace_cdn.service.protocol', $protocol );

		if ( 'https' !== $protocol ) {
			$w3tc_c->set( 'cdn.rackspace_cdn.domains', array( $domain ) );
		}

		$w3tc_c->save();

		// reset calculated state.
		$state = Dispatcher::config_state();
		$state->set( 'cdn.rackspace_cdn.access_state', '' );
		$state->save();

		$postfix = Util_Admin::custom_message_id(
			array(),
			array( 'cdn_configuration_saved' => 'CDN credentials are saved successfully' )
		);
		echo esc_url( 'Location admin.php?page=w3tc_cdn&' . $postfix );
		exit();
	}

	/**
	 * Handles AJAX request to render the form for configuring domains.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_rackspace_configure_domains() {
		$this->render_configure_domains_form();
		exit();
	}

	/**
	 * Handles AJAX request to save domain configuration changes.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_rackspace_configure_domains_done() {
		$details = array(
			'cnames' => Util_Request::get_array( 'cdn_cnames' ),
		);

		$core = Dispatcher::component( 'Cdn_Core' );
		$cdn  = $core->get_cdn();

		try {
			// try to obtain CNAMEs.
			$cdn->service_domains_set( $details['cnames'] );

			$w3tc_c = Dispatcher::config();
			$w3tc_c->set( 'cdn.rackspace_cdn.domains', $details['cnames'] );
			$w3tc_c->save();

			$postfix = Util_Admin::custom_message_id(
				array(),
				array( 'cdn_cnames_saved' => 'CNAMEs are saved successfully' )
			);
			echo esc_url( 'Location admin.php?page=w3tc_cdn&' . $postfix );
			exit();
		} catch ( \Exception $ex ) {
			$details['error_message'] = $ex->getMessage();
		}

		$this->render_configure_domains_form( $details );
		exit();
	}

	/**
	 * Renders the form for configuring domains.
	 *
	 * @param array $details Optional. Array of details, including domain configurations. Defaults to an empty array.
	 *
	 * @return void
	 */
	private function render_configure_domains_form( $details = array() ) {
		if ( isset( $details['cnames'] ) ) {
			$w3tc_cnames = $details['cnames'];
		} else {
			$core = Dispatcher::component( 'Cdn_Core' );
			$cdn  = $core->get_cdn();

			try {
				// try to obtain CNAMEs.
				$w3tc_cnames = $cdn->service_domains_get();
			} catch ( \Exception $ex ) {
				$details['error_message'] = $ex->getMessage();
				$w3tc_cnames              = array();
			}
		}

		include W3TC_DIR . '/Cdn_RackSpaceCdn_Popup_View_ConfigureDomains.php';
	}

	/**
	 * Renders the value change summary for a specific service field.
	 *
	 * @param array  $details Array containing the service details.
	 * @param string $field   Name of the field to render value changes for.
	 *
	 * @return void
	 */
	private function render_service_value_change( $details, $field ) {
		Util_Ui::hidden( 'w3tc-rackspace-value-' . $field, $field, $details[ $field ]['new'] );

		if ( ! isset( $details[ $field ]['current'] ) || $details[ $field ]['current'] === $details[ $field ]['new'] ) {
			echo esc_html( $details[ $field ]['new'] );
		} else {
			echo wp_kses(
				sprintf(
					// translators: 1 opening HTML strong tag, 2 current setting value, 3 closing HTML strong tag followed by HTML line break,
					// translators: 4 opening HTML strong tag, 5 new setting value, 6 closing HTML strong tag followed by HTML line break.
					__(
						'currently set to %1$s%2$s%3$s will be changed to %4$s%5$s%6$s',
						'w3-total-cache'
					),
					'<strong>',
					empty( $details[ $field ]['current'] ) ? '<empty>' : $details[ $field ]['current'],
					'</strong><br />',
					'<strong>',
					$details[ $field ]['new'],
					'</strong><br />'
				),
				array(
					'strong' => array(),
					'empty'  => array(),
					'br'     => array(),
				)
			);
		}
	}
}
