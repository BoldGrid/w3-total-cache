<?php
/**
 * File: Cdnfsd_LimeLight_Popup.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdnfsd_LimeLight_Popup
 */
class Cdnfsd_LimeLight_Popup {
	/**
	 * Initializes the AJAX actions for the LimeLight popup.
	 *
	 * @return void
	 */
	public static function w3tc_ajax() {
		$o = new Cdnfsd_LimeLight_Popup();

		add_action( 'w3tc_ajax_cdnfsd_limelight_intro', array( $o, 'w3tc_ajax_cdnfsd_limelight_intro' ) );
		add_action( 'w3tc_ajax_cdnfsd_limelight_save', array( $o, 'w3tc_ajax_cdnfsd_limelight_save' ) );
	}

	/**
	 * Handles the AJAX request for the LimeLight intro.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdnfsd_limelight_intro() {
		$this->render_intro( array() );
	}

	/**
	 * Renders the intro view for the LimeLight popup.
	 *
	 * @param array $details Details for the intro view.
	 *
	 * @return void
	 */
	private function render_intro( $details ) {
		$config = Dispatcher::config();

		include W3TC_DIR . '/Cdnfsd_LimeLight_Popup_View_Intro.php';
		exit();
	}

	/**
	 * Handles the AJAX request for saving LimeLight settings.
	 *
	 * @return void
	 *
	 * @throws \Exception If the API purge request fails.
	 */
	public function w3tc_ajax_cdnfsd_limelight_save() {
		$short_name = Util_Request::get_string( 'short_name' );
		$username   = Util_Request::get_string( 'username' );
		$api_key    = Util_Request::get_string( 'api_key' );

		try {
			$api = new Cdnfsd_LimeLight_Api( $short_name, $username, $api_key );
			$url = Util_Environment::home_domain_root_url() . '/';

			$items = array(
				array(
					'pattern' => $url,
					'exact'   => true,
					'evict'   => false,
					'incqs'   => false,
				),
			);

			$api->purge( $items );
		} catch ( \Exception $ex ) {
			$this->render_intro(
				array(
					'error_message' => 'Failed to make test purge request: ' . $ex->getMessage(),
				)
			);
			exit();
		}

		$c = Dispatcher::config();
		$c->set( 'cdnfsd.limelight.short_name', $short_name );
		$c->set( 'cdnfsd.limelight.username', $username );
		$c->set( 'cdnfsd.limelight.api_key', $api_key );
		$c->save();

		include W3TC_DIR . '/Cdnfsd_LimeLight_Popup_View_Success.php';
		exit();
	}
}
