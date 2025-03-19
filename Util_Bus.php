<?php
/**
 * File: Util_Bus.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Util_Bus
 */
class Util_Bus {
	/**
	 * Add W3TC action callback
	 *
	 * @param string $key      Key.
	 * @param mixed  $callback Callback.
	 *
	 * @return void
	 */
	public static function add_ob_callback( $key, $callback ) {
		$GLOBALS['_w3tc_ob_callbacks'][ $key ] = $callback;
	}

	/**
	 * Do W3TC action callbacks
	 *
	 * @param array $order Order.
	 * @param mixed $value Value.
	 *
	 * @return mixed
	 */
	public static function do_ob_callbacks( $order, $value ) {
		foreach ( $order as $key ) {
			if ( isset( $GLOBALS['_w3tc_ob_callbacks'][ $key ] ) ) {
				$callback = $GLOBALS['_w3tc_ob_callbacks'][ $key ];
				if ( is_callable( $callback ) ) {
					$value = call_user_func( $callback, $value );
				}
			}
		}

		return $value;
	}
}
