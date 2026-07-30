<?php
/**
 * File: CdnEngine_RackSpaceCloudFiles.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class CdnEngine_RackSpaceCloudFiles
 *
 * Rackspace Cloud Files CDN engine
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class CdnEngine_RackSpaceCloudFiles extends CdnEngine_Base {
	/**
	 * Access state
	 *
	 * @var array
	 */
	private $_access_state;

	/**
	 * Container
	 *
	 * @var object
	 */
	private $_container;

	/**
	 * CDN RackSpace API CloudFiles object
	 *
	 * @var Cdn_RackSpace_Api_CloudFiles
	 */
	private $_api_files;

	/**
	 * CDN RackSpace API CloudFilesCdn object
	 *
	 * @var Cdn_RackSpace_Api_CloudFilesCdn
	 */
	private $_api_cdn;

	/**
	 * Callback function to handle the updated access state.
	 *
	 * This callback is invoked with a JSON-encoded string containing the new access state
	 * whenever authentication occurs and the access state is refreshed.
	 *
	 * @var callable
	 */
	private $_new_access_state_callback;

	/**
	 * Initializes the CdnEngine_RackSpaceCloudFiles class with configuration.
	 *
	 * @param array $w3tc_config Configuration options for the class.
	 *
	 * @return void
	 */
	public function __construct( $w3tc_config = array() ) {
		$w3tc_config = array_merge(
			array(
				'user_name'    => '',
				'api_key'      => '',
				'region'       => '',
				'container'    => '',
				'cname'        => array(),
				'access_state' => '',
			),
			$w3tc_config
		);

		$this->_container                 = $w3tc_config['container'];
		$this->_new_access_state_callback = $w3tc_config['new_access_state_callback'];

		// init access state.
		$this->_access_state = @json_decode( $w3tc_config['access_state'], true );
		if ( ! is_array( $this->_access_state ) ) {
			$this->_access_state = array();
		}

		$this->_access_state = array_merge(
			array(
				'access_token'             => '',
				'access_region_descriptor' => array(),
				'host_http'                => '',
				'host_https'               => '',
			),
			$this->_access_state
		);

		parent::__construct( $w3tc_config );
		$this->_create_api(
			array( $this, '_on_new_access_requested_api_files' ),
			array( $this, '_on_new_access_requested_api_cdn' )
		);
	}

	/**
	 * Creates API instances for files and CDN.
	 *
	 * @param callable $new_access_required_callback_api_files Callback for file API access requests.
	 * @param callable $new_access_required_callback_api_cdn   Callback for CDN API access requests.
	 *
	 * @return void
	 */
	private function _create_api( $new_access_required_callback_api_files, $new_access_required_callback_api_cdn ) {
		$this->_api_files = new Cdn_RackSpace_Api_CloudFiles(
			array(
				'access_token'             => $this->_access_state['access_token'],
				'access_region_descriptor' => $this->_access_state['access_region_descriptor'],
				'new_access_required'      => $new_access_required_callback_api_files,
			)
		);

		$this->_api_cdn = new Cdn_RackSpace_Api_CloudFilesCdn(
			array(
				'access_token'             => $this->_access_state['access_token'],
				'access_region_descriptor' => $this->_access_state['access_region_descriptor'],
				'new_access_required'      => $new_access_required_callback_api_cdn,
			)
		);
	}

	/**
	 * Handles new access requests for file API.
	 *
	 * @return Cdn_RackSpace_Api_CloudFiles Instance of the file API.
	 */
	public function _on_new_access_requested_api_files() {
		$this->_on_new_access_requested();
		return $this->_api_files;
	}

	/**
	 * Handles new access requests for CDN API.
	 *
	 * @return Cdn_RackSpace_Api_CloudFilesCdn Instance of the CDN API.
	 */
	public function _on_new_access_requested_api_cdn() {
		$this->_on_new_access_requested();
		return $this->_api_cdn;
	}

	/**
	 * Processes new access requests and updates the access state.
	 *
	 * @return void
	 *
	 * @throws \Exception If authentication fails or the region is not found.
	 */
	private function _on_new_access_requested() {
		$w3tc_r = Cdn_RackSpace_Api_Tokens::authenticate( $this->_config['user_name'], $this->_config['api_key'] );
		if ( ! isset( $w3tc_r['access_token'] ) || ! isset( $w3tc_r['services'] ) ) {
			throw new \Exception( \esc_html__( 'Authentication failed.', 'w3-total-cache' ) );
		}

		$w3tc_r['regions'] = Cdn_RackSpace_Api_Tokens::cloudfiles_services_by_region( $w3tc_r['services'] );

		if ( ! isset( $w3tc_r['regions'][ $this->_config['region'] ] ) ) {
			throw new \Exception(
				\esc_html(
					sprintf(
						// translators: 1: Region name.
						\__( 'Region %1$s not found.', 'w3-total-cache' ),
						$this->_config['region']
					)
				)
			);
		}

		$this->_access_state['access_token']             = $w3tc_r['access_token'];
		$this->_access_state['access_region_descriptor'] = $w3tc_r['regions'][ $this->_config['region'] ];

		$this->_create_api(
			array( $this, '_on_new_access_requested_second_time' ),
			array( $this, '_on_new_access_requested_second_time' )
		);

		$w3tc_c = $this->_api_cdn->container_get( $this->_config['container'] );

		$this->_access_state['host_http']  = substr( $w3tc_c['x-cdn-uri'], 7 );
		$this->_access_state['host_https'] = substr( $w3tc_c['x-cdn-ssl-uri'], 8 );

		call_user_func( $this->_new_access_state_callback, wp_json_encode( $this->_access_state ) );
	}

	/**
	 * Handles repeated access requests in case of authentication failure.
	 *
	 * @return void
	 *
	 * @throws \Exception Always throws an exception for failed authentication.
	 */
	private function _on_new_access_requested_second_time() {
		throw new \Exception( \esc_html__( 'Authentication failed.', 'w3-total-cache' ) );
	}

	/**
	 * Formats a URL for a given file path.
	 *
	 * @param string $path The file path to format.
	 *
	 * @return string|false The formatted URL, or false if the domain is not found.
	 */
	public function _format_url( $path ) {
		$domain = $this->get_domain( $path );

		if ( $domain ) {
			$scheme = $this->_get_scheme();

			// it does not support '+', requires '%2B'.
			$path     = str_replace( '+', '%2B', $path );
			$w3tc_url = sprintf( '%s://%s/%s', $scheme, $domain, $path );

			return $w3tc_url;
		}

		return false;
	}

	/**
	 * Uploads files to Rackspace Cloud Files.
	 *
	 * @param array    $files         Array of file descriptors for upload.
	 * @param array    $results       Reference to an array for storing results.
	 * @param bool     $force_rewrite Whether to force overwriting existing files.
	 * @param int|null $timeout_time  Optional timeout time in seconds.
	 *
	 * @return bool True on success, false on error.
	 */
	public function upload( $files, &$results, $force_rewrite = false, $timeout_time = null ) {
		foreach ( $files as $w3tc_file ) {
			$local_path  = $w3tc_file['local_path'];
			$remote_path = $w3tc_file['remote_path'];

			// process at least one item before timeout so that progress goes on.
			if ( ! empty( $results ) && ! is_null( $timeout_time ) && time() > $timeout_time ) {
				return 'timeout';
			}

			if ( ! file_exists( $local_path ) ) {
				$results[] = $this->_get_result(
					$local_path,
					$remote_path,
					W3TC_CDN_RESULT_ERROR,
					'Source file not found.',
					$w3tc_file
				);

				continue;
			}

			$file_content = file_get_contents( $local_path );

			$do_write = true;

			// rewrite is optional, check md5.
			if ( ! $force_rewrite ) {
				$object_meta = null;

				try {
					$object_meta = $this->_api_files->object_get_meta_or_null( $this->_container, $remote_path );
				} catch ( \Exception $exception ) {
					$results[] = $this->_get_result(
						$local_path,
						$remote_path,
						W3TC_CDN_RESULT_ERROR,
						sprintf( 'Unable to check object (%s).', $exception->getMessage() ),
						$w3tc_file
					);
					$do_write  = false;
				}

				if ( is_array( $object_meta ) && isset( $object_meta['etag'] ) ) {
					$md5_actual = md5( $file_content );

					if ( $md5_actual === $object_meta['etag'] ) {
						$results[] = $this->_get_result(
							$local_path,
							$remote_path,
							W3TC_CDN_RESULT_OK,
							'Object up-to-date.',
							$w3tc_file
						);
						$do_write  = false;
					}
				}
			}

			if ( $do_write ) {
				try {
					$this->_api_files->object_create(
						array(
							'container'    => $this->_container,
							'name'         => $remote_path,
							'content_type' => Util_Mime::get_mime_type( $local_path ),
							'content'      => $file_content,
						)
					);
					$results[] = $this->_get_result(
						$local_path,
						$remote_path,
						W3TC_CDN_RESULT_OK,
						'OK',
						$w3tc_file
					);
				} catch ( \Exception $exception ) {
					$results[] = $this->_get_result(
						$local_path,
						$remote_path,
						W3TC_CDN_RESULT_ERROR,
						sprintf( 'Unable to create object (%s).', $exception->getMessage() ),
						$w3tc_file
					);
				}
			}
		}

		return ! $this->_is_error( $results );
	}

	/**
	 * Deletes files from Rackspace Cloud Files.
	 *
	 * @param array $files   Array of file descriptors to delete.
	 * @param array $results Reference to an array for storing results.
	 *
	 * @return bool True on success, false on error.
	 */
	public function delete( $files, &$results ) {
		foreach ( $files as $w3tc_file ) {
			$local_path  = $w3tc_file['local_path'];
			$remote_path = $w3tc_file['remote_path'];

			try {
				$this->_api_files->object_delete( $this->_container, $remote_path );
				$results[] = $this->_get_result(
					$local_path,
					$remote_path,
					W3TC_CDN_RESULT_OK,
					'OK',
					$w3tc_file
				);
			} catch ( \Exception $exception ) {
				$results[] = $this->_get_result(
					$local_path,
					$remote_path,
					W3TC_CDN_RESULT_ERROR,
					sprintf( 'Unable to delete object (%s).', $exception->getMessage() ),
					$w3tc_file
				);
			}
		}

		return ! $this->_is_error( $results );
	}

	/**
	 * Tests the connection to Rackspace Cloud Files.
	 *
	 * @param string $error Reference to store error messages, if any.
	 *
	 * @return bool True if the test succeeds, false otherwise.
	 */
	public function test( &$error ) {
		$filename = 'test_rscf_' . md5( time() );

		try {
			$object = $this->_api_files->object_create(
				array(
					'container'    => $this->_container,
					'name'         => $filename,
					'content_type' => 'text/plain',
					'content'      => $filename,
				)
			);
		} catch ( \Exception $exception ) {
			$error = sprintf( 'Unable to write object (%s).', $exception->getMessage() );
			return false;
		}

		$w3tc_result = true;
		try {
			$w3tc_r = wp_remote_get( 'http://' . $this->get_host_http() . '/' . $filename );

			if ( $w3tc_r['body'] !== $filename ) {
				$error       = 'Failed to retrieve object after storing.';
				$w3tc_result = false;
			}
		} catch ( \Exception $exception ) {
			$error       = sprintf( 'Unable to read object (%s).', $exception->getMessage() );
			$w3tc_result = false;
		}

		try {
			$this->_api_files->object_delete( $this->_container, $filename );
		} catch ( \Exception $exception ) {
			$error       = sprintf( 'Unable to delete object (%s).', $exception->getMessage() );
			$w3tc_result = false;
		}

		return $w3tc_result;
	}

	/**
	 * Retrieves the list of available domains for the service.
	 *
	 * @return array List of domain names.
	 */
	public function get_domains() {
		if ( Util_Environment::is_https() ) {
			if ( ! empty( $this->_config['cname'] ) ) {
				return (array) $this->_config['cname'];
			}

			return array( $this->get_host_https() );
		} else {
			if ( ! empty( $this->_config['cname'] ) ) {
				return (array) $this->_config['cname'];
			}

			return array( $this->get_host_http() );
		}
	}

	/**
	 * Retrieves the service descriptor including the domain used for accessing the CDN.
	 *
	 * @return string Descriptor of the service including domain used for accessing the CDN.
	 */
	public function get_via() {
		return sprintf( 'Rackspace Cloud Files: %s', parent::get_via() );
	}

	/**
	 * Retrieves the HTTP host URL.
	 *
	 * @return string The HTTP host URL.
	 */
	public function get_host_http() {
		if ( empty( $this->_access_state['host_http'] ) ) {
			$this->_on_new_access_requested();
		}

		return $this->_access_state['host_http'];
	}

	/**
	 * Retrieves the HTTPS host URL.
	 *
	 * @return string The HTTPS host URL.
	 */
	public function get_host_https() {
		if ( empty( $this->_access_state['host_https'] ) ) {
			$this->_on_new_access_requested();
		}

		return $this->_access_state['host_https'];
	}
}
