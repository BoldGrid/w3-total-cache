<?php
/**
 * File: CdnEngine_Azure.php
 *
 * @package W3TC
 */

namespace W3TC;

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Common\ServiceException;

/**
 * Class: CdnEngine_Azure
 *
 * Windows Azure Storage CDN engine
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class CdnEngine_Azure extends CdnEngine_Base {
	/**
	 * Storage client object
	 *
	 * @var \MicrosoftAzure\Storage\Blob\BlobRestProxy
	 */
	private $_client = null;

	/**
	 * Constructor for initializing the CdnEngine_Azure object.
	 *
	 * @param array $config An associative array of configuration values.
	 *
	 * @return void
	 */
	public function __construct( $config = array() ) {
		$config = array_merge(
			array(
				'user'      => '',
				'key'       => '',
				'container' => '',
				'cname'     => array(),
			),
			$config
		);

		parent::__construct( $config );

		// Load the Composer autoloader.
		require_once W3TC_DIR . '/vendor/autoload.php';
	}

	/**
	 * Initialize the Azure Blob Storage client.
	 *
	 * Validates the configuration and establishes a connection to Azure Blob Storage.
	 *
	 * @param string $error A reference variable to capture error messages.
	 *
	 * @return bool Returns true if initialization is successful, false otherwise.
	 */
	public function _init( &$error ) {
		if ( empty( $this->_config['user'] ) ) {
			$error = 'Empty account name.';
			return false;
		}

		if ( empty( $this->_config['key'] ) ) {
			$error = 'Empty account key.';

			return false;
		}

		if ( empty( $this->_config['container'] ) ) {
			$error = 'Empty container name.';

			return false;
		}

		try {
			$this->_client = BlobRestProxy::createBlobService(
				'DefaultEndpointsProtocol=https;AccountName=' . $this->_config['user'] . ';AccountKey=' . $this->_config['key']
			);
		} catch ( \Exception $ex ) {
			$error = $ex->getMessage();
			return false;
		}

		return true;
	}

	/**
	 * Upload files to Azure Blob Storage.
	 *
	 * @param array    $files         An array of files to be uploaded.
	 * @param array    $results       A reference to an array where the upload results will be stored.
	 * @param bool     $force_rewrite Whether to force rewrite of existing files (default false).
	 * @param int|null $timeout_time  The time (in Unix timestamp) when the upload should timeout (optional).
	 *
	 * @return bool|string Returns true if the upload is successful, 'timeout' if a timeout occurs, or false if there is an error.
	 */
	public function upload( $files, &$results, $force_rewrite = false, $timeout_time = null ) {
		$error = null;

		if ( ! $this->_init( $error ) ) {
			$results = $this->_get_results( $files, W3TC_CDN_RESULT_HALT, $error );

			return false;
		}

		foreach ( $files as $file ) {
			$remote_path = $file['remote_path'];
			$local_path  = $file['local_path'];

			// process at least one item before timeout so that progress goes on.
			if ( ! empty( $results ) ) {
				if ( ! is_null( $timeout_time ) && time() > $timeout_time ) {
					return 'timeout';
				}
			}

			$results[] = $this->_upload( $file, $force_rewrite );
		}

		return ! $this->_is_error( $results );
	}

	/**
	 * Upload a single file to Azure Blob Storage.
	 *
	 * @param array $file          An array containing the local and remote file paths.
	 * @param bool  $force_rewrite Whether to force rewrite of existing files (default false).
	 *
	 * @return array The result of the upload operation.
	 */
	public function _upload( $file, $force_rewrite = false ) {
		$local_path  = $file['local_path'];
		$remote_path = $file['remote_path'];

		if ( ! file_exists( $local_path ) ) {
			return $this->_get_result( $local_path, $remote_path, W3TC_CDN_RESULT_ERROR, 'Source file not found.', $file );
		}

		$contents    = @file_get_contents( $local_path );
		$md5         = md5( $contents );   // @md5_file( $local_path ); phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		$content_md5 = $this->_get_content_md5( $md5 );

		if ( ! $force_rewrite ) {
			try {
				$properties_result = $this->_client->getBlobProperties( $this->_config['container'], $remote_path );
				$p                 = $properties_result->getProperties();

				$local_size = @filesize( $local_path );

				if ( $local_size === $p->getContentLength() && $content_md5 === $p->getContentMD5() ) {
					return $this->_get_result( $local_path, $remote_path, W3TC_CDN_RESULT_OK, 'File up-to-date.', $file );
				}
			} catch ( \Exception $exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			}
		}

		$headers = $this->get_headers_for_file( $file );

		try {
			// $headers
			$options = new \MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions();
			$options->setContentMD5( $content_md5 );
			if ( isset( $headers['Content-Type'] ) ) {
				$options->setContentType( $headers['Content-Type'] );
			}

			if ( isset( $headers['Cache-Control'] ) ) {
				$options->setCacheControl( $headers['Cache-Control'] );
			}

			$this->_client->createBlockBlob( $this->_config['container'], $remote_path, $contents, $options );
		} catch ( \Exception $exception ) {
			return $this->_get_result(
				$local_path,
				$remote_path,
				W3TC_CDN_RESULT_ERROR,
				sprintf( 'Unable to put blob (%s).', $exception->getMessage() ),
				$file
			);
		}

		return $this->_get_result( $local_path, $remote_path, W3TC_CDN_RESULT_OK, 'OK', $file );
	}

	/**
	 * Delete files from Azure Blob Storage.
	 *
	 * @param array $files   An array of files to be deleted.
	 * @param array $results A reference to an array where the delete results will be stored.
	 *
	 * @return bool Returns true if all deletions are successful, false otherwise.
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
				$r         = $this->_client->deleteBlob( $this->_config['container'], $remote_path );
				$results[] = $this->_get_result( $local_path, $remote_path, W3TC_CDN_RESULT_OK, 'OK', $file );
			} catch ( \Exception $exception ) {
				$results[] = $this->_get_result(
					$local_path,
					$remote_path,
					W3TC_CDN_RESULT_ERROR,
					sprintf( 'Unable to delete blob (%s).', $exception->getMessage() ),
					$file
				);
			}
		}

		return ! $this->_is_error( $results );
	}

	/**
	 * Test the connection and functionality of Azure Blob Storage.
	 *
	 * @param string $error A reference variable to capture error messages.
	 *
	 * @return bool Returns true if the test is successful, false otherwise.
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
			$containers = $this->_client->listContainers();
		} catch ( \Exception $exception ) {
			$error = sprintf( 'Unable to list containers (%s).', $exception->getMessage() );

			return false;
		}

		$container = null;

		foreach ( $containers->getContainers() as $_container ) {
			if ( $_container->getName() === $this->_config['container'] ) {
				$container = $_container;
				break;
			}
		}

		if ( ! $container ) {
			$error = sprintf( 'Container doesn\'t exist: %s.', $this->_config['container'] );

			return false;
		}

		try {
			$this->_client->createBlockBlob( $this->_config['container'], $string, $string );
		} catch ( \Exception $exception ) {
			$error = sprintf( 'Unable to create blob (%s).', $exception->getMessage() );
			return false;
		}

		try {
			$properties_result = $this->_client->getBlobProperties( $this->_config['container'], $string );
			$p                 = $properties_result->getProperties();
			$size              = $p->getContentLength();
			$md5               = $p->getContentMD5();
		} catch ( \Exception $exception ) {
			$error = sprintf( 'Unable to get blob properties (%s).', $exception->getMessage() );
			return false;
		}

		if ( strlen( $string ) !== $size || $this->_get_content_md5( md5( $string ) ) !== $md5 ) {
			try {
				$this->_client->deleteBlob( $this->_config['container'], $string );
			} catch ( \Exception $exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			}

			$error = 'Blob data properties are not equal.';
			return false;
		}

		try {
			$get_blob    = $this->_client->getBlob( $this->_config['container'], $string );
			$data_stream = $get_blob->getContentStream();
			$data        = stream_get_contents( $data_stream );
		} catch ( \Exception $exception ) {
			$error = sprintf( 'Unable to get blob data (%s).', $exception->getMessage() );
			return false;
		}

		if ( $data !== $string ) {
			try {
				$this->_client->deleteBlob( $this->_config['container'], $string );
			} catch ( \Exception $exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			}

			$error = 'Blob datas are not equal.';
			return false;
		}

		try {
			$this->_client->deleteBlob( $this->_config['container'], $string );
		} catch ( \Exception $exception ) {
			$error = sprintf( 'Unable to delete blob (%s).', $exception->getMessage() );

			return false;
		}

		return true;
	}

	/**
	 * Retrieves the domains for the Azure CDN configuration.
	 *
	 * @return array The list of domains based on the current configuration.
	 */
	public function get_domains() {
		if ( ! empty( $this->_config['cname'] ) ) {
			return (array) $this->_config['cname'];
		} elseif ( ! empty( $this->_config['user'] ) ) {
			$domain = sprintf( '%s.blob.core.windows.net', $this->_config['user'] );

			return array(
				$domain,
			);
		}

		return array();
	}

	/**
	 * Retrieves the "via" string indicating the source of the request.
	 *
	 * @return string The "via" string for the Azure CDN.
	 */
	public function get_via() {
		return sprintf( 'Windows Azure Storage: %s', parent::get_via() );
	}

	/**
	 * Creates a container in Azure Storage if it doesn't already exist.
	 *
	 * @throws \Exception If there is an error creating the container.
	 *
	 * @return void
	 */
	public function create_container() {
		if ( ! $this->_init( $error ) ) {
			throw new \Exception( esc_html( $error ) );
		}

		try {
			$containers = $this->_client->listContainers();
		} catch ( \Exception $exception ) {
			$error = sprintf( 'Unable to list containers (%s).', $exception->getMessage() );
			throw new \Exception( esc_html( $error ) );
		}

		if ( in_array( $this->_config['container'], (array) $containers, true ) ) {
			$error = sprintf( 'Container already exists: %s.', $this->_config['container'] );
			throw new \Exception( esc_html( $error ) );
		}

		try {
			$create_container_options = new \MicrosoftAzure\Storage\Blob\Models\CreateContainerOptions();
			$create_container_options->setPublicAccess( \MicrosoftAzure\Storage\Blob\Models\PublicAccessType::CONTAINER_AND_BLOBS );

			$this->_client->createContainer( $this->_config['container'], $create_container_options );
		} catch ( \Exception $exception ) {
			$error = sprintf( 'Unable to create container: %s (%s)', $this->_config['container'], $exception->getMessage() );
			throw new \Exception( esc_html( $error ) );
		}
	}

	/**
	 * Converts the provided MD5 hash to a base64-encoded string.
	 *
	 * @param string $md5 The MD5 hash to convert.
	 *
	 * @return string The base64-encoded MD5 hash.
	 */
	public function _get_content_md5( $md5 ) {
		return base64_encode( pack( 'H*', $md5 ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Formats the URL for a given path in the Azure CDN.
	 *
	 * @param string $path The path to format the URL for.
	 *
	 * @return string|false The formatted URL or false if the domain or container is missing.
	 */
	public function _format_url( $path ) {
		$domain = $this->get_domain( $path );

		if ( $domain && ! empty( $this->_config['container'] ) ) {
			$scheme = $this->_get_scheme();
			$url    = sprintf( '%s://%s/%s/%s', $scheme, $domain, $this->_config['container'], $path );

			return $url;
		}

		return false;
	}

	/**
	 * Returns the header support flag for the CDN.
	 *
	 * @return int The header support flag indicating the CDN's capabilities.
	 */
	public function headers_support() {
		return W3TC_CDN_HEADER_UPLOADABLE;
	}

	/**
	 * Returns the path to prepend for the given path, considering the container.
	 *
	 * @param string $path The path to modify.
	 *
	 * @return string The modified path with the container prepended.
	 */
	public function get_prepend_path( $path ) {
		$path = parent::get_prepend_path( $path );
		$path = $this->_config['container'] ? trim( $path, '/' ) . '/' . trim( $this->_config['container'], '/' ) : $path;
		return $path;
	}
}
