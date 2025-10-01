<?php
/**
 * File: Cdn_TotalCdn_CustomHostname.php
 *
 * @since   X.X.X
 * @package W3TC
 */

namespace W3TC;

/**
 * Manages automatic Total CDN custom hostname configuration for FSD.
 *
 * @since X.X.X
 */
class Cdn_TotalCdn_CustomHostname {
	/**
	 * Cached check results for the current request.
	 *
	 * @since X.X.X
	 *
	 * @var array
	 */
	private static $checked = array();

	/**
	 * Runtime error messages queued for immediate display.
	 *
	 * @since X.X.X
	 *
	 * @var array
	 */
	private static $runtime_errors = array();

	/**
	 * Determines whether the custom hostname should be attempted during save.
	 *
	 * Why:
	 * - Attempts to create or reconcile the Full Site Delivery (FSD) custom hostname
	 *   only when it is necessary to avoid unnecessary API calls and to ensure the
	 *   CDN configuration matches the site state. This prevents duplicate hostname
	 *   creation attempts and avoids overwriting valid status values.
	 *
	 * Example:
	 * - When a site operator enables FSD and sets a TotalCDN pull zone ID, this
	 *   method will return true so the plugin can ensure the custom hostname exists
	 *   for that pull zone (triggering an API call to add/check the hostname).
	 * - When nothing relevant changed (same pull zone, same hostname, and status is
	 *   already a valid configured state), this returns false to skip extra work.
	 *
	 * @since X.X.X
	 *
	 * @param Config      $new_config New configuration values.
	 * @param Config|null $old_config Previous configuration values.
	 *
	 * @return bool True when an attempt should be made; false to skip.
	 */
	public static function should_attempt_on_save( Config $new_config, ?Config $old_config = null ): bool {
		/*
		 * If FSD with Total CDN isn't applicable for the new config, no attempt is required.
		 * Why: avoids triggering hostname logic when FSD is disabled or a different engine is used.
		 */
		if ( ! self::is_applicable( $new_config ) ) {
			return false;
		}

		$pull_zone_id = (int) $new_config->get_integer( 'cdn.totalcdn.pull_zone_id' );

		/*
		 * If there is no valid pull zone, there's nothing to configure on TotalCDN.
		 * Why: TotalCDN hostname operations require a valid pull zone ID.
		 */
		if ( $pull_zone_id <= 0 ) {
			return false;
		}

		/*
		 * No previous config or FSD was not previously enabled with Total CDN.
		 * Why: this is a new activation of FSD or switch to Total CDN so we must attempt
		 * to ensure the hostname is created for the newly-enabled scenario.
		 */
		if ( ! $old_config || ! $old_config->get_boolean( 'cdnfsd.enabled' ) ||
			'totalcdn' !== $old_config->get_string( 'cdnfsd.engine' ) ) {
			return true;
		}

		/*
		 * Pull zone changed.
		 * Why: a different pull zone may require the hostname to be (re)associated or
		 * validated against the new zone, so we must attempt configuration.
		 */
		if ( $pull_zone_id !== (int) $old_config->get_integer( 'cdn.totalcdn.pull_zone_id' ) ) {
			return true;
		}

		$hostname = self::get_site_hostname();

		/*
		 * Hostname changed or status indicates not configured.
		 * Why: if the site hostname differs from the stored hostname, the CDN needs to be
		 * rechecked/updated; if the status is empty or error, a retry is necessary.
		 * Example: site domain changed from 'example.com' to 'www.example.com' — the
		 * plugin should attempt to add or validate the new hostname.
		 */
		if ( $hostname && $hostname !== $new_config->get_string( 'cdnfsd.totalcdn.custom_hostname' ) ) {
			return true;
		}

		$status = strtolower( $new_config->get_string( 'cdnfsd.totalcdn.custom_hostname_status' ) );

		/*
		 * Status indicates not configured or error.
		 * Why: an empty status or 'error' means the hostname isn't in a known-good state,
		 * so an attempt should be made to resolve it.
		 */
		if ( empty( $status ) || 'error' === $status ) {
			return true;
		}

		// No relevant changes detected; skip the attempt to avoid unnecessary API calls.
		return false;
	}

