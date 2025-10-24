<?php
/**
 * File: Extension_Genesis_Page_View.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_Genesis_Plugin_Admin
 *
 * W3 GenesisExtension module
 */
class Extension_Genesis_Plugin_Admin {
	/**
	 * Initializes the Genesis extension hooks and filters.
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'w3tc_extension_page_genesis.theme', array( '\W3TC\Extension_Genesis_Page', 'w3tc_extension_page_genesis_theme' ) );
		add_filter( 'w3tc_errors', array( $this, 'w3tc_errors' ) );
	}

	/**
	 * Defines the Genesis extension details and checks requirements.
	 *
	 * @param array  $extensions Existing extensions array to append new extensions.
	 * @param object $config     Configuration object for validating requirements.
	 *
	 * @return array Updated extensions array with Genesis extension details.
	 */
	public static function w3tc_extensions( $extensions, $config ) {
		$requirements = array();

		if ( ! self::is_theme_found() ) {
			$requirements[] = 'Optimizes "Genesis Framework" version >= 1.9.0, which is not active';
		}

		if ( ! $config->is_extension_active( 'fragmentcache' ) ) {
			$requirements[] = 'Activate "Fragment Cache" extension first';
		}

		$extensions['genesis.theme'] = array(
			'name'            => __( 'Genesis Framework by StudioPress', 'w3-total-cache' ),
			'author'          => 'W3 EDGE',
			'description'     => __( 'Provides 30-60% improvement in page generation time for the Genesis Framework by Copyblogger Media.', 'w3-total-cache' ),
			'author_uri'      => 'https://www.w3-edge.com/',
			'extension_uri'   => 'https://www.w3-edge.com/',
			'extension_id'    => 'genesis.theme',
			'pro_feature'     => true,
			'pro_excerpt'     => __( 'Increase the performance of themes powered by the Genesis Theme Framework by up to 60%.', 'w3-total-cache' ),
			'pro_description' => array(),
			'settings_exists' => true,
			'version'         => '0.1',
			'enabled'         => empty( $requirements ),
			'requirements'    => implode( ', ', $requirements ),
			'path'            => 'w3-total-cache/Extension_Genesis_Plugin.php',
		);

		return $extensions;
	}

	/**
	 * Adds error messages related to the Genesis extension configuration.
	 *
	 * @param array $errors Existing error messages array.
	 *
	 * @return array Updated error messages array.
	 */
	public function w3tc_errors( $errors ) {
		$c = Dispatcher::config();

		if ( ! $c->is_extension_active_frontend( 'fragmentcache' ) ) {
			$errors['genesis_fragmentcache_disabled'] = __( 'Please enable <strong>Fragment Cache</strong> module to make sure <strong>Genesis extension</strong> works properly.', 'w3-total-cache' );
		}

		return $errors;
	}

	/**
	 * Checks if the Genesis theme is active or available in the current setup.
	 *
	 * @return bool True if the Genesis theme is found, false otherwise.
	 */
	private static function is_theme_found() {
		if ( ! is_network_admin() ) {
			return ( defined( 'PARENT_THEME_NAME' ) && 'Genesis' === PARENT_THEME_NAME );
		}

		$themes      = Util_Theme::get_themes();
		$theme_found = false;
		foreach ( $themes as $theme ) {
			if ( 'genesis' === strtolower( $theme->Template ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				return true;
			}
		}
	}

	/**
	 * Appends Genesis-related hooks to the list of extension hooks.
	 *
	 * @param array $hooks Existing hooks array to append Genesis hooks.
	 *
	 * @return array Updated hooks array with Genesis hooks.
	 */
	public static function w3tc_extensions_hooks( $hooks ) {
		if ( ! self::show_notice() ) {
			return $hooks;
		}

		if ( ! isset( $hooks['filters']['w3tc_notes'] ) ) {
			$hooks['filters']['w3tc_notes'] = array();
		}

		$hooks['filters']['w3tc_notes'][] = 'w3tc_notes_genesis_theme';
		return $hooks;
	}

	/**
	 * Determines if a notice related to the Genesis extension should be displayed.
	 *
	 * @return bool True if the notice should be displayed, false otherwise.
	 */
	private static function show_notice() {
		$config = Dispatcher::config();
		if ( $config->is_extension_active( 'genesis.theme' ) ) {
			return false;
		}

		if ( ! self::is_theme_found() ) {
			return false;
		}

		$state = Dispatcher::config_state();
		if ( $state->get_boolean( 'genesis.theme.hide_note_suggest_activation' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Adds a notice suggesting the activation of the Genesis extension.
	 *
	 * @param array $notes Existing array of notices to append the Genesis notice.
	 *
	 * @return array Updated array of notices with the Genesis suggestion.
	 */
	public static function w3tc_notes_genesis_theme( $notes ) {
		if ( ! self::show_notice() ) {
			return $notes;
		}

		$extension_id = 'genesis.theme';

		$notes[ $extension_id ] = sprintf(
			// Translators: 1 opening HTML a tag to W3TC extensions page, 2 closing HTML a tag, 3 opening HTML a tag, 4 button link.
			__(
				'Activating the %1$sGenesis Theme%2$s extension for W3 Total Cache may be helpful for your site. %3$sClick here%2$s to try it. %4$s',
				'w3-total-cache'
			),
			'<a href="' . Util_Ui::admin_url( 'admin.php?page=w3tc_extensions#' . $extension_id ) . '">',
			'</a>',
			'<a href="' . Util_Ui::url( array( 'w3tc_extensions_activate' => $extension_id ) ) . '">',
			Util_Ui::button_link(
				__( 'Hide this message', 'w3-total-cache' ),
				Util_Ui::url(
					array(
						'w3tc_default_config_state' => 'y',
						'key'                       => 'genesis.theme.hide_note_suggest_activation',
						'value'                     => 'true',
					)
				)
			)
		);

		return $notes;
	}
}
