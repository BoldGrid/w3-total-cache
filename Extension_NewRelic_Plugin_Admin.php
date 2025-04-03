<?php
/**
 * File: Extension_NewRelic_Plugin_Admin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_NewRelic_Plugin_Admin
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class Extension_NewRelic_Plugin_Admin {
	/**
	 * Config
	 *
	 * @var Config
	 */
	private $_config;

	/**
	 * Retrieves the list of available extensions and adds New Relic to the list.
	 *
	 * @param array $extensions List of existing extensions.
	 * @param array $config Configuration data.
	 *
	 * @return array Updated list of extensions.
	 */
	public static function w3tc_extensions( $extensions, $config ) {
		$extensions['newrelic'] = array(
			'name'                        => 'New Relic',
			'author'                      => 'W3 EDGE',
			'description'                 => __( 'Legacy: New Relic is software analytics platform offering app performance management and mobile monitoring solutions.', 'w3-total-cache' ),
			'author_uri'                  => 'https://www.w3-edge.com/',
			'extension_uri'               => 'https://www.w3-edge.com/',
			'extension_id'                => 'newrelic',
			'settings_exists'             => true,
			'version'                     => '1.0',
			'enabled'                     => true,
			'requirements'                => '',
			'active_frontend_own_control' => true,
			'path'                        => 'w3-total-cache/Extension_NewRelic_Plugin.php',
		);

		return $extensions;
	}

	/**
	 * Constructor for the New Relic extension.
	 * Initializes the configuration for the New Relic plugin.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Executes the necessary actions and filters for the New Relic extension.
	 *
	 * @return void
	 */
	public function run() {
		add_filter( 'w3tc_compatibility_test', array( $this, 'verify_compatibility' ) );
		add_action( 'w3tc_config_save', array( $this, 'w3tc_config_save' ), 10, 1 );

		add_filter( 'w3tc_admin_actions', array( $this, 'w3tc_admin_actions' ) );
		add_filter( 'w3tc_admin_menu', array( $this, 'w3tc_admin_menu' ) );
		add_filter( 'w3tc_extension_plugin_links_newrelic', array( $this, 'w3tc_extension_plugin_links' ) );
		add_action( 'w3tc_settings_page-w3tc_monitoring', array( $this, 'w3tc_settings_page_w3tc_monitoring' ) );

		add_action( 'admin_init_w3tc_general', array( '\W3TC\Extension_NewRelic_GeneralPage', 'admin_init_w3tc_general' ) );
		add_action( 'w3tc_ajax', array( '\W3TC\Extension_NewRelic_Popup', 'w3tc_ajax' ) );

		if ( Util_Admin::is_w3tc_admin_page() ) {
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			add_action( 'network_admin_notices', array( $this, 'admin_notices' ) );
		}

		$v                    = $this->_config->get_string( array( 'newrelic', 'api_key' ) );
		$new_relic_configured = ! empty( $v );

		if ( $new_relic_configured ) {
			add_action( 'admin_init_w3tc_dashboard', array( '\W3TC\Extension_NewRelic_Widget', 'admin_init_w3tc_dashboard' ) );
			add_action( 'w3tc_ajax', array( '\W3TC\Extension_NewRelic_Widget', 'w3tc_ajax' ) );

			add_filter( 'w3tc_notes', array( $this, 'w3tc_notes' ) );
		}
	}

	/**
	 * Modifies the admin menu to include New Relic-related items if applicable.
	 *
	 * @param array $menu Existing admin menu items.
	 *
	 * @return array Modified admin menu with New Relic monitoring option.
	 */
	public function w3tc_admin_menu( $menu ) {
		$c               = Dispatcher::config();
		$monitoring_type = $c->get_string( array( 'newrelic', 'monitoring_type' ) );
		if ( 'amp' === $monitoring_type ) {
			$menu['w3tc_monitoring'] = array(
				'page_title'     => __( 'Monitoring', 'w3-total-cache' ),
				'menu_text'      => __( 'Monitoring', 'w3-total-cache' ),
				'visible_always' => false,
				'order'          => 1200,
			);
		}

		return $menu;
	}

	/**
	 * Adds custom actions for the New Relic extension.
	 *
	 * @param array $handlers Existing array of admin action handlers.
	 *
	 * @return array Modified array of action handlers with the New Relic handler added.
	 */
	public function w3tc_admin_actions( $handlers ) {
		$handlers['new_relic'] = 'Extension_NewRelic_AdminActions';
		return $handlers;
	}

	/**
	 * Adds custom plugin links for the New Relic extension.
	 *
	 * @param array $links Existing array of plugin links.
	 *
	 * @return array Modified array of plugin links with New Relic settings link added.
	 */
	public function w3tc_extension_plugin_links( $links ) {
		$links   = array();
		$links[] = '<a class="edit" href="' . esc_attr( Util_Ui::admin_url( 'admin.php?page=w3tc_general#monitoring' ) ) .
			'">' . __( 'Settings' ) . '</a>';

		return $links;
	}

	/**
	 * Displays the settings page for the New Relic extension.
	 *
	 * @return void
	 */
	public function w3tc_settings_page_w3tc_monitoring() {
		$v = new Extension_NewRelic_Page();
		$v->render_content();
	}

	/**
	 * Displays admin notices related to New Relic configuration.
	 *
	 * @return void
	 */
	public function admin_notices() {
		$api_key = $this->_config->get_string( array( 'newrelic', 'api_key' ) );
		if ( empty( $api_key ) ) {
			return;
		}

		$nerser = Dispatcher::component( 'Extension_NewRelic_Service' );

		$verify_running_result = $nerser->verify_running();
		$not_running           = is_array( $verify_running_result );

		if ( $not_running ) {
			$message  = '<p>' .
				__( 'New Relic is not running correctly. ', 'w3-total-cache' ) .
				'<a href="#" class="w3tc_link_more {for_class: \'w3tc_nr_admin_notice\'}">' .
				'more</a> ' .
				'<div class="w3tc_none w3tc_nr_admin_notice">' .
				__( 'The plugin has detected the following issues:. ', 'w3-total-cache' );
			$message .= "<ul class=\"w3-bullet-list\">\n";
			foreach ( $verify_running_result as $cause ) {
				$message .= "<li>$cause</li>";
			}
			$message .= "</ul>\n";

			$message .= '<p>' . sprintf(
				// Translators: 1 opening HTML link to General Settings page monitoring section, 2 closing HTML link.
				__(
					'Please review the %1$ssettings%2$s.',
					'w3-total-cache'
				),
				'<a href="' . network_admin_url( 'admin.php?page=w3tc_general#monitoring' ) . '">',
				'</a>'
			) . '</p>';
			$message .= "</div></p>\n";

			Util_Ui::error_box( $message );
		}
	}

	/**
	 * Adds custom notes to the W3 Total Cache dashboard related to New Relic.
	 *
	 * @param array $notes Existing array of notes to be displayed.
	 *
	 * @return array Modified array of notes with New Relic notifications added.
	 */
	public function w3tc_notes( $notes ) {
		$newrelic_notes = Dispatcher::component( 'Extension_NewRelic_AdminNotes' );
		$notes          = array_merge( $notes, $newrelic_notes->notifications( $this->_config ) );

		return $notes;
	}

	/**
	 * Verifies the compatibility of the New Relic extension with the current environment.
	 *
	 * @param array $verified_list List of verified components and their statuses.
	 *
	 * @return array Modified list of verified components with New Relic verification results added.
	 */
	public function verify_compatibility( $verified_list ) {
		$nerser          = Dispatcher::component( 'Extension_NewRelic_Service' );
		$nr_verified     = $nerser->verify_compatibility();
		$verified_list[] = '<strong>New Relic</strong>';
		foreach ( $nr_verified as $criteria => $result ) {
			$verified_list[] = sprintf( "$criteria: %s", $result );
		}

		return $verified_list;
	}

	/**
	 * Handles the saving of configuration settings for the New Relic extension.
	 *
	 * @param object $config Configuration object containing the settings to be saved.
	 *
	 * @return void
	 */
	public function w3tc_config_save( $config ) {
		// frontend activity.
		$api_key   = $config->get_string( array( 'newrelic', 'api_key' ) );
		$is_filled = ! empty( $api_key );

		if ( $is_filled ) {
			$monitoring_type = $config->get_string( array( 'newrelic', 'monitoring_type' ) );

			if ( 'browser' === $monitoring_type ) {
				$v         = $config->get_string( array( 'newrelic', 'browser.application_id' ) );
				$is_filled = ! empty( $v );
			} else {
				$v         = $config->get_string( array( 'newrelic', 'apm.application_name' ) );
				$is_filled = ! empty( $v );
			}
		}

		$config->set_extension_active_frontend( 'newrelic', $is_filled );
	}
}
