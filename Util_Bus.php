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
	 * @param string $w3tc_key      Key.
	 * @param mixed  $callback Callback.
	 *
	 * @return void
	 */
	public static function add_ob_callback( $w3tc_key, $callback ) {
		$GLOBALS['w3tc_ob_callbacks'][ $w3tc_key ] = $callback;
	}

	/**
	 * Do W3TC action callbacks
	 *
	 * @param array $order Order.
	 * @param mixed $w3tc_value Value.
	 *
	 * @return mixed
	 */
	public static function do_ob_callbacks( $order, $w3tc_value ) {
		foreach ( $order as $w3tc_key ) {
			if ( isset( $GLOBALS['w3tc_ob_callbacks'][ $w3tc_key ] ) ) {
				$callback = $GLOBALS['w3tc_ob_callbacks'][ $w3tc_key ];
				if ( is_callable( $callback ) ) {
					$w3tc_value = call_user_func( $callback, $w3tc_value );
				}
			}
		}

		return $w3tc_value;
	}
}
