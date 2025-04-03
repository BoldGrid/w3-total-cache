<?php
/**
 * File: CdnEngine_Mirror_Highwinds.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class CdnEngine_Mirror_Highwinds
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */
class CdnEngine_Mirror_Highwinds extends CdnEngine_Mirror {
	/**
	 * CDN Highwinds API object
	 *
	 * @var Cdn_Highwinds_Api
	 */
	private $api;

	/**
	 * Domains
	 *
	 * @var array
	 */
	private $domains;

	/**
	 * Host hash code
	 *
	 * @var string
	 */
	private $host_hash_code;

	/**
	 * Constructor for the CDN engine using Highwinds.
	 *
	 * @param array $config Configuration array containing 'account_hash', 'api_token', and optional parameters like 'domains' and 'host_hash_code'.
	 *
	 * @return void
	 */
	public function __construct( $config = array() ) {
		$this->api            = new Cdn_Highwinds_Api( $config['account_hash'], $config['api_token'] );
		$this->host_hash_code = $config['host_hash_code'];

		if ( ! empty( $config['domains'] ) ) {
			$this->domains = (array) $config['domains'];
		} else {
			$this->domains = array( 'cds.' . $config['host_hash_code'] . '.hwcdn.net' );
		}

		parent::__construct( $config );
	}

	/**
	 * Purges specific files from the Highwinds CDN.
	 *
	 * @param array $files   Array of file descriptors to purge, each containing 'remote_path'.
	 * @param array $results Reference array to store the purge results.
	 *
	 * @return bool True if purge is successful, false otherwise.
	 */
	public function purge( $files, &$results ) {
		$results = array();
		try {
			$urls = array();
			foreach ( $files as $file ) {
				$urls[] = $this->_format_url( $file['remote_path'] );
			}

			$this->api->purge( $urls, false );

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
	 * Purges all files from the Highwinds CDN.
	 *
	 * @param array $results Reference array to store the purge results.
	 *
	 * @return bool True if purge is successful, false otherwise.
	 */
	public function purge_all( &$results ) {
		$results = array();
		try {
			$urls = array();
			foreach ( $this->domains as $domain ) {
				$urls[] = 'http://' . $domain . '/';
				$urls[] = 'https://' . $domain . '/';
			}

			$this->api->purge( $urls, true );

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
	 * Retrieves the list of domains associated with the Highwinds CDN.
	 *
	 * @return array List of domain strings.
	 */
	public function get_domains() {
		return $this->domains;
	}

	/**
	 * Fetches analytics transfer data for the Highwinds service.
	 *
	 * @return array Parsed analytics transfer data.
	 *
	 * @throws \Exception If the response cannot be parsed or lacks required metrics.
	 */
	public function service_analytics_transfer() {
		$start_date = gmdate( 'Y-m-d', strtotime( '-30 days', time() ) ) . 'T00:00:00Z';
		$end_date   = gmdate( 'Y-m-d' ) . 'T00:00:00Z';

		$response = $this->api->analytics_transfer(
			$this->host_hash_code,
			'P1D',
			'CDS',
			$start_date,
			$end_date
		);
		if ( ! isset( $response['series'] ) || ! is_array( $response['series'] ) || count( $response['series'] ) < 1 ) {
			throw new \Exception( \esc_html__( 'Cant parse response.', 'w3-total-cache' ) );
		}

		$series = $response['series'][0];
		if ( ! isset( $series['metrics'] ) || ! is_array( $series['metrics'] ) ) {
			throw new \Exception( \esc_html__( 'Cant parse response - no metrics.', 'w3-total-cache' ) );
		}

		$metrics = $series['metrics'];
		if ( ! isset( $series['metrics'] ) || ! is_array( $series['data'] ) ) {
			throw new \Exception( \esc_html__( 'Cant parse response - no metrics.', 'w3-total-cache' ) );
		}

		$output = array();
		foreach ( $series['data'] as $data ) {
			$item  = array();
			$count = count( $metrics );
			for ( $m = 0; $m < $count; $m++ ) {
				$item[ $metrics[ $m ] ] = $data[ $m ];
			}

			$output[] = $item;
		}

		return $output;
	}

	/**
	 * Retrieves the list of configured CNAMEs from the Highwinds CDN service.
	 *
	 * @return array List of configured CNAME domain strings.
	 */
	public function service_cnames_get() {
		$scope_id      = $this->_get_scope_id();
		$configuration = $this->api->configure_scope_get( $this->host_hash_code, $scope_id );

		$domains = array();

		if ( isset( $configuration['hostname'] ) ) {
			foreach ( $configuration['hostname'] as $d ) {
				$domains[] = $d['domain'];
			}
		}

		return $domains;
	}

	/**
	 * Updates the configured CNAMEs for the Highwinds CDN service.
	 *
	 * @param array $domains List of domain strings to configure as CNAMEs.
	 *
	 * @return void
	 */
	public function service_cnames_set( $domains ) {
		$scope_id      = $this->_get_scope_id();
		$configuration = $this->api->configure_scope_get( $this->host_hash_code, $scope_id );

		$hostname = array();
		foreach ( $domains as $d ) {
			$hostname[] = array( 'domain' => $d );
		}

		$configuration['hostname'] = $hostname;
		$this->api->configure_scope_set( $this->host_hash_code, $scope_id, $configuration );
	}

	/**
	 * Retrieves the scope ID for the Highwinds CDN configuration.
	 *
	 * @return int The scope ID for the CDN configuration.
	 *
	 * @throws \Exception If the required scope for the CDN has not been created.
	 */
	private function _get_scope_id() {
		$scopes_response = $this->api->configure_scopes( $this->host_hash_code );
		$scope_id        = 0;

		foreach ( $scopes_response['list'] as $scope ) {
			if ( 'CDS' === $scope['platform'] ) {
				return $scope['id'];
			}
		}

		throw new Exception( \esc_html__( 'Scope CDN hasnt been created.', 'w3-total-cache' ) );
	}
}
