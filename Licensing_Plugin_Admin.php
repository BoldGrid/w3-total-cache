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
	 * Usermeta key for storing dismissed license notices.
	 *
	 * @since X.X.X
	 *
	 * @var string
	 */
	const NOTICE_DISMISSED_META_KEY = 'w3tc_license_notice_dismissed';

	/**
	 * Time in seconds after which a dismissed notice can reappear if conditions persist.
	 * Set to 6 days (automatic license check interval is 5 days).
	 *
	 * @since X.X.X
	 *
	 * @var int
	 */
	const NOTICE_DISMISSAL_RESET_TIME = 518400;

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

		// AJAX handler for dismissing license notices.
		add_action( 'wp_ajax_w3tc_dismiss_license_notice', array( $this, 'ajax_dismiss_license_notice' ) );
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
				'href'   => wp_nonce_url( network_admin_url( 'admin.php?page=w3tc_dashboard&amp;w3tc_message_action=licensing_upgrade' ), 'w3tc' ),
			);
		}

		if ( defined( 'W3TC_DEBUG' ) && W3TC_DEBUG ) {
			$menu_items['90040.licensing'] = array(
				'id'     => 'w3tc_debug_overlay_upgrade',
				'parent' => 'w3tc_debug_overlays',
				'title'  => esc_html__( 'Upgrade', 'w3-total-cache' ),
				'href'   => wp_nonce_url( network_admin_url( 'admin.php?page=w3tc_dashboard&amp;w3tc_message_action=licensing_upgrade' ), 'w3tc' ),
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
		$changed     = false;
		$new_key     = $config->get_string( 'plugin.license_key' );
		$new_key_set = ! empty( $new_key );
		$old_key     = $old_config->get_string( 'plugin.license_key' );
		$old_key_set = ! empty( $old_key );

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
				$activate_result = Licensing_Core::activate_license( $new_key, W3TC_VERSION );
				$changed         = true;
				if ( $activate_result ) {
					$config->set( 'common.track_usage', true );
				}
				break;

			// Current key is set and new different key provided. Deactivating old and activating new.
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
			if ( isset( $deactivate_result ) && ! empty( $deactivate_result->license_status ) ) {
				$status = $deactivate_result->license_status;

				switch ( true ) {
					case ( strpos( $status, 'inactive.expired.' ) === 0 ):
						$messages[] = array(
							'message' => sprintf(
								// Translators: 1 Product name.
								__(
									'Your previous %1$s Pro license key is expired and will remain registered to this domain.',
									'w3-total-cache'
								),
								W3TC_POWERED_BY
							),
							'type'    => 'error',
						);
						break;

					case ( strpos( $status, 'inactive.not_present' ) === 0 ):
						$messages[] = array(
							'message' => sprintf(
								// Translators: 1 Product name.
								__(
									'Your previous %1$s Pro license key was not found and cannot be deactivated.',
									'w3-total-cache'
								),
								W3TC_POWERED_BY
							),
							'type'    => 'info',
						);
						break;

					case ( strpos( $status, 'inactive' ) === 0 ):
						$messages[] = array(
							'message' => sprintf(
								// Translators: 1 Product name.
								__(
									'Your previous %1$s Pro license key has been deactivated.',
									'w3-total-cache'
								),
								W3TC_POWERED_BY
							),
							'type'    => 'info',
						);
						break;

					case ( strpos( $status, 'invalid' ) === 0 ):
						$messages[] = array(
							'message' => sprintf(
								// translators: 1 Product name, 2 HTML anchor open tag, 3 HTML anchor close tag.
								__(
									'Your previous %1$s Pro license key is invalid and cannot be deactivated. Please %2$scontact support%3$s for assistance.',
									'w3-total-cache'
								),
								W3TC_POWERED_BY,
								'<a href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_support' ) ) . '">',
								'</a>'
							),
							'type'    => 'error',
						);
						break;
				}
			}

			// Handle new activation status.
			if ( isset( $activate_result ) ) {
				$status = $activate_result->license_status;

				switch ( true ) {
					case ( strpos( $status, 'active' ) === 0 ):
						$messages[] = array(
							'message' => sprintf(
								// Translators: 1 Product name.
								__(
									'The %1$s Pro license key you provided is valid and has been applied.',
									'w3-total-cache'
								),
								W3TC_POWERED_BY
							),
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
				} else {
					// Enqueue lightbox assets on non-W3TC pages if license notices may be shown.
					$this->maybe_enqueue_lightbox_assets();
				}
			}
		}
	}

	/**
	 * Enqueues lightbox assets if license-related notices may need them.
	 *
	 * This is called on non-W3TC admin pages where the lightbox isn't already loaded.
	 * Only enqueues if there's a license status that would display a notice.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	private function maybe_enqueue_lightbox_assets() {
		$state          = Dispatcher::config_state();
		$license_status = $state->get_string( 'license.status' );
		$license_key    = $this->get_license_key();

		// Check if we have a license notice to display.
		$license_message = $this->get_license_notice( $license_status, $license_key );

		if ( $license_message ) {
			$sanitized_status = $this->sanitize_status_for_id( $license_status );

			// Only enqueue if the notice isn't dismissed.
			if ( ! $this->is_notice_dismissed( 'license-status-' . $sanitized_status ) ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_lightbox_assets' ) );
			}
		}
	}

	/**
	 * Enqueues lightbox JavaScript and CSS assets.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function enqueue_lightbox_assets() {
		wp_enqueue_script(
			'w3tc-lightbox',
			plugins_url( 'pub/js/lightbox.js', W3TC_FILE ),
			array( 'jquery' ),
			W3TC_VERSION,
			false
		);

		wp_enqueue_style(
			'w3tc-lightbox',
			plugins_url( 'pub/css/lightbox.css', W3TC_FILE ),
			array(),
			W3TC_VERSION
		);
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
	 * Sanitizes a license status string for use in HTML IDs.
	 *
	 * Ensures the status only contains valid characters for HTML IDs
	 * (lowercase letters, numbers, hyphens, underscores) and replaces
	 * dots with hyphens for readability.
	 *
	 * @since X.X.X
	 *
	 * @param string $status The license status to sanitize.
	 *
	 * @return string The sanitized status safe for use in HTML IDs.
	 */
	private function sanitize_status_for_id( $status ) {
		// Replace dots with hyphens for readability (e.g., "active.dunning" -> "active-dunning").
		$status = str_replace( '.', '-', $status );

		// Use WordPress sanitize_html_class which only allows a-z, A-Z, 0-9, _, -.
		return sanitize_html_class( $status, 'unknown' );
	}

	/**
	 * Displays admin notices related to licensing.
	 *
	 * @return void
	 */
	public function admin_notices() {
		$state           = Dispatcher::config_state();
		$license_status  = $state->get_string( 'license.status' );
		$license_key     = $this->get_license_key();
		$has_notices     = false;

		// Check for PayPal billing update requirement (shows on all admin pages).
		if ( $state->get_boolean( 'license.paypal_billing_update_required' ) && ! $this->is_notice_dismissed( 'paypal-billing-update-required' ) ) {
			$billing_url = $this->get_billing_url( $license_key );
			if ( $billing_url ) {
				$billing_message = sprintf(
					// Translators: 1 Product name, 2 opening HTML a tag to billing portal, 3 closing HTML a tag.
					__(
						'Your %1$s Pro subscription requires a billing agreement update. Please %2$supdate your billing information%3$s to ensure uninterrupted service. If you have already updated your billing information, please ignore and dismiss this message.',
						'w3-total-cache'
					),
					W3TC_POWERED_BY,
					'<a href="' . esc_url( $billing_url ) . '" target="_blank" rel="noopener noreferrer">',
					'</a>'
				);
			} else {
				$billing_message = sprintf(
					// Translators: 1 Product name.
					__(
						'Your %1$s Pro subscription requires a billing agreement update. Please update your billing information or contact support to ensure uninterrupted service.',
						'w3-total-cache'
					),
					W3TC_POWERED_BY
				);
			}

			Util_Ui::error_box( '<p>' . $billing_message . '</p>', 'w3tc-paypal-billing-update-required', true );
			$has_notices = true;
		}

		$license_message = $this->get_license_notice( $license_status, $license_key );

		if ( $license_message ) {
			// Sanitize status for use in HTML ID (only allows a-z, 0-9, -, _).
			$sanitized_status = $this->sanitize_status_for_id( $license_status );

			if ( ! $this->is_notice_dismissed( 'license-status-' . $sanitized_status ) ) {
				Util_Ui::error_box( '<p>' . $license_message . '</p>', 'w3tc-license-status-' . $sanitized_status, true );
				$has_notices = true;
			}
		}

		$license_update_messages = get_option( 'license_update_messages' );

		if ( $license_update_messages ) {
			foreach ( $license_update_messages as $message_data ) {
				if ( 'error' === $message_data['type'] ) {
					Util_Ui::error_box( '<p>' . $message_data['message'] . '</p>', 'w3tc-license-update-message', true );
				} elseif ( 'info' === $message_data['type'] ) {
					Util_Ui::e_notification_box( '<p>' . $message_data['message'] . '</p>', 'w3tc-license-update-message', true );
				}
			}
			delete_option( 'license_update_messages' );
			$has_notices = true;
		}

		// Output JavaScript for persistent dismissal if there are notices.
		if ( $has_notices ) {
			$this->output_dismissal_script();
		}
	}

	/**
	 * Outputs JavaScript for handling persistent notice dismissals via AJAX.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	private function output_dismissal_script() {
		$nonce = wp_create_nonce( 'w3tc' );
		?>
		<script type="text/javascript">
		jQuery(function($) {
			$(document).on('click', '.notice.is-dismissible[id^="w3tc-"] .notice-dismiss', function() {
				var $notice = $(this).closest('.notice');
				var noticeId = $notice.attr('id');

				if (noticeId && noticeId.indexOf('w3tc-') === 0) {
					// Remove the 'w3tc-' prefix for storage.
					var cleanId = noticeId.replace('w3tc-', '');

					$.post(ajaxurl, {
						action: 'w3tc_dismiss_license_notice',
						notice_id: cleanId,
						_wpnonce: '<?php echo esc_js( $nonce ); ?>'
					});
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Generates a license notice based on the provided license status and key.
	 *
	 * @since X.X.X
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
						// Translators: 1 Product name, 2 opening HTML a tag to billing portal, 3 closing HTML a tag.
						__(
							'Your %1$s Pro subscription payment is past due. Please update your %2$sBilling Information%3$s to prevent service interruption',
							'w3-total-cache'
						),
						W3TC_POWERED_BY,
						'<a href="' . esc_url( $billing_url ) . '" target="_blank">',
						'</a>'
					);
				}

				return sprintf(
					// Translators: 1 Product name.
					__(
						'Your %1$s Pro subscription payment is past due. Please update your billing information or contact us to prevent service interruption',
						'w3-total-cache'
					),
					W3TC_POWERED_BY
				);

			case $this->_status_is( $status, 'inactive.expired' ):
				return sprintf(
					// Translators: 1 Product name, 2 HTML input to renew subscription.
					__(
						'Your %1$s Pro license key has expired. %2$s to continue using the Pro features',
						'w3-total-cache'
					),
					W3TC_POWERED_BY,
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
					// Translators: 1 Product name, 2 opening HTML a tag to reset license URIs, 3 closing HTML a tag.
					__(
						'Your %1$s license key is not active for this site. You can switch your license to this website following %2$sthis link%3$s',
						'w3-total-cache'
					),
					W3TC_POWERED_BY,
					'<a class="w3tc_licensing_reset_rooturi" href="' . esc_url( $reset_url ) . '">',
					'</a>'
				);

			case $this->_status_is( $status, 'inactive.by_rooturi.activations_limit_reached' ):
				return sprintf(
					// Translators: 1 Product name.
					__(
						'Your %1$s license key is not active and cannot be activated due to the license activation limit being reached.',
						'w3-total-cache'
					),
					W3TC_POWERED_BY
				);

			case $this->_status_is( $status, 'inactive' ):
				return sprintf(
					// Translators: 1 Product name.
					__(
						'The %1$s license key is not active.',
						'w3-total-cache'
					),
					W3TC_POWERED_BY
				);

			case $this->_status_is( $status, 'invalid' ):
				$url = is_network_admin()
					? network_admin_url( 'admin.php?page=w3tc_general#licensing' )
					: admin_url( 'admin.php?page=w3tc_general#licensing' );
				return sprintf(
					// Translators: 1 Product name, 2 opening HTML a tag to license setting, 3 closing HTML a tag.
					__(
						'Your current %1$s Pro license key is not valid. %2$sPlease confirm it%3$s.',
						'w3-total-cache'
					),
					W3TC_POWERED_BY,
					'<a href="' . esc_url( $url ) . '">',
					'</a>'
				);

			case ( 'no_key' === $status || $this->_status_is( $status, 'active' ) || $this->_status_is( $status, 'free' ) ):
				return '';

			default:
				return sprintf(
					// translators: 1: Product name, 2: HTML anchor open tag, 3: HTML anchor close tag.
					__(
						'The %1$s license key cannot be verified. Please %2$scontact support%3$s for assistance.',
						'w3-total-cache'
					),
					W3TC_POWERED_BY,
					'<a href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_support' ) ) . '">',
					'</a>'
				);
		}
	}

	/**
	 * Generates the billing URL for a given license key.
	 *
	 * @since X.X.X
	 *
	 * @param string $license_key The license key used to generate the billing URL.
	 *
	 * @return string The billing URL associated with the provided license key, or
	 *                an empty string if the request fails or the response is invalid.
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

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
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
					// translators: 1: Product name, 2: HTML break tag, 3: Anchor/link open tag, 4: Anchor/link close tag.
					esc_html__(
						'By allowing us to collect data about how %1$s is used, we can improve our features and experience for everyone. This data will not include any personally identifiable information.%2$sFeel free to review our %3$sterms of use and privacy policy%4$s.',
						'w3-total-cache'
					),
					W3TC_POWERED_BY,
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
	 * @return string The updated license status.
	 */
	private function maybe_update_license_status() {
		$state = Dispatcher::config_state();
		if ( time() < $state->get_integer( 'license.next_check' ) ) {
			return;
		}

		$check_timeout                  = 3600 * 24 * 5;
		$status                         = '';
		$terms                          = '';
		$paypal_billing_update_required = false;
		$license_key                    = $this->get_license_key();

		$old_plugin_type                    = $this->_config->get_string( 'plugin.type' );
		$old_status                         = $state->get_string( 'license.status' );
		$old_paypal_billing_update_required = $state->get_boolean( 'license.paypal_billing_update_required' );
		$plugin_type                        = '';

		if ( ! empty( $license_key ) || defined( 'W3TC_LICENSE_CHECK' ) ) {
			$license = Licensing_Core::check_license( $license_key, W3TC_VERSION );

			if ( $license ) {
				$status = $license->license_status;
				$terms  = $license->license_terms;
				if ( $this->_status_is( $status, 'active' ) ) {
					$plugin_type = 'pro';
				} elseif ( $this->_status_is( $status, 'inactive.by_rooturi' ) &&
					Util_Environment::is_w3tc_pro_dev() ) {
					$status      = 'valid';
					$plugin_type = 'pro_dev';
				}

				// Check for PayPal billing update requirement.
				if ( isset( $license->paypal_billing_update_required ) ) {
					$paypal_billing_update_required = filter_var(
						$license->paypal_billing_update_required,
						FILTER_VALIDATE_BOOLEAN
					);
				}
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
		} else {
			$check_timeout = 60;
		}

		// Clear dismissed notices when conditions change.
		if ( $old_paypal_billing_update_required && ! $paypal_billing_update_required ) {
			// PayPal billing update is no longer required, clear the dismissal for all users.
			$this->clear_dismissed_notice_for_all_users( 'paypal-billing-update-required' );
		}

		if ( $old_status !== $status && ! empty( $old_status ) && ! empty( $status ) ) {
			// License status changed, clear the old status dismissal for all users.
			$this->clear_dismissed_notice_for_all_users( 'license-status-' . $this->sanitize_status_for_id( $old_status ) );
		}

		$state->set( 'license.status', $status );
		$state->set( 'license.next_check', time() + $check_timeout );
		$state->set( 'license.terms', $terms );
		$state->set( 'license.paypal_billing_update_required', $paypal_billing_update_required );
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

	/**
	 * AJAX handler for dismissing license notices.
	 *
	 * Saves the dismissal timestamp in usermeta for persistent per-user dismissal.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function ajax_dismiss_license_notice() {
		check_ajax_referer( 'w3tc', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$notice_id = isset( $_POST['notice_id'] ) ? sanitize_key( $_POST['notice_id'] ) : '';

		if ( empty( $notice_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid notice ID' ) );
		}

		$user_id            = get_current_user_id();
		$dismissed_notices  = get_user_meta( $user_id, self::NOTICE_DISMISSED_META_KEY, true );

		if ( ! is_array( $dismissed_notices ) ) {
			$dismissed_notices = array();
		}

		$dismissed_notices[ $notice_id ] = time();

		update_user_meta( $user_id, self::NOTICE_DISMISSED_META_KEY, $dismissed_notices );

		wp_send_json_success( array( 'message' => 'Notice dismissed' ) );
	}

	/**
	 * Checks if a specific license notice has been dismissed by the current user.
	 *
	 * Returns true if the notice was dismissed and the reset time has not elapsed.
	 * If the reset time has elapsed and the condition still persists, the dismissal
	 * is cleared and the notice will show again.
	 *
	 * @since X.X.X
	 *
	 * @param string $notice_id The unique identifier for the notice.
	 *
	 * @return bool True if the notice is dismissed and should not be shown.
	 */
	private function is_notice_dismissed( $notice_id ) {
		$user_id           = get_current_user_id();
		$dismissed_notices = get_user_meta( $user_id, self::NOTICE_DISMISSED_META_KEY, true );

		if ( ! is_array( $dismissed_notices ) || ! isset( $dismissed_notices[ $notice_id ] ) ) {
			return false;
		}

		$dismissed_time = (int) $dismissed_notices[ $notice_id ];
		$time_elapsed   = time() - $dismissed_time;

		// If enough time has passed, clear the dismissal so the notice can show again.
		if ( $time_elapsed > self::NOTICE_DISMISSAL_RESET_TIME ) {
			unset( $dismissed_notices[ $notice_id ] );
			update_user_meta( $user_id, self::NOTICE_DISMISSED_META_KEY, $dismissed_notices );
			return false;
		}

		return true;
	}

	/**
	 * Clears a specific dismissed notice for all users.
	 *
	 * This should be called when the condition that triggered the notice is resolved.
	 * Uses a targeted query to only retrieve users who have the specific notice dismissed,
	 * rather than all users with any dismissed notices.
	 *
	 * @since X.X.X
	 *
	 * @param string $notice_id The unique identifier for the notice to clear.
	 *
	 * @return void
	 */
	private function clear_dismissed_notice_for_all_users( $notice_id ) {
		global $wpdb;

		// Only get users who have this specific notice dismissed.
		// The meta_value is a serialized array, so we search for the notice_id within it.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s",
				self::NOTICE_DISMISSED_META_KEY,
				'%' . $wpdb->esc_like( $notice_id ) . '%'
			)
		);

		foreach ( $user_ids as $user_id ) {
			$dismissed_notices = get_user_meta( $user_id, self::NOTICE_DISMISSED_META_KEY, true );

			if ( is_array( $dismissed_notices ) && isset( $dismissed_notices[ $notice_id ] ) ) {
				unset( $dismissed_notices[ $notice_id ] );

				if ( empty( $dismissed_notices ) ) {
					delete_user_meta( $user_id, self::NOTICE_DISMISSED_META_KEY );
				} else {
					update_user_meta( $user_id, self::NOTICE_DISMISSED_META_KEY, $dismissed_notices );
				}
			}
		}
	}
}
