<?php
/**
 * File: CdnEngine_CloudFront.php
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC_SKIPLIB_AWS' ) ) {
	require_once W3TC_DIR . '/vendor/autoload.php';
}

/**
 * Class CdnEngine_CloudFront
 *
 * Amazon CloudFront (S3 origin) CDN engine
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */
class CdnEngine_CloudFront extends CdnEngine_Base {
	/**
	 * CDN Engine S3 object
	 *
	 * @var CdnEngine_S3
	 */
	private $s3;

	/**
	 * CloudFront Client API object
	 *
	 * @var CloudFrontClient
	 */
	private $api;

	/**
	 * Constructs the CDN Engine CloudFront instance.
	 *
	 * @param array $config Configuration settings.
	 *
	 * @return void
	 */
	public function __construct( $config = array() ) {
		$config = array_merge(
			array(
				'id' => '',
			),
			$config
		);

		parent::__construct( $config );

		$this->s3 = new CdnEngine_S3( $config );
	}

	/**
	 * Initializes the CloudFront API client.
	 *
	 * @return bool Returns true if the initialization is successful.
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
				'region'      => $this->_config['bucket_location'],
				'version'     => '2018-11-05',
			)
		);

		return true;
	}

	/**
	 * Formats the URL based on the provided path.
	 *
	 * @param string $path The file path to format.
	 *
	 * @return string|false Returns the formatted URL or false if the domain is not found.
	 */
	public function _format_url( $path ) {
		$domain = $this->get_domain( $path );

		if ( $domain ) {
			$scheme = $this->_get_scheme();

			// it does not support '+', requires '%2B'.
			$path = str_replace( '+', '%2B', $path );
			$url  = sprintf( '%s://%s/%s', $scheme, $domain, $path );

			return $url;
		}

		return false;
	}

	/**
	 * Uploads files to the CloudFront CDN.
	 *
	 * @param array $files         Files to upload.
	 * @param array $results       Reference to store the results of the upload.
	 * @param bool  $force_rewrite Whether to force file overwrite.
	 * @param int   $timeout_time  Timeout duration for the upload.
	 *
	 * @return bool Returns true if upload is successful, false otherwise.
	 */
	public function upload( $files, &$results, $force_rewrite = false, $timeout_time = null ) {
		return $this->s3->upload( $files, $results, $force_rewrite, $timeout_time );
	}

	/**
	 * Deletes files from the CloudFront CDN.
	 *
	 * @param array $files   Files to delete.
	 * @param array $results Reference to store the results of the delete operation.
	 *
	 * @return bool Returns true if delete is successful, false otherwise.
	 */
	public function delete( $files, &$results ) {
		return $this->s3->delete( $files, $results );
	}

	/**
	 * Purges files from the CloudFront CDN and uploads them to S3.
	 *
	 * @param array $files   Files to purge.
	 * @param array $results Reference to store the results of the purge operation.
	 *
	 * @return bool Returns true if purge is successful, false otherwise.
	 */
	public function purge( $files, &$results ) {
		if ( ! $this->s3->upload( $files, $results, true ) ) {
			return false;
		}

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
	 * Retrieves the region based on the bucket location.
	 *
	 * @return string The region for the CDN.
	 */
	public function get_region() {
		switch ( $this->_config['bucket_location'] ) {
			case 'us-east-1':
				$region = '';
				break;
			case 'us-east-1-e':
				$region = 'us-east-1.';
				break;
			default:
				$region = $this->_config['bucket_location'] . '.';
				break;
		}

		return $region;
	}

	/**
	 * Gets the origin URL for the CloudFront CDN.
	 *
	 * @return string The origin URL.
	 */
	public function _get_origin() {
		return sprintf( '%1$s.s3.%2$samazonaws.com', $this->_config['bucket'], $this->get_region() );
	}

	/**
	 * Retrieves the list of domains for the CloudFront distribution.
	 *
	 * @return array An array of domains associated with the CDN.
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
	 * Tests the connection and configuration of the CloudFront CDN.
	 *
	 * @param string $error Reference to store any error message if the test fails.
	 *
	 * @return bool Returns true if the test passes, false otherwise.
	 */
	public function test( &$error ) {
		$this->_init();
		if ( ! $this->s3->test( $error ) ) {
			return false;
		}

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
			$error = sprintf( 'Distribution for origin "%s" is disabled.', $this->_get_origin() );
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
	 * Creates a CloudFront distribution container and returns the container ID.
	 *
	 * This method initializes the container, creates a CloudFront distribution using the provided
	 * configuration, and extracts the domain name from the distribution result. It handles CNAMEs
	 * and origins and returns the distribution's container ID based on the CloudFront domain.
	 *
	 * @return string The container ID associated with the CloudFront distribution.
	 *
	 * @throws \Exception If unable to create the distribution for the origin.
	 */
	public function create_container() {
		$this->_init();
		$this->s3->create_container();

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
									'Items'    => array( 'HEAD', 'GET' ),
									'Quantity' => 2,
								),
								'Items'         => array( 'HEAD', 'GET' ),
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
							'LambdaFunctionAssociations' => array( 'Quantity' => 0 ),
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
									'DomainName'     => $origin_domain,
									'Id'             => $origin_domain,
									'OriginPath'     => '',
									'CustomHeaders'  => array( 'Quantity' => 0 ),
									'S3OriginConfig' => array(
										'OriginAccessIdentity' => '',
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
	 * Retrieves the "via" information for the CloudFront distribution.
	 *
	 * This method fetches the domain and formats the "via" string in the format:
	 * 'Amazon Web Services: CloudFront: <domain>', returning 'N/A' if the domain is not set.
	 *
	 * @return string The formatted "via" information.
	 */
	public function get_via() {
		$domain = $this->get_domain();
		$via    = ( $domain ? $domain : 'N/A' );

		return sprintf( 'Amazon Web Services: CloudFront: %s', $via );
	}

	/**
	 * Retrieves the CloudFront distribution based on the origin.
	 *
	 * This method checks for an existing distribution matching the provided origin. If no
	 * distribution is found, it throws an exception. It can also accept an optional parameter
	 * to provide a list of distributions.
	 *
	 * @param array|null $dists Optional. A list of distributions to search through. If null,
	 *                          the list is fetched from the CloudFront API.
	 *
	 * @return array The distribution details associated with the origin.
	 *
	 * @throws \Exception If no distribution is found for the origin.
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
