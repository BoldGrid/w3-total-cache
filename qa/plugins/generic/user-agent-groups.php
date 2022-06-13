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
 * phpcs:disable: WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput, WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.PHP.DevelopmentFunctions.error_log_var_dump
 */

if ( ! defined( 'DONOTCACHEPAGE' ) ) {
	define( 'DONOTCACHEPAGE', true );
}

require __DIR__ . '/wp-load.php';

$blog_id   = $_REQUEST['blog_id'];
$url       = $_REQUEST['url'];
$parsed    = wp_parse_url( $url );
$host_port = strtolower( $parsed['host'] ) . ( isset( $parsed['port'] ) ? ':' . $parsed['port'] : '' );
$path      = $parsed['path'];

/**
 * Get cache key.
 *
 * @param string $path      Path.
 * @param string $host_port Host port.
 * @param string $group     Group.
 * @return string
 */
function get_cache_key( $path, $host_port, $group ) {
	global $url, $engine;

	if ( 'file_generic' === $engine ) {
		// Make sure one slash is at the end.
		$extra = '';

		if ( '/' === substr( $path, strlen( $path ) - 1, 1 ) ) {
			$extra = '_slash';
		}

		// Return the cache key.
		return $host_port . $path . '_index' . $extra . ( empty( $group ) ? '' : '_' . $group ) .
			( preg_match( '/https/', $url ) ? '_ssl' : '' ) . '.html';
	}

	$cache_key = md5( $host_port . $path ) . ( empty( $group ) ? '' : '_' . $group );

	if ( preg_match( '/https/', $url ) ) {
		$cache_key .= '_ssl';
	};

	return $cache_key;
}

$engine     = $_REQUEST['engine'];
$cache_key1 = get_cache_key( $path, $host_port, 'test1' );
$cache_key2 = get_cache_key( $path, $host_port, '' );

switch ( $engine ) {
	case 'apc':
		$instance = new \W3TC\Cache_Apcu(
			array(
				'section'     => 'page',
				'blog_id'     => $blog_id,
				'module'      => 'pgcache',
				'host'        => '',
				'instance_id' => \W3TC\Util_Environment::instance_id(),
			)
		);
		break;

	case 'file':
		$instance = new \W3TC\Cache_File(
			array(
				'section'         => 'page',
				'locking'         => false,
				'flush_timelimit' => 100,
				'blog_id'         => $blog_id,
				'module'          => 'pgcache',
				'host'            => '',
				'instance_id'     => \W3TC\Util_Environment::instance_id(),
			)
		);
		break;

	case 'file_generic':
		$instance = new \W3TC\Cache_File_Generic(
			array(
				'cache_dir'       => W3TC_CACHE_PAGE_ENHANCED_DIR,
				'section'         => 'page',
				'locking'         => false,
				'flush_timelimit' => 100,
				'blog_id'         => $blog_id,
				'module'          => 'pgcache',
				'host'            => '',
				'instance_id'     => \W3TC\Util_Environment::instance_id(),
			)
		);
		break;

	case 'memcached':
		$params = array(
			'section'     => 'page',
			'servers'     => array( '127.0.0.1:11211' ),
			'blog_id'     => $blog_id,
			'module'      => 'pgcache',
			'host'        => '',
			'instance_id' => \W3TC\Util_Environment::instance_id(),
		);

		if ( class_exists( 'Memcached' ) ) {
			$instance = new \W3TC\Cache_Memcached( $params );
		} else {
			$instance = new \W3TC\Cache_Memcache( $params );
		}

		break;

	case 'redis':
		$instance = new \W3TC\Cache_Redis(
			array(
				'section'        => 'page',
				'servers'        => array( '127.0.0.1:6379' ),
				'dbid'           => 0,
				'password'       => '',
				'blog_id'        => $blog_id,
				'module'         => 'pgcache',
				'host'           => '',
				'instance_id'    => \W3TC\Util_Environment::instance_id(),
				'timeout'        => 0,
				'retry_interval' => 0,
				'read_timeout'   => 0,
			)
		);
		break;

	case 'xcache':
		$instance = new \W3TC\Cache_Xcache(
			array(
				'section'     => 'page',
				'blog_id'     => $blog_id,
				'module'      => 'pgcache',
				'host'        => '',
				'instance_id' => \W3TC\Util_Environment::instance_id(),
			)
		);
		break;
}

var_dump( $cache_key1 );
var_dump( $cache_key2 );

$v1 = $instance->get( $cache_key1 );
$v2 = $instance->get( $cache_key2 );

echo isset( $v1['content'] ) && isset( $v2['content'] ) ? 'ok' : 'error';
