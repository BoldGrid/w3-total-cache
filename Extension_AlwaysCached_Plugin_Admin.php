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
	}
}
