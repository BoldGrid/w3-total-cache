<?php
/**
 * File: dynamic-mfunc-callback.php
 *
 * QA fixture: registers a `qa_multiply` callback in the
 * `w3tc_dynamic_callbacks` registry so the post-content mfunc tests can
 * exercise the registered-callback dispatcher introduced by the eval-rce
 * removal pass. The callback returns the product of two integers — the QA
 * suites assert on the exact product as the cache hit indicator.
 *
 * Installed as a must-use plugin by the dynamic-* QA tests under
 * `qa/tests/pagecache/` and `qa/tests/browsercache/`. The companion fixture
 * `dynamic-late-init.php` registers the same callback for the late-init
 * scenario AND emits a tag from `wp_footer`.
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
