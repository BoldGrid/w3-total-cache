<?php
/**
 * File: Licensing_Plugin_Admin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Licensing_Plugin_Admin
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable Generic.CodeAnalysis.EmptyStatement
 */
class Licensing_Plugin_Admin {
	/**
	 * Config
	 *
	 * @var Config
	 */
	private $_config = null;

	/**
	 * Constructor for the Licensing Plugin Admin class.
	 *
	 * Initializes the configuration.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Registers hooks for the plugin's admin functionality.
	 *
	 * Adds actions and filters for admin initialization, AJAX, UI updates, and admin bar menu.
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'w3tc_config_ui_save-w3tc_general', array( $this, 'possible_state_change' ), 2, 10 );

		add_action( 'w3tc_message_action_licensing_upgrade', array( $this, 'w3tc_message_action_licensing_upgrade' ) );

		add_filter( 'w3tc_admin_bar_menu', array( $this, 'w3tc_admin_bar_menu' ) );
	}

	/**
	 * Adds licensing menu items to the admin bar.
	 *
	 * @param array $menu_items Existing admin bar menu items.
	 *
	 * @return array Modified admin bar menu items.
	 */
	public function w3tc_admin_bar_menu( $menu_items ) {
		if ( ! Util_Environment::is_w3tc_pro( $this->_config ) ) {
			$license_key       = $this->_config->get_string( 'plugin.license_key' );
			$license_key_param = ! empty( $license_key ) ? '&renew_key=' . $license_key : '';

			$menu_items['00020.licensing'] = array(
				'id'     => 'w3tc_overlay_upgrade',
				'parent' => 'w3tc',
				'title'  => wp_kses(
					sprintf(
						// translators: 1 opening HTML span tag, 2 closing HTML span tag.
						__(
							'%1$sUpgrade Performance%2$s',
							'w3-total-cache'
						),
						'<span style="color: red; background: none;">',
						'</span>'
					),
					array(
						'span' => array(
							'style' => array(),
						),
					)
				),
				'href'   => wp_nonce_url(
					network_admin_url(
						'admin.php?page=w3tc_dashboard&amp;w3tc_message_action=licensing_upgrade' . $license_key_param
					),
					'w3tc'
				),
			);
		}

		if ( defined( 'W3TC_DEBUG' ) && W3TC_DEBUG ) {
			$license_key       = $this->_config->get_string( 'plugin.license_key' );
			$license_key_param = ! empty( $license_key ) ? '&renew_key=' . $license_key : '';

			$menu_items['90040.licensing'] = array(
				'id'     => 'w3tc_debug_overlay_upgrade',
				'parent' => 'w3tc_debug_overlays',
				'title'  => esc_html__( 'Upgrade', 'w3-total-cache' ),
				'href'   => wp_nonce_url(
					network_admin_url(
						'admin.php?page=w3tc_dashboard&amp;w3tc_message_action=licensing_upgrade' . $license_key_param
					),
					'w3tc'
				),
			);
		}

		return $menu_items;
	}

	/**
	 * Handles the licensing upgrade action.
	 *
	 * Adds a hook to modify the admin head for licensing upgrades.
	 *
	 * @return void
	 */
	public function w3tc_message_action_licensing_upgrade() {
		add_action( 'admin_head', array( $this, 'admin_head_licensing_upgrade' ) );
	}

	/**
	 * Outputs JavaScript for the licensing upgrade page.
	 *
	 * @return void
	 */
	public function admin_head_licensing_upgrade() {
		?>
		<script type="text/javascript">
			jQuery(function() {
				w3tc_lightbox_upgrade(w3tc_nonce, 'topbar_performance');
				jQuery('#w3tc-license-instruction').show();
			});
		</script>
		<?php
	}

