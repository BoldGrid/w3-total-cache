<?php
/**
 * File: Mobile_UserAgent.php
 *
 * W3TC Mobile detection.
 *
 * @package W3TC
 *
 * @subpackage QA
 */

namespace W3TC;

/**
 * Class Mobile_UserAgent
 */
class Mobile_UserAgent extends Mobile_Base {
	/**
	 * Constructs the Mobile_UserAgent object and initializes the parent constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct( 'mobile.rgroups', 'agents' );
	}

	/**
	 * Verifies if the given group_compare_value matches the User-Agent string.
	 *
	 * phpcs:disable WordPress.Security.ValidatedSanitizedInput
	 *
	 * @param string $group_compare_value The regex pattern to compare with the User-Agent string.
	 *
	 * @return bool True if the User-Agent matches the pattern, false otherwise.
	 */
	public function group_verifier( $group_compare_value ) {
		return preg_match(
			'~' . $group_compare_value . '~i',
			isset( $_SERVER['HTTP_USER_AGENT'] ) ? htmlspecialchars( $_SERVER['HTTP_USER_AGENT'] ) : ''
		);
	}
}
