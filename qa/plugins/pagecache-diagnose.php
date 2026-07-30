<?php
/**
 * QA probe: page cache key resolution and backend entry diagnostics.
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
 */

if ( ! defined( 'DONOTCACHEPAGE' ) ) {
	define( 'DONOTCACHEPAGE', true );
}

if ( ! defined( 'ABSPATH' ) ) {
	require __DIR__ . '/wp-load.php';
}

if ( ! function_exists( 'w3tc_qa_pagecache_key_resolve' ) ) {
	require defined( 'W3TC_DIR' ) ? W3TC_DIR . '/qa/plugins/pagecache-key.php' : __DIR__ . '/pagecache-key.php';
}

$probe_url = getenv( 'W3TC_QA_PROBE_URL' );
if ( ! $probe_url && isset( $_REQUEST['url'] ) ) {
	$probe_url = wp_unslash( $_REQUEST['url'] );
}
if ( ! $probe_url ) {
	$probe_url = home_url( '/' );
}

$user_agent = w3tc_qa_pagecache_default_user_agent();
if ( isset( $_REQUEST['user_agent'] ) ) {
	$user_agent = wp_unslash( $_REQUEST['user_agent'] );
} elseif ( isset( $_SERVER['HTTP_USER_AGENT'] ) && '' !== $_SERVER['HTTP_USER_AGENT'] ) {
	$user_agent = wp_unslash( $_SERVER['HTTP_USER_AGENT'] );
}

$options = array(
	'user_agent' => $user_agent,
);

$key_info = w3tc_qa_pagecache_key_resolve( $probe_url, $options );
$found    = w3tc_qa_pagecache_cache_find( $probe_url, $options );
$config   = \W3TC\Dispatcher::config();

$enhanced_exists = false;
if ( 'file_generic' === $config->get_string( 'pgcache.engine' ) && ! empty( $key_info['enhanced_path'] ) ) {
	$enhanced_exists = file_exists( $key_info['enhanced_path'] );
}

$enhanced_dir_writable = false;
if ( 'file_generic' === $config->get_string( 'pgcache.engine' ) && ! empty( $key_info['enhanced_path'] ) ) {
	$enhanced_dir = dirname( $key_info['enhanced_path'] );
	if ( is_dir( $enhanced_dir ) ) {
		$enhanced_dir_writable = is_writable( $enhanced_dir );
	}
}

echo wp_json_encode(
	array(
		'url'                   => $probe_url,
		'user_agent'            => $user_agent,
		'page_key'              => $key_info['page_key'],
		'page_group'            => $key_info['page_group'],
		'cache_group'           => $key_info['cache_group'],
		'enhanced_path'         => $key_info['enhanced_path'],
		'enhanced_exists'       => $enhanced_exists,
		'enhanced_dir_writable' => $enhanced_dir_writable,
		'cache_found'           => is_array( $found ) && isset( $found['content'] ),
		'pgcache_enabled'       => $config->get_boolean( 'pgcache.enabled' ),
		'pgcache_engine'        => $config->get_string( 'pgcache.engine' ),
		'pgcache_cache_home'    => $config->get_boolean( 'pgcache.cache.home' ),
		'http_host'             => isset( $_SERVER['HTTP_HOST'] ) ? wp_unslash( $_SERVER['HTTP_HOST'] ) : '',
	)
);
