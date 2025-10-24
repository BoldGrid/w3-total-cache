<?php
/**
 * File: CdnEngine_Mirror_CloudFront.php
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC_SKIPLIB_AWS' ) ) {
	require_once W3TC_DIR . '/vendor/autoload.php';
}

/**
 * Class CdnEngine_Mirror_CloudFront
 *
 * Amazon CloudFront (mirror) CDN engine
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found
 */
class CdnEngine_Mirror_CloudFront extends CdnEngine_Mirror {
	/**
	 * CloudFront Client object
	 *
	 * @var CloudFrontClient
	 */
	private $api;

	/**
	 * Constructor for the CDN Engine CloudFront class.
	 *
	 * @param array $config Configuration array for CloudFront client.
	 *
	 * @return void
	 */
	public function __construct( $config = array() ) {
		parent::__construct( $config );
	}

	/**
	 * Initializes the CloudFront client API.
	 *
	 * @return bool Returns true if the CloudFront client is successfully initialized.
	 *
	 * @throws \Exception If the initialization fails.
	 */
	public function _init() {
		if ( ! is_null( $this->api ) ) {
			return;
		}

		if ( empty( $this->_config['key'] ) && empty( $this->_config['secret'] ) ) {
			$credentials = \Aws\Credentials\CredentialProvider::defaultProvider();
		} else {
			$credentials = new \Aws\Credentials\Credentials(
				$this->_config['key'],
				$this->_config['secret']
			);
		}

		$this->api = new \Aws\CloudFront\CloudFrontClient(
			array(
				'credentials' => $credentials,
				'region'      => 'us-east-1',
				'version'     => '2018-11-05',
			)
		);

		return true;
	}

	/**
	 * Retrieves the origin for the CDN.
	 *
	 * @return string The host and port of the origin.
	 */
	public function _get_origin() {
		return Util_Environment::host_port();
	}

	/**
	 * Purges the specified files from the CDN.
	 *
	 * @param array $files  Array of files to purge from the CDN.
	 * @param array $results Reference to an array where the purge results will be stored.
	 *
	 * @return bool Returns true if the purge is successful, otherwise false.
	 *
	 * @throws \Exception If the purge fails.
	 */
	public function purge( $files, &$results ) {
		try {
			$this->_init();
			$dist = $this->_get_distribution();
		} catch ( \Exception $ex ) {
			$results = $this->_get_results( $files, W3TC_CDN_RESULT_HALT, $ex->getMessage() );
			return false;
		}

		$paths = array();

		foreach ( $files as $file ) {
			$remote_file = $file['remote_path'];
			$paths[]     = '/' . $remote_file;
		}

		try {
			$invalidation = $this->api->createInvalidation(
				array(
					'DistributionId'    => $dist['Id'],
					'InvalidationBatch' => array(
						'CallerReference' => 'w3tc-' . microtime(),
						'Paths'           => array(
							'Items'    => $paths,
							'Quantity' => count( $paths ),
						),
					),
				)
			);
		} catch ( \Exception $ex ) {
			$results = $this->_get_results(
				$files,
				W3TC_CDN_RESULT_HALT,
				sprintf( 'Unable to create invalidation batch (%s).', $ex->getMessage() )
			);

			return false;
		}

		$results = $this->_get_results( $files, W3TC_CDN_RESULT_OK, 'OK' );

		return true;
	}

	/**
	 * Purges all files from the CDN.
	 *
	 * @param array $results Reference to an array where the purge results will be stored.
	 *
	 * @return bool Returns true if the purge is successful, otherwise false.
	 */
	public function purge_all( &$results ) {
		return $this->purge( array( array( 'remote_path' => '*' ) ), $results );
	}

	/**
	 * Retrieves the domains associated with the CDN.
	 *
	 * @return array Array of domain names associated with the CDN.
	 */
	public function get_domains() {
		if ( ! empty( $this->_config['cname'] ) ) {
			return (array) $this->_config['cname'];
		} elseif ( ! empty( $this->_config['id'] ) ) {
			$domain = sprintf( '%s.cloudfront.net', $this->_config['id'] );

			return array(
				$domain,
			);
		}

		return array();
	}

	/**
	 * Tests the CloudFront distribution connection and configuration.
	 *
	 * @param string $error Reference to a variable where error messages will be stored.
	 *
	 * @return bool Returns true if the test passes, otherwise false.
	 */
	public function test( &$error ) {
		$this->_init();

		/**
		 * Search active CF distribution
		 */
		$dists = $this->api->listDistributions();

		if ( ! isset( $dists['DistributionList']['Items'] ) ) {
			$error = 'Unable to list distributions.';
			return false;
		}

		if ( ! count( $dists['DistributionList']['Items'] ) ) {
			$error = 'No distributions found.';

			return false;
		}

		$dist = $this->_get_distribution( $dists );
		if ( 'Deployed' !== $dist['Status'] ) {
			$error = sprintf( 'Distribution status is not Deployed, but "%s".', $dist['Status'] );
			return false;
		}

		if ( ! $dist['Enabled'] ) {
			$error = sprintf( 'Distribution for origin "%s" is disabled.', $origin );
			return false;
		}

		if ( ! empty( $this->_config['cname'] ) ) {
			$domains = (array) $this->_config['cname'];
			$cnames  = ( isset( $dist['Aliases']['Items'] ) ? (array) $dist['Aliases']['Items'] : array() );

			foreach ( $domains as $domain ) {
				$_domains = array_map( 'trim', explode( ',', $domain ) );

				foreach ( $_domains as $_domain ) {
					if ( ! in_array( $_domain, $cnames, true ) ) {
						$error = sprintf( 'Domain name %s is not in distribution <acronym title="Canonical Name">CNAME</acronym> list.', $_domain );

						return false;
					}
				}
			}
		} elseif ( ! empty( $this->_config['id'] ) ) {
			$domain = $this->get_domain();

			if ( $domain !== $dist['DomainName'] ) {
				$error = sprintf( 'Distribution domain name mismatch (%s != %s).', $domain, $dist['DomainName'] );

				return false;
			}
		}

		return true;
	}

