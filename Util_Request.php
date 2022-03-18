<?php
namespace W3TC;

/**
 * W3 Request object
 */

/**
 * class Request
 */
class Util_Request {
	/**
	 * Returns request value
	 *
	 * @param string  $key
	 * @param mixed   $default
	 * @return mixed
	 */
	static function get( $key, $default = null ) {
		$request = Util_Request::get_request();

		if ( isset( $request[$key] ) ) {
			$value = $request[$key];

			if ( defined( 'TEMPLATEPATH' ) ) {
				$value = Util_Environment::stripslashes( $value );
			}

			return is_array( $value ) ? map_deep( $value, 'sanitize_text_field' ) : sanitize_text_field( $value );
		}

		return $default;
	}

	/**
	 * Returns string value
	 *
	 * @param string  $key
	 * @param string  $default
	 * @param boolean $trim
	 * @return string
	 */
	static function get_string( $key, $default = '', $trim = true ) {
		$value = (string) Util_Request::get( $key, $default );

		return ( $trim ) ? trim( $value ) : $value;
	}

	static function get_label( $key, $default = '' ) {
		$v = self::get_string( $key, $default);
		return preg_replace('/[^A-Za-z0-9_\\-]/', '', $v);
	}

	/**
	 * Returns integer value
	 *
	 * @param string  $key
	 * @param integer $default
	 * @return integer
	 */
	static function get_integer( $key, $default = 0 ) {
		return (integer) Util_Request::get( $key, $default );
	}

	/**
	 * Returns double value
	 *
	 * @param string  $key
	 * @param double|float $default
	 * @return double
	 */
	static function get_double( $key, $default = 0. ) {
		return (double) Util_Request::get( $key, $default );
	}

	/**
	 * Returns boolean value
	 *
	 * @param string  $key
	 * @param boolean $default
	 * @return boolean
	 */
	static function get_boolean( $key, $default = false ) {
		return Util_Environment::to_boolean( Util_Request::get( $key, $default ) );
	}

	/**
	 * Returns array value
	 *
	 * @param string  $key
	 * @param array   $default
	 * @return array
	 */
	static function get_array( $key, $default = array() ) {
		$value = Util_Request::get( $key );

		if ( is_array( $value ) ) {
			return $value;
		} elseif ( $value != '' ) {
			return preg_split( "/[\r\n,;]+/", trim( $value ) );
		}

		return $default;
	}

	/**
	 * Returns array value
	 *
	 * @param string  $prefix
	 * @param array   $default
	 * @return array
	 */
	static function get_as_array( $prefix, $default = array() ) {
		$request = Util_Request::get_request();
		$array = array();
		foreach ( $request as $key => $value ) {
			if ( strpos( $key, $prefix ) === 0 || strpos( $key, str_replace( '.', '_', $prefix ) ) === 0 ) {
				$array[ substr( $key, strlen( $prefix ) ) ] = sanitize_text_field( $value );
			}
		}
		return $array;
	}

	/**
	 * Returns request array
	 *
	 * @return array
	 */
	static function get_request() {
		if ( !isset( $_GET ) ) {
			$_GET = array();
		}

		if ( !isset( $_POST ) ) {
			$_POST = array();
		}

		return array_merge( $_GET, $_POST );
	}
}
