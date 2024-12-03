<?php
/**
 * File: CdnEngine_Mirror_StackPath.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class CdnEngine_Mirror_StackPath
 */
class CdnEngine_Mirror_StackPath extends CdnEngine_Mirror {
	/**
	 * Constructs the CdnEngine_Mirror_StackPath class.
	 *
	 * @param array $config Configuration settings for the StackPath CDN engine. Keys include:
	 *                      - 'authorization_key' (string): The authorization key for API access.
	 *                      - 'alias' (string): The account alias.
	 *                      - 'consumerkey' (string): The consumer key for API access.
	 *                      - 'consumersecret' (string): The consumer secret for API access.
	 *                      - 'zone_id' (int): The zone ID for the StackPath configuration.
	 *
	 * @return void
	 */
	public function __construct( $config = array() ) {
		$config     = array_merge(
			array(
				'authorization_key' => '',
				'alias'             => '',
				'consumerkey'       => '',
				'consumersecret'    => '',
				'zone_id'           => 0,
			),
			$config
		);
		$split_keys = explode( '+', $config['authorization_key'] );

		if ( 3 === count( $split_keys ) ) {
			list( $config['alias'], $config['consumerkey'], $config['consumersecret'] ) = $split_keys;
		}

		parent::__construct( $config );
	}

	/**
	 * Purges a specific set of files from the StackPath CDN.
	 *
	 * @param array $files   Array of files to be purged. Each file should have a 'remote_path' key.
	 * @param array $results Reference to an array where the purge results will be stored.
	 *
	 * @return bool True if the purge operation is successful, false otherwise.
	 *
	 * @throws \Exception If there is a failure during the API call to purge files.
	 */
	public function purge( $files, &$results ) {
		if ( empty( $this->_config['authorization_key'] ) ) {
			$results = $this->_get_results( $files, W3TC_CDN_RESULT_HALT, __( 'Empty Authorization Key.', 'w3-total-cache' ) );

			return false;
		}

		if ( empty( $this->_config['alias'] ) ||
			empty( $this->_config['consumerkey'] ) ||
			empty( $this->_config['consumersecret'] ) ) {
			$results = $this->_get_results( $files, W3TC_CDN_RESULT_HALT, __( 'Malformed Authorization Key.', 'w3-total-cache' ) );

			return false;
		}

		$api = new Cdn_StackPath_Api(
			$this->_config['alias'],
			$this->_config['consumerkey'],
			$this->_config['consumersecret']
		);

		$results = array();

		try {
			$zone_id = $this->_config['zone_id'];

			if ( 0 === $zone_id || is_null( $zone_id ) ) {
				$results[] = $this->_get_result(
					'',
					'',
					W3TC_CDN_RESULT_ERROR,
					__( 'No zone defined', 'w3-total-cache' )
				);
				return ! $this->_is_error( $results );
			}

			$files_to_pass = array();
			foreach ( $files as $file ) {
				$files_to_pass[] = '/' . $file['remote_path'];
			}

			$params = array( 'files' => $files_to_pass );
			$api->delete_site_cache( $zone_id, $params );

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
	 * Purges all cached files from the StackPath CDN zone.
	 *
	 * @param array $results Reference to an array where the purge results will be stored.
	 *
	 * @return bool True if the purge operation is successful, false otherwise.
	 *
	 * @throws \Exception If there is a failure during the API call to purge the zone.
	 */
	public function purge_all( &$results ) {
		if ( empty( $this->_config['authorization_key'] ) ) {
			$results = $this->_get_results(
				array(),
				W3TC_CDN_RESULT_HALT,
				__( 'Empty Authorization Key.', 'w3-total-cache' )
			);

			return false;
		}

		if ( empty( $this->_config['alias'] ) || empty( $this->_config['consumerkey'] ) || empty( $this->_config['consumersecret'] ) ) {
			$results = $this->_get_results(
				array(),
				W3TC_CDN_RESULT_HALT,
				__( 'Malformed Authorization Key.', 'w3-total-cache' )
			);

			return false;
		}

		$api = new Cdn_StackPath_Api( $this->_config['alias'], $this->_config['consumerkey'], $this->_config['consumersecret'] );

		$results = array();

		try {
			$zone_id = $this->_config['zone_id'];

			if ( 0 === $zone_id || is_null( $zone_id ) ) {
				$results[] = $this->_get_result(
					'',
					'',
					W3TC_CDN_RESULT_ERROR,
					__( 'No zone defined', 'w3-total-cache' )
				);
				return ! $this->_is_error( $results );
			}

			$file_purge = $api->delete_site_cache( $zone_id );
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
}
