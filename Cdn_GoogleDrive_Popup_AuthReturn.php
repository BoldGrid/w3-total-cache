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
	 * This method retrieves the client ID, refresh token, and access token from the request,
	 * sets the access token for a new Google client, and queries Google Drive for folders.
	 * It then filters the folders to include only those that are direct children of the root.
	 * Finally, it includes the view to display the results.
	 *
	 * @return void
	 */
	public function render() {
		$client_id          = Util_Request::get_string( 'oa_client_id' );
		$w3tc_refresh_token = Util_Request::get_string( 'oa_refresh_token' );

		$token_array       = array(
			'access_token' => Util_Request::get_string( 'oa_access_token' ),
			'token_type'   => Util_Request::get_string( 'oa_token_type' ),
			'expires_in'   => Util_Request::get_string( 'oa_expires_in' ),
			'created'      => Util_Request::get_string( 'oa_created' ),
		);
		$w3tc_access_token = wp_json_encode( $token_array );

		/**
		 * RT9-233: The AdminActions handler that invokes us
		 * (`w3tc_cdn_google_drive_auth_return`) has already validated
		 * the state token. Read the same value here so the view can
		 * emit it as a hidden form input — the subsequent
		 * `auth_set` POST then carries it back and gets re-validated
		 * at the config-write boundary.
		 */
		$oauth_state = Util_Request::get_string( Cdn_GoogleDrive_OAuthState::STATE_PARAM );

		$client = new \W3TCG_Google_Client();
		$client->setClientId( $client_id );
		$client->setAccessToken( $w3tc_access_token );

		$w3tc_service = new \W3TCG_Google_Service_Drive( $client );

		$items = $w3tc_service->files->listFiles(
			array(
				'q' => "mimeType = 'application/vnd.google-apps.folder'",
			)
		);

		$folders = array();
		foreach ( $items as $w3tc_item ) {
			if ( count( $w3tc_item->parents ) > 0 && $w3tc_item->parents[0]->isRoot ) {
				$folders[] = $w3tc_item;
			}
		}

		include W3TC_DIR . '/Cdn_GoogleDrive_Popup_AuthReturn_View.php';
	}
}
