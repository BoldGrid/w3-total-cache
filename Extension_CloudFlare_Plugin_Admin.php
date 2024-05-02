<?php
namespace W3TC;

class Extension_CloudFlare_Plugin_Admin {
	private $_config;
	private $api;

	public static function w3tc_extensions( $extensions, $config ) {
		$current_user            = wp_get_current_user();
		$message                 = array( 'Cloudflare' );
		$cloudflare_signup_email = '';
		$cloudflare_signup_user  = '';

		if ( is_a( $current_user, 'WP_User' ) ) {
			if ( $current_user->user_email ) {
				$cloudflare_signup_email = $current_user->user_email;
			}

			if ( $current_user->user_login && 'admin' !== $current_user->user_login ) {
				$cloudflare_signup_user = $current_user->user_login;
			}
		}

		$extensions['cloudflare'] = array(
			'name'            => 'Cloudflare',
			'author'          => 'W3 EDGE',
			'description'     => wp_kses(
				sprintf(
					// translators:	1 opening HTML a tag to Cloudflare signup page with affiliate association, 2 closing HTML a tag,
					// translators: 3 opening HTML abbr tag, 4 closing HTML abbr tag,
					// translators: 5 opening HTML a tag to Cloudflare account page, 6 closing HTML a tag,
					// translators: 7 opening HTML a tag to Cloudflare help page, 8 closing HTML a tag.
					__(
						'Cloudflare protects and accelerates websites. %1$sSign up now for free%2$s to get started, or if you have an account simply log in to obtain your %3$sAPI%4$s token / global key from the %5$saccount page%6$s to enter it on the General Settings box that appears after plugin activation. Contact the Cloudflare %7$ssupport team%8$s with any questions.',
						'w3-total-cache'
					),
					'<a href="' . esc_url( 'https://www.cloudflare.com/sign-up.html?affiliate=w3edge&amp;seed_domain=' . Util_Environment::host() . '&amp;email=' . $cloudflare_signup_email . '&amp;username=' . $cloudflare_signup_user ) . '" target="_blank">',
					'</a>',
					'<abbr title="' . esc_attr__( 'Application Programming Interface', 'w3-total-cache' ) . '">',
					'</abbr>',
					'<a target="_blank" href="https://www.cloudflare.com/my-account">',
					'</a>',
					'<a href="http://www.cloudflare.com/help.html" target="_blank">',
					'</a>'
				),
				array(
					'a'    => array(
						'href'   => array(),
						'target' => array(),
					),
					'abbr' => array(
						'title' => array(),
					),
				)
			),
			'author_uri'      => 'https://www.w3-edge.com/',
			'extension_uri'   => 'https://www.w3-edge.com/',
			'extension_id'    => 'cloudflare',
			'settings_exists' => true,
			'version'         => '0.3',
			'enabled'         => true,
			'requirements'    => implode( ', ', $message ),
			'path'            => 'w3-total-cache/Extension_CloudFlare_Plugin.php',
		);

		return $extensions;
	}

	function run() {
		$c             = Dispatcher::config();
		$this->api     = new Extension_CloudFlare_Api(
			array(
				'email'                 => $c->get_string( array( 'cloudflare', 'email' ) ),
				'key'                   => $c->get_string( array( 'cloudflare', 'key' ) ),
				'zone_id'               => $c->get_string( array( 'cloudflare', 'zone_id' ) ),
				'timelimit_api_request' => $c->get_integer( array( 'cloudflare', 'timelimit.api_request' ) ),
			)
		);
		$this->_config = $c;

		add_action( 'w3tc_config_ui_save-w3tc_extensions', array( $this, 'w3tc_save_options' ), 10, 0 );

		add_filter( 'w3tc_dashboard_actions', array( $this, 'w3tc_dashboard_actions' ) );

		$widget = new Extension_CloudFlare_Widget();
		$widget->init();

		// modify settings page.
		add_filter( 'w3tc_ui_config_item_cdnfsd.enabled', array( $this, 'w3tc_ui_config_item_cdnfsd_enabled' ) );
		add_filter( 'w3tc_ui_config_item_cdnfsd.engine', array( $this, 'w3tc_ui_config_item_cdnfsd_engine' ) );

		add_filter( 'w3tc_settings_general_anchors', array( $this, 'w3tc_settings_general_anchors' ) );
		add_action( 'w3tc_settings_general_boxarea_cloudflare', array( $this, 'w3tc_settings_general_boxarea_cloudflare' ) );

		add_action( 'wp_ajax_w3tc_cloudflare_api_request', array( $this, 'action_cloudflare_api_request' ) );

		// modify main menu.
		add_filter( 'w3tc_admin_bar_menu', array( $this, 'w3tc_admin_bar_menu' ) );

		// dashboard.
		add_action( 'admin_print_scripts-toplevel_page_w3tc_dashboard', array( $this, 'admin_print_scripts_w3tc_dashboard' ) );
		add_filter( 'w3tc_admin_actions', array( $this, 'w3tc_admin_actions' ) );

		// own settings page.
		add_action( 'w3tc_extension_page_cloudflare', array( '\W3TC\Extension_CloudFlare_Page', 'w3tc_extension_page_cloudflare' ) );

		add_action( 'w3tc_ajax', array( '\W3TC\Extension_CloudFlare_Popup', 'w3tc_ajax' ) );

		$cdnfsd_engine = $c->get_string( 'cdnfsd.engine' );

		if ( empty( $cdnfsd_engine ) || 'cloudflare' === $cdnfsd_engine ) {
			add_action( 'w3tc_settings_box_cdnfsd', array( '\W3TC\Extension_CloudFlare_Page', 'w3tc_settings_box_cdnfsd' ) );
		}

		// add notices about api health.
		if ( Util_Admin::is_w3tc_admin_page() ) {
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			add_action( 'network_admin_notices', array( $this, 'admin_notices' ) );
		}

		$this->check_ip_versions();
	}

