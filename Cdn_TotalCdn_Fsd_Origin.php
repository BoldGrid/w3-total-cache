<?php
/**
 * File: Cdn_TotalCdn_Fsd_Origin.php
 *
 * @since   X.X.X
 * @package W3TC
 */

namespace W3TC;

/**
 * Handles updating the Total CDN origin when Full Site Delivery is enabled.
 *
 * @since X.X.X
 */
class Cdn_TotalCdn_Fsd_Origin {
	/**
	 * Determines whether the origin should be updated when saving settings.
	 *
	 * @since X.X.X
	 *
	 * @param Config      $new_config New configuration values.
	 * @param Config|null $old_config Previous configuration values.
	 *
	 * @return bool True when the origin requires an update.
	 */
	public static function should_update_on_save( Config $new_config, ?Config $old_config = null ): bool {
		if ( ! self::is_applicable( $new_config ) ) {
			return false;
		}

		if ( ! $old_config || ! self::is_applicable( $old_config ) ) {
			return true;
		}

		if ( ! $old_config->get_boolean( 'cdnfsd.enabled' ) && $new_config->get_boolean( 'cdnfsd.enabled' ) ) {
			return true;
		}

		if ( 'totalcdn' !== $old_config->get_string( 'cdnfsd.engine' ) ) {
			return true;
		}

		$new_zone_id = (int) $new_config->get_integer( 'cdn.totalcdn.pull_zone_id' );
		$old_zone_id = (int) $old_config->get_integer( 'cdn.totalcdn.pull_zone_id' );

		if ( $new_zone_id > 0 && $new_zone_id !== $old_zone_id ) {
			return true;
		}

		$new_origin = $new_config->get_string( 'cdn.totalcdn.origin_url' );

		if ( ! self::origin_uses_ip( $new_origin ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Updates the origin URL and host header for the configured pull zone.
	 *
	 * @since X.X.X
	 *
	 * @param Config $config Configuration instance.
	 * @param array  $args   {
	 *     Optional arguments.
	 *
	 *     @type \W3TC\Cdn_TotalCdn_Api $api Preconfigured API instance (primarily for testing).
	 * }
	 *
	 * @return array {
	 *     Result details.
	 *
	 *     @type bool        $success     True when the origin was updated.
	 *     @type bool        $skipped     True when no update was required.
	 *     @type string|null $ip_address  Resolved IP address.
	 *     @type string|null $origin_url  Origin URL applied to the pull zone.
	 *     @type string|null $host_header Host header applied to the pull zone.
	 *     @type string|null $error       Error message when unsuccessful.
	 * }
	 */
	public static function ensure( Config $config, array $args = array() ): array {
		$result = array(
			'success'     => false,
			'skipped'     => false,
			'ip_address'  => null,
			'origin_url'  => null,
			'host_header' => null,
			'error'       => null,
		);

		if ( ! self::is_applicable( $config ) ) {
			$result['success'] = true;
			$result['skipped'] = true;
			return $result;
		}

		$host_header = Util_Environment::get_site_hostname();

		if ( empty( $host_header ) ) {
			$result['error'] = \__( 'Unable to determine the site hostname required for Full Site Delivery.', 'w3-total-cache' );
			return $result;
		}

		$pull_zone_id = (int) $config->get_integer( 'cdn.totalcdn.pull_zone_id' );

		try {
			$api = isset( $args['api'] ) && $args['api'] instanceof Cdn_TotalCdn_Api
				? $args['api']
				: new Cdn_TotalCdn_Api(
					array(
						'account_api_key' => $config->get_string( 'cdn.totalcdn.account_api_key' ),
						'pull_zone_id'    => $pull_zone_id,
					)
				);
		} catch ( \Exception $exception ) {
			$result['error'] = $exception->getMessage();
			return $result;
		}

		try {
			$ip_address = $api->get_origin_ip_address();
		} catch ( \Exception $exception ) {
			$result['error'] = $exception->getMessage();
			return $result;
		}

		if ( empty( $ip_address ) || false === \filter_var( $ip_address, FILTER_VALIDATE_IP ) ) {
			$result['error'] = \__( 'The CDN API did not return a valid IP address for Full Site Delivery.', 'w3-total-cache' );
			return $result;
		}

		$site_url = \get_option( 'siteurl' );
		if ( ! \is_string( $site_url ) || '' === $site_url ) {
			$site_url = \home_url();
		}

		$scheme = \wp_parse_url( $site_url, PHP_URL_SCHEME );
		if ( empty( $scheme ) ) {
			$scheme = \is_ssl() ? 'https' : 'http';
		}

		$origin_url = $scheme . '://' . $ip_address;

		try {
			$api->update_pull_zone(
				$pull_zone_id,
				array(
					'OriginUrl'        => $origin_url,
					'OriginHostHeader' => $host_header,
				)
			);
		} catch ( \Exception $exception ) {
			$result['error'] = $exception->getMessage();
			return $result;
		}

		$config->set( 'cdn.totalcdn.origin_url', $origin_url );
		$config->set( 'cdnfsd.totalcdn.origin_url', $origin_url );

		$result['success']     = true;
		$result['ip_address']  = $ip_address;
		$result['origin_url']  = $origin_url;
		$result['host_header'] = $host_header;

		return $result;
	}

	/**
	 * Determines if the configuration meets the requirements for an origin update.
	 *
	 * @since X.X.X
	 *
	 * @param Config $config Configuration instance.
	 *
	 * @return bool True when the configuration is applicable.
	 */
	private static function is_applicable( Config $config ): bool {
		if ( ! $config->get_boolean( 'cdnfsd.enabled' ) ) {
			return false;
		}

		if ( 'totalcdn' !== $config->get_string( 'cdnfsd.engine' ) ) {
			return false;
		}

		if ( empty( $config->get_string( 'cdn.totalcdn.account_api_key' ) ) ) {
			return false;
		}

		if ( (int) $config->get_integer( 'cdn.totalcdn.pull_zone_id' ) <= 0 ) {
			return false;
		}

		return true;
	}

	/**
	 * Determines whether the supplied origin URL contains a valid IP host.
	 *
	 * @since X.X.X
	 *
	 * @param string $origin_url Origin URL to evaluate.
	 *
	 * @return bool True when the origin host is an IP address.
	 */
	private static function origin_uses_ip( string $origin_url ): bool {
		if ( empty( $origin_url ) ) {
			return false;
		}

		$host = \wp_parse_url( $origin_url, PHP_URL_HOST );

		return ! empty( $host ) && false !== \filter_var( $host, FILTER_VALIDATE_IP );
	}
}
