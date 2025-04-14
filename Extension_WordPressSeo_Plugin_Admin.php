<?php
/**
 * File: Extension_WordPressSeo_Plugin_Admin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_WordPressSeo_Plugin_Admin
 *
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class Extension_WordPressSeo_Plugin_Admin {
	/**
	 * Runs the setup for the WordPress SEO extension.
	 *
	 * @return void
	 */
	public function run() {
		add_filter( 'w3tc_extension_plugin_links_wordpress-seo', array( $this, 'remove_settings' ) );
		add_action( 'w3tc_activate_extension_wordpress-seo', array( $this, 'activate' ) );
		add_action( 'w3tc_deactivate_extension_wordpress-seo', array( $this, 'deactivate' ) );
	}

	/**
	 * Removes a setting link for the WordPress SEO extension.
	 *
	 * @param array $links Array of current settings links.
	 *
	 * @return array Modified array of settings links.
	 */
	public function remove_settings( $links ) {
		array_pop( $links );
		return $links;
	}

	/**
	 * Retrieves extension details for WordPress SEO.
	 *
	 * @param array  $extensions Array of existing extensions.
	 * @param object $config     Configuration object.
	 *
	 * @return array Modified array of extensions.
	 */
	public static function w3tc_extensions( $extensions, $config ) {
		$message = array();
		if ( ! self::criteria_match() ) {
			$message[] = 'Optimizes "Yoast SEO" plugin, which is not active';
		}

		$extensions['wordpress-seo'] = array(
			'name'            => 'Yoast SEO',
			'author'          => 'W3 EDGE',
			'description'     => __( 'Configures W3 Total Cache to comply with Yoast SEO requirements automatically.', 'w3-total-cache' ),
			'author_uri'      => 'https://www.w3-edge.com/',
			'extension_uri'   => 'https://www.w3-edge.com/',
			'extension_id'    => 'wordpress-seo',
			'settings_exists' => true,
			'version'         => '0.1',
			'enabled'         => self::criteria_match(),
			'requirements'    => implode( ', ', $message ),
			'path'            => 'w3-total-cache/Extension_WordPressSeo_Plugin.php',
		);

		return $extensions;
	}

	/**
	 * Adds hooks related to the WordPress SEO extension.
	 *
	 * @param array $hooks Array of existing hooks.
	 *
	 * @return array Modified array of hooks.
	 */
	public static function w3tc_extensions_hooks( $hooks ) {
		if ( ! self::show_notice() ) {
			return $hooks;
		}

		if ( ! isset( $hooks['filters']['w3tc_notes'] ) ) {
			$hooks['filters']['w3tc_notes'] = array();
		}

		$hooks['filters']['w3tc_notes'][] = 'w3tc_notes_wordpress_seo';
		return $hooks;
	}

	/**
	 * Determines whether a notice should be shown for the WordPress SEO extension.
	 *
	 * @return bool True if the notice should be shown, false otherwise.
	 */
	private static function show_notice() {
		$config = Dispatcher::config();
		if ( $config->is_extension_active( 'wordpress-seo' ) ) {
			return false;
		}

		if ( ! self::criteria_match() ) {
			return false;
		}

		$state = Dispatcher::config_state();
		if ( $state->get_boolean( 'wordpress_seo.hide_note_suggest_activation' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Adds a note about activating the WordPress SEO extension for W3 Total Cache.
	 *
	 * @param array $notes Array of current notes.
	 *
	 * @return array Modified array of notes.
	 */
	public static function w3tc_notes_wordpress_seo( $notes ) {
		if ( ! self::show_notice() ) {
			return $notes;
		}

		$extension_id = 'wordpress-seo';

		$notes[ $extension_id ] = sprintf(
			// Translators: 1 opening HTML link to extensions page, 2 closing HTML link
			// Translators: 3 opening HTML link to activate extensionlink, 4 button link.
			__(
				'Activating the %1$sYoast SEO%2$s extension for W3 Total Cache may be helpful for your site. %3$sClick here%2$s to try it. %4$s',
				'w3-total-cache'
			),
			'<a href="' . Util_Ui::admin_url( 'admin.php?page=w3tc_extensions#' . $extension_id ) . '">',
			'</a>',
			'<a class="button" href="' . Util_Ui::url( array( 'w3tc_extensions_activate' => $extension_id ) ) . '">',
			Util_Ui::button_link(
				__( 'Hide this message', 'w3-total-cache' ),
				Util_Ui::url(
					array(
						'w3tc_default_config_state' => 'y',
						'key'                       => 'wordpress_seo.hide_note_suggest_activation',
						'value'                     => 'true',
					)
				)
			)
		);

		return $notes;
	}

	/**
	 * Checks whether the required criteria for WordPress SEO are met.
	 *
	 * @return bool True if criteria are met, false otherwise.
	 */
	private static function criteria_match() {
		return defined( 'WPSEO_VERSION' );
	}

	/**
	 * Activates the WordPress SEO extension for W3 Total Cache.
	 *
	 * @return void
	 *
	 * @throws \Exception If there is an issue during activation.
	 */
	public function activate() {
		try {
			$config = Dispatcher::config();
			$config->set( 'pgcache.prime.enabled', true );
			$config->set( 'pgcache.prime.sitemap', '/sitemap_index.xml' );
			$config->save();
		} catch ( \Exception $ex ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		}
	}

	/**
	 * Deactivates the WordPress SEO extension for W3 Total Cache.
	 *
	 * @return void
	 *
	 * @throws \Exception If there is an issue during deactivation.
	 */
	public function deactivate() {
		try {
			$config = Dispatcher::config();
			$config->set( 'pgcache.prime.enabled', false );
			$config->save();
		} catch ( \Exception $ex ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		}
	}
}
