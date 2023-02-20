<?php
/**
 * File: Utils.php
 *
 * @package W3TC
 */

/**
 * Copyright 2011 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Collection of static utility methods used for convenience across
 * the client library.
 *
 * @author Chirag Shah <chirags@google.com>
 */
class W3TCG_Google_Utils {
	/**
	 * URL safe base 64 encoder function
	 *
	 * @param string $data URL to be base 64 encoded.
	 *
	 * @return string
	 */
	public static function urlSafeB64Encode( $data ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$b64 = base64_encode( $data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$b64 = str_replace(
			array( '+', '/', '\r', '\n', '=' ),
			array( '-', '_' ),
			$b64
		);
		return $b64;
	}

	/**
	 * URL safe base 64 de-coder function
	 *
	 * @param string $b64 Base 64 encoded string.
	 *
	 * @return string
	 */
	public static function urlSafeB64Decode( $b64 ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$b64 = str_replace(
			array( '-', '_' ),
			array( '+', '/' ),
			$b64
		);
		return base64_decode( $b64 ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	}

	/**
	 * Misc function used to count the number of bytes in a post body, in the
	 * world of multi-byte chars and the unpredictability of
	 * strlen/mb_strlen/sizeof, this is the only way to do that in a sane
	 * manner at the moment.
	 *
	 * This algorithm was originally developed for the
	 * Solar Framework by Paul M. Jones
	 *
	 * @link   http://solarphp.com/
	 * @link   http://svn.solarphp.com/core/trunk/Solar/Json.php
	 * @link   http://framework.zend.com/svn/framework/standard/trunk/library/Zend/Json/Decoder.php
	 * @param  string $str String to get length of.
	 * @return int The number of bytes in a string.
	 */
	public static function getStrLen( $str ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$strlen_var = strlen( $str );
		$ret        = 0;
		for ( $count = 0; $count < $strlen_var; ++ $count ) {
			$ordinal_value = ord( $str[ $ret ] );
			switch ( true ) {
				case ( ( $ordinal_value >= 0x20 ) && ( $ordinal_value <= 0x7F ) ):
					// characters U-00000000 - U-0000007F (same as ASCII).
					$ret ++;
					break;
				case ( ( $ordinal_value & 0xE0 ) === 0xC0 ):
					// characters U-00000080 - U-000007FF, mask 110XXXXX.
					// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8.
					$ret += 2;
					break;
				case ( ( $ordinal_value & 0xF0 ) === 0xE0 ):
					// characters U-00000800 - U-0000FFFF, mask 1110XXXX.
					// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8.
					$ret += 3;
					break;
				case ( ( $ordinal_value & 0xF8 ) === 0xF0 ):
					// characters U-00010000 - U-001FFFFF, mask 11110XXX.
					// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8.
					$ret += 4;
					break;
				case ( ( $ordinal_value & 0xFC ) === 0xF8 ):
					// characters U-00200000 - U-03FFFFFF, mask 111110XX.
					// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8.
					$ret += 5;
					break;
				case ( ( $ordinal_value & 0xFE ) === 0xFC ):
					// characters U-04000000 - U-7FFFFFFF, mask 1111110X.
					// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8.
					$ret += 6;
					break;
				default:
					$ret ++;
			}
		}

		return $ret;
	}

	/**
	 * Normalize all keys in an array to lower-case.
	 *
	 * @param array $arr Array to normalize all keys to lowercase.
	 * @return array Normalized array.
	 */
	public static function normalize( $arr ) {
		if ( ! is_array( $arr ) ) {
			return array();
		}
		$normalized = array();
		foreach ( $arr as $key => $val ) {
			$normalized[ strtolower( $key ) ] = $val;
		}
		return $normalized;
	}

	/**
	 * Convert a string to camelCase
	 *
	 * @param  string $value String to convert to camelCase.
	 * @return string
	 */
	public static function camelCase( $value ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$value    = ucwords( str_replace( array( '-', '_' ), ' ', $value ) );
		$value    = str_replace( ' ', '', $value );
		$value[0] = strtolower( $value[0] );
		return $value;
	}
}
