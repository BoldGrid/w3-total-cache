<?php
/**
 * File: garbage-collection.php
 *
 * Database cache: Garbage collection.
 *
 * Template Name: Database cache: Garbage collection
 * Template Post Type: post, page
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Recommended, WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.DB
 */

require __DIR__ . '/wp-load.php';

global $wpdb;

$blog_id = $_REQUEST['blog_id'];
$url     = $_REQUEST['url'];
$parsed  = wp_parse_url( $url );
$host    = strtolower( $parsed['host'] );
$action  = $_REQUEST['action'];

switch ( $action ) {
	case 'add_cache':
		$title = $wpdb->get_var( "Select post_title from $wpdb->posts where ID = 1" );
		echo '<div id="added">';
		echo $title ? 'ok' : 'error';
		echo '</div>';
		break;

	case 'get_path':
		$key = md5( "Select post_title from $wpdb->posts where ID = 1" );
		$c   = new \W3TC\Cache_File(
			array(
				'section'         => 'db',
				'locking'         => false,
				'flush_timelimit' => 100,
				'blog_id'         => $blog_id,
				'module'          => 'dbcache',
				'host'            => $host,
				'instance_id'     => \W3TC\Util_Environment::instance_id(),
				'use_wp_hash'     => true,
			)
		);

		$cache       = $c->get( $key );
		$storage_key = sprintf( 'w3tc_%d_%s_%d_%s_%s', \W3TC\Util_Environment::instance_id(), $host, $blog_id, 'dbcache', $key );
		$sub_path    = $c->_get_path( $storage_key );
		$cache_dir   = \W3TC\Util_Environment::cache_blog_dir( 'db', $blog_id );
		$path        = $cache_dir . DIRECTORY_SEPARATOR . 'singletables' . DIRECTORY_SEPARATOR . $sub_path;

		echo '<div id="path">' . $path . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		break;

	case 'garbage_collection':
		// Taking garbage collection value in seconds.
		$cronjob   = wp_get_schedule( 'w3_dbcache_cleanup' );
		$schedules = wp_get_schedules();
		$seconds   = $schedules['w3_dbcache_cleanup']['interval'];

		echo '<div id="schedule">' . $cronjob . ' ' . $seconds . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		do_action( 'w3_dbcache_cleanup' );
		break;
}
