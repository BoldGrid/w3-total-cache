<?php
/**
 * File: Cdnfsd_TotalCdn_Status_Hostname.php
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'W3TC' ) || die;

/**
 * Class: Cdnfsd_TotalCdn_Status_Hostname
 *
 * @since X.X.X
 */
class Cdnfsd_TotalCdn_Status_Hostname {
	/**
	 * Runs the Total CDN hostname status test.
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
	public static function test_hostname_status() {
		$config = Dispatcher::config();

		$account_api_key = $config->get_string( 'cdn.totalcdn.account_api_key' );
		$pull_zone_id    = (int) $config->get_integer( 'cdn.totalcdn.pull_zone_id' );

		$hostname = Util_Environment::get_site_hostname();

		if ( empty( $hostname ) ) {
			return array(
				'status'  => 'fail',
				'message' => \__( 'Unable to determine the site hostname.', 'w3-total-cache' ),
			);
		}

		$api = new Cdn_TotalCdn_Api(
			array(
				'account_api_key' => $account_api_key,
				'pull_zone_id'    => $pull_zone_id,
			)
		);

		try {
			$response = $api->check_custom_hostname( $hostname );
		} catch ( \Exception $exception ) {
			return array(
				'status'  => 'fail',
				'message' => \__( 'Failed to check the pull zone for the custom hostname.', 'w3-total-cache' ),
				'log'     => $exception->getMessage(),
			);
		}

		$exists = isset( $response['Exists'] ) ? (bool) $response['Exists'] : false;
		if ( $exists ) {
			return array(
				'status'  => 'pass',
				'message' => \__( 'Custom hostname exists within pull zone record.', 'w3-total-cache' ),
			);
		}

		try {
			$api->add_custom_hostname( $hostname );
		} catch ( \Exception $exception ) {
			return array(
				'status'  => 'fail',
				'message' => \__( 'Failed to add custom hostname to pull zone.', 'w3-total-cache' ),
				'log'     => $exception->getMessage(),
			);
		}

		return array(
			'status'  => 'pass',
			'message' => \__( 'The custom hostname was added to the Total CDN pull zone.', 'w3-total-cache' ),
		);
	}
}
