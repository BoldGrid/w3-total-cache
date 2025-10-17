<?php
/**
 * File: Cdnfsd_TotalCdn_Status_Dns.php
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'W3TC' ) || die;

/**
 * Class: Cdnfsd_TotalCdn_Status_Dns
 *
 * @since X.X.X
 */
class Cdnfsd_TotalCdn_Status_Dns {
	/**
	 * Runs the Total CDN dns status test.
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
	public static function test_dns_status() {
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
			$response = $api->verify_hostname_cdn( $hostname );
		} catch ( \Exception $exception ) {
			return array(
				'status'  => 'fail',
				'message' => \__( 'Failed to verify if the hostname points to a CDN provider.', 'w3-total-cache' ),
				'log'     => $exception->getMessage(),
			);
		}

		$verified = isset( $response['Success'] ) ? (bool) $response['Success'] : false;
		if ( ! $verified ) {
			return array(
				'status'  => 'fail',
				'message' => \sprintf(
					// Translators: 1 host name.
					\__(
						'%1$s is not pointed to a CDN provider.  For more information on how to configure your DNS please click %2$shere%3$s.',
						'w3-total-cache'
					),
					$hostname,
					'<a href="https://www.boldgrid.com/support/w3-total-cache/total-cdn-dns-setup/" target="_blank">',
					'</a>'
				),
			);
		}

		return array(
			'status'  => 'pass',
			'message' => \__( 'The custom hostname is pointed to a CDN provider.', 'w3-total-cache' ),
		);
	}
}
