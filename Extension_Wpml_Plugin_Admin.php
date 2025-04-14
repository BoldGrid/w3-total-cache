<?php
/**
 * File: Extension_Wpml_Plugin_Admin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_Wpml_Plugin_Admin
 */
class Extension_Wpml_Plugin_Admin {
	/**
	 * Adds a filter to display WPML-related notes.
	 *
	 * @return void
	 */
	public function run() {
		add_filter( 'w3tc_notes', array( $this, 'w3tc_notes' ) );
	}

	/**
	 * Modifies the notes array to include WPML-related information.
	 *
	 * @param array $notes {
	 *     Existing notes.
	 *
	 *     @type string $key Note identifier.
	 *     @type string $message Note message.
	 * }
	 *
	 * @return array Modified notes with WPML-related information added.
	 */
	public function w3tc_notes( $notes ) {
		$config   = Dispatcher::config();
		$settings = get_option( 'icl_sitepress_settings' );

		if (
			$config->get_boolean( 'pgcache.enabled' ) &&
			'file_generic' === $config->get_string( 'pgcache.engine' ) &&
			isset( $settings['language_negotiation_type'] ) &&
			3 === (int) $settings['language_negotiation_type']
		) {
			$state = Dispatcher::config_state();

			if ( ! $state->get_boolean( 'wpml.hide_note_language_negotiation_type' ) ) {
				$notes[] = sprintf(
					// Translators: 1 button link.
					__(
						'W3 Total Cache\'s Page caching cannot work effectively when WPML Language URL format is "Language name added as a parameter" used. Please consider another URL format. Visit the WPML -&gt; Languages settings. %1$s',
						'w3-total-cache'
					),
					Util_Ui::button_hide_note2(
						array(
							'w3tc_default_config_state' => 'y',
							'key'                       => 'wpml.hide_note_language_negotiation_type',
							'value'                     => 'true',
						)
					)
				);
			}
		}

		return $notes;
	}

	/**
	 * Registers WPML extension details.
	 *
	 * @param array  $extensions {
	 *     Existing list of extensions.
	 *
	 *     @type array $wpml {
	 *         Details for the WPML extension.
	 *
	 *         @type string   $name             Name of the extension.
	 *         @type string   $author           Author of the extension.
	 *         @type string   $description      Description of the extension.
	 *         @type string   $author_uri       URL to the author's website.
	 *         @type string   $extension_uri    URL to the extension's page.
	 *         @type string   $extension_id     Unique ID of the extension.
	 *         @type bool     $pro_feature      Whether this is a pro feature.
	 *         @type string   $pro_excerpt      Short description for the pro feature.
	 *         @type array    $pro_description  Detailed description for the pro feature.
	 *         @type bool     $settings_exists  Whether settings exist for the extension.
	 *         @type string   $version          Version of the extension.
	 *         @type bool     $enabled          Whether the extension is enabled.
	 *         @type string   $disabled_message Message displayed when the extension is disabled.
	 *         @type string   $requirements     List of requirements for enabling the extension.
	 *         @type string   $path             Path to the extension file.
	 *     }
	 * }
	 * @param object $config Configuration object.
	 *
	 * @return array Modified extensions with WPML details.
	 */
	public static function w3tc_extensions( $extensions, $config ) {
		$base_plugin_active = self::base_plugin_active();
		$enabled            = $base_plugin_active;
		$disabled_message   = '';

		$requirements = array();
		if ( ! $base_plugin_active ) {
			$requirements[] = 'Install and activate WPML or TranslatePress.';
		}

		if ( empty( $requirements ) && ! Util_Environment::is_w3tc_pro( $config ) ) {
			$enabled = false;
		}

		$extensions['wpml'] = array(
			'name'             => 'WPML',
			'author'           => 'W3 EDGE',
			'description'      => __( 'Improves page caching interoperability with WPML and TranslatePress.', 'w3-total-cache' ),
			'author_uri'       => 'https://www.w3-edge.com/',
			'extension_uri'    => 'https://www.w3-edge.com/',
			'extension_id'     => 'wpml',
			'pro_feature'      => true,
			'pro_excerpt'      => __( 'Improve the caching performance of websites localized by WPML.', 'w3-total-cache' ),
			'pro_description'  => array(
				__( 'Localization is a type of personalization that makes websites more difficult to scale. This extension reduces the response time of websites localized by WPML.', 'w3-total-cache' ),
			),
			'settings_exists'  => false,
			'version'          => '0.1',
			'enabled'          => $enabled,
			'disabled_message' => $disabled_message,
			'requirements'     => implode( ', ', $requirements ),
			'path'             => 'w3-total-cache/Extension_Wpml_Plugin.php',
		);

		return $extensions;
	}

