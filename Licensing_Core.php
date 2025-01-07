<?php
/**
 * File: Licensing_Core.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Licensing_Core
 */
class Licensing_Core {
	/**
	 * Activates a license for the plugin.
	 *
	 * @param string $license License key to be activated.
	 * @param string $version Version of the plugin being licensed.
	 *
	 * @return mixed|false Decoded license data on success, false on failure.
	 */
	public static function activate_license( $license, $version ) {
		$state = Dispatcher::config_state_master();

		// data to send in our API request.
		$api_params = array(
			'edd_action'          => 'activate_license',
			'license'             => $license,   // legacy.
			'license_key'         => $license,
			'home_url'            => network_home_url(),
			'item_name'           => rawurlencode( W3TC_PURCHASE_PRODUCT_NAME ), // the name of our product in EDD.
			'plugin_install_date' => gmdate( 'Y-m-d\\TH:i:s\\Z', $state->get_integer( 'common.install' ) ),
			'r'                   => wp_rand(),
			'version'             => $version,
		);

		// Call the custom API.
		$response = wp_remote_get(
			add_query_arg(
				$api_params,
				W3TC_LICENSE_API_URL
			),
			array(
				'timeout'   => 15,
				'sslverify' => false,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		// decode the license data.
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		return $license_data;
	}

	/**
	 * Deactivates a license for the plugin.
	 *
	 * @param string $license License key to be deactivated.
	 *
	 * @return bool True if the license was successfully deactivated, false otherwise.
	 */
	public static function deactivate_license( $license ) {
		// data to send in our API request.
		$api_params = array(
			'edd_action'  => 'deactivate_license',
			'license'     => $license,   // legacy.
			'license_key' => $license,
			'home_url'    => network_home_url(),
			'item_name'   => rawurlencode( W3TC_PURCHASE_PRODUCT_NAME ), // the name of our product in EDD.
			'r'           => wp_rand(),
		);

		// Call the custom API.
		$response = wp_remote_get(
			add_query_arg(
				$api_params,
				W3TC_LICENSE_API_URL
			),
			array(
				'timeout'   => 15,
				'sslverify' => false,
			)
		);

		// make sure the response came back okay.
		if ( is_wp_error( $response ) ) {
			return false;
		}

		// decode the license data.
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		// $license_data->license will be either "deactivated" or "failed"
		return 'deactivated' === $license_data->license;
	}

	/**
	 * Checks the status of a license.
	 *
	 * @param string $license License key to be checked.
	 * @param string $version Version of the plugin being checked.
	 *
	 * @return mixed|false Decoded license data on success, false on failure.
	 */
	public static function check_license( $license, $version ) {
		global $wp_version;

		$api_params = array(
			'edd_action'  => 'check_license',
			'license'     => $license,   // legacy.
			'license_key' => $license,
			'home_url'    => network_home_url(),
			'item_name'   => rawurlencode( W3TC_PURCHASE_PRODUCT_NAME ),
			'r'           => wp_rand(),
			'version'     => $version,
		);

		// Call the custom API.
		$response = wp_remote_get(
			add_query_arg(
				$api_params,
				W3TC_LICENSE_API_URL
			),
			array(
				'timeout'   => 15,
				'sslverify' => false,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		return $license_data;
	}

	/**
	 * Resets the root URI for a license.
	 *
	 * @param string $license License key associated with the reset request.
	 * @param string $version Version of the plugin associated with the reset request.
	 *
	 * @return mixed|false Decoded status data on success, false on failure.
	 */
	public static function reset_rooturi( $license, $version ) {
		// data to send in our API request.
		$api_params = array(
			'edd_action'  => 'reset_rooturi',
			'license_key' => $license,
			'home_url'    => network_home_url(),
			'item_name'   => rawurlencode( W3TC_PURCHASE_PRODUCT_NAME ), // the name of our product in EDD.
			'r'           => wp_rand(),
			'version'     => $version,
		);

		// Call the custom API.
		$response = wp_remote_get(
			add_query_arg(
				$api_params,
				W3TC_LICENSE_API_URL
			),
			array(
				'timeout'   => 15,
				'sslverify' => false,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		// decode the license data.
		$status = json_decode( wp_remote_retrieve_body( $response ) );

		return $status;
	}

	/**
	 * Accepts the terms of service for the community license.
	 *
	 * @return void
	 */
	public static function terms_accept() {
		$c = Dispatcher::config();
		if ( ! Util_Environment::is_w3tc_pro( $c ) ) {
			$state_master = Dispatcher::config_state_master();
			$state_master->set( 'license.community_terms', 'accept' );
			$state_master->save();

			$c->set( 'common.track_usage', true );
			$c->save();
		}
	}

	/**
	 * Generates a purchase URL for the plugin.
	 *
	 * @param string $data_src  Optional data source for the URL.
	 * @param string $renew_key Optional renewal key for the license.
	 * @param string $client_id Optional client ID associated with the purchase.
	 *
	 * @return string URL for purchasing or renewing the plugin.
	 */
	public static function purchase_url( $data_src = '', $renew_key = '', $client_id = '' ) {
		$state = Dispatcher::config_state_master();
		return W3TC_PURCHASE_URL .
			( strpos( W3TC_PURCHASE_URL, '?' ) === false ? '?' : '&' ) .
			'install_date=' . rawurlencode( $state->get_integer( 'common.install' ) ) .
			( empty( $data_src ) ? '' : '&data_src=' . rawurlencode( $data_src ) ) .
			( empty( $renew_key ) ? '' : '&renew_key=' . rawurlencode( $renew_key ) ) .
			( empty( $client_id ) ? '' : '&client_id=' . rawurlencode( $client_id ) );
	}

	/**
	 * Retrieves the user's terms of service choice.
	 *
	 * @since 2.2.2
	 *
	 * @return string Terms of service choice as stored in the configuration.
	 */
	public static function get_tos_choice() {
		$config = Dispatcher::config();

		if ( Util_Environment::is_w3tc_pro( $config ) ) {
			$state = Dispatcher::config_state();
			$terms = $state->get_string( 'license.terms' );
		} else {
			$state_master = Dispatcher::config_state_master();
			$terms        = $state_master->get_string( 'license.community_terms' );
		}

		return $terms;
	}
}
