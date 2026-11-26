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
	 * data with the authorization URL. After an OAuth return has been stored, the folder-picker popup URL carries
	 * the opaque handle rather than the oa_* parameters.
	 *
	 * @return void
	 */
	public static function admin_print_scripts_w3tc_cdn() {
		\wp_enqueue_script(
			'w3tc_cdn_google_drive',
			\plugins_url( 'Cdn_GoogleDrive_Page_View.js', W3TC_FILE ),
			array( 'jquery' ),
			'1.0',
			false
		);

		/**
		 * A continuation already has a state token. Issuing another one
		 * would replace it before the auth-set POST can verify it.
		 */
		$popup_url = self::auth_return_popup_url();
		if ( '' !== $popup_url ) {
			$state = Cdn_GoogleDrive_OAuthTokens::request_string( Cdn_GoogleDrive_OAuthState::STATE_PARAM );
		} else {
			$state = Cdn_GoogleDrive_OAuthState::issue();
		}

		$path = 'admin.php?page=w3tc_cdn';
		if ( '' !== $state ) {
			$path .= '&' . Cdn_GoogleDrive_OAuthState::STATE_PARAM
				. '=' . \rawurlencode( $state );
		}
		$w3tc_return_url = Util_Ui::admin_url( $path );

		\wp_localize_script(
			'w3tc_cdn_google_drive',
			'w3tc_cdn_google_drive_url',
			array( W3TC_GOOGLE_DRIVE_AUTHORIZE_URL . '?return_url=' . \rawurlencode( $w3tc_return_url ) )
		);

		if ( '' === $popup_url ) {
			return;
		}

		\wp_localize_script(
			'w3tc_cdn_google_drive',
			'w3tc_cdn_google_drive_popup_url',
			array( $popup_url )
		);
	}

	/**
	 * Store OAuth return parameters and redirect to a token-free admin URL.
	 *
	 * Runs on admin_init, before the CDN screen is rendered.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	public static function maybe_redirect_oauth_return() {
		if ( \wp_doing_ajax() || ( \function_exists( 'wp_doing_cron' ) && \wp_doing_cron() ) ) {
			return;
		}

		$url = self::oauth_return_redirect_url();
		if ( '' === $url || \headers_sent() ) {
			return;
		}

		Util_Response::header( 'Referrer-Policy', 'no-referrer' );
		\wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Build the token-free URL for an OAuth return, storing values when the state token matches.
	 *
	 * @since 2.11.1
	 *
	 * @return string Admin URL, or '' when this request is not an OAuth return.
	 */
	public static function oauth_return_redirect_url() {
		if ( 'w3tc_cdn' !== Cdn_GoogleDrive_OAuthTokens::request_string( 'page' ) ) {
			return '';
		}
		if ( ! Cdn_GoogleDrive_OAuthTokens::request_has_oauth_parameters() ) {
			return '';
		}

		$clean = Util_Ui::admin_url( 'admin.php?page=w3tc_cdn' );
		if ( ! \current_user_can( 'manage_options' ) ) {
			return $clean;
		}

		$state = Cdn_GoogleDrive_OAuthTokens::request_string( Cdn_GoogleDrive_OAuthState::STATE_PARAM );
		if ( ! Cdn_GoogleDrive_OAuthState::verify( $state ) ) {
			return $clean;
		}

		$handle = Cdn_GoogleDrive_OAuthTokens::store( Cdn_GoogleDrive_OAuthTokens::from_request() );
		if ( '' === $handle ) {
			return $clean;
		}

		return \add_query_arg(
			array(
				Cdn_GoogleDrive_OAuthState::STATE_PARAM   => $state,
				Cdn_GoogleDrive_OAuthTokens::HANDLE_PARAM => $handle,
			),
			$clean
		);
	}

	/**
	 * Popup URL for the folder picker.
	 *
	 * The URL contains the state token and the opaque handle. It does not contain oa_* parameters.
	 *
	 * @since 2.11.1
	 *
	 * @return string Absolute admin URL, or '' when the handle is absent or does not resolve.
	 */
	public static function auth_return_popup_url() {
		$handle = Cdn_GoogleDrive_OAuthTokens::request_string( Cdn_GoogleDrive_OAuthTokens::HANDLE_PARAM );
		$state  = Cdn_GoogleDrive_OAuthTokens::request_string( Cdn_GoogleDrive_OAuthState::STATE_PARAM );
		if ( '' === $handle || '' === $state ) {
			return '';
		}
		if ( ! Cdn_GoogleDrive_OAuthState::verify( $state ) ) {
			return '';
		}

		$tokens = Cdn_GoogleDrive_OAuthTokens::read( $handle );
		if ( ! Cdn_GoogleDrive_OAuthTokens::are_complete( $tokens ) ) {
			return '';
		}

		$path  = Util_Nonce::admin_nonce_url( 'admin.php', 'w3tc_cdn_google_drive_auth_return' );
		$path .= '&page=w3tc_cdn&w3tc_cdn_google_drive_auth_return';
		$path .= '&' . Cdn_GoogleDrive_OAuthState::STATE_PARAM . '=' . \rawurlencode( $state );
		$path .= '&' . Cdn_GoogleDrive_OAuthTokens::HANDLE_PARAM . '=' . \rawurlencode( $handle );

		return Util_Ui::admin_url( $path );
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
