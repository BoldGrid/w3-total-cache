<?php
/**
 * File: dynamic-late-init.php
 *
 * Page cache: Dynamic late-init QA fixture.
 *
 * Registers a `qa_multiply` callback against the `w3tc_dynamic_callbacks`
 * filter (the registered-callback dispatcher) and emits a call:slug mfunc tag
 * from `wp_footer`. The
 * late-init test asserts on the product `969526` (= 484763 * 2) to confirm
 * pgcache's late-init mode re-processes cached buffers through
 * `_parse_dynamic` and dispatches the callback.
 *
 * Template Name: Page cache: Dynamic late init
 * Template Post Type: post, page
 *
 * @package W3TC
 * @subpackage QA
 */

add_filter(
	'w3tc_dynamic_callbacks',
	function ( $callbacks ) {
		$callbacks['qa_multiply'] = function ( $args ) {
			$a = isset( $args['a'] ) ? (int) $args['a'] : 0;
			$b = isset( $args['b'] ) ? (int) $args['b'] : 0;
			return (string) ( $a * $b );
		};
		return $callbacks;
	}
);

add_action(
	'wp_footer',
	function () {
		$token = defined( 'W3TC_DYNAMIC_SECURITY' ) ? W3TC_DYNAMIC_SECURITY : '';
		if ( '' === $token ) {
			return;
		}
		/**
		 * HMAC envelope is added at cache-write time by
		 * PgCache_ContentGrabber::_sign_dynamic_tags(); emitters MUST NOT
		 * compute it themselves.
		 */
		echo '<!-- mfunc ' . esc_html( $token ) . ' call:qa_multiply {"a":484763,"b":2} --><!-- /mfunc ' . esc_html( $token ) . ' -->';
	}
);
