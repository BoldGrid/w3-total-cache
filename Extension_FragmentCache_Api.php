<?php
/**
 * File: Extension_FragmentCache_Api.php
 *
 * @package W3TC
 */

defined( 'ABSPATH' ) || exit;
/**
 * Flushes a specific fragment cache group.
 *
 * @param string $fragment_group The name of the fragment cache group to flush.
 *
 * @return bool True if the group was successfully flushed, false otherwise.
 */
function w3tc_fragmentcache_flush_group( $fragment_group ) {
	$w3tc_o = \W3TC\Dispatcher::component( 'CacheFlush' );
	return $w3tc_o->fragmentcache_flush_group( $fragment_group );
}

/**
 * Flushes all fragment cache groups.
 *
 * @return bool True if all groups were successfully flushed, false otherwise.
 */
function w3tc_fragmentcache_flush() {
	$w3tc_o = \W3TC\Dispatcher::component( 'CacheFlush' );
	return $w3tc_o->fragmentcache_flush();
}

/**
 * Registers a fragment cache group with specific actions and expiration.
 *
 * phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
 *
 * @param string $w3tc_group      The name of the fragment cache group.
 * @param array  $actions    List of actions associated with the group.
 * @param int    $expiration The expiration time in seconds for the group.
 *
 * @return bool True if the group was successfully registered, false otherwise.
 */
function w3tc_register_fragment_group( $w3tc_group, $actions, $expiration ) {
	if ( ! is_int( $expiration ) ) {
		$expiration = (int) $expiration;
		trigger_error( __FUNCTION__ . ' needs expiration parameter to be an int.', E_USER_WARNING );
	}
	$w3tc_o = \W3TC\Dispatcher::component( 'Extension_FragmentCache_Core' );
	return $w3tc_o->register_group( $w3tc_group, $actions, $expiration );
}

/**
 * Registers a global fragment cache group with specific actions and expiration.
 *
 * phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
 *
 * @param string $w3tc_group      The name of the global fragment cache group.
 * @param array  $actions    List of actions associated with the global group.
 * @param int    $expiration The expiration time in seconds for the global group.
 *
 * @return bool True if the global group was successfully registered, false otherwise.
 */
function w3tc_register_fragment_global_group( $w3tc_group, $actions, $expiration ) {
	if ( ! is_int( $expiration ) ) {
		$expiration = (int) $expiration;
		trigger_error( __FUNCTION__ . ' needs expiration parameter to be an int.', E_USER_WARNING );
	}
	$w3tc_o = \W3TC\Dispatcher::component( 'Extension_FragmentCache_Core' );
	return $w3tc_o->register_global_group( $w3tc_group, $actions, $expiration );
}

/**
 * Starts capturing output for a fragment cache.
 *
 * @param string $id    The unique identifier for the fragment cache.
 * @param string $w3tc_group Optional. The fragment cache group name.
 * @param string $w3tc_hook  Optional. A hook name to apply after outputting the fragment.
 *
 * @return bool True if the fragment is already cached and output, false otherwise.
 */
function w3tc_fragmentcache_start( $id, $w3tc_group = '', $w3tc_hook = '' ) {
	$fragment = w3tc_fragmentcache_get( $id, $w3tc_group );
	if ( false === $fragment ) {
		w3tc_caching_fragment( $id, $w3tc_group );
		ob_start();
	} else {
		echo esc_html( $fragment );
		if ( $w3tc_hook ) {
			remove_all_filters( $w3tc_hook );
		}
		return true;
	}
	return false;
}

/**
 * Starts filtering and capturing output for a fragment cache.
 *
 * @param string $id    The unique identifier for the fragment cache.
 * @param string $w3tc_group Optional. The fragment cache group name.
 * @param string $w3tc_hook  Optional. A hook name to apply after outputting the fragment.
 * @param mixed  $w3tc_data  Optional. Data to use if the fragment is not cached.
 *
 * @return mixed The cached fragment or the provided data.
 */
