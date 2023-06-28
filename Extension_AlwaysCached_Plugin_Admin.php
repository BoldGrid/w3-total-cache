<?php
namespace W3TC;



class Extension_AlwaysCached_Plugin_Admin {
	/**
	 * @param unknown $extensions
	 * @param Config  $config
	 * @return mixed
	 */
	static public function w3tc_extensions( $extensions, $config ) {
		$extensions['alwayscached'] = array (
			'name' => 'Always Cached',
			'author' => 'W3 EDGE',
			'description' =>  __( 'Always cached.', 'w3-total-cache' ),
			'author_uri' => 'https://www.w3-edge.com/',
			'extension_uri' => 'https://www.w3-edge.com/',
			'extension_id' => 'alwayscached',
			'settings_exists' => true,
			'version' => '1.0',
			'enabled' => true,
			'requirements' => '',
			'path' => 'w3-total-cache/Extension_AlwaysCached_Plugin.php'
		);

		return $extensions;
	}



	function __construct() {
	}



	function run() {
		// own settings page.
		add_action( 'w3tc_extension_page_alwayscached', [
			'\W3TC\Extension_AlwaysCached_Page',
			'w3tc_extension_page_alwayscached'
		] );

		add_action( 'admin_print_scripts', [
			'\W3TC\Extension_AlwaysCached_Page',
			'admin_print_scripts',
		] );

		add_filter( 'w3tc_admin_actions', [ $this, 'w3tc_admin_actions' ] );

		add_action( 'w3tc_ajax', [
			'\W3TC\Extension_AlwaysCached_Page',
			'w3tc_ajax'
		] );
	}



	public function w3tc_admin_actions( $handlers ) {
		$handlers['alwayscached'] = 'Extension_AlwaysCached_AdminActions';
		return $handlers;
	}
}
