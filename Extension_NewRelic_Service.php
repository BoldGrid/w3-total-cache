<?php
/**
 * File: Extension_NewRelic_Service.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_NewRelic_Service
 *
 * Wrapper for NewRelicAPI.
 * deprecated
 *
 * @see NewRelicAPI
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */
class Extension_NewRelic_Service {
	/**
	 * API key
	 *
	 * @var string
	 */
	private $_api_key;

	/**
	 * Cache time
	 *
	 * @var int
	 */
	private $_cache_time;

	/**
	 * Constructor for initializing the New Relic service.
	 *
	 * @param string $api_key Optional API key to override the default configuration.
	 *
	 * @return void
	 */
	public function __construct( $api_key = '' ) {
		$config = Dispatcher::config();
		if ( $api_key ) {
			$this->_api_key = $api_key;
		} else {
			$this->_api_key = $config->get_string( array( 'newrelic', 'api_key' ) );
		}

		$this->_cache_time = $config->get_integer( array( 'newrelic', 'cache_time' ), 5 );
		if ( $this->_cache_time < 1 ) {
			$this->_cache_time = 5;
		}
	}

	/**
	 * Verifies compatibility with the New Relic PHP agent.
	 *
	 * The verifications is based on https://newrelic.com/docs/php/new-relic-for-php
	 *
	 * @return array Associative array of verification results with status messages.
	 */
	public function verify_compatibility() {
		$php_versions   = array( '5.2.x', '5.3.x', '5.4.x' );
		$verified       = array();
		$version        = explode( '.', PHP_VERSION );
		$php_version    = sprintf( '%s.%s.%s', $version[0], $version[1], $version[2] );
		$php_version_ok = version_compare( $php_version, '5.2', '>' );

		$supported_string = __( 'Supported', 'w3-total-cache' );

		$verified[ __( 'PHP version', 'w3-total-cache' ) ] = (
			$php_version_ok ? $supported_string : sprintf(
				// Translators: 1 PHP version.
				__(
					'Not supported: %1$s.',
					'w3-total-cache'
				),
				$php_version
			)
		);

		$os_name = php_uname( 's' );
		switch ( $os_name ) {
			case 'Linux':
				/**
				 * Any other version of Linux with kernel 2.6.13 or later
				 * (2.6.26 and later highly recommended) and glibc 2.5 or later
				 */
				$version    = explode( '.', php_uname( 'r' ) );
				$os_version = sprintf( '%d.%d.%d', $version[0], $version[1], $version[2] );
				$os_check   = version_compare( $os_version, '2.6.13', '>=' );
				break;
			case 'FreeBSD':
				/**
				 * You must enable the linkthr build option so that the New Relic agent will not cause your PHP to hang.
				 */
				$version    = explode( '.', php_uname( 'r' ) );
				$os_version = sprintf( '%d.%d', $version[0], $version[1] );
				$os_check   = version_compare( $os_version, '7.3', '>=' );
				break;
			case 'MacOS/X':
				/**
				 * MacOS/X configurations do not use the standard /etc/init.d/newrelic-daemon script.
				 * Instead, they use /usr/bin/newrelic-daemon-service in the same way; for example:
				 * /usr/bin/newrelic-daemon-service restart.
				 */
				$version    = explode( '.', php_uname( 'r' ) );
				$os_version = sprintf( '%d.%d', $version[0], $version[1] );
				$os_check   = version_compare( $os_version, '10.5', '>=' );
				break;
			case 'Open Solaris':
				/**
				 * Must be snv_134b or later
				 */
				$version    = explode( '.', php_uname( 'r' ) );
				$os_version = sprintf( '%d', $version[0] );
				$os_check   = version_compare( $os_version, '10', '==' );
				break;
			default:
				$os_check   = false;
				$os_name    = php_uname();
				$os_version = '';
		}

		$verified[ __( 'Operating System', 'w3-total-cache' ) ] = ( $os_check ) ?
			$supported_string :
			sprintf(
				// translators: 1 OS name, 2 OS version, 3 opening HTML a tag to NewRelic for PHP requirments, 4 closing HTML a tag.
				__(
					'Not Supported. (%1$s %2$s See %3$sNewRelic Requirements%4$s page.)',
					'w3-total-cache'
				),
				$os_name,
				$os_version,
				'<a href="https://docs.newrelic.com/docs/apm/agents/php-agent/getting-started/php-agent-compatibility-requirements/" target="_blank">',
				'</a>'
			);

		/**
		 * Apache 2.2 or 2.4 via mod_php
		 * Or any web server that supports FastCGI using php-fpm
		 */
		$server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';
		$server          = explode( '/', $server_software );
		$ws_check        = false;
		$ws_name         = $server_software;
		$ws_version      = '';

		if ( count( $server ) > 1 ) {
			$ws_name    = $server[0];
			$ws_version = $server[1];
			$version    = explode( '.', $ws_version );
			if ( count( $version ) > 1 ) {
				$ws_version = sprintf( '%d.%d', $version[0], $version[1] );
			}
		}
		switch ( true ) {
			case Util_Environment::is_apache():
				if ( $ws_version ) {
					$ws_check = version_compare( $ws_version, '2.2', '>=' );
				}
				break;
			case Util_Environment::is_nginx():
				$ws_check = 'fpm-fcgi' === php_sapi_name();
				$ws_name .= php_sapi_name();
				break;
			default:
				$ws_check   = 'fpm-fcgi' === php_sapi_name();
				$ws_name    = $server_software;
				$ws_version = '';
		}
		$verified[ __( 'Web Server', 'w3-total-cache' ) ] = $ws_check ?
			$supported_string :
			sprintf(
				// translators: 1 Web Server name, 2 Web Server version, 3 opening HTML a tag to NewRelic requirments for php, 4 closing HTML a tag.
				__(
					'Not Supported. (%1$s %2$s See %3$sNewRelic Requirements%4$s page.)',
					'w3-total-cache'
				),
				$ws_name,
				$ws_version,
				'<a href="https://docs.newrelic.com/docs/apm/agents/php-agent/getting-started/php-agent-compatibility-requirements/" target="_blank">',
				'</a>'
			);
		return $verified;
	}

