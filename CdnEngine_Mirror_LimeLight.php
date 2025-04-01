<?php
/**
 * File: CdnEngine_Mirror_Highwinds.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class CdnEngine_Mirror_LimeLight
 */
class CdnEngine_Mirror_LimeLight extends CdnEngine_Mirror {
	/**
	 * Short name
	 *
	 * @var string
	 */
	private $short_name;

	/**
	 * Username
	 *
	 * @var string
	 */
	private $username;

	/**
	 * API key
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Debug flag
	 *
	 * @var bool
	 */
	private $debug;

	/**
	 * Domains
	 *
	 * @var array
	 */
	private $domains;

	/**
	 * Initializes the CdnEngine_Mirror_LimeLight class with configuration parameters.
	 *
	 * @param array $config Configuration parameters including 'short_name', 'username', 'api_key', 'debug', and 'domains'.
	 *
	 * @return void
	 */
	public function __construct( $config = array() ) {
		$this->short_name = $config['short_name'];
		$this->username   = $config['username'];
		$this->api_key    = $config['api_key'];
		$this->debug      = $config['debug'];

		$this->domains = (array) $config['domains'];

		parent::__construct( $config );
	}

	/**
	 * Purges specific files from the LimeLight CDN cache.
	 *
	 * @param array $files   Array of file descriptors to purge from the CDN.
	 * @param array $results Reference to an array where the purge results will be stored.
	 *
	 * @return bool True if the purge was successful, false otherwise.
	 *
	 * @throws \Exception If credentials are not specified.
	 */
	public function purge( $files, &$results ) {
		if ( empty( $this->short_name ) || empty( $this->username ) || empty( $this->api_key ) ) {
			throw new \Exception( \esc_html__( 'Credentials are not specified.', 'w3-total-cache' ) );
		}

		$api = new Cdnfsd_LimeLight_Api( $this->short_name, $this->username, $this->api_key );

		$results = array();
		try {
			$items = array();
			foreach ( $files as $file ) {
				$url     = $this->_format_url( $file['remote_path'] );
				$items[] = array(
					'pattern' => $url,
					'exact'   => true,
					'evict'   => false,
					'incqs'   => false,
				);

				// max number of items per request based on API docs.
				if ( count( $items ) >= 100 ) {
					if ( $this->debug ) {
						Util_Debug::log( 'cdn', wp_json_encode( $items, JSON_PRETTY_PRINT ) );
					}

					$api->purge( $items );
					$items = array();
				}
			}

			if ( $this->debug ) {
				Util_Debug::log( 'cdn', wp_json_encode( $items, JSON_PRETTY_PRINT ) );
			}

			$api->purge( $items );

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
				\__( 'Failed to purge: ', 'w3-total-cache' ) . $e->getMessage()
			);
		}

		return ! $this->_is_error( $results );
	}

	/**
	 * Purges all content from the LimeLight CDN cache for all configured domains.
	 *
	 * @param array $results Reference to an array where the purge results will be stored.
	 *
	 * @return bool True if the purge was successful, false otherwise.
	 *
	 * @throws \Exception If access key is not specified.
	 */
	public function purge_all( &$results ) {
		if ( empty( $this->short_name ) || empty( $this->username ) || empty( $this->api_key ) ) {
			throw new \Exception( \esc_html__( 'Access key not specified.', 'w3-total-cache' ) );
		}

		$api = new Cdnfsd_LimeLight_Api( $this->short_name, $this->username, $this->api_key );

		$results = array();
		try {
			$items = array();
			foreach ( $this->domains as $domain ) {
				$items[] = array(
					'pattern' => 'http://' . $domain . '/*',
					'exact'   => false,
					'evict'   => false,
					'incqs'   => false,
				);
				$items[] = array(
					'pattern' => 'https://' . $domain . '/*',
					'exact'   => false,
					'evict'   => false,
					'incqs'   => false,
				);
			}

			if ( $this->debug ) {
				Util_Debug::log( 'cdn', wp_json_encode( $items, JSON_PRETTY_PRINT ) );
			}

			$api->purge( $items );

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
				\__( 'Failed to purge all: ', 'w3-total-cache' ) . $e->getMessage()
			);
		}

		return ! $this->_is_error( $results );
	}

	/**
	 * Retrieves the list of domains associated with this CDN engine.
	 *
	 * @return array List of domains.
	 */
	public function get_domains() {
		return $this->domains;
	}
}
