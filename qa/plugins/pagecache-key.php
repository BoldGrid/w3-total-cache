<?php
/**
 * QA helper: resolve PgCache page cache keys via PgCache_ContentGrabber.
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( function_exists( 'w3tc_qa_pagecache_key_resolve' ) ) {
	return;
}

use W3TC\Dispatcher;
use W3TC\PgCache_ContentGrabber;
use W3TC\Util_Environment;

/**
 * Returns request URI (path and query) for a full URL.
 *
 * @since 2.10.0
 *
 * @param string $url Full URL.
 * @return string
 */
function w3tc_qa_pagecache_key_request_uri( $url ) {
	$parts = wp_parse_url( $url );
	$uri   = isset( $parts['path'] ) ? $parts['path'] : '/';

	if ( ! empty( $parts['query'] ) ) {
		$uri .= '?' . $parts['query'];
	}

	return $uri;
}

/**
 * Applies a legacy QA postfix to a page key (e.g. AMP `_amp` on the extension segment).
 *
 * @since 2.10.0
 *
 * @param string $page_key Page cache key.
 * @param string $postfix  Suffix for the extension segment.
 * @return string
 */
function w3tc_qa_pagecache_key_apply_postfix( $page_key, $postfix ) {
	if ( empty( $postfix ) ) {
		return $page_key;
	}

	if ( preg_match( '/^(.*?)(\.(?:html|xml))((?:_(?:gzip|br))?)$/', $page_key, $matches ) ) {
		return $matches[1] . $postfix . $matches[2] . $matches[3];
	}

	if ( preg_match( '/^(.+?)((?:_(?:gzip|br))?)$/', $page_key, $matches ) ) {
		return $matches[1] . $postfix . $matches[2];
	}

	return $page_key . $postfix;
}

/**
 * Marks a reflector accessible on PHP < 8.1 (no-op since 8.1, deprecated since 8.5).
 *
 * @since 2.10.0
 *
 * @param ReflectionProperty|ReflectionMethod $reflector Reflection property or method.
 * @return void
 */
function w3tc_qa_pagecache_key_set_accessible( $reflector ) {
	if ( \PHP_VERSION_ID < 80100 ) {
		$reflector->setAccessible( true );
	}
}

/**
 * Sets $_SERVER values used by PgCache_ContentGrabber key generation.
 *
 * @since 2.10.0
 *
 * @param string $url     Full URL.
 * @param array  $options Optional probe options (see w3tc_qa_pagecache_key_resolve()).
 * @return array Saved superglobal values keyed by server index.
 */
function w3tc_qa_pagecache_key_bootstrap_request( $url, $options = array() ) {
	$parts  = wp_parse_url( $url );
	$saved  = array();
	$server = array(
		'REQUEST_URI'            => w3tc_qa_pagecache_key_request_uri( $url ),
		'REQUEST_METHOD'         => 'GET',
		'HTTPS'                  => ( isset( $parts['scheme'] ) && 'https' === $parts['scheme'] ) ? 'on' : '',
		'HTTP_X_FORWARDED_PROTO' => ( isset( $parts['scheme'] ) && 'https' === $parts['scheme'] ) ? 'https' : '',
	);

	if ( isset( $parts['host'] ) ) {
		$server['HTTP_HOST'] = $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
	}

	if ( array_key_exists( 'user_agent', $options ) ) {
		if ( null === $options['user_agent'] || '' === $options['user_agent'] ) {
			$server['HTTP_USER_AGENT'] = '';
		} else {
			$server['HTTP_USER_AGENT'] = $options['user_agent'];
		}
	}

	if ( array_key_exists( 'accept_encoding', $options ) ) {
		$server['HTTP_ACCEPT_ENCODING'] = $options['accept_encoding'];
	}

	foreach ( $server as $key => $value ) {
		if ( array_key_exists( $key, $_SERVER ) ) {
			$saved[ $key ] = $_SERVER[ $key ];
		} else {
			$saved[ $key ] = null;
		}

		if ( '' === $value && null === $saved[ $key ] ) {
			unset( $_SERVER[ $key ] );
		} else {
			$_SERVER[ $key ] = $value;
		}
	}

	return $saved;
}

