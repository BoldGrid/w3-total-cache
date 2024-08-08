<?php
/**
 * File: CdnEngine_Azure.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Windows Azure Storage CDN engine
 */
class CdnEngine_Azure_MI extends CdnEngine_Base {

	/**
	 * Constructor.
	 *
	 * @param array $config Configuration.
	 */
	public function __construct( $config = array() ) {
		$config = array_merge(
			array(
				'user' => '',
				'clientid' => getenv('ENTRA_CLIENT_ID')?: '',
				'container' => '',
				'cname' => array(),
			),
			$config
		);

		parent::__construct( $config );

		// Load the Composer autoloader.
		require_once W3TC_DIR . '/vendor/autoload.php';
	}

	/**
	 * Inits storage client object
	 *
	 * @param string $error Error message.
	 * @return bool
	 */
	public function _init( &$error ) {
		if ( empty( $this->_config['user'] ) ) {
			$error = 'Empty account name.';
			return false;
		}

		if ( empty( $this->_config['clientid'] ) ) {
			$this->_config['clientid'] = getenv('ENTRA_CLIENT_ID');
			if ( empty( $this->_config['clientid'] ) ) {
				$error = 'Empty entra client id.';
				return false;
			}
		}

		if ( empty( $this->_config['container'] ) ) {
			$error = 'Empty container name.';

			return false;
		}

		return true;
	}

	/**
	 * Uploads files to Azure Blob Storage
	 *
	 * @param array   $files
	 * @param array   $results
	 * @param boolean $force_rewrite
	 * @return boolean
	 */
	function upload( $files, &$results, $force_rewrite = false,
		$timeout_time = NULL ) {
		$error = null;

		if ( !$this->_init( $error ) ) {
			$results = $this->_get_results( $files, W3TC_CDN_RESULT_HALT, $error );

			return false;
		}

		foreach ( $files as $file ) {
			$remote_path = $file['remote_path'];
			$local_path = $file['local_path'];

			// process at least one item before timeout so that progress goes on
			if ( !empty( $results ) ) {
				if ( !is_null( $timeout_time ) && time() > $timeout_time ) {
					return 'timeout';
				}
			}

			$results[] = $this->_upload( $file, $force_rewrite );
		}

		return !$this->_is_error( $results );
	}

	/**
	 * Uploads file
	 *
	 * @param string  $local_path
	 * @param string  $remote_path
	 * @param bool    $force_rewrite
	 * @return array
	 */
	function _upload( $file, $force_rewrite = false ) {
		$local_path = $file['local_path'];
		$remote_path = $file['remote_path'];

		if ( !file_exists( $local_path ) ) {
			return $this->_get_result( $local_path, $remote_path,
				W3TC_CDN_RESULT_ERROR, 'Source file not found.', $file );
		}

		$contents = @file_get_contents( $local_path );
		$md5 = md5( $contents );   // @md5_file( $local_path );
		$content_md5 = $this->_get_content_md5( $md5 );

		if ( !$force_rewrite ) {
			try {
				$p = CdnEngine_Azure_MI_Utility::getBlobProperties( $this->_config['clientid'],
					$this->_config['user'], $this->_config['container'], $remote_path );
				$local_size = @filesize( $local_path );

				//check if Content-Length is available in $p array
				if ( isset( $p['Content-Length']) && $local_size == $p['Content-Length'] 
					&& isset( $p['Content-MD5']) && $content_md5 === $p['Content-MD5'] ) {
					return $this->_get_result( $local_path, $remote_path,
						W3TC_CDN_RESULT_OK, 'File up-to-date.', $file );
				}
			} catch ( \Exception $exception ) {
			}
		}

		$headers = $this->get_headers_for_file( $file );

		try {
			$contentType = isset($headers['Content-Type']) ? $headers['Content-Type'] : 'application/octet-stream';
			$cacheControl = isset($headers['Cache-Control']) ? $headers['Cache-Control'] : '';

			CdnEngine_Azure_MI_Utility::createBlockBlob( $this->_config['clientid'], $this->_config['user'], 
				$this->_config['container'], $remote_path, $contents, $contentType, $content_md5, $cacheControl);
			
		} catch ( \Exception $exception ) {
			return $this->_get_result( $local_path, $remote_path,
				W3TC_CDN_RESULT_ERROR,
				sprintf( 'Unable to put blob (%s).', $exception->getMessage() ),
				$file );
		}

		return $this->_get_result( $local_path, $remote_path, W3TC_CDN_RESULT_OK,
			'OK', $file );
	}

