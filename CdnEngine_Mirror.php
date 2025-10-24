<?php
/**
 * File: CdnEngine_Mirror.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class CdnEngine_Mirror
 *
 * W3 CDN Mirror Class
 */
class CdnEngine_Mirror extends CdnEngine_Base {
	/**
	 * Constructor for the CdnEngine_Mirror class.
	 *
	 * @param array $config Optional configuration settings for the engine.
	 *
	 * @return void
	 */
	public function __construct( $config = array() ) {
		$config = array_merge(
			array(
				'domain' => array(),
			),
			$config
		);

		parent::__construct( $config );
	}

	/**
	 * Uploads files to the mirror CDN.
	 *
	 * @param array $files         Array of files to upload.
	 * @param array $results       Reference to an array for storing upload results.
	 * @param bool  $force_rewrite Whether to force overwriting existing files.
	 * @param int   $timeout_time  Optional timeout time in seconds.
	 *
	 * @return bool True on success, false otherwise.
	 */
	public function upload( $files, &$results, $force_rewrite = false, $timeout_time = null ) {
		$results = $this->_get_results( $files, W3TC_CDN_RESULT_OK, 'OK' );

		return true;
	}

	/**
	 * Deletes files from the mirror CDN.
	 *
	 * @param array $files   Array of files to delete.
	 * @param array $results Reference to an array for storing deletion results.
	 *
	 * @return bool True on success, false otherwise.
	 */
	public function delete( $files, &$results ) {
		$results = $this->_get_results( $files, W3TC_CDN_RESULT_OK, 'OK' );

		return true;
	}

	/**
	 * Tests the connectivity and functionality of the mirror CDN.
	 *
	 * @param string $error Reference to a string for storing any error message.
	 *
	 * @return bool True if the test succeeds, false otherwise.
	 */
	public function test( &$error ) {
		if ( ! parent::test( $error ) ) {
			return false;
		}

		$results = array();
		$files   = array(
			array(
				'local_path'  => '',
				'remote_path' => 'purge_test_' . time(),
			),
		);

		if ( ! $this->purge( $files, $results ) && isset( $results[0]['error'] ) ) {
			$error = $results[0]['error'];

			return false;
		}

		return true;
	}

	/**
	 * Retrieves the list of configured CDN domains.
	 *
	 * @return array List of configured domains.
	 */
	public function get_domains() {
		if ( ! empty( $this->_config['domain'] ) ) {
			return (array) $this->_config['domain'];
		}

		return array();
	}

	/**
	 * Indicates support for headers in the mirror CDN.
	 *
	 * @return int Header support constant.
	 */
	public function headers_support() {
		return W3TC_CDN_HEADER_MIRRORING;
	}
}
