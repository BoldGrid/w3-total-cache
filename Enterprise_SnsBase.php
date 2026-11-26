<?php
/**
 * File: Enterprise_SnsBase.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC_SKIPLIB_AWS' ) ) {
	require_once W3TC_DIR . '/vendor/autoload.php';
}

/**
 * Class Enterprise_SnsBase
 *
 * Base class for Sns communication
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class Enterprise_SnsBase {
	/**
	 * Plugin configuration.
	 *
	 * @since 2.11.1
	 *
	 * @var Config
	 */
	protected $_config;

	/**
	 * SNS region.
	 *
	 * @since 2.11.1
	 *
	 * @var string
	 */
	protected $_region;

	/**
	 * SNS topic ARN.
	 *
	 * @since 2.11.1
	 *
	 * @var string
	 */
	protected $_topic_arn;

	/**
	 * SNS API key.
	 *
	 * @since 2.11.1
	 *
	 * @var string
	 */
	protected $_api_key;

	/**
	 * SNS API secret.
	 *
	 * @since 2.11.1
	 *
	 * @var string
	 */
	protected $_api_secret;

	/**
	 * Whether debug logging is enabled.
	 *
	 * @since 2.11.1
	 *
	 * @var bool
	 */
	protected $_debug;

	/**
	 * SNS API client.
	 *
	 * @since 2.11.1
	 *
	 * @var \Aws\Sns\SnsClient|null
	 */
	protected $_api;

	/**
	 * Class constructor for Enterprise_SnsBase.
	 *
	 * Initializes the configuration, region, topic ARN, API key, API secret, and debug flag.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();

		$this->_region     = $this->_config->get_string( 'cluster.messagebus.sns.region' );
		$this->_topic_arn  = $this->_config->get_string( 'cluster.messagebus.sns.topic_arn' );
		$this->_api_key    = $this->_config->get_string( 'cluster.messagebus.sns.api_key' );
		$this->_api_secret = $this->_config->get_string( 'cluster.messagebus.sns.api_secret' );

		$this->_debug = $this->_config->get_boolean( 'cluster.messagebus.debug' );
		$this->_api   = null;
	}

	/**
	 * Retrieves the API client for AWS SNS.
	 *
	 * Checks if the API client is initialized and if not, sets it up using the provided credentials or default provider.
	 *
	 * @return \Aws\Sns\SnsClient The AWS SNS client instance.
	 *
	 * @throws \Exception If API Key or API Secret is not configured and cannot use default credentials.
	 */
	protected function _get_api() {
		if ( is_null( $this->_api ) ) {
			if ( empty( $this->_api_key ) && empty( $this->_api_secret ) ) {
				$credentials = \Aws\Credentials\CredentialProvider::defaultProvider();
			} else {
				if ( empty( $this->_api_key ) ) {
					throw new \Exception( \esc_html__( 'API Key is not configured.', 'w3-total-cache' ) );
				}

				if ( empty( $this->_api_secret ) ) {
					throw new \Exception( \esc_html__( 'API Secret is not configured.', 'w3-total-cache' ) );
				}

				$credentials = new \Aws\Credentials\Credentials( $this->_api_key, $this->_api_secret );
			}

			$this->_api = new \Aws\Sns\SnsClient(
				array(
					'credentials' => $credentials,
					'region'      => $this->_region,
					'version'     => '2010-03-31',
				)
			);
		}

		return $this->_api;
	}

	/**
	 * Logs a message with an optional backtrace to a debug file.
	 *
	 * Writes a message with an optional backtrace to the log file if debugging is enabled.
	 *
	 * @param string $w3tc_message   The message to log.
	 * @param array  $backtrace Optional. The backtrace data to log.
	 *
	 * @return bool Whether the log was successfully written.
	 */
	protected function _log( $w3tc_message, $backtrace = null ) {
		if ( ! $this->_debug ) {
			return true;
		}

		$w3tc_data = sprintf( "[%s] %s\n", gmdate( 'r' ), $w3tc_message );
		if ( $backtrace ) {
			$debug      = print_r( $backtrace, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			$w3tc_data .= $debug . "\n";
		}
		$w3tc_data = strtr( $w3tc_data, '<>', '..' );
		$w3tc_data = Util_Debug::redact( $w3tc_data );

		$filename = Util_Debug::log_filename( 'sns' );

		return @file_put_contents( $filename, $w3tc_data, FILE_APPEND );
	}
}
