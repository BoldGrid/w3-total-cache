<?php
/**
 * File: CdnEngine_S3_Compatible.php
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! class_exists( 'S3Compatible' ) ) {
	require_once W3TC_LIB_DIR . '/S3Compatible.php';
}

/**
 * Class CdnEngine_S3_Compatible
 *
 * Amazon S3 CDN engine
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class CdnEngine_S3_Compatible extends CdnEngine_Base {
	/**
	 * S3Compatible object
	 *
	 * @var S3Compatible
	 */
	private $_s3 = null;

	/**
	 * Constructs the S3-compatible CDN engine instance.
	 *
	 * @param array $config Configuration options for S3-compatible storage.
	 *
	 * @return void
	 */
	public function __construct( $config = array() ) {
		$config = array_merge(
			array(
				'key'    => '',
				'secret' => '',
				'bucket' => '',
				'cname'  => array(),
			),
			$config
		);

		$this->_s3 = new \S3Compatible( $config['key'], $config['secret'], false, $config['api_host'] );
		$this->_s3->setSignatureVersion( 'v2' );

		parent::__construct( $config );
	}

	/**
	 * Formats the URL for a given file path.
	 *
	 * @param string $path The file path to format into a URL.
	 *
	 * @return string|false The formatted URL or false if the domain is unavailable.
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
	 * Uploads files to the S3-compatible storage.
	 *
	 * @param array    $files         Array of file descriptors for upload.
	 * @param array    $results       Reference to an array where upload results will be stored.
	 * @param bool     $force_rewrite Whether to force overwriting existing files.
	 * @param int|null $timeout_time  Optional timeout time in seconds.
	 *
	 * @return bool True if upload was successful, false otherwise.
	 */
	public function upload( $files, &$results, $force_rewrite = false, $timeout_time = null ) {

		$error = null;

		foreach ( $files as $file ) {
			$local_path  = $file['local_path'];
			$remote_path = $file['remote_path'];

			// process at least one item before timeout so that progress goes on.
			if ( ! empty( $results ) && ! is_null( $timeout_time ) && time() > $timeout_time ) {
				return 'timeout';
			}

			$results[] = $this->_upload( $file, $force_rewrite );

			if ( $this->_config['compression'] && $this->_may_gzip( $remote_path ) ) {
				$file['remote_path_gzip'] = $remote_path . $this->_gzip_extension;
				$results[]                = $this->_upload_gzip( $file, $force_rewrite );
			}
		}

		return ! $this->_is_error( $results );
	}

	/**
	 * Uploads a single file to the S3-compatible storage.
	 *
	 * @param array $file          File descriptor for upload.
	 * @param bool  $force_rewrite Whether to force overwriting the file.
	 *
	 * @return array The result of the upload operation.
	 */
	public function _upload( $file, $force_rewrite = false ) {
		$local_path  = $file['local_path'];
		$remote_path = $file['remote_path'];

		if ( ! file_exists( $local_path ) ) {
			return $this->_get_result(
				$local_path,
				$remote_path,
				W3TC_CDN_RESULT_ERROR,
				'Source file not found.',
				$file
			);
		}

		if ( ! $force_rewrite ) {
			$this->_set_error_handler();
			$info = @$this->_s3->getObjectInfo( $this->_config['bucket'], $remote_path );
			$this->_restore_error_handler();

			if ( $info ) {
				$hash    = @md5_file( $local_path );
				$s3_hash = ( isset( $info['hash'] ) ? $info['hash'] : '' );

				if ( $hash === $s3_hash ) {
					return $this->_get_result(
						$local_path,
						$remote_path,
						W3TC_CDN_RESULT_OK,
						'Object up-to-date.',
						$file
					);
				}
			}
		}

		$headers = $this->get_headers_for_file( $file, array( 'ETag' => '*' ) );

		$this->_set_error_handler();
		$result = @$this->_s3->putObjectFile(
			$local_path,
			$this->_config['bucket'],
			$remote_path,
			\S3Compatible::ACL_PUBLIC_READ,
			array(),
			$headers
		);
		$this->_restore_error_handler();

		if ( $result ) {
			return $this->_get_result(
				$local_path,
				$remote_path,
				W3TC_CDN_RESULT_OK,
				'OK',
				$file
			);
		}

		return $this->_get_result(
			$local_path,
			$remote_path,
			W3TC_CDN_RESULT_ERROR,
			sprintf( 'Unable to put object (%s).', $this->_get_last_error() ),
			$file
		);
	}

	/**
	 * Uploads a gzipped version of a file to the S3-compatible storage.
	 *
	 * @param array $file          File descriptor for upload.
	 * @param bool  $force_rewrite Whether to force overwriting the file.
	 *
	 * @return array The result of the upload operation.
	 */
	public function _upload_gzip( $file, $force_rewrite = false ) {
		$local_path  = $file['local_path'];
		$remote_path = $file['remote_path_gzip'];

		if ( ! function_exists( 'gzencode' ) ) {
			return $this->_get_result(
				$local_path,
				$remote_path,
				W3TC_CDN_RESULT_ERROR,
				"GZIP library doesn't exist.",
				$file
			);
		}

		if ( ! file_exists( $local_path ) ) {
			return $this->_get_result(
				$local_path,
				$remote_path,
				W3TC_CDN_RESULT_ERROR,
				'Source file not found.',
				$file
			);
		}

		$contents = @file_get_contents( $local_path );
		if ( false === $contents ) {
			return $this->_get_result(
				$local_path,
				$remote_path,
				W3TC_CDN_RESULT_ERROR,
				'Unable to read file.',
				$file
			);
		}

		$data = gzencode( $contents );

		if ( ! $force_rewrite ) {
			$this->_set_error_handler();
			$info = @$this->_s3->getObjectInfo( $this->_config['bucket'], $remote_path );
			$this->_restore_error_handler();

			if ( $info ) {
				$hash    = md5( $data );
				$s3_hash = ( isset( $info['hash'] ) ? $info['hash'] : '' );

				if ( $hash === $s3_hash ) {
					return $this->_get_result(
						$local_path,
						$remote_path,
						W3TC_CDN_RESULT_OK,
						'Object up-to-date.',
						$file
					);
				}
			}
		}

		$headers = $this->get_headers_for_file( $file, array( 'ETag' => '*' ) );
		$headers = array_merge(
			$headers,
			array(
				'Vary'             => 'Accept-Encoding',
				'Content-Encoding' => 'gzip',
			)
		);

		$this->_set_error_handler();
		$result = @$this->_s3->putObjectString(
			$data,
			$this->_config['bucket'],
			$remote_path,
			\S3Compatible::ACL_PUBLIC_READ,
			array(),
			$headers
		);
		$this->_restore_error_handler();

		if ( $result ) {
			return $this->_get_result(
				$local_path,
				$remote_path,
				W3TC_CDN_RESULT_OK,
				'OK',
				$file
			);
		}

		return $this->_get_result(
			$local_path,
			$remote_path,
			W3TC_CDN_RESULT_ERROR,
			sprintf( 'Unable to put object (%s).', $this->_get_last_error() ),
			$file
		);
	}

	/**
	 * Deletes files from the S3-compatible storage.
	 *
	 * @param array $files   Array of file descriptors to delete.
	 * @param array $results Reference to an array where deletion results will be stored.
	 *
	 * @return bool True if deletion was successful, false otherwise.
	 */
	public function delete( $files, &$results ) {
		$error = null;

		foreach ( $files as $file ) {
			$local_path  = $file['local_path'];
			$remote_path = $file['remote_path'];

			$this->_set_error_handler();
			$result = @$this->_s3->deleteObject( $this->_config['bucket'], $remote_path );
			$this->_restore_error_handler();

			if ( $result ) {
				$results[] = $this->_get_result(
					$local_path,
					$remote_path,
					W3TC_CDN_RESULT_OK,
					'OK',
					$file
				);
			} else {
				$results[] = $this->_get_result(
					$local_path,
					$remote_path,
					W3TC_CDN_RESULT_ERROR,
					sprintf( 'Unable to delete object (%s).', $this->_get_last_error() ),
					$file
				);
			}

			if ( $this->_config['compression'] ) {
				$remote_path_gzip = $remote_path . $this->_gzip_extension;

				$this->_set_error_handler();
				$result = @$this->_s3->deleteObject( $this->_config['bucket'], $remote_path_gzip );
				$this->_restore_error_handler();

				if ( $result ) {
					$results[] = $this->_get_result(
						$local_path,
						$remote_path_gzip,
						W3TC_CDN_RESULT_OK,
						'OK',
						$file
					);
				} else {
					$results[] = $this->_get_result(
						$local_path,
						$remote_path_gzip,
						W3TC_CDN_RESULT_ERROR,
						sprintf( 'Unable to delete object (%s).', $this->_get_last_error() ),
						$file
					);
				}
			}
		}

		return ! $this->_is_error( $results );
	}

	/**
	 * Tests the S3-compatible storage connection.
	 *
	 * @param string $error Reference to a string where error messages will be stored.
	 *
	 * @return bool True if the connection test passes, false otherwise.
	 */
	public function test( &$error ) {
		if ( ! parent::test( $error ) ) {
			return false;
		}

		$string = 'test_s3_' . md5( time() );

		$this->_set_error_handler();

		if (
			! @$this->_s3->putObjectString(
				$string,
				$this->_config['bucket'],
				$string,
				\S3Compatible::ACL_PUBLIC_READ
			)
		) {
			$error = sprintf( 'Unable to put object (%s).', $this->_get_last_error() );

			$this->_restore_error_handler();

			return false;
		}

		$object = @$this->_s3->getObject( $this->_config['bucket'], $string );
		if ( ! $object ) {
			$error = sprintf( 'Unable to get object (%s).', $this->_get_last_error() );

			$this->_restore_error_handler();
			return false;
		}

		if ( (string) $object->body !== $string ) {
			$error = 'Objects are not equal.';

			@$this->_s3->deleteObject( $this->_config['bucket'], $string );
			$this->_restore_error_handler();

			return false;
		}

		if ( ! @$this->_s3->deleteObject( $this->_config['bucket'], $string ) ) {
			$error = sprintf( 'Unable to delete object (%s).', $this->_get_last_error() );

			$this->_restore_error_handler();

			return false;
		}

		$this->_restore_error_handler();

		return true;
	}

	/**
	 * Retrieves the configured domains for the S3-compatible storage.
	 *
	 * @return array List of domains.
	 */
	public function get_domains() {
		return (array) $this->_config['cname'];
	}

	/**
	 * Retrieves a descriptive string indicating the type of CDN in use including domain.
	 *
	 * @return string The description of the CDN including domain.
	 */
	public function get_via() {
		return sprintf( 'S3-compatible: %s', parent::get_via() );
	}

	/**
	 * Checks if the storage supports custom headers.
	 *
	 * @return int Flag indicating header support capability.
	 */
	public function headers_support() {
		return W3TC_CDN_HEADER_UPLOADABLE;
	}
}