	/**
	 * Admin notices.
	 */
	public function admin_notices() {
		$plugins = get_plugins();
		if ( array_key_exists( 'cloudflare/cloudflare.php', $plugins ) && $this->_config->get_boolean( 'notes.cloudflare_plugin' ) ) {
			echo wp_kses(
				sprintf(
					// translators: 1 opening HTML div tag with class "error" followed by opening HTML p tag,
					// translators: 2 HTML button with lable "Hide this message" that will hide the error message,
					// translators: 3 closing HTML p tag followed by closing HTML div tag.
					__(
						'%1$sCloudflare plugin detected. We recommend removing the plugin as it offers no additional capabilities when W3 Total Cache is installed. This message will disappear when Cloudflare is removed. %2$s%3$s',
						'w3-total-cache'
					),
					'<div class="error"><p>',
					Util_Ui::button_hide_note( 'Hide this message', 'cloudflare_plugin' ),
					'</p></div>'
				),
				array(
					'div'   => array(
						'class' => array(),
					),
					'input' => array(
						'class'   => array(),
						'value'   => array(),
						'type'    => array(),
						'onclick' => array(),
					),
					'p'     => array(),
				)
			);
		}
	}

	public function w3tc_admin_bar_menu( $menu_items ) {
		$menu_items['20810.cloudflare'] = array(
			'id'     => 'w3tc_flush_cloudflare',
			'parent' => 'w3tc_flush',
			'title'  => __( 'Cloudflare', 'w3-total-cache' ),
			'href'   => wp_nonce_url( admin_url( 'admin.php?page=w3tc_dashboard&amp;w3tc_cloudflare_flush' ), 'w3tc' ),
		);

		return $menu_items;
	}

	/**
	 * Check if last check has expired.  If so update Cloudflare IPs.
	 */
	function check_ip_versions() {
		$state = Dispatcher::config_state_master();

		if ( $state->get_integer( 'extension.cloudflare.next_ips_check' ) < time() ) {
			// update asap to avoid multiple processes entering the check.
			$state->set( 'extension.cloudflare.next_ips_check', time() + 7 * 24 * 60 * 60 );

			$data = array();
			try {
				$data = $this->api->get_ip_ranges();
			} catch ( \Exception $ex ) {
				// Missing exception handling?
			}

			if ( isset( $data['ip4'] ) ) {
				$state->set( 'extension.cloudflare.ips.ip4', $data['ip4'] );
			}

			if ( isset( $data['ip6'] ) ) {
				$state->set( 'extension.cloudflare.ips.ip6', $data['ip6'] );
			}

			$state->save();
		}
	}

	/**
	 * Send Cloudflare API request.
	 *
	 * @return void
	 */
	function action_cloudflare_api_request() {
		$result   = false;
		$response = null;
		$actions  = array(
			'dev_mode',
			'sec_lvl',
			'fpurge_ts',
		);
		$email    = Util_Request::get_string( 'email' );
		$key      = Util_Request::get_string( 'key' );
		$zone     = Util_Request::get_string( 'zone' );
		$action   = Util_Request::get_string( 'command' );
		$value    = Util_Request::get_string( 'value' );
		$nonce    = Util_Request::get_string( '_wpnonce' );

		if ( ! wp_verify_nonce( $nonce, 'w3tc' ) ) {
			$error = 'Access denied.';
		} elseif ( ! $email ) {
			$error = 'Empty email.';
		} elseif ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			$error = 'Invalid email.';
		} elseif ( ! $key ) {
			$error = 'Empty token / global key.';
		} elseif ( ! $zone ) {
			$error = 'Empty zone.';
		} elseif ( strpos( $zone, '.' ) === false ) {
			$error = 'Invalid domain.';
		} elseif ( ! in_array( $action, $actions, true ) ) {
			$error = 'Invalid action.';
		} else {
			$config = array(
				'email' => $email,
				'key'   => $key,
				'zone'  => $zone,
			);

			@$this->api = new Extension_CloudFlare_Api( $config );

			@set_time_limit( $this->_config->get_integer( array( 'cloudflare', 'timelimit.api_request' ) ) );
			$response = $this->api->api_request( $action, $value );

			if ( $response ) {
				if ( 'success' === $response->result ) {
					$result = true;
					$error  = 'OK';
				} else {
					$error = $response->msg;
				}
			} else {
				$error = 'Unable to make Cloudflare API request.';
			}
		}