	/**
	 * Checks if the base WPML or TranslatePress plugin is active.
	 *
	 * @return bool True if the base plugin is active, false otherwise.
	 */
	public static function base_plugin_active() {
		return defined( 'ICL_SITEPRESS_VERSION' ) || defined( 'TRP_PLUGIN_VERSION' );
	}

	/**
	 * Modifies the hooks to include the WPML notes filter.
	 *
	 * @param array $hooks {
	 *     Existing hooks.
	 *
	 *     @type array $filters {
	 *         List of filter hooks.
	 *
	 *         @type array $w3tc_notes WPML notes filter hooks.
	 *     }
	 * }
	 *
	 * @return array Modified hooks with WPML notes filter.
	 */
	public static function w3tc_extensions_hooks( $hooks ) {
		if ( ! self::show_notice() ) {
			return $hooks;
		}

		if ( ! isset( $hooks['filters']['w3tc_notes'] ) ) {
			$hooks['filters']['w3tc_notes'] = array();
		}

		$hooks['filters']['w3tc_notes'][] = 'w3tc_notes_wpml';
		return $hooks;
	}

	/**
	 * Determines whether to show the WPML activation notice.
	 *
	 * @return bool True if the notice should be shown, false otherwise.
	 */
	private static function show_notice() {
		$config = Dispatcher::config();
		if ( $config->is_extension_active( 'wpml' ) ) {
			return false;
		}

		if ( ! self::base_plugin_active() ) {
			return false;
		}

		$state = Dispatcher::config_state();
		if ( $state->get_boolean( 'wpml.hide_note_suggest_activation' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Modifies the notes array to include WPML activation suggestion.
	 *
	 * @param array $notes {
	 *     Existing notes.
	 *
	 *     @type string $wpml Message suggesting WPML activation.
	 * }
	 *
	 * @return array Modified notes with WPML activation suggestion.
	 */
	public static function w3tc_notes_wpml( $notes ) {
		if ( ! self::show_notice() ) {
			return $notes;
		}

		$extension_id = 'wpml';

		$config = Dispatcher::config();
		if ( ! Util_Environment::is_w3tc_pro( $config ) ) {
			$activate_text = 'Available after <a href="#" class="button-buy-plugin" data-src="wpml_requirements3">upgrade</a>. ';
		} else {
			$activate_text = sprintf(
				'<a class="button" href="%s">Click here</a> to try it. ',
				Util_Ui::url( array( 'w3tc_extensions_activate' => $extension_id ) )
			);
		}

		$notes[ $extension_id ] = sprintf(
			// Translators: 1 opening HTML link to extensions page, 2 closing HTML link, 3 activate text, 4 button link.
			__(
				'Activating the %1$sWPML%2$s extension for W3 Total Cache may be helpful for your site. %3$s%4$s',
				'w3-total-cache'
			),
			'<a href="' . Util_Ui::admin_url( 'admin.php?page=w3tc_extensions#' . $extension_id ) . '">',
			'</a>',
			$activate_text,
			Util_Ui::button_link(
				__( 'Hide this message', 'w3-total-cache' ),
				Util_Ui::url(
					array(
						'w3tc_default_config_state' => 'y',
						'key'                       => 'wpml.hide_note_suggest_activation',
						'value'                     => 'true',
					)
				)
			)
		);

		return $notes;
	}
}
