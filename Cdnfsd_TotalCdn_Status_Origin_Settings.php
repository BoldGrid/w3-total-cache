<?php
/**
 * File: Cdnfsd_TotalCdn_Status_Origin_Settings.php
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'W3TC' ) || die;

/**
 * Class: Cdnfsd_TotalCdn_Status_Origin_Settings
 *
 * @since X.X.X
 */
class Cdnfsd_TotalCdn_Status_Origin_Settings {
	/**
	 * Runs the Total CDN origin settings status test.
	 *
	 * @since X.X.X
	 *
	 * @return array {
	 *     Test result data.
	 *
	 *     @type string $status  Normalized status string (pass|fail|untested).
	 *     @type string $message Optional message describing the outcome.
	 *     @type string $log     Optional technical error message.
	 * }
	 */
	public static function test_origin_settings_status() {
		$config = Dispatcher::config();

		$account_api_key = $config->get_string( 'cdn.totalcdn.account_api_key' );
		$pull_zone_id    = (int) $config->get_integer( 'cdn.totalcdn.pull_zone_id' );

		$api = new Cdn_TotalCdn_Api(
			array(
				'account_api_key' => $account_api_key,
				'pull_zone_id'    => $pull_zone_id,
			)
		);

		try {
			$response = $api->get_pull_zone();
		} catch ( \Exception $exception ) {
			return array(
				'status'  => 'fail',
				'message' => \__( 'Failed to fetch pull zone info.', 'w3-total-cache' ),
				'log'     => $exception->getMessage(),
			);
		}

		try {
			$ip_address = $api->get_origin_ip_address();
		} catch ( \Exception $exception ) {
			return array(
				'status'  => 'fail',
				'message' => \__( 'Failed to fetch origin IP address.', 'w3-total-cache' ),
				'log'     => $exception->getMessage(),
			);
		}

		if ( empty( $ip_address ) || false === \filter_var( $ip_address, FILTER_VALIDATE_IP ) ) {
			return array(
				'status'  => 'fail',
				'message' => \__( 'The CDN API did not return a valid IP address for Full Site Delivery.', 'w3-total-cache' ),
				'log'     => $exception->getMessage(),
			);
		}

		$scheme     = Util_Environment::get_site_scheme();
		$origin_url = $scheme . '://' . $ip_address;

		if ( $origin_url !== $response['OriginUrl'] ) {
			$origin_result = Cdn_TotalCdn_Fsd_Origin::ensure( $config );

			if ( ! empty( $origin_result['error'] ) ) {
				return array(
					'status'  => 'fail',
					'message' => \__( 'Failed to fix the CDN origin URL.', 'w3-total-cache' ),
					'log'     => $origin_result['error'],
				);
			}
		}

		$hostname = Util_Environment::get_site_hostname();

		if ( empty( $hostname ) ) {
			return array(
				'status'  => 'fail',
				'message' => \__( 'Unable to determine the site hostname.', 'w3-total-cache' ),
			);
		}

		if ( $hostname !== $response['OriginHostHeader'] ) {
			$hostname_result = Cdn_TotalCdn_CustomHostname::ensure( $config );

			if ( ! empty( $hostname_result['error'] ) ) {
				return array(
					'status'  => 'fail',
					'message' => \__( 'Failed to fix the CDN origin host header.', 'w3-total-cache' ),
					'log'     => $hostname_result['error'],
				);
			}
		}

		return array(
			'status'  => 'pass',
			'message' => \__( 'The correct CDN origin settings were detected.', 'w3-total-cache' ),
		);
	}
}
