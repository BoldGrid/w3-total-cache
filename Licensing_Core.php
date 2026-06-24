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
	 * Resolves the license-API base URL after asserting it lives on a
	 * `.w3-edge.com` host. The constant `W3TC_LICENSE_API_URL` is
	 * declared in {@see w3-total-cache-api.php} behind an
	 * `if ( ! defined() )` guard, which lets any code that loads
	 * before the plugin (MU plugins, wp-config.php, an unrelated RCE
	 * primitive) point license traffic at an attacker host and exfil
	 * the license key + home URL.
	 *
	 * The allowlist accepts the canonical production host
	 * (`www.w3-edge.com`) plus any `*.w3-edge.com` subdomain so a
	 * legitimate staging override (`staging.w3-edge.com`) still works.
	 * Schemes other than `https` are refused outright — even a valid
	 * `*.w3-edge.com` host over plain `http://` leaks the license key
	 * in transit.
	 *
	 * Returns `false` on rejection; callers short-circuit to their
	 * existing "request failed" path (the same path
	 * `is_wp_error($response)` already feeds into).
	 *
	 * @since 2.10.0
	 *
	 * @return string|false Sanitized base URL, or false if the
	 *                      configured constant is unsafe.
	 */
	private static function _license_api_base_url() {
		$w3tc_url = \defined( 'W3TC_LICENSE_API_URL' ) ? W3TC_LICENSE_API_URL : '';
		if ( ! \is_string( $w3tc_url ) || '' === $w3tc_url ) {
			return false;
		}
		$scheme = \wp_parse_url( $w3tc_url, PHP_URL_SCHEME );
		$host   = \wp_parse_url( $w3tc_url, PHP_URL_HOST );
		if ( 'https' !== $scheme || ! \is_string( $host ) || '' === $host ) {
			return false;
		}
		$host_lc = \strtolower( $host );
		if ( 'w3-edge.com' === $host_lc ) {
			return $w3tc_url;
		}
		/**
		 * Subdomain match: leading dot in the suffix prevents an
		 * attacker-owned `xw3-edge.com` from passing the check.
		 */
		$suffix = '.w3-edge.com';
		$slen   = \strlen( $suffix );
		$hlen   = \strlen( $host_lc );
		if ( $hlen > $slen && \substr( $host_lc, -$slen ) === $suffix ) {
			return $w3tc_url;
		}
		return false;
	}

	/**
	 * Activates a license for the plugin.
	 *
	 * @param string $w3tc_license License key to be activated.
	 * @param string $version Version of the plugin being licensed.
	 *
	 * @return mixed|false Decoded license data on success, false on failure.
	 */
	public static function activate_license( $w3tc_license, $version ) {
		$state = Dispatcher::config_state_master();

		// data to send in our API request.
		$api_params = array(
			'edd_action'          => 'activate_license',
			'license'             => $w3tc_license,   // legacy.
			'license_key'         => $w3tc_license,
			'home_url'            => network_home_url(),
			'item_name'           => rawurlencode( W3TC_PURCHASE_PRODUCT_NAME ), // the name of our product in EDD.
			'plugin_install_date' => gmdate( 'Y-m-d\\TH:i:s\\Z', $state->get_integer( 'common.install' ) ),
			'r'                   => wp_rand(),
			'version'             => $version,
		);

		$base = self::_license_api_base_url();
		if ( false === $base ) {
			return false;
		}

		// Call the custom API.
		$response = wp_remote_get(
			add_query_arg(
				$api_params,
				$base
			),
			array(
				'timeout' => 15,
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
	 * @param string $w3tc_license License key to be deactivated.
	 *
	 * @return object|false Decoded license data on success, false on failure.
	 */
	public static function deactivate_license( $w3tc_license ) {
		// Data to send in our API request.
		$api_params = array(
			'edd_action'  => 'deactivate_license',
			'license'     => $w3tc_license,   // legacy.
			'license_key' => $w3tc_license,
			'home_url'    => network_home_url(),
			'item_name'   => rawurlencode( W3TC_PURCHASE_PRODUCT_NAME ), // the name of our product in EDD.
			'r'           => wp_rand(),
		);

		$base = self::_license_api_base_url();
		if ( false === $base ) {
			return false;
		}

		// Call the custom API.
		$response = wp_remote_get(
			add_query_arg(
				$api_params,
				$base
			),
			array(
				'timeout' => 15,
			)
		);

		// Make sure the response came back okay.
		if ( is_wp_error( $response ) ) {
			return false;
		}

		// Decode the license data.
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		// $license_data->license will be either "deactivated" or "failed"
		return $license_data;
	}

	/**
	 * Checks the status of a license.
	 *
	 * @param string $w3tc_license License key to be checked.
	 * @param string $version Version of the plugin being checked.
	 *
	 * @return mixed|false Decoded license data on success, false on failure.
	 */
	public static function check_license( $w3tc_license, $version ) {
		global $wp_version;

		$api_params = array(
			'edd_action'  => 'check_license',
			'license'     => $w3tc_license,   // legacy.
			'license_key' => $w3tc_license,
			'home_url'    => network_home_url(),
			'item_name'   => rawurlencode( W3TC_PURCHASE_PRODUCT_NAME ),
			'r'           => wp_rand(),
			'version'     => $version,
		);

		$base = self::_license_api_base_url();
		if ( false === $base ) {
			return false;
		}

		// Call the custom API.
		$response = wp_remote_get(
			add_query_arg(
				$api_params,
				$base
			),
			array(
				'timeout' => 15,
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
	 * @param string $w3tc_license License key associated with the reset request.
	 * @param string $version Version of the plugin associated with the reset request.
	 *
	 * @return mixed|false Decoded status data on success, false on failure.
	 */
	public static function reset_rooturi( $w3tc_license, $version ) {
		// data to send in our API request.
		$api_params = array(
			'edd_action'  => 'reset_rooturi',
			'license_key' => $w3tc_license,
			'home_url'    => network_home_url(),
			'item_name'   => rawurlencode( W3TC_PURCHASE_PRODUCT_NAME ), // the name of our product in EDD.
			'r'           => wp_rand(),
			'version'     => $version,
		);

		$base = self::_license_api_base_url();
		if ( false === $base ) {
			return false;
		}

		// Call the custom API.
		$response = wp_remote_get(
			add_query_arg(
				$api_params,
				$base
			),
			array(
				'timeout' => 15,
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
		$w3tc_c = Dispatcher::config();
		if ( ! Util_Environment::is_w3tc_pro( $w3tc_c ) ) {
			$state_master = Dispatcher::config_state_master();
			$state_master->set( 'license.community_terms', 'accept' );
			$state_master->save();

			$w3tc_c->set( 'common.track_usage', true );
			$w3tc_c->save();
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
		$w3tc_config = Dispatcher::config();

		if ( Util_Environment::is_w3tc_pro( $w3tc_config ) ) {
			$state = Dispatcher::config_state();
			$terms = $state->get_string( 'license.terms' );
		} else {
			$state_master = Dispatcher::config_state_master();
			$terms        = $state_master->get_string( 'license.community_terms' );
		}

		return $terms;
	}
}
