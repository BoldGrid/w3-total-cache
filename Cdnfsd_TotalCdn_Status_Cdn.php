<?php
/**
 * File: Cdnfsd_TotalCdn_Status_Cdn.php
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'W3TC' ) || die;

/**
 * Class: Cdnfsd_TotalCdn_Status_Cdn
 *
 * @since X.X.X
 */
class Cdnfsd_TotalCdn_Status_Cdn {
	/**
	 * Runs the Total CDN status test.
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
	public static function test_cdn_status() {
		$config = Dispatcher::config();

		$cdnfsd_engine = $config->get_string( 'cdnfsd.engine' );
		$cdn_hostname  = $config->get_string( 'cdn.totalcdn.cdn_hostname' );
		$hostname      = Util_Environment::get_site_hostname();

		// Build the CDN URL for the homepage using the CDN hostname.
		$cdn_home_url = 'https://' . $cdn_hostname . '/';

		// Request the homepage from the CDN.
		$response = \wp_remote_get(
			$cdn_home_url,
			array( 'headers' => array( 'Host' => $hostname ) )
		);

		if ( \is_wp_error( $response ) ) {
			return array(
				'status'  => 'fail',
				'message' => \__( 'Failed to request homepage.', 'w3-total-cache' ),
				'log'     => $response->get_error_message(),
			);
		}

		$request_headers = \wp_remote_retrieve_headers( $response );
		$request_headers = \is_a( $request_headers, 'WpOrg\Requests\Utility\CaseInsensitiveDictionary' ) ? $request_headers->getAll() : $request_headers;
		$request_headers = \array_change_key_case( $request_headers, CASE_LOWER );

		// List of potential CDN response headers.
		$cdn_headers = require W3TC_DIR . '/Cdnfsd_TotalCdn_Status_Headers.php';
		$cdn_headers = isset( $cdn_headers[ $cdnfsd_engine ] ) ? $cdn_headers[ $cdnfsd_engine ] : array();

		$found_headers = array();
		foreach ( $cdn_headers as $header ) {
			if ( isset( $request_headers[ $header ] ) ) {
				$found_headers[] = $header;
			}
		}

		if ( empty( $found_headers ) ) {
			return array(
				'status'  => 'fail',
				'message' => \__( 'No expected CDN response headers were detected. If DNS was changed recently it could take some time to propagate, please wait and try again.', 'w3-total-cache' ),
			);
		}

		if (
			! isset( $request_headers['x-w3tc-cdnfsd'] )
			|| ! isset( $request_headers['x-w3tc-hostname'] )
			|| 'totalcdn' !== $request_headers['x-w3tc-cdnfsd']
			|| $hostname !== $request_headers['x-w3tc-hostname']
		) {
			return array(
				'status'  => 'fail',
				'message' => \__( 'The expected W3TC headers were not detected.', 'w3-total-cache' ),
			);
		}

		return array(
			'status'  => 'pass',
			'message' => \__( 'The expected CDN response headers were detected.', 'w3-total-cache' ),
		);
	}
}
