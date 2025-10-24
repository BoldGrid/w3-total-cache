<?php
/**
 * File: Extension_WordPressSeo_Plugin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_WordPressSeo_Plugin
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class Extension_WordPressSeo_Plugin {
	/**
	 * Config
	 *
	 * @var Config
	 */
	private $_config;

	/**
	 * Constructs the class instance and initializes the configuration.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Runs the plugin by adding necessary filters based on configuration.
	 *
	 * @return void
	 */
	public function run() {
		if ( $this->_config->get_boolean( 'cdn.enabled' ) ) {
			add_filter( 'wpseo_xml_sitemap_img_src', array( $this, 'wpseo_cdn_filter' ) );
		}
	}

	/**
	 * Filters the image source URL for WP SEO plugin to use a CDN.
	 *
	 * @param string $uri The original image URI to be processed.
	 *
	 * @return string The modified image URL with CDN.
	 */
	public function wpseo_cdn_filter( $uri ) {
		$common      = Dispatcher::component( 'Cdn_Core' );
		$cdn         = $common->get_cdn();
		$parsed      = wp_parse_url( $uri );
		$path        = $parsed['path'];
		$remote_path = $common->uri_to_cdn_uri( $path );
		$new_url     = $cdn->format_url( $remote_path );

		return $new_url;
	}
}

$p = new Extension_WordPressSeo_Plugin();
$p->run();

if ( is_admin() ) {
	$p = new Extension_WordPressSeo_Plugin_Admin();
	$p->run();
}