/**
 * Restores $_SERVER values saved by w3tc_qa_pagecache_key_bootstrap_request().
 *
 * @since 2.10.0
 *
 * @param array $saved Saved superglobal values.
 * @return void
 */
function w3tc_qa_pagecache_key_restore_request( $saved ) {
	foreach ( $saved as $key => $value ) {
		if ( null === $value ) {
			unset( $_SERVER[ $key ] );
		} else {
			$_SERVER[ $key ] = $value;
		}
	}
}

/**
 * Returns the HTTP host PgCache uses for cache keys (matches Util_Environment::host_port()).
 *
 * @since 2.10.0
 *
 * @return string
 */
function w3tc_qa_pagecache_host_from_request() {
	if ( ! empty( $_SERVER['HTTP_HOST'] ) ) {
		return htmlspecialchars( stripslashes( wp_unslash( $_SERVER['HTTP_HOST'] ) ) );
	}

	return '';
}

/**
 * Drops the cached ContentGrabber singleton created during wp-load.
 *
 * @since 2.10.0
 *
 * @return void
 */
function w3tc_qa_pagecache_key_reset_grabber_singleton() {
	$reflection = new ReflectionClass( Dispatcher::class );
	$instances  = $reflection->getProperty( 'instances' );
	w3tc_qa_pagecache_key_set_accessible( $instances );
	$value      = $instances->getValue();
	unset( $value['PgCache_ContentGrabber'], $value['Mobile_UserAgent'], $value['Mobile_Referrer'] );
	$instances->setValue( null, $value );
}

/**
 * Aligns W3TC multisite blog context with an explicit probe blog ID.
 *
 * switch_to_blog() alone does not refresh Util_Environment::blog_id(); the
 * memoized $w3tc_w3_current_blog_id global must be reset so Cache_* backends
 * use the same blog_id prefix as runtime page-cache writes.
 *
 * @since 2.10.0
 *
 * @param int $blog_id Target blog ID.
 * @return void
 */
function w3tc_qa_pagecache_key_enter_blog_context( $blog_id ) {
	if ( ! \function_exists( 'is_multisite' ) || ! is_multisite() ) {
		return;
	}

	Util_Environment::reset_microcache();
	switch_to_blog( (int) $blog_id );
	$GLOBALS['w3tc_w3_current_blog_id'] = (int) $blog_id;
	Dispatcher::reset_config();
	w3tc_qa_pagecache_key_reset_grabber_singleton();
}

/**
 * Restores multisite blog context after w3tc_qa_pagecache_key_enter_blog_context().
 *
 * @since 2.10.0
 *
 * @return void
 */
function w3tc_qa_pagecache_key_leave_blog_context() {
	if ( ! \function_exists( 'is_multisite' ) || ! is_multisite() ) {
		return;
	}

	restore_current_blog();
	Util_Environment::reset_microcache();
	Dispatcher::reset_config();
	w3tc_qa_pagecache_key_reset_grabber_singleton();
}

/**
 * Returns a ContentGrabber aligned with the cached page request.
 *
 * @since 2.10.0
 *
 * @param string $url Full URL.
 * @return PgCache_ContentGrabber
 */
