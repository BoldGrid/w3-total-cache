<?php
/**
 * File: Extension_NewRelic_Core.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_NewRelic_Core
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */
class Extension_NewRelic_Core {
	/**
	 * Retrieves the effective browser application.
	 *
	 * @return array|null The browser application data if available, null otherwise.
	 *
	 * @throws \Exception If there is an issue fetching the browser application.
	 */
	public function get_effective_browser_application() {
		$c       = Dispatcher::config();
		$api_key = $c->get( array( 'newrelic', 'api_key' ) );
		$id      = $c->get( array( 'newrelic', 'browser.application_id' ) );

		if ( empty( $api_key ) || empty( $id ) ) {
			return null;
		}

		$applications_string = get_option( 'w3tc_nr_browser_applications' );
		$applications        = @json_decode( $applications_string, true );
		if ( ! is_array( $applications ) ) {
			$applications = array();
		}

		if ( isset( $applications[ $id ] ) ) {
			return $applications[ $id ];
		}

		try {
			$api = new Extension_NewRelic_Api( $api_key );
			$app = $api->get_browser_application( $id );

			if ( ! is_null( $app ) ) {
				$applications[ $id ] = $app;
				update_option( 'w3tc_nr_browser_applications', wp_json_encode( $applications ) );
			}

			return $app;
		} catch ( \Exception $ex ) {
			return null;
		}
	}
}
