<?php
/**
 * File: Util_Request.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Util_Request
 *
 * W3 Request object
 *
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class Util_Request {
	/**
	 * Returns request value
	 *
	 * @param string $w3tc_key           Key.
	 * @param mixed  $default_value Default value.
	 *
	 * @return mixed
	 */
	public static function get( $w3tc_key, $default_value = null ) {
		$request = self::get_request();

		if ( isset( $request[ $w3tc_key ] ) ) {
			$w3tc_value = $request[ $w3tc_key ];

			if ( defined( 'TEMPLATEPATH' ) ) {
				$w3tc_value = Util_Environment::stripslashes( $w3tc_value );
			}

			return $w3tc_value;
		}

		return $default_value;
	}

	/**
	 * Returns string value
	 *
	 * @param string $w3tc_key           Key.
	 * @param string $default_value Default value.
	 * @param bool   $trim          Trim.
	 *
	 * @return string
	 */
	public static function get_string( $w3tc_key, $default_value = '', $trim = true ) {
		$w3tc_value = (string) self::get( $w3tc_key, $default_value );

		return ( $trim ) ? trim( $w3tc_value ) : $w3tc_value;
	}

	/**
	 * Get label.
	 *
	 * @param string $w3tc_key           Key.
	 * @param string $default_value Default value.
	 *
	 * @return string
	 */
	public static function get_label( $w3tc_key, $default_value = '' ) {
		$v = self::get_string( $w3tc_key, $default_value );
		return preg_replace( '/[^A-Za-z0-9_\\-]/', '', $v );
	}

	/**
	 * Returns integer value.
	 *
	 * @param string $w3tc_key           Key.
	 * @param int    $default_value Default value.
	 *
	 * @return int
	 */
	public static function get_integer( $w3tc_key, $default_value = 0 ) {
		return (int) self::get( $w3tc_key, $default_value );
	}

	/**
	 * Returns double value.
	 *
	 * @param string       $w3tc_key           Key.
	 * @param double|float $default_value Default value.
	 *
	 * @return double
	 */
	public static function get_double( $w3tc_key, $default_value = 0. ) {
		return (float) self::get( $w3tc_key, $default_value );
	}

	/**
	 * Returns boolean value.
	 *
	 * @param string $w3tc_key           Key.
	 * @param bool   $default_value Default value.
	 *
	 * @return bool
	 */
	public static function get_boolean( $w3tc_key, $default_value = false ) {
		return Util_Environment::to_boolean( self::get( $w3tc_key, $default_value ) );
	}

	/**
	 * Returns array value.
	 *
	 * @param string $w3tc_key           Key.
	 * @param array  $default_value Default value.
	 *
	 * @return array
	 */
	public static function get_array( $w3tc_key, $default_value = array() ) {
		$w3tc_value = self::get( $w3tc_key );

		if ( is_array( $w3tc_value ) ) {
			return $w3tc_value;
		} elseif ( ! empty( $w3tc_value ) ) {
			return preg_split( "/[\r\n,;]+/", trim( $w3tc_value ) );
		}

		return $default_value;
	}

	/**
	 * Returns array value.
	 *
	 * @param string $prefix        Prefix.
	 * @param array  $default_value Default value.
	 *
	 * @return array
	 */
	public static function get_as_array( $prefix, $default_value = array() ) {
		$request = self::get_request();
		$array   = array();

		foreach ( $request as $w3tc_key => $w3tc_value ) {
			if ( strpos( $w3tc_key, $prefix ) === 0 || strpos( $w3tc_key, str_replace( '.', '_', $prefix ) ) === 0 ) {
				$array[ substr( $w3tc_key, strlen( $prefix ) ) ] = $w3tc_value;
			}
		}
		return $array;
	}

	/**
	 * Returns request array.
	 *
	 * phpcs:disable WordPress.Security.NonceVerification.Recommended
	 *
	 * @return array
	 */
	public static function get_request() {
		if ( ! isset( $_GET ) ) {
			$_GET = array();
		}

		if ( ! isset( $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$_POST = array();
		}

		return array_merge( $_GET, $_POST ); // phpcs:ignore
	}
}