	/**
	 * Deletes files from storage
	 *
	 * @param array   $files
	 * @param array   $results
	 * @return boolean
	 */
	function delete( $files, &$results ) {
		$error = null;

		if ( !$this->_init( $error ) ) {
			$results = $this->_get_results( $files, W3TC_CDN_RESULT_HALT, $error );

			return false;
		}

		foreach ( $files as $file ) {
			$local_path = $file['local_path'];
			$remote_path = $file['remote_path'];

			try {

				CdnEngine_Azure_MI_Utility::deleteBlob( $this->_config['clientid'], $this->_config['user'], 
					$this->_config['container'], $remote_path );
				$results[] = $this->_get_result( $local_path, $remote_path,
					W3TC_CDN_RESULT_OK, 'OK', $file );
			} catch ( \Exception $exception ) {
				$results[] = $this->_get_result( $local_path, $remote_path,
					W3TC_CDN_RESULT_ERROR,
					sprintf( 'Unable to delete blob (%s).', $exception->getMessage() ),
					$file );
			}
		}

		return !$this->_is_error( $results );
	}

	/**
	 * Tests Azure Blob Storage
	 *
	 * @param string  $error
	 * @return boolean
	 */
	function test( &$error ) {
		if ( !parent::test( $error ) ) {
			return false;
		}

		$string = 'test_azure_' . md5( time() );

		if ( !$this->_init( $error ) ) {
			return false;
		}

		try {
			$containers = CdnEngine_Azure_MI_Utility::listContainers( $this->_config['clientid'],
				$this->_config['user']);
		} catch ( \Exception $exception ) {
			$error = sprintf( 'Unable to list containers (%s).', $exception->getMessage() );

			return false;
		}

		$container = null;

		foreach ( $containers as $_container ) {
			if ( $_container['Name'] == $this->_config['container'] ) {
				$container = $_container;
				break;
			}
		}

		if ( !$container ) {
			$error = sprintf( 'Container doesn\'t exist: %s.', $this->_config['container'] );

			return false;
		}

		try {
			CdnEngine_Azure_MI_Utility::createBlockBlob( $this->_config['clientid'],
				$this->_config['user'], $this->_config['container'],  $string, $string );

		} catch ( \Exception $exception ) {
			$error = sprintf( 'Unable to create blob (%s).', $exception->getMessage() );
			return false;
		}

		try {
			$p = CdnEngine_Azure_MI_Utility::getBlobProperties( $this->_config['clientid'],
				$this->_config['user'], $this->_config['container'], $string );

			$size = isset( $p['Content-Length']) ? $p['Content-Length'] : -1;
			$md5 = isset( $p['Content-MD5']) ? $p['Content-MD5'] : '';
		} catch ( \Exception $exception ) {
			$error = sprintf( 'Unable to get blob properties (%s).', $exception->getMessage() );
			return false;
		}

		if ( $size != strlen( $string ) || $this->_get_content_md5( md5( $string ) ) != $md5 ) {
			try {
				CdnEngine_Azure_MI_Utility::deleteBlob( $this->_config['clientid'],
					$this->_config['user'], $this->_config['container'], $string );

			} catch ( \Exception $exception ) {
			}

			$error = 'Blob data properties are not equal.';
			return false;
		}

		try {
			$blob_response = CdnEngine_Azure_MI_Utility::getBlob( $this->_config['clientid'],
					$this->_config['user'], $this->_config['container'], $string );

			$data = isset( $blob_response['data'] ) ? $blob_response['data'] : '';
		} catch ( \Exception $exception ) {
			$error = sprintf( 'Unable to get blob data (%s).', $exception->getMessage() );
			return false;
		}


		if ( $data != $string ) {
			try {
				CdnEngine_Azure_MI_Utility::deleteBlob( $this->_config['clientid'],
					$this->_config['user'], $this->_config['container'], $string );
			} catch ( \Exception $exception ) {
			}

			$error = 'Blob datas are not equal.';
			return false;
		}

		try {
			CdnEngine_Azure_MI_Utility::deleteBlob( $this->_config['clientid'],
					$this->_config['user'], $this->_config['container'], $string );
		} catch ( \Exception $exception ) {
			$error = sprintf( 'Unable to delete blob (%s).', $exception->getMessage() );

			return false;
		}

		return true;
	}

