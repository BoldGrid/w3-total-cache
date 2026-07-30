<?php
/**
 * Example extension admin bootstrap.
 *
 * @package W3TCExample
 */

namespace W3TCExample;

/**
 * Backend functionality of an extension.
 *
 * Loaded only for wp-admin requests.
 */
class Extension_Example_Admin {
	/**
	 * W3tc_extensions filter handler.
	 *
	 * @param array        $extensions Extension descriptors to fill.
	 * @param \W3TC\Config $w3tc_config     W3 Total Cache configuration.
	 *
	 * @return array
	 */
	public static function w3tc_extensions( $extensions, $w3tc_config ) {
		$extensions['example'] = array(
			'name'            => 'Example Extension',
			'author'          => 'W3 EDGE',
			'description'     => __( 'Example extension', 'w3-total-cache' ),
			'author_uri'      => 'https://www.w3-edge.com/',
			'extension_uri'   => 'https://www.w3-edge.com/',
			'extension_id'    => 'example',
			'settings_exists' => true,
			'version'         => '1.0',
			'enabled'         => true,
			'requirements'    => '',
			'path'            => 'w3-total-cache-example/Extension_Example.php',
		);

		return $extensions;
	}

	/**
	 * Entry point of extension for wp-admin requests.
	 *
	 * Called from Extension_Example.php.
	 *
	 * @return void
	 */
	public function run() {
		add_action(
			'w3tc_extension_page_example',
			array(
				$this,
				'w3tc_extension_page',
			)
		);

		add_action(
			'w3tc_config_ui_save',
			array(
				$this,
				'w3tc_config_ui_save',
			),
			10,
			2
		);

		add_action(
			'w3tc_widget_setup',
			array(
				$this,
				'w3tc_widget_setup',
			)
		);

		add_action(
			'w3tc_deactivate_extension_example',
			array(
				$this,
				'w3tc_deactivate_extension',
			)
		);
	}

	/**
	 * Show settings page.
	 *
	 * @return void
	 */
	public function w3tc_extension_page() {
		include __DIR__ . '/Extension_Example_Page_View.php';
	}

	/**
	 * Get control when configuration is changed by user.
	 *
	 * @param \W3TC\Config $w3tc_config     New configuration.
	 * @param \W3TC\Config $old_config Previous configuration.
	 *
	 * @return void
	 */
	public function w3tc_config_ui_save( $w3tc_config, $old_config ) {
		if ( $w3tc_config->get( array( 'example', 'is_title_postfix' ) ) !=
			$old_config->get( array( 'example', 'is_title_postfix' ) ) ||
			$w3tc_config->get( array( 'example', 'title_postfix' ) ) !=
			$old_config->get( array( 'example', 'title_postfix' ) ) ) {
			w3tc_flush_posts();
		}
	}

	/**
	 * Registers widget on W3 Total Cache Dashboard page.
	 *
	 * @return void
	 */
	public function w3tc_widget_setup() {
		$w3tc_screen = get_current_screen();
		add_meta_box(
			'example',
			'example',
			array( $this, 'widget_content' ),
			$w3tc_screen,
			'normal',
			'core'
		);
	}

	/**
	 * Renders content of widget.
	 *
	 * @return void
	 */
	public function widget_content() {
		echo "Example extension's widget";
	}

	/**
	 * Called when extension is deactivated.
	 *
	 * @return void
	 */
	public function w3tc_deactivate_extension() {
	}
}