function w3tc_qa_pagecache_key_grabber_for_url( $url ) {
	$grabber    = Dispatcher::component( 'PgCache_ContentGrabber' );
	$reflection = new ReflectionClass( $grabber );

	$request_uri_property = $reflection->getProperty( '_request_uri' );
	w3tc_qa_pagecache_key_set_accessible( $request_uri_property );
	$request_uri_property->setValue( $grabber, w3tc_qa_pagecache_key_request_uri( $url ) );

	$fragments_property = $reflection->getProperty( '_request_url_fragments' );
	w3tc_qa_pagecache_key_set_accessible( $fragments_property );
	$fragments_property->setValue(
		$grabber,
		array(
			'host' => w3tc_qa_pagecache_host_from_request(),
		)
	);

	$preprocess_method = $reflection->getMethod( '_preprocess_request_uri' );
	w3tc_qa_pagecache_key_set_accessible( $preprocess_method );
	$preprocess_method->invoke( $grabber );

	return $grabber;
}

/**
 * Reads page key extension data from PgCache_ContentGrabber.
 *
 * @since 2.10.0
 *
 * @param PgCache_ContentGrabber $grabber Content grabber instance.
 * @return array
 */
function w3tc_qa_pagecache_key_get_extension( PgCache_ContentGrabber $grabber ) {
	$reflection = new ReflectionClass( $grabber );

	$key_extension_method = $reflection->getMethod( '_get_key_extension' );
	w3tc_qa_pagecache_key_set_accessible( $key_extension_method );

	return $key_extension_method->invoke( $grabber );
}

/**
 * Runs the same key extraction path used when writing page cache entries.
 *
 * @since 2.10.0
 *
 * @param PgCache_ContentGrabber $grabber            Content grabber instance.
 * @param array                  $page_key_extension Page key extension array.
 * @param bool                   $with_filter        Whether extract filters should run.
 * @return array {
 *     @type string $page_key   Extracted page key.
 *     @type string $page_group Extracted page group.
 * }
 */
function w3tc_qa_pagecache_key_extract( PgCache_ContentGrabber $grabber, array $page_key_extension, $with_filter = true ) {
	$reflection = new ReflectionClass( $grabber );

	$extract_method = $reflection->getMethod( '_set_extract_page_key' );
	w3tc_qa_pagecache_key_set_accessible( $extract_method );
	$extract_method->invoke( $grabber, $page_key_extension, $with_filter );

	$page_key_property = $reflection->getProperty( '_page_key' );
	w3tc_qa_pagecache_key_set_accessible( $page_key_property );

	$page_group_property = $reflection->getProperty( '_page_group' );
	w3tc_qa_pagecache_key_set_accessible( $page_group_property );

	return array(
		'page_key'   => $page_key_property->getValue( $grabber ),
		'page_group' => $page_group_property->getValue( $grabber ),
	);
}

/**
 * Returns the pgcache backend used for a page group.
 *
 * @since 2.10.0
 *
 * @param string $page_group Page cache group.
 * @return object
 */
function w3tc_qa_pagecache_get_backend( $page_group = '' ) {
	$grabber    = Dispatcher::component( 'PgCache_ContentGrabber' );
	$reflection = new ReflectionClass( $grabber );
	$method     = $reflection->getMethod( '_get_cache' );
	w3tc_qa_pagecache_key_set_accessible( $method );

	return $method->invoke( $grabber, $page_group );
}

/**
 * Reads a page cache entry using the same backend instance as PgCache.
 *
 * @since 2.10.0
 *
 * @param string      $page_key    Page cache key.
 * @param string      $page_group  Filtered page cache group passed to Cache_*->get().
 * @param string|null $cache_group Unfiltered group used to select the backend (defaults to page_group).
 * @return mixed
 */
function w3tc_qa_pagecache_cache_get( $page_key, $page_group = '', $cache_group = null ) {
	if ( null === $cache_group ) {
		$cache_group = $page_group;
	}

	return w3tc_qa_pagecache_get_backend( $cache_group )->get( $page_key, $page_group );
}

/**
 * Stores a page cache entry using the same backend instance as PgCache.
 *
 * @since 2.10.0
 *
 * @param string      $page_key    Page cache key.
 * @param mixed       $value       Cache payload.
 * @param int         $expire      Expiration in seconds.
 * @param string      $page_group  Filtered page cache group passed to Cache_*->set().
 * @param string|null $cache_group Unfiltered group used to select the backend (defaults to page_group).
 * @return bool
 */