	/**
	 * Handles possible state changes for plugin licensing.
	 *
	 * @param object $config     Current configuration object.
	 * @param object $old_config Previous configuration object.
	 *
	 * @return void
	 */
	public function possible_state_change( $config, $old_config ) {
		$changed = false;

		$new_key     = $config->get_string( 'plugin.license_key' );
		$new_key_set = ! empty( $new_key );
		$old_key     = $old_config->get_string( 'plugin.license_key' );
		$old_key_set = ! empty( $old_key );

		$config_state = Dispatcher::config_state();

		$old_status = $config_state->get_string( 'license.status' );

		$this->maybe_update_license_status( true );

		$new_status = $config_state->get_string( 'license.status' );

		switch ( true ) {
			// No new key or old key. Do nothing.
			case ( ! $new_key_set && ! $old_key_set ):
				return;

			// Current key set but new is blank, deactivating old.
			case ( ! $new_key_set && $old_key_set ):
				$deactivate_result = Licensing_Core::deactivate_license( $old_key );
				$changed           = true;
				break;

			// Current key is blank but new is not, activating new.
			case ( $new_key_set && ! $old_key_set ):
			case ( 'free' === $old_status && strpos( $new_status, 'active' ) ):
				$activate_result = Licensing_Core::activate_license( $new_key, W3TC_VERSION );
				$changed         = true;
				if ( $activate_result ) {
					$config->set( 'common.track_usage', true );
				}
				break;

			// Current key is set and new different key provided or keys are the same but free upgraded to pro. Deactivating old and activating new.
			case ( $new_key_set && $old_key_set && $new_key !== $old_key ):
				$deactivate_result = Licensing_Core::deactivate_license( $old_key );
				$activate_result   = Licensing_Core::activate_license( $new_key, W3TC_VERSION );
				$changed           = true;
				break;
		}

		if ( $changed ) {
			$state = Dispatcher::config_state();
			$state->set( 'license.next_check', 0 );
			$state->save();

			delete_transient( 'w3tc_imageservice_limited' );

			$messages = array();

			// If the old key was deactivated, add a message.
			if ( ! empty( $deactivate_result->license_status ) ) {
				$status = $deactivate_result->license_status;

				switch ( true ) {
					case ( strpos( $status, 'inactive.expired.' ) === 0 ):
						$messages[] = array(
							'message' => __( 'Your previous W3 Total Cache Pro license key is expired and will remain registered to this domain.', 'w3-total-cache' ),
							'type'    => 'error',
						);
						break;

					case ( strpos( $status, 'inactive.not_present' ) === 0 ):
						$messages[] = array(
							'message' => __( 'Your previous W3 Total Cache Pro license key was not found and cannot be deactivated.', 'w3-total-cache' ),
							'type'    => 'info',
						);
						break;

					case ( strpos( $status, 'inactive' ) === 0 ):
						$messages[] = array(
							'message' => __( 'Your previous W3 Total Cache Pro license key has been deactivated.', 'w3-total-cache' ),
							'type'    => 'info',
						);
						break;

					case ( strpos( $status, 'invalid' ) === 0 ):
						$messages[] = array(
							'message' => sprintf(
								// translators: 1: HTML anchor open tag, 2: HTML anchor close tag.
								__( 'Your previous W3 Total Cache Pro license key is invalid and cannot be deactivated. Please %1$scontact support%2$s for assistance.', 'w3-total-cache' ),
								'<a href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_support' ) ) . '">',
								'</a>'
							),
							'type'    => 'error',
						);
						break;
				}
			}

			// Handle new activation status.
			if ( isset( $activate_result ) && $activate_result ) {
				$status = $activate_result->license_status;

				switch ( true ) {
					case ( strpos( $status, 'active' ) === 0 ):
						$messages[] = array(
							'message' => __( 'The W3 Total Cache Pro license key you provided is valid and has been applied.', 'w3-total-cache' ),
							'type'    => 'info',
						);
						break;
				}
			}

