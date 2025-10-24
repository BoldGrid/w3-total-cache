<?php
/**
 * File: SystemOpCache_Core.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class SystemOpCache_Core
 */
class SystemOpCache_Core {
	/**
	 * Checks if opcode caching is enabled.
	 *
	 * This method verifies if either Zend OPcache or APCu OPcache is enabled
	 * on the server.
	 *
	 * @return bool True if opcode caching is enabled, false otherwise.
	 */
	public function is_enabled() {
		return Util_Installed::opcache() || Util_Installed::apc_opcache();
	}

	/**
	 * Flushes the opcode cache.
	 *
	 * Clears the opcode cache for the currently enabled caching mechanism
	 * (either Zend OPcache or APCu OPcache). If no supported opcode caching
	 * mechanism is found, it returns false.
	 *
	 * @return bool True if the cache was successfully cleared, false otherwise.
	 */
	public function flush() {
		if ( Util_Installed::opcache() ) {
			return opcache_reset();
		} elseif ( Util_Installed::apc_opcache() ) {
			$result  = apc_clear_cache(); // that doesnt clear user cache.
			$result |= apc_clear_cache( 'opcode' ); // extra.
			return $result;
		}

		return false;
	}

	/**
	 * Flushes the opcode cache for a specific file.
	 *
	 * This method attempts to invalidate the opcode cache for a given file
	 * by first resolving its path and then using the appropriate caching
	 * function (e.g., `opcache_invalidate` or `apc_compile_file`). If the file
	 * cannot be resolved or the cache cannot be invalidated, it returns false.
	 *
	 * @param string $filename The relative or absolute path to the file.
	 *
	 * @return bool True if the file cache was successfully invalidated, false otherwise.
	 */
	public function flush_file( $filename ) {
		if ( file_exists( $filename ) ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
		} elseif ( file_exists( ABSPATH . $filename ) ) {
			$filename = ABSPATH . DIRECTORY_SEPARATOR . $filename;
		} elseif ( file_exists( WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $filename ) ) {
			$filename = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $filename;
		} elseif ( file_exists( WPINC . DIRECTORY_SEPARATOR . $filename ) ) {
			$filename = WPINC . DIRECTORY_SEPARATOR . $filename;
		} elseif ( file_exists( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $filename ) ) {
			$filename = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $filename;
		} else {
			return false;
		}

		if ( function_exists( 'opcache_invalidate' ) ) {
			return opcache_invalidate( $filename, true );
		} elseif ( function_exists( 'apc_compile_file' ) ) {
			return apc_compile_file( $filename );
		}

		return false;
	}
}
