<?php
/**
 * File: Extension_ImageOptimizer_Plugin_Admin.php
 *
 * @since X.X.X
 */

namespace W3TC;

/**
 * Class: Extension_ImageOptimizer_Plugin_Admin
 *
 * @since X.X.X
 */
class Extension_ImageOptimizer_Plugin_Admin {
	/**
	 * Configuration.
	 *
	 * @since X.X.X
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * Image Optimizer API class object.
	 *
	 * @since X.X.X
	 *
	 * @var Extension_ImageOptimizer_API
	 */
	 private $api;

	/**
	 * Constructor.
	 *
	 * @since X.X.X
	 */
	function __construct() {
		$this->config = Dispatcher::config();
	}

	/**
	 * Get extension information.
	 *
	 * @since X.X.X
	 * @static
	 *
	 * @param  array $extensions Extensions.
	 * @param  array $config Configuration.
	 * @return array
	 */
	public static function w3tc_extensions( $extensions, $config ) {
		$extensions['optimager'] = array(
			'name'             => 'Image Optimizer Service',
			'author'           => 'W3 EDGE',
			'description'      => __(
				'Adds image optimization service options to the media library.',
				'w3-total-cache'
			),
			'author_uri'       => 'https://www.w3-edge.com/',
			'extension_uri'    => 'https://www.w3-edge.com/',
			'extension_id'     => 'optimager',
			'settings_exists'  => true,
			'version'          => '1.0',
			'enabled'          => true,
			'disabled_message' => '',
			'requirements'     => '',
			'path'             => 'w3-total-cache/Extension_ImageOptimizer_Plugin.php',
		);

		return $extensions;
	}

	/**
	 * Load the admin extension.
	 *
	 * @since X.X.X
	 * @static
	 */
	static public function w3tc_extension_load_admin() {
		$o = new Extension_ImageOptimizer_Plugin_Admin();

		add_action( 'w3tc_extension_page_optimager', array( $o, 'w3tc_extension_page_optimager' ) );

		/**
		 * Filters the Media list table columns.
		 *
		 * @since 2.5.0
		 *
		 * @param string[] $posts_columns An array of columns displayed in the Media list table.
		 * @param bool     $detached      Whether the list table contains media not attached
		 *                                to any posts. Default true.
		 */
		add_filter( 'manage_media_columns', array( $o, 'add_media_column' ) );
	}

	/**
	 * Load the extension settings page view.
	 *
	 * @since X.X.X
	 */
	public function w3tc_extension_page_optimager() {
		$c = $this->config;

		require W3TC_DIR . '/Extension_ImageOptimizer_Page_View.php';
	}

	/**
	 * Add image optimization controls to the Media Library table in list view.
	 *
	 * @since X.X.X
	 *
	 * @param string[] $posts_columns An array of columns displayed in the Media list table.
	 * @param bool     $detached      Whether the list table contains media not attached
	 *                                to any posts. Default true.
	 */
	public function add_media_column( $posts_columns, $detached = true ) {


		return $posts_columns;
	}
}
