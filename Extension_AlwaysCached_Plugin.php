<?php
/**
 * File: Extension_AlwaysCached_Plugin.php
 *
 * AlwaysCached plugin admin controller.
 *
 * @since 2.5.1
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * AlwaysCached Plugin.
 *
 * @since 2.5.1
 */
class Extension_AlwaysCached_Plugin {
	/**
	 * Run method for AlwaysCached.
	 *
	 * @since 2.5.1
	 *
	 * @return void
	 */
	public function run() {
		if ( ! Extension_AlwaysCached_Plugin::is_enabled() ) {
			return null;
		}

		add_action( 'init', array( $this, 'init' ) );
		add_filter( 'w3tc_admin_bar_menu', array( $this, 'w3tc_admin_bar_menu' ) );
		add_filter( 'w3tc_pagecache_flush_url',
			array( $this, 'w3tc_pagecache_flush_url' ),
			1000 );
		add_filter( 'w3tc_pagecache_flush_all_groups',
			array( $this, 'w3tc_pagecache_flush_all_groups' ),
			1000 );
		add_filter( 'w3tc_pagecache_rules_apache_rewrite_cond',
			array( $this, 'w3tc_pagecache_rules_apache_rewrite_cond' ) );
	}

	/**
	 * Init for AlwaysCached.
	 *
	 * @since 2.5.1
	 *
	 * @return void
	 */
	public function init() {
		if ( isset( $_REQUEST['w3tc_alwayscached'] ) ) {
			Extension_AlwaysCached_Worker::run();
			exit();
		}
	}

	/**
	 * Adds admin bar menu links.
	 *
	 * @since 2.5.1
	 *
	 * @param array $menu_items Menu items.
	 *
	 * @return array
	 */
	public function w3tc_admin_bar_menu( $menu_items ) {
		if ( ! is_admin() ) {
			$menu_items['10025.always_cached'] = array(
				'id'     => 'w3tc_flush_current_page',
				'parent' => 'w3tc',
				'title'  => __( 'Regenerate Current Page', 'w3-total-cache' ),
				'href'   => wp_nonce_url(
					admin_url( 'admin.php?page=w3tc_dashboard&amp;w3tc_alwayscached_regenerate&amp;post_id=' . Util_Environment::detect_post_id() ),
					'w3tc'
				),
			);
		}

		return $menu_items;
	}

	/**
	 * Adds AlwaysCached Apache rules.
	 *
	 * @since 2.5.1
	 *
	 * @param string $rewrite_conditions Apache rules buffer.
	 *
	 * @return string
	 */
	public function w3tc_pagecache_rules_apache_rewrite_cond( $rewrite_conditions ) {
		$rewrite_conditions .= "    RewriteCond %{HTTP:w3tcalwayscached} =\"\"\n";
		return $rewrite_conditions;
	}

	/**
	 * Adds AlwaysCached Apache rules.
	 *
	 * Data format expected:
	 * array(
	 *  'url' =>
	 *  'cache' =>
	 *  'mobile_groups' =>
	 *  'referrer_groups' =>
	 *  'cookies' =>
	 *  'encryptions' =>
	 *  'compressions' =>
	 *  'group' =>
	 *  'parent' => object with _get_page_key method
	 * )
	 *
	 * @since 2.5.1
	 *
	 * @param array $data Data for flush request.
	 *
	 * @return array
	 */
	public function w3tc_pagecache_flush_url( $data ) {
		// no support for mobile_groups, referrer_groups, cookies, group atm.
		foreach ( $data['encryptions'] as $encryption ) {
			$page_key_extension = array(
				'useragent'   => $data['mobile_groups'][0],
				'referrer'    => $data['referrer_groups'][0],
				'cookie'      => $data['cookies'][0],
				'encryption'  => $encryption,
				'compression' => false,
				'group'       => $data['group'],
			);

			$page_key = $data['parent']->_get_page_key( $page_key_extension, $data['url'] );

			if ( $data['cache']->exists( $page_key, $data['group'] ) ) {
				Extension_AlwaysCached_Queue::add(
					$page_key,
					$data['url'],
					$page_key_extension
				);
			}
		}

		return array();
	}

	public function w3tc_pagecache_flush_all_groups( $groups ) {
		// only empty group catched, which is regular pages
		$c = Dispatcher::config();
		if ( !$c->get_boolean( array( 'alwayscached', 'flush_all' ) ) ) {
			return $groups;
		}

		if ( in_array( '', $groups ) ) {
			Extension_AlwaysCached_Queue::add(
				':flush_group.regenerate',
				'',
				array( 'group' => '' ),
				50
			);
			Extension_AlwaysCached_Queue::add(
				':flush_group.remainder',
				'',
				array( 'group' => '', 'before_time' => time() ),
				25
			);

			$groups = array_filter(
				$groups,
				function( $i) {
					return !empty($i);
				} );
		}

		return $groups;
	}

	/**
	 * Gets the enabled status of the extension.
	 *
	 * @since 2.5.1
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$config            = Dispatcher::config();
		$extensions_active = $config->get_array( 'extensions.active' );
		return Util_Environment::is_w3tc_pro( $config ) && array_key_exists( 'alwayscached', $extensions_active );
	}
}

$p = new Extension_AlwaysCached_Plugin();
$p->run();

if ( is_admin() ) {
	$p = new Extension_AlwaysCached_Plugin_Admin();
	$p->run();
}