	/**
	 * Ensures the custom hostname exists and returns the operation result.
	 *
	 * @since X.X.X
	 *
	 * @param Config $config Configuration instance to operate on.
	 * @param array  $args   {
	 *     Optional arguments.
	 *
	 *     @type bool     $persist  Whether to persist configuration changes immediately.
	 *     @type callable $on_error Callback triggered on failure. Receives the error message string.
	 * }
	 *
	 * @return array {
	 *     Result details.
	 *
	 *     @type bool        $success  True when the hostname is confirmed or added.
	 *     @type bool        $skipped  True when the check was skipped due to missing prerequisites.
	 *     @type string|null $hostname Hostname that was checked.
	 *     @type string|null $status   Last known status value.
	 *     @type string|null $error    Error message when unsuccessful.
	 *     @type bool        $added    True when an add operation was triggered.
	 * }
	 */
	public static function ensure( Config $config, array $args = array() ): array {
		$persist  = isset( $args['persist'] ) ? (bool) $args['persist'] : false;
		$on_error = isset( $args['on_error'] ) ? $args['on_error'] : null;
		$result   = array(
			'success'  => false,
			'skipped'  => false,
			'hostname' => null,
			'status'   => null,
			'error'    => null,
			'added'    => false,
		);

		if ( ! self::is_applicable( $config ) ) {
			$result['success'] = true;
			$result['skipped'] = true;
			return $result;
		}

		$hostname = self::get_site_hostname();

		if ( ! $hostname ) {
			$result['error'] = __( 'Unable to determine the site hostname required for Full Site Delivery.', 'w3-total-cache' );
			self::update_config_error( $config, '', $result['error'], $persist );
			self::trigger_error_callback( $on_error, $result['error'] );
			return $result;
		}

		$result['hostname'] = $hostname;

		$account_api_key = $config->get_string( 'cdn.totalcdn.account_api_key' );
		$pull_zone_id    = (int) $config->get_integer( 'cdn.totalcdn.pull_zone_id' );

		if ( empty( $account_api_key ) || $pull_zone_id <= 0 ) {
			$result['success'] = true;
			$result['skipped'] = true;
			return $result;
		}

		$cache_key = $pull_zone_id . '|' . $hostname;
		if ( isset( self::$checked[ $cache_key ] ) ) {
			return self::$checked[ $cache_key ];
		}

		$api = new Cdn_TotalCdn_Api(
			array(
				'account_api_key' => $account_api_key,
				'pull_zone_id'    => $pull_zone_id,
			)
		);

		$status = '';

		// First, check if the hostname already exists.
		try {
			$response = $api->check_custom_hostname( $hostname );
			$exists   = array_key_exists( 'Exists', $response ) ? (bool) $response['Exists'] : false;
			$status   = $exists ? 'exists' : '';

			$result['status'] = $status;

			if ( true === $exists || self::status_indicates_configured( $status ) ) {
				$result['success'] = true;
				self::update_config_state( $config, $hostname, $status, '', $persist );
				self::$checked[ $cache_key ] = $result;
				return $result;
			}
		} catch ( \Exception $check_exception ) {
			$result['status'] = '';
		}

		// Attempt to add the hostname since it doesn't exist or isn't confirmed.
		try {
			$api->add_custom_hostname( $hostname );
			$result['added']   = true;
			$result['success'] = true;
			$status            = ( '' === $status ) ? 'pending' : $status;
			$result['status']  = $status;
			self::update_config_state( $config, $hostname, $status, '', $persist );
		} catch ( \Exception $add_exception ) {
			$message = $add_exception->getMessage();

			if ( self::message_indicates_already_exists( $message ) ) {
				$result['success'] = true;
				$status            = ( '' === $status ) ? 'pending' : $status;
				$result['status']  = $status;
				self::update_config_state( $config, $hostname, $status, '', $persist );
			} else {
				$result['error'] = $message;
				self::update_config_error( $config, $hostname, $message, $persist );
				self::trigger_error_callback( $on_error, $message );
			}
		}

		self::$checked[ $cache_key ] = $result;
		return $result;
	}

	/**
	 * Registers an admin notice for runtime (non-redirect) contexts.
	 *
	 * @since X.X.X
	 *
	 * @param string $message Message to display.
	 *
	 * @return void
	 */
	public static function register_runtime_error( string $message ): void {
		if ( empty( $message ) ) {
			return;
		}

		if ( empty( self::$runtime_errors ) ) {
			\add_action( 'admin_notices', array( __CLASS__, 'print_runtime_errors' ) );
		}

		self::$runtime_errors[] = $message;
	}

