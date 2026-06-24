<?php
/**
 * File: Cdn_RackSpaceCloudFiles_Popup.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_RackSpaceCloudFiles_Popup
 */
class Cdn_RackSpaceCloudFiles_Popup {
	/**
	 * Initializes AJAX actions for Rackspace Cloud Files.
	 *
	 * This method registers various AJAX actions related to
	 * Rackspace Cloud Files integration.
	 *
	 * @return void
	 */
	public static function w3tc_ajax() {
		$w3tc_o = new Cdn_RackSpaceCloudFiles_Popup();

		add_action( 'w3tc_ajax_cdn_rackspace_authenticate', array( $w3tc_o, 'w3tc_ajax_cdn_rackspace_authenticate' ) );
		add_action( 'w3tc_ajax_cdn_rackspace_intro_done', array( $w3tc_o, 'w3tc_ajax_cdn_rackspace_intro_done' ) );
		add_action( 'w3tc_ajax_cdn_rackspace_regions_done', array( $w3tc_o, 'w3tc_ajax_cdn_rackspace_regions_done' ) );
		add_action( 'w3tc_ajax_cdn_rackspace_containers_done', array( $w3tc_o, 'w3tc_ajax_cdn_rackspace_containers_done' ) );
	}

	/**
	 * Handles Rackspace authentication via AJAX.
	 *
	 * Authenticates the user with Rackspace using the provided credentials
	 * and displays the introduction view.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_rackspace_authenticate() {
		$w3tc_c = Dispatcher::config();

		$details = array(
			'user_name' => $w3tc_c->get_string( 'cdn.rscf.user' ),
			'api_key'   => $w3tc_c->get_string( 'cdn.rscf.key' ),
		);

		include W3TC_DIR . '/Cdn_RackSpaceCloudFiles_Popup_View_Intro.php';
		exit();
	}

	/**
	 * Handles the completion of the Rackspace introduction step via AJAX.
	 *
	 * Validates the provided user credentials and fetches available regions
	 * from Rackspace. Displays the regions selection view.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_rackspace_intro_done() {
		$user_name = Util_Request::get_string( 'user_name' );
		$api_key   = Util_Request::get_string( 'api_key' );

		try {
			$w3tc_r = Cdn_RackSpace_Api_Tokens::authenticate( $user_name, $api_key );
		} catch ( \Exception $ex ) {
			$details = array(
				'user_name'     => $user_name,
				'api_key'       => $api_key,
				'error_message' => 'Can\'t authenticate: ' . $ex->getMessage(),
			);
			include W3TC_DIR . '/Cdn_RackSpaceCloudFiles_Popup_View_Intro.php';
			exit();
		}

		$w3tc_r['regions'] = Cdn_RackSpace_Api_Tokens::cloudfiles_services_by_region( $w3tc_r['services'] );

		$details = array(
			'user_name'                     => $user_name,
			'api_key'                       => $api_key,
			'access_token'                  => $w3tc_r['access_token'],
			'region_descriptors'            => $w3tc_r['regions'],
			// avoid fights with quotes, magic_quotes may break randomly.
			'region_descriptors_serialized' => strtr( wp_json_encode( $w3tc_r['regions'] ), '"\\', '!^' ),
		);

		include W3TC_DIR . '/Cdn_RackSpaceCloudFiles_Popup_View_Regions.php';
		exit();
	}

	/**
	 * Handles the completion of Rackspace region selection via AJAX.
	 *
	 * Verifies the selected region and retrieves available containers for the region.
	 * Displays the containers selection view.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_rackspace_regions_done() {
		$user_name          = Util_Request::get_string( 'user_name' );
		$api_key            = Util_Request::get_string( 'api_key' );
		$w3tc_access_token  = Util_Request::get_string( 'access_token' );
		$w3tc_region        = Util_Request::get( 'region' );
		$region_descriptors = json_decode( strtr( Util_Request::get_string( 'region_descriptors' ), '!^', '"\\' ), true );

		if ( ! isset( $region_descriptors[ $w3tc_region ] ) ) {
			$details = array(
				'user_name'     => $user_name,
				'api_key'       => $api_key,
				'error_message' => 'Please select region ' . $w3tc_region,
			);
			include W3TC_DIR . '/Cdn_RackSpaceCloudFiles_Popup_View_Intro.php';
			exit();
		}

		$api = new Cdn_RackSpace_Api_CloudFilesCdn(
			array(
				'access_token'             => $w3tc_access_token,
				'access_region_descriptor' => $region_descriptors[ $w3tc_region ],
				'new_access_required'      => '',
			)
		);

		try {
			$containers = $api->containers();
		} catch ( \Exception $ex ) {
			$details = array(
				'user_name'     => $user_name,
				'api_key'       => $api_key,
				'error_message' => $ex->getMessage(),
			);
			include W3TC_DIR . '/Cdn_RackSpaceCloudFiles_Popup_View_Intro.php';
			exit();
		}

		$details = array(
			'user_name'                           => $user_name,
			'api_key'                             => $api_key,
			'access_token'                        => $w3tc_access_token,
			'access_region_descriptor_serialized' => strtr( wp_json_encode( $region_descriptors[ $w3tc_region ] ), '"\\', '!^' ),
			'region'                              => $w3tc_region,
			// avoid fights with quotes, magic_quotes may break randomly.
			'containers'                          => $containers,
		);

		include W3TC_DIR . '/Cdn_RackSpaceCloudFiles_Popup_View_Containers.php';
		exit();
	}

	/**
	 * Handles the completion of Rackspace container selection via AJAX.
	 *
	 * Creates a new container if none is selected, enables CDN for the container,
	 * and saves the Rackspace configuration. Redirects to the CDN settings page.
	 *
	 * @return void
	 *
	 * @throws \Exception If container selection is ommitted.
	 */
	public function w3tc_ajax_cdn_rackspace_containers_done() {
		$user_name                = Util_Request::get_string( 'user_name' );
		$api_key                  = Util_Request::get_string( 'api_key' );
		$w3tc_access_token        = Util_Request::get_string( 'access_token' );
		$access_region_descriptor = json_decode( strtr( Util_Request::get_string( 'access_region_descriptor' ), '!^', '"\\' ), true );
		$w3tc_region              = Util_Request::get_string( 'region' );
		$w3tc_container           = Util_Request::get( 'container' );

		$api_files = new Cdn_RackSpace_Api_CloudFiles(
			array(
				'access_token'             => $w3tc_access_token,
				'access_region_descriptor' => $access_region_descriptor,
				'new_access_required'      => '',
			)
		);
		$api_cdn   = new Cdn_RackSpace_Api_CloudFilesCdn(
			array(
				'access_token'             => $w3tc_access_token,
				'access_region_descriptor' => $access_region_descriptor,
				'new_access_required'      => '',
			)
		);

		try {
			if ( empty( $w3tc_container ) ) {
				$container_new = Util_Request::get_string( 'container_new' );

				if ( empty( $container_new ) ) {
					throw new \Exception( 'Please select container' );
				}

				$api_files->container_create( $container_new );
				$api_cdn->container_cdn_enable( $container_new );
				$w3tc_container = $container_new;
			}
		} catch ( \Exception $ex ) {
			$containers               = $api_cdn->containers();
			$details                  = array(
				'user_name'                           => $user_name,
				'api_key'                             => $api_key,
				'access_token'                        => $w3tc_access_token,
				// avoid fights with quotes, magic_quotes may break randomly.
				'access_region_descriptor_serialized' => strtr( wp_json_encode( $access_region_descriptor ), '"\\', '!^' ),
				'region'                              => $w3tc_region,
				'containers'                          => $containers,
			);
			$details['error_message'] = $ex->getMessage();
			include W3TC_DIR . '/Cdn_RackSpaceCloudFiles_Popup_View_Containers.php';
			exit();
		}

		$w3tc_c = Dispatcher::config();

		$w3tc_c->set( 'cdn.rscf.user', $user_name );
		$w3tc_c->set( 'cdn.rscf.key', $api_key );
		$w3tc_c->set( 'cdn.rscf.location', $w3tc_region );
		$w3tc_c->set( 'cdn.rscf.container', $w3tc_container );
		$w3tc_c->save();

		// reset calculated state.
		$state = Dispatcher::config_state();
		$state->set( 'cdn.rackspace_cf.access_state', '' );
		$state->save();

		$postfix = Util_Admin::custom_message_id(
			array(),
			array( 'cdn_configuration_saved' => 'CDN credentials are saved successfully' )
		);
		echo 'Location admin.php?page=w3tc_cdn&' . wp_kses( $postfix, Util_Ui::get_allowed_html_for_wp_kses_from_content( $postfix ) );
		exit();
	}
}
