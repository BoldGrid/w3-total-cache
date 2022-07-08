<?php
/**
 * File: garbage-collection.php
 *
 * Object cache: Garbage collection test.
 *
 * Template Name: Object cache: Garbage collection
 * Template Post Type: post, page
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput, WordPress.WP.GlobalVariablesOverride.Prohibited
 */

require __DIR__ . '/wp-load.php';

$action = $_REQUEST['action'];
$group  = $_REQUEST['group'];
$id     = 'test';
$data   = 'object cache test';

switch ( $action ) {
	case 'setCache':
		echo wp_cache_set( $id, $data, $group ) ? 'setCache ok' : 'setCache error';
		die;

	case 'getCache':
		$blog_id   = $_REQUEST['blog_id'];
		$url       = $_REQUEST['url'];
		$parsed    = wp_parse_url( $url );
		$scheme    = $parsed['scheme'];
		$host      = strtolower( $parsed['host'] );
		$cache_key = $blog_id . $group . $id;

		$instance = new \W3TC\Cache_File(
			array(
				'section'         => 'object',
				'locking'         => false,
				'flush_timelimit' => 3,
				'blog_id'         => $blog_id,
				'module'          => 'object',
				'host'            => $host,
				'instance_id'     => \W3TC\Util_Environment::instance_id(),
			)
		);

		$storage_key = $instance->get_item_key( $cache_key );
		$sub_path    = $instance->_get_path( $storage_key );
		$cache_dir   = \W3TC\Util_Environment::cache_blog_dir( 'object', $blog_id );
		$path        = $cache_dir . DIRECTORY_SEPARATOR . $sub_path;

		echo file_exists( $path ) ? 'cache exists' : 'cache not exists';
		die;

	case 'flush':
		// Taking garbage collection value in seconds.
		$cronjob   = wp_get_schedule( 'w3_objectcache_cleanup' );
		$schedules = wp_get_schedules();
		$seconds   = $schedules['w3_objectcache_cleanup']['interval'];

		echo esc_html( $cronjob ) . ' ' . esc_html( $seconds );

		do_action( 'w3_objectcache_cleanup' );

		die;
}
