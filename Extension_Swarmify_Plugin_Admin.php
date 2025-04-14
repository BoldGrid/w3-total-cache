<?php
/**
 * File: Extension_Swarmify_Plugin_Admin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_Swarmify_Plugin_Admin
 *
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class Extension_Swarmify_Plugin_Admin {
	/**
	 * Adds Swarmify extension details to the extensions list.
	 *
	 * @param array  $extensions Array of current extensions to which Swarmify details will be added.
	 * @param object $config     Configuration object.
	 *
	 * @return array Updated array of extensions with Swarmify details.
	 */
	public static function w3tc_extensions( $extensions, $config ) {
		$extensions['swarmify'] = array(
			'name'                        => 'Swarmify',
			'author'                      => 'W3 EDGE',
			'description'                 => __( 'Optimize your video performance by enabling the Swarmify SmartVideo™ solution.', 'w3-total-cache' ),
			'author_uri'                  => 'https://www.w3-edge.com/',
			'extension_uri'               => 'https://www.w3-edge.com/',
			'extension_id'                => 'swarmify',
			'settings_exists'             => true,
			'version'                     => '1.0',
			'enabled'                     => true,
			'requirements'                => '',
			'active_frontend_own_control' => true,
			'path'                        => 'w3-total-cache/Extension_Swarmify_Plugin.php',
		);

		return $extensions;
	}

	/**
	 * Registers the necessary hooks and actions for the plugin.
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'w3tc_config_save', array( $this, 'w3tc_config_save' ), 10, 1 );

		add_action( 'w3tc_extension_page_swarmify', array( $this, 'w3tc_extension_page_swarmify' ) );

		add_filter( 'w3tc_admin_actions', array( $this, 'w3tc_admin_actions' ) );
	}

	/**
	 * Renders the Swarmify extension settings page content.
	 *
	 * @return void
	 */
	public function w3tc_extension_page_swarmify() {
		$v = new Extension_Swarmify_Page();
		$v->render_content();
	}

	/**
	 * Adds Swarmify specific admin actions.
	 *
	 * @param array $handlers Existing array of admin actions.
	 *
	 * @return array Modified array with added Swarmify admin action.
	 */
	public function w3tc_admin_actions( $handlers ) {
		$handlers['swarmify'] = 'Extension_Swarmify_AdminActions';
		return $handlers;
	}

	/**
	 * Saves the Swarmify configuration to the settings.
	 *
	 * @param object $config Configuration object to be saved.
	 *
	 * @return void
	 */
	public function w3tc_config_save( $config ) {
		// frontend activity.
		$api_key   = $config->get_string( array( 'swarmify', 'api_key' ) );
		$is_filled = ! empty( $api_key );

		$config->set_extension_active_frontend( 'swarmify', $is_filled );
	}
}
