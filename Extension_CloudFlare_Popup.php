<?php
/**
 * File: Extension_CloudFlare_Popup.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_CloudFlare_Popup
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */
class Extension_CloudFlare_Popup {
	/**
	 * Handles the AJAX requests for CloudFlare extension.
	 *
	 * @return void
	 */
	public static function w3tc_ajax() {
		$o = new Extension_CloudFlare_Popup();

		add_action( 'w3tc_ajax_extension_cloudflare_intro', array( $o, 'w3tc_ajax_extension_cloudflare_intro' ) );
		add_action( 'w3tc_ajax_extension_cloudflare_intro_done', array( $o, 'w3tc_ajax_extension_cloudflare_intro_done' ) );
		add_action( 'w3tc_ajax_extension_cloudflare_zones_done', array( $o, 'w3tc_ajax_extension_cloudflare_zones_done' ) );
	}

	/**
	 * Displays the introductory page for the CloudFlare extension.
	 *
	 * @return void
	 */
	public function w3tc_ajax_extension_cloudflare_intro() {
		$c       = Dispatcher::config();
		$details = array(
			'email' => $c->get_string( array( 'cloudflare', 'email' ) ),
			'key'   => $c->get_string( array( 'cloudflare', 'key' ) ),
		);

		include W3TC_DIR . '/Extension_CloudFlare_Popup_View_Intro.php';
		exit();
	}

	/**
	 * Handles the AJAX request when the CloudFlare intro process is completed.
	 *
	 * @return void
	 */
	public function w3tc_ajax_extension_cloudflare_intro_done() {
		$this->_render_extension_cloudflare_zones(
			array(
				'email' => Util_Request::get_string( 'email' ),
				'key'   => Util_Request::get_string( 'key' ),
				'page'  => empty( Util_Request::get_integer( 'page' ) ) ? 1 : Util_Request::get_integer( 'page' ),
			)
		);
	}

	/**
	 * Renders the CloudFlare zones.
	 *
	 * @param array $details Associative array containing 'email', 'key', and 'page' information.
	 *
	 * @return void
	 *
	 * @throws \Exception If there is an issue authenticating with the CloudFlare API.
	 */
	private function _render_extension_cloudflare_zones( $details ) {
		$email   = $details['email'];
		$key     = $details['key'];
		$page    = $details['page'];
		$details = array(
			'email'       => $email,
			'key'         => $key,
			'page'        => $page,
			'zones'       => array(),
			'total_pages' => 1,
		);

		try {
			$api                    = new Extension_CloudFlare_Api(
				array(
					'email' => $email,
					'key'   => $key,
				)
			);
			$r                      = $api->zones( $page );
			$details['zones']       = $r['result'];
			$details['total_pages'] = $r['result_info']['total_pages'];
		} catch ( \Exception $ex ) {
			$details['error_message'] = 'Can\'t authenticate: ' . $ex->getMessage();
			include W3TC_DIR . '/Extension_CloudFlare_Popup_View_Intro.php';
			exit();
		}

		include W3TC_DIR . '/Extension_CloudFlare_Popup_View_Zones.php';
		exit();
	}

	/**
	 * Handles the AJAX request when the CloudFlare zones process is completed.
	 *
	 * @return void
	 */
	public function w3tc_ajax_extension_cloudflare_zones_done() {
		$email   = Util_Request::get_string( 'email' );
		$key     = Util_Request::get_string( 'key' );
		$zone_id = Util_Request::get( 'zone_id' );

		if ( empty( $zone_id ) ) {
			$this->_render_extension_cloudflare_zones(
				array(
					'email'         => $email,
					'key'           => $key,
					'error_message' => 'Please select zone',
				)
			);
		}

		$zone_name = '';

		// get zone name.
		try {
			$api       = new Extension_CloudFlare_Api(
				array(
					'email' => $email,
					'key'   => $key,
				)
			);
			$zone      = $api->zone( $zone_id );
			$zone_name = $zone['name'];
		} catch ( \Exception $ex ) {
			$details['error_message'] = 'Can\'t authenticate: ' . $ex->getMessage();
			include W3TC_DIR . '/Extension_CloudFlare_Popup_View_Intro.php';
			exit();
		}

		$c = Dispatcher::config();

		$c->set( array( 'cloudflare', 'email' ), $email );
		$c->set( array( 'cloudflare', 'key' ), $key );
		$c->set( array( 'cloudflare', 'zone_id' ), $zone_id );
		$c->set( array( 'cloudflare', 'zone_name' ), $zone_name );
		$c->save();

		delete_transient( 'w3tc_cloudflare_stats' );

		$postfix = Util_Admin::custom_message_id(
			array(),
			array(
				'extension_cloudflare_configuration_saved' => 'Cloudflare credentials are saved successfully',
			)
		);
		echo 'Location admin.php?page=w3tc_extensions&extension=cloudflare&action=view&' . esc_attr( $postfix );
		exit();
	}
}
