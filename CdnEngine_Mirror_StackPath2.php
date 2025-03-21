<?php
/**
 * File: CdnEngine_Mirror_StackPath2.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class CdnEngine_Mirror_StackPath2
 */
class CdnEngine_Mirror_StackPath2 extends CdnEngine_Mirror {
	/**
	 * Constructs the CDN Engine for StackPath2.
	 *
	 * @param array $config Array of configuration parameters including client ID, client secret, stack ID, and more.
	 *
	 * @return void
	 */
	public function __construct( $config = array() ) {
		$config = array_merge(
			array(
				'client_id'           => '',
				'client_secret'       => '',
				'stack_id'            => '',
				'site_root_domain'    => '',
				'access_token'        => '',
				'on_new_access_token' => null,
			),
			$config
		);

		parent::__construct( $config );
	}

	/**
	 * Purges a specific list of files from the StackPath CDN.
	 *
	 * @param array $files   Array of files to be purged.
	 * @param array $results Reference to an array where the results of the purge operation will be stored.
	 *
	 * @return bool True if purge operation is successful, false otherwise.
	 *
	 * @throws \Exception If the purge API call fails.
	 */
	public function purge( $files, &$results ) {
		if ( empty( $this->_config['client_id'] ) ) {
			$results = $this->_get_results(
				$files,
				W3TC_CDN_RESULT_HALT,
				__( 'Empty Authorization Key.', 'w3-total-cache' )
			);

			return false;
		}

		$url_prefixes = $this->url_prefixes();
		$api          = new Cdn_StackPath2_Api( $this->_config );
		$results      = array();

		try {
			$items = array();
			foreach ( $files as $file ) {
				foreach ( $url_prefixes as $prefix ) {
					$items[] = array(
						'url'       => $prefix . '/' . $file['remote_path'],
						'recursive' => true,
					);
				}
			}
			$api->purge( array( 'items' => $items ) );

			$results[] = $this->_get_result(
				'',
				'',
				W3TC_CDN_RESULT_OK,
				'OK'
			);
		} catch ( \Exception $e ) {
			$results[] = $this->_get_result(
				'',
				'',
				W3TC_CDN_RESULT_HALT,
				__( 'Failure to pull zone: ', 'w3-total-cache' ) . $e->getMessage()
			);
		}

		return ! $this->_is_error( $results );
	}

	/**
	 * Purges all files from the StackPath CDN.
	 *
	 * @param array $results Reference to an array where the results of the purge operation will be stored.
	 *
	 * @return bool True if purge all operation is successful, false otherwise.
	 *
	 * @throws \Exception If the purge all API call fails.
	 */
	public function purge_all( &$results ) {
		if ( empty( $this->_config['client_id'] ) ) {
			$results = $this->_get_results( $files, W3TC_CDN_RESULT_HALT, __( 'Empty Authorization Key.', 'w3-total-cache' ) );

			return false;
		}

		$url_prefixes = $this->url_prefixes();
		$api          = new Cdn_StackPath2_Api( $this->_config );
		$results      = array();

		try {
			$items = array();
			foreach ( $url_prefixes as $prefix ) {
				$items[] = array(
					'url'       => $prefix . '/',
					'recursive' => true,
				);
			}

			$r = $api->purge( array( 'items' => $items ) );
		} catch ( \Exception $e ) {
			$results[] = $this->_get_result(
				'',
				'',
				W3TC_CDN_RESULT_HALT,
				__( 'Failure to pull zone: ', 'w3-total-cache' ) . $e->getMessage()
			);
		}

		return ! $this->_is_error( $results );
	}

	/**
	 * Retrieves URL prefixes for the StackPath CDN based on SSL configuration.
	 *
	 * @return array Array of URL prefixes.
	 */
	private function url_prefixes() {
		$url_prefixes = array();

		if ( 'auto' === $this->_config['ssl'] || 'enabled' === $this->_config['ssl'] ) {
			$url_prefixes[] = 'https://' . $this->_config['site_root_domain'];
		}

		if ( 'auto' === $this->_config['ssl'] || 'enabled' !== $this->_config['ssl'] ) {
			$url_prefixes[] = 'http://' . $this->_config['site_root_domain'];
		}

		return $url_prefixes;
	}
}
