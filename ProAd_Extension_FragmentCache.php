<?php
namespace W3TC;



class ProAd_Extension_FragmentCache {
	/**
	 * Called from outside, to get extension's details
	 */
	static public function w3tc_extensions( $extensions, $config ) {
		$requirements = array();

		$extensions['fragmentcache'] = array (
			'name' => 'Fragment Cache',
			'author' => 'W3 EDGE',
			'description' => 'Caching of page fragments.',
			'author_uri' => 'https://www.w3-edge.com/',
			'extension_uri' => 'https://www.w3-edge.com/',
			'extension_id' => 'fragmentcache',
			'pro_feature' => true,
			'pro_excerpt' => __( 'Increase the performance of dynamic sites that cannot benefit from the caching of entire pages.', 'w3-total-cache' ),
			'pro_description' => array(
				__( 'Fragment caching extends the core functionality of WordPress by enabling caching policies to be set on groups of objects that are cached. This allows you to optimize various elements in themes and plugins to use caching to save resources and reduce response times. You can also use caching methods like Memcached or Redis (for example) to scale. Instructions for use are available in the FAQ available under the help menu. This feature also gives you control over the caching policies by the group as well as visibility into the configuration by extending the WordPress Object API with additional functionality.', 'w3-total-cache' ),
				__( 'Fragment caching is a powerful, but advanced feature. If you need help, take a look at our premium support, customization and audit services.', 'w3-total-cache' ),
			),
			'settings_exists' => true,
			'version' => '1.0',
			'enabled' => false,
			'requirements' => implode( ', ', $requirements ),
			'active_frontend_own_control' => true,
			'path' => 'w3-total-cache/ProAd_Extension_FragmentCache.php'
		);

		return $extensions;
	}
}
