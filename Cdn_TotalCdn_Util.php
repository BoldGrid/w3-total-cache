<?php
/**
 * File: Cdn_TotalCdn_Util.php
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdnfsd_TotalCdn_Page
 *
 * @since X.X.X
 */
class Cdn_TotalCdn_Util {
	/**
	 * Determines if Total CDN is enabled.
	 *
	 * @since X.X.X
	 *
	 * @return bool
	 */
	public static function is_totalcdn_cdn_enabled() {
		$config = Dispatcher::config();

		return (
			$config->get_boolean( 'cdn.enabled' )
			&& 'totalcdn' === $config->get_string( 'cdn.engine' )
		);
	}

	/**
	 * Determines if Total CDN FSD is enabled.
	 *
	 * @since X.X.X
	 *
	 * @return bool
	 */
	public static function is_totalcdn_cdnfsd_enabled() {
		$config = Dispatcher::config();

		return (
			$config->get_boolean( 'cdnfsd.enabled' )
			&& 'totalcdn' === $config->get_string( 'cdnfsd.engine' )
		);
	}

	/**
	 * Determines if Total CDN is authorized.
	 *
	 * @since X.X.X
	 *
	 * @return bool
	 */
	public static function is_totalcdn_authorized() {
		$config = Dispatcher::config();

		return (
			! empty( $config->get_string( 'cdn.totalcdn.account_api_key' ) )
			&& ! empty( $config->get_integer( 'cdn.totalcdn.pull_zone_id' ) )
		);
	}

	/**
	 * Determines if Total CDN license is active.
	 *
	 * @since X.X.X
	 *
	 * @return bool
	 */
	public static function is_totalcdn_license_active() {
		$config_state = Dispatcher::config_state();

		$status = $config_state->get( 'cdn.totalcdn.status', 'inactive.no_key' );

		return 0 === strpos( $status, 'active' );
	}
}