	/**
	 * Returns the user-facing failure message.
	 *
	 * @since X.X.X
	 *
	 * @return string
	 */
	public static function failure_message(): string {
		return __( 'W3 Total Cache could not configure the Full Site Delivery custom hostname automatically. Please contact support for assistance.', 'w3-total-cache' );
	}

	/**
	 * Prints queued runtime admin errors.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function print_runtime_errors(): void {
		foreach ( self::$runtime_errors as $message ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
		}
	}

	/**
	 * Helper: determines whether configuration is applicable.
	 *
	 * @since X.X.X
	 *
	 * @param Config $config Configuration instance.
	 *
	 * @return bool
	 */
	private static function is_applicable( Config $config ): bool {
		return (bool) ( $config->get_boolean( 'cdnfsd.enabled' ) && 'totalcdn' === $config->get_string( 'cdnfsd.engine' ) );
	}

	/**
	 * Helper: resolves the current site hostname.
	 *
	 * @since X.X.X
	 *
	 * @return string|null
	 */
	private static function get_site_hostname(): ?string {
		$site_url = get_option( 'siteurl' );
		$hostname = is_string( $site_url ) ? wp_parse_url( $site_url, PHP_URL_HOST ) : '';

		if ( empty( $hostname ) ) {
			$hostname = wp_parse_url( home_url(), PHP_URL_HOST );
		}

		if ( empty( $hostname ) ) {
			return null;
		}

		return strtolower( trim( $hostname ) );
	}

	/**
	 * Helper: checks if the status indicates the hostname is already configured.
	 *
	 * @since X.X.X
	 *
	 * @param string $status Response status (lowercase).
	 *
	 * @return bool
	 */
	private static function status_indicates_configured( string $status ): bool {
		if ( empty( $status ) ) {
			return false;
		}

		if ( in_array( $status, array( 'notfound', 'invalid', 'error' ), true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Helper: checks if the API message indicates the hostname already exists.
	 *
	 * @since X.X.X
	 *
	 * @param string $message Exception message.
	 *
	 * @return bool
	 */
	private static function message_indicates_already_exists( string $message ): bool {
		$message = strtolower( $message );

		return false !== strpos( $message, 'already exists' ) || false !== strpos( $message, 'already configured' );
	}

	/**
	 * Helper: updates configuration state after a successful check.
	 *
	 * @since X.X.X
	 *
	 * @param Config $config   Configuration instance.
	 * @param string $hostname Hostname value.
	 * @param string $status   Status string.
	 * @param string $message  Optional message to store.
	 * @param bool   $persist  Whether to persist immediately.
	 *
	 * @return void
	 */
	private static function update_config_state( Config $config, string $hostname, string $status, string $message, bool $persist ): void {
		$changed = false;
		$status  = strtolower( trim( $status ) );
		$message = trim( $message );

		if ( $config->get_string( 'cdnfsd.totalcdn.custom_hostname' ) !== $hostname ) {
			$config->set( 'cdnfsd.totalcdn.custom_hostname', $hostname );
			$changed = true;
		}

		if ( $config->get_string( 'cdnfsd.totalcdn.custom_hostname_status' ) !== $status ) {
			$config->set( 'cdnfsd.totalcdn.custom_hostname_status', $status );
			$changed = true;
		}

		if ( $config->get_string( 'cdnfsd.totalcdn.custom_hostname_last_error' ) !== $message ) {
			$config->set( 'cdnfsd.totalcdn.custom_hostname_last_error', $message );
			$changed = true;
		}

		$config->set( 'cdnfsd.totalcdn.custom_hostname_last_checked', time() );

		if ( $changed && $persist ) {
			$config->save();
		}
	}

	/**
	 * Helper: updates configuration when an error occurs.
	 *
	 * @since X.X.X
	 *
	 * @param Config $config   Configuration instance.
	 * @param string $hostname Hostname value.
	 * @param string $message  Error message.
	 * @param bool   $persist  Whether to persist immediately.
	 *
	 * @return void
	 */
	private static function update_config_error( Config $config, string $hostname, string $message, bool $persist ): void {
		self::update_config_state( $config, $hostname, 'error', $message, $persist );
	}

	/**
	 * Helper: triggers the error callback if available.
	 *
	 * @since X.X.X
	 *
	 * @param callable|null $callback Callback to invoke.
	 * @param string        $message  Error message.
	 *
	 * @return void
	 */
	private static function trigger_error_callback( ?callable $callback, string $message ): void {
		if ( is_callable( $callback ) ) {
			call_user_func( $callback, $message );
		}
	}
}
