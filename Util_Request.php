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
	 * @param string $key           Key.
	 * @param mixed  $default_value Default value.
	 *
	 * @return mixed
	 */
	public static function get( $key, $default_value = null ) {
		$request = self::get_request();

		if ( isset( $request[ $key ] ) ) {
			$value = $request[ $key ];

			if ( defined( 'TEMPLATEPATH' ) ) {
				$value = Util_Environment::stripslashes( $value );
			}

			return $value;
		}

		return $default_value;
	}

	/**
	 * Returns string value
	 *
	 * @param string $key           Key.
	 * @param string $default_value Default value.
	 * @param bool   $trim          Trim.
	 *
	 * @return string
	 */
	public static function get_string( $key, $default_value = '', $trim = true ) {
		$value = (string) self::get( $key, $default_value );

		return ( $trim ) ? trim( $value ) : $value;
	}

	/**
	 * Get label.
	 *
	 * @param string $key           Key.
	 * @param string $default_value Default value.
	 *
	 * @return string
	 */
	public static function get_label( $key, $default_value = '' ) {
		$v = self::get_string( $key, $default_value );
		return preg_replace( '/[^A-Za-z0-9_\\-]/', '', $v );
	}

	/**
	 * Returns integer value.
	 *
	 * @param string $key           Key.
	 * @param int    $default_value Default value.
	 *
	 * @return int
	 */
	public static function get_integer( $key, $default_value = 0 ) {
		return (int) self::get( $key, $default_value );
	}

	/**
	 * Returns double value.
	 *
	 * @param string       $key           Key.
	 * @param double|float $default_value Default value.
	 *
	 * @return double
	 */
	public static function get_double( $key, $default_value = 0. ) {
		return (double) self::get( $key, $default_value ); // phpcs:ignore WordPress.PHP.TypeCasts.DoubleRealFound
	}

	/**
	 * Returns boolean value.
	 *
	 * @param string $key           Key.
	 * @param bool   $default_value Default value.
	 *
	 * @return bool
	 */
	public static function get_boolean( $key, $default_value = false ) {
		return Util_Environment::to_boolean( self::get( $key, $default_value ) );
	}

	/**
	 * Returns array value.
	 *
	 * @param string $key           Key.
	 * @param array  $default_value Default value.
	 *
	 * @return array
	 */
	public static function get_array( $key, $default_value = array() ) {
		$value = self::get( $key );

		if ( is_array( $value ) ) {
			return $value;
		} elseif ( ! empty( $value ) ) {
			return preg_split( "/[\r\n,;]+/", trim( $value ) );
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

		foreach ( $request as $key => $value ) {
			if ( strpos( $key, $prefix ) === 0 || strpos( $key, str_replace( '.', '_', $prefix ) ) === 0 ) {
				$array[ substr( $key, strlen( $prefix ) ) ] = $value;
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
