<?php
/**
 * Reflection helpers.
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Reflection helpers.
 *
 * @since 2.11.0
 */
class Util_Reflection {

	/**
	 * Allow invoking non-public reflected members on PHP versions that require it.
	 *
	 * @since 2.11.0
	 *
	 * @param \ReflectionMethod|\ReflectionProperty $ref Reflected member.
	 *
	 * @return void
	 */
	public static function set_accessible( $ref ) {
		if ( \PHP_VERSION_ID < 80100 ) {
			$ref->setAccessible( true );
		}
	}
}
