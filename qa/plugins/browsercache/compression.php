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

if ( ! defined( 'DONOTCACHEPAGE' ) ) {
	define( 'DONOTCACHEPAGE', true );
}

require __DIR__ . '/wp-load.php';

if ( ! function_exists( 'w3tc_qa_pagecache_key_resolve' ) ) {
	require defined( 'W3TC_DIR' ) ? W3TC_DIR . '/qa/plugins/pagecache-key.php' : __DIR__ . '/../pagecache-key.php';
}

$url             = $_REQUEST['url'];
$wp_content_path = $_REQUEST['wp_content_path'];
$engine          = $_REQUEST['engine'];

$plain_key = w3tc_qa_pagecache_key_resolve(
	$url,
	array(
		'compression'   => '',
		'blog_id'       => isset( $_REQUEST['blog_id'] ) ? (int) $_REQUEST['blog_id'] : 0,
		'user_agent'    => w3tc_qa_pagecache_default_user_agent(),
	)
);
$gzip_key = w3tc_qa_pagecache_key_resolve(
	$url,
	array(
		'compression'     => 'gzip',
		'accept_encoding' => 'gzip',
		'blog_id'           => isset( $_REQUEST['blog_id'] ) ? (int) $_REQUEST['blog_id'] : 0,
		'user_agent'        => w3tc_qa_pagecache_default_user_agent(),
	)
);

if ( 'file_generic' === $engine ) {
	$plain_path = ! empty( $plain_key['enhanced_path'] )
		? $plain_key['enhanced_path']
		: w3tc_qa_pagecache_key_enhanced_path( $wp_content_path, $plain_key['page_key'] );
	$gzip_path  = ! empty( $gzip_key['enhanced_path'] )
		? $gzip_key['enhanced_path']
		: w3tc_qa_pagecache_key_enhanced_path( $wp_content_path, $gzip_key['page_key'] );

	echo file_exists( $plain_path ) ? 'plain found ' : 'plain not found ';
	echo file_exists( $gzip_path ) ? 'gzip found ' : 'gzip not found ';
	exit;
}

$v = w3tc_qa_pagecache_cache_find(
	$url,
	array(
		'compression' => '',
		'blog_id'     => isset( $_REQUEST['blog_id'] ) ? (int) $_REQUEST['blog_id'] : 0,
		'user_agent'  => w3tc_qa_pagecache_default_user_agent(),
	)
);

echo ( is_array( $v ) && ! empty( $v['content'] ) ) ? 'plain found ' : 'plain not found ';

$v_gzip = w3tc_qa_pagecache_cache_find(
	$url,
	array(
		'compression'     => 'gzip',
		'accept_encoding' => 'gzip',
		'blog_id'         => isset( $_REQUEST['blog_id'] ) ? (int) $_REQUEST['blog_id'] : 0,
		'user_agent'      => w3tc_qa_pagecache_default_user_agent(),
		'exact'           => true,
	)
);

echo ( is_array( $v_gzip ) && ! empty( $v_gzip['content'] ) ) ? 'gzip found ' : 'gzip not found ';
