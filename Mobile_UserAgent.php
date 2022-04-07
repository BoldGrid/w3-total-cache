<?php
namespace W3TC;

/**
 * W3TC Mobile detection
 */

/**
 * class Mobile
 */
class Mobile_UserAgent extends Mobile_Base {
	/**
	 * PHP5-style constructor
	 */
	function __construct() {
		parent::__construct( 'mobile.rgroups', 'agents' );
	}

	function group_verifier( $group_compare_value ) {
		return preg_match( '~' . $group_compare_value . '~i', isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' );
	}
}
