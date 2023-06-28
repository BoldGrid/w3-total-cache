<?php
namespace W3TC;

/**
 * W3TC plugin to keep content always cached and regenerate in a queue
 * when needed
 */
class Extension_AlwaysCached_Plugin {
	/**
	 * Runs plugin
	 */
	public function run() {
		add_action( 'init', [ $this, 'init' ] );
		add_filter( 'w3tc_pagecache_flush_url',
			[ $this, 'w3tc_pagecache_flush_url' ] );
		add_filter( 'w3tc_pagecache_rules_apache_rewrite_cond',
			[ $this, 'w3tc_pagecache_rules_apache_rewrite_cond' ] );
		add_action( 'w3tc_environment_fix_on_wpadmin_request', [
			'\W3TC\Extension_AlwaysCached_Environment',
			'w3tc_environment_fix_on_wpadmin_request' ] );
		add_action( 'w3tc_environment_fix_on_event', [
			'\W3TC\Extension_AlwaysCached_Environment',
			'w3tc_environment_fix_on_event' ], 10, 2 );
		add_action( 'w3tc_environment_fix_after_deactivation', [
			'\W3TC\Extension_AlwaysCached_Environment',
			'w3tc_environment_fix_after_deactivation' ] );
	}



	public function init() {
		if (!empty($_SERVER['HTTP_W3TCALWAYSCACHED'])) {
			header('w3tc_test: here');
		}

		if ( isset( $_REQUEST['w3tc_alwayscached'] ) ) {
			Extension_AlwaysCached_Worker::run();
			exit();
		}
	}



	public function w3tc_pagecache_rules_apache_rewrite_cond( $rewrite_conditions ) {
		$rewrite_conditions .= "    RewriteCond %{HTTP:w3tcalwayscached} =\"\"\n";
		return $rewrite_conditions;
	}

	/* data format expected:
		'url' =>
		'cache' =>
		'mobile_groups' =>
		'referrer_groups' =>
		'cookies' =>
		'encryptions' =>
		'compressions' =>
		'group' =>
		'parent' => object with _get_page_key method
	*/
	public function w3tc_pagecache_flush_url( $data ) {
		// no support for mobile_groups, referrer_groups, cookies, group atm
		foreach ( $data['encryptions'] as $encryption ) {
			$page_key_extension = [
				'useragent' => $data['mobile_groups'][0],
				'referrer' => $data['referrer_groups'][0],
				'cookie' => $data['cookies'][0],
				'encryption' => $encryption,
				'compression' => false,
				'group' => $data['group']
			];

			$page_key = $data['parent']->_get_page_key(
				$page_key_extension, $data['url'] );

			if ( $data['cache']->exists( $page_key, $data['group'] ) ) {
				Extension_AlwaysCached_Queue::add( $page_key, $data['url'],
					$page_key_extension );
			}
		}

		return [];
	}
}

$p = new Extension_AlwaysCached_Plugin();
$p->run();

if ( is_admin() ) {
	$p = new Extension_AlwaysCached_Plugin_Admin();
	$p->run();
}
