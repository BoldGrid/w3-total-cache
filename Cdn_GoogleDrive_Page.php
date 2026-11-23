<?php
/**
 * File: Cdn_GoogleDrive_Page.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdn_GoogleDrive_Page
 */
class Cdn_GoogleDrive_Page {
	/**
	 * Enqueues scripts for the Google Drive CDN integration in the WordPress admin.
	 *
	 * This method enqueues the JavaScript file necessary for the Google Drive CDN integration and localizes script
	 * data with authorization URLs and a return URL. It also handles the Google OAuth callback by passing relevant
	 * data through to the script.
	 *
	 * @return void
	 */
	public static function admin_print_scripts_w3tc_cdn() {
		wp_enqueue_script(
			'w3tc_cdn_google_drive',
			plugins_url( 'Cdn_GoogleDrive_Page_View.js', W3TC_FILE ),
			array( 'jquery' ),
			'1.0',
			false
		);

		/**
		 * RT9-233: Mint a session-bound state token and embed it in
		 * the OAuth return URL. The external w3-edge proxy preserves
		 * the return_url's query string when redirecting the browser
		 * back, so the token round-trips through to the callback. See
		 * Cdn_GoogleDrive_OAuthState for the threat model.
		 */
		$state = Cdn_GoogleDrive_OAuthState::issue();
		$path  = 'admin.php?page=w3tc_cdn';
		if ( '' !== $state ) {
			$path .= '&' . Cdn_GoogleDrive_OAuthState::STATE_PARAM
				. '=' . rawurlencode( $state );
		}
		$w3tc_return_url = self_admin_url( $path );

		wp_localize_script(
			'w3tc_cdn_google_drive',
			'w3tc_cdn_google_drive_url',
			array( W3TC_GOOGLE_DRIVE_AUTHORIZE_URL . '?return_url=' . rawurlencode( $w3tc_return_url ) )
		);

		// it's return from google oauth.
		if ( ! empty( Util_Request::get_string( 'oa_client_id' ) ) ) {
			/**
			 * RT9-233: Refuse to enqueue the auto-opening popup
			 * unless the submitted state token matches the one this
			 * session issued. Without this gate, an attacker who
			 * holds valid OAuth tokens for an attacker-owned Google
			 * Drive account can craft a URL with `oa_*` params and
			 * trick an authenticated admin into writing attacker
			 * credentials to `cdn.google_drive.*` config.
			 */
			$submitted_state = Util_Request::get_string( Cdn_GoogleDrive_OAuthState::STATE_PARAM );
			if ( ! Cdn_GoogleDrive_OAuthState::verify( $submitted_state ) ) {
				return;
			}

			$path = Util_Nonce::admin_nonce_url( 'admin.php', 'w3tc_cdn_google_drive_auth_return' ) .
				'&page=w3tc_cdn&w3tc_cdn_google_drive_auth_return';
			foreach ( $_GET as $w3tc_key => $w3tc_value ) { // phpcs:ignore
				if ( substr( $w3tc_key, 0, 3 ) === 'oa_' ) {
					$path .= '&' . rawurlencode( $w3tc_key ) . '=' . rawurlencode( Util_Request::get_string( $w3tc_key ) );
				}
			}
			/**
			 * RT9-233: Forward the validated state into the popup URL
			 * so the AuthReturn handler can re-validate independently
			 * (defense in depth — popup URL is reachable by URL alone
			 * and shouldn't trust the upstream Page-level check).
			 */
			$path .= '&' . Cdn_GoogleDrive_OAuthState::STATE_PARAM
				. '=' . rawurlencode( $submitted_state );

			$popup_url = self_admin_url( $path );

			wp_localize_script(
				'w3tc_cdn_google_drive',
				'w3tc_cdn_google_drive_popup_url',
				array( $popup_url )
			);

		}
	}

	/**
	 * Displays the configuration settings for the Google Drive CDN integration in the W3 Total Cache settings page.
	 *
	 * This method loads the Google Drive CDN settings section in the W3 Total Cache settings page by including a
	 * specific view file and passing configuration data.
	 *
	 * @return void
	 */
	public static function w3tc_settings_cdn_boxarea_configuration() {
		$w3tc_config = Dispatcher::config();
		require W3TC_DIR . '/Cdn_GoogleDrive_Page_View.php';
	}
}
