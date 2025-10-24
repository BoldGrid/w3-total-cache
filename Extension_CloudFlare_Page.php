<?php
/**
 * File: Extension_CloudFlare_Page.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_CloudFlare_Page
 */
class Extension_CloudFlare_Page {
	/**
	 * Enqueues the necessary scripts for the Cloudflare performance page.
	 *
	 * phpcs:disable WordPress.Security.NonceVerification.Recommended
	 *
	 * @return void
	 */
	public static function admin_print_scripts_performance_page_w3tc_cdn() {
		if (
			(
				isset( $_REQUEST['extension'] ) &&
				'cloudflare' === Util_Request::get_string( 'extension' )
			) ||
			(
				isset( $_REQUEST['page'] ) &&
				'w3tc_cdnfsd' === Util_Request::get_string( 'page' )
			)
		) {
			wp_enqueue_script(
				'w3tc_extension_cloudflare',
				plugins_url( 'Extension_CloudFlare_Page_View.js', W3TC_FILE ),
				array( 'jquery' ),
				'1.0',
				false
			);
		}
	}

	/**
	 * Includes the CDN settings box view for the Cloudflare extension.
	 *
	 * @return void
	 */
	public static function w3tc_settings_box_cdnfsd() {
		include W3TC_DIR . '/Extension_CloudFlare_Cdn_Page_View.php';
	}

	/**
	 * Renders the main Cloudflare extension page.
	 *
	 * @return void
	 *
	 * @throws \Exception If an error occurs while retrieving Cloudflare settings.
	 */
	public static function w3tc_extension_page_cloudflare() {
		$c   = Dispatcher::config();
		$api = Extension_CloudFlare_SettingsForUi::api();

		$email   = $c->get_string( array( 'cloudflare', 'email' ) );
		$key     = $c->get_string( array( 'cloudflare', 'key' ) );
		$zone_id = $c->get_string( array( 'cloudflare', 'zone_id' ) );

		if ( empty( $email ) || empty( $key ) || empty( $zone_id ) ) {
			$state = 'not_configured';
		} else {
			$settings = array();

			try {
				$settings = Extension_CloudFlare_SettingsForUi::settings_get( $api );
				$state    = 'available';
			} catch ( \Exception $ex ) {
				$state         = 'not_available';
				$error_message = $ex->getMessage();

			}
		}

		$config = $c;

		include W3TC_DIR . '/Extension_CloudFlare_Page_View.php';
	}

	/**
	 * Renders a checkbox input for the Cloudflare settings.
	 *
	 * @param array $settings The current Cloudflare settings.
	 * @param array $data     Metadata for the checkbox input (key, label, description, etc.).
	 *
	 * @return void
	 */
	private static function cloudflare_checkbox( $settings, $data ) {
		if ( ! isset( $settings[ $data['key'] ] ) ) {
			return;
		}

		$value    = ( 'on' === $settings[ $data['key'] ]['value'] );
		$disabled = ! $settings[ $data['key'] ]['editable'];

		Util_Ui::table_tr(
			array(
				'id'          => $data['key'],
				'label'       => $data['label'],
				'checkbox'    => array(
					'name'     => 'cloudflare_api_' . $data['key'],
					'value'    => $value,
					'disabled' => $disabled,
					'label'    => 'Enable',
				),
				'description' => $data['description'],
			)
		);
	}

	/**
	 * Renders a select box input for the Cloudflare settings.
	 *
	 * @param array $settings The current Cloudflare settings.
	 * @param array $data     Metadata for the select box input (key, label, values, etc.).
	 *
	 * @return void
	 */
	private static function cloudflare_selectbox( $settings, $data ) {
		if ( ! isset( $settings[ $data['key'] ] ) ) {
			return;
		}

		$value    = $settings[ $data['key'] ]['value'];
		$disabled = ! $settings[ $data['key'] ]['editable'];

		Util_Ui::table_tr(
			array(
				'id'          => $data['key'],
				'label'       => $data['label'],
				'selectbox'   => array(
					'name'     => 'cloudflare_api_' . $data['key'],
					'value'    => $value,
					'disabled' => $disabled,
					'values'   => $data['values'],
				),
				'description' => $data['description'],
			)
		);
	}

	/**
	 * Renders a text input box for the Cloudflare settings.
	 *
	 * @param array $settings The current Cloudflare settings.
	 * @param array $data     Metadata for the text input box (key, label, description, etc.).
	 *
	 * @return void
	 */
	private static function cloudflare_textbox( $settings, $data ) {
		if ( ! isset( $settings[ $data['key'] ] ) ) {
			return;
		}

		$value    = $settings[ $data['key'] ]['value'];
		$disabled = ! $settings[ $data['key'] ]['editable'];

		Util_Ui::table_tr(
			array(
				'id'          => $data['key'],
				'label'       => $data['label'],
				'textbox'     => array(
					'name'     => 'cloudflare_api_' . $data['key'],
					'value'    => $value,
					'disabled' => $disabled,
				),
				'description' => $data['description'],
			)
		);
	}
}
