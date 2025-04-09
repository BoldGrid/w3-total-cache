<?php
/**
 * File: Cdn_LimeLight_Popup.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_LimeLight_Popup
 *
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class Cdn_LimeLight_Popup {
	/**
	 * Handles AJAX requests for LimeLight CDN.
	 *
	 * This method is called to initialize the necessary actions for handling AJAX requests related to the LimeLight CDN functionality.
	 * It hooks the appropriate functions into the WordPress AJAX system to handle requests for displaying the introductory view
	 * and saving settings.
	 *
	 * @return void
	 */
	public static function w3tc_ajax() {
		$o = new Cdnfsd_LimeLight_Popup();

		add_action( 'w3tc_ajax_cdn_limelight_intro', array( $o, 'w3tc_ajax_cdn_limelight_intro' ) );
		add_action( 'w3tc_ajax_cdn_limelight_save', array( $o, 'w3tc_ajax_cdn_limelight_save' ) );
	}

	/**
	 * Handles the AJAX request to display the introductory view for LimeLight CDN.
	 *
	 * This method is triggered when an AJAX request to display the introduction page for LimeLight CDN is received.
	 * It renders the introductory page, potentially using configuration data passed to it.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_limelight_intro() {
		$this->render_intro( array() );
	}

	/**
	 * Renders the introductory page for LimeLight CDN.
	 *
	 * This private method renders the introductory page for LimeLight CDN using the provided configuration data.
	 * It retrieves the domain settings from the configuration and includes the view file that displays the introductory content.
	 *
	 * @param array $details Configuration details to be passed to the view (empty in this case).
	 *
	 * @return void
	 */
	private function render_intro( $details ) {
		$config  = Dispatcher::config();
		$domain  = '';
		$domains = $config->get_array( 'cdn.limelight.host.domains' );
		if ( count( $domains ) > 0 ) {
			$domain = $domains[0];
		}

		include W3TC_DIR . '/Cdn_LimeLight_Popup_View_Intro.php';
		exit();
	}

	/**
	 * Handles the AJAX request to save the LimeLight CDN settings.
	 *
	 * This method processes the form submission that contains LimeLight CDN configuration settings.
	 * It validates the input data, makes an API call to purge the cache on the LimeLight CDN, and saves the configuration settings.
	 * If there is an error during the purge request, it renders the introduction view with an error message.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_limelight_save() {
		$short_name = Util_Request::get_string( 'short_name' );
		$username   = Util_Request::get_string( 'username' );
		$api_key    = Util_Request::get_string( 'api_key' );
		$domain     = Util_Request::get_string( 'domain' );

		try {
			$api = new Cdnfsd_LimeLight_Api( $short_name, $username, $api_key );
			$url = ( Util_Environment::is_https() ? 'https://' : 'http://' ) . $domain . '/test';

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
		$c->set( 'cdn.limelight.short_name', $short_name );
		$c->set( 'cdn.limelight.username', $username );
		$c->set( 'cdn.limelight.api_key', $api_key );
		$c->set( 'cdn.limelight.host.domains', array( $domain ) );
		$c->save();

		include W3TC_DIR . '/Cdn_LimeLight_Popup_View_Success.php';
		exit();
	}
}
