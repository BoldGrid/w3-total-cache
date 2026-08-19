<?php
/**
 * Compliant: the constant-overridable outbound URL is allowlisted before use.
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Good_Outbound_Consumer
 */
class Good_Outbound_Consumer {
	/**
	 * Fetches the notice feed.
	 *
	 * @return array|\WP_Error|null
	 */
	private function fetch_notices() {
		$feed_url = \defined( 'W3TC_NOTICE_FEED' ) ? W3TC_NOTICE_FEED : '';
		if ( ! Util_Url::is_https_host_allowlisted( $feed_url, array( 'w3-edge.com' ), array( '.w3-edge.com' ) ) ) {
			return null;
		}

		return \wp_remote_get( $feed_url );
	}
}