function w3tc_qa_pagecache_cache_set( $page_key, $value, $expire, $page_group = '', $cache_group = null ) {
	if ( null === $cache_group ) {
		$cache_group = $page_group;
	}

	return w3tc_qa_pagecache_get_backend( $cache_group )->set( $page_key, $value, $expire, $page_group );
}

/**
 * Default User-Agent used by QA page-cache browser tests (see qa/lib/sys.js).
 *
 * @since 2.10.0
 *
 * @return string
 */
function w3tc_qa_pagecache_default_user_agent() {
	return 'Mozilla/5.0 (X11; Linux i686) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/40.0.2214.111';
}

/**
 * Returns the mobile user-agent group for a UA string without Mobile_Base static caching.
 *
 * @since 2.10.0
 *
 * @param string $user_agent HTTP User-Agent header value.
 * @return string Active group name or empty string.
 */
function w3tc_qa_pagecache_mobile_useragent_group( $user_agent ) {
	$config = Dispatcher::config();

	if ( ! $config->get_boolean( 'mobile.enabled' ) ) {
		return '';
	}

	$groups    = $config->get_array( 'mobile.rgroups' );
	$user_agent = htmlspecialchars( $user_agent );

	foreach ( $groups as $group_name => $group_config ) {
		if ( empty( $group_config['enabled'] ) || empty( $group_config['agents'] ) ) {
			continue;
		}

		foreach ( (array) $group_config['agents'] as $pattern ) {
			if ( $pattern && preg_match( '~' . $pattern . '~i', $user_agent ) ) {
				return $group_name;
			}
		}
	}

	return '';
}

/**
 * Applies QA probe defaults before key resolution.
 *
 * @since 2.10.0
 *
 * @param array $options Optional probe options (see w3tc_qa_pagecache_key_resolve()).
 * @return array
 */
function w3tc_qa_pagecache_normalize_probe_options( $options ) {
	if ( ! array_key_exists( 'user_agent', $options ) ) {
		if ( isset( $_SERVER['HTTP_USER_AGENT'] ) && '' !== $_SERVER['HTTP_USER_AGENT'] ) {
			$options['user_agent'] = wp_unslash( $_SERVER['HTTP_USER_AGENT'] );
		} else {
			$options['user_agent'] = w3tc_qa_pagecache_default_user_agent();
		}
	}

	if ( ! array_key_exists( 'compression', $options ) ) {
		$options['compression'] = '';
	}

	if ( ! array_key_exists( 'accept_encoding', $options ) ) {
		$options['accept_encoding'] = '';
	}

	return $options;
}

/**
 * Reads a cached page via PgCache_ContentGrabber extraction (same path as runtime HIT/MISS).
 *
 * @since 2.10.0
 *
 * @param PgCache_ContentGrabber $grabber   Configured content grabber.
 * @param array                  $extension Page key extension array.
 * @return array|null
 */
function w3tc_qa_pagecache_extract_cached_page( PgCache_ContentGrabber $grabber, array $extension ) {
	$reflection = new ReflectionClass( $grabber );

	$ext_prop = $reflection->getProperty( '_page_key_extension' );
	w3tc_qa_pagecache_key_set_accessible( $ext_prop );
	$ext_prop->setValue( $grabber, $extension );

	$extract_method = $reflection->getMethod( '_extract_cached_page' );
	w3tc_qa_pagecache_key_set_accessible( $extract_method );

	foreach ( array( false, true ) as $with_filter ) {
		$data = $extract_method->invoke( $grabber, $with_filter );
		if ( is_array( $data ) && isset( $data['content'] ) ) {
			return $data;
		}
	}

	return null;
}

