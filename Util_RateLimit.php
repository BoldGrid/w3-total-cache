<?php
/**
 * File: Util_RateLimit.php
 *
 * Transient-backed request budget helper for admin AJAX and public
 * endpoint abuse throttling.
 *
 * @package W3TC
 *
 * @since 2.10.6
 */

namespace W3TC;

/**
 * Class Util_RateLimit
 *
 * @since 2.10.6
 */
class Util_RateLimit {
	/**
	 * Whether the caller may proceed under the named budget.
	 *
	 * Increments the counter when the request is allowed. Returns false
	 * when the budget is exhausted for the current window.
	 *
	 * @since 2.10.6
	 *
	 * @param string $bucket          Budget name (letters, digits, underscore).
	 * @param int    $max             Maximum allowed hits in the window.
	 * @param int    $window_seconds  Transient TTL / window length.
	 * @param string $discriminator   Optional per-user or per-IP suffix.
	 *
	 * @return bool
	 */
	public static function allow( $bucket, $max, $window_seconds, $discriminator = '' ) {
		if ( ! \is_string( $bucket ) || '' === $bucket ) {
			return false;
		}

		$max            = (int) $max;
		$window_seconds = (int) $window_seconds;
		if ( $max < 1 || $window_seconds < 1 ) {
			return false;
		}

		$key   = self::transient_key( $bucket, $discriminator );
		$count = (int) \get_transient( $key );
		if ( $count >= $max ) {
			return false;
		}

		\set_transient( $key, $count + 1, $window_seconds );
		return true;
	}

	/**
	 * Build a transient key for a rate-limit bucket.
	 *
	 * @since 2.10.6
	 *
	 * @param string $bucket        Budget name.
	 * @param string $discriminator Optional discriminator.
	 *
	 * @return string
	 */
	private static function transient_key( $bucket, $discriminator ) {
		$bucket = \preg_replace( '/[^a-z0-9_]/', '', \strtolower( $bucket ) );
		$suffix = '';
		if ( \is_string( $discriminator ) && '' !== $discriminator ) {
			$suffix = '_' . \substr( \md5( $discriminator ), 0, 12 );
		}

		return 'w3tc_rl_' . $bucket . $suffix;
	}
}
