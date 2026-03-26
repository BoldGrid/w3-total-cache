<?php
/**
 * File: Extension_NewRelic_Popup.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_NewRelic_Popup
 *
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class Extension_NewRelic_Popup {
	/**
	 * Registers AJAX actions for New Relic integration.
	 *
	 * @return void
	 */
	public static function w3tc_ajax() {
		$o = new Extension_NewRelic_Popup();

		add_action( 'w3tc_ajax_newrelic_popup', array( $o, 'w3tc_ajax_newrelic_popup' ) );
		add_action( 'w3tc_ajax_newrelic_list_applications', array( $o, 'w3tc_ajax_newrelic_list_applications' ) );
		add_action( 'w3tc_ajax_newrelic_apply_configuration', array( $o, 'w3tc_ajax_newrelic_apply_configuration' ) );
	}

	/**
	 * AJAX handler for the intro view for New Relic popup.
	 *
	 * @return void
	 */
	public function w3tc_ajax_newrelic_popup() {
		$c = Dispatcher::config();

		$this->render_intro(
			array(
				'api_key' => $c->get_string( array( 'newrelic', 'api_key' ) ),
			)
		);
	}

	/**
	 * Renders the intro view for New Relic popup.
	 *
	 * @param array $details New Relic details array.
	 *
	 * @return void
	 */
	private function render_intro( $details ) {
		include W3TC_DIR . '/Extension_NewRelic_Popup_View_Intro.php';
	}

	/**
	 * Renders the list of New Relic applications based on the provided API key.
	 *
	 * @return void
	 *
	 * @throws \Exception If there is an issue with the New Relic API request.
	 */
	public function w3tc_ajax_newrelic_list_applications() {
		$api_key = Util_Request::get_string( 'api_key' );
		$c       = Dispatcher::config();
		$details = array(
			'api_key'                => $api_key,
			'monitoring_type'        => $c->get_string( array( 'newrelic', 'monitoring_type' ) ),
			'apm.application_name'   => $c->get_string( array( 'newrelic', 'apm.application_name' ) ),
			'browser.application_id' => $c->get_string( array( 'newrelic', 'browser.application_id' ) ),
		);

		$service = new Extension_NewRelic_Service( $api_key );
		try {
			$api                             = new Extension_NewRelic_Api( $api_key );
			$details['apm_applications']     = array_values(
				array_filter(
					$service->get_applications(),
					static function ( $name ) {
						if ( ! is_string( $name ) ) {
							return false;
						}

						$trimmed = trim( $name );
						return '' !== $trimmed && 'null' !== strtolower( $trimmed );
					}
				)
			);
			$apm_names_lower = array_map( 'strtolower', $details['apm_applications'] );
			$details['browser_applications'] = array_values(
				array_filter(
					$api->get_browser_applications(),
					function ( $app ) use ( $apm_names_lower ) {
						if ( ! is_array( $app ) ) {
							return false;
						}

						$name     = isset( $app['name'] ) ? trim( (string) $app['name'] ) : '';
						$has_id   = isset( $app['id'] ) && '' !== (string) $app['id'];
						$has_name = '' !== $name && 'null' !== strtolower( $name );
						$has_key  = isset( $app['browser_monitoring_key'] ) && '' !== trim( (string) $app['browser_monitoring_key'] );

						// Only treat real Browser apps (they expose a browser monitoring key).
						// If an APM app shares the same name and no separate Browser app exists, skip it.
						$is_duplicate_apm = in_array( strtolower( $name ), $apm_names_lower, true );

						return $has_id && $has_name && $has_key && ! $is_duplicate_apm;
					}
				)
			);
		} catch ( \Exception $ex ) {
			$details = array(
				'api_key'       => $api_key,
				'error_message' => 'API key verification failed: ' . $ex->getMessage(),
			);
			$this->render_intro( $details );
			return;
		}

		// Clear selection if the stored monitoring type has no available apps.
		$monitoring_type = $details['monitoring_type'];
		if ( 'apm' === $monitoring_type && empty( $details['apm_applications'] ) ) {
			$details['monitoring_type'] = '';
		} elseif ( 'browser' === $monitoring_type && empty( $details['browser_applications'] ) ) {
			$details['monitoring_type'] = '';
		} elseif ( 'apm' !== $monitoring_type && 'browser' !== $monitoring_type ) {
			$details['monitoring_type'] = '';
		}

		$details['browser_disabled'] = ! Util_Environment::is_w3tc_pro( $c );

		include W3TC_DIR . '/Extension_NewRelic_Popup_View_ListApplications.php';
	}

	/**
	 * Applies New Relic configuration settings from the request and updates the configuration.
	 *
	 * @return void
	 */
	public function w3tc_ajax_newrelic_apply_configuration() {
		$api_key                = Util_Request::get_string( 'api_key' );
		$monitoring_type        = Util_Request::get_string( 'monitoring_type', 'apm' );
		$apm_application_name   = Util_Request::get_string( 'apm_application_name' );
		$browser_application_id = Util_Request::get_string( 'browser_application_id' );
		$c                      = Dispatcher::config();

		$c->set( array( 'newrelic', 'api_key' ), $api_key );

		if ( 'apm' === $monitoring_type ) {
			$c->set( array( 'newrelic', 'monitoring_type' ), 'apm' );
			$c->set( array( 'newrelic', 'apm.application_name' ), $apm_application_name );
		} else {
			$c->set( array( 'newrelic', 'monitoring_type' ), 'browser' );
			$c->set( array( 'newrelic', 'browser.application_id' ), $browser_application_id );
		}

		// Resolve and persist account id when available.
		try {
			$service    = new Extension_NewRelic_Service( $api_key );
			$account_id = $service->get_account_id();
			if ( $account_id ) {
				$c->set( array( 'newrelic', 'account_id' ), $account_id );
			}
		} catch ( \Exception $ignored ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		}

		$c->save();

		// flush cached values on api key change to allow to reset it from ui if something goes wrong.
		update_option( 'w3tc_nr_account_id', '' );
		update_option( 'w3tc_nr_application_id', '' );

		$postfix = Util_Admin::custom_message_id(
			array(),
			array(
				'newrelic_configuration_saved' => 'NewRelic configuration is saved successfully',
			)
		);

		// Note: this response is parsed by JS (see `pub/js/lightbox.js`) and assigned to `window.location`.
		// Do NOT use `esc_url()` here (it HTML-entity encodes `&` into `&#038;`, which becomes a broken fragment).
		echo 'Location ' . \esc_url_raw( 'admin.php?page=w3tc_general&' . $postfix );
	}
}