/**
 * Bootstraps PgCache probe state for a URL.
 *
 * @since 2.10.0
 *
 * @param string $url     Full URL of the cached page.
 * @param array  $options Optional probe options (see w3tc_qa_pagecache_key_resolve()).
 * @return array {
 *     @type PgCache_ContentGrabber $grabber   Configured content grabber.
 *     @type array                  $extension Page key extension array.
 *     @type array                  $saved     Saved $_SERVER values for restore.
 *     @type bool                   $switched  Whether switch_to_blog() was used.
 * }
 */
function w3tc_qa_pagecache_probe_context( $url, $options = array() ) {
	$options  = w3tc_qa_pagecache_normalize_probe_options( $options );
	$switched = false;

	if ( isset( $options['blog_id'] ) && \function_exists( 'is_multisite' ) && is_multisite() ) {
		w3tc_qa_pagecache_key_enter_blog_context( (int) $options['blog_id'] );
		$switched = true;
	}

	$saved = w3tc_qa_pagecache_key_bootstrap_request( $url, $options );
	w3tc_qa_pagecache_key_reset_grabber_singleton();

	$grabber   = w3tc_qa_pagecache_key_grabber_for_url( $url );
	$extension = w3tc_qa_pagecache_key_get_extension( $grabber );

	if ( array_key_exists( 'user_agent', $options ) ) {
		$extension['useragent'] = w3tc_qa_pagecache_mobile_useragent_group( $options['user_agent'] );
	}

	if ( ! empty( $options['extension'] ) && is_array( $options['extension'] ) ) {
		$extension = array_merge( $extension, $options['extension'] );
	}

	if ( array_key_exists( 'compression', $options ) ) {
		$extension['compression'] = $options['compression'];
	}

	return array(
		'grabber'   => $grabber,
		'extension' => $extension,
		'saved'     => $saved,
		'switched'  => $switched,
	);
}

/**
 * Restores probe state bootstrapped by w3tc_qa_pagecache_probe_context().
 *
 * @since 2.10.0
 *
 * @param array $context Context array from w3tc_qa_pagecache_probe_context().
 * @return void
 */
function w3tc_qa_pagecache_probe_context_restore( array $context ) {
	w3tc_qa_pagecache_key_restore_request( $context['saved'] );

	if ( ! empty( $context['switched'] ) ) {
		w3tc_qa_pagecache_key_leave_blog_context();
	}
}

/**
 * Locates a cached page entry and the keys PgCache uses to read it.
 *
 * @since 2.10.0
 *
 * @param string $url     Full URL of the cached page.
 * @param array  $options Optional probe options (see w3tc_qa_pagecache_key_resolve()).
 *                        Set `exact` => true to skip gzip-to-plain fallback in _extract_cached_page().
 * @return array|null {
 *     @type string $page_key    Cache key for Cache_*->get().
 *     @type string $page_group  Filtered cache group for Cache_*->get().
 *     @type string $cache_group Unfiltered group used to select the backend.
 *     @type array  $value       Cache payload array.
 * }
 */
