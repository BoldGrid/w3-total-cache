<?php
/**
 * File: Cdnfsd_TotalCdn_Status_Ssl.php
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'W3TC' ) || die;

/**
 * Class: Cdnfsd_TotalCdn_Status_Ssl
 *
 * @since X.X.X
 */
class Cdnfsd_TotalCdn_Status_Ssl {
	/**
	 * Runs the Total CDN SSL status test.
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
	public static function test_ssl_status() {
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
			$response = $api->get_pull_zone_from_provider( $pull_zone_id );
		} catch ( \Exception $exception ) {
			return array(
				'status'  => 'fail',
				'message' => \__( 'Failed to fetch pull zone info from CDN provider.', 'w3-total-cache' ),
				'log'     => $exception->getMessage(),
			);
		}

		$hostname = Util_Environment::get_site_hostname();

		if ( empty( $hostname ) ) {
			return array(
				'status'  => 'fail',
				'message' => \__( 'Unable to determine the site hostname.', 'w3-total-cache' ),
			);
		}

		$has_ssl = false;

		$hostnames = isset( $response['Hostnames'] ) && is_array( $response['Hostnames'] ) ? $response['Hostnames'] : array();

		foreach ( $hostnames as $entry ) {
			$value = isset( $entry['Value'] ) ? strtolower( (string) $entry['Value'] ) : '';
			$cert  = isset( $entry['HasCertificate'] ) ? (bool) $entry['HasCertificate'] : false;
			// Compare the hostnames in a normalized (case-insensitive) way.
			if ( $value === $hostname && $cert ) {
				$has_ssl = true;
				break;
			}
		}

		if ( $has_ssl ) {
			return array(
				'status'  => 'pass',
				'message' => __( 'SSL certificate detected for pull zone hostname.', 'w3-total-cache' ),
			);
		}

		try {
			$api->load_free_certificate( $hostname );
		} catch ( \Exception $exception ) {
			return array(
				'status'  => 'fail',
				'message' => \__( 'Failed to add SSL certificate to pull zone.', 'w3-total-cache' ),
				'log'     => $exception->getMessage(),
			);
		}

		return array(
			'status'  => 'pass',
			'message' => \__( 'Successfully added SSL certificate to pull zone.', 'w3-total-cache' ),
		);
	}
}