	/**
	 * Returns CDN domain
	 *
	 * @return array
	 */
	function get_domains() {
		if ( !empty( $this->_config['cname'] ) ) {
			return (array) $this->_config['cname'];
		} elseif ( !empty( $this->_config['user'] ) ) {
			$domain = sprintf( '%s.blob.core.windows.net', $this->_config['user'] );

			return array(
				$domain
			);
		}

		return array();
	}

	/**
	 * Returns via string
	 *
	 * @return string
	 */
	function get_via() {
		return sprintf( 'Windows Azure Storage: %s', parent::get_via() );
	}

	/**
	 * Creates bucket
	 *
	 * @param string  $error
	 * @return boolean
	 */
	function create_container() {
		if ( !$this->_init( $error ) ) {
			throw new \Exception( $error );
		}

		try {
			$containers = CdnEngine_Azure_MI_Utility::listContainers( $this->_config['clientid'],
				$this->_config['user']);

		} catch ( \Exception $exception ) {
			$error = sprintf( 'Unable to list containers (%s).', $exception->getMessage() );
			throw new \Exception( $error );
		}

		foreach ( $containers as $_container ) {
			if ( $_container['Name'] == $this->_config['container'] ) {
				$error = sprintf( 'Container already exists: %s.', $this->_config['container'] );
				throw new \Exception( $error );
			}
		}

		try {
			CdnEngine_Azure_MI_Utility::createContainer( $this->_config['clientid'],
				$this->_config['user'], $this->_config['container'] );

			} catch ( \Exception $exception ) {
			$error = sprintf( 'Unable to create container: %s (%s)', $this->_config['container'], $exception->getMessage() );
			throw new \Exception( $error );
		}
	}

	/**
	 * Returns Content-MD5 header value
	 *
	 * @param string  $string
	 * @return string
	 */
	function _get_content_md5( $md5 ) {
		return base64_encode( pack( 'H*', $md5 ) );
	}

	/**
	 * Formats object URL
	 *
	 * @param string  $path
	 * @return string
	 */
	function _format_url( $path ) {
		$domain = $this->get_domain( $path );

		if ( $domain && !empty( $this->_config['container'] ) ) {
			$scheme = $this->_get_scheme();
			$url = sprintf( '%s://%s/%s/%s', $scheme, $domain, $this->_config['container'], $path );

			return $url;
		}

		return false;
	}

	/**
	 * How and if headers should be set
	 *
	 * @return string W3TC_CDN_HEADER_NONE, W3TC_CDN_HEADER_UPLOADABLE, W3TC_CDN_HEADER_MIRRORING
	 */
	function headers_support() {
		return W3TC_CDN_HEADER_UPLOADABLE;
	}

	function get_prepend_path( $path ) {
		$path = parent::get_prepend_path( $path );
		$path = $this->_config['container'] ? trim( $path, '/' ) . '/' . trim( $this->_config['container'], '/' ): $path;
		return $path;
	}
}
