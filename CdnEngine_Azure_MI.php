<?php
/**
 * File: CdnEngine_Azure.php
 *
 * Microsoft Azure Managed Identities are available only for services running on Azure when a "system assigned" identity is enabled.
 *
 * A system assigned managed identity is restricted to one per resource and is tied to the lifecycle of a resource.
 * You can grant permissions to the managed identity by using Azure role-based access control (Azure RBAC).
 * The managed identity is authenticated with Microsoft Entra ID, so you don’t have to store any credentials in code.
 *
 * @package W3TC
 * @since   2.7.7
 */

namespace W3TC;

/**
 * Class: CdnEngine_Azure_MI
 *
 * Windows Azure Storage CDN engine.
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class CdnEngine_Azure_MI extends CdnEngine_Base {

	/**
	 * Constructor.
	 *
	 * @since 2.7.7
	 *
	 * @param array $config Configuration.
	 */
	public function __construct( $config = array() ) {
		$config = array_merge(
			array(
				'user'      => (string) getenv( 'STORAGE_ACCOUNT_NAME' ),
				'client_id' => (string) getenv( 'ENTRA_CLIENT_ID' ),
				'container' => (string) getenv( 'BLOB_CONTAINER_NAME' ),
				'cname'     => empty( getenv( 'BLOB_STORAGE_URL' ) ) ? array() : array( (string) getenv( 'BLOB_STORAGE_URL' ) ),
			),
			$config
		);

		parent::__construct( $config );

		// Load the Composer autoloader.
		require_once W3TC_DIR . '/vendor/autoload.php';
	}

	/**
	 * Initialize storage client object.
	 *
	 * @since 2.7.7
	 *
	 * @param string $error Error message.
	 * @return bool
	 */
	public function _init( &$error ) {
		if ( empty( $this->_config['user'] ) ) {
			$error = 'Empty account name.';
			return false;
		}

		if ( empty( $this->_config['client_id'] ) ) {
			$error = 'Empty Entra client ID.';
			return false;
		}

		if ( empty( $this->_config['container'] ) ) {
			$error = 'Empty container name.';

			return false;
		}

		return true;
	}

	/**
	 * Upload files to Azure Blob Storage.
	 *
	 * @since 2.7.7
	 *
	 * @param array    $files         Files.
	 * @param array    $results       Results.
	 * @param bool     $force_rewrite Force rewrite.
	 * @param int|null $timeout_time Timeout time.
	 * @return bool
	 */
	public function upload( $files, &$results, $force_rewrite = false, $timeout_time = null ) {
		$error = null;

		if ( ! $this->_init( $error ) ) {
			$results = $this->_get_results( $files, W3TC_CDN_RESULT_HALT, $error );

			return false;
		}

		foreach ( $files as $file ) {
			// Process at least one item before timeout so that progress goes on.
			if ( ! empty( $results ) ) {
				if ( ! is_null( $timeout_time ) && time() > $timeout_time ) {
					// Timeout.
					return false;
				}
			}

			$results[] = $this->_upload( $file, $force_rewrite );
		}

		return ! $this->_is_error( $results );
	}

	/**
	 * Upload file to Azure Blob Storage.
	 *
	 * @since 2.7.7
	 *
	 * @param string $file File path.
	 * @param bool   $force_rewrite Force rewrite.
	 * @return array
	 */
	public function _upload( $file, $force_rewrite = false ) {
		$local_path  = $file['local_path'];
		$remote_path = $file['remote_path'];

		if ( ! file_exists( $local_path ) ) {
			return $this->_get_result( $local_path, $remote_path, W3TC_CDN_RESULT_ERROR, 'Source file not found.', $file );
		}

		$contents    = @file_get_contents( $local_path );
		$md5         = md5( $contents );
		$content_md5 = $this->_get_content_md5( $md5 );

		if ( ! $force_rewrite ) {
			try {
				$p = CdnEngine_Azure_MI_Utility::get_blob_properties(
					$this->_config['client_id'],
					$this->_config['user'],
					$this->_config['container'],
					$remote_path
				);

				$local_size = @filesize( $local_path );

				// Check if Content-Length is available in $p array.
				if ( isset( $p['Content-Length'] ) && (int) $local_size === (int) $p['Content-Length'] && isset( $p['Content-MD5'] ) && $content_md5 === $p['Content-MD5'] ) {
					return $this->_get_result( $local_path, $remote_path, W3TC_CDN_RESULT_OK, 'File up-to-date.', $file );
				}
			} catch ( \Exception $exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			}
		}

		$headers = $this->get_headers_for_file( $file );

		try {
			$content_type  = isset( $headers['Content-Type'] ) ? $headers['Content-Type'] : 'application/octet-stream';
			$cache_control = isset( $headers['Cache-Control'] ) ? $headers['Cache-Control'] : '';

			CdnEngine_Azure_MI_Utility::create_block_blob(
				$this->_config['client_id'],
				$this->_config['user'],
				$this->_config['container'],
				$remote_path,
				$contents,
				$content_type,
				$content_md5,
				$cache_control
			);

		} catch ( \Exception $exception ) {
			return $this->_get_result(
				$local_path,
				$remote_path,
				W3TC_CDN_RESULT_ERROR,
				sprintf( 'Unable to put blob (%1$s).', $exception->getMessage() ),
				$file
			);
		}

		return $this->_get_result( $local_path, $remote_path, W3TC_CDN_RESULT_OK, 'OK', $file );
	}

	/**
	 * Delete files from Azure Blob Storage.
	 *
	 * @since 2.7.7
	 *
	 * @param array $files   Files.
	 * @param array $results Results.
	 * @return bool
	 */
	public function delete( $files, &$results ) {
		$error = null;

		if ( ! $this->_init( $error ) ) {
			$results = $this->_get_results( $files, W3TC_CDN_RESULT_HALT, $error );

			return false;
		}

		foreach ( $files as $file ) {
			$local_path  = $file['local_path'];
			$remote_path = $file['remote_path'];

			try {
				CdnEngine_Azure_MI_Utility::delete_blob(
					$this->_config['client_id'],
					$this->_config['user'],
					$this->_config['container'],
					$remote_path
				);

				$results[] = $this->_get_result( $local_path, $remote_path, W3TC_CDN_RESULT_OK, 'OK', $file );
			} catch ( \Exception $exception ) {
				$results[] = $this->_get_result(
					$local_path,
					$remote_path,
					W3TC_CDN_RESULT_ERROR,
					sprintf( 'Unable to delete blob (%1$s).', $exception->getMessage() ),
					$file
				);
			}
		}

		return ! $this->_is_error( $results );
	}

	/**
	 * Test Azure Blob Storage.
	 *
	 * @since 2.7.7
	 *
	 * @param string $error Error message.
	 * @return bool
	 */
	public function test( &$error ) {
		if ( ! parent::test( $error ) ) {
			return false;
		}

		$string = 'test_azure_' . md5( time() );

		if ( ! $this->_init( $error ) ) {
			return false;
		}

		try {
			$containers = CdnEngine_Azure_MI_Utility::list_containers(
				$this->_config['client_id'],
				$this->_config['user']
			);
		} catch ( \Exception $exception ) {
			$error = sprintf( 'Unable to list containers (%1$s).', $exception->getMessage() );
			return false;
		}

		$container = null;

		foreach ( $containers as $_container ) {
			if ( $_container['Name'] === $this->_config['container'] ) {
				$container = $_container;
				break;
			}
		}

		if ( ! $container ) {
			$error = sprintf( 'Container doesn\'t exist: %1$s.', $this->_config['container'] );
			return false;
		}

		try {
			CdnEngine_Azure_MI_Utility::create_block_blob(
				$this->_config['client_id'],
				$this->_config['user'],
				$this->_config['container'],
				$string,
				$string
			);

		} catch ( \Exception $exception ) {
			$error = sprintf( 'Unable to create blob (%1$s).', $exception->getMessage() );
			return false;
		}

		try {
			$p = CdnEngine_Azure_MI_Utility::get_blob_properties(
				$this->_config['client_id'],
				$this->_config['user'],
				$this->_config['container'],
				$string
			);

			$size = isset( $p['Content-Length'] ) ? (int) $p['Content-Length'] : -1;
			$md5  = isset( $p['Content-MD5'] ) ? $p['Content-MD5'] : '';
		} catch ( \Exception $exception ) {
			$error = sprintf( 'Unable to get blob properties (%1$s).', $exception->getMessage() );
			return false;
		}

		if ( strlen( $string ) !== $size || $this->_get_content_md5( md5( $string ) ) !== $md5 ) {
			try {
				CdnEngine_Azure_MI_Utility::delete_blob(
					$this->_config['client_id'],
					$this->_config['user'],
					$this->_config['container'],
					$string
				);

			} catch ( \Exception $exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			}

			$error = 'Blob data properties are not equal.';
			return false;
		}

		try {
			$blob_response = CdnEngine_Azure_MI_Utility::get_blob(
				$this->_config['client_id'],
				$this->_config['user'],
				$this->_config['container'],
				$string
			);

			$data = isset( $blob_response['data'] ) ? $blob_response['data'] : '';
		} catch ( \Exception $exception ) {
			$error = sprintf( 'Unable to get blob data (%1$s).', $exception->getMessage() );
			return false;
		}

		if ( $data !== $string ) {
			try {
				CdnEngine_Azure_MI_Utility::delete_blob(
					$this->_config['client_id'],
					$this->_config['user'],
					$this->_config['container'],
					$string
				);
			} catch ( \Exception $exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			}

			$error = 'Blob datas are not equal.';
			return false;
		}

		try {
			CdnEngine_Azure_MI_Utility::delete_blob(
				$this->_config['client_id'],
				$this->_config['user'],
				$this->_config['container'],
				$string
			);
		} catch ( \Exception $exception ) {
			$error = sprintf( 'Unable to delete blob (%s).', $exception->getMessage() );

			return false;
		}

		return true;
	}

	/**
	 * Returns CDN domains.
	 *
	 * @since 2.7.7
	 *
	 * @return array
	 */
	public function get_domains() {
		if ( ! empty( $this->_config['cname'] ) ) {
			return (array) $this->_config['cname'];
		} elseif ( ! empty( $this->_config['user'] ) ) {
			$domain = sprintf( '%1$s.blob.core.windows.net', $this->_config['user'] );
			return array( $domain );
		}

		return array();
	}

	/**
	 * Returns via string.
	 *
	 * @since 2.7.7
	 *
	 * @return string
	 */
	public function get_via() {
		return sprintf( 'Windows Azure Storage: %1$s', parent::get_via() );
	}

	/**
	 * Create an Azure Blob Storage container/bucket.
	 *
	 * @since 2.7.7
	 *
	 * @return bool
	 * @throws \Exception Exception.
	 */
	public function create_container() {
		if ( ! $this->_init( $error ) ) {
			throw new \Exception( esc_html( $error ) );
		}

		try {
			$containers = CdnEngine_Azure_MI_Utility::list_containers(
				$this->_config['client_id'],
				$this->_config['user']
			);
		} catch ( \Exception $exception ) {
			$error = sprintf( 'Unable to list containers (%1$s).', $exception->getMessage() );
			throw new \Exception( esc_html( $error ) );
		}

		foreach ( $containers as $_container ) {
			if ( $_container['Name'] === $this->_config['container'] ) {
				$error = sprintf( 'Container already exists: %1$s.', $this->_config['container'] );
				throw new \Exception( esc_html( $error ) );
			}
		}

		try {
			$result = CdnEngine_Azure_MI_Utility::create_container(
				$this->_config['client_id'],
				$this->_config['user'],
				$this->_config['container']
			);

			return true; // Maybe return container ID.
		} catch ( \Exception $exception ) {
			$error = sprintf( 'Unable to create container: %1$s (%2$s)', $this->_config['container'], $exception->getMessage() );
			throw new \Exception( esc_html( $error ) );
		}
	}

	/**
	 * Return Content-MD5 header value.
	 *
	 * @since 2.7.7
	 *
	 * @param string $md5 MD5 hash.
	 * @return string Base64-encoded packed (hex string, high nibble first, repeating to the end of the input data) data from the input MD% string.
	 */
	public function _get_content_md5( $md5 ) {
		return base64_encode( pack( 'H*', $md5 ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Format object URL.
	 *
	 * @since 2.7.7
	 *
	 * @param string $path Path.
	 * @return string|false
	 */
	public function _format_url( $path ) {
		$domain = $this->get_domain( $path );

		if ( $domain && ! empty( $this->_config['container'] ) ) {
			$scheme = $this->_get_scheme();
			$url    = sprintf( '%1$s://%2$s/%3$s/%4$s', $scheme, $domain, $this->_config['container'], $path );

			return $url;
		}

		return false;
	}

	/**
	 * How and if headers should be set.
	 *
	 * @since 2.7.7
	 *
	 * @return string W3TC_CDN_HEADER_NONE, W3TC_CDN_HEADER_UPLOADABLE, or W3TC_CDN_HEADER_MIRRORING.
	 */
	public function headers_support() {
		return W3TC_CDN_HEADER_UPLOADABLE;
	}

	/**
	 * Get prepend path.
	 *
	 * @since 2.7.7
	 *
	 * @param string $path Path.
	 * @return string
	 */
	public function get_prepend_path( $path ) {
		$path = parent::get_prepend_path( $path );
		$path = $this->_config['container'] ? trim( $path, '/' ) . '/' . trim( $this->_config['container'], '/' ) : $path;
		return $path;
	}
}
