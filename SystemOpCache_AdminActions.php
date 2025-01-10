<?php
/**
 * File: SystemOpCache_AdminActions.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class SystemOpCache_AdminActions
 */
class SystemOpCache_AdminActions {
	/**
	 * Flushes the OPCache and redirects with a success or error message.
	 *
	 * This method is designed to clear the PHP OPCache to ensure the latest
	 * PHP code changes are loaded and executed. It interacts with the
	 * `SystemOpCache_Core` component to perform the flush operation and
	 * provides user feedback via admin redirect messages.
	 *
	 * @throws Exception If the `SystemOpCache_Core` component is unavailable
	 *                   or the flush operation encounters an unexpected error.
	 *
	 * @return void This method performs a redirect and does not return a value.
	 */
	public function w3tc_opcache_flush() {
		$core    = Dispatcher::component( 'SystemOpCache_Core' );
		$success = $core->flush();

		if ( $success ) {
			Util_Admin::redirect_with_custom_messages2(
				array(
					'notes' => array( 'OPCache was flushed successfully' ),
				),
				true
			);
		} else {
			Util_Admin::redirect_with_custom_messages2(
				array(
					'errors' => array( 'Failed to flush OPCache' ),
				),
				true
			);
		}
	}
}