function w3tc_qa_pagecache_cache_locate( $url, $options = array() ) {
	// A legacy postfix (e.g. AMP `_amp`) identifies a distinct cache variant whose key
	// cannot be derived from the request URI alone: the runtime `_amp` suffix is only
	// appended when the request carried the AMP marker (see PgCache_ContentGrabber::
	// _get_page_key()), but probes resolve the variant from the bare page URL. Resolve
	// and read that key directly so a co-existing non-postfix entry (the regular page,
	// cached when the test first visits the URL without the AMP marker) cannot shadow
	// the lookup and cause the QA marker to land on the wrong entry.
	if ( ! empty( $options['page_key_postfix'] ) ) {
		$key_info = w3tc_qa_pagecache_key_resolve( $url, $options );
		$value    = w3tc_qa_pagecache_cache_get(
			$key_info['page_key'],
			$key_info['page_group'],
			$key_info['cache_group']
		);

		if ( is_array( $value ) && isset( $value['content'] ) ) {
			return array(
				'page_key'    => $key_info['page_key'],
				'page_group'  => $key_info['page_group'],
				'cache_group' => $key_info['cache_group'],
				'value'       => $value,
			);
		}

		return null;
	}

	$exact   = ! empty( $options['exact'] );
	$context = w3tc_qa_pagecache_probe_context( $url, $options );
	$grabber = $context['grabber'];
	$extension = $context['extension'];

	if ( ! $exact ) {
		$value = w3tc_qa_pagecache_extract_cached_page( $grabber, $extension );
		if ( is_array( $value ) && isset( $value['content'] ) ) {
			$reflection = new ReflectionClass( $grabber );

			$page_key_property = $reflection->getProperty( '_page_key' );
			w3tc_qa_pagecache_key_set_accessible( $page_key_property );
			$page_key = $page_key_property->getValue( $grabber );

			$page_group_property = $reflection->getProperty( '_page_group' );
			w3tc_qa_pagecache_key_set_accessible( $page_group_property );
			$page_group = $page_group_property->getValue( $grabber );

			if ( ! empty( $page_key ) ) {
				w3tc_qa_pagecache_probe_context_restore( $context );

				return array(
					'page_key'    => $page_key,
					'page_group'  => $page_group,
					'cache_group' => $extension['group'],
					'value'       => $value,
				);
			}
		}
	}

	foreach ( array( false, true ) as $with_filter ) {
		$extracted = w3tc_qa_pagecache_key_extract( $grabber, $extension, $with_filter );
		if ( empty( $extracted['page_key'] ) ) {
			continue;
		}

		$value = w3tc_qa_pagecache_cache_get(
			$extracted['page_key'],
			$extracted['page_group'],
			$extension['group']
		);

		if ( is_array( $value ) && isset( $value['content'] ) ) {
			w3tc_qa_pagecache_probe_context_restore( $context );

			return array(
				'page_key'    => $extracted['page_key'],
				'page_group'  => $extracted['page_group'],
				'cache_group' => $extension['group'],
				'value'       => $value,
			);
		}
	}

	w3tc_qa_pagecache_probe_context_restore( $context );

	return null;
}

/**
 * Prepends a marker string to a cached page payload (QA cache mutation).
 *
 * @since 2.10.0
 *
 * @param string $url     Full URL of the cached page.
 * @param string $prefix  String to prepend to cached HTML.
 * @param array  $options Optional probe options (see w3tc_qa_pagecache_key_resolve()).
 * @return array|null Updated cache payload or null when no entry exists.
 */
function w3tc_qa_pagecache_cache_prepend_content( $url, $prefix, $options = array() ) {
	$located = w3tc_qa_pagecache_cache_locate( $url, $options );
	if ( ! is_array( $located ) || ! isset( $located['value']['content'] ) ) {
		return null;
	}

	$value            = $located['value'];
	$value['content'] = $prefix . $value['content'];
	unset( $value['expires_at'], $value['key_version'] );

	w3tc_qa_pagecache_cache_set(
		$located['page_key'],
		$value,
		100,
		$located['page_group'],
		$located['cache_group']
	);

	return w3tc_qa_pagecache_cache_get(
		$located['page_key'],
		$located['page_group'],
		$located['cache_group']
	);
}

/**
 * Locates a cached page payload using PgCache lookup semantics.
 *
 * @since 2.10.0
 *
 * @param string $url     Full URL of the cached page.
 * @param array  $options Optional probe options (see w3tc_qa_pagecache_key_resolve()).
 *                        Set `exact` => true to skip gzip-to-plain fallback in _extract_cached_page().
 * @return array|null Cache payload array or null when no entry exists.
 */
function w3tc_qa_pagecache_cache_find( $url, $options = array() ) {
	$located = w3tc_qa_pagecache_cache_locate( $url, $options );

	if ( is_array( $located ) && isset( $located['value'] ) ) {
		return $located['value'];
	}

	return null;
}

