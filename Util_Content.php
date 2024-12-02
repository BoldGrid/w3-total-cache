<?php
/**
 * File: Util_Content.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Util_Content
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */
class Util_Content {
	/**
	 * Check if content is HTML
	 *
	 * @param string $content Content.
	 *
	 * @return boolean
	 */
	public static function is_html( $content ) {
		$content = self::_is_html_prepare( $content );
		return stripos( $content, '<html' ) === 0 || stripos( $content, '<!DOCTYPE' ) === 0;
	}

	/**
	 * Check if content is HTML or XML
	 *
	 * @param string $content Content.
	 *
	 * @return boolean
	 */
	public static function is_html_xml( $content ) {
		$content = self::_is_html_prepare( $content );
		return stripos( $content, '<?xml' ) === 0 || stripos( $content, '<html' ) === 0 || stripos( $content, '<!DOCTYPE' ) === 0;
	}

	/**
	 * Prepare HTML
	 *
	 * @param string $content Content.
	 *
	 * @return string
	 */
	private static function _is_html_prepare( $content ) {
		if ( strlen( $content ) > 1000 ) {
			$content = substr( $content, 0, 1000 );
		}

		if ( strstr( $content, '<!--' ) !== false ) {
			$content = preg_replace( '~<!--.*?-->~s', '', $content );
		}

		$content = ltrim( $content, "\x00\x09\x0A\x0D\x20\xBB\xBF\xEF" );

		return $content;
	}

	/**
	 * If content can handle HTML comments, can disable printout per request using filter 'w3tc_can_print_comment'
	 *
	 * @param unknown $buffer Buffer.
	 *
	 * @return bool
	 */
	public static function can_print_comment( $buffer ) {
		if ( function_exists( 'apply_filters' ) ) {
			return apply_filters( 'w3tc_can_print_comment', self::is_html_xml( $buffer ) && ! defined( 'DOING_AJAX' ) );
		}

		return self::is_html_xml( $buffer ) && ! defined( 'DOING_AJAX' );
	}

	/**
	 * Returns GMT date
	 *
	 * @param integer $time Time.
	 *
	 * @return string
	 */
	public static function http_date( $time ) {
		return gmdate( 'D, d M Y H:i:s \G\M\T', $time );
	}

	/**
	 * Escapes HTML comment
	 *
	 * @param string $comment Comment.
	 *
	 * @return mixed
	 */
	public static function escape_comment( $comment ) {
		while ( strstr( $comment, '--' ) !== false ) {
			$comment = str_replace( '--', '- -', $comment );
		}

		return $comment;
	}

	/**
	 * Deprecated. Added to prevent loading-order errors during upgrades
	 * from older w3tc plugin versions
	 *
	 * @return bool
	 */
	public static function is_database_error() {
		return false;
	}

	/**
	 * Converts
	 * 127.0.0.1:1234 to ( '123.0.0.1', 1234 )
	 * tls://127.0.0.1:1234 to ( 'tls://123.0.0.1', 1234 )
	 * unix:/my/pipe to ( 'unix:/my/pipe', 0 )
	 *
	 * Doesnt fit to that class perfectly but selected due to common usage
	 * of loaded classes
	 *
	 * @param string $server       Server.
	 * @param int    $port_default Port default.
	 *
	 * @return array
	 */
	public static function endpoint_to_host_port( $server, $port_default = 0 ) {
		$p = strrpos( $server, ':' );
		if ( 'unix:' === substr( $server, 0, 5 ) || false === $p ) {
			return array( trim( $server ), $port_default );
		}

		return array(
			trim( substr( $server, 0, $p ) ),
			(int) substr( $server, $p + 1 ),
		);
	}
}
