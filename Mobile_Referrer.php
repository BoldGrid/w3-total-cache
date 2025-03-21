<?php
/**
 * File: Mobile_Referrer.php
 *
 * @package W3TC
 */

namespace W3TC;

// W3TC Referrer detection.
define( 'W3TC_REFERRER_COOKIE_NAME', 'w3tc_referrer' );

/**
 * Class Mobile_Referrer
 */
class Mobile_Referrer extends Mobile_Base {
	/**
	 * Constructs the Mobile_Referrer object.
	 *
	 * Initializes the parent class with specific group and referrer settings.
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct( 'referrer.rgroups', 'referrers' );
	}

	/**
	 * Retrieves the HTTP referrer from cookies or the server.
	 *
	 * If enabled groups are present, attempts to get the HTTP referrer from a cookie or the server.
	 * Sets or clears cookies based on conditions.
	 *
	 * @return string The sanitized HTTP referrer or an empty string if not available.
	 */
	public function get_http_referrer() {
		$http_referrer = '';

		if ( $this->has_enabled_groups() ) {
			if ( isset( $_COOKIE[ W3TC_REFERRER_COOKIE_NAME ] ) ) {
				$http_referrer = htmlspecialchars( $_COOKIE[ W3TC_REFERRER_COOKIE_NAME ] ); // phpcs:ignore
			} elseif ( isset( $_SERVER['HTTP_REFERER'] ) ) {
				$http_referrer = filter_var( $_SERVER['HTTP_REFERER'], FILTER_SANITIZE_URL ); // phpcs:ignore

				setcookie( W3TC_REFERRER_COOKIE_NAME, $http_referrer, 0, '/' /* not defined yet Util_Environment::network_home_url_uri()*/ );
			}
		} elseif ( isset( $_COOKIE[ W3TC_REFERRER_COOKIE_NAME ] ) ) {
			setcookie( W3TC_REFERRER_COOKIE_NAME, '', 1 );
		}

		return $http_referrer;
	}

	/**
	 * Verifies if the HTTP referrer matches a specified group comparison value.
	 *
	 * Uses a static reference to store the HTTP referrer for efficient reuse.
	 * Matches the referrer against a regular expression provided in `$group_compare_value`.
	 *
	 * @param string $group_compare_value The regex pattern to compare against the HTTP referrer.
	 *
	 * @return bool True if the referrer matches the provided pattern, false otherwise.
	 */
	public function group_verifier( $group_compare_value ) {
		static $http_referrer = null;
		if ( is_null( $http_referrer ) ) {
			$http_referrer = $this->get_http_referrer();
		}

		return $http_referrer && preg_match( '~' . $group_compare_value . '~i', $http_referrer );
	}

	/**
	 * Retrieves the group HTTP referrer.
	 *
	 * This method acts as a wrapper for `get_http_referrer`.
	 *
	 * @return string The sanitized HTTP referrer.
	 */
	public function do_get_group() {
		return $this->get_http_referrer();
	}
}
