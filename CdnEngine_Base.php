<?php
/**
 * File: CdnEngine_Base.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * W3 CDN Base class
 */
define( 'W3TC_CDN_RESULT_HALT', -1 );
define( 'W3TC_CDN_RESULT_ERROR', 0 );
define( 'W3TC_CDN_RESULT_OK', 1 );
define( 'W3TC_CDN_HEADER_NONE', 'none' );
define( 'W3TC_CDN_HEADER_UPLOADABLE', 'uploadable' );
define( 'W3TC_CDN_HEADER_MIRRORING', 'mirroring' );

/**
 * Class CdnEngine_Base
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class CdnEngine_Base {
	/**
	 * Engine configuration
	 *
	 * @var array
	 */
	protected $_config = array();

	/**
	 * Gzip extension
	 *
	 * @var string
	 */
	protected $_gzip_extension = '.gzip';

	/**
	 * Last error
	 *
	 * @var string
	 */
	protected $_last_error = '';

	/**
	 * Constructor method for initializing the CdnEngine_Base object with configuration settings.
	 *
	 * @param array $config Optional. An array of configuration options to override default values.
	 *                      Defaults include 'debug', 'ssl', 'compression', and 'headers'.
	 */
	public function __construct( $config = array() ) {
		$this->_config = array_merge(
			array(
				'debug'       => false,
				'ssl'         => 'auto',
				'compression' => false,
				'headers'     => array(),
			),
			$config
		);
	}

	/**
	 * Upload files to the CDN.
	 *
	 * @param array $files         An array of files to upload.
	 * @param array $results       A reference to an array where results will be stored.
	 * @param bool  $force_rewrite Optional. Whether to force a rewrite. Default is false.
	 * @param int   $timeout_time  Optional. The timeout time in seconds. Default is null.
	 *
	 * @return bool False on failure.
	 */
	public function upload( $files, &$results, $force_rewrite = false, $timeout_time = null ) {
		$results = $this->_get_results(
			$files,
			W3TC_CDN_RESULT_HALT,
			'Not implemented.'
		);

		return false;
	}

	/**
	 * Delete files from the CDN.
	 *
	 * @param array $files   An array of files to delete.
	 * @param array $results A reference to an array where results will be stored.
	 *
	 * @return bool False on failure.
	 */
	public function delete( $files, &$results ) {
		$results = $this->_get_results(
			$files,
			W3TC_CDN_RESULT_HALT,
			'Not implemented.'
		);

		return false;
	}

	/**
	 * Purge files from the CDN.
	 *
	 * @param array $files   An array of files to purge.
	 * @param array $results A reference to an array where results will be stored.
	 *
	 * @return bool False on failure.
	 */
	public function purge( $files, &$results ) {
		return $this->upload( $files, $results, true );
	}

	/**
	 * Purge all files from the CDN.
	 *
	 * @param array $results A reference to an array where results will be stored.
	 *
	 * @return bool False on failure.
	 */
	public function purge_all( &$results ) {
		$results = $this->_get_results(
			array(),
			W3TC_CDN_RESULT_HALT,
			'Not implemented.'
		);

		return false;
	}

	/**
	 * Test the connection to the CDN.
	 *
	 * @param string $error A reference to a variable where any error message will be stored.
	 *
	 * @return bool True if the test is successful, false otherwise.
	 */
	public function test( &$error ) {
		if ( ! $this->_test_domains( $error ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Create a container on the CDN.
	 *
	 * @throws \Exception If the method is not implemented.
	 */
	public function create_container() {
		throw new \Exception( \esc_html__( 'Not implemented.', 'w3-total-cache' ) );
	}

	/**
	 * Get the appropriate domain for a given path.
	 *
	 * @param string $path Optional. The path to check. Default is an empty string.
	 *
	 * @return string|false The selected domain or false if no domain is found.
	 */
	public function get_domain( $path = '' ) {
		$domains = $this->get_domains();
		$count   = count( $domains );

		if ( $count ) {
			switch ( true ) {
				/**
				 * Reserved CSS
				 */
				case ( isset( $domains[0] ) && $this->_is_css( $path ) ):
					$domain = $domains[0];
					break;

				/**
				 * Reserved JS after body
				 */
				case ( isset( $domains[2] ) && $this->_is_js_body( $path ) ):
					$domain = $domains[2];
					break;

				/**
				 * Reserved JS before /body
				 */
				case ( isset( $domains[3] ) && $this->_is_js_footer( $path ) ):
					$domain = $domains[3];
					break;

				/**
				 * Reserved JS in head, moved here due to greedy regex
				 */
				case ( isset( $domains[1] ) && $this->_is_js( $path ) ):
					$domain = $domains[1];
					break;

				default:
					if ( ! isset( $domains[0] ) ) {
						$scheme = $this->_get_scheme();
						if ( 'https' === $scheme && ! empty( $domains['https_default'] ) ) {
							return $domains['https_default'];
						} else {
							return isset( $domains['http_default'] ) ? $domains['http_default'] :
								$domains['https_default'];
						}
					} elseif ( $count > 4 ) {
						$domain = $this->_get_domain( array_slice( $domains, 4 ), $path );
					} else {
						$domain = $this->_get_domain( $domains, $path );
					}
			}

			/**
			 * Custom host for SSL
			 */
			list( $domain_http, $domain_https ) = array_map( 'trim', explode( ',', $domain . ',' ) );

			$scheme = $this->_get_scheme();

			switch ( $scheme ) {
				case 'http':
					$domain = $domain_http;
					break;

				case 'https':
					$domain = ( $domain_https ? $domain_https : $domain_http );
					break;
			}

			return $domain;
		}

		return false;
	}

	/**
	 * Get all available domains.
	 *
	 * @return array An array of domains.
	 */
	public function get_domains() {
		return array();
	}

	/**
	 * Get the domain used for accessing the CDN.
	 *
	 * @return string The domain URL.
	 */
	public function get_via() {
		$domain = $this->get_domain();

		if ( $domain ) {
			return $domain;
		}

		return 'N/A';
	}

	/**
	 * Format a URL for the given path.
	 *
	 * @param string $path The path to format.
	 *
	 * @return string|false The formatted URL or false on failure.
	 */
	public function format_url( $path ) {
		$url = $this->_format_url( $path );

		if ( $url && $this->_config['compression'] && ( isset( $_SERVER['HTTP_ACCEPT_ENCODING'] ) ? stristr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_ENCODING'] ) ), 'gzip' ) !== false : false ) && $this->_may_gzip( $path ) ) {
			$qpos = strpos( $url, '?' );
			if ( false !== $qpos ) {
				$url = substr_replace( $url, $this->_gzip_extension, $qpos, 0 );
			} else {
				$url .= $this->_gzip_extension;
			}
		}

		return $url;
	}

	/**
	 * Get the URL to prepend to a given path.
	 *
	 * @param string $path The path to prepend the URL to.
	 *
	 * @return string|false The full URL or false if no domain is found.
	 */
	public function get_prepend_path( $path ) {
		$domain = $this->get_domain( $path );

		if ( $domain ) {
			$scheme = $this->_get_scheme();
			$url    = sprintf( '%s://%s', $scheme, $domain );

			return $url;
		}

		return false;
	}

	/**
	 * Format a URL for the given path, with the appropriate scheme and domain.
	 *
	 * @param string $path The path to format.
	 *
	 * @return string|false The formatted URL or false if no domain is found.
	 */
	public function _format_url( $path ) {
		$domain = $this->get_domain( $path );

		if ( $domain ) {
			$scheme = $this->_get_scheme();
			$url    = sprintf( '%s://%s/%s', $scheme, $domain, $path );

			return $url;
		}

		return false;
	}

	/**
	 * Get results for a set of files.
	 *
	 * @param array  $files  The files for which results are generated.
	 * @param string $result Optional. The result status. Default is W3TC_CDN_RESULT_OK.
	 * @param string $error  Optional. The error message. Default is 'OK'.
	 *
	 * @return array An array of results for each file.
	 */
	public function _get_results( $files, $result = W3TC_CDN_RESULT_OK, $error = 'OK' ) {
		$results = array();

		foreach ( $files as $key => $file ) {
			if ( is_array( $file ) ) {
				$local_path  = $file['local_path'];
				$remote_path = $file['remote_path'];
			} else {
				$local_path  = $key;
				$remote_path = $file;
			}

			$results[] = $this->_get_result(
				$local_path,
				$remote_path,
				$result,
				$error,
				$file
			);
		}

		return $results;
	}

	/**
	 * Retrieves the result data for a local and remote file path.
	 *
	 * @param string     $local_path  The local file path.
	 * @param string     $remote_path The remote file path.
	 * @param int        $result      The result status (default is W3TC_CDN_RESULT_OK).
	 * @param string     $error       The error message (default is 'OK').
	 * @param mixed|null $descriptor  Additional descriptor (default is null).
	 *
	 * @return array The result array containing local path, remote path, result, error, and descriptor.
	 */
	public function _get_result( $local_path, $remote_path, $result = W3TC_CDN_RESULT_OK, $error = 'OK', $descriptor = null ) {
		if ( $this->_config['debug'] ) {
			$this->_log( $local_path, $remote_path, $error );
		}

		return array(
			'local_path'  => $local_path,
			'remote_path' => $remote_path,
			'result'      => $result,
			'error'       => $error,
			'descriptor'  => $descriptor,
		);
	}

	/**
	 * Checks if any of the results contain an error.
	 *
	 * @param array $results The results to check.
	 *
	 * @return bool True if any result is an error, otherwise false.
	 */
	public function _is_error( $results ) {
		foreach ( $results as $result ) {
			if ( W3TC_CDN_RESULT_OK !== $result['result'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Retrieves the HTTP headers for a given file.
	 *
	 * @param array $file      The file data array containing local path and original URL.
	 * @param array $whitelist Optional whitelist for specific headers (default is empty).
	 *
	 * @return array The HTTP headers for the file.
	 */
	public function get_headers_for_file( $file, $whitelist = array() ) {
		$local_path = $file['local_path'];
		$mime_type  = Util_Mime::get_mime_type( $local_path );

		$link = $file['original_url'];

		$headers = array(
			'Content-Type'                => $mime_type,
			'Last-Modified'               => Util_Content::http_date( time() ),
			'Access-Control-Allow-Origin' => '*',
			'Link'                        => '<' . $link . '>; rel="canonical"',
		);

		$section = Util_Mime::mime_type_to_section( $mime_type );

		if ( isset( $this->_config['headers'][ $section ] ) ) {
			$hc = $this->_config['headers'][ $section ];

			if ( isset( $whitelist['ETag'] ) && $hc['etag'] ) {
				$headers['ETag'] = '"' . @md5_file( $local_path ) . '"';
			}

			if ( $hc['expires'] ) {
				$headers['Expires'] = Util_Content::http_date( time() + $hc['lifetime'] );
				$expires_set        = true;
			}

			$headers = array_merge( $headers, $hc['static'] );
		}

		return $headers;
	}

	/**
	 * Determines whether a file may be compressed using Gzip.
	 *
	 * @param string $file The file path.
	 *
	 * @return bool True if the file may be gzipped, otherwise false.
	 */
	public function _may_gzip( $file ) {
		/**
		 * Remove query string
		 */
		$file = preg_replace( '~\?.*$~', '', $file );

		/**
		 * Check by file extension
		 */
		if ( preg_match( '~\.(ico|js|css|xml|xsd|xsl|svg|htm|html|txt)$~i', $file ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Tests the configured domains for valid hostnames.
	 *
	 * @param string $error A reference to store the error message if any domain is invalid.
	 *
	 * @return bool True if all domains are valid, otherwise false.
	 */
	public function _test_domains( &$error ) {
		$domains = $this->get_domains();

		if ( ! count( $domains ) ) {
			$error = 'Empty hostname / CNAME list.';

			return false;

		}

		foreach ( $domains as $domain ) {
			$_domains = array_map( 'trim', explode( ',', $domain ) );

			foreach ( $_domains as $_domain ) {
				$matches = null;

				if ( preg_match( '~^([a-z0-9\-\.]*)~i', $_domain, $matches ) ) {
					$hostname = $matches[1];
				} else {
					$hostname = $_domain;
				}

				if ( empty( $hostname ) ) {
					continue;
				}

				if ( gethostbyname( $hostname ) === $hostname ) {
					$error = sprintf( 'Unable to resolve hostname: %s.', $hostname );

					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Checks if a file is a CSS file.
	 *
	 * @param string $path The file path.
	 *
	 * @return bool True if the file is a CSS file, otherwise false.
	 */
	public function _is_css( $path ) {
		return preg_match( '~[a-zA-Z0-9\-_]*(\.include\.[0-9]+)?\.css$~', $path );
	}

	/**
	 * Checks if a file is a JavaScript file.
	 *
	 * @param string $path The file path.
	 *
	 * @return bool True if the file is a JavaScript file, otherwise false.
	 */
	public function _is_js( $path ) {
		return preg_match( '~([a-z0-9\-_]+(\.include\.[a-z0-9]+)\.js)$~', $path ) || preg_match( '~[\w\d\-_]+\.js~', $path );
	}

	/**
	 * Checks if a file is a JavaScript file that should be included in the body.
	 *
	 * @param string $path The file path.
	 *
	 * @return bool True if the file is a JavaScript file for the body, otherwise false.
	 */
	public function _is_js_body( $path ) {
		return preg_match( '~[a-z0-9\-_]+(\.include-body\.[a-z0-9]+)\.js$~', $path );
	}

	/**
	 * Checks if a file is a JavaScript file that should be included in the footer.
	 *
	 * @param string $path The file path.
	 *
	 * @return bool True if the file is a JavaScript file for the footer, otherwise false.
	 */
	public function _is_js_footer( $path ) {
		return preg_match( '~[a-z0-9\-_]+(\.include-footer\.[a-z0-9]+)\.js$~', $path );
	}

	/**
	 * Retrieves the domain for a specific file path from a list of domains.
	 *
	 * @param array  $domains The list of domains.
	 * @param string $path    The file path.
	 *
	 * @return string|false The selected domain or false if no domain is found.
	 */
	public function _get_domain( $domains, $path ) {
		$count = count( $domains );
		if ( isset( $domains['http_default'] ) ) {
			--$count;
		}

		if ( isset( $domains['https_default'] ) ) {
			--$count;
		}

		if ( $count ) {
			/**
			 * Use for equal URLs same host to allow caching by browser
			 */
			$hash   = $this->_get_hash( $path );
			$domain = $domains[ $hash % $count ];

			return $domain;
		}

		return false;
	}

	/**
	 * Generates a hash from a given key.
	 *
	 * @param string $key The key to hash.
	 *
	 * @return int The generated hash value.
	 */
	public function _get_hash( $key ) {
		$hash = abs( crc32( $key ) );

		return $hash;
	}

	/**
	 * Retrieves the scheme (HTTP or HTTPS) based on the configuration.
	 *
	 * @return string The scheme ('http' or 'https').
	 */
	public function _get_scheme() {
		switch ( $this->_config['ssl'] ) {
			default:
			case 'auto':
				$scheme = ( Util_Environment::is_https() ? 'https' : 'http' );
				break;

			case 'enabled':
				$scheme = 'https';
				break;

			case 'disabled':
				$scheme = 'http';
				break;

			case 'rejected':
				$scheme = 'http';
				break;
		}

		return $scheme;
	}

	/**
	 * Logs a message with local and remote file paths and an error.
	 *
	 * @param string $local_path  The local file path.
	 * @param string $remote_path The remote file path.
	 * @param string $error       The error message.
	 *
	 * @return int|false The number of bytes written to the log file, or false on failure.
	 */
	public function _log( $local_path, $remote_path, $error ) {
		$data = sprintf( "[%s] [%s => %s] %s\n", gmdate( 'r' ), $local_path, $remote_path, $error );
		$data = strtr( $data, '<>', '..' );

		$filename = Util_Debug::log_filename( 'cdn' );

		return @file_put_contents( $filename, $data, FILE_APPEND );
	}

	/**
	 * Handles errors by saving the error message.
	 *
	 * @param int    $errno   The error number.
	 * @param string $errstr  The error message.
	 *
	 * @return bool Always returns false.
	 */
	public function _error_handler( $errno, $errstr ) {
		$this->_last_error = $errstr;

		return false;
	}

	/**
	 * Retrieves the last error message.
	 *
	 * @return string The last error message.
	 */
	public function _get_last_error() {
		return $this->_last_error;
	}

	/**
	 * Sets a custom error handler.
	 *
	 * phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
	 *
	 * @return void
	 */
	public function _set_error_handler() {
		set_error_handler(
			array(
				$this,
				'_error_handler',
			)
		);
	}

	/**
	 * Restores the default error handler.
	 *
	 * @return void
	 */
	public function _restore_error_handler() {
		restore_error_handler();
	}

	/**
	 * Retrieves the header support status.
	 *
	 * @return string The header support status (W3TC_CDN_HEADER_NONE).
	 */
	public function headers_support() {
		return W3TC_CDN_HEADER_NONE;
	}
}
