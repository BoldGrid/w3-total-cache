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
		$client_id     = Util_Request::get_string( 'oa_client_id' );
		$refresh_token = Util_Request::get_string( 'oa_refresh_token' );

		$token_array  = array(
			'access_token' => Util_Request::get_string( 'oa_access_token' ),
			'token_type'   => Util_Request::get_string( 'oa_token_type' ),
			'expires_in'   => Util_Request::get_string( 'oa_expires_in' ),
			'created'      => Util_Request::get_string( 'oa_created' ),
		);
		$access_token = wp_json_encode( $token_array );

		$client = new \W3TCG_Google_Client();
		$client->setClientId( $client_id );
		$client->setAccessToken( $access_token );

		$service = new \W3TCG_Google_Service_Drive( $client );

		$items = $service->files->listFiles(
			array(
				'q' => "mimeType = 'application/vnd.google-apps.folder'",
			)
		);

		$folders = array();
		foreach ( $items as $item ) {
			if ( count( $item->parents ) > 0 && $item->parents[0]->isRoot ) {
				$folders[] = $item;
			}
		}

		include W3TC_DIR . '/Cdn_GoogleDrive_Popup_AuthReturn_View.php';
	}
}
