<?php
/**
 * File: CdnEngine_Mirror_BunnyCdn.php
 *
 * @since   2.6.0
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: CdnEngine_Mirror_BunnyCdn
 *
 * @since 2.6.0
 *
 * @extends CdnEngine_Mirror
 */
class CdnEngine_Mirror_BunnyCdn extends CdnEngine_Mirror {
	/**
	 * Constructor.
	 *
	 * @param array $config {
	 *     Configuration.
	 *
	 *     @type string $account_api_key Account API key.
	 *     @type string $storage_api_key Storage API key.
	 *     @type string $stream_api_key  Steam API key.
	 *     @type int    $pull_zone_id    Pull zone id.
	 *     @type string $cdn_hostname    CDN hostname.
	 * }
	 */
	public function __construct( array $config = array() ) {
		$config = \array_merge(
			array(
				'account_api_key' => '',
				'storage_api_key' => '',
				'stream_api_key'  => '',
				'pull_zone_id'    => null,
				'domain'          => '',
			),
			$config
		);

		parent::__construct( $config );
	}

	/**
	 * Purge remote files.
	 *
	 * @since 2.6.0
	 *
	 * @param  array $files   Local and remote file paths.
	 * @param  array $results Results.
	 * @return bool
	 */
	public function purge( $files, &$results ) {
		if ( empty( $this->_config['account_api_key'] ) ) {
			$results = $this->_get_results( $files, W3TC_CDN_RESULT_HALT, \__( 'Missing account API key.', 'w3-total-cache' ) );

			return false;
		}

		if ( empty( $this->_config['cdn_hostname'] ) ) {
			$results = $this->_get_results( $files, W3TC_CDN_RESULT_HALT, \__( 'Missing CDN hostname.', 'w3-total-cache' ) );

			return false;
		}

		$url_prefixes = $this->url_prefixes();
		$api          = new Cdn_BunnyCdn_Api( $this->_config );
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

			$results[] = $this->_get_result( '', '', W3TC_CDN_RESULT_OK, 'OK' );
		} catch ( \Exception $e ) {
			$results[] = $this->_get_result( '', '', W3TC_CDN_RESULT_HALT, \__( 'Could not purge pull zone items: ', 'w3-total-cache' ) . $e->getMessage() );
		}

		return ! $this->_is_error( $results );
	}

	/**
	 * Purge CDN completely.
	 *
	 * @since 2.6.0
	 *
	 * @param  array $results Results.
	 * @return bool
	 */
	public function purge_all( &$results ) {
		if ( empty( $this->_config['account_api_key'] ) ) {
			$results = $this->_get_results( array(), W3TC_CDN_RESULT_HALT, __( 'Missing account API key.', 'w3-total-cache' ) );

			return false;
		}

		// Purge active pull zones: CDN & CDNFSD.
		$active_zone_ids = array();
		$config          = Dispatcher::config();
		$cdn_zone_id     = $config->get_integer( 'cdn.bunnycdn.pull_zone_id' );
		$cdnfsd_zone_id  = $config->get_integer( 'cdnfsd.bunnycdn.pull_zone_id' );

		if ( $config->get_boolean( 'cdn.enabled' ) && 'bunnycdn' === $config->get_string( 'cdn.engine' ) && $cdn_zone_id ) {
			$active_ids[] = $cdn_zone_id;
		}

		if ( $config->get_boolean( 'cdnfsd.enabled' ) && 'bunnycdn' === $config->get_string( 'cdnfsd.engine' ) && $cdnfsd_zone_id ) {
			$active_ids[] = $cdnfsd_zone_id;
		}

		if ( empty( $active_ids ) ) {
			$results = $this->_get_results( array(), W3TC_CDN_RESULT_HALT, __( 'Missing pull zone id.', 'w3-total-cache' ) );

			return false;
		}

		$results = array();

		foreach ( $active_ids as $id ) {
			$api = new Cdn_BunnyCdn_Api( array_merge( $this->_config, array( 'pull_zone_id' => $id ) ) );

			try {
				$api->purge_pull_zone();
				$results[] = $this->_get_result( '', '' ); // W3TC_CDN_RESULT_OK.
			} catch ( \Exception $e ) {
				$results[] = $this->_get_result( '', '', W3TC_CDN_RESULT_HALT, \__( 'Could not purge pull zone', 'w3-total-cache' ) . '; ' . $e->getMessage() );
			}
		}

		return ! $this->_is_error( $results );
	}

	/**
	 * Get URL prefixes.
	 *
	 * If set to "auto", then add URLs for both "http" and "https".
	 *
	 * @since 2.6.0
	 *
	 * @return array
	 */
	private function url_prefixes() {
		$url_prefixes = array();

		if ( 'auto' === $this->_config['ssl'] || 'enabled' === $this->_config['ssl'] ) {
			$url_prefixes[] = 'https://' . $this->_config['cdn_hostname'];
		}
		if ( 'auto' === $this->_config['ssl'] || 'enabled' !== $this->_config['ssl'] ) {
			$url_prefixes[] = 'http://' . $this->_config['cdn_hostname'];
		}

		return $url_prefixes;
	}
}