		$return = array(
			'result'   => $result,
			'error'    => $error,
			'response' => $response,
		);

		wp_send_json( $return );
	}

	/**
	 * W3TC Dashboard page modifications
	 */
	public function w3tc_admin_actions( $handlers ) {
		$handlers['cloudflare'] = 'Extension_CloudFlare_AdminActions';
		return $handlers;
	}

	public function admin_print_scripts_w3tc_dashboard() {
		wp_enqueue_script(
			'w3tc_extension_cloudflare_dashboard',
			plugins_url( 'Extension_CloudFlare_View_Dashboard.js', W3TC_FILE ),
			array( 'jquery' ),
			'1.0',
			true
		);
	}

	public function w3tc_dashboard_actions( $actions ) {
		$email = $this->_config->get_string( array( 'cloudflare', 'email' ) );
		$key   = $this->_config->get_string( array( 'cloudflare', 'key' ) );

		if ( empty( $email ) || empty( $key ) ) {
			return $actions;
		}

		$modules            = Dispatcher::component( 'ModuleStatus' );
		$can_empty_memcache = $modules->can_empty_memcache();
		$can_empty_opcode   = $modules->can_empty_opcode();
		$can_empty_file     = $modules->can_empty_file();
		$can_empty_varnish  = $modules->can_empty_varnish();

		$actions[] = sprintf(
			'<input type="submit" class="dropdown-item" name="w3tc_cloudflare_flush_all_except_cf" value="%1$s"%2$s>',
			esc_attr__( 'Empty All Caches Except Cloudflare', 'w3-total-cache' ),
			( ! $can_empty_memcache && ! $can_empty_opcode && ! $can_empty_file && ! $can_empty_varnish )
				? ' disabled="disabled"' : ''
		);

		return $actions;
	}

	public function w3tc_ui_config_item_cdnfsd_enabled( $a ) {
		$c             = Dispatcher::config();
		$cdnfsd_engine = $c->get_string( 'cdnfsd.engine' );

		// overwrite behavior if controlled by extension.
		if ( empty( $cdnfsd_engine ) || 'cloudflare' === $cdnfsd_engine ) {
			$a['value'] = true;
		}

		return $a;
	}

	public function w3tc_ui_config_item_cdnfsd_engine( $a ) {
		$c             = Dispatcher::config();
		$cdnfsd_engine = $c->get_string( 'cdnfsd.engine' );

		// overwrite behavior if controlled by extension.
		if ( empty( $cdnfsd_engine ) || 'cloudflare' === $cdnfsd_engine ) {
			$a['value'] = 'cloudflare';
		}

		if ( isset( $a['selectbox_values']['cloudflare'] ) ) {
			$a['selectbox_values']['cloudflare']['label']    = 'Cloudflare';
			$a['selectbox_values']['cloudflare']['disabled'] = null;
		}

		return $a;
	}

	public function w3tc_settings_general_anchors( $anchors ) {
		$anchors[] = array(
			'id'   => 'cloudflare',
			'text' => 'Cloudflare',
		);
		return $anchors;
	}

	public function w3tc_settings_general_boxarea_cloudflare() {
		$config = $this->_config;
		include W3TC_DIR . '/Extension_CloudFlare_GeneralPage_View.php';
	}

	/**
	 * Applies Cloudflare API settings.
	 */
	public function w3tc_save_options() {
		if( 'cloudflare' === Util_Request::get_string( 'extension' ) ) {
			$api = Extension_CloudFlare_SettingsForUi::api();
			$errors = Extension_CloudFlare_SettingsForUi::settings_set( $api );

			if ( empty( $errors ) ) {
				Util_Admin::redirect_with_custom_messages2(
					array(
						'notes' => array(
							'cloudflare_save_done' => __( 'Cloudflare settings are successfully updated.', 'w3-total-cache' ),
						),
					)
				);
			} else {
				Util_Admin::redirect_with_custom_messages2(
					array(
						'errors' => array(
							'cloudflare_save_error' => __( 'Failed to update Cloudflare settings:', 'w3-total-cache' ) .
								"<br />\n" . implode( "<br />\n", $errors ),
						),
					)
				);
			}
		}
	}
}
