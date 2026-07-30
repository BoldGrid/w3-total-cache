<?php
/**
 * File: BrowserCache_Core.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class BrowserCache_Core
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */
class BrowserCache_Core {
	/**
	 * Returns replace extensions
	 *
	 * @param Config $w3tc_config Config.
	 *
	 * @return array
	 */
	public function get_replace_extensions( $w3tc_config ) {
		$types      = array();
		$extensions = array();

		if ( $w3tc_config->get_boolean( 'browsercache.cssjs.replace' ) ) {
			$types = array_merge( $types, array_keys( $this->_get_cssjs_types() ) );
		}

		if ( $w3tc_config->get_boolean( 'browsercache.html.replace' ) ) {
			$types = array_merge( $types, array_keys( $this->_get_html_types() ) );
		}

		if ( $w3tc_config->get_boolean( 'browsercache.other.replace' ) ) {
			$types = array_merge( $types, array_keys( $this->_get_other_types() ) );
		}

		foreach ( $types as $type ) {
			$extensions = array_merge( $extensions, explode( '|', $type ) );
		}

		return $extensions;
	}

	/**
	 * Returns replace extensions
	 *
	 * @param Config $w3tc_config Config.
	 *
	 * @return array
	 */
	public function get_replace_querystring_extensions( $w3tc_config ) {
		$extensions = array();

		if ( $w3tc_config->get_boolean( 'browsercache.cssjs.replace' ) ) {
			$this->_fill_extensions( $extensions, $this->_get_cssjs_types(), 'replace' );
		}

		if ( $w3tc_config->get_boolean( 'browsercache.html.replace' ) ) {
			$this->_fill_extensions( $extensions, $this->_get_html_types(), 'replace' );
		}

		if ( $w3tc_config->get_boolean( 'browsercache.other.replace' ) ) {
			$this->_fill_extensions( $extensions, $this->_get_other_types(), 'replace' );
		}

		if ( $w3tc_config->get_boolean( 'browsercache.cssjs.querystring' ) ) {
			$this->_fill_extensions( $extensions, $this->_get_cssjs_types(), 'querystring' );
		}

		if ( $w3tc_config->get_boolean( 'browsercache.html.querystring' ) ) {
			$this->_fill_extensions( $extensions, $this->_get_html_types(), 'querystring' );
		}

		if ( $w3tc_config->get_boolean( 'browsercache.other.querystring' ) ) {
			$this->_fill_extensions( $extensions, $this->_get_other_types(), 'querystring' );
		}

		return $extensions;
	}

	/**
	 * Returns replace extensions
	 *
	 * @param array  $extensions Extensions.
	 * @param array  $types      Types.
	 * @param string $operation Operation.
	 *
	 * @return void
	 */
	private function _fill_extensions( &$extensions, $types, $operation ) {
		foreach ( array_keys( $types ) as $type ) {
			$type_extensions = explode( '|', $type );
			foreach ( $type_extensions as $w3tc_ext ) {
				if ( ! isset( $extensions[ $w3tc_ext ] ) ) {
					$extensions[ $w3tc_ext ] = array();
				}

				$extensions[ $w3tc_ext ][ $operation ] = true;
			}
		}
	}

	/**
	 * Returns CSS/JS mime types
	 *
	 * @return array
	 */
	private function _get_cssjs_types() {
		$mime_types = include W3TC_INC_DIR . '/mime/cssjs.php';
		return $mime_types;
	}

	/**
	 * Returns HTML mime types
	 *
	 * @return array
	 */
	private function _get_html_types() {
		$mime_types = include W3TC_INC_DIR . '/mime/html.php';
		return $mime_types;
	}

	/**
	 * Returns other mime types
	 *
	 * @return array
	 */
	private function _get_other_types() {
		$mime_types = include W3TC_INC_DIR . '/mime/other.php';
		return $mime_types;
	}
}
