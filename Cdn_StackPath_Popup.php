<?php
/**
 * File: Cdn_StackPath_Popup.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_StackPath_Popup
 *
 * phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
 */
class Cdn_StackPath_Popup {
	/**
	 * Handles AJAX actions for StackPath CDN integration.
	 *
	 * This static method initializes the AJAX hooks for StackPath-related actions.
	 *
	 * @return void
	 */
	public static function w3tc_ajax() {
		$o = new Cdn_StackPath_Popup();

		add_action( 'w3tc_ajax_cdn_stackpath_intro', array( $o, 'w3tc_ajax_cdn_stackpath_intro' ) );
		add_action( 'w3tc_ajax_cdn_stackpath_list_zones', array( $o, 'w3tc_ajax_cdn_stackpath_list_zones' ) );
		add_action( 'w3tc_ajax_cdn_stackpath_view_zone', array( $o, 'w3tc_ajax_cdn_stackpath_view_zone' ) );
		add_action( 'w3tc_ajax_cdn_stackpath_configure_zone', array( $o, 'w3tc_ajax_cdn_stackpath_configure_zone' ) );
	}

	/**
	 * AJAX handler for the StackPath CDN introduction screen.
	 *
	 * This method renders the introduction view for configuring StackPath CDN.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_stackpath_intro() {
		$config = Dispatcher::config();

		$this->render_intro(
			array(
				'api_key' => $config->get_string( 'cdn.stackpath.authorization_key' ),
			),
		);
	}

	/**
	 * Renders the introduction view for StackPath CDN configuration.
	 *
	 * @param array $details Details required for rendering the introduction, including API key.
	 *
	 * @return void
	 */
	private function render_intro( $details ) {
		$config         = Dispatcher::config();
		$url_obtain_key = W3TC_STACKPATH_AUTHORIZE_URL;

		include W3TC_DIR . '/Cdn_StackPath_Popup_View_Intro.php';
		exit();
	}