	/**
	 * Verifies if the New Relic service is running properly.
	 *
	 * @return mixed An array of error messages or true if everything is configured correctly.
	 *
	 * @throws \Exception If an error occurs while verifying the configuration.
	 */
	public function verify_running() {
		$config = Dispatcher::config();

		$error = array();
		if ( ! $this->get_api_key() ) {
			$error['api_key'] = __( 'API Key is not configured.', 'w3-total-cache' );
		}

		if ( 'browser' === $config->get( array( 'newrelic', 'monitoring_type' ) ) ) {
			$name = $this->get_effective_appname();
			if ( empty( $name ) ) {
				$error['application_id'] = __( 'Application ID is not configured. Enter/Select application name.', 'w3-total-cache' );
			}
		} else {
			if ( ! $this->module_is_enabled() ) {
				$error['module_enabled'] = __( 'PHP module is not enabled.', 'w3-total-cache' );
			}

			if ( ! $this->agent_enabled() ) {
				$error['agent_enabled'] = __( 'PHP agent is not enabled.', 'w3-total-cache' );
			}

			if ( ! $this->get_account_id() ) {
				$error['account_id'] = __( 'Account ID is not configured.', 'w3-total-cache' );
			}

			if ( 0 === $this->get_effective_application_id() ) {
				$error['application_id'] = __( 'Application ID is not configured. Enter/Select application name.', 'w3-total-cache' );
			}

			try {
				if ( ! $this->get_license_key_from_ini() ) {
					$error['license'] = __( 'License key could not be detected in ini file.', 'w3-total-cache' );
				}

				$licences = explode( ' ', trim( $this->get_license_key_from_ini() ) );
				$licences = array_map( 'trim', $licences );
				if (
					$this->get_license_key_from_ini() &&
					$this->get_license_key_from_account() &&
					! in_array( trim( $this->get_license_key_from_account() ), $licences, true )
				) {
					$error['license'] = sprintf(
						// Translators: 1 license key, 2 license key list.
						__(
							'Configured license key does not match license key(s) in account: <br />%1$s <br />%2$s',
							'w3-total-cache'
						),
						$this->get_license_key_from_ini(),
						implode( '<br />', $licences )
					);
				}

				$this->get_account_id();
			} catch ( \Exception $ex ) {
				$error['api_key'] = __( 'API Key is invalid.', 'w3-total-cache' );
			}
		}

		return $error ? $error : true;
	}

	/**
	 * Checks if the New Relic PHP agent is enabled.
	 *
	 * @return bool True if the agent is enabled, false otherwise.
	 */
	public function agent_enabled() {
		return ini_get( 'newrelic.enabled' );
	}

	/**
	 * Checks if the New Relic module is enabled in PHP.
	 *
	 * @return bool True if the module is enabled, false otherwise.
	 */
	public function module_is_enabled() {
		return function_exists( 'newrelic_set_appname' );
	}

	/**
	 * Retrieves the New Relic license key from the PHP configuration.
	 *
	 * @return string The New Relic license key.
	 */
	public function get_license_key_from_ini() {
		return ini_get( 'newrelic.license' );
	}

