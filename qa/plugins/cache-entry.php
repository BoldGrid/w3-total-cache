<?php
/**
 * File: cache-entry.php
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput, WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.OutputNotEscaped
 */

if ( ! defined( 'DONOTCACHEPAGE' ) ) {
	define( 'DONOTCACHEPAGE', true );
}

require __DIR__ . '/wp-load.php';

if ( ! function_exists( 'w3tc_qa_pagecache_key_resolve' ) ) {
	require defined( 'W3TC_DIR' ) ? W3TC_DIR . '/qa/plugins/pagecache-key.php' : __DIR__ . '/pagecache-key.php';
}

$url              = $_REQUEST['url'];
$wp_content_path  = $_REQUEST['wp_content_path'];
$page_key_postfix = isset( $_REQUEST['page_key_postfix'] ) ? $_REQUEST['page_key_postfix'] : '';
$parsed           = wp_parse_url( $url );
$host_port        = strtolower( $parsed['host'] ) . ( isset( $parsed['port'] ) ? ':' . $parsed['port'] : '' );
$path             = isset( $parsed['path'] ) ? $parsed['path'] : '/';
$engine           = $_REQUEST['engine'];

$key_info = w3tc_qa_pagecache_key_resolve(
	$url,
	array(
		'page_key_postfix' => $page_key_postfix,
		'blog_id'          => isset( $_REQUEST['blog_id'] ) ? (int) $_REQUEST['blog_id'] : 0,
		'user_agent'       => w3tc_qa_pagecache_default_user_agent(),
	)
);

if ( 'file_generic' === $engine ) {
	$cache_path = w3tc_qa_pagecache_key_enhanced_path( $wp_content_path, $key_info['page_key'] );

	if ( ! file_exists( $cache_path ) ) {
		echo 'cache file missing: ' . esc_html( $cache_path );
		exit;
	}

	$content = 'Test of cache' . file_get_contents( $cache_path );

	file_put_contents( $cache_path, $content );

	echo $content;
	exit;
}

$probe_options = array(
	'page_key_postfix' => $page_key_postfix,
	'blog_id'          => isset( $_REQUEST['blog_id'] ) ? (int) $_REQUEST['blog_id'] : 0,
	'user_agent'       => w3tc_qa_pagecache_default_user_agent(),
);

$located = w3tc_qa_pagecache_cache_locate( $url, $probe_options );

if ( ! is_array( $located ) || ! isset( $located['page_key'] ) ) {
	echo esc_html(
		'checking ' . $host_port . $path . ' cache key ' . $key_info['page_key']
	);
	echo ' no entry found';
	exit;
}

echo esc_html(
	'checking ' . $host_port . $path . ' cache key ' . $located['page_key']
);

$located['value']['content'] = 'Test of cache' . $located['value']['content'];
unset( $located['value']['expires_at'], $located['value']['key_version'] );

w3tc_qa_pagecache_cache_set(
	$located['page_key'],
	$located['value'],
	100,
	$located['page_group'],
	$located['cache_group']
);

$v = w3tc_qa_pagecache_cache_get(
	$located['page_key'],
	$located['page_group'],
	$located['cache_group']
);

if ( ! is_array( $v ) || ! isset( $v['content'] ) ) {
	echo ' no entry found';
	exit;
}

echo $v['content'];
