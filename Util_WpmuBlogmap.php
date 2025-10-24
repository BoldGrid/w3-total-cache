<?php
/**
 * File: Util_WpmuBlogmap.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Util_WpmuBlogmap
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class Util_WpmuBlogmap {
	/**
	 * Content of files by filename
	 *
	 * @var array
	 * @static
	 */
	private static $content_by_filename = array();

	/**
	 * Generates a unique blog map filename based on the blog's home URL.
	 *
	 * This method determines the appropriate blog map file path and name by hashing the blog's home URL. The file path structure
	 * varies depending on whether `W3TC_BLOG_LEVELS` is defined. If defined, the file path includes subdirectories based on the
	 * hashed URL to prevent file collisions in multi-site environments.
	 *
	 * @param string $blog_home_url The home URL of the blog (e.g., 'https://example.com').
	 *
	 * @return string The full file path and name for the blog map file.
	 */
	public static function blogmap_filename_by_home_url( $blog_home_url ) {
		if ( ! defined( 'W3TC_BLOG_LEVELS' ) ) {
			return W3TC_CACHE_BLOGMAP_FILENAME;
		} else {
			$filename = dirname( W3TC_CACHE_BLOGMAP_FILENAME ) . '/' . basename( W3TC_CACHE_BLOGMAP_FILENAME, '.json' ) . '/';

			$s = md5( $blog_home_url );
			for ( $n = 0; $n < W3TC_BLOG_LEVELS; $n++ ) {
				$filename .= substr( $s, $n, 1 ) . '/';
			}

			return $filename . basename( W3TC_CACHE_BLOGMAP_FILENAME );
		}
	}

	/**
	 * Retrieves data for the current blog based on its host and URL structure.
	 *
	 * This method attempts to identify the current blog in a WordPress multisite network using either a subdomain or subdirectory-based
	 * configuration. If the blog is not found, it registers the blog's host or URL to be added to the blog map.
	 *
	 * @return array|null The blog data if found, or null if the blog is not registered.
	 *
	 * @throws Exception If errors occur during environment or data retrieval.
	 *
	 * @details
	 * - **Subdomain Configuration**: Tries to retrieve blog data using the host.
	 * - **Subdirectory Configuration**: Iteratively checks parent directories in the URL path.
	 * - **Global Registration**: Sets `$GLOBALS['w3tc_blogmap_register_new_item']` to register the blog if it cannot be found in the map.
	 */
	public static function get_current_blog_data() {
		$host = Util_Environment::host();

		// subdomain.
		if ( Util_Environment::is_wpmu_subdomain() ) {
			$blog_data = self::try_get_current_blog_data( $host );
			if ( is_null( $blog_data ) ) {
				$GLOBALS['w3tc_blogmap_register_new_item'] = $host;
			}

			return $blog_data;
		} else {
			// try subdir blog.
			$url = $host . ( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '' ); // phpcs:ignore
			$pos = strpos( $url, '?' );
			if ( false !== $pos ) {
				$url = substr( $url, 0, $pos );
			}

			$url       = rtrim( $url, '/' );
			$start_url = $url;

			for ( ;; ) {
				$blog_data = self::try_get_current_blog_data( $url );
				if ( ! is_null( $blog_data ) ) {
					return $blog_data;
				}

				$pos = strrpos( $url, '/' );
				if ( false === $pos ) {
					break;
				}

				$url = rtrim( substr( $url, 0, $pos ), '/' );
			}

			$GLOBALS['w3tc_blogmap_register_new_item'] = $start_url;

			return null;
		}
	}

	/**
	 * Attempts to retrieve blog data for a given URL from the blog map.
	 *
	 * This method checks if the blog data corresponding to the provided URL exists in the cached data or retrieves it from the
	 * appropriate file. If the blog data is found, it returns the relevant information; otherwise, it returns `null`.
	 *
	 * @param string $url The home URL of the blog to look up.
	 *
	 * @return array|null The blog data if found, or null if the blog is not registered.
	 *
	 * @throws Exception If file reading or decoding errors occur.
	 *
	 * @details
	 * - **File Caching**: The method uses `self::$content_by_filename` to cache previously read blog map files, reducing redundant
	 *                     file operations.
	 * - **File Retrieval**: If the data is not cached, it attempts to read the file using the filename generated from
	 *                       `blogmap_filename_by_home_url`.
	 * - **JSON Decoding**: Reads and decodes JSON data from the file if it exists. Invalid or malformed JSON will result in `null`.
	 * - **Blog Match**: Checks if the URL exists in the retrieved blog map data. Returns the associated data if a match is found.
	 */
	public static function try_get_current_blog_data( $url ) {
		$filename = self::blogmap_filename_by_home_url( $url );

		if ( isset( self::$content_by_filename[ $filename ] ) ) {
			$blog_data = self::$content_by_filename[ $filename ];
		} else {
			$blog_data = null;

			if ( file_exists( $filename ) ) {
				$data      = file_get_contents( $filename );
				$blog_data = @json_decode( $data, true );

				if ( is_array( $blog_data ) ) {
					self::$content_by_filename[ $filename ] = $blog_data;
				}
			}
		}

		if ( isset( $blog_data[ $url ] ) ) {
			return $blog_data[ $url ];
		}

		return null;
	}

	/**
	 * Registers a new blog in the blog map.
	 *
	 * This method adds the current blog to the blog map file if it is not already registered. The blog map associates blog URLs with their
	 * unique identifiers, supporting both subdomain and subdirectory WordPress multisite installations.
	 *
	 * @param object $config The configuration object containing settings for the current operation. Specifically, the `common.force_master`
	 *                       setting is used to determine the blog type.
	 *
	 * @return bool Returns `true` if the blog was successfully registered, or `false` if the blog was already registered or an error occurred.
	 *
	 * @details
	 * - **Multisite Handling**: - Detects whether the multisite is using subdomains or subdirectories to determine the home URL of
	 *                             the blog to register.
	 * - **Validation**:         - Ensures that the URL and blog data conform to expected formats and sanitizes the input.
	 * - **File Operations**:    - Reads the existing blog map file if it exists. If the file doesn’t exist, it initializes a new empty map.
	 *                           - Uses `file_put_contents_atomic` for safe and atomic file writes.
	 * - **Caching**:            - Clears the cached file content in `self::$content_by_filename` to ensure consistency.
	 * - **Error Handling**:     - Catches exceptions during file operations and returns `false` in case of errors.
	 *
	 * @throws \Exception If file operations fail and are not caught by internal error handling.
	 */
	public static function register_new_item( $config ) {
		if ( ! isset( $GLOBALS['current_blog'] ) ) {
			return false;
		}

		// Find blog_home_url.
		if ( Util_Environment::is_wpmu_subdomain() ) {
			$blog_home_url = $GLOBALS['w3tc_blogmap_register_new_item'];
		} else {
			$home_url = rtrim( get_home_url(), '/' );
			if ( 'http://' === substr( $home_url, 0, 7 ) ) {
				$home_url = substr( $home_url, 7 );
			} elseif ( 'https://' === substr( $home_url, 0, 8 ) ) {
				$home_url = substr( $home_url, 8 );
			}

			if ( substr( $GLOBALS['w3tc_blogmap_register_new_item'], 0, strlen( $home_url ) ) === $home_url ) {
				$blog_home_url = $home_url;
			} else {
				$blog_home_url = $GLOBALS['w3tc_blogmap_register_new_item'];
			}
		}

		// Write contents.
		$filename = self::blogmap_filename_by_home_url( $blog_home_url );

		if ( ! @file_exists( $filename ) ) {
			$blog_ids = array();
		} else {
			$data     = @file_get_contents( $filename );
			$blog_ids = @json_decode( $data, true );
			if ( ! is_array( $blog_ids ) ) {
				$blog_ids = array();
			}
		}

		if ( isset( $blog_ids[ $blog_home_url ] ) ) {
			return false;
		}

		$data                       = $config->get_boolean( 'common.force_master' ) ? 'm' : 'c';
		$blog_home_url              = preg_replace( '/[^a-zA-Z0-9\+\.%~!:()\/\-\_]/', '', $blog_home_url );
		$blog_ids[ $blog_home_url ] = $data . $GLOBALS['current_blog']->blog_id;

		$data = json_encode( $blog_ids );

		try {
			Util_File::file_put_contents_atomic( $filename, $data );
		} catch ( \Exception $ex ) {
			return false;
		}

		unset( self::$content_by_filename[ $filename ] );
		unset( $GLOBALS['w3tc_blogmap_register_new_item'] );

		return true;
	}
}
