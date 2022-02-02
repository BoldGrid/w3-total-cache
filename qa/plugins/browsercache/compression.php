<?php
/**
 * File: compression.php
 *
 * Browser compression.
 *
 * Template Name: Browser cache: Compression
 * Template Post Type: post, page
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput, WordPress.WP.GlobalVariablesOverride.Prohibited
 */

require __DIR__ . '/wp-load.php';

if ( ! defined( 'DONOTCACHEPAGE' ) ) {
	define( 'DONOTCACHEPAGE', true );
}

$blog_id         = $_REQUEST['blog_id'];
$url             = $_REQUEST['url'];
$wp_content_path = $_REQUEST['wp_content_path'];
$parsed          = wp_parse_url( $url );
$scheme          = $parsed['scheme'];
$host_port       = strtolower( $parsed['host'] ) . ( isset( $parsed['port'] ) ? ':' . $parsed['port'] : '' );
$path            = $parsed['path'];
$content         = '';
$engine          = $_REQUEST['engine'];

if ( 'file_generic' === $engine ) {
	$cache_path = $wp_content_path . 'cache/page_enhanced/' . $host_port . $path . '_index' .
		( 'https' === $scheme ? '_ssl' : '' ) . '.html';

	echo file_exists( $cache_path ) ? 'plain found ' : 'plain not found ';

	$cache_path = $wp_content_path . 'cache/page_enhanced/' . $host_port . $path . '_index' .
		( 'https' === $scheme ? '_ssl' : '' ) . '.html_gzip';

	echo file_exists( $cache_path ) ? 'gzip found ' : 'gzip not found ';
	exit;
}

$cache_key = md5( $host_port . $path );

if ( 'https' === $scheme ) {
	$cache_key .= '_ssl';
}

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

	default:
		break;
}

$v = $instance->get( $cache_key );

echo ! empty( $v['content'] ) ? 'plain found ' : 'plain not found ';

$v_gzip = $instance->get( $cache_key . '_gzip' );

echo ! empty( $v_gzip['content'] ) ? 'gzip found ' : 'gzip not found ';
