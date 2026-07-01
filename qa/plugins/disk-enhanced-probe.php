<?php
/**
 * QA probe: Disk Enhanced cache file and rewrite rule diagnostics.
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

$key_info = w3tc_qa_pagecache_key_resolve( $probe_url );

$variants = array(
	$key_info['page_key'],
	preg_replace( '/\.html$/', '.html_gzip', $key_info['page_key'] ),
	preg_replace( '/\.html$/', '.html_br', $key_info['page_key'] ),
);

$exists = array();
foreach ( array_unique( $variants ) as $variant ) {
	$path               = w3tc_qa_pagecache_key_enhanced_path( WP_CONTENT_DIR . '/', $variant );
	$exists[ $variant ] = file_exists( $path );
}

$htaccess_path = \W3TC\Util_Environment::site_path() . '.htaccess';
$htaccess      = file_exists( $htaccess_path ) ? file_get_contents( $htaccess_path ) : '';

$apache_prefix = '';
if ( preg_match( '/RewriteCond "%\{DOCUMENT_ROOT\}([^"]+_index[^"]*\.html)"/', $htaccess, $prefix_match ) ) {
	$apache_prefix = $prefix_match[1];
}

$enhanced_dir = '';
if ( ! empty( $key_info['enhanced_path'] ) ) {
	$enhanced_dir = dirname( $key_info['enhanced_path'] );
}

$enhanced_dir_exists    = ( '' !== $enhanced_dir && is_dir( $enhanced_dir ) );
$enhanced_dir_writable  = ( $enhanced_dir_exists && is_writable( $enhanced_dir ) );
$enhanced_root          = defined( 'W3TC_CACHE_PAGE_ENHANCED_DIR' ) ? W3TC_CACHE_PAGE_ENHANCED_DIR : '';
$enhanced_root_writable = ( '' !== $enhanced_root && is_dir( $enhanced_root ) && is_writable( $enhanced_root ) );

$enhanced_dir_list = array();
if ( $enhanced_dir_exists ) {
	$enhanced_dir_list = array_values( array_diff( scandir( $enhanced_dir ), array( '.', '..' ) ) );
}

$apache_cond_plain = '';
if ( preg_match( '/RewriteCond "%\{DOCUMENT_ROOT\}([^"]+_index[^"]*\.html)"/', $htaccess, $cond_match ) ) {
	$apache_cond_plain = $cond_match[1];
}

$old_path = $key_info['enhanced_path'] . '_old';
$old_exists = ( '' !== $key_info['enhanced_path'] && file_exists( $old_path ) );
$old_mtime  = $old_exists ? @filemtime( $old_path ) : false;
$old_age_sec = ( $old_mtime ? time() - $old_mtime : null );
$plain_exists = ! empty( $exists[ $key_info['page_key'] ] );

/**
 * Resolve the cond the way Apache does: DOCUMENT_ROOT is ABSPATH minus the
 * (network) site URI (subdirectory installs serve ABSPATH at e.g. /wp/), and
 * W3TC_URI_PATH_SLASH comes from the probed URL path.
 */
$apache_cond_resolved = '';
if ( '' !== $apache_cond_plain ) {
	$doc_root  = realpath( untrailingslashit( ABSPATH ) );
	$site_uri  = \W3TC\Util_Environment::url_to_uri( network_site_url( '/' ) );
	if ( $doc_root && '' !== $site_uri && substr( $doc_root, -strlen( $site_uri ) ) === $site_uri ) {
		$doc_root = substr( $doc_root, 0, -strlen( $site_uri ) );
	} else {
		$home_path = realpath( untrailingslashit( \W3TC\Util_Environment::site_path() ) );
		if ( $home_path ) {
			$doc_root = $home_path;
		}
	}

	$probe_uri_path = trim( (string) wp_parse_url( $probe_url, PHP_URL_PATH ), '/' );
	$uri_path_slash = ( '' !== $probe_uri_path ) ? $probe_uri_path . '/' : '';

	/** Apache %{HTTP_HOST} carries the port, matching the cache-key host (see pagecache-key.php). */
	$probe_parts = wp_parse_url( $probe_url );
	$cond_host   = isset( $probe_parts['host'] )
		? $probe_parts['host'] . ( isset( $probe_parts['port'] ) ? ':' . $probe_parts['port'] : '' )
		: \W3TC\Util_Environment::host_port();

	if ( $doc_root ) {
		$apache_cond_resolved = $doc_root . str_replace(
			array(
				'%{HTTP_HOST}',
				'%{ENV:W3TC_URI_PATH_SLASH}',
				'%{ENV:W3TC_SLASH}',
				'%{ENV:W3TC_SSL}',
				'%{ENV:W3TC_PREVIEW}',
			),
			array(
				$cond_host,
				$uri_path_slash,
				'_slash',
				'',
				'',
			),
			$apache_cond_plain
		);
	}
}

echo wp_json_encode(
	array(
		'url'                    => $probe_url,
		'page_key'               => $key_info['page_key'],
		'enhanced_path'          => $key_info['enhanced_path'],
		'variants'               => $exists,
		'enhanced_dir'           => $enhanced_dir,
		'enhanced_dir_exists'    => $enhanced_dir_exists,
		'enhanced_dir_writable'  => $enhanced_dir_writable,
		'enhanced_dir_list'      => $enhanced_dir_list,
		'enhanced_root_writable' => $enhanced_root_writable,
		'old_path'               => $old_path,
		'old_exists'             => $old_exists,
		'old_age_sec'            => $old_age_sec,
		'stale_old_only'         => ( $old_exists && ! $plain_exists ),
		'apache_cond_plain'      => $apache_cond_plain,
		'apache_cond_resolved'   => $apache_cond_resolved,
		'apache_cond_exists'     => ( '' !== $apache_cond_resolved && file_exists( $apache_cond_resolved ) ),
		'htaccess_core'          => ( false !== strpos( $htaccess, W3TC_MARKER_BEGIN_PGCACHE_CORE ) ),
		'htaccess_uri'           => ( false !== strpos( $htaccess, 'W3TC_URI_PATH_SLASH' ) ),
		'apache_prefix_sample'   => $apache_prefix,
	)
);