			// Store messages for processing.
			update_option( 'license_update_messages', $messages );
		}
	}

	/**
	 * Initializes admin-specific features and hooks.
	 *
	 * Adds admin notices, UI filters, and license status checks.
	 *
	 * @return void
	 */
	public function admin_init() {
		$capability = apply_filters( 'w3tc_capability_admin_notices', 'manage_options' );

		$this->maybe_update_license_status();

		if ( current_user_can( $capability ) ) {
			if ( is_admin() ) {
				/**
				 * Only admin can see W3TC notices and errors
				 */
				if ( ! Util_Environment::is_wpmu() ) {
					add_action( 'admin_notices', array( $this, 'admin_notices' ), 1, 1 );
				}
				add_action( 'network_admin_notices', array( $this, 'admin_notices' ), 1, 1 );

				if ( Util_Admin::is_w3tc_admin_page() ) {
					add_filter( 'w3tc_notes', array( $this, 'w3tc_notes' ) );
				}
			}
		}
	}

	/**
	 * Checks if a status starts with a specific prefix.
	 *
	 * @param string $s           The status string.
	 * @param string $starts_with The prefix to check against.
	 *
	 * @return bool True if the status starts with the prefix, false otherwise.
	 */
	private function _status_is( $s, $starts_with ) {
		$s           .= '.';
		$starts_with .= '.';
		return substr( $s, 0, strlen( $starts_with ) ) === $starts_with;
	}

	/**
	 * Displays admin notices related to licensing.
	 *
	 * phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
	 * phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript
	 *
	 * @return void
	 */
	public function admin_notices() {
		$state           = Dispatcher::config_state();
		$license_status  = $state->get_string( 'license.status' );
		$cdn_status      = $state->get_string( 'cdn.totalcdn.status' );
		$license_key     = $this->get_license_key();
		$license_message = '';
		$cdn_message     = '';

		$license_message = $this->get_license_notice( $license_status, $license_key );
		$cdn_message     = $this->get_cdn_notice( $cdn_status, $license_key );

		if ( $license_message || $cdn_message ) {
			if ( ! Util_Admin::is_w3tc_admin_page() ) {
				echo '<script src="' . esc_url( plugins_url( 'pub/js/lightbox.js', W3TC_FILE ) ) . '"></script>';
				echo '<link rel="stylesheet" id="w3tc-lightbox-css" href="' . esc_url( plugins_url( 'pub/css/lightbox.css', W3TC_FILE ) ) . '" type="text/css" media="all" />';
			}
		}

		if ( $license_message ) {
			Util_Ui::error_box( '<p>' . $license_message . '</p>' );
		}

		if ( $cdn_message ) {
			Util_Ui::error_box( '<p>' . $cdn_message . '</p>' );
		}

		$license_update_messages = get_option( 'license_update_messages' );
		if ( $license_update_messages ) {
			foreach ( $license_update_messages as $message_data ) {
				$box_type = ( 'error' === $message_data['type'] ) ? 'error_box' : 'e_notification_box';
				Util_Ui::$box_type( '<p>' . $message_data['message'] . '</p>' );
			}

			delete_option( 'license_update_messages' );
		}
	}

	/**
	 * Generates a CDN notice based on the provided status and license key.
	 *
	 * @param string $status The status of the CDN service or license.
	 * @param string $license_key The license key associated with the CDN service.
	 *
	 * @return string The generated CDN notice message.
	 */
	private function get_cdn_notice( $status, $license_key ) {
		switch ( true ) {
			case $this->_status_is( $status, 'active.dunning' ):
				$billing_url = $this->get_billing_url( $license_key );
				if ( $billing_url ) {
					return sprintf(
						// Translators: 1 Total CDN tradmark, 2 opening HTML a tag to billing portal
						// Translators: 3 closing HTML a tag, 4 button to refresh license status.
						__(
							'Your %1$s subscription payment is past due. Please update your %2$sBilling Information%3$s to prevent service interruption. Once your billing information has been updated and payment is successful please manually %4$s otherwise it may take up to 1 hour to refresh on its own.',
							'w3-total-cache'
						),
						'Total CDN',
						'<a class="button" href="' . esc_url( $billing_url ) . '" target="_blank">',
						'</a>',
						'<button type="button" class="button button-refresh-license" data-nonce="' . esc_attr( wp_create_nonce( 'w3tc' ) ) . '" data-action="w3tc_force_license_refresh">' . esc_html__( 'Refresh License Status', 'w3-total-cache' ) . '</button>'
					);
				}

				return __( 'Your Total CDN subscription payment is past due. Please update your billing information or contact us.', 'w3-total-cache' );

			case $this->_status_is( $status, 'canceled' ):
			case $this->_status_is( $status, 'inactive.expired' ):
				return sprintf(
					// Translators: 1 HTML input to renew subscription, 2 Total CDN tradmark.
					__(
						'Your %2$s subscription has expired. %1$s to continue using %2$s',
						'w3-total-cache'
					),
					'<input type="button" class="button button-buy-tcdn" data-nonce="' .
						wp_create_nonce( 'w3tc' ) . '" data-renew-key="' . esc_attr( $license_key ) .
						'" data-src="cdn" value="' . esc_attr__( 'Renew Now', 'w3-total-cache' ) . '" />',
					'Total CDN'
				);

			default:
				return '';
		}
	}

	/**
	 * Generates a license notice based on the provided license status and key.
	 *
	 * @param string $status The current status of the license (e.g., 'active', 'expired').
	 * @param string $license_key The license key associated with the plugin.
	 *
	 * @return string The formatted license notice message.
	 */
	private function get_license_notice( $status, $license_key ) {
		switch ( true ) {
			case $this->_status_is( $status, 'active.dunning' ):
				$billing_url = $this->get_billing_url( $license_key );
				if ( $billing_url ) {
					return sprintf(
						// Translators: 1 Total CDN tradmark, 2 opening HTML a tag to billing portal, 3 closing HTML a tag.
						__(
							'Your %1$s Pro subscription payment is past due. Please update your %2$sBilling Information%3$s to prevent service interruption',
							'w3-total-cache'
						),
						'Total Cache',
						'<a href="' . esc_url( $billing_url ) . '" target="_blank">',
						'</a>'
					);
				}

				return __( 'Your Total Cache Pro subscription payment is past due. Please update your billing information or contact us to prevent service interruption', 'w3-total-cache' );

			case $this->_status_is( $status, 'inactive.expired' ):
				return sprintf(
					// Translators: 1 HTML input to renew subscription.
					__(
						'Your W3 Total Cache Pro license key has expired. %1$s to continue using the Pro features',
						'w3-total-cache'
					),
					'<input type="button" class="button button-renew-plugin" data-nonce="' .
						wp_create_nonce( 'w3tc' ) . '" data-renew-key="' . esc_attr( $license_key ) .
						'" data-src="licensing_expired" value="' . esc_attr__( 'Renew Now', 'w3-total-cache' ) . '" />'
				);

			case $this->_status_is( $status, 'inactive.by_rooturi' ) || $this->_status_is( $status, 'inactive.by_rooturi.activations_limit_not_reached' ):
				$reset_url = Util_Ui::url(
					array(
						'page'                         => 'w3tc_general',
						'w3tc_licensing_reset_rooturi' => 'y',
					)
				);
				return sprintf(
					// Translators: 1 opening HTML a tag to reset license URIs, 2 closing HTML a tag.
					__(
						'Your W3 Total Cache license key is not active for this site. You can switch your license to this website following %1$sthis link%2$s',
						'w3-total-cache'
					),
					'<a class="w3tc_licensing_reset_rooturi" href="' . esc_url( $reset_url ) . '">',
					'</a>'
				);

			case $this->_status_is( $status, 'inactive.by_rooturi.activations_limit_reached' ):
				return __( 'Your W3 Total Cache license key is not active and cannot be activated due to the license activation limit being reached.', 'w3-total-cache' );

			case $this->_status_is( $status, 'inactive' ):
				return __( 'The W3 Total Cache license key is not active.', 'w3-total-cache' );

			case $this->_status_is( $status, 'invalid' ):
				$url = is_network_admin()
					? network_admin_url( 'admin.php?page=w3tc_general#licensing' )
					: admin_url( 'admin.php?page=w3tc_general#licensing' );
				return sprintf(
					// Translators: 1 opening HTML a tag to license setting, 2 closing HTML a tag.
					__(
						'Your current W3 Total Cache Pro license key is not valid. %1$sPlease confirm it%2$s.',
						'w3-total-cache'
					),
					'<a href="' . esc_url( $url ) . '">',
					'</a>'
				);

			case ( 'no_key' === $status || $this->_status_is( $status, 'active' ) || $this->_status_is( $status, 'free' ) ):
				return '';

			default:
				return sprintf(
					// translators: 1: HTML anchor open tag, 2: HTML anchor close tag.
					__( 'The W3 Total Cache license key cannot be verified. Please %1$scontact support%2$s for assistance.', 'w3-total-cache' ),
					'<a href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_support' ) ) . '">',
					'</a>'
				);
		}
	}

	/**
	 * Generates the billing URL for a given license key.
	 *
	 * @param string $license_key The license key used to generate the billing URL.
	 *
	 * @return string The billing URL associated with the provided license key.
	 */
	private function get_billing_url( $license_key ) {
		$api_params = array(
			'edd_action'  => 'get_recurly_hlt_link',
			'license'     => $license_key,
			'license_key' => $license_key,
		);

		$response = wp_remote_get(
			add_query_arg( $api_params, W3TC_LICENSE_API_URL ),
			array(
				'timeout'   => 15,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$body = wp_remote_retrieve_body( $response );

		return filter_var( $body, FILTER_VALIDATE_URL ) ? esc_url_raw( $body ) : '';
	}

	/**
	 * Modifies the notes displayed in the W3TC UI.
	 *
	 * @param array $notes Existing notes to display.
	 *
	 * @return array Modified notes with licensing terms.
	 */
	public function w3tc_notes( $notes ) {
		$terms        = '';
		$state_master = Dispatcher::config_state_master();

		if ( Util_Environment::is_pro_constant( $this->_config ) ) {
			$terms = 'accept';
		} elseif ( ! Util_Environment::is_w3tc_pro( $this->_config ) ) {
			$terms = $state_master->get_string( 'license.community_terms' );

			$buttons = sprintf(
				'<br /><br />%s&nbsp;%s',
				Util_Ui::button_link(
					__( 'Accept', 'w3-total-cache' ),
					Util_Ui::url( array( 'w3tc_licensing_terms_accept' => 'y' ) )
				),
				Util_Ui::button_link(
					__( 'Decline', 'w3-total-cache' ),
					Util_Ui::url( array( 'w3tc_licensing_terms_decline' => 'y' ) )
				)
			);
		} else {
			$state = Dispatcher::config_state();
			$terms = $state->get_string( 'license.terms' );

			$return_url = self_admin_url( Util_Ui::url( array( 'w3tc_licensing_terms_refresh' => 'y' ) ) );

			$buttons =
				sprintf( '<form method="post" action="%s">', W3TC_TERMS_ACCEPT_URL ) .
				Util_Ui::r_hidden( 'return_url', 'return_url', $return_url ) .
				Util_Ui::r_hidden( 'license_key', 'license_key', $this->get_license_key() ) .
				Util_Ui::r_hidden( 'home_url', 'home_url', home_url() ) .
				'<input type="submit" class="button" name="answer" value="Accept" />&nbsp;' .
				'<input type="submit" class="button" name="answer" value="Decline" />' .
				'</form>';
		}

		if ( 'accept' !== $terms && 'decline' !== $terms && 'postpone' !== $terms ) {
			if ( $state_master->get_integer( 'common.install' ) < 1542029724 ) {
				/* installed before 2018-11-12 */
				$notes['licensing_terms'] = sprintf(
					// translators: 1 opening HTML a tag to W3TC Terms page, 2 closing HTML a tag.
					esc_html__(
						'Our terms of use and privacy policies have been updated. Please %1$sreview%2$s and accept them.',
						'w3-total-cache'
					),
					'<a target="_blank" href="' . esc_url( W3TC_TERMS_URL ) . '">',
					'</a>'
				) . $buttons;
			} else {
				$notes['licensing_terms'] = sprintf(
					// translators: 1: HTML break tag, 2: Anchor/link open tag, 3: Anchor/link close tag.
					esc_html__(
						'By allowing us to collect data about how W3 Total Cache is used, we can improve our features and experience for everyone. This data will not include any personally identifiable information.%1$sFeel free to review our %2$sterms of use and privacy policy%3$s.',
						'w3-total-cache'
					),
					'<br />',
					'<a target="_blank" href="' . esc_url( W3TC_TERMS_URL ) . '">',
					'</a>'
				) .
					$buttons;
			}
		}

		return $notes;
	}

	/**
	 * Updates the license status if needed.
	 *
	 * Performs a license check and updates the configuration state accordingly.
	 *
	 * @param bool $force Force update flag.
	 *
	 * @return string The updated license status.
	 */
	private function maybe_update_license_status( $force = false ) {
		$state = Dispatcher::config_state();

		$next_check = $state->get_integer( 'license.next_check' );

		/**
		 * If the license status is in dunning and the next_check interval is greater
		 * than an hour, we force a recheck.
		 */
		if (
			(
				$this->_status_is( $state->get_string( 'cdn.totalcdn.status' ), 'active.dunning' ) ||
				$this->_status_is( $state->get_string( 'license.status' ), 'active.dunning' )
			) &&
			$next_check - time() > 3600
		) {
			$force = true;
		}

		if ( time() < $next_check && ! $force ) {
			return;
		}

		$check_timeout = 3600 * 24 * 5;
		$status        = '';
		$terms         = '';
		$license_key   = $this->get_license_key();

		$old_plugin_type = $this->_config->get_string( 'plugin.type' );
		$plugin_type     = '';

		if ( ! empty( $license_key ) || defined( 'W3TC_LICENSE_CHECK' ) ) {
			$license = Licensing_Core::check_license( $license_key, W3TC_VERSION );

			if ( $license ) {
				$status = $license->license_status;
				$terms  = $license->license_terms;
				if ( $this->_status_is( $status, 'active' ) ) {
					$plugin_type = 'pro';
				} elseif ( $this->_status_is( $status, 'inactive.by_rooturi' ) && Util_Environment::is_w3tc_pro_dev() ) {
					$status      = 'valid';
					$plugin_type = 'pro_dev';
				} elseif ( $this->_status_is( $status, 'free' ) ) {
					$status = 'free';
				}

				$cdn_api_key = isset( $license->cdn_api_key ) ? $license->cdn_api_key : '';
				$this->_config->set( 'cdn.totalcdn.account_api_key', $cdn_api_key );

				$cdn_account_id = isset( $license->cdn_account_id ) ? $license->cdn_account_id : '';
				$this->_config->set( 'cdn.totalcdn.account_id', $cdn_account_id );

				$this->_config->save();

				$cdn_terms = isset( $license->cdn_terms ) ? $license->cdn_terms : '';
				$state->set( 'cdn.totalcdn.terms', $cdn_terms );

				$cdn_status = isset( $license->cdn_status ) ? $license->cdn_status : '';
				$state->set( 'cdn.totalcdn.status', $cdn_status );

				$state->save();
			}

			$this->_config->set( 'plugin.type', $plugin_type );
		} else {
			$status = 'no_key';
		}

		if ( 'no_key' === $status ) {
			// Do nothing.
		} elseif ( $this->_status_is( $status, 'invalid' ) ) {
			// Do nothing.
		} elseif ( $this->_status_is( $status, 'inactive' ) ) {
			// Do nothing.
		} elseif ( $this->_status_is( $status, 'active' ) ) {
			// Do nothing.
		} elseif ( $this->_status_is( $status, 'free' ) ) {
			// Do nothing.
		} elseif ( $this->_status_is( $status, 'active.dunning' ) ) {
			$check_timeout = 3600;
		} else {
			$check_timeout = 60;
		}

		$state->set( 'license.status', $status );
		$state->set( 'license.next_check', time() + $check_timeout );
		$state->set( 'license.terms', $terms );
		$state->save();

		if ( $old_plugin_type !== $plugin_type ) {
			try {
				$this->_config->set( 'plugin.type', $plugin_type );
				$this->_config->save();
			} catch ( \Exception $ex ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// missing exception handle?
			}
		}
		return $status;
	}

	/**
	 * Retrieves the license key for the plugin.
	 *
	 * @return string The license key.
	 */
	public function get_license_key() {
		$license_key = $this->_config->get_string( 'plugin.license_key', '' );
		if ( '' === $license_key ) {
			$license_key = ini_get( 'w3tc.license_key' );
		}
		return $license_key;
	}
}
