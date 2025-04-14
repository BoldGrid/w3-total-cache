<?php
/**
 * File: Cdn_GoogleDrive_AdminActions.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_GoogleDrive_AdminActions
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class Cdn_GoogleDrive_AdminActions {
	/**
	 * Config.
	 *
	 * @var Config
	 */
	private $_config = null;

	/**
	 * Initializes the class and configures necessary settings.
	 *
	 * This constructor retrieves the configuration settings using the Dispatcher class
	 * and stores it in the class property for further use.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Handles the return from Google Drive authentication and renders the authentication view.
	 *
	 * This method is invoked after a user has completed the Google Drive authentication process.
	 * It initializes the `Cdn_GoogleDrive_Popup_AuthReturn` view, renders it, and then exits the script
	 * to prevent further execution.
	 *
	 * @return void
	 */
	public function w3tc_cdn_google_drive_auth_return() {
		$view = new Cdn_GoogleDrive_Popup_AuthReturn();
		$view->render();
		exit();
	}

	/**
	 * Sets up the Google Drive authentication and folder configuration.
	 *
	 * This method handles the Google Drive authorization by retrieving client details (e.g., client ID,
	 * access token, refresh token) from the request. It sets up the Google Client, requests folder details
	 * from the Google Drive API, creates a new folder if necessary, and saves the configuration to the plugin's settings.
	 * Additionally, it sets the folder permissions and redirects the user back to the CDN settings page.
	 *
	 * @return void
	 */
	public function w3tc_cdn_google_drive_auth_set() {
		// thanks wp core for wp_magic_quotes hell.
		$client_id     = Util_Request::get_string( 'client_id' );
		$access_token  = Util_Request::get_string( 'access_token' );
		$refresh_token = Util_Request::get_string( 'refresh_token' );

		$client = new \W3TCG_Google_Client();
		$client->setClientId( $client_id );
		$client->setAccessToken( $access_token );

		// get folder details.
		$service = new \W3TCG_Google_Service_Drive( $client );

		if ( empty( Util_Request::get_string( 'folder' ) ) ) {
			$file = new \W3TCG_Google_Service_Drive_DriveFile(
				array(
					'title'    => Util_Request::get_string( 'folder_new' ),
					'mimeType' => 'application/vnd.google-apps.folder',
				)
			);

			$created_file   = $service->files->insert( $file );
			$used_folder_id = $created_file->id;
		} else {
			$used_folder_id = Util_Request::get_string( 'folder' );
		}

		$permission = new \W3TCG_Google_Service_Drive_Permission();
		$permission->setValue( '' );
		$permission->setType( 'anyone' );
		$permission->setRole( 'reader' );

		$service->permissions->insert( $used_folder_id, $permission );

		$used_folder = $service->files->get( $used_folder_id );

		// save new configuration.
		delete_transient( 'w3tc_cdn_google_drive_folder_ids' );
		$this->_config->set( 'cdn.google_drive.client_id', $client_id );
		$this->_config->set( 'cdn.google_drive.refresh_token', $refresh_token );
		$this->_config->set( 'cdn.google_drive.folder.id', $used_folder->id );
		$this->_config->set( 'cdn.google_drive.folder.title', $used_folder->title );
		$this->_config->set( 'cdn.google_drive.folder.url', $used_folder->webViewLink ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$this->_config->save();

		$cs = Dispatcher::config_state();
		$cs->set( 'cdn.google_drive.access_token', $access_token );
		$cs->save();

		wp_safe_redirect( 'admin.php?page=w3tc_cdn', false );
	}
}
