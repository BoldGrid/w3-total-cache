<?php
/**
 * File: Extension_CloudFlare_AdminActions.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_CloudFlare_AdminActions
 */
class Extension_CloudFlare_AdminActions {
	/**
	 * Flushes the Cloudflare cache.
	 *
	 * @return void
	 *
	 * @throws \Exception If the Cloudflare purge fails.
	 */
	public function w3tc_cloudflare_flush() {
		$c   = Dispatcher::config();
		$api = new Extension_CloudFlare_Api(
			array(
				'email'                 => $c->get_string( array( 'cloudflare', 'email' ) ),
				'key'                   => $c->get_string( array( 'cloudflare', 'key' ) ),
				'zone_id'               => $c->get_string( array( 'cloudflare', 'zone_id' ) ),
				'timelimit_api_request' => $c->get_integer( array( 'cloudflare', 'timelimit.api_request' ) ),
			)
		);

		try {
			$v = $api->purge();
		} catch ( \Exception $ex ) {
			Util_Admin::redirect_with_custom_messages2(
				array(
					'errors' => array(
						'cloudflare_flush' => __( 'Failed to purge Cloudflare cache: ', 'w3-total-cache' ) .
						$ex->getMessage(),
					),
				)
			);
			return;
		}

		Util_Admin::redirect_with_custom_messages2(
			array(
				'notes' => array(
					'cloudflare_flush' => __( 'Cloudflare cache successfully emptied.', 'w3-total-cache' ),
				),
			)
		);
	}

	/**
	 * Flushes all cache components except Cloudflare.
	 *
	 * @return void
	 */
	public function w3tc_cloudflare_flush_all_except_cf() {
		Dispatcher::component( 'CacheFlush' )->flush_all(
			array(
				'cloudflare' => 'skip',
			)
		);

		Util_Admin::redirect(
			array(
				'w3tc_note' => 'flush_all',
			),
			true
		);
	}
}
