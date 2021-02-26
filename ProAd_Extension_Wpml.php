<?php
namespace W3TC;

class ProAd_Extension_Wpml {
	static public function w3tc_extensions( $extensions, $config ) {
		$base_plugin_active = self::base_plugin_active();
		$disabled_message = '';

		$requirements = array();
		if ( !$base_plugin_active ) {
			$requirements[] = 'Ensure "WPML" plugin compatibility, which is not currently active.';
		}

		$extensions['proad_wpml'] = array(
			'name' => 'WPML',
			'author' => 'W3 EDGE',
			'description' => __( 'Improves page caching interoperability with WPML.',
				'w3-total-cache' ),
			'author_uri' => 'https://www.w3-edge.com/',
			'extension_uri' => 'https://www.w3-edge.com/',
			'extension_id' => 'wpml',
			'pro_feature' => true,
			'pro_excerpt' => __( 'Improve the caching performance of websites localized by WPML.', 'w3-total-cache'),
			'pro_description' => array(
				__( 'Localization is a type of personalization that makes websites more difficult to scale. This extension reduces the response time of websites localized by WPML.', 'w3-total-cache')
			),
			'settings_exists' => false,
			'version' => '0.1',
			'enabled' => false,
			'disabled_message' => $disabled_message,
			'requirements' => implode( ', ', $requirements ),
			'path' => 'w3-total-cache/ProAd_Extension_Wpml.php'
		);

		return $extensions;
	}

	static public function base_plugin_active() {
		return defined( 'ICL_SITEPRESS_VERSION' );
	}

	static private function show_notice() {
		$config = Dispatcher::config();

		if ( !self::base_plugin_active() ) {
			return false;
		}

		$state = Dispatcher::config_state();
		if ( $state->get_boolean( 'wpml.hide_note_suggest_activation' ) ) {
			return false;
		}

		return true;
	}

	static public function w3tc_notes( $notes ) {
		if ( !self::show_notice() ) {
			return $notes;
		}

		$extension_id = 'proad_wpml';

		$config = Dispatcher::config();
		$activate_text = 'Available after <a href="#" class="button-buy-plugin" data-src="wpml_requirements3">upgrade</a>. ';

		$notes[$extension_id] = sprintf(
			__( 'Activating the <a href="%s">WPML</a> extension for W3 Total Cache may be helpful for your site. %s%s',
				'w3-total-cache' ),
			Util_Ui::admin_url( 'admin.php?page=w3tc_extensions#' . $extension_id ),
			$activate_text,
			Util_Ui::button_link(
				__( 'Hide this message', 'w3-total-cache' ),
				Util_Ui::url( array(
						'w3tc_default_config_state' => 'y',
						'key' => 'wpml.hide_note_suggest_activation',
						'value' => 'true' ) ) ) );

		return $notes;
	}
}
