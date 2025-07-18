<?php
/**
 * File: Extensions_Plugin_Admin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Extensions_Plugin_Admin
 */
class Extensions_Plugin_Admin {
	/**
	 * Config.
	 *
	 * @var Config
	 */
	private $_config = null; // phpcs:ignore

	/**
	 * Constructor for initializing the configuration.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Runs the plugin, setting up various hooks and filters for extensions.
	 *
	 * @return void
	 */
	public function run() {
		// Attach w3tc-bundled extensions.
		add_filter( 'w3tc_extensions', array( '\W3TC\Extension_CloudFlare_Plugin_Admin', 'w3tc_extensions' ), 10, 2 );
		add_filter( 'w3tc_extensions', array( '\W3TC\Extension_FragmentCache_Plugin_Admin', 'w3tc_extensions' ), 10, 2 );
		add_filter( 'w3tc_extensions', array( '\W3TC\Extension_Genesis_Plugin_Admin', 'w3tc_extensions' ), 10, 2 );
		add_filter( 'w3tc_extensions_hooks', array( '\W3TC\Extension_Genesis_Plugin_Admin', 'w3tc_extensions_hooks' ) );
		add_filter( 'w3tc_notes_genesis_theme', array( '\W3TC\Extension_Genesis_Plugin_Admin', 'w3tc_notes_genesis_theme' ) );
		add_filter( 'w3tc_extensions', array( '\W3TC\Extension_AlwaysCached_Plugin_Admin', 'w3tc_extensions' ), 10, 2 );
		add_filter( 'w3tc_extensions', array( '\W3TC\Extension_NewRelic_Plugin_Admin', 'w3tc_extensions' ), 10, 2 );
		add_filter( 'w3tc_extensions', array( '\W3TC\Extension_Swarmify_Plugin_Admin', 'w3tc_extensions' ), 10, 2 );
		add_filter( 'w3tc_extensions', array( '\W3TC\Extension_WordPressSeo_Plugin_Admin', 'w3tc_extensions' ), 10, 2 );
		add_filter( 'w3tc_extensions_hooks', array( '\W3TC\Extension_WordPressSeo_Plugin_Admin', 'w3tc_extensions_hooks' ) );
		add_action( 'w3tc_notes_wordpress_seo', array( '\W3TC\Extension_WordPressSeo_Plugin_Admin', 'w3tc_notes_wordpress_seo' ) );
		add_filter( 'w3tc_extensions', array( '\W3TC\Extension_Wpml_Plugin_Admin', 'w3tc_extensions' ), 10, 2 );
		add_filter( 'w3tc_extensions', array( '\W3TC\Extension_Amp_Plugin_Admin', 'w3tc_extensions' ), 10, 2 );
		add_filter( 'w3tc_extensions_hooks', array( '\W3TC\Extension_Wpml_Plugin_Admin', 'w3tc_extensions_hooks' ) );
		add_action( 'w3tc_notes_wpml', array( '\W3TC\Extension_Wpml_Plugin_Admin', 'w3tc_notes_wpml' ) );
		add_filter( 'w3tc_extensions', array( '\W3TC\Extension_ImageService_Plugin_Admin', 'w3tc_extensions' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'admin_init' ), 1 );
		add_filter( 'pre_update_option_active_plugins', array( $this, 'pre_update_option_active_plugins' ) );
		add_filter( 'w3tc_admin_menu', array( $this, 'w3tc_admin_menu' ), 10000 );
		add_action( 'w3tc_settings_page-w3tc_extensions', array( $this, 'w3tc_settings_page_w3tc_extensions' ) );

		if ( Util_Admin::is_w3tc_admin_page() ) {
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );

			$action_val = Util_Request::get_string( 'action' );
			if ( ! empty( Util_Request::get_string( 'extension' ) ) && ! empty( $action_val ) ) {
				if ( in_array( $action_val, array( 'activate', 'deactivate' ), true ) ) {
					add_action( 'init', array( $this, 'change_extension_status' ) );
				}
			} elseif ( ! empty( Util_Request::get_array( 'checked' ) ) ) {
				add_action( 'admin_init', array( $this, 'change_extensions_status' ) );
			}
		}
	}


	/**
	 * Modifies the admin menu based on the active extension.
	 *
	 * @param array $menu Existing menu array to be modified.
	 *
	 * @return array Modified menu array.
	 */
	public function w3tc_admin_menu( $menu ) {
		if ( ! \user_can( \get_current_user_id(), 'manage_options' ) ) {
			return $menu;
		}

		$extension_val = Util_Request::get_string( 'extension' );
		$extension     = ( ! empty( $extension_val ) ? esc_attr( $extension_val ) : '' );
		$page_title    = '';

		switch ( $extension ) {
			case 'alwayscached':
				$page_title = __( 'Always Cached Extension Settings', 'w3-total-cache' );
				break;
			case 'amp':
				$page_title = __( 'AMP Extension Settings', 'w3-total-cache' );
				break;
			case 'cloudflare':
				$page_title = __( 'Cloudflare Extension Settings', 'w3-total-cache' );
				break;
			case 'swarmify':
				$page_title = __( 'Swarmify Extension Settings', 'w3-total-cache' );
				break;
			case 'genesis.theme':
				$page_title = __( 'Genesis Extension Settings', 'w3-total-cache' );
				break;
			default:
				$page_title = __( 'Extensions', 'w3-total-cache' );
		}

		$menu['w3tc_extensions'] = array(
			'page_title'     => $page_title,
			'menu_text'      => __( 'Extensions', 'w3-total-cache' ),
			'visible_always' => false,
			'order'          => 1900,
		);

		return $menu;
	}

	/**
	 * Renders the settings page for extensions.
	 *
	 * @return void
	 */
	public function w3tc_settings_page_w3tc_extensions() {
		$o = new Extensions_Page();
		$o->render_content();
	}

	/**
	 * Pre-processes the update of the active plugins option.
	 *
	 * @param mixed $o Option value to be updated.
	 *
	 * @return mixed Modified option value.
	 */
	public function pre_update_option_active_plugins( $o ) {
		delete_option( 'w3tc_extensions_hooks' );

		return $o;
	}

	/**
	 * Initializes settings for the admin interface (administrators only).
	 *
	 * @return void
	 */
	public function admin_init() {
		if ( ! \user_can( \get_current_user_id(), 'manage_options' ) ) {
			return;
		}

		// Used to load even inactive extensions if they want to.
		$s     = get_option( 'w3tc_extensions_hooks' );
		$hooks = @json_decode( $s, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( ! isset( $hooks['next_check_date'] ) ||
			$hooks['next_check_date'] < time() ) {
			$hooks = array(
				'actions'         => array(),
				'filters'         => array(),
				'next_check_date' => time() + 24 * 60 * 60,
			);

			$hooks = apply_filters( 'w3tc_extensions_hooks', $hooks );

			update_option( 'w3tc_extensions_hooks', wp_json_encode( $hooks ) );
		}

		if ( isset( $hooks['actions'] ) ) {
			foreach ( $hooks['actions'] as $hook => $actions_to_call ) {
				if ( is_array( $actions_to_call ) ) {
					add_action(
						$hook,
						function () use ( $actions_to_call ) {
							foreach ( $actions_to_call as $action ) {
								do_action( $action );
							}
						}
					);
				}
			}
		}

		if ( isset( $hooks['filters'] ) ) {
			foreach ( $hooks['filters'] as $hook => $filters_to_call ) {
				if ( is_array( $filters_to_call ) ) {
					add_filter(
						$hook,
						function ( $v ) use ( $filters_to_call ) {
							foreach ( $filters_to_call as $filter ) {
								$v = apply_filters( $filter, $v );
							}

							return $v;
						}
					);
				}
			}
		}
	}

	/**
	 * Changes the status of selected extensions (activate/deactivate and administrators only).
	 *
	 * @return void
	 */
	public function change_extensions_status() {
		if ( ! \user_can( \get_current_user_id(), 'manage_options' ) ) {
			return;
		}

		$message    = '';
		$extensions = Util_Request::get_array( 'checked' );
		$action     = Util_Request::get( 'action' );

		if ( '-1' === $action ) {
			$action = Util_Request::get( 'action2' );   // Dropdown at bottom.
		}

		if ( 'activate-selected' === $action ) {
			foreach ( $extensions as $extension ) {
				if ( Extensions_Util::activate_extension( $extension, $this->_config ) ) {
					$message .= '&activated=' . $extension;
				}
			}
			wp_safe_redirect( Util_Ui::admin_url( sprintf( 'admin.php?page=w3tc_extensions%s', $message ) ) );
			exit;
		} elseif ( 'deactivate-selected' === $action ) {
			foreach ( $extensions as $extension ) {
				if ( Extensions_Util::deactivate_extension( $extension, $this->_config ) ) {
					$message .= '&deactivated=' . $extension;
				}
			}
			wp_safe_redirect( Util_Ui::admin_url( sprintf( 'admin.php?page=w3tc_extensions%s', $message ) ) );
			exit;
		} else {
			wp_safe_redirect( Util_Ui::admin_url( 'admin.php?page=w3tc_extensions' ) );
			exit;
		}
	}

	/**
	 * Changes the status of a specific extension (activate/deactivate and administrators only).
	 *
	 * @return void
	 */
	public function change_extension_status() {
		if ( ! \user_can( \get_current_user_id(), 'manage_options' ) ) {
			return;
		}

		$action = Util_Request::get_string( 'action' );

		if ( in_array( $action, array( 'activate', 'deactivate' ), true ) ) {
			$extension = Util_Request::get_string( 'extension' );

			if ( 'activate' === $action ) {
				Extensions_Util::activate_extension( $extension, $this->_config );
				wp_safe_redirect( Util_Ui::admin_url( sprintf( 'admin.php?page=w3tc_extensions&activated=%s', $extension ) ) );
				exit;
			} elseif ( 'deactivate' === $action ) {
				Extensions_Util::deactivate_extension( $extension, $this->_config );
				wp_safe_redirect( Util_Ui::admin_url( sprintf( 'admin.php?page=w3tc_extensions&deactivated=%s', $extension ) ) );
				exit;
			}
		}
	}

	/**
	 * Displays admin notices related to active extensions (administrators only).
	 *
	 * @see Extensions_Util::get_active_extensions()
	 *
	 * @return void
	 */
	public function admin_notices() {
		if ( ! \user_can( \get_current_user_id(), 'manage_options' ) ) {
			return;
		}

		$extensions_active = Extensions_Util::get_active_extensions( $this->_config );

		foreach ( $extensions_active as $id => $info ) {
			$transient_name = 'w3tc_activation_' . $id;
			$action_name    = 'w3tc_' . $id . '_action';
			$action_val     = Util_Request::get_string( $action_name );

			if ( ! empty( $action_val ) && 'dismiss_activation_notice' === $action_val ) {
				delete_transient( $transient_name );
			}

			if ( isset( $info['notice'] ) && get_transient( $transient_name ) ) {
				?>
				<div class="notice notice-warning inline is-dismissible">
					<p>
				<?php
				echo wp_kses(
					$info['notice'],
					array(
						'a' => array(
							'class'  => array(),
							'href'   => array(),
							'target' => array(),
						),
					)
				);
				?>
						</p>
				</div>
				<?php
			}
		}
	}
}
