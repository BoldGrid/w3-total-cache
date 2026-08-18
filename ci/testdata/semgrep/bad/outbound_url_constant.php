<?php
/**
 * Expected: w3tc-outbound-url-constant-allowlist.
 *
 * Reads a constant-overridable outbound URL and requests it without running the
 * host allowlist check first.
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Bad_Outbound_Consumer
 */
class Bad_Outbound_Consumer {
	/**
	 * Fetches the notice feed.
	 *
	 * @return array|\WP_Error
	 */
	private function fetch_notices() {
		$feed_url = \defined( 'W3TC_NOTICE_FEED' ) ? W3TC_NOTICE_FEED : '';

		return \wp_remote_get( $feed_url );
	}
}