	/**
	 * Retrieves the API key used for authentication with the New Relic service.
	 *
	 * @return string The API key.
	 */
	public function get_api_key() {
		return $this->_api_key;
	}

	/**
	 * Retrieves the New Relic API instance.
	 *
	 * phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
	 *
	 * @return \NewRelicAPI The API instance for interacting with New Relic.
	 */
	private function getAPI() {
		static $api = null;
		if ( ! $api ) {
			require_once W3TC_LIB_NEWRELIC_DIR . '/NewRelicAPI.php';
			$api = new \NewRelicAPI( $this->_api_key );
		}

		return $api;
	}

	/**
	 * Retrieves a list of applications associated with the New Relic account.
	 *
	 * @return array An array of applications.
	 */
	public function get_applications() {
		if ( empty( $this->_api_key ) ) {
			return array();
		}

		return $this->getAPI()->get_applications( $this->get_account_id() );
	}

	/**
	 * Retrieves a list of browser applications associated with the New Relic account.
	 *
	 * @return array An array of browser applications.
	 */
	public function get_browser_applications() {
		return $this->getAPI()->get_browser_applications();
	}

	/**
	 * Retrieves a specific application by its ID.
	 *
	 * @param int $application_id The application ID.
	 *
	 * @return array The details of the specified application.
	 */
	public function get_application( $application_id ) {
		$applications = $this->get_applications();
		return $applications[ $application_id ];
	}

	/**
	 * Retrieves the summary of a specific application.
	 *
	 * @return array The application summary.
	 */
	public function get_application_summary() {
		return $this->getAPI()->get_application_summary(
			$this->get_account_id(),
			$this->get_effective_application_id()
		);
	}

	/**
	 * Retrieves the account information associated with the New Relic service.
	 *
	 * @return array The account details.
	 */
	public function get_account() {
		static $account = null;
		if ( ! $account ) {
			$account = $this->getAPI()->get_account();
		}
		return $account;
	}

	/**
	 * Retrieves the subscription details of the New Relic account.
	 *
	 * @return mixed The subscription details or null if not available.
	 */
	public function get_subscription() {
		$account = $this->get_account();
		if ( $account ) {
			return $account['subscription'];
		}
		return null;
	}

	/**
	 * Determines whether the current subscription allows metric retrieval.
	 *
	 * @return bool True if the subscription is not 'Lite', false otherwise.
	 */
	public function can_get_metrics() {
		$subscription = $this->get_subscription();
		return 'Lite' !== $subscription['product-name'];
	}

	/**
	 * Retrieves the license key associated with the current account.
	 *
	 * @return string|null The license key, or null if not found.
	 */
	public function get_license_key_from_account() {
		$account = $this->get_account();
		if ( $account ) {
			return $account['license-key'];
		}
		return null;
	}

	/**
	 * Fetches the application settings for the current account and application.
	 *
	 * @return array The application settings.
	 */
	public function get_application_settings() {
		$settings = $this->getAPI()->get_application_settings(
			$this->get_account_id(),
			$this->get_effective_application_id()
		);

		return $settings;
	}

	/**
	 * Updates the application settings for the current account and application.
	 *
	 * @param array $application The application settings to update.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function update_application_settings( $application ) {
		$result = $this->getAPI()->update_application_settings(
			$this->get_account_id(),
			$this->get_effective_application_id(),
			$application
		);
		return $result;
	}

	/**
	 * Retrieves metric names based on an optional regular expression filter and limit.
	 *
	 * @param string $regex Optional regular expression to filter metric names.
	 * @param string $limit Optional limit for the number of metrics to retrieve.
	 *
	 * @return array An associative array of metric names and their corresponding metric objects.
	 */
	public function get_metric_names( $regex = '', $limit = '' ) {
		$metric_names_object = $this->getAPI()->get_metric_names( $this->get_effective_application_id(), $regex, $limit );
		if ( ! $metric_names_object ) {
			return array();
		}

		$metric_names = array();
		foreach ( $metric_names_object as $metric ) {
			$metric_names[ $metric->name ] = $metric;
		}

		return $metric_names;
	}

