<?php
/**
 * File: Util_CacheControl.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Reads Cache-Control directives shared by page-cache storage and prefetch.
 *
 * @since 2.11.1
 */
class Util_CacheControl {
	/**
	 * Whether a Cache-Control field tells a shared cache not to store the response.
	 *
	 * A shared cache may store a response that carries must-revalidate. That
	 * directive only stops use of a stale stored response, and cache-with-validation
	 * sends it on responses this plugin stores.
	 *
	 * @since 2.11.1
	 *
	 * @param mixed $field Header field value, or several values.
	 * @return bool
	 */
	public static function disallows_shared_store( $field ) {
		if ( \is_array( $field ) ) {
			$field = \implode( ', ', $field );
		}

		if ( ! \is_string( $field ) || '' === $field ) {
			return false;
		}

		foreach ( \explode( ',', \strtolower( $field ) ) as $directive ) {
			$directive = \trim( $directive );
			if ( '' === $directive ) {
				continue;
			}

			$name = \trim( \explode( '=', $directive, 2 )[0] );
			if ( 'no-store' === $name || 'no-cache' === $name || 'private' === $name ) {
				return true;
			}
		}

		return false;
	}
}