	/**
	 * AJAX handler for listing StackPath zones.
	 *
	 * Retrieves and displays the available zones for the configured API key.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_stackpath_list_zones() {
		$api_key = Util_Request::get_string( 'api_key' );

		$api = Cdn_StackPath_Api::create( $api_key );
		if ( ! $api->is_valid() ) {
			$this->render_intro(
				array(
					'api_key'       => $api_key,
					'error_message' => 'Can\'t authenticate: API key not valid',
				)
			);
			exit();
		}

		try {
			$zones = $api->get_sites();
		} catch ( \Exception $ex ) {
			$error_message = 'Can\'t authenticate: ' . $ex->getMessage();
			$this->render_intro(
				array(
					'api_key'       => $api_key,
					'error_message' => $error_message,
				)
			);
			exit();
		}

		$details = array(
			'api_key' => $api_key,
			'zones'   => $zones,
		);

		include W3TC_DIR . '/Cdn_StackPath_Popup_View_Zones.php';
		exit();
	}

	/**
	 * AJAX handler for viewing a StackPath zone.
	 *
	 * Displays the details of a specific StackPath zone or prepares for creating a new one.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_StackPath_view_zone() {
		$config  = Dispatcher::config();
		$api_key = Util_Request::get_string( 'api_key' );
		$zone_id = Util_Request::get( 'zone_id', '' );

		$details = array(
			'api_key'      => $api_key,
			'zone_id'      => $zone_id,
			'name'         => '',
			'url'          => array(
				'new' => get_home_url(),
			),
			'compress'     => array(
				'new' => 1,
			),
			'ssl'          => array(
				// off, dedicated, sni, shared.
				'current' => null,
				'new'     => null,
			),
			'cors_headers' => array(
				'new' => ( $config->get_boolean( 'cdn.cors_header' ) ? 0 : 1 ),
			),
			'domains'      => array(),
		);

		if ( empty( $zone_id ) ) {
			// create new zone mode.
			$details['name']       = Util_Request::get( 'zone_new_name' );
			$details['ssl']['new'] = 'new';
		} else {
			$api = Cdn_StackPath_Api::create( $api_key );
			try {
				$zone                          = $api->get_site( $zone_id );
				$details['domains']['current'] = $api->get_custom_domains( $zone_id );
			} catch ( \Exception $ex ) {
				$this->render_intro(
					array(
						'api_key'       => $api_key,
						'error_message' => 'Can\'t obtain zone: ' . $ex->getMessage(),
					)
				);
				exit();
			}

			$details['name']                    = $zone['name'];
			$details['compress']['current']     = $zone['compress'];
			$details['cors_headers']['current'] = $zone['cors_headers'];

			if ( $zone['ssl'] ) {
				$details['ssl']['current'] = 'dedicated';
			} elseif ( $zone['ssl_sni'] ) {
				$details['ssl']['current'] = 'sni';
			} elseif ( $zone['sslshared'] ) {
				$details['ssl']['current'] = 'shared';
			} else {
				$details['ssl']['current'] = 'off';
			}

			$details['url']['current'] = $zone['url'];
		}

		// ssl is not enabled at StackPath - offer it.
		if (
			Util_Environment::is_https() &&
			(
				is_null( $details['ssl']['current'] ) ||
				'off' === $details['ssl']['current']
			)
		) {
			$details['ssl']['new'] = 'shared';
		}

		include W3TC_DIR . '/Cdn_StackPath_Popup_View_Zone.php';
		exit();
	}

	/**
	 * AJAX handler for configuring a StackPath zone.
	 *
	 * Creates or updates a StackPath zone based on user inputs and updates configuration settings.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_stackpath_configure_zone() {
		$api_key = Util_Request::get_string( 'api_key' );
		$zone_id = Util_Request::get( 'zone_id', '' );

		if ( empty( $zone_id ) ) {
			$zone = array(
				'name'             => Util_Request::get( 'name' ),
				'label'            => Util_Request::get( 'name' ),
				'url'              => Util_Request::get( 'url' ),
				'use_stale'        => 1,
				'queries'          => 1,
				'compress'         => 1,
				'backend_compress' => 1,
			);
		} else {
			$zone = array();

			if ( ! empty( Util_Request::get_string( 'url_change' ) ) ) {
				$zone['url'] = Util_Request::get_string( 'url' );
			}

			if ( ! empty( Util_Request::get_string( 'compress_change' ) ) ) {
				$zone['compress'] = Util_Request::get_string( 'compress' );
			}

			if ( ! empty( Util_Request::get_string( 'cors_headers_change' ) ) ) {
				$zone['cors_headers'] = Util_Request::get_string( 'cors_headers' );
			}

			if ( 'shared' === Util_Request::get_string( 'ssl' ) ) {
				$zone['sslshared'] = 1;
				$zone['http2']     = 1;
			}
		}

		$api = Cdn_StackPath_Api::create( $api_key );

		try {
			if ( empty( $zone_id ) ) {
				$response = $api->create_site( $zone );
				$zone_id  = $response['id'];
			} else {
				if ( count( array_keys( $zone ) ) > 0 ) {
					$response = $api->update_site( $zone_id, $zone );
				}
			}

			$response = $api->get_site( $zone_id );
		} catch ( \Exception $ex ) {
			$this->render_intro(
				array(
					'api_key'       => $api_key,
					'error_message' => 'Failed to configure zone: ' . $ex->getMessage(),
				)
			);
			exit();
		}

		$c                        = Dispatcher::config();
		$domains                  = $c->get( 'cdn.stackpath.domain' );
		$domains['http_default']  = $response['cdn_url'];
		$domains['https_default'] = $response['ssl_url'];

		$c->set( 'cdn.stackpath.authorization_key', $api_key );
		$c->set( 'cdn.stackpath.zone_id', $zone_id );
		$c->set( 'cdn.stackpath.domain', $domains );
		$c->save();

		include W3TC_DIR . '/Cdn_StackPath_Popup_View_Success.php';
		exit();
	}

	/**
	 * Renders a text box for zone details and handles value changes.
	 *
	 * @param array  $details {
	 *     Zone details containing current and new values.
	 *
	 *     @type array $field {
	 *         The field name to render changes for.
	 *
	 *         @type string $current The current value of the field.
	 *         @type string $new The new value of the field.
	 *     }
	 * }
	 * @param string $field The field name being rendered and managed.
	 *
	 * @return void
	 */
	private function render_zone_textbox_change( $details, $field ) {
		Util_Ui::hidden( '', $field, $details[ $field ]['new'] );

		if ( ! isset( $details[ $field ]['current'] ) ) {
			echo 'will be set to <strong>' . esc_html( $details[ $field ]['new'] ) . '</strong>';
		} elseif ( $details[ $field ]['current'] === $details[ $field ]['new'] ) {
				echo '<strong>' . esc_html( $details[ $field ]['new'] ) . '</strong>';
		} else {
			?>
			currently set to <strong><?php echo esc_html( $details[ $field ]['current'] ); ?></strong>
			<br />
			<label class="w3tc_change_label">
				<input type="checkbox" name="<?php echo esc_attr( $field ); ?>_change" value="y" checked="checked" /> change to <strong><?php echo esc_html( $details[ $field ]['new'] ); ?></strong>
			</label>
			<br />
			<?php
		}
	}

	/**
	 * Renders a boolean field for zone details and handles value changes.
	 *
	 * @param array  $details {
	 *     Zone details containing current and new values.
	 *
	 *     @type array $field {
	 *         The field name to render changes for.
	 *
	 *         @type string $current The current value of the field.
	 *         @type string $new The new value of the field.
	 *     }
	 * }
	 * @param string $field The field name being rendered and managed.
	 *
	 * @return void
	 */
	private function render_zone_boolean_change( $details, $field ) {
		Util_Ui::hidden( '', $field, $details[ $field ]['new'] );

		if ( ! isset( $details[ $field ]['current'] ) ) {
			echo 'will be set to <strong>' . esc_html( $this->render_zone_boolean( $details[ $field ]['new'] ) ) . '</strong>';
		} elseif ( $details[ $field ]['current'] === $details[ $field ]['new'] ) {
				echo '<strong>' . esc_html( $this->render_zone_boolean( $details[ $field ]['new'] ) ) . '</strong>';
		} else {
			?>
			currently set to <strong><?php echo esc_html( $this->render_zone_boolean( $details[ $field ]['current'] ) ); ?></strong>
			<br />
			<label class="w3tc_change_label">';
				<input type="checkbox" name="<?php echo esc_attr( $field ); ?>_change" value="y" checked="checked" /> change to <strong><?php echo esc_html( $this->render_zone_boolean( $details[ $field ]['new'] ) ); ?></strong>
			</label>
			<br />
			<?php
		}
	}

	/**
	 * Renders a boolean field's status as a string.
	 *
	 * @param mixed $v The value representing the boolean status.
	 *
	 * @return void
	 */
	private function render_zone_boolean( $v ) {
		if ( empty( $v ) ) {
			echo 'disabled';
		} else {
			echo 'enabled';
		}
	}
}