	/**
	 * Creates a new CloudFront distribution container.
	 *
	 * phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
	 *
	 * @return string The ID of the newly created distribution container.
	 *
	 * @throws \Exception If the distribution creation fails.
	 */
	public function create_container() {
		$this->_init();

		// plugin cant set CNAMEs list since it CloudFront requires certificate to be specified associated with it.
		$cnames = array();

		// make distibution.
		$origin_domain = $this->_get_origin();

		try {
			$result = $this->api->createDistribution(
				array(
					'DistributionConfig' => array(
						'CallerReference'      => $origin_domain,
						'Comment'              => 'Created by W3-Total-Cache',
						'DefaultCacheBehavior' => array(
							'AllowedMethods'             => array(
								'CachedMethods' => array(
									'Items'    => array(
										'HEAD',
										'GET',
									),
									'Quantity' => 2,
								),
								'Items'         => array(
									'HEAD',
									'GET',
								),
								'Quantity'      => 2,
							),
							'Compress'                   => true,
							'DefaultTTL'                 => 86400,
							'FieldLevelEncryptionId'     => '',
							'ForwardedValues'            => array(
								'Cookies'              => array(
									'Forward' => 'none',
								),
								'Headers'              => array(
									'Quantity' => 0,
								),
								'QueryString'          => false,
								'QueryStringCacheKeys' => array(
									'Quantity' => 0,
								),
							),
							'LambdaFunctionAssociations' => array(
								'Quantity' => 0,
							),
							'MinTTL'                     => 0,
							'SmoothStreaming'            => false,
							'TargetOriginId'             => $origin_domain,
							'TrustedSigners'             => array(
								'Enabled'  => false,
								'Quantity' => 0,
							),
							'ViewerProtocolPolicy'       => 'allow-all',
						),
						'Enabled'              => true,
						'Origins'              => array(
							'Items'    => array(
								array(
									'DomainName'         => $origin_domain,
									'Id'                 => $origin_domain,
									'OriginPath'         => '',
									'CustomHeaders'      => array(
										'Quantity' => 0,
									),
									'CustomOriginConfig' => array(
										'HTTPPort'             => 80,
										'HTTPSPort'            => 443,
										'OriginProtocolPolicy' => 'match-viewer',
									),
								),
							),
							'Quantity' => 1,
						),
						'Aliases'              => array(
							'Items'    => $cnames,
							'Quantity' => count( $cnames ),
						),
					),
				)
			);

			// extract domain dynamic part stored later in a config.
			$domain       = $result['Distribution']['DomainName'];
			$container_id = '';
			if ( preg_match( '~^(.+)\.cloudfront\.net$~', $domain, $matches ) ) {
				$container_id = $matches[1];
			}

			return $container_id;

		} catch ( \Aws\Exception\AwsException $ex ) {
			throw new \Exception(
				\esc_html(
					sprintf(
						// Translators: 1 Origin domain name, 2 AWS error message.
						\__( 'Unable to create distribution for origin %1$s: %2$s', 'w3-total-cache' ),
						$origin_domain,
						$ex->getAwsErrorMessage()
					)
				)
			);
		} catch ( \Exception $ex ) {
			throw new \Exception(
				\esc_html(
					sprintf(
						// Translators: 1 Origin domain name, 2 Error message.
						\__( 'Unable to create distribution for origin %1$s: %2$s', 'w3-total-cache' ),
						$origin_domain,
						$ex->getMessage()
					)
				)
			);
		}
	}

	/**
	 * Retrieves the CDN's "via" information.
	 *
	 * @return string The "via" string indicating the CDN's origin.
	 */
	public function get_via() {
		$domain = $this->get_domain();
		$via    = ( $domain ? $domain : 'N/A' );

		return sprintf( 'Amazon Web Services: CloudFront: %s', $via );
	}

	/**
	 * Retrieves the CloudFront distribution for the origin.
	 *
	 * @param array|null $dists Optional array of distributions to search through. If null, all distributions are fetched.
	 *
	 * @return array The distribution information for the origin.
	 *
	 * @throws \Exception If no matching distribution is found.
	 */
	private function _get_distribution( $dists = null ) {
		if ( is_null( $dists ) ) {
			$dists = $this->api->listDistributions();
		}

		if ( ! isset( $dists['DistributionList']['Items'] ) || ! count( $dists['DistributionList']['Items'] ) ) {
			throw new \Exception( \esc_html__( 'No distributions found.', 'w3-total-cache' ) );
		}

		$dist   = false;
		$origin = $this->_get_origin();

		$items = $dists['DistributionList']['Items'];
		foreach ( $items as $dist ) {
			if ( isset( $dist['Origins']['Items'] ) ) {
				foreach ( $dist['Origins']['Items'] as $o ) {
					if ( isset( $o['DomainName'] ) && $o['DomainName'] === $origin ) {
						return $dist;
					}
				}
			}
		}

		throw new \Exception(
			\esc_html(
				sprintf(
					// Translators: 1 Origin name.
					\__( 'Distribution for origin "%1$s" not found.', 'w3-total-cache' ),
					$origin
				)
			)
		);
	}
}
