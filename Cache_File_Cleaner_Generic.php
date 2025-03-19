<?php
/**
 * File: Cache_File_Cleaner_Generic.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cache_File_Cleaner_Generic
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */
class Cache_File_Cleaner_Generic extends Cache_File_Cleaner {
	/**
	 * Number of items processed
	 *
	 * @var integer
	 */
	private $processed_count = 0;

	/**
	 * Cache expire time
	 *
	 * @var int
	 */
	private $_expire = 0;

	/**
	 * Minimum valid time
	 *
	 * @var int
	 */
	private $time_min_valid = -1;

	/**
	 * Old file minimum valid time
	 *
	 * @var int
	 */
	private $old_file_time_min_valid = -1;

	/**
	 * PHP5-style constructor
	 *
	 * @param array $config Config.
	 *
	 * @return void
	 */
	public function __construct( $config = array() ) {
		parent::__construct( $config );

		$this->_expire = ( isset( $config['expire'] ) ? (int) $config['expire'] : 0 );

		if ( ! $this->_expire ) {
			$this->_expire = 0;
		} elseif ( $this->_expire > W3TC_CACHE_FILE_EXPIRE_MAX ) {
			$this->_expire = W3TC_CACHE_FILE_EXPIRE_MAX;
		}

		if ( ! empty( $config['time_min_valid'] ) ) {
			$this->time_min_valid          = $config['time_min_valid'];
			$this->old_file_time_min_valid = $config['time_min_valid'];
		} elseif ( $this->_expire > 0 ) {
			$this->time_min_valid          = time() - $this->_expire;
			$this->old_file_time_min_valid = time() - $this->_expire * 5;
		}
	}

	/**
	 * Clean
	 *
	 * @param string $path   Path.
	 * @param bool   $remove Remove flag.
	 *
	 * @return void
	 */
	public function _clean( $path, $remove = false ) {
		$dir = false;
		if ( is_dir( $path ) ) {
			$dir = @opendir( $path );
		}

		if ( $dir ) {
			while ( ( $entry = @readdir( $dir ) ) !== false ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
				if ( '.' === $entry || '..' === $entry ) {
					continue;
				}

				$full_path = $path . DIRECTORY_SEPARATOR . $entry;

				foreach ( $this->_exclude as $mask ) {
					if ( fnmatch( $mask, basename( $entry ) ) ) {
						continue 2;
					}
				}

				if ( @is_dir( $full_path ) ) {
					$this->_clean( $full_path );
				} else {
					$this->_clean_file( $entry, $full_path );
				}
			}

			@closedir( $dir );
			if ( $this->is_empty_dir( $path ) ) {
				@rmdir( $path );
			}
		}
	}

	/**
	 * Clean file
	 *
	 * @param string $entry     Entry.
	 * @param string $full_path Full path.
	 *
	 * @return void
	 */
	public function _clean_file( $entry, $full_path ) {
		if ( '_old' === substr( $entry, -4 ) ) {
			if ( ! $this->is_old_file_valid( $full_path ) ) {
				$this->processed_count++;
				@unlink( $full_path );
			}
		} elseif ( ! $this->is_valid( $full_path ) ) {
			$old_entry_path = $full_path . '_old';
			$this->processed_count++;
			if ( ! @rename( $full_path, $old_entry_path ) ) {
				// if we can delete old entry - do second attempt to store in old-entry file.
				if ( @unlink( $old_entry_path ) ) {
					if ( ! @rename( $full_path, $old_entry_path ) ) {
						// last attempt - just remove entry.
						@unlink( $full_path );
					}
				}
			}
		}
	}

	/**
	 * Checks if file is valid
	 *
	 * @param string $file File.
	 *
	 * @return bool
	 */
	public function is_valid( $file ) {
		if ( $this->time_min_valid > 0 && file_exists( $file ) ) {
			$ftime = @filemtime( $file );
			if ( $ftime && $ftime >= $this->time_min_valid ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if old file is valid
	 *
	 * @param string $file File.
	 *
	 * @return bool
	 */
	public function is_old_file_valid( $file ) {
		if ( $this->old_file_time_min_valid > 0 && file_exists( $file ) ) {
			$ftime = @filemtime( $file );

			if ( $ftime && $ftime >= $this->old_file_time_min_valid ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if directory is empty
	 *
	 * @param string $dir Directory.
	 *
	 * @return bool
	 */
	public function is_empty_dir( $dir ) {
		$files = @scandir( $dir );
		return $files && count( $files ) <= 2;
	}
}
