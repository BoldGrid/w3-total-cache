<?php
/**
 * File: CdnEngine_Mirror_RackSpaceCdn.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class CdnEngine_Mirror_RackSpaceCdn
 *
 * Rackspace CDN (pull) engine
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */
class CdnEngine_Mirror_RackSpaceCdn extends CdnEngine_Mirror {
	/**
	 * Access state
	 *
	 * @var array
	 */
	private $_access_state;

	/**
	 * Service ID
	 *
	 * @var string
	 */
	private $_service_id;

	/**
	 * Domains
	 *
	 * @var array
	 */
	private $_domains;

	/**
	 * CDN RackSpace API object
	 *
	 * @var Cdn_RackSpace_Api_Cdn
	 */
	private $_api;

	/**
	 * New access state callback
	 *
	 * @var callable
	 */
	private $_new_access_state_callback;

	/**
	 * Initializes the CdnEngine_Mirror_RackSpaceCdn instance with configuration parameters.
	 *
	 * @param array $w3tc_config Configuration settings for the RackSpace CDN service.
	 *
	 * @return void
	 */
	public function __construct( $w3tc_config = array() ) {
		$w3tc_config = array_merge(
			array(
				'user_name'                 => '',
				'api_key'                   => '',
				'region'                    => '',
				'service_id'                => '',
				'service_access_url'        => '',
				'service_protocol'          => 'http',
				'domains'                   => array(),
				'access_state'              => '',
				'new_access_state_callback' => '',
			),
			$w3tc_config
		);

		$this->_service_id                = $w3tc_config['service_id'];
		$this->_new_access_state_callback = $w3tc_config['new_access_state_callback'];

		// init access state.
		$this->_access_state = @json_decode( $w3tc_config['access_state'], true );
		if ( ! is_array( $this->_access_state ) ) {
			$this->_access_state = array();
		}

		$this->_access_state = array_merge(
			array(
				'access_token'             => '',
				'access_region_descriptor' => array(),
			),
			$this->_access_state
		);

		// cnames.
		if ( 'https' !== $w3tc_config['service_protocol'] && ! empty( $w3tc_config['domains'] ) ) {
			$this->_domains = (array) $w3tc_config['domains'];
		} else {
			$this->_domains = array( $w3tc_config['service_access_url'] );
		}

		// form 'ssl' parameter based on service protocol.
		if ( 'https' === $w3tc_config['service_protocol'] ) {
			$w3tc_config['ssl'] = 'enabled';
		} else {
			$w3tc_config['ssl'] = 'disabled';
		}

		parent::__construct( $w3tc_config );
		$this->_create_api( array( $this, '_on_new_access_requested_api' ) );
	}

	/**
	 * Creates the API instance for RackSpace CDN.
	 *
	 * @param callable $new_access_required_callback_api Callback for handling access renewal.
	 *
	 * @return void
	 */
	private function _create_api( $new_access_required_callback_api ) {
		$this->_api = new Cdn_RackSpace_Api_Cdn(
			array(
				'access_token'             => $this->_access_state['access_token'],
				'access_region_descriptor' => $this->_access_state['access_region_descriptor'],
				'new_access_required'      => $new_access_required_callback_api,
			)
		);
	}

	/**
	 * Handles the process of requesting new access tokens and region descriptors via the API.
	 *
	 * @return Cdn_RackSpace_Api_Cdn The API instance with updated access credentials.
	 *
	 * @throws \Exception If authentication or region retrieval fails.
	 */
	public function _on_new_access_requested_api() {
		$w3tc_r = Cdn_RackSpace_Api_Tokens::authenticate( $this->_config['user_name'], $this->_config['api_key'] );
		if ( ! isset( $w3tc_r['access_token'] ) || ! isset( $w3tc_r['services'] ) ) {
			throw new \Exception( \esc_html__( 'Authentication failed.', 'w3-total-cache' ) );
		}

		$w3tc_r['regions'] = Cdn_RackSpace_Api_Tokens::cdn_services_by_region( $w3tc_r['services'] );

		if ( ! isset( $w3tc_r['regions'][ $this->_config['region'] ] ) ) {
			throw new \Exception(
				\esc_html(
					sprintf(
						// translators: 1: Region name.
						\__( 'Region %1$s not found.', 'w3-total-cache' ),
						$this->_config['region']
					)
				)
			);
		}

		$this->_access_state['access_token']             = $w3tc_r['access_token'];
		$this->_access_state['access_region_descriptor'] = $w3tc_r['regions'][ $this->_config['region'] ];

		$this->_create_api( array( $this, '_on_new_access_requested_second_time' ) );

		if ( ! empty( $this->_new_access_state_callback ) ) {
			call_user_func( $this->_new_access_state_callback, wp_json_encode( $this->_access_state ) );
		}

		return $this->_api;
	}

	/**
	 * Handles the fallback case when the first authentication attempt fails.
	 *
	 * @return void
	 *
	 * @throws \Exception Always throws an exception indicating authentication failure.
	 */
	private function _on_new_access_requested_second_time() {
		throw new \Exception( \esc_html__( 'Authentication failed', 'w3-total-cache' ) );
	}

	/**
	 * Purges a list of files from the RackSpace CDN.
	 *
	 * @param array $files   Array of file descriptors to purge.
	 * @param array $results Reference to an array for storing purge results.
	 *
	 * @return bool True on success, false if there were errors during purging.
	 */
	public function purge( $files, &$results ) {
		$results = array();

		try {
			foreach ( $files as $w3tc_file ) {
				$w3tc_url = $this->_format_url( $w3tc_file['remote_path'] );
				$this->_api->purge( $this->_service_id, $w3tc_url );

				$results[] = $this->_get_result( '', '', W3TC_CDN_RESULT_OK, 'OK' );
			}
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
	 * Retrieves the current set of domains associated with the CDN service.
	 *
	 * @return array List of domains.
	 */
	public function get_domains() {
		return $this->_domains;
	}

	/**
	 * Retrieves the domains associated with the RackSpace service.
	 *
	 * @return array List of domains configured in the service.
	 */
	public function service_domains_get() {
		$w3tc_service = $this->_api->service_get( $this->_service_id );

		$domains = array();

		if ( isset( $w3tc_service['domains'] ) ) {
			foreach ( $w3tc_service['domains'] as $d ) {
				$domains[] = $d['domain'];
			}
		}

		return $domains;
	}

	/**
	 * Updates the domains associated with the RackSpace service.
	 *
	 * @param array $domains List of new domains to set.
	 *
	 * @return void
	 */
	public function service_domains_set( $domains ) {
		$w3tc_value = array();
		foreach ( $domains as $d ) {
			$v = array( 'domain' => $d );
			if ( 'https' === $this->_config['service_protocol'] ) {
				$v['protocol'] = 'https';
			}

			$w3tc_value[] = $v;
		}

		$this->_api->service_set(
			$this->_service_id,
			array(
				array(
					'op'    => 'replace',
					'path'  => '/domains',
					'value' => $w3tc_value,
				),
			)
		);
	}
}
