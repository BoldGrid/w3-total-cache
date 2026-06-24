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
		if ( ! \current_user_can( 'manage_options' ) ) {
			wp_die(
				\esc_html__( 'You do not have sufficient permissions to perform this action.', 'w3-total-cache' ),
				'',
				array( 'response' => 403 )
			);
		}
		/**
		 * RT9-233: Validate the session-bound OAuth state token before
		 * rendering the folder picker. The popup URL is reachable to
		 * anyone holding the current admin's session cookie via a
		 * crafted `?oa_*=...` payload — without state validation,
		 * attacker-supplied OAuth tokens would silently be queried
		 * against attacker-owned Google Drive accounts and displayed
		 * as the admin's folder list. See Cdn_GoogleDrive_OAuthState.
		 */
		if ( ! Cdn_GoogleDrive_OAuthState::verify( Util_Request::get_string( Cdn_GoogleDrive_OAuthState::STATE_PARAM ) ) ) {
			wp_die(
				\esc_html__( 'OAuth state token missing or expired — restart the Google Drive authorization flow.', 'w3-total-cache' ),
				'',
				array( 'response' => 403 )
			);
		}
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
		/**
		 * Hard admin gate: only manage_options users may bind a Google
		 * Drive OAuth token to the site CDN config.
		 *
		 * @since 2.10.0
		 */
		if ( ! \current_user_can( 'manage_options' ) ) {
			wp_die(
				\esc_html__( 'You do not have sufficient permissions to perform this action.', 'w3-total-cache' ),
				'',
				array( 'response' => 403 )
			);
		}

		/**
		 * RT9-233: OAuth state-to-session binding. The hidden state
		 * input rendered by Cdn_GoogleDrive_Popup_AuthReturn_View must
		 * match the token this admin's session issued at authorize-
		 * link-click time. Without this gate, an admin tricked into
		 * loading a crafted `?oa_*` URL would submit attacker-supplied
		 * Google credentials into `cdn.google_drive.*` config and the
		 * site would upload its CDN payload to the attacker's Drive.
		 *
		 * Single-use: consume the transient after a successful match
		 * so the same state cannot replay a second config write.
		 *
		 * @since 2.10.0
		 */
		if ( ! Cdn_GoogleDrive_OAuthState::verify( Util_Request::get_string( Cdn_GoogleDrive_OAuthState::STATE_PARAM ) ) ) {
			wp_die(
				\esc_html__( 'OAuth state token missing or expired — restart the Google Drive authorization flow.', 'w3-total-cache' ),
				'',
				array( 'response' => 403 )
			);
		}
		Cdn_GoogleDrive_OAuthState::consume();

		// thanks wp core for wp_magic_quotes hell.
		$client_id          = Util_Request::get_string( 'client_id' );
		$w3tc_access_token  = Util_Request::get_string( 'access_token' );
		$w3tc_refresh_token = Util_Request::get_string( 'refresh_token' );

		$client = new \W3TCG_Google_Client();
		$client->setClientId( $client_id );
		$client->setAccessToken( $w3tc_access_token );

		// get folder details.
		$w3tc_service = new \W3TCG_Google_Service_Drive( $client );

		if ( empty( Util_Request::get_string( 'folder' ) ) ) {
			$w3tc_file = new \W3TCG_Google_Service_Drive_DriveFile(
				array(
					'title'    => Util_Request::get_string( 'folder_new' ),
					'mimeType' => 'application/vnd.google-apps.folder',
				)
			);

			$created_file   = $w3tc_service->files->insert( $w3tc_file );
			$used_folder_id = $created_file->id;
		} else {
			$used_folder_id = Util_Request::get_string( 'folder' );
		}

		$permission = new \W3TCG_Google_Service_Drive_Permission();
		$permission->setValue( '' );
		$permission->setType( 'anyone' );
		$permission->setRole( 'reader' );

		$w3tc_service->permissions->insert( $used_folder_id, $permission );

		$used_folder = $w3tc_service->files->get( $used_folder_id );

		// save new configuration.
		delete_transient( 'w3tc_cdn_google_drive_folder_ids' );
		$this->_config->set( 'cdn.google_drive.client_id', $client_id );
		$this->_config->set( 'cdn.google_drive.refresh_token', $w3tc_refresh_token );
		$this->_config->set( 'cdn.google_drive.folder.id', $used_folder->id );
		$this->_config->set( 'cdn.google_drive.folder.title', $used_folder->title );
		$this->_config->set( 'cdn.google_drive.folder.url', $used_folder->webViewLink ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$this->_config->save();

		$cs = Dispatcher::config_state();
		$cs->set( 'cdn.google_drive.access_token', $w3tc_access_token );
		$cs->save();

		wp_safe_redirect( 'admin.php?page=w3tc_cdn', false );
	}
}
