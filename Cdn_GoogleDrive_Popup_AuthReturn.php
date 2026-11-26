<?php
/**
 * File: Cdn_GoogleDrive_Popup_AuthReturn.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_GoogleDrive_Popup_AuthReturn
 */
class Cdn_GoogleDrive_Popup_AuthReturn {
	/**
	 * Renders the Google Drive authorization return view.
	 *
	 * Loads OAuth return values from the user-bound store, sets the access token
	 * for a new Google client, and queries Google Drive for folders.
	 * The view receives the opaque handle and the state token.
	 *
	 * @return void
	 */
	public function render() {
		$oauth_handle = Cdn_GoogleDrive_OAuthTokens::request_string( Cdn_GoogleDrive_OAuthTokens::HANDLE_PARAM );
		$tokens       = Cdn_GoogleDrive_OAuthTokens::read( $oauth_handle );
		if ( ! Cdn_GoogleDrive_OAuthTokens::are_complete( $tokens ) ) {
			\wp_die(
				\esc_html__( 'Google Drive authorization expired. Restart the authorization flow.', 'w3-total-cache' ),
				'',
				array( 'response' => 403 )
			);
		}

		$client_id         = $tokens['oa_client_id'];
		$w3tc_access_token = Cdn_GoogleDrive_OAuthTokens::access_token_json( $tokens );
		$oauth_state       = Cdn_GoogleDrive_OAuthTokens::request_string( Cdn_GoogleDrive_OAuthState::STATE_PARAM );

		try {
			$client = new \W3TCG_Google_Client();
			$client->setClientId( $client_id );
			$client->setAccessToken( $w3tc_access_token );

			$w3tc_service = new \W3TCG_Google_Service_Drive( $client );

			$items = $w3tc_service->files->listFiles(
				array(
					'q' => "mimeType = 'application/vnd.google-apps.folder'",
				)
			);
		} catch ( \W3TCG_Google_Exception $ex ) {
			\wp_die(
				\esc_html__( 'Google Drive could not be reached with this authorization. Restart the authorization flow.', 'w3-total-cache' ),
				'',
				array( 'response' => 403 )
			);
		}

		$folders = array();
		foreach ( $items as $w3tc_item ) {
			if ( count( $w3tc_item->parents ) > 0 && $w3tc_item->parents[0]->isRoot ) {
				$folders[] = $w3tc_item;
			}
		}

		unset( $client_id, $w3tc_access_token, $tokens );

		include W3TC_DIR . '/Cdn_GoogleDrive_Popup_AuthReturn_View.php';
	}
}