	/**
	 * Retrieves metric data for the given metrics and field over a specified number of days.
	 *
	 * phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
	 *
	 * @param array|string $metrics The metrics to fetch data for.
	 * @param string       $field   The field of data to retrieve.
	 * @param int          $days    The number of days of data to retrieve.
	 * @param bool         $summary Whether to summarize the data.
	 * @param bool         $use_subgroup Whether to group data by subgroups.
	 *
	 * @return array An array of formatted metric data.
	 */
	public function get_metric_data( $metrics, $field, $days = 7, $summary = true, $use_subgroup = true ) {
		if ( ! is_array( $metrics ) ) {
			$metrics = array( $metrics );
		}

		$begin     = new \DateTime( gmdate( 'Y-m-d G:i:s', strtotime( ( $days > 1 ? "-$days days" : "-$days day" ) ) ) );
		$beginStr  = $begin->format( 'Y-m-d' ) . 'T' . $begin->format( 'H:i:s' ) . 'Z';
		$to        = new \DateTime( gmdate( 'Y-m-d G:i:s' ) );
		$toStr     = $to->format( 'Y-m-d' ) . 'T' . $to->format( 'H:i:s' ) . 'Z';
		$cache_key = md5(
			implode(
				',',
				array(
					$this->get_account_id(),
					$this->get_effective_application_id(),
					$beginStr,
					$toStr,
					implode( ',', $metrics ),
					$field,
					$summary,
				)
			)
		);

		$metric_data    = $this->getAPI()->get_metric_data(
			$this->get_account_id(),
			$this->get_effective_application_id(),
			$beginStr,
			$toStr,
			$metrics,
			$field,
			$summary
		);
		$formatted_data = array();

		if ( $metric_data ) {
			foreach ( $metric_data as $metric ) {
				$path  = explode( '/', $metric->name );
				$group = $path[0];
				if ( $use_subgroup ) {
					$subgroup                                = isset( $path[1] ) ? ( 'all' === $path[1] ? 0 : $path[1] ) : 0;
					$formatted_data[ $group ][ $subgroup ][] = $metric;
				} else {
					$formatted_data[ $group ][] = $metric;
				}
			}
		}

		return $formatted_data;
	}

	/**
	 * Retrieves a set of dashboard metrics for monitoring.
	 *
	 * @return array An array of formatted dashboard metric data.
	 */
	public function get_dashboard_metrics() {
		$metrics = array( 'Database/all', 'WebTransaction', 'EndUser' );
		$field   = 'average_response_time';
		return $this->get_metric_data( $metrics, $field, 1, true );
	}

	/**
	 * Retrieves the slowest page load times for web transactions.
	 *
	 * @return array An associative array of slowest page loads by transaction name and response time.
	 */
	public function get_slowest_page_load() {
		$metric_names = $this->get_metric_names( 'EndUser/WebTransaction/WebTransaction/' );

		$metric_names_keys = array_keys( $metric_names );
		$metric_data       = $this->get_metric_data( $metric_names_keys, 'average_response_time', 1 );
		$slowest           = array();

		if ( $metric_data ) {
			$transactions = $metric_data['EndUser']['WebTransaction'];
			foreach ( $transactions as $transaction ) {
				$key             = str_replace( 'EndUser/WebTransaction/WebTransaction', '', $transaction->name );
				$slowest[ $key ] = $transaction->average_response_time;
			}
			$slowest = $this->_sort_and_slice( $slowest, 5 );
		}
		return $slowest;
	}

	/**
	 * Retrieves the slowest web transactions based on response time.
	 *
	 * @return array An associative array of the slowest web transactions by name and response time.
	 */
	public function get_slowest_webtransactions() {
		$metric_names      = $this->get_metric_names( '^WebTransaction/' );
		$metric_names_keys = array_keys( $metric_names );
		$metric_data       = $this->get_metric_data( $metric_names_keys, 'average_response_time', 1 );
		$slowest           = array();
		if ( $metric_data ) {
			$transactions = $metric_data['WebTransaction'];
			foreach ( $transactions as $transaction ) {
				foreach ( $transaction as $tr_sub ) {
					$key             = str_replace( 'WebTransaction', '', $tr_sub->name );
					$slowest[ $key ] = $tr_sub->average_response_time;
				}
			}
			$slowest = $this->_sort_and_slice( $slowest, 5 );
		}
		return $slowest;
	}

	/**
	 * Retrieves the slowest database transactions based on response time.
	 *
	 * @return array An associative array of the slowest database transactions by name and response time.
	 */
	public function get_slowest_database() {
		$metric_names      = $this->get_metric_names( '^Database/' );
		$metric_names_keys = array_keys( $metric_names );
		$metric_names_keys = array_slice( $metric_names_keys, 7 );
		$metric_data       = $this->get_metric_data( $metric_names_keys, 'average_response_time', 1, true, false );
		$slowest           = array();
		if ( $metric_data ) {
			$transactions = $metric_data['Database'];
			foreach ( $transactions as $transaction ) {
				$key             = str_replace( 'Database', '', $transaction->name );
				$slowest[ $key ] = $transaction->average_response_time;
			}
			$slowest = $this->_sort_and_slice( $slowest, 5 );
		}
		return $slowest;
	}

