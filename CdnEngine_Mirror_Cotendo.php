<?php
/**
 * File: CdnEngine_Mirror_Cotendo.php
 *
 * @package W3TC
 */

namespace W3TC;

define( 'W3TC_CDN_MIRROR_COTENDO_WSDL', 'https://api.cotendo.net/cws?wsdl' );
define( 'W3TC_CDN_MIRROR_COTENDO_ENDPOINT', 'http://api.cotendo.net/cws?ver=1.0' );
define( 'W3TC_CDN_MIRROR_COTENDO_NAMESPACE', 'http://api.cotendo.net/' );

/**
 * Class CdnEngine_Mirror_Cotendo
 */
class CdnEngine_Mirror_Cotendo extends CdnEngine_Mirror {
	/**
	 * Constructs a new instance of the class.
	 *
	 * @param array $config Configuration settings for the instance.
	 *
	 * @return void
	 */
	public function __construct( $config = array() ) {
		$config = array_merge(
			array(
				'username' => '',
				'password' => '',
				'zones'    => array(),
			),
			$config
		);

		parent::__construct( $config );
	}

	/**
	 * Purges specified files from the CDN.
	 *
	 * @param array $files   List of files to purge.
	 * @param array $results Reference to the array where the purge results will be stored.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function purge( $files, &$results ) {
		if ( empty( $this->_config['username'] ) ) {
			$results = $this->_get_results( $files, W3TC_CDN_RESULT_HALT, __( 'Empty username.', 'w3-total-cache' ) );

			return false;
		}

		if ( empty( $this->_config['password'] ) ) {
			$results = $this->_get_results( $files, W3TC_CDN_RESULT_HALT, __( 'Empty password.', 'w3-total-cache' ) );

			return false;
		}

		if ( empty( $this->_config['zones'] ) ) {
			$results = $this->_get_results( $files, W3TC_CDN_RESULT_HALT, __( 'Empty zones list.', 'w3-total-cache' ) );

			return false;
		}

		require_once W3TC_LIB_DIR . '/Nusoap/nusoap.php';

		$client = new \nusoap_client(
			W3TC_CDN_MIRROR_COTENDO_WSDL,
			'wsdl'
		);

		$error = $client->getError();

		if ( $error ) {
			$results = $this->_get_results(
				$files,
				W3TC_CDN_RESULT_HALT,
				sprintf(
					// Translators: 1 error message.
					__(
						'Constructor error (%1$s).',
						'w3-total-cache'
					),
					$error
				)
			);

			return false;
		}

		$client->authtype      = 'basic';
		$client->username      = $this->_config['username'];
		$client->password      = $this->_config['password'];
		$client->forceEndpoint = W3TC_CDN_MIRROR_COTENDO_ENDPOINT; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		foreach ( (array) $this->_config['zones'] as $zone ) {
			$expressions = array();

			foreach ( $files as $file ) {
				$remote_path   = $file['remote_path'];
				$expressions[] = '/' . $remote_path;
			}

			$expression = implode( "\n", $expressions );

			$params = array(
				'cname'           => $zone,
				'flushExpression' => $expression,
				'flushType'       => 'hard',
			);

			$client->call( 'doFlush', $params, W3TC_CDN_MIRROR_COTENDO_NAMESPACE );

			if ( $client->fault ) {
				$results = $this->_get_results(
					$files,
					W3TC_CDN_RESULT_HALT,
					__( 'Invalid response.', 'w3-total-cache' )
				);

				return false;
			}

			$error = $client->getError();

			if ( $error ) {
				$results = $this->_get_results(
					$files,
					W3TC_CDN_RESULT_HALT,
					sprintf(
						// Translators: 1 error message.
						__(
							'Unable to purge (%1$s).',
							'w3-total-cache'
						),
						$error
					)
				);

				return false;
			}
		}

		$results = $this->_get_results(
			$files,
			W3TC_CDN_RESULT_OK,
			__( 'OK', 'w3-total-cache' )
		);

		return true;
	}

	/**
	 * Purges all files from the CDN.
	 *
	 * @param array $results Reference to the array where the purge results will be stored.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function purge_all( &$results ) {
		return $this->purge(
			array(
				array(
					'local_path'  => '*',
					'remote_path' => '*',
				),
			),
			$results
		);
	}
}
