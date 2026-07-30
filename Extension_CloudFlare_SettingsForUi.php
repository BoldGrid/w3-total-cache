<?php
/**
 * File: Extension_CloudFlare_SettingsForUi.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_CloudFlare_SettingsForUi
 */
class Extension_CloudFlare_SettingsForUi {
	/**
	 * Retrieves the Cloudflare API object with the configuration parameters.
	 *
	 * @return \Extension_CloudFlare_Api The API object for interacting with Cloudflare.
	 */
	public static function api() {
		$w3tc_c = Dispatcher::config();
		$api    = new Extension_CloudFlare_Api(
			array(
				'email'                 => $w3tc_c->get_string( array( 'cloudflare', 'email' ) ),
				'key'                   => $w3tc_c->get_string( array( 'cloudflare', 'key' ) ),
				'zone_id'               => $w3tc_c->get_string( array( 'cloudflare', 'zone_id' ) ),
				'timelimit_api_request' => $w3tc_c->get_integer(
					array( 'cloudflare', 'timelimit.api_request' )
				),
			)
		);

		return $api;
	}

	/**
	 * Retrieves and adjusts the Cloudflare settings.
	 *
	 * @param \Extension_CloudFlare_Api $api The Cloudflare API object.
	 *
	 * @return array The modified Cloudflare settings.
	 */
	public static function settings_get( $api ) {
		$w3tc_settings = $api->zone_settings();

		// adjust settings that are out of regular presentation.
		if ( isset( $w3tc_settings['security_header'] ) ) {
			$v = $w3tc_settings['security_header']['value'];

			$w3tc_settings['security_header']['editable'] = false;
			$w3tc_settings['security_header']['value']    = 'off';
			if ( isset( $v['strict_transport_security']['enabled'] ) ) {
				$w3tc_settings['security_header']['value'] = $v['strict_transport_security']['enabled'] ? 'on' : 'off';
			}
		}
		if ( isset( $w3tc_settings['mobile_redirect'] ) ) {
			$v = $w3tc_settings['mobile_redirect']['value'];

			$w3tc_settings['mobile_redirect']['editable'] = false;
			$w3tc_settings['mobile_redirect']['value']    = 'off';
			if ( isset( $v['status'] ) ) {
				$w3tc_settings['mobile_redirect']['value'] = $v['status'] ? 'on' : 'off';
			}
		}
		if ( isset( $w3tc_settings['minify'] ) ) {
			$v = $w3tc_settings['minify']['value'];

			$editable                     = $w3tc_settings['minify']['editable'];
			$w3tc_settings['minify_js']   = array(
				'editable' => $editable,
				'value'    => $v['js'],
			);
			$w3tc_settings['minify_css']  = array(
				'editable' => $editable,
				'value'    => $v['css'],
			);
			$w3tc_settings['minify_html'] = array(
				'editable' => $editable,
				'value'    => $v['html'],
			);
		}

		return $w3tc_settings;
	}

	/**
	 * Updates the Cloudflare settings based on the request.
	 *
	 * phpcs:disable WordPress.Security.NonceVerification.Recommended
	 *
	 * @param \Extension_CloudFlare_Api $api The Cloudflare API object.
	 *
	 * @return array List of error messages encountered during the settings update.
	 *
	 * @throws \Exception If there is an error during the API request.
	 */
	public static function settings_set( $api ) {
		$errors        = array();
		$w3tc_settings = self::settings_get( $api );
		$to_update     = array();

		$prefix = 'cloudflare_api_';
		foreach ( $_REQUEST as $w3tc_key => $w3tc_value ) {
			if ( substr( $w3tc_key, 0, strlen( $prefix ) ) !== $prefix ) {
				continue;
			}

			if ( ! isset( $w3tc_value ) ) {
				continue;
			}

			$w3tc_value = Util_Request::get_string( $w3tc_key );

			$settings_key = substr( $w3tc_key, strlen( $prefix ) );

			if ( ! isset( $w3tc_settings[ $settings_key ] ) ) {
				$errors[] = 'Option ' . $settings_key . ' is not available';
				continue;
			}

			$current_value = $w3tc_settings[ $settings_key ]['value'];

			// convert checkbox value to on/off
			// exception: rocket loader, ssl is not checkbox so contains real value.
			if (
				'rocket_loader' !== $settings_key &&
				'ssl' !== $settings_key &&
				(
					'on' === $current_value ||
					'off' === $current_value
				)
			) {
				// it's boolean, so control is checkbox - convert it.
				$w3tc_value = ( '0' === $w3tc_value ? 'off' : 'on' );
			}

			if ( $current_value === $w3tc_value ) {
				continue; // no update required.
			}

			if ( ! $w3tc_settings[ $settings_key ]['editable'] ) {
				$errors[] = 'Option ' . $settings_key . ' is read-only';
				continue;
			}

			$to_update[ $settings_key ] = $w3tc_value;
		}

		// mutate settings back to the format of API.
		if (
			isset( $to_update['minify_js'] ) ||
			isset( $to_update['minify_css'] ) ||
			isset( $to_update['minify_html'] )
		) {
			$v = $w3tc_settings['minify']['value'];
			if ( isset( $to_update['minify_js'] ) ) {
				$v['js'] = $to_update['minify_js'];
				unset( $to_update['minify_js'] );
			}

			if ( isset( $to_update['minify_css'] ) ) {
				$v['css'] = $to_update['minify_css'];
				unset( $to_update['minify_css'] );
			}

			if ( isset( $to_update['minify_html'] ) ) {
				$v['html'] = $to_update['minify_html'];
				unset( $to_update['minify_html'] );
			}

			$to_update['minify'] = $v;
		}

		// do the settings update via API.
		foreach ( $to_update as $w3tc_key => $w3tc_value ) {
			try {
				$api->zone_setting_set( $w3tc_key, $w3tc_value );
			} catch ( \Exception $ex ) {
				$errors[] = 'Failed to update option ' . $w3tc_key . ': ' .
					$ex->getMessage();
			}
		}

		return $errors;
	}
}
