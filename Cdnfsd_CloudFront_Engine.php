<?php
/**
 * File: Cdnfsd_CloudFront_Engine.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC_SKIPLIB_AWS' ) ) {
	require_once W3TC_DIR . '/vendor/autoload.php';
}

/**
 * Class Cdnfsd_CloudFront_Engine
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */
class Cdnfsd_CloudFront_Engine {
	/**
	 * Access key
	 *
	 * @var string
	 */
	private $access_key;

	/**
	 * Secret key
	 *
	 * @var string
	 */
	private $secret_key;

	/**
	 * Distribution ID
	 *
	 * @var string
	 */
	private $distribution_id;

	/**
	 * Constructor for the CloudFront engine.
	 *
	 * @param array $w3tc_config Configuration array with keys:
	 *                      'access_key'      - AWS access key.
	 *                      'secret_key'      - AWS secret key.
	 *                      'distribution_id' - CloudFront distribution ID.
	 *
	 * @return void
	 */
	public function __construct( $w3tc_config = array() ) {
		$this->access_key      = $w3tc_config['access_key'];
		$this->secret_key      = $w3tc_config['secret_key'];
		$this->distribution_id = $w3tc_config['distribution_id'];
	}

	/**
	 * Flushes a list of URLs from the CloudFront cache.
	 *
	 * @param array $urls Array of URLs to be invalidated.
	 *
	 * @return void
	 */
	public function flush_urls( $urls ) {
		$api = $this->_api();

		$uris = array();
		foreach ( $urls as $w3tc_url ) {
			$parsed       = wp_parse_url( $w3tc_url );
			$relative_url = ( isset( $parsed['path'] ) ? $parsed['path'] : '/' ) .
				( isset( $parsed['query'] ) ? '?' . $parsed['query'] : '' );
			$uris[]       = $relative_url;
		}

		$api->createInvalidation(
			array(
				'DistributionId'    => $this->distribution_id,
				'InvalidationBatch' => array(
					'CallerReference' => 'w3tc-' . microtime(),
					'Paths'           => array(
						'Items'    => $uris,
						'Quantity' => count( $uris ),
					),
				),
			)
		);
	}

	/**
	 * Flushes the entire CloudFront cache.
	 *
	 * @return void
	 */
	public function flush_all() {
		$api  = $this->_api();
		$uris = apply_filters( 'w3tc_cdn_cf_flush_all_uris', array( '/*' ) );

		$api->createInvalidation(
			array(
				'DistributionId'    => $this->distribution_id,
				'InvalidationBatch' => array(
					'CallerReference' => 'w3tc-' . microtime(),
					'Paths'           => array(
						'Items'    => $uris,
						'Quantity' => count( $uris ),
					),
				),
			)
		);
	}

	/**
	 * Creates and returns an instance of the CloudFront API client.
	 *
	 * @return \Aws\CloudFront\CloudFrontClient Instance of the CloudFront client.
	 *
	 * @throws \Exception If required credentials or distribution ID are missing.
	 */
	private function _api() {
		if ( empty( $this->distribution_id ) ) {
			throw new \Exception( \esc_html__( 'CloudFront distribution not specified.', 'w3-total-cache' ) );
		}

		if ( empty( $this->access_key ) && empty( $this->secret_key ) ) {
			$credentials = \Aws\Credentials\CredentialProvider::defaultProvider();
		} else {
			if ( empty( $this->access_key ) ) {
				throw new \Exception( \esc_html__( 'Access key not specified.', 'w3-total-cache' ) );
			}

			if ( empty( $this->secret_key ) ) {
				throw new \Exception( \esc_html__( 'Secret key not specified.', 'w3-total-cache' ) );
			}

			$credentials = new \Aws\Credentials\Credentials( $this->access_key, $this->secret_key );
		}

		return new \Aws\CloudFront\CloudFrontClient(
			array(
				'credentials' => $credentials,
				'region'      => 'us-east-1',
				'version'     => '2018-11-05',
			)
		);
	}
}
