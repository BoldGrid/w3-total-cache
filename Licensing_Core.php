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
		if ( ! Util_Url::is_https_host_allowlisted( $w3tc_url, array( 'w3-edge.com' ), array( '.w3-edge.com' ) ) ) {
			return false;
		}
		return $w3tc_url;
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

		return self::license_get( $api_params, true );
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

		return self::license_get( $api_params, false );
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

		return self::license_get( $api_params, true );
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

		return self::license_get( $api_params, false );
	}

	/**
	 * Network option that serializes license.jwt writes.
	 *
	 * @var string
	 */
	const JWT_LOCK_OPTION = 'w3tc_license_jwt_refresh_lock';

	/**
	 * Issue or refresh the token used on /v2/ service calls.
	 *
	 * Scheduled license checks call check_license() directly and do not come
	 * through here, so an expired token does not trigger refresh on that path.
	 *
	 * @since 2.11.0
	 *
	 * @return string Current license.jwt, or empty.
	 */
	public static function ensure_service_token() {
		$token = self::current_jwt();
		if ( '' === $token ) {
			self::recheck_with_license_key();
			return self::current_jwt();
		}

		if ( self::token_needs_refresh( $token ) ) {
			if ( 'failed' === self::refresh_license_jwt() ) {
				self::recheck_with_license_key();
			}
		}

		return self::current_jwt();
	}

	/**
	 * One recovery attempt after an image service 401.
	 *
	 * Sends a single refresh when a token exists, otherwise one check.
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	public static function reissue_for_unauthorized() {
		if ( '' === self::current_jwt() ) {
			self::recheck_with_license_key();
			return;
		}

		if ( 'failed' === self::refresh_license_jwt() ) {
			self::recheck_with_license_key();
		}
	}

	/**
	 * JWT stored on the master w3tc_state option.
	 *
	 * Reads the raw site option so a concurrent writer is visible.
	 *
	 * @since 2.11.0
	 *
	 * @return string
	 */
	public static function current_jwt() {
		$data = self::raw_master_state();
		return isset( $data['license.jwt'] ) ? (string) $data['license.jwt'] : '';
	}

	/**
	 * Remove license.jwt. Used when the license key changes or is removed.
	 *
	 * A key change clears the token even when another caller holds the refresh
	 * lock. The lock, when this caller can take it, covers only the write.
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	public static function clear_license_jwt() {
		$owner = self::acquire_jwt_lock();
		$data  = self::raw_master_state();
		if ( ! empty( $data['license.jwt'] ) ) {
			$data['license.jwt'] = '';
			self::write_master_state( $data );
			self::clear_imageservice_transients();
		}
		self::release_jwt_lock( $owner );
	}

	/**
	 * GET the licensing API. /v2/ calls may store a returned token.
	 *
	 * @since 2.11.0
	 *
	 * @param array $api_params Query parameters.
	 * @param bool  $v2         Whether this action is a /v2/ call.
	 *
	 * @return object|false
	 */
	private static function license_get( array $api_params, $v2 ) {
		$base = self::_license_api_base_url();
		if ( false === $base ) {
			return false;
		}

		$url   = $v2 ? trailingslashit( $base ) . 'v2/' : $base;
		$prior = $v2 ? self::current_jwt() : '';

		$response = wp_remote_get(
			add_query_arg( $api_params, $url ),
			array(
				'timeout'     => 15,
				'redirection' => 0,
			)
		);

		if ( $v2 ) {
			$owner = self::acquire_jwt_lock();
			if ( is_string( $owner ) ) {
				self::store_token_from_response( $response, $prior );
				self::release_jwt_lock( $owner );
			}
		}

		if ( is_wp_error( $response ) ) {
			return false;
		}

		return json_decode( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * POST /v2/refresh. Does not call check_license on failure.
	 *
	 * @since 2.11.0
	 *
	 * @return string ok, failed, or skipped.
	 */
	private static function refresh_license_jwt() {
		$token = self::current_jwt();
		if ( '' === $token ) {
			return 'failed';
		}

		if ( self::jwt_lock_is_live() ) {
			return 'skipped';
		}

		$base = self::_license_api_base_url();
		if ( false === $base ) {
			return 'failed';
		}

		$response = wp_remote_post(
			trailingslashit( $base ) . 'v2/refresh',
			array(
				'timeout'     => 15,
				'redirection' => 0,
				'headers'     => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'        => wp_json_encode(
					array(
						'home_url' => network_home_url(),
					)
				),
			)
		);

		$code   = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$owner  = self::acquire_jwt_lock();
		$result = 'rejected';
		if ( is_string( $owner ) && $code >= 200 && $code < 300 ) {
			$result = self::store_token_from_response( $response, $token );
		}
		self::release_jwt_lock( $owner );
		return ( $code >= 200 && $code < 300 && 'rejected' !== $result ) ? 'ok' : 'failed';
	}

	/**
	 * check_license with the configured license key.
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	private static function recheck_with_license_key() {
		$key = Dispatcher::config()->get_string( 'plugin.license_key' );
		if ( '' === $key ) {
			return;
		}

		self::check_license( $key, W3TC_VERSION );
	}

	/**
	 * Whether a token should be refreshed before a service call.
	 *
	 * Public expires_at wins. Otherwise exp. Malformed tokens are due.
	 * Refresh starts 120 seconds before expiry.
	 *
	 * @since 2.11.0
	 *
	 * @param string $token JWT.
	 *
	 * @return bool
	 */
	private static function token_needs_refresh( $token ) {
		$payload = self::public_claims( $token );
		if ( ! is_array( $payload ) ) {
			return true;
		}

		$deadline = time() + 120;
		if ( isset( $payload['expires_at'] ) && is_string( $payload['expires_at'] ) && '' !== $payload['expires_at'] ) {
			$expires = strtotime( $payload['expires_at'] );
			return false === $expires || $expires <= $deadline;
		}

		if ( isset( $payload['exp'] ) && is_numeric( $payload['exp'] ) ) {
			return (int) $payload['exp'] <= $deadline;
		}

		return true;
	}

	/**
	 * Persist a 2xx three-segment token onto master license.jwt.
	 *
	 * When $only_if_current is a string, skip the write if the raw option
	 * no longer holds that token.
	 *
	 * @since 2.11.0
	 *
	 * @param array|WP_Error $response        HTTP response.
	 * @param string|null    $only_if_current Expected current token.
	 *
	 * @return string stored, skipped, or rejected.
	 */
	private static function store_token_from_response( $response, $only_if_current ) {
		if ( is_wp_error( $response ) ) {
			return 'rejected';
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return 'rejected';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! is_object( $body ) || ! isset( $body->token ) || ! self::is_storable_token( $body->token ) ) {
			return 'rejected';
		}

		$token   = (string) $body->token;
		$data    = self::raw_master_state();
		$current = isset( $data['license.jwt'] ) ? (string) $data['license.jwt'] : '';
		if ( null !== $only_if_current && $current !== (string) $only_if_current ) {
			return 'skipped';
		}
		if ( $current === $token ) {
			return 'stored';
		}

		$data['license.jwt'] = $token;
		self::write_master_state( $data );
		self::clear_imageservice_transients();
		return 'stored';
	}

	/**
	 * Whether the token is three segments with a JSON object payload.
	 *
	 * The private claim is not decrypted or inspected.
	 *
	 * @since 2.11.0
	 *
	 * @param mixed $token Candidate token.
	 *
	 * @return bool
	 */
	private static function is_storable_token( $token ) {
		return is_array( self::public_claims( $token ) );
	}

	/**
	 * Public JWT payload, or null when the token is not usable.
	 *
	 * @since 2.11.0
	 *
	 * @param mixed $token Candidate token.
	 *
	 * @return array|null
	 */
	private static function public_claims( $token ) {
		if ( ! is_string( $token ) ) {
			return null;
		}

		$parts = explode( '.', $token );
		if ( 3 !== count( $parts ) || '' === $parts[0] || '' === $parts[1] || '' === $parts[2] ) {
			return null;
		}

		$json = self::base64url_decode( $parts[1] );
		if ( ! is_string( $json ) ) {
			return null;
		}

		$payload = json_decode( $json, true );
		if ( ! is_array( $payload ) ) {
			return null;
		}
		if ( array() !== $payload && array_keys( $payload ) === range( 0, count( $payload ) - 1 ) ) {
			return null;
		}
		return $payload;
	}

	/**
	 * Base64url decode.
	 *
	 * @since 2.11.0
	 *
	 * @param string $value Segment.
	 *
	 * @return string|null
	 */
	private static function base64url_decode( $value ) {
		$remainder = strlen( $value ) % 4;
		if ( 0 < $remainder ) {
			$value .= str_repeat( '=', 4 - $remainder );
		}
		$decoded = base64_decode( strtr( $value, '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		return false === $decoded ? null : $decoded;
	}

	/**
	 * Whether another caller holds an unexpired refresh lock.
	 *
	 * @since 2.11.0
	 *
	 * @return bool
	 */
	private static function jwt_lock_is_live() {
		self::forget_site_option_cache( self::JWT_LOCK_OPTION );
		$existing = get_site_option( self::JWT_LOCK_OPTION, false );
		return is_array( $existing ) && isset( $existing['expires'] ) && (int) $existing['expires'] >= time();
	}

	/**
	 * Insert the network lock unless a live owner holds it.
	 *
	 * @since 2.11.0
	 *
	 * @return string|false Owner id, or false when a live lock exists.
	 */
	private static function acquire_jwt_lock() {
		$owner = function_exists( 'wp_generate_password' ) ? wp_generate_password( 16, false ) : bin2hex( random_bytes( 8 ) );
		$value = array(
			'owner'   => $owner,
			'expires' => time() + 30,
		);

		if ( add_site_option( self::JWT_LOCK_OPTION, $value ) ) {
			return $owner;
		}

		$existing = get_site_option( self::JWT_LOCK_OPTION, false );
		if ( is_array( $existing ) && isset( $existing['expires'] ) && (int) $existing['expires'] < time() ) {
			delete_site_option( self::JWT_LOCK_OPTION );
			if ( add_site_option( self::JWT_LOCK_OPTION, $value ) ) {
				return $owner;
			}
		}

		return false;
	}

	/**
	 * Delete the lock only when this caller still owns it.
	 *
	 * @since 2.11.0
	 *
	 * @param string|false $owner Owner id from acquire_jwt_lock().
	 *
	 * @return void
	 */
	private static function release_jwt_lock( $owner ) {
		if ( ! is_string( $owner ) || '' === $owner ) {
			return;
		}

		self::forget_site_option_cache( self::JWT_LOCK_OPTION );
		$existing = get_site_option( self::JWT_LOCK_OPTION, false );
		if ( is_array( $existing ) && isset( $existing['owner'] ) && (string) $existing['owner'] === $owner ) {
			delete_site_option( self::JWT_LOCK_OPTION );
		}
	}

	/**
	 * Decoded master w3tc_state site option.
	 *
	 * @since 2.11.0
	 *
	 * @return array
	 */
	private static function raw_master_state() {
		self::forget_site_option_cache( 'w3tc_state' );
		$data = json_decode( (string) get_site_option( 'w3tc_state' ), true );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Drop the option cache so a compare sees another request's write.
	 *
	 * Single-site network options live in the autoloaded options cache.
	 *
	 * @since 2.11.0
	 *
	 * @param string $option Option name.
	 *
	 * @return void
	 */
	private static function forget_site_option_cache( $option ) {
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( $option, 'options' );
		if ( function_exists( 'get_current_network_id' ) ) {
			$network_id = (int) get_current_network_id();
			wp_cache_delete( $network_id . ':' . $option, 'site-options' );
			wp_cache_delete( $network_id . ':notoptions', 'site-options' );
		}
	}

	/**
	 * Write master state and keep the ConfigState singleton aligned.
	 *
	 * @since 2.11.0
	 *
	 * @param array $data State map.
	 *
	 * @return void
	 */
	private static function write_master_state( array $data ) {
		$encoded = wp_json_encode( $data );
		update_site_option( 'w3tc_state', $encoded );

		$state = Dispatcher::config_state_master();
		$ref   = new \ReflectionObject( $state );
		foreach ( array( '_data' => $data, '_saved_encoded' => $encoded, '_loaded' => true ) as $prop_name => $prop_value ) {
			$prop = $ref->getProperty( $prop_name );
			$prop->setAccessible( true );
			$prop->setValue( $state, $prop_value );
		}
	}

	/**
	 * Drop cached image-service usage after the license identity changes.
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	private static function clear_imageservice_transients() {
		delete_transient( 'w3tc_imageservice_usage' );
		delete_transient( 'w3tc_imageservice_limited' );
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
