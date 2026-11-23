<?php
/**
 * File: user-agent-groups.php
 *
 * Generic: User-agent groups.
 *
 * Template Name: Generic: User-agent groups
 * Template Post Type: post, page
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput, WordPress.WP.GlobalVariablesOverride.Prohibited
 */

if ( ! defined( 'DONOTCACHEPAGE' ) ) {
	define( 'DONOTCACHEPAGE', true );
}

require __DIR__ . '/wp-load.php';

if ( ! function_exists( 'w3tc_qa_pagecache_key_resolve' ) ) {
	require defined( 'W3TC_DIR' ) ? W3TC_DIR . '/qa/plugins/pagecache-key.php' : __DIR__ . '/../pagecache-key.php';
}

$url = $_REQUEST['url'];

$probe_options = array();
if ( isset( $_REQUEST['blog_id'] ) ) {
	$probe_options['blog_id'] = (int) $_REQUEST['blog_id'];
}

$chrome_ua = 'Mozilla/5.0 (X11; Linux i686) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/40.0.2214.111';
$safari_ua = 'Mozilla/5.0 (X11; Linux i686) AppleWebKit/537.36 (KHTML, like Gecko) Safari/537.36';

$safari_options = array_merge( $probe_options, array( 'user_agent' => $safari_ua ) );
$chrome_options = array_merge( $probe_options, array( 'user_agent' => $chrome_ua ) );

$v1 = w3tc_qa_pagecache_cache_find( $url, $safari_options );
$v2 = w3tc_qa_pagecache_cache_find( $url, $chrome_options );

if ( is_array( $v1 ) && isset( $v1['content'] ) && is_array( $v2 ) && isset( $v2['content'] ) ) {
	echo 'ok';
	exit;
}

/**
 * Scans Redis for page-cache keys so failure output shows what runtime wrote.
 *
 * @return array
 */
function w3tc_qa_uag_redis_keys() {
	if ( ! class_exists( 'Redis' ) ) {
		return array( 'phpredis not available' );
	}

	$config  = \W3TC\Dispatcher::config();
	$servers = $config->get_array( 'pgcache.redis.servers' );
	$keys    = array();

	foreach ( $servers as $server ) {
		$parts = explode( ':', $server );
		$redis = new Redis();

		try {
			$redis->connect( $parts[0], isset( $parts[1] ) ? (int) $parts[1] : 6379, 2 );

			$dbid = $config->get_integer( 'pgcache.redis.dbid' );
			if ( $dbid ) {
				$redis->select( $dbid );
			}

			$it = null;
			while ( true ) {
				$batch = $redis->scan( $it, '*pgcache*', 200 );
				if ( false === $batch ) {
					break;
				}
				foreach ( $batch as $k ) {
					$keys[] = $k;
				}
				if ( 0 === $it ) {
					break;
				}
			}
		} catch ( Exception $e ) {
			$keys[] = 'scan failed: ' . $e->getMessage();
		}
	}

	sort( $keys );

	return $keys;
}

$config      = \W3TC\Dispatcher::config();
$diag        = array(
	'safari_found'    => is_array( $v1 ) && isset( $v1['content'] ) ? true : false,
	'chrome_found'    => is_array( $v2 ) && isset( $v2['content'] ) ? true : false,
	'safari_resolved' => w3tc_qa_pagecache_key_resolve( $url, $safari_options ),
	'chrome_resolved' => w3tc_qa_pagecache_key_resolve( $url, $chrome_options ),
	'probe_options'   => $probe_options,
	'is_multisite'    => is_multisite(),
	'util_blog_id'    => \W3TC\Util_Environment::blog_id(),
	'instance_id'     => \W3TC\Util_Environment::instance_id(),
	'pgcache_engine'  => $config->get_string( 'pgcache.engine' ),
	'mobile_enabled'  => $config->get_boolean( 'mobile.enabled' ),
	'mobile_rgroups'  => $config->get_array( 'mobile.rgroups' ),
	'redis_keys'      => w3tc_qa_uag_redis_keys(),
);

echo 'error ' . wp_json_encode( $diag, JSON_PRETTY_PRINT );