	/**
	 * Retrieves the frontend response time metric.
	 *
	 * @return float The average frontend response time.
	 */
	public function get_frontend_response_time() {
		$metric_data = $this->get_metric_data( 'EndUser', 'average_fe_response_time', 1, true, false );
		return isset( $metric_data['EndUser'] ) ? $metric_data['EndUser'][0]->average_fe_response_time : 0;
	}

	/**
	 * Sorts and slices the given slowest data to a specified size.
	 *
	 * @param array $slowest The array of slowest data to sort.
	 * @param int   $size    The number of top slowest items to return.
	 *
	 * @return array The top slowest data, sorted and sliced.
	 */
	private function _sort_and_slice( $slowest, $size ) {
		arsort( $slowest, SORT_NUMERIC );
		if ( count( $slowest ) > $size ) {
			$slowest = array_slice( $slowest, 0, $size );
		}
		return $slowest;
	}

	/**
	 * Retrieves the name of an application based on its ID.
	 *
	 * @param int $application_id The ID of the application.
	 *
	 * @return string The application name, or an empty string if not found.
	 */
	public function get_application_name( $application_id ) {
		$apps = $this->get_applications( $this->get_account_id() );
		return isset( $apps[ $application_id ] ) ? $apps[ $application_id ] : '';
	}

	/**
	 * Retrieves the account ID associated with the current API key.
	 *
	 * @return int The account ID, or 0 if not found or invalid.
	 */
	public function get_account_id() {
		if ( empty( $this->_api_key ) ) {
			return 0;
		}

		$ids_string = get_option( 'w3tc_nr_account_id' );
		$ids        = @json_decode( $ids_string, true );
		if ( ! is_array( $ids ) ) {
			$ids = array();
		}

		if ( isset( $ids[ $this->_api_key ] ) ) {
			return $ids[ $this->_api_key ];
		}

		$ids[ $this->_api_key ] = 0;

		try {
			$account = $this->getAPI()->get_account();

			if ( $account ) {
				$ids[ $this->_api_key ] = (int) $account['id'];
			}
		} catch ( \Exception $ex ) {
			return 0;
		}

		update_option( 'w3tc_nr_account_id', wp_json_encode( $ids ) );

		return $ids[ $this->_api_key ];
	}

	/**
	 * Retrieves the application ID based on the application name.
	 *
	 * @param string $appname The application name.
	 *
	 * @return int The application ID, or 0 if not found.
	 */
	public function get_application_id( $appname ) {
		if ( empty( $appname ) ) {
			return 0;
		}

		$apps = $this->get_applications();
		foreach ( $apps as $id => $name ) {
			if ( $name === $appname ) {
				return $id;
			}
		}
		return 0;
	}

	/**
	 * Retrieves the effective application ID based on the current configuration and API key.
	 *
	 * @return int The effective application ID, or 0 if an error occurs.
	 */
	public function get_effective_application_id() {
		$config = Dispatcher::config();

		$monitoring_type = $config->get_string( array( 'newrelic', 'monitoring_type' ) );
		if ( 'browser' === $monitoring_type ) {
			return $config->get_string( array( 'newrelic', 'browser.application_id' ) );
		}

		$appname    = $this->get_effective_appname();
		$ids_string = get_option( 'w3tc_nr_application_id' );
		$ids        = @json_decode( $ids_string, true );
		if ( ! is_array( $ids ) ) {
			$ids = array();
		}

		$key = md5( $this->_api_key . $appname );
		if ( isset( $ids[ $key ] ) ) {
			return $ids[ $key ];
		}

		try {
			$ids[ $key ] = $this->get_application_id( $appname );
		} catch ( \Exception $ex ) {
			return 0;
		}

		update_option( 'w3tc_nr_application_id', wp_json_encode( $ids ) );

		return $ids[ $key ];
	}

	/**
	 * Retrieves the effective application name based on the current configuration and monitoring type.
	 *
	 * @return string The effective application name.
	 */
	public function get_effective_appname() {
		$config = Dispatcher::config();

		$monitoring_type = $config->get_string( array( 'newrelic', 'monitoring_type' ) );
		if ( 'browser' === $monitoring_type ) {
			$core = Dispatcher::component( 'Extension_NewRelic_Core' );
			$a    = $core->get_effective_browser_application();
			if ( isset( $a['name'] ) ) {
				return $a['name'];
			}

			return '?';
		}

		return $config->get_string( array( 'newrelic', 'apm.application_name' ) );
	}
}
