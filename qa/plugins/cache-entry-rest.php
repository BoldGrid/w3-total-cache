<?php
/**
 * File: cache-entry-rest.php
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput, WordPress.WP.GlobalVariablesOverride.Prohibited
 */

define( 'DONOTCACHEPAGE', true );

require __DIR__ . '/wp-load.php';

$blog_id         = $_REQUEST['blog_id'];
$url             = $_REQUEST['url'];
$wp_content_path = $_REQUEST['wp_content_path'];
$parsed          = wp_parse_url( $url );
$scheme          = $parsed['scheme'];
$host_port       = strtolower( $parsed['host'] ) . ( isset( $parsed['port'] ) ? ':' . $parsed['port'] : '' );
$path            = $parsed['path'];
$content         = '';
$engine          = $_REQUEST['engine'];
$cache_key       = md5( $host_port . $path );

if ( 'https' === $scheme ) {
	$cache_key .= '_ssl';
}

echo esc_html( 'checking ' . $host_port . $path . ' blog ' . $blog_id . ' cache key ' . $cache_key . ' ' );

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
		$instance = new \W3TC\Cache_File(
			array(
				'section'         => 'page',
				'cache_dir'       => W3TC_CACHE_PAGE_ENHANCED_DIR . DIRECTORY_SEPARATOR . $host_port,
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
		echo 'Unknown engine';
		exit;
}

$v = $instance->get( $cache_key, 'rest' );

if ( ! isset( $v['content'] ) ) {
	echo 'no entry found';
	exit;
}

$v['content'] = 'Test of cache';

$instance->set( $cache_key, $v, 100, 'rest' );

// Try to read again.
$v       = $instance->get( $cache_key, 'rest' );
$content = $v['content'];

echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
