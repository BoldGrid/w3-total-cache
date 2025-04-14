<?php
/**
 * File: Cache_File.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cache_File
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class Cache_File extends Cache_Base {
	/**
	 * Path to cache dir
	 *
	 * @var string
	 */
	protected $_cache_dir = '';

	/**
	 * Directory to flush
	 *
	 * @var string
	 */
	protected $_flush_dir = '';
	/**
	 * Exclude files
	 *
	 * @var array
	 */
	protected $_exclude = array();

	/**
	 * Flush time limit
	 *
	 * @var int
	 */
	protected $_flush_timelimit = 0;

	/**
	 * File locking
	 *
	 * @var boolean
	 */
	protected $_locking = false;

	/**
	 * If path should be generated based on wp_hash
	 *
	 * @var bool
	 */
	protected $_use_wp_hash = false;

	/**
	 * Constructs the Cache_File instance.
	 *
	 * Initializes the cache file settings using the provided configuration array. Sets up the cache directory, exclusions, flush
	 * time limits, locking behavior, and flushing directory based on the configuration. If specific configurations are not provided,
	 * defaults are determined using environment utilities.
	 *
	 * @param array $config {
	 *     Optional. Configuration options for the cache file.
	 *
	 *     @type string $cache_dir        The directory where cache files are stored.
	 *     @type array  $exclude          List of items to exclude from caching.
	 *     @type int    $flush_timelimit  The time limit for flushing the cache.
	 *     @type bool   $locking          Whether to use locking for cache file access.
	 *     @type string $flush_dir        The directory where cache flush operations occur.
	 *     @type bool   $use_wp_hash     Whether to use WordPress-specific hashing for cache files.
	 * }
	 *
	 * @return void
	 */
	public function __construct( $config = array() ) {
		parent::__construct( $config );
		if ( isset( $config['cache_dir'] ) ) {
			$this->_cache_dir = trim( $config['cache_dir'] );
		} else {
			$this->_cache_dir = Util_Environment::cache_blog_dir( $config['section'], $config['blog_id'] );
		}

		$this->_exclude         = isset( $config['exclude'] ) ? (array) $config['exclude'] : array();
		$this->_flush_timelimit = isset( $config['flush_timelimit'] ) ? (int) $config['flush_timelimit'] : 180;
		$this->_locking         = isset( $config['locking'] ) ? (bool) $config['locking'] : false;

		if ( isset( $config['flush_dir'] ) ) {
			$this->_flush_dir = $config['flush_dir'];
		} elseif ( $config['blog_id'] <= 0 && ! isset( $config['cache_dir'] ) ) {
			// Clear whole section if we operate on master cache and in a mode when cache_dir not strictly specified.
			$this->_flush_dir = Util_Environment::cache_dir( $config['section'] );
		} else {
			$this->_flush_dir = $this->_cache_dir;
		}

		if ( isset( $config['use_wp_hash'] ) && $config['use_wp_hash'] ) {
			$this->_use_wp_hash = true;
		}
	}

	/**
	 * Adds a value to the cache if it does not already exist.
	 *
	 * Attempts to retrieve the value using the specified key and group. If the key does not exist in the cache, the value is
	 * added with the specified expiration time.
	 *
	 * @param string $key    The cache key.
	 * @param mixed  $value  The variable to store in the cache.
	 * @param int    $expire Optional. Time in seconds until the cache entry expires. Default is 0 (no expiration).
	 * @param string $group  Optional. The group to which the cache belongs. Default is an empty string.
	 *
	 * @return bool True if the value was added, false if it already exists or on failure.
	 */
	public function add( $key, &$value, $expire = 0, $group = '' ) {
		if ( $this->get( $key, $group ) === false ) {
			return $this->set( $key, $value, $expire, $group );
		}

		return false;
	}

	/**
	 * Stores the value in the cache with the specified expiration time. The data is serialized and written to a file with a
	 * header indicating the expiration time. File locking can be used for write operations if enabled.
	 *
	 * @param string $key        An MD5 of the DB query.
	 * @param mixed  $content    Data to be cached.
	 * @param int    $expiration Optional. Time in seconds until the cache entry expires. Default is 0 (no expiration).
	 * @param string $group      Optional. The group to which the cache belongs. Default is an empty string.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function set( $key, $content, $expiration = 0, $group = '' ) {
		/**
		 * Get the file pointer of the cache file.
		 * The $key is transformed to a storage key (format "w3tc_INSTANCEID_HOST_BLOGID_dbcache_HASH").
		 * The file path is in the format: CACHEDIR/db/BLOGID/GROUP/[0-9a-f]{3}/[0-9a-f]{3}/[0-9a-f]{32}.
		 */
		$fp = $this->fopen_write( $key, $group, 'wb' );

		if ( ! $fp ) {
			return false;
		}

		if ( $this->_locking ) {
			@flock( $fp, LOCK_EX );
		}

		if ( $expiration <= 0 || $expiration > W3TC_CACHE_FILE_EXPIRE_MAX ) {
			$expiration = W3TC_CACHE_FILE_EXPIRE_MAX;
		}

		$expires_at = time() + $expiration;
		@fputs( $fp, pack( 'L', $expires_at ) );
		@fputs( $fp, '<?php exit; ?>' );
		@fputs( $fp, @serialize( $content ) );
		@fclose( $fp );

		if ( $this->_locking ) {
			@flock( $fp, LOCK_UN );
		}

		return true;
	}

	/**
	 * Retrieves a value from the cache along with its old state information.
	 *
	 * Fetches the cached value for the specified key and group. If the cache entry has expired but old data usage is enabled, the
	 * expired data can still be returned while updating its expiration time temporarily.
	 *
	 * @param string $key    The cache key.
	 * @param string $group  Optional. The group to which the cache belongs. Default is an empty string.
	 *
	 * @return array An array containing the unserialized cached data (or null if not found) and a boolean indicating if old data was used.
	 */
	public function get_with_old( $key, $group = '' ) {
		list( $data, $has_old_data ) = $this->_get_with_old_raw( $key, $group );
		if ( ! empty( $data ) ) {
			$data_unserialized = @unserialize( $data );
		} else {
			$data_unserialized = $data;
		}

		return array( $data_unserialized, $has_old_data );
	}

	/**
	 * Retrieves the raw cached data and expiration status for a key.
	 *
	 * Reads the cached data file to determine the expiration time and fetches the data if it is valid. If the data is expired and
	 * old data usage is enabled, the expiration time is updated temporarily and the expired data is returned.
	 *
	 * @param string $key    The cache key.
	 * @param string $group  Optional. The group to which the cache belongs. Default is an empty string.
	 *
	 * @return array An array containing the raw cached data (or null if not found) and a boolean indicating if old data was used.
	 */
	private function _get_with_old_raw( $key, $group = '' ) {
		$has_old_data = false;

		$storage_key = $this->get_item_key( $key );

		$path = $this->_cache_dir . DIRECTORY_SEPARATOR . $this->_get_path( $storage_key, $group );
		if ( ! is_readable( $path ) ) {
			return array( null, $has_old_data );
		}

		$fp = @fopen( $path, 'rb' );
		if ( ! $fp || 4 > filesize( $path ) ) {
			return array( null, $has_old_data );
		}

		if ( $this->_locking ) {
			@flock( $fp, LOCK_SH );
		}

		$expires_at = @fread( $fp, 4 );
		$data       = null;

		if ( false !== $expires_at ) {
			list( , $expires_at ) = @unpack( 'L', $expires_at );

			if ( time() > $expires_at ) {
				if ( $this->_use_expired_data ) {
					// update expiration so other threads will use old data.
					$fp2 = @fopen( $path, 'cb' );

					if ( $fp2 ) {
						@fputs( $fp2, pack( 'L', time() + 30 ) );
						@fclose( $fp2 );
					}
					$has_old_data = true;
				}
			} else {
				$data = '';

				while ( ! @feof( $fp ) ) {
					$data .= @fread( $fp, 4096 );
				}
				$data = substr( $data, 14 );
			}
		}

		if ( $this->_locking ) {
			@flock( $fp, LOCK_UN );
		}

		@fclose( $fp );

		return array( $data, $has_old_data );
	}

	/**
	 * Replaces an existing cache value with a new one.
	 *
	 * Updates the cache entry for the specified key and group if it already exists. If the key does not exist, no action is taken.
	 *
	 * @param string $key    The cache key.
	 * @param mixed  $value  The variable to store in the cache.
	 * @param int    $expire Optional. Time in seconds until the cache entry expires. Default is 0 (no expiration).
	 * @param string $group  Optional. The group to which the cache belongs. Default is an empty string.
	 *
	 * @return bool True if the value was replaced, false otherwise.
	 */
	public function replace( $key, &$value, $expire = 0, $group = '' ) {
		if ( false !== $this->get( $key, $group ) ) {
			return $this->set( $key, $value, $expire, $group );
		}

		return false;
	}

	/**
	 * Deletes a value from the cache.
	 *
	 * Removes the cache entry for the specified key and group. If "use expired data" is enabled, the expiration time of the cache
	 * entry is set to zero instead of deleting the file.
	 *
	 * @param string $key    The cache key.
	 * @param string $group  Optional. The group to which the cache belongs. Default is an empty string.
	 *
	 * @return bool True if the value was successfully deleted, false otherwise.
	 */
	public function delete( $key, $group = '' ) {
		$storage_key = $this->get_item_key( $key );

		$path = $this->_cache_dir . DIRECTORY_SEPARATOR . $this->_get_path( $storage_key, $group );

		if ( ! file_exists( $path ) ) {
			return true;
		}

		if ( $this->_use_expired_data ) {
			$fp = @fopen( $path, 'cb' );

			if ( $fp ) {
				if ( $this->_locking ) {
					@flock( $fp, LOCK_EX );
				}

				@fputs( $fp, pack( 'L', 0 ) ); // make it expired.
				@fclose( $fp );

				if ( $this->_locking ) {
					@flock( $fp, LOCK_UN );
				}

				return true;
			}
		}

		return @unlink( $path );
	}

	/**
	 * Performs a hard delete of a cache entry.
	 *
	 * Completely removes the cache file for the specified key and group without checking for expiration or other conditions.
	 *
	 * @param string $key    The cache key.
	 * @param string $group  Optional. The group to which the cache belongs. Default is an empty string.
	 *
	 * @return bool True if the file was successfully deleted, false otherwise.
	 */
	public function hard_delete( $key, $group = '' ) {
		$key  = $this->get_item_key( $key );
		$path = $this->_cache_dir . DIRECTORY_SEPARATOR . $this->_get_path( $key, $group );
		return @unlink( $path );
	}

	/**
	 * Flushes all cache entries or those belonging to a specific group.
	 *
	 * Deletes all files in the cache directory or a specific group subdirectory. If the group is "sitemaps", the flush is performed
	 * based on a regular expression defined in the configuration.
	 *
	 * @param string $group Optional. The group to flush. Default is an empty string.
	 *
	 * @return bool Always returns true.
	 */
	public function flush( $group = '' ) {
		@set_time_limit( $this->_flush_timelimit );

		if ( 'sitemaps' === $group ) {
			$config        = Dispatcher::config();
			$sitemap_regex = $config->get_string( 'pgcache.purge.sitemap_regex' );
			$this->_flush_based_on_regex( $sitemap_regex );
		} else {
			$flush_dir = $group ? $this->_cache_dir . DIRECTORY_SEPARATOR . $group . DIRECTORY_SEPARATOR : $this->_flush_dir;
			Util_File::emptydir( $flush_dir, $this->_exclude );
		}

		return true;
	}

	/**
	 * Retrieves an extension array for ahead-of-generation cache handling.
	 *
	 * Returns an array containing the current timestamp for cache generation purposes.
	 *
	 * @param string $group The cache group.
	 *
	 * @return array An array with the `before_time` key set to the current timestamp.
	 */
	public function get_ahead_generation_extension( $group ) {
		return array(
			'before_time' => time(),
		);
	}

	/**
	 * Flushes a cache group after ahead-of-generation processing.
	 *
	 * Performs any cleanup or flushing required for a cache group after an ahead-of-generation operation.
	 *
	 * @param string $group The cache group.
	 * @param array  $extension {
	 *     An extension array with generation metadata.
	 *
	 *     @type mixed $before_time The time before the generation.
	 * }
	 *
	 * @return void
	 */
	public function flush_group_after_ahead_generation( $group, $extension ) {
		$dir = $this->_flush_dir;
		$extension['before_time'];
	}

	/**
	 * Retrieves the last modified time of a cache file.
	 *
	 * Returns the modification time of the cache file for the specified key and group.
	 *
	 * @param string $key    The cache key.
	 * @param string $group  Optional. The group to which the cache belongs. Default is an empty string.
	 *
	 * @return int|false The file modification time as a Unix timestamp, or false if the file does not exist.
	 */
	public function mtime( $key, $group = '' ) {
		$path =
			$this->_cache_dir . DIRECTORY_SEPARATOR . $this->_get_path( $key, $group );

		if ( file_exists( $path ) ) {
			return @filemtime( $path );
		}

		return false;
	}

	/**
	 * Returns subpath for the cache file (format: [0-9a-f]{3}/[0-9a-f]{3}/[0-9a-f]{32}).
	 *
	 * Creates the file path for the cache file based on the key and group. A hash of the key is used to create subdirectories
	 * for organizational purposes.
	 *
	 * @param string $key   Storage key (format: "w3tc_INSTANCEID_HOST_BLOGID_dbcache_HASH").
	 * @param string $group Optional. The group to which the cache belongs. Default is an empty string.
	 *
	 * @return string The file path for the cache entry.
	 */
	public function _get_path( $key, $group = '' ) {
		if ( $this->_use_wp_hash && function_exists( 'wp_hash' ) ) {
			$hash = wp_hash( $key ); // Most common.
		} else {
			$hash = md5( $key ); // Less common, but still used in some cases.
		}

		return ( $group ? $group . DIRECTORY_SEPARATOR : '' ) . sprintf( '%s/%s/%s.php', substr( $hash, 0, 3 ), substr( $hash, 3, 3 ), $hash );
	}

	/**
	 * Calculates the size of the cache directory.
	 *
	 * Recursively calculates the total size and number of files in the cache directory. Stops processing if the timeout is exceeded.
	 *
	 * @param string $timeout_time The timeout timestamp.
	 *
	 * @return array An array containing the total size (`bytes`), the number of items (`items`), and whether a timeout occurred
	 *               (`timeout_occurred`).
	 */
	public function get_stats_size( $timeout_time ) {
		$size = array(
			'bytes'            => 0,
			'items'            => 0,
			'timeout_occurred' => false,
		);

		$size = $this->dirsize( $this->_cache_dir, $size, $timeout_time );

		return $size;
	}

	/**
	 * Recursively calculates the size of a directory.
	 *
	 * Iterates through all files and subdirectories within the specified directory to calculate the total size and count of items.
	 * Checks for timeouts every 1000 items.
	 *
	 * @param string $path         The directory path.
	 * @param array  $size         {
	 *     The size data array.
	 *
	 *     @type int $bytes             The total size of the directory in bytes.
	 *     @type int $items             The total number of items (files/subdirectories).
	 *     @type bool $timeout_occurred Flag indicating whether a timeout has occurred.
	 * }
	 * @param int    $timeout_time The timeout timestamp.
	 *
	 * @return array Updated size data.
	 */
	private function dirsize( $path, $size, $timeout_time ) {
		$dir = @opendir( $path );

		if ( $dir ) {
			$entry = @readdir( $dir );
			while ( ! $size['timeout_occurred'] && false !== $entry ) {
				if ( '.' === $entry || '..' === $entry ) {
					$entry = @readdir( $dir );
					continue;
				}

				$full_path = $path . DIRECTORY_SEPARATOR . $entry;

				if ( @is_dir( $full_path ) ) {
					$size = $this->dirsize( $full_path, $size, $timeout_time );
				} else {
					$size['bytes'] += @filesize( $full_path );

					// dont check time() for each file, quite expensive.
					++$size['items'];
					if ( 0 === $size['items'] % 1000 ) {
						$size['timeout_occurred'] |= ( time() > $timeout_time );
					}
				}

				$entry = @readdir( $dir );
			}

			@closedir( $dir );
		}

		return $size;
	}

	/**
	 * Sets a new value if the old value matches the current value.
	 *
	 * This method checks if the current value in the cache matches the provided old value. If they match, it sets the new value.
	 * Cannot guarantee atomicity due to potential file lock failures.
	 *
	 * @param string $key       Cache key.
	 * @param mixed  $old_value The expected current value.
	 * @param mixed  $new_value The value to set if the old value matches.
	 *
	 * @return bool True if the value was set, false otherwise.
	 */
	public function set_if_maybe_equals( $key, $old_value, $new_value ) {
		// Cant guarantee atomic action here, filelocks fail often.
		$value = $this->get( $key );
		if ( isset( $old_value['content'] ) && $value['content'] !== $old_value['content'] ) {
			return false;
		}

		return $this->set( $key, $new_value );
	}

	/**
	 * Increments a counter stored in the cache by a given value.
	 *
	 * This method appends the increment value to the counter file. If the value is 1, it stores it as 'x' for efficiency. Larger
	 * increments are stored as space-separated integers.
	 *
	 * @param string $key   Cache key.
	 * @param int    $value The increment value (must be non-zero).
	 *
	 * @return bool True on success, false on failure.
	 */
	public function counter_add( $key, $value ) {
		if ( 0 === $value ) {
			return true;
		}

		$fp = $this->fopen_write( $key, '', 'a' );
		if ( ! $fp ) {
			return false;
		}

		// use "x" to store increment, since it's most often case
		// and it will save 50% of size if only increments are used.
		if ( 1 === $value ) {
			@fputs( $fp, 'x' );
		} else {
			@fputs( $fp, ' ' . (int) $value );
		}

		@fclose( $fp );

		return true;
	}

	/**
	 * Sets a counter value in the cache.
	 *
	 * This method initializes a counter file with the provided value, along with an expiration time and a PHP exit directive to
	 * prevent execution.
	 *
	 * @param string $key   Cache key.
	 * @param int    $value The counter value to set.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function counter_set( $key, $value ) {
		$fp = $this->fopen_write( $key, '', 'wb' );
		if ( ! $fp ) {
			return false;
		}

		$expire     = W3TC_CACHE_FILE_EXPIRE_MAX;
		$expires_at = time() + $expire;

		@fputs( $fp, pack( 'L', $expires_at ) );
		@fputs( $fp, '<?php exit; ?>' );
		@fputs( $fp, (int) $value );
		@fclose( $fp );

		return true;
	}

	/**
	 * Retrieves the value of a counter from the cache.
	 *
	 * This method reads the counter file and calculates the total value by counting occurrences of 'x' and summing other stored values.
	 *
	 * @param string $key Cache key.
	 *
	 * @return int The counter value, or 0 if the key does not exist.
	 */
	public function counter_get( $key ) {
		list( $value, $old_data ) = $this->_get_with_old_raw( $key );
		if ( empty( $value ) ) {
			return 0;
		}

		$original_length = strlen( $value );
		$cut_value       = str_replace( 'x', '', $value );

		$count = $original_length - strlen( $cut_value );

		// values more than 1 are stored as <space>value.
		$a = explode( ' ', $cut_value );
		foreach ( $a as $counter_value ) {
			$count += (int) $counter_value;
		}

		return $count;
	}

	/**
	 * Open the cache file for writing and return the file pointer.
	 *
	 * Ensures the directory structure exists before attempting to open the file.
	 *
	 * @param string $key An MD5 of the DB query.
	 * @param string $group Cache group.
	 * @param string $mode File mode.  For example: 'wb' for write binary.
	 *
	 * @return resource|false File pointer on success, false on failure.
	 */
	private function fopen_write( $key, $group, $mode ) {
		// Get the storage key (format: "w3tc_INSTANCEID_HOST_BLOGID_dbcache_$key").
		$storage_key = $this->get_item_key( $key );

		// Get the subpath for the cache file (format: [0-9a-f]{3}/[0-9a-f]{3}/[0-9a-f]{32}).
		$sub_path = $this->_get_path( $storage_key, $group );

		// Ge the entire path of the cache file.
		$path = $this->_cache_dir . DIRECTORY_SEPARATOR . $sub_path;

		// Create the directory if it does not exist.
		$dir = dirname( $path );

		if ( ! @is_dir( $dir ) ) {
			if ( ! Util_File::mkdir_from( $dir, dirname( W3TC_CACHE_DIR ) ) ) {
				return false;
			}
		}

		// Open the cache file for writing.
		return @fopen( $path, $mode );
	}

	/**
	 * Flushes cache files matching a specific regex pattern.
	 *
	 * This method scans a directory and removes cache files that match the provided regular expression. Supports multisite setups.
	 *
	 * @since 2.7.1
	 *
	 * @param string $regex The regular expression pattern to match file names.
	 *
	 * @return void
	 */
	private function _flush_based_on_regex( $regex ) {
		if ( Util_Environment::is_wpmu() && ! Util_Environment::is_wpmu_subdomain() ) {
			$domain    = get_home_url();
			$parsed    = parse_url( $domain );
			$host      = $parsed['host'];
			$path      = isset( $parsed['path'] ) ? '/' . trim( $parsed['path'], '/' ) : '';
			$flush_dir = W3TC_CACHE_PAGE_ENHANCED_DIR . DIRECTORY_SEPARATOR . $host . $path;
		} else {
			$flush_dir = W3TC_CACHE_PAGE_ENHANCED_DIR . DIRECTORY_SEPARATOR . Util_Environment::host();
		}

		$dir = @opendir( $flush_dir );
		if ( $dir ) {
			$entry = @readdir( $dir );
			while ( false !== $entry ) {
				if ( '.' === $entry || '..' === $entry ) {
					$entry = @readdir( $dir );
					continue;
				}

				if ( preg_match( '~' . $regex . '~', basename( $entry ) ) ) {
					Util_File::rmdir( $flush_dir . DIRECTORY_SEPARATOR . $entry );
				}

				$entry = @readdir( $dir );
			}

			@closedir( $dir );
		}
	}
}