function w3tc_fragmentcache_filter_start( $id, $w3tc_group = '', $w3tc_hook = '', $w3tc_data = null ) {
	w3tc_caching_fragment( $id, $w3tc_group );
	$fragment = w3tc_fragmentcache_get( $id, $w3tc_group );
	if ( false !== $fragment ) {
		if ( $w3tc_hook ) {
			remove_all_filters( $w3tc_hook );
		}
		return $fragment;
	}
	return $w3tc_data;
}

/**
 * Ends capturing output for a fragment cache and stores it.
 *
 * @param string  $id    The unique identifier for the fragment cache.
 * @param string  $w3tc_group Optional. The fragment cache group name.
 * @param boolean $debug Optional. Whether to include debugging information in the fragment.
 *
 * @return void
 */
function w3tc_fragmentcache_end( $id, $w3tc_group = '', $debug = false ) {
	if ( w3tc_is_caching_fragment( $id, $w3tc_group ) ) {
		$content = ob_get_contents();
		if ( $debug ) {
			$content = sprintf(
				"\r\n" . '<!-- fragment start (%s%s)-->' . "\r\n" . '%s' . "\r\n" . '<!-- fragment end (%1$s%2$s) cached at %s by W3 Total Cache expires in %d seconds -->' . "\r\n",
				$w3tc_group,
				$id,
				$content,
				date_i18n( 'Y-m-d H:i:s' ),
				1000
			);
		}
		w3tc_fragmentcache_store( $id, $w3tc_group, $content );
		ob_end_flush();
	}
}


/**
 * Ends filtering output for a fragment cache and stores it.
 *
 * @param string $id    The unique identifier for the fragment cache.
 * @param string $w3tc_group Optional. The fragment cache group name.
 * @param mixed  $w3tc_data  The data to store in the fragment cache.
 *
 * @return mixed The stored data.
 */
function w3tc_fragmentcache_filter_end( $id, $w3tc_group = '', $w3tc_data = null ) {
	if ( w3tc_is_caching_fragment( $id, $w3tc_group ) ) {
		w3tc_fragmentcache_store( $id, $w3tc_group, $w3tc_data );
	}
	return $w3tc_data;
}

/**
 * Stores content in a fragment cache.
 *
 * @param string $id      The unique identifier for the fragment cache.
 * @param string $w3tc_group   Optional. The fragment cache group name.
 * @param string $content The content to store in the fragment cache.
 *
 * @return void
 */
function w3tc_fragmentcache_store( $id, $w3tc_group = '', $content = '' ) {
	/* default expiration in a case its not catched by fc plugin */
	set_transient( "{$w3tc_group}{$id}", $content, 1000 );
}

/**
 * Retrieves content from a fragment cache.
 *
 * @param string $id    The unique identifier for the fragment cache.
 * @param string $w3tc_group Optional. The fragment cache group name.
 *
 * @return mixed The cached content or false if not found.
 */
function w3tc_fragmentcache_get( $id, $w3tc_group = '' ) {
	return get_transient( "{$w3tc_group}{$id}" );
}

/**
 * Flushes a specific fragment from the cache.
 *
 * @param string $id    The unique identifier for the fragment cache.
 * @param string $w3tc_group Optional. The fragment cache group name.
 *
 * @return void
 */
function w3tc_fragmentcache_flush_fragment( $id, $w3tc_group = '' ) {
	delete_transient( "{$w3tc_group}{$id}" );
}

/**
 * Checks if a fragment is currently being cached.
 *
 * @param string $id    The unique identifier for the fragment cache.
 * @param string $w3tc_group Optional. The fragment cache group name.
 *
 * @return bool True if the fragment is being cached, false otherwise.
 */
function w3tc_is_caching_fragment( $id, $w3tc_group = '' ) {
	global $w3tc_caching_fragment;
	return isset( $w3tc_caching_fragment[ "{$w3tc_group}{$id}" ] ) &&
		$w3tc_caching_fragment[ "{$w3tc_group}{$id}" ];
}

/**
 * Marks a fragment as being cached.
 *
 * @param string $id    The unique identifier for the fragment cache.
 * @param string $w3tc_group Optional. The fragment cache group name.
 *
 * @return void
 */
function w3tc_caching_fragment( $id, $w3tc_group = '' ) {
	global $w3tc_caching_fragment;
	$w3tc_caching_fragment[ "{$w3tc_group}{$id}" ] = true;
}