/**
 * Resolves page cache key metadata for QA backend probes.
 *
 * @since 2.10.0
 *
 * @param string $url     Full URL of the cached page.
 * @param array  $options {
 *     Optional overrides.
 *
 *     @type string $user_agent       HTTP User-Agent for mobile/referrer groups.
 *     @type int    $blog_id          Multisite blog ID for cache backend selection.
 *     @type string $accept_encoding  HTTP Accept-Encoding header value.
 *     @type mixed  $compression      Force compression segment (`false`, `''`, `gzip`, `br`).
 *     @type string $page_key_postfix Legacy postfix appended to the extension segment (e.g. `_amp`).
 *     @type array  $extension        Merge into the page key extension array.
 * }
 * @return array {
 *     @type string $page_key      Cache key for Cache_*->get().
 *     @type string $page_group    Filtered cache group for Cache_*->get().
 *     @type string $cache_group   Unfiltered cache group for backend selection.
 *     @type string $enhanced_path Absolute path to a Disk Enhanced plain HTML file, if applicable.
 * }
 */
function w3tc_qa_pagecache_key_resolve( $url, $options = array() ) {
	$options  = w3tc_qa_pagecache_normalize_probe_options( $options );
	$switched = false;

	if ( isset( $options['blog_id'] ) && \function_exists( 'is_multisite' ) && is_multisite() ) {
		w3tc_qa_pagecache_key_enter_blog_context( (int) $options['blog_id'] );
		$switched = true;
	}

	$saved = w3tc_qa_pagecache_key_bootstrap_request( $url, $options );
	w3tc_qa_pagecache_key_reset_grabber_singleton();

	$grabber   = w3tc_qa_pagecache_key_grabber_for_url( $url );
	$extension = w3tc_qa_pagecache_key_get_extension( $grabber );

	if ( array_key_exists( 'user_agent', $options ) ) {
		$extension['useragent'] = w3tc_qa_pagecache_mobile_useragent_group( $options['user_agent'] );
	}

	if ( ! empty( $options['extension'] ) && is_array( $options['extension'] ) ) {
		$extension = array_merge( $extension, $options['extension'] );
	}

	if ( array_key_exists( 'compression', $options ) ) {
		$extension['compression'] = $options['compression'];
	}

	$cache_group = $extension['group'];
	$extracted   = w3tc_qa_pagecache_key_extract( $grabber, $extension, false );
	$page_key    = $extracted['page_key'];
	$page_group  = $extracted['page_group'];

	if ( ! empty( $options['page_key_postfix'] ) ) {
		$page_key = w3tc_qa_pagecache_key_apply_postfix( $page_key, $options['page_key_postfix'] );
	}

	w3tc_qa_pagecache_key_restore_request( $saved );

	$enhanced_path = '';
	$config        = Dispatcher::config();

	if ( 'file_generic' === $config->get_string( 'pgcache.engine' ) ) {
		$enhanced_path = w3tc_qa_pagecache_key_enhanced_path( trailingslashit( WP_CONTENT_DIR ), $page_key );
	}

	$result = array(
		'page_key'      => $page_key,
		'page_group'    => $page_group,
		'cache_group'   => $cache_group,
		'enhanced_path' => $enhanced_path,
	);

	if ( $switched ) {
		w3tc_qa_pagecache_key_leave_blog_context();
	}

	return $result;
}

/**
 * Builds the Disk Enhanced filesystem path under a wp-content directory.
 *
 * @since 2.10.0
 *
 * @param string $wp_content_path Absolute wp-content path with trailing slash.
 * @param string $page_key        Page cache key returned by w3tc_qa_pagecache_key_resolve().
 * @return string
 */
function w3tc_qa_pagecache_key_enhanced_path( $wp_content_path, $page_key ) {
	return $wp_content_path . 'cache/page_enhanced/' . $page_key;
}
